<?php
// BrisaCMS - Media Management & Metadata Storage

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('media_meta_file')) {
function media_meta_file(): string {
    return ROOT_PATH . '/content/media_metadata.json';
}
}

/**
 * Normalizes an absolute URL or path to a relative media path.
 * e.g. "http://example.com/media/2026/10/photo.jpg" -> "2026/10/photo.jpg"
 * e.g. "/media/2026/10/photo.jpg" -> "2026/10/photo.jpg"
 * e.g. "2026/10/photo.jpg" -> "2026/10/photo.jpg"
 */
function media_normalize_rel(string $path_or_url): string {
    $path = parse_url($path_or_url, PHP_URL_PATH) ?? $path_or_url;
    $path = str_replace('\\', '/', $path);
    
    // Strip everything up to /media/
    if (preg_match('#(?:^|/)media/(.+)$#i', $path, $m)) {
        $path = $m[1];
    }
    
    // Clean directory traversals
    $path = ltrim(str_replace(['../', '..\\', '//'], '', $path), '/');
    return $path;
}

/**
 * Returns all media metadata indexed by relative file path.
 */
function media_get_all_meta(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $file = media_meta_file();
    if (!file_exists($file)) {
        $cache = [];
        return $cache;
    }

    $raw = @file_get_contents($file);
    $data = json_decode($raw, true);
    $cache = is_array($data) ? $data : [];
    return $cache;
}

/**
 * Retrieves metadata for a specific media relative path or URL.
 */
function media_get_meta(string $rel_or_url): array {
    $rel = media_normalize_rel($rel_or_url);
    if (!$rel) {
        return [
            'alt'         => '',
            'title'       => '',
            'caption'     => '',
            'description' => '',
            'updated_at'  => '',
        ];
    }

    $all = media_get_all_meta();
    $meta = $all[$rel] ?? [];

    return [
        'alt'         => (string)($meta['alt'] ?? ''),
        'title'       => (string)($meta['title'] ?? ''),
        'caption'     => (string)($meta['caption'] ?? ''),
        'description' => (string)($meta['description'] ?? ''),
        'updated_at'  => (string)($meta['updated_at'] ?? ''),
    ];
}

/**
 * Saves metadata for a media file.
 */
function media_save_meta(string $rel_or_url, array $data): bool {
    $rel = media_normalize_rel($rel_or_url);
    if (!$rel) return false;

    $file = media_meta_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $all = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $all = json_decode($raw, true) ?: [];
    }

    $all[$rel] = [
        'alt'         => trim((string)($data['alt'] ?? '')),
        'title'       => trim((string)($data['title'] ?? '')),
        'caption'     => trim((string)($data['caption'] ?? '')),
        'description' => trim((string)($data['description'] ?? '')),
        'updated_at'  => date('c'),
    ];

    $fp = @fopen($file, 'c+');
    if (!$fp) return false;

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    fclose($fp);
    return false;
}

/**
 * Removes metadata when a file is deleted.
 */
function media_delete_meta(string $rel_or_url): bool {
    $rel = media_normalize_rel($rel_or_url);
    if (!$rel) return false;

    $file = media_meta_file();
    if (!file_exists($file)) return true;

    $raw = @file_get_contents($file);
    $all = json_decode($raw, true) ?: [];

    if (!isset($all[$rel])) return true;

    unset($all[$rel]);
    file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return true;
}
