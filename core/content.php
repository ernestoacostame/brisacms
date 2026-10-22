<?php
// BrisaCMS - Content Manager (File-based, no DB)
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
            $haystack = strtolower(($data['title'] ?? '') . ' ' . strip_tags($data['excerpt'] ?? '') . ' ' . strip_tags($data['content'] ?? ''));
            if (str_contains($haystack, $query_lower)) {
                $data['_type'] = $type;
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

// ── WordPress XML importer with inline image download ─────────────────────

function wp_download_image(string $src_url, string $base_url, array &$log): string {
    // Extract path preserving WP structure e.g. /wp-content/uploads/2025/02/img.jpg
    $parsed   = parse_url($src_url);
    $url_path = $parsed['path'] ?? '';

    // Keep only the part after /uploads/ (or full path if not WP uploads)
    if (preg_match('#/uploads/(.+)$#', $url_path, $m)) {
        $rel_path = $m[1]; // e.g. 2025/02/image.jpg
    } else {
        $rel_path = ltrim($url_path, '/');
    }

    // Sanitize each path segment
    $parts     = explode('/', $rel_path);
    $parts     = array_map(fn($p) => preg_replace('/[^a-zA-Z0-9._\-]/', '-', $p), $parts);
    $rel_path  = implode('/', $parts);

    $dest_dir  = ROOT_PATH . '/media/' . dirname($rel_path);
    $dest_file = ROOT_PATH . '/media/' . $rel_path;
    $local_url = $base_url . '/media/' . $rel_path;

    // Already downloaded
    if (file_exists($dest_file)) return $local_url;

    // Create directory structure
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    // Download with timeout
    $ctx  = stream_context_create(['http' => [
        'timeout'          => 20,
        'follow_location'  => true,
        'ignore_errors'    => true,
        'header'           => "User-Agent: BrisaCMS/1.0 (compatible)\r\n",
    ]]);
    $data = @file_get_contents($src_url, false, $ctx);

    if ($data === false || strlen($data) < 100) {
        $log[] = "  ⚠ No se pudo descargar: $src_url";
        return $src_url; // leave original URL on failure
    }

    file_put_contents($dest_file, $data);
    $log[] = "  ↓ Imagen: $rel_path (" . round(strlen($data)/1024) . " KB)";
    return $local_url;
}

function wp_rewrite_images(string $html, string $base_url, array &$log): string {
    // Match <img src="..."> tags, including inside <figure> blocks
    return preg_replace_callback(
        '/(<img[^>]+\ssrc=")([^"]+)("[^>]*>)/i',
        function($m) use ($base_url, &$log) {
            $src = $m[2];
            // Only rewrite external absolute URLs
            if (!preg_match('#^https?://#i', $src)) return $m[0];
            // Skip if already pointing to our domain
            if (str_contains($src, parse_url($base_url, PHP_URL_HOST) ?? '')) return $m[0];
            $new_src = wp_download_image($src, $base_url, $log);
            return $m[1] . $new_src . $m[3];
        },
        $html
    );
}

function wp_build_attachment_map(object $channel): array {
    // Build a map of attachment_id -> URL for featured image resolution
    $map = [];
    foreach ($channel->item as $item) {
        $wp = $item->children('wp', true);
        if ((string)$wp->post_type !== 'attachment') continue;
        $id  = (string)$wp->post_id;
        $url = (string)$wp->attachment_url;
        if (!$url) $url = (string)$item->guid;
        if ($id && $url) $map[$id] = $url;
    }
    return $map;
}

function import_wordpress_xml(string $xml_content, bool $download_images = true): array {
    $log      = [];
    $base_url = rtrim(base_url(), '/');

    try {
        // Fix common encoding issues in WP exports
        $xml_content = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u',
            '', $xml_content
        );

        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($xml_content, LIBXML_NOCDATA);
        $xml->registerXPathNamespace('wp',      'http://wordpress.org/export/1.2/');
        $xml->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->registerXPathNamespace('dc',      'http://purl.org/dc/elements/1.1/');
        $xml->registerXPathNamespace('excerpt', 'http://wordpress.org/export/1.2/excerpt/');

        $channel     = $xml->channel;
        $attach_map  = wp_build_attachment_map($channel); // id -> url map for featured images
        $imported    = 0;
        $skipped     = 0;
        $img_count   = 0;

        foreach ($channel->item as $item) {
            $wp         = $item->children('wp', true);
            $content_ns = $item->children('http://purl.org/rss/1.0/modules/content/');
            $excerpt_ns = $item->children('http://wordpress.org/export/1.2/excerpt/');

            $post_type = (string)$wp->post_type;
            $status    = (string)$wp->status;

            // Skip attachments, nav menu items, etc.
            if (!in_array($post_type, ['post', 'page'])) { $skipped++; continue; }

            $type  = $post_type === 'post' ? 'articles' : 'pages';
            $title = html_entity_decode((string)$item->title, ENT_QUOTES, 'UTF-8');
            $slug  = (string)$wp->post_name ?: slug_from_title($title);
            $body  = (string)$content_ns->encoded;
            $exc   = strip_tags((string)$excerpt_ns->encoded);
            $date  = (string)$wp->post_date;

            // Remove Gutenberg block comments (<!-- wp:paragraph --> etc.)
            $body = preg_replace('/<!--\s*wp:[a-zA-Z\/\-]+(\s+\{[^}]*\})?\s*-->/s', '', $body);
            $body = preg_replace('/<!--\s*\/wp:[a-zA-Z\/\-]+\s*-->/s', '', $body);

            // Unwrap empty figure wrappers left by Gutenberg, keep img inside
            $body = preg_replace('/<figure[^>]*>\s*(<img[^>]+>)\s*<\/figure>/i', '$1', $body);
            $body = trim($body);

            // ── Download images referenced in content ──────────────────
            $img_before = $img_count;
            if ($download_images && $body) {
                $img_log = [];
                $body    = wp_rewrite_images($body, $base_url, $img_log);
                $log     = array_merge($log, $img_log);
                $img_count += count($img_log);
            }

            // ── Featured image from WP meta ────────────────────────────
            $featured      = '';
            $thumbnail_id  = '';
            foreach ($wp->postmeta as $meta) {
                if ((string)$meta->meta_key === '_thumbnail_id') {
                    $thumbnail_id = (string)$meta->meta_value;
                    break;
                }
            }
            if ($thumbnail_id && isset($attach_map[$thumbnail_id])) {
                $feat_src = $attach_map[$thumbnail_id];
                if ($download_images) {
                    $img_log  = [];
                    $featured = wp_download_image($feat_src, $base_url, $img_log);
                    $log      = array_merge($log, $img_log);
                } else {
                    $featured = $feat_src;
                }
            }

            // ── Categories and tags ───────────────────────────────────
            $categories = [];
            $tags       = [];
            foreach ($item->category as $cat) {
                $domain = (string)$cat['domain'];
                $val    = html_entity_decode((string)$cat, ENT_QUOTES, 'UTF-8');
                if ($domain === 'category' && $val && $val !== 'Uncategorized') $categories[] = $val;
                if ($domain === 'post_tag' && $val) $tags[] = $val;
            }

            save_content($type, [
                'title'          => $title,
                'slug'           => $slug,
                'content'        => $body,
                'excerpt'        => $exc,
                'status'         => $status === 'publish' ? 'published' : 'draft',
                'categories'     => array_values(array_unique($categories)),
                'tags'           => array_values(array_unique($tags)),
                'featured_image' => $featured,
                'created_at'     => $date ?: date('c'),
                'wp_import'      => true,
            ]);

            $imgs_this = $img_count - $img_before;
            $img_str   = $imgs_this > 0 ? " ($imgs_this img)" : '';
            $log[]     = "✓ $type: $title$img_str";
            $imported++;
        }

        $log[] = "---";
        $log[] = "✅ Completado: $imported importados, $skipped omitidos, $img_count imágenes descargadas.";

    } catch (Exception $e) {
        $log[] = "❌ Error: " . $e->getMessage();
    }
    return $log;
}
