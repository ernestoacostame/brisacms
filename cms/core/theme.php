<?php
// FluxCMS - Theme Engine

function theme_path(string $theme = ''): string {
    return THEMES_PATH . '/' . ($theme ?: active_theme());
}

function available_themes(): array {
    $themes = [];
    foreach (glob(THEMES_PATH . '/*/theme.json') as $file) {
        $info = json_decode(file_get_contents($file), true);
        $name = basename(dirname($file));
        $themes[$name] = array_merge(['name' => $name, 'label' => ucfirst($name)], $info ?? []);
    }
    return $themes;
}

function theme_file(string $filename, string $theme = ''): string {
    $theme = $theme ?: active_theme();
    $file = theme_path($theme) . "/$filename";
    if (file_exists($file)) return $file;
    // Fallback to default theme
    return theme_path('default') . "/$filename";
}

function render_theme(string $template, array $vars = []): void {
    extract($vars);
    $config = cms_config();
    $site_title = $config['site_title'] ?? CMS_NAME;
    $theme_color = $config['theme_color'] ?? '#6366f1';
    $theme = active_theme();
    $base = base_url();
    
    $file = theme_file("$template.php");
    if (!file_exists($file)) {
        echo "<p>Template '$template' not found.</p>";
        return;
    }
    
    // Load header
    $header_file = theme_file('header.php');
    if (file_exists($header_file)) include $header_file;
    include $file;
    $footer_file = theme_file('footer.php');
    if (file_exists($footer_file)) include $footer_file;
}

function theme_asset_url(string $asset): string {
    return base_url() . '/themes/' . active_theme() . '/' . $asset;
}

function get_theme_css_vars(): string {
    $color = theme_color();
    // Ensure valid hex color
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6366f1';
    $r = hexdec(substr($color, 1, 2));
    $g = hexdec(substr($color, 3, 2));
    $b = hexdec(substr($color, 5, 2));
    return ":root {
        --accent: {$color};
        --accent-rgb: {$r},{$g},{$b};
        --accent-light: rgba({$r},{$g},{$b},0.1);
        --accent-medium: rgba({$r},{$g},{$b},0.3);
    }";
}
