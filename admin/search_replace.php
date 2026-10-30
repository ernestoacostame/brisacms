<?php
// Redirige al plugin oficial Search & Replace (search-replace)
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_login();

if (cms_plugin_is_active('search-replace')) {
    header('Location: ' . base_url() . '/admin/plugins.php?action=settings&plugin=search-replace');
    exit;
} else {
    header('Location: ' . base_url() . '/admin/plugins.php?error=inactive');
    exit;
}
