<?php
// BrisaCMS - User Management & 2FA Engine (RFC 6238 TOTP)

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
if (!defined('CONTENT_PATH')) define('CONTENT_PATH', ROOT_PATH . '/content');
define('USERS_PATH', CONTENT_PATH . '/users');

/**
 * Initializes users storage and seeds the initial admin user from config.json if needed.
 */
function users_init(): void {
    if (!is_dir(USERS_PATH)) {
        @mkdir(USERS_PATH, 0755, true);
    }

    $htaccess = USERS_PATH . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
    }

    // Check if any user files exist
    $files = glob(USERS_PATH . '/*.json');
    if (empty($files)) {
        $config = function_exists('cms_config') ? cms_config() : [];
        $admin_user = $config['admin_user'] ?? 'admin';
        $admin_pass = $config['admin_pass'] ?? '';

        if (!$admin_pass && function_exists('hash_password')) {
            $admin_pass = hash_password('admin1234');
        }

        $default_admin = [
            'username'           => $admin_user,
            'name'               => 'Administrador',
            'email'              => 'admin@' . (parse_url($config['base_url'] ?? 'http://localhost', PHP_URL_HOST) ?: 'localhost'),
            'password'           => $admin_pass,
            'role'               => 'admin',
            'status'             => 'active',
            'bio'                => 'Administrador principal del sitio.',
            'avatar'             => '',
            'two_factor_enabled' => false,
            'two_factor_secret'  => '',
            'recovery_codes'     => [],
            'created_at'         => date('c'),
            'updated_at'         => date('c'),
            'last_login'         => date('c'),
        ];
        file_put_contents(USERS_PATH . '/' . sanitize_user_filename($admin_user) . '.json', json_encode($default_admin, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function sanitize_user_filename(string $username): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $username)) ?: 'user';
}

/**
 * Retrieves a user by username.
 */
function get_user(string $username): ?array {
    users_init();
    $file = USERS_PATH . '/' . sanitize_user_filename($username) . '.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/**
 * Lists all registered users.
 */
function list_users(): array {
    users_init();
    $files = glob(USERS_PATH . '/*.json');
    $users = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['username'])) {
            $users[] = $data;
        }
    }
    usort($users, function($a, $b) {
        if (($a['role'] ?? '') === 'admin' && ($b['role'] ?? '') !== 'admin') return -1;
        if (($a['role'] ?? '') !== 'admin' && ($b['role'] ?? '') === 'admin') return 1;
        return strcasecmp($a['username'] ?? '', $b['username'] ?? '');
    });
    return $users;
}

/**
 * Saves or updates a user.
 */
function save_user(array $user_data): bool {
    users_init();
    $username = trim($user_data['username'] ?? '');
    if (!$username) return false;

    $filename = sanitize_user_filename($username) . '.json';
    $path = USERS_PATH . '/' . $filename;

    $existing = get_user($username) ?? [];
    $data = array_merge($existing, $user_data);

    $data['username'] = $username;
    $data['updated_at'] = date('c');
    if (empty($data['created_at'])) $data['created_at'] = date('c');

    return (bool)file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Deletes a user by username.
 */
function delete_user(string $username): bool {
    users_init();
    // Cannot delete the last admin
    $user = get_user($username);
    if (!$user) return false;

    if (($user['role'] ?? '') === 'admin') {
        $admins = array_filter(list_users(), fn($u) => ($u['role'] ?? '') === 'admin');
        if (count($admins) <= 1) {
            return false;
        }
    }

    $file = USERS_PATH . '/' . sanitize_user_filename($username) . '.json';
    if (file_exists($file)) {
        return @unlink($file);
    }
    return false;
}

/**
 * Retrieves the currently logged-in user profile.
 */
function current_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        if (function_exists('session_start_secure')) {
            session_start_secure();
        } else {
            @session_start();
        }
    }
    $username = $_SESSION['username'] ?? null;
    if (!$username) return null;
    return get_user($username);
}

/**
 * Checks role-based capability.
 */
function user_can(string $capability, ?array $user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;

    $role = $user['role'] ?? 'author';

    // Admins can do everything
    if ($role === 'admin') return true;

    return match ($capability) {
        'manage_settings', 'manage_users', 'manage_plugins', 'manage_themes', 'delete_others_posts' => false,
        'publish_articles', 'edit_others_articles', 'upload_media', 'delete_media' => in_array($role, ['admin', 'editor']),
        'create_articles', 'edit_own_articles', 'save_drafts' => in_array($role, ['admin', 'editor', 'author']),
        default => false,
    };
}

// ═════════════════════════════════════════════════════════════════════════════
// ── 2FA / TOTP ENGINE (RFC 6238) ─────────────────────────────────────────────
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Generates a standard Base32 TOTP secret.
 */
