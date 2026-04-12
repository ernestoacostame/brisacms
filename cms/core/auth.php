<?php
// FluxCMS - Authentication & Security

function session_start_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }
}

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2
    ]);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function login(string $username, string $password): bool {
    $config = cms_config();
    if (empty($config['admin_user']) || empty($config['admin_pass'])) return false;
    if ($username !== $config['admin_user']) return false;
    if (!verify_password($password, $config['admin_pass'])) {
        log_failed_attempt();
        return false;
    }
    session_start_secure();
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    reset_failed_attempts();
    return true;
}

function logout(): void {
    session_start_secure();
    $_SESSION = [];
    session_destroy();
}

function is_logged_in(): bool {
    session_start_secure();
    if (empty($_SESSION['logged_in'])) return false;
    // Session timeout: 4 hours
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
    $file = CACHE_PATH . '/failed_logins.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $data[$ip] = ($data[$ip] ?? 0) + 1;
    $data[$ip . '_time'] = time();
    file_put_contents($file, json_encode($data));
}

function reset_failed_attempts(): void {
    $file = CACHE_PATH . '/failed_logins.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    unset($data[$ip], $data[$ip . '_time']);
    file_put_contents($file, json_encode($data));
}

function is_rate_limited(): bool {
    $file = CACHE_PATH . '/failed_logins.json';
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
