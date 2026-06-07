<?php
// BrisaCMS - Backup Preview endpoint
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (!verify_csrf($_GET['csrf'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'CSRF error']); exit;
}

$type = in_array($_GET['type'] ?? '', ['articles','pages']) ? $_GET['type'] : 'articles';
$slug = trim($_GET['slug'] ?? '');
$timestamp = trim($_GET['timestamp'] ?? '');

if (!$slug || !$timestamp) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']); exit;
}

$backup_dir = CACHE_PATH . '/content_backups/' . $type;
$backup_file = $backup_dir . '/' . $slug . '_' . $timestamp . '.json';

if (!file_exists($backup_file)) {
    echo json_encode(['success' => false, 'error' => 'Backup not found']); exit;
}

$data = json_decode(file_get_contents($backup_file), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid backup file']); exit;
}

echo json_encode([
    'success' => true,
    'content' => $data['content'] ?? '',
    'title' => $data['title'] ?? '',
    'timestamp' => $timestamp
]);
