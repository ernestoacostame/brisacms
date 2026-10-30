<?php
// KutBlog - Analytics Track Endpoint
// Handles anonymous pageviews and scroll depth beacons without cookies

require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/analytics.php';

// Always return 204 No Content
http_response_code(204);

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw || strlen($raw) > 4096) {
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    exit;
}

$path = (string)($data['path'] ?? '');
if ($path === '') {
    exit;
}

$ip = analytics_client_ip();
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

if (isset($data['depth'])) {
    $depth = (int)$data['depth'];
    $dwell = (int)($data['dwell'] ?? 0);
    analytics_record_scroll($path, $ip, $ua, $depth, $dwell);
} else {
    $ref   = (string)($data['referrer'] ?? '');
    $touch = !empty($data['touch']);
    analytics_record_view($path, $ip, $ua, $ref, $touch);
}

exit;
