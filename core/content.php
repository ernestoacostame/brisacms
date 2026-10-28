<?php
// KutBlog - Content Manager (File-based, no DB)
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/auth.php';

function content_path(string $type): string {
    return CONTENT_PATH . '/' . $type;
}

function slug_from_title(string $title): string {
    $slug = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if ($translit !== false) $slug = $translit;
    }
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    return trim(strtolower($slug), '-');
}

function save_content_backup(string $type, array $data): void {
    $dir = content_path($type);
    $slug = $data['slug'] ?? 'unknown';
    
    // Create backup directory if it doesn't exist
    $backup_dir = CACHE_PATH . '/content_backups/' . $type;
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Create a backup with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . '/' . $slug . '_' . $timestamp . '.json';
    
    // Save the backup
    file_put_contents($backup_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Keep only the last 10 backups for each slug
    $backups = glob($backup_dir . '/' . $slug . '_*.json');
    if (count($backups) > 10) {
        // Sort by creation time (oldest first)
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest backups beyond limit
        for ($i = 0; $i < count($backups) - 10; $i++) {
            unlink($backups[$i]);
        }
    }
}

function get_content_backups(string $type, string $slug): array {
    $backup_dir = CACHE_PATH . '/content_backups/' . $type;
    if (!is_dir($backup_dir)) {
        return [];
    }
    
    $backups = glob($backup_dir . '/' . $slug . '_*.json');
    $result = [];
    
    foreach ($backups as $backup_file) {
        $timestamp = basename($backup_file, '.json');
        $timestamp = substr($timestamp, strlen($slug) + 1); // Remove slug prefix
        
        $result[] = [
            'file' => $backup_file,
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', filemtime($backup_file)),
            'data' => json_decode(file_get_contents($backup_file), true)
        ];
    }
    
    // Sort by timestamp (newest first)
    usort($result, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return $result;
}

function save_content(string $type, array $data): string {
    $dir = content_path($type);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $slug = $data['slug'] ?? slug_from_title($data['title'] ?? 'untitled');
    $slug = sanitize_filename($slug);
    
    // Ensure unique slug — skip if the file already belongs to this article
    $original = $slug;
    $i = 1;
    while (file_exists("$dir/$slug.json")
        && ($data['original_slug'] ?? '') !== $slug
        && ($data['slug'] ?? '')          !== $slug) {
        $slug = $original . '-' . $i++;
    }
    
    // Delete old file if slug changed
    if (!empty($data['original_slug']) && $data['original_slug'] !== $slug) {
        $old = "$dir/{$data['original_slug']}.json";
        if (file_exists($old)) unlink($old);

        if ($type === 'articles' && cms_plugin_is_active('fediverse')) {
            require_once __DIR__ . '/../plugins/fediverse/activitypub.php';
            ap_update_slug($data['original_slug'], $slug);
        }
    }
    
    unset($data['original_slug']);
    $data['slug'] = $slug;
    $data['updated_at'] = date('c');
    if (empty($data['created_at'])) $data['created_at'] = date('c');
    
    // Create a backup before saving
    save_content_backup($type, $data);
    
    file_put_contents("$dir/$slug.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Clear index and page cache
    invalidate_content_index($type);
    clear_cache();
    
    return $slug;
}

function get_content(string $type, string $slug): ?array {
    $file = content_path($type) . "/$slug.json";
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);

    if ($data && $type === 'articles' && empty($data['mastodon_url'])) {
        if (cms_plugin_is_active('fediverse')) {
            $config = cms_config();
            $username = $config['fediverse_username'] ?? 'blog';
            $data['mastodon_url'] = base_url() . "/users/{$username}/notes/{$slug}";
        }
    }
    return $data;
}

function delete_content(string $type, string $slug): bool {
    $file = content_path($type) . "/$slug.json";
    if (!file_exists($file)) return false;

    // Notificar al Fediverso de la eliminación antes de purgar el archivo
    if ($type === 'articles' && cms_plugin_is_active('fediverse')) {
        require_once ROOT_PATH . '/plugins/fediverse/activitypub.php';
        if (function_exists('ap_delete_article')) {
            ap_delete_article($slug);
        }
    }

    unlink($file);
    invalidate_content_index($type);
    clear_cache();
    return true;
}

/**
 * High-performance content index cache.
 * Avoids reading and parsing hundreds of JSON files from disk on every query.
 */
function get_content_index(string $type, bool $force = false): array {
    if (!$force && isset($GLOBALS['__content_index_cache'][$type])) {
        return $GLOBALS['__content_index_cache'][$type];
    }

    $dir = content_path($type);
    if (!is_dir($dir)) return [];

    $cache_dir = CACHE_PATH;
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);
    $cache_file = $cache_dir . "/content_index_{$type}.json";

    // Use cached index if it exists and directory has not been modified
    if (!$force && file_exists($cache_file)) {
        $index_mtime = filemtime($cache_file);
        $dir_mtime = filemtime($dir);
        if ($index_mtime >= $dir_mtime) {
            $raw = @file_get_contents($cache_file);
            $cached = json_decode($raw, true);
            if (is_array($cached)) {
                $GLOBALS['__content_index_cache'][$type] = $cached;
                return $cached;
            }
        }
    }

    // Build index from disk
    $files = glob("$dir/*.json");
    $items = [];
    if ($files) {
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            $data = json_decode($raw, true);
            if (!$data || !is_array($data)) continue;
            $items[] = $data;
        }
    }

    // Sort descending by created_at
    usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

    // Atomic cache write
    @file_put_contents($cache_file, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
    $GLOBALS['__content_index_cache'][$type] = $items;
    return $items;
}

function invalidate_content_index(?string $type = null): void {
    if ($type !== null) {
        unset($GLOBALS['__content_index_cache'][$type]);
        $file = CACHE_PATH . "/content_index_{$type}.json";
        if (file_exists($file)) @unlink($file);
    } else {
        unset($GLOBALS['__content_index_cache']);
        $files = glob(CACHE_PATH . '/content_index_*.json');
        if ($files) {
            foreach ($files as $f) @unlink($f);
        }
    }
}

function list_content(string $type, bool $published_only = false, int $page = 1, int $per_page = 10): array {
    $dir = content_path($type);
    if (!is_dir($dir)) return ['items' => [], 'total' => 0, 'pages' => 0];

    // Read from optimized index cache instead of disk glob + parsing each file
    $items = get_content_index($type);

    if ($published_only) {
        $items = array_values(array_filter($items, fn($d) => ($d['status'] ?? 'draft') === 'published'));
    }

    $total = count($items);
    $pages = (int)ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;

    return [
        'items' => array_slice($items, $offset, $per_page),
        'total' => $total,
        'pages' => $pages,
        'page' => $page
    ];
}

function search_content(string $query): array {
    $results = [];
    $query_lower = strtolower(trim($query));
    if ($query_lower === '') return [];

    foreach (['articles', 'pages'] as $type) {
        // Search through indexed records fast
        $items = get_content_index($type);
        foreach ($items as $data) {
            $transcript = !empty($data['podcast_meta']['transcript']) ? (' ' . strtolower($data['podcast_meta']['transcript'])) : '';
            $haystack = strtolower(($data['title'] ?? '') . ' ' . strip_tags($data['excerpt'] ?? '') . ' ' . strip_tags($data['content'] ?? '')) . $transcript;
            if (str_contains($haystack, $query_lower)) {
                $data['_type'] = $type;
                if (!empty($data['podcast_meta']['is_podcast'])) {
                    $data['_is_podcast'] = true;
                }
                $results[] = $data;
            }
        }
    }
    return $results;
}

function content_exists(string $type, string $slug): bool {
    return file_exists(content_path($type) . "/$slug.json");
}

function clear_cache(): void {
    $files = glob(CACHE_PATH . '/page_*.html');
    if ($files) foreach ($files as $f) @unlink($f);
    invalidate_content_index();
}

// ── WordPress Importer ───────────────────────────────────────────────────
// La funcionalidad de importación de WordPress ahora reside en el plugin
// oficial `plugins/wp-import/`.
if (!function_exists('import_wordpress_xml')) {
    function import_wordpress_xml(string $xml_content, bool $download_images = true): array {
        $plugin_file = ROOT_PATH . '/plugins/wp-import/wp-import.php';
        if (file_exists($plugin_file)) {
            require_once $plugin_file;
            return import_wordpress_xml($xml_content, $download_images);
        }
        return ['❌ Error: El plugin wp-import no está disponible.'];
    }
}

