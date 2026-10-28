<?php
// KutBlog - Authentication, User Roles & Security Engine
require_once __DIR__ . '/users.php';

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }
}

function hash_password(string $password): string {
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2
        ]);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Attempts primary login with username and password.
 * Returns:
 *   true           -> Login successful
 *   '2fa_required' -> Password valid, but 2FA token required to complete login
 *   false          -> Credentials incorrect or account blocked
 */
function login(string $username, string $password): bool|string {
    $username = trim($username);
    if (!$username || !$password) return false;

    // Retrieve user from user storage
    $user = get_user($username);

    // Fallback: migrate from config if not yet created in users storage
    if (!$user) {
        $config = function_exists('cms_config') ? cms_config() : [];
        if (!empty($config['admin_user']) && strcasecmp($username, $config['admin_user']) === 0) {
            if (verify_password($password, $config['admin_pass'] ?? '')) {
                // Auto-create user
                $user = [
                    'username' => $config['admin_user'],
                    'name'     => 'Administrador',
                    'email'    => 'admin@localhost',
                    'password' => $config['admin_pass'],
                    'role'     => 'admin',
                    'status'   => 'active',
                    'two_factor_enabled' => false,
                    'created_at' => date('c'),
                ];
                save_user($user);
            }
        }
    }

    if (!$user) {
        log_failed_attempt();
        return false;
    }

    // Check account status
    if (($user['status'] ?? 'active') !== 'active') {
        log_failed_attempt();
        return false;
    }

    // Verify password
    if (!verify_password($password, $user['password'] ?? '')) {
        log_failed_attempt();
        return false;
    }

    session_start_secure();

    // Check if 2FA is enabled for this user
    if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
        $_SESSION['2fa_pending_user'] = $user['username'];
        $_SESSION['2fa_pending_time'] = time();
        return '2fa_required';
    }

    // Direct login without 2FA
    $_SESSION['logged_in']  = true;
    $_SESSION['username']   = $user['username'];
    $_SESSION['user_role']  = $user['role'] ?? 'admin';
    $_SESSION['login_time'] = time();
    unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_pending_time']);

    $user['last_login'] = date('c');
    save_user($user);
    reset_failed_attempts();
    return true;
}

/**
 * Completes login by verifying 2FA TOTP code or backup recovery code.
 */
function login_verify_2fa(string $code): bool {
    session_start_secure();
    $username = $_SESSION['2fa_pending_user'] ?? null;
    $pendingTime = $_SESSION['2fa_pending_time'] ?? 0;

    // Timeout: 5 minutes to complete 2FA challenge
    if (!$username || time() - $pendingTime > 300) {
        unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_pending_time']);
        return false;
    }

    $user = get_user($username);
    if (!$user) return false;

    $secret = $user['two_factor_secret'] ?? '';
    $valid = false;

    // Check 6-digit TOTP
    if ($secret && totp_verify($secret, $code)) {
        $valid = true;
    } elseif (totp_verify_recovery_code($user, $code)) {
        // Recovery code used
        $valid = true;
    }

    if ($valid) {
        $_SESSION['logged_in']  = true;
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_role']  = $user['role'] ?? 'admin';
        $_SESSION['login_time'] = time();
        unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_pending_time']);

        $user['last_login'] = date('c');
        save_user($user);
        reset_failed_attempts();
        return true;
    }

    log_failed_attempt();
    return false;
}

function logout(): void {
    session_start_secure();
    $_SESSION = [];
    session_destroy();
}

function is_logged_in(): bool {
    session_start_secure();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) return false;
    // Session timeout: 4 hours of inactivity
    if (time() - ($_SESSION['login_time'] ?? 0) > 14400) {
        logout();
        return false;
    }
    return true;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url() . '/admin/login.php');
        exit;
    }
}

function require_permission(string $capability): void {
    require_login();
    if (!user_can($capability)) {
        http_response_code(403);
        exit('Acceso denegado: no tienes permisos para realizar esta acción.');
    }
}

function generate_csrf(): string {
    session_start_secure();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    session_start_secure();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function log_failed_attempt(): void {
    $dir = defined('CACHE_PATH') ? CACHE_PATH : dirname(__DIR__) . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/failed_logins.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $data[$ip] = ($data[$ip] ?? 0) + 1;
    $data[$ip . '_time'] = time();
    @file_put_contents($file, json_encode($data));
}

function reset_failed_attempts(): void {
    $dir = defined('CACHE_PATH') ? CACHE_PATH : dirname(__DIR__) . '/cache';
    $file = $dir . '/failed_logins.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    unset($data[$ip], $data[$ip . '_time']);
    @file_put_contents($file, json_encode($data));
}

function is_rate_limited(): bool {
    $dir = defined('CACHE_PATH') ? CACHE_PATH : dirname(__DIR__) . '/cache';
    $file = $dir . '/failed_logins.json';
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts = $data[$ip] ?? 0;
    $last_time = $data[$ip . '_time'] ?? 0;
    // Reset after 15 minutes
    if (time() - $last_time > 900) return false;
    return $attempts >= 5;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitize_filename(string $name): string {
    $name = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '-', $name));
    return preg_replace('/-+/', '-', trim($name, '-'));
}
