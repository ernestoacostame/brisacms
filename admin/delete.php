<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_login();

$type = in_array($_GET['type'] ?? '', ['articles', 'pages']) ? $_GET['type'] : null;
$slug = $_GET['slug'] ?? '';
$csrf = $_GET['csrf'] ?? '';

if ($type && $slug && verify_csrf($csrf)) {
    if ($type === 'articles') {
        if (cms_plugin_is_active('fediverse')) {
            require_once dirname(__DIR__) . '/plugins/fediverse/activitypub.php';
            ap_delete_article($slug);
        }
    }
    delete_content($type, $slug);
    header("Location: " . base_url() . "/admin/{$type}.php?deleted=1");
} else {
    header("Location: " . base_url() . "/admin/");
}
exit;
