<?php
// Redirige al plugin oficial Importador de WordPress (wp-import)
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_login();

if (cms_plugin_is_active('wp-import')) {
    header('Location: ' . base_url() . '/admin/plugins.php?action=settings&plugin=wp-import&tab=xml');
    exit;
}

header('Location: ' . base_url() . '/admin/plugins.php?error=inactive');
exit;
