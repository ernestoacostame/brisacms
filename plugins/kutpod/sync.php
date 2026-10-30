<?php
/**
 * KutPod Plugin - Synchronization Engine for KutBlog
 * Synchronizes episodes from KutPod (via local SQLite or remote REST API)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
require_once ROOT_PATH . '/core/config.php';
require_once ROOT_PATH . '/core/content.php';
require_once __DIR__ . '/kutpod.php';

/**
 * Ejecuta la sincronización completa de episodios
 */
function kutpod_run_sync(): array {
    $cfg = kutpod_config();
    $stats = ['created' => 0, 'updated' => 0, 'errors' => []];

    $mode = $cfg['connection_mode'] ?? 'local';
    $episodes = [];

    if ($mode === 'local') {
        $dbPath = $cfg['local_db_path'] ?: '/home/elav/Developer/KutStudio/kutpod/storage/kutpod.db';
        if (!file_exists($dbPath)) {
            $stats['errors'][] = "No se encontró la base de datos de KutPod en: $dbPath";
            return $stats;
        }

        try {
            $pdo = new PDO("sqlite:$dbPath", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $whereClause = "e.hidden = 0 AND e.status = 'published'";
            $params = [];
            if (!empty($cfg['podcast_filter']) && $cfg['podcast_filter'] !== 'all') {
                $whereClause .= " AND (p.slug = :pslug OR p.id = :pid)";
                $params[':pslug'] = $cfg['podcast_filter'];
                $params[':pid'] = is_numeric($cfg['podcast_filter']) ? (int)$cfg['podcast_filter'] : 0;
            }

            $stmt = $pdo->prepare("
                SELECT e.*, p.slug AS podcast_slug, p.title AS podcast_title, p.color AS podcast_color
                FROM episodes e
                JOIN podcasts p ON p.id = e.podcast_id
                WHERE $whereClause
                ORDER BY COALESCE(e.published_at, e.publish_at, e.created_at) DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $rawCover = $row['cover'] ?: '';
                $coverUrl = kutpod_resolve_cover_url($rawCover, $cfg, [
                    'podcast_slug' => $row['podcast_slug'],
                    'audio_url'    => $row['audio_url'] ?? ''
                ]);
                $episodes[] = [
                    'id'            => (int)$row['id'],
                    'guid'          => $row['guid'] ?: ('kutpod-' . $row['id']),
                    'podcast_id'    => (int)$row['podcast_id'],
                    'podcast_slug'  => $row['podcast_slug'],
                    'podcast_title' => $row['podcast_title'],
                    'slug'          => $row['slug'],
                    'title'         => $row['title'],
                    'season'        => (int)($row['season'] ?? 1),
                    'number'        => (int)($row['number'] ?? 0),
                    'notes_md'      => $row['notes_md'] ?? '',
                    'audio_url'     => $row['audio_url'] ?? '',
                    'cover'         => $coverUrl,
                    'instance_url'  => $cfg['instance_url'] ?? '',
                    'duration_secs' => (int)($row['duration_secs'] ?? 0),
                    'published_at'  => $row['published_at'] ?: $row['created_at'],
                    'downloads'     => (int)($row['downloads'] ?? 0),
                    'transcript_url'=> $row['transcript_url'] ?? '',
                    'chapters_url'  => $row['chapters_url'] ?? ''
                ];
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "Error al conectar con SQLite de KutPod: " . $e->getMessage();
            return $stats;
        }
    } else {
        // Modo Remoto vía REST API / Feed
        $remoteUrl = rtrim($cfg['instance_url'] ?? '', '/');
        if (!$remoteUrl) {
            $stats['errors'][] = "URL de instancia de KutPod no especificada.";
            return $stats;
        }

        $feedUrl = $remoteUrl . '/feed.php?all=1';
        // Alternativamente consultar API
        $apiUrl = $remoteUrl . '/api/episodes';
        
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header'  => "User-Agent: KutBlog-KutPod/1.0\r\n"
            ]
        ]);
        
        $json = @file_get_contents($apiUrl, false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            $episodes = $data['items'] ?? [];
        } else {
            // Intentar leer feed RSS principal
            $stats['errors'][] = "No se pudo consultar el endpoint REST remoto en: $apiUrl";
            return $stats;
        }
    }

    // Procesar cada episodio e importarlo / actualizarlo en KutBlog
    foreach ($episodes as $ep) {
        $res = kutpod_upsert_article_from_episode($ep, $cfg);
        if ($res === 'created') $stats['created']++;
        elseif ($res === 'updated') $stats['updated']++;
    }

    // Invalidar cachés
    invalidate_content_index('articles');
    if (function_exists('clear_cache')) {
        clear_cache();
    }

    return $stats;
}

