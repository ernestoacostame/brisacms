<?php
// FluxCMS - Configuration
define('CMS_VERSION', '1.0.0');
define('CMS_NAME', 'BrisaCMS');
define('ROOT_PATH', dirname(__DIR__));
define('CONTENT_PATH', ROOT_PATH . '/content');
define('THEMES_PATH', ROOT_PATH . '/themes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('CONFIG_FILE', ROOT_PATH . '/config.json');
define('INSTALLED_FLAG', ROOT_PATH . '/.installed');

function cms_config(): array {
    if (!file_exists(CONFIG_FILE)) return [];
    return json_decode(file_get_contents(CONFIG_FILE), true) ?? [];
}

function cms_save_config(array $data): void {
    file_put_contents(CONFIG_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function is_installed(): bool {
    return file_exists(INSTALLED_FLAG);
}

// Auto-detect base URL
function base_url(): string {
    $config = cms_config();
    return $config['base_url'] ?? '';
}

function site_title(): string {
    $config = cms_config();
    return $config['site_title'] ?? CMS_NAME;
}

function active_theme(): string {
    $config = cms_config();
    return $config['theme'] ?? 'default';
}

function theme_color(): string {
    $config = cms_config();
    return $config['theme_color'] ?? '#6366f1';
}

// ── i18n ──────────────────────────────────────────────────────────────────
function detect_admin_lang(): string {
    // 1. Explicit override via GET param (persisted in session)
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['es','en'])) {
        @session_start();
        $_SESSION['admin_lang'] = $_GET['lang'];
        return $_GET['lang'];
    }
    // 2. Session preference
    @session_start();
    if (!empty($_SESSION['admin_lang'])) return $_SESSION['admin_lang'];
    // 3. Browser Accept-Language header
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    return (stripos($accept, 'es') === 0 || strpos($accept, 'es-') !== false || strpos($accept, ',es') !== false) ? 'es' : 'en';
}

function __( string $key, string $fallback = '' ): string {
    static $strings = null;
    if ($strings === null) {
        $lang    = detect_admin_lang();
        $file    = ROOT_PATH . "/core/lang/{$lang}.php";
        $strings = file_exists($file) ? require $file : [];
        // Always fall back to English for missing keys
        if ($lang !== 'en') {
            $en_file = ROOT_PATH . '/core/lang/en.php';
            $en      = file_exists($en_file) ? require $en_file : [];
            $strings = array_merge($en, $strings);
        }
    }
    return htmlspecialchars($strings[$key] ?? $fallback ?: $key, ENT_QUOTES, 'UTF-8');
}

// Raw (no htmlspecialchars) — for use in JS strings or known-safe values
function __raw( string $key, string $fallback = '' ): string {
    static $strings_raw = null;
    if ($strings_raw === null) {
        $lang    = detect_admin_lang();
        $file    = ROOT_PATH . "/core/lang/{$lang}.php";
        $strings_raw = file_exists($file) ? require $file : [];
        if ($lang !== 'en') {
            $en = ROOT_PATH . '/core/lang/en.php';
            $strings_raw = array_merge(file_exists($en) ? require $en : [], $strings_raw);
        }
    }
    return $strings_raw[$key] ?? $fallback ?: $key;
}
