<?php
// FluxCMS - Content Manager (File-based, no DB)
require_once __DIR__ . '/markdown.php';

function content_path(string $type): string {
    return CONTENT_PATH . '/' . $type;
}

function slug_from_title(string $title): string {
    $slug = strtolower($title);
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
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
    }
    
    unset($data['original_slug']);
    $data['slug'] = $slug;
    $data['updated_at'] = date('c');
    if (empty($data['created_at'])) $data['created_at'] = date('c');
    
    file_put_contents("$dir/$slug.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Clear cache
    clear_cache();
    
    return $slug;
}

function get_content(string $type, string $slug): ?array {
    $file = content_path($type) . "/$slug.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function delete_content(string $type, string $slug): bool {
    $file = content_path($type) . "/$slug.json";
    if (!file_exists($file)) return false;
    unlink($file);
    clear_cache();
    return true;
}

function list_content(string $type, bool $published_only = false, int $page = 1, int $per_page = 10): array {
    $dir = content_path($type);
    if (!is_dir($dir)) return ['items' => [], 'total' => 0, 'pages' => 0];
    
    $files = glob("$dir/*.json");
    $items = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($published_only && ($data['status'] ?? 'draft') !== 'published') continue;
        $items[] = $data;
    }
    
    // Sort by created_at descending
    usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    
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
    foreach (['articles', 'pages'] as $type) {
        $dir = content_path($type);
        if (!is_dir($dir)) continue;
        foreach (glob("$dir/*.json") as $file) {
            $data = json_decode(file_get_contents($file), true);
            $haystack = strtolower(($data['title'] ?? '') . ' ' . strip_tags($data['content'] ?? ''));
            if (str_contains($haystack, strtolower($query))) {
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
    if ($files) foreach ($files as $f) unlink($f);
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
