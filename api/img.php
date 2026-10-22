<?php
// BrisaCMS - Image Cache & Optimization Endpoint
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/image_cache.php';

$src     = trim($_GET['src'] ?? '');
$width   = isset($_GET['w']) ? (int)$_GET['w'] : 0;
$height  = isset($_GET['h']) ? (int)$_GET['h'] : 0;
$quality = isset($_GET['q']) ? (int)$_GET['q'] : 82;

if (!$src) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Parámetro "src" requerido.');
}

image_cache_serve($src, $width, $height, $quality);
