<?php
// BrisaCMS - Autosave endpoint
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'CSRF error']); exit;
}

$type    = in_array($_POST['type'] ?? '', ['articles','pages']) ? $_POST['type'] : 'articles';
$slug    = trim($_POST['slug'] ?? '');
$title   = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$format  = in_array($_POST['content_format'] ?? '', ['html','markdown']) ? $_POST['content_format'] : 'html';

// Require at least a title to autosave
if (!$title) {
    echo json_encode(['ok' => false, 'error' => 'No title yet']); exit;
}

// If no slug yet, generate one from the title
if (!$slug) {
    $slug = save_content($type, [
        'title'          => $title,
        'content'        => $content,
        'content_format' => $format,
        'status'         => 'draft',
        'excerpt'        => $_POST['excerpt'] ?? '',
        'categories'     => array_filter(array_map('trim', explode(',', $_POST['categories'] ?? ''))),
        'tags'           => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
        'featured_image' => $_POST['featured_image'] ?? '',
        'mastodon_url'   => $_POST['mastodon_url'] ?? '',
    ]);
    echo json_encode(['ok' => true, 'slug' => $slug, 'new' => true]);
    exit;
}

// Existing article — load and update content only (preserve status and other fields)
$existing = get_content($type, $slug) ?? [];
$existing['title']          = $title;
$existing['content']        = $content;
$existing['content_format'] = $format;
$existing['excerpt']        = $_POST['excerpt'] ?? ($existing['excerpt'] ?? '');
$existing['categories']     = array_filter(array_map('trim', explode(',', $_POST['categories'] ?? '')));
$existing['tags']           = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
$existing['featured_image'] = $_POST['featured_image'] ?? ($existing['featured_image'] ?? '');
$existing['mastodon_url']   = $_POST['mastodon_url'] ?? ($existing['mastodon_url'] ?? '');
// Never change status to published via autosave
if (!isset($existing['status'])) $existing['status'] = 'draft';

$new_slug = save_content($type, $existing);
echo json_encode(['ok' => true, 'slug' => $new_slug, 'new' => false]);