function totp_generate_secret(int $length = 16): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($bytes[$i]) % 32];
    }
    return $secret;
}

/**
 * Base32 decode helper.
 */
function totp_base32_decode(string $b32): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(str_replace('=', '', trim($b32)));
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';
    $len = strlen($b32);

    for ($i = 0; $i < $len; $i++) {
        $val = strpos($chars, $b32[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

/**
 * Calculates current 6-digit TOTP code for a secret.
 */
function totp_calc(string $secret, ?int $time = null): string {
    $timeSlice = (int)floor(($time ?? time()) / 30);
    $secretBytes = totp_base32_decode($secret);
    $data = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $data, $secretBytes, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $unpacked = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string)($unpacked % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Verifies a 6-digit TOTP code with time discrepancy allowance.
 */
function totp_verify(string $secret, string $code, int $discrepancy = 1): bool {
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^[0-9]{6}$/', $code)) return false;

    $currentTime = time();
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calcTime = $currentTime + ($i * 30);
        if (hash_equals(totp_calc($secret, $calcTime), $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Generates an array of secure one-time backup recovery codes.
 */
function totp_generate_recovery_codes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $part1 = strtoupper(bin2hex(random_bytes(2)));
        $part2 = strtoupper(bin2hex(random_bytes(2)));
        $codes[] = $part1 . '-' . $part2;
    }
    return $codes;
}

/**
 * Verifies and consumes a backup recovery code.
 */
function totp_verify_recovery_code(array &$user, string $code): bool {
    $code = strtoupper(trim(str_replace(' ', '', $code)));
    $recovery_codes = $user['recovery_codes'] ?? [];
    if (empty($recovery_codes)) return false;

    foreach ($recovery_codes as $idx => $entry) {
        $storedCode = is_array($entry) ? ($entry['code'] ?? '') : $entry;
        $used = is_array($entry) ? ($entry['used'] ?? false) : false;

        if (!$used && hash_equals(strtoupper($storedCode), $code)) {
            // Mark as used
            if (is_array($entry)) {
                $user['recovery_codes'][$idx]['used'] = true;
                $user['recovery_codes'][$idx]['used_at'] = date('c');
            } else {
                $user['recovery_codes'][$idx] = ['code' => $storedCode, 'used' => true, 'used_at' => date('c')];
            }
            save_user($user);
            return true;
        }
    }
    return false;
}

/**
 * Generates otpauth URI adhering strictly to RFC 6238 and Apple/Google Authenticator specs.
 */
function totp_otpauth_url(string $user, string $secret, string $issuer = 'BrisaCMS'): string {
    $cleanSecret = strtoupper(trim(str_replace(['=', ' ', '-'], '', $secret)));
    $cleanIssuer = trim(str_replace([':', '/', '?', '&', '='], '', $issuer)) ?: 'BrisaCMS';
    $cleanUser   = trim(str_replace([':', '/', '?', '&', '='], '', $user)) ?: 'admin';

    $label = rawurlencode($cleanIssuer) . ':' . rawurlencode($cleanUser);
    return 'otpauth://totp/' . $label . '?secret=' . $cleanSecret . '&issuer=' . rawurlencode($cleanIssuer) . '&algorithm=SHA1&digits=6&period=30';
}

/**
 * Resolves public avatar URL for a user or relative path.
 */
function user_avatar_url(?string $avatar): string {
    if (!$avatar) {
        return '';
    }
    if (preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }
    $baseUrl = rtrim(base_url(), '/');
    $cleanPath = ltrim($avatar, '/');
    return $baseUrl . '/' . $cleanPath;
}

/**
 * Handles avatar image upload for a user and returns relative path.
 */
function user_save_avatar(string $username, array $file): ?string {
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/avif' => 'avif',
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    if ($file['size'] > 8 * 1024 * 1024) {
        return null;
    }

    $ext = $allowed[$mime];
    $cleanUser = sanitize_user_filename($username);
    $dir = ROOT_PATH . '/media/avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $filename = 'avatar_' . $cleanUser . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
    $targetPath = $dir . '/' . $filename;

    $moved = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $targetPath)
        : @copy($file['tmp_name'], $targetPath);

    if ($moved) {
        // Remove old avatar if local file in media/avatars/
        $user = get_user($username);
        if (!empty($user['avatar']) && str_starts_with($user['avatar'], 'media/avatars/')) {
            $oldFile = ROOT_PATH . '/' . $user['avatar'];
            if (file_exists($oldFile) && is_file($oldFile) && realpath($oldFile) !== realpath($targetPath)) {
                @unlink($oldFile);
            }
        }
        return 'media/avatars/' . $filename;
    }

    return null;
}