/**
 * Inserta o actualiza un artículo en KutBlog a partir de un episodio
 */
function kutpod_upsert_article_from_episode(array $ep, array $cfg): string {
    $articlesDir = content_path('articles');
    if (!is_dir($articlesDir)) {
        @mkdir($articlesDir, 0755, true);
    }

    $episodeSlug = sanitize_filename($ep['slug'] ?: slug_from_title($ep['title']));
    $targetFile = "$articlesDir/{$episodeSlug}.json";

    // Buscar si ya existe un artículo con este GUID o ID
    $existing = null;
    $existingFile = null;

    if (file_exists($targetFile)) {
        $candidate = json_decode(file_get_contents($targetFile), true);
        if (!empty($candidate['podcast_meta']['guid']) && $candidate['podcast_meta']['guid'] === $ep['guid']) {
            $existing = $candidate;
            $existingFile = $targetFile;
        } else {
            // Colisión de slug entre podcasts distintos -> prefijar slug del podcast
            $pSlug = $ep['podcast_slug'] ?? 'podcast';
            $episodeSlug = sanitize_filename("{$pSlug}-{$episodeSlug}");
            $targetFile = "$articlesDir/{$episodeSlug}.json";
            if (file_exists($targetFile)) {
                $existing = json_decode(file_get_contents($targetFile), true);
                $existingFile = $targetFile;
            }
        }
    } else {
        // Buscar en otros artículos por podcast_meta.guid o id
        foreach (glob("$articlesDir/*.json") as $f) {
            $candidate = json_decode(file_get_contents($f), true);
            if (!empty($candidate['podcast_meta']['guid']) && $candidate['podcast_meta']['guid'] === $ep['guid']) {
                $existing = $candidate;
                $existingFile = $f;
                break;
            }
        }
    }

    // Parsear timestamps desde las notas para generar lista de capítulos
    $chapters = kutpod_extract_chapters_from_notes($ep['notes_md']);

    // Construir bloque de notas formateadas
    $notes = trim($ep['notes_md']);

    // Formatear duración
    $durSecs = (int)($ep['duration_secs'] ?? 0);
    $durFmt = $durSecs > 0 ? sprintf('%02d:%02d:%02d', floor($durSecs/3600), floor(($durSecs%3600)/60), $durSecs%60) : '0:00';
    if (str_starts_with($durFmt, '00:')) $durFmt = substr($durFmt, 3);

    $defaultStatus = $cfg['default_status'] ?? 'draft';
    $defaultCategory = $cfg['default_category'] ?? 'Podcast';

    $podcastMeta = [
        'is_podcast'     => true,
        'episode_id'     => $ep['id'],
        'guid'           => $ep['guid'],
        'podcast_id'     => $ep['podcast_id'] ?? 1,
        'podcast_slug'   => $ep['podcast_slug'] ?? 'default',
        'podcast_title'  => $ep['podcast_title'] ?? '',
        'episode_slug'   => $ep['slug'],
        'audio_url'      => $ep['audio_url'] ?? '',
        'duration_secs'  => $durSecs,
        'duration_fmt'   => $durFmt,
        'season'         => (int)($ep['season'] ?? 1),
        'number'         => (int)($ep['number'] ?? 0),
        'downloads'      => (int)($ep['downloads'] ?? 0),
        'transcript_url' => $ep['transcript_url'] ?? '',
        'chapters_url'   => $ep['chapters_url'] ?? '',
        'chapters'       => $chapters,
        'synced_at'      => date('c')
    ];

    $coverUrl = kutpod_resolve_cover_url($ep['cover'] ?? '', $cfg, $ep);

    if ($existing && $existingFile) {
        // Actualizar artículo existente
        // Respetamos si el usuario ya modificó el status o el contenido propio
        $existing['podcast_meta'] = $podcastMeta;
        $existing['title'] = $ep['title'];
        
        // Si las notas fueron actualizadas en KutPod y se permite auto-update:
        if (!empty($cfg['auto_update_notes'])) {
            $existing['content'] = $notes;
        }

        // Si no tenía imagen o tenía una ruta relativa sin dominio (/media/covers/...), actualizar con la URL resuelta:
        if ((empty($existing['featured_image']) || str_starts_with($existing['featured_image'], '/media/covers/')) && !empty($coverUrl)) {
            $existing['featured_image'] = $coverUrl;
        }

        file_put_contents($existingFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return 'updated';
    } else {
        // Crear nuevo artículo
        $categories = [];
        if (!empty($cfg['categorize_by_show']) && !empty($ep['podcast_title'])) {
            $categories[] = $ep['podcast_title'];
        } else {
            $categories[] = $defaultCategory;
        }
        $tags = ['podcast', 'audio'];
        if (!empty($ep['podcast_title'])) {
            $tags[] = strtolower($ep['podcast_title']);
        }

        $excerpt = mb_substr(strip_tags($notes), 0, 180, 'UTF-8');
        if (mb_strlen(strip_tags($notes), 'UTF-8') > 180) $excerpt .= '...';

        $newArticle = [
            'title'          => $ep['title'],
            'slug'           => $episodeSlug,
            'content'        => $notes,
            'content_format' => 'html',
            'excerpt'        => $excerpt,
            'status'         => $defaultStatus,
            'categories'     => array_values(array_unique($categories)),
            'tags'           => array_values(array_unique($tags)),
            'featured_image' => $coverUrl,
            'created_at'     => date('c', strtotime($ep['published_at'] ?? 'now')),
            'updated_at'     => date('c'),
            'podcast_meta'   => $podcastMeta
        ];

        file_put_contents($targetFile, json_encode($newArticle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return 'created';
    }
}

/**
 * Resuelve la URL de la portada para que sea absoluta y accesible desde cualquier navegador
 */
function kutpod_resolve_cover_url(?string $cover, array $cfg, array $ep = []): string {
    if (empty($cover)) return '';
    $cover = trim($cover);
    if (preg_match('#^https?://#i', $cover)) {
        return $cover;
    }
    // Si viene una ruta relativa como /media/covers/...
    $base = '';
    if (!empty($ep['instance_url'])) {
        $base = rtrim($ep['instance_url'], '/');
    } elseif (!empty($cfg['instance_url'])) {
        $base = rtrim($cfg['instance_url'], '/');
    } elseif (!empty($ep['audio_url']) && preg_match('#^https?://#i', $ep['audio_url'])) {
        $parts = parse_url($ep['audio_url']);
        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            $base = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) && !in_array((int)$parts['port'], [80, 443]) ? ':' . $parts['port'] : '');
        }
    }
    if (!$base) {
        $base = 'http://localhost:8000';
    }
    return $base . (str_starts_with($cover, '/') ? '' : '/') . $cover;
}

/**
 * Extrae capítulos y timestamps de las notas Markdown
 */
function kutpod_extract_chapters_from_notes(string $notes): array {
    $chapters = [];
    $lines = explode("\n", $notes);

    foreach ($lines as $line) {
        $line = trim($line);
        // Coincide con [00:12], 01:45, [1:23:45], 01:23:45 - Título
        if (preg_match('/(?:^|[\(\[\s])(\d{1,2}:\d{2}(?::\d{2})?)(?:[\)\]\s])\s*[-–—:]?\s*(.+)$/u', $line, $m)) {
            $timeStr = $m[1];
            $title = trim($m[2]);

            // Convertir timeStr a segundos
            $parts = array_map('intval', explode(':', $timeStr));
            $secs = 0;
            if (count($parts) === 3) {
                $secs = $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
            } elseif (count($parts) === 2) {
                $secs = $parts[0] * 60 + $parts[1];
            }

            $chapters[] = [
                'time_str' => $timeStr,
                'seconds'  => $secs,
                'title'    => strip_tags($title)
            ];
        }
    }

    return $chapters;
}
