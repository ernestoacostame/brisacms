<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_login();

$type = in_array($_GET['type'] ?? '', ['articles', 'pages']) ? $_GET['type'] : null;
$slug = $_GET['slug'] ?? '';
$csrf = $_GET['csrf'] ?? '';
$perm = !empty($_GET['permanent']);

if ($type && $slug && verify_csrf($csrf)) {
    if ($perm) {
        purge_content($type, $slug);
        header("Location: " . base_url() . "/admin/trash.php?purged=1");
    } else {
        trash_content($type, $slug);
        header("Location: " . base_url() . "/admin/{$type}.php?trashed=1");
    }
} else {
    header("Location: " . base_url() . "/admin/");
}
exit;
