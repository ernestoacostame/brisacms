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
