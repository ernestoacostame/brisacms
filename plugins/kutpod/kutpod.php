<?php
/**
 * KutPod Plugin for KutBlog
 * Main Bootstrapper and Extension Manager
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('CONTENT_PATH')) {
    define('CONTENT_PATH', ROOT_PATH . '/content');
}

// ── Configuración del Plugin ──────────────────────────────────────────────────
function kutpod_config_file(): string {
    return CONTENT_PATH . '/kutpod_config.json';
}

function kutpod_config(): array {
    $defaults = [
        'connection_mode'        => 'local', // 'local' (SQLite) o 'remote' (API)
        'instance_url'           => 'http://localhost:8000',
        'local_db_path'          => '/home/elav/Developer/KutStudio/kutpod/storage/kutpod.db',
        'webhook_secret'         => '',
        'podcast_filter'         => 'all', // 'all' o slug específico del show
        'categorize_by_show'     => true,  // Asigna el nombre del show como categoría
        'default_status'         => 'draft', // 'draft' o 'published'
        'default_category'       => 'Podcast',
        'auto_update_notes'      => true,
        'delete_on_kutpod_delete'=> false,
        'player_position'        => 'top', // 'top', 'bottom', 'manual'
        'player_theme'           => 'auto', // 'auto', 'dark', 'light'
        'enable_timestamps'      => true,
        'enable_transcripts'     => true,
        'enable_stats_badge'     => true,
        'enable_directory'       => true,
        'directory_slug'         => 'podcasts',
        'directory_title'        => 'Episodios y Podcasts',
        'directory_desc'         => 'Explora todos los episodios de nuestro podcast, escucha en el navegador y accede a notas y transcripciones completas.',
        'enable_related'         => true,
        'search_transcripts'     => true
    ];

    $file = kutpod_config_file();
    if (!file_exists($file)) {
        // Generar token secreto si es la primera vez
        $defaults['webhook_secret'] = bin2hex(random_bytes(16));
        kutpod_save_config($defaults);
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true) ?? [];
    return array_merge($defaults, $data);
}

function kutpod_save_config(array $data): void {
    $file = kutpod_config_file();
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── Parser Universal de URLs de KutPod ─────────────────────────────────────────
/**
 * Parsea cualquier formato de URL de KutPod (pública, embed, tracker o API)
 * y extrae base_url, podcast_slug (sin @), episode_slug y episode_id.
 */
function kutpod_parse_episode_url(string $url): ?array {
    $url = trim($url);
    if (empty($url)) return null;

    // Limpiar caracteres circundantes de enlaces Markdown o etiquetas HTML
    $url = trim($url, " \t\n\r\0\x0B<>\"'()");

    $parts = @parse_url($url);
    if (!$parts || empty($parts['path'])) return null;

    $base = '';
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port']) && !in_array((int)$parts['port'], [80, 443])) {
            $base .= ':' . $parts['port'];
        }
    }

    $path = trim($parts['path'], '/');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $podcastSlug = '';
    $episodeSlug = '';
    $episodeId = 0;

    // 1. Parámetros Query (?id=42 o ?show=flash&ep=slug)
    if (!empty($query['id']) && is_numeric($query['id'])) {
        $episodeId = (int)$query['id'];
    }
    if (!empty($query['show'])) {
        $podcastSlug = ltrim($query['show'], '@');
    }
    if (!empty($query['ep'])) {
        $episodeSlug = $query['ep'];
    }

    // 2. Coincidencias en el Path
    if (!$episodeSlug && !$episodeId) {
        // Embed con show: embed/@show/ep o embed/show/ep
        if (preg_match('#^embed/@?([a-z0-9\-]+)/([a-z0-9\-]+)#i', $path, $m)) {
            $podcastSlug = $m[1];
            $episodeSlug = $m[2];
        }
        // Embed de un solo slug o ID: embed/123 o embed/mi-ep
        elseif (preg_match('#^embed/([a-z0-9\-]+)#i', $path, $m)) {
            if (is_numeric($m[1])) {
                $episodeId = (int)$m[1];
            } else {
                $episodeSlug = $m[1];
            }
        }
        // Tracker URL de OP3: r/show/ep o r/show/ep/audio.mp3
        elseif (preg_match('#^r/([a-z0-9\-]+)/([a-z0-9\-]+)#i', $path, $m)) {
            $podcastSlug = $m[1];
            $episodeSlug = $m[2];
        }
        // Episodio público con show: @show/episodes/ep o @show/ep o shows/show/ep
        elseif (preg_match('#^(?:shows/|@)?([a-z0-9\-]+)/(?:episodes/)?([a-z0-9\-]+)#i', $path, $m)) {
            $ignored = ['episodes', 'admin', 'api', 'install', 'p', 'site.php', 'index.php', 'feed', 'users', 'r'];
            if (!in_array(strtolower($m[1]), $ignored)) {
                $podcastSlug = $m[1];
                $episodeSlug = $m[2];
            } elseif (strtolower($m[1]) === 'episodes') {
                $episodeSlug = $m[2];
            }
        }
        // Episodio directo: episodes/ep-slug o episode/ep-slug
        elseif (preg_match('#^(?:episodes|episode)/([a-z0-9\-]+)#i', $path, $m)) {
            $episodeSlug = $m[1];
        }
    }

    if (!$episodeSlug && !$episodeId) {
        return null;
    }

    return [
        'base_url'     => $base,
        'podcast_slug' => $podcastSlug,
        'episode_slug' => $episodeSlug,
        'episode_id'   => $episodeId,
        'raw_url'      => $url,
    ];
}

/**
 * Conexión opcional a la base de datos local SQLite de KutPod
 */
function kutpod_db(): ?PDO {
    static $pdo = null;
    static $attempted = false;
    if ($attempted) return $pdo;
    $attempted = true;

    $cfg = kutpod_config();
    $dbPath = $cfg['local_db_path'] ?? '';
    if (!file_exists($dbPath) || !extension_loaded('pdo_sqlite')) {
        return null;
    }

    try {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 2,
        ]);
        return $pdo;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Consulta un episodio en la BD local de KutPod si está disponible
 */
function kutpod_lookup_episode(?string $podcastSlug, ?string $episodeSlug, int $episodeId = 0): ?array {
    $db = kutpod_db();
    if (!$db) return null;

    try {
        if ($episodeId > 0) {
            $stmt = $db->prepare("
                SELECT e.*, p.slug as podcast_slug, p.title as podcast_title, p.cover_image as podcast_cover
                FROM episodes e
                JOIN podcasts p ON e.podcast_id = p.id
                WHERE e.id = ?
                LIMIT 1
            ");
            $stmt->execute([$episodeId]);
            $r = $stmt->fetch();
            if ($r) return $r;
        }

        if ($podcastSlug && $episodeSlug) {
            $stmt = $db->prepare("
                SELECT e.*, p.slug as podcast_slug, p.title as podcast_title, p.cover_image as podcast_cover
                FROM episodes e
                JOIN podcasts p ON e.podcast_id = p.id
                WHERE p.slug = ? AND e.slug = ?
                LIMIT 1
            ");
            $stmt->execute([$podcastSlug, $episodeSlug]);
            $r = $stmt->fetch();
            if ($r) return $r;
        }

        if ($episodeSlug) {
            $stmt = $db->prepare("
                SELECT e.*, p.slug as podcast_slug, p.title as podcast_title, p.cover_image as podcast_cover
                FROM episodes e
                JOIN podcasts p ON e.podcast_id = p.id
                WHERE e.slug = ?
                LIMIT 1
            ");
            $stmt->execute([$episodeSlug]);
            $r = $stmt->fetch();
            if ($r) return $r;
        }
    } catch (\Throwable $e) {}

    return null;
}

/**
 * Resuelve y enriquece metadatos a partir de una URL de KutPod
 */
function kutpod_resolve_meta_from_url(string $url, array $existingMeta = []): array {
    $parsed = kutpod_parse_episode_url($url);
    if (!$parsed) return $existingMeta;

    $meta = $existingMeta;
    $meta['is_podcast'] = true;
    $meta['source_url'] = $parsed['raw_url'];

    if (!empty($parsed['podcast_slug'])) {
        $meta['podcast_slug'] = $parsed['podcast_slug'];
    }
    if (!empty($parsed['episode_slug'])) {
        $meta['episode_slug'] = $parsed['episode_slug'];
    }
    if (!empty($parsed['episode_id'])) {
        $meta['episode_id'] = $parsed['episode_id'];
    }

    $dbEp = kutpod_lookup_episode($meta['podcast_slug'] ?? '', $meta['episode_slug'] ?? '', $meta['episode_id'] ?? 0);
    if ($dbEp) {
        $meta['podcast_slug']  = $dbEp['podcast_slug'] ?? ($meta['podcast_slug'] ?? '');
        $meta['podcast_title'] = $dbEp['podcast_title'] ?? ($meta['podcast_title'] ?? '');
        $meta['episode_slug']  = $dbEp['slug'] ?? ($meta['episode_slug'] ?? '');
        $meta['episode_id']    = (int)($dbEp['id'] ?? 0);
        $meta['duration_secs'] = (int)($dbEp['duration_secs'] ?? 0);
        $meta['duration_fmt']  = $dbEp['duration_fmt'] ?? ($meta['duration_secs'] ? gmdate('H:i:s', $meta['duration_secs']) : '');
        $meta['season']        = (int)($dbEp['season'] ?? 0);
        $meta['number']        = (int)($dbEp['number'] ?? 0);
        $meta['downloads']     = (int)($dbEp['downloads_op3'] ?? 0);
        if (!empty($dbEp['audio_url'])) {
            $meta['audio_url'] = $dbEp['audio_url'];
        }
        if (empty($meta['transcript']) && !empty($dbEp['transcript'])) {
            $meta['transcript'] = $dbEp['transcript'];
        }
    }

    return $meta;
}

// ── Enrutador de peticiones del Plugin ────────────────────────────────────────
function kutpod_route_request(string $path, array $segments): bool {
    $cfg = kutpod_config();

    // 1. Webhook receptor
    if ($path === 'api/kutpod/webhook' || $path === 'api/kutpod-webhook') {
        require __DIR__ . '/webhook.php';
        return true;
    }

    // 2. Sincronización manual / API
    if ($path === 'api/kutpod/sync') {
        require_once __DIR__ . '/sync.php';
        $stats = kutpod_run_sync();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stats);
        return true;
    }

    // 3. Servir assets estáticos del plugin con cabeceras correctas
    if ($path === 'plugins/kutpod/assets/kutpod-blog.css') {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        readfile(__DIR__ . '/assets/kutpod-blog.css');
        return true;
    }
    if ($path === 'plugins/kutpod/assets/kutpod-blog.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        readfile(__DIR__ . '/assets/kutpod-blog.js');
        return true;
    }

    // 4. Directorio público de episodios (/podcasts o /episodios)
    $dirSlug = $cfg['directory_slug'] ?: 'podcasts';
    if ($cfg['enable_directory'] && ($path === $dirSlug || $path === 'podcasts' || $path === 'episodios')) {
        $header_file = theme_file('header.php');
        if (file_exists($header_file)) include $header_file;
        kutpod_theme_header('directory', []);
        require __DIR__ . '/templates/directory.php';
        kutpod_theme_footer('directory', []);
        $footer_file = theme_file('footer.php');
        if (file_exists($footer_file)) include $footer_file;
        return true;
    }

    return false;
}

// ── Filtro de contenido para artículos ─────────────────────────────────────────
function kutpod_filter_content(string $content, array $post): string {
    $cfg = kutpod_config();
    $meta = $post['podcast_meta'] ?? [];

    // 1. Detectar si hay una URL en kutpod_url o en el cuerpo
    $parsed = null;
    $foundUrl = '';

    if (!empty($post['kutpod_url'])) {
        $parsed = kutpod_parse_episode_url($post['kutpod_url']);
        if ($parsed) $foundUrl = $post['kutpod_url'];
    }

    if (!$parsed) {
        // Shortcode [kutpod url="..."]
        if (preg_match('/\[kutpod\s+url=["\']([^"\']+)["\']\]/i', $content, $sm)) {
            $parsed = kutpod_parse_episode_url($sm[1]);
            if ($parsed) $foundUrl = $sm[0];
        }
        // Buscar cualquier URL de KutPod en el texto
        elseif (preg_match_all('#https?://[^\s"\'<>()]+#i', $content, $allUrls)) {
            foreach ($allUrls[0] as $u) {
                $pu = kutpod_parse_episode_url($u);
                if ($pu) {
                    $parsed = $pu;
                    $foundUrl = $u;
                    break;
                }
            }
        }
    }

    $isPodcast = !empty($meta['is_podcast']) || !empty($meta['audio_url']) || !empty($parsed);

    // 1. Caso Artículo de Podcast
    if ($isPodcast) {
        // Enriquecer $meta con los datos parseados si faltan
        if ($parsed) {
            if (empty($meta['podcast_slug']) && !empty($parsed['podcast_slug'])) {
                $meta['podcast_slug'] = $parsed['podcast_slug'];
            }
            if (empty($meta['episode_slug']) && !empty($parsed['episode_slug'])) {
                $meta['episode_slug'] = $parsed['episode_slug'];
            }
            if (empty($meta['episode_id']) && !empty($parsed['episode_id'])) {
                $meta['episode_id'] = $parsed['episode_id'];
            }
        }

        // Si tenemos BD local, podemos rellenar estadísticas/slugs faltantes
        if (!empty($meta['episode_slug']) || !empty($meta['episode_id'])) {
            $dbEp = kutpod_lookup_episode($meta['podcast_slug'] ?? '', $meta['episode_slug'] ?? '', $meta['episode_id'] ?? 0);
            if ($dbEp) {
                if (empty($meta['podcast_slug'])) $meta['podcast_slug'] = $dbEp['podcast_slug'];
                if (empty($meta['episode_slug'])) $meta['episode_slug'] = $dbEp['slug'];
                if (empty($meta['downloads']) && !empty($dbEp['downloads_op3'])) $meta['downloads'] = (int)$dbEp['downloads_op3'];
                if (empty($meta['duration_secs']) && !empty($dbEp['duration_secs'])) $meta['duration_secs'] = (int)$dbEp['duration_secs'];
                if (empty($meta['duration_fmt']) && !empty($dbEp['duration_fmt'])) $meta['duration_fmt'] = $dbEp['duration_fmt'];
                if (empty($meta['season']) && !empty($dbEp['season'])) $meta['season'] = (int)$dbEp['season'];
                if (empty($meta['number']) && !empty($dbEp['number'])) $meta['number'] = (int)$dbEp['number'];
                if (empty($meta['transcript']) && !empty($dbEp['transcript'])) $meta['transcript'] = $dbEp['transcript'];
                if (empty($meta['podcast_title']) && !empty($dbEp['podcast_title'])) $meta['podcast_title'] = $dbEp['podcast_title'];
            }
        }

        // Determinar host/base de KutPod (priorizar la de la URL ingresada si es válida)
        $instanceUrl = (!empty($parsed['base_url']))
            ? rtrim($parsed['base_url'], '/')
            : rtrim($cfg['instance_url'] ?: 'http://localhost:8000', '/');

        $pSlug  = trim($meta['podcast_slug'] ?? ($parsed['podcast_slug'] ?? ''));
        $epSlug = trim($meta['episode_slug'] ?? ($parsed['episode_slug'] ?? ''));
        $epId   = (int)($meta['episode_id'] ?? ($parsed['episode_id'] ?? 0));
        $theme  = $cfg['player_theme'] ?? 'auto';

        // Si todavía no tenemos epSlug ni epId, intentar usar el slug del post como último recurso
        if (!$epSlug && !$epId && !empty($post['slug'])) {
            $epSlug = $post['slug'];
        }

        // Construir embedSrc correcto sin forzar @default
        $embedSrc = '';
        if ($epId > 0) {
            $embedSrc = $instanceUrl . "/embed/" . $epId . "?theme=" . urlencode($theme);
        } elseif ($pSlug && $epSlug) {
            $embedSrc = $instanceUrl . "/embed/@" . rawurlencode($pSlug) . "/" . rawurlencode($epSlug) . "?theme=" . urlencode($theme);
        } elseif ($epSlug) {
            $embedSrc = $instanceUrl . "/embed/" . rawurlencode($epSlug) . "?theme=" . urlencode($theme);
        }

        if (!$embedSrc) {
            return $content;
        }

        // Construir barra de estadísticas
        $statsBar = '';
        if (!empty($cfg['enable_stats_badge'])) {
            $downloads = (int)($meta['downloads'] ?? 0);
            $durFmt = $meta['duration_fmt'] ?? (!empty($meta['duration_secs']) ? gmdate('H:i:s', $meta['duration_secs']) : '');
            $season = (int)($meta['season'] ?? 0);
            $number = (int)($meta['number'] ?? 0);

            $statsBar = '<div class="kutpod-stats-bar">';
            if ($season > 0 && $number > 0) {
                $statsBar .= '<span class="kutpod-stat-pill">🎙️ T' . $season . ' · Ep ' . $number . '</span>';
            } elseif (!empty($meta['podcast_title'])) {
                $statsBar .= '<span class="kutpod-stat-pill">🎙️ ' . htmlspecialchars($meta['podcast_title']) . '</span>';
            }
            if ($durFmt) {
                $statsBar .= '<span class="kutpod-stat-pill">⏱️ ' . htmlspecialchars($durFmt) . '</span>';
            }
            if ($downloads > 0) {
                $popularClass = ($downloads > 500) ? ' kutpod-stat-popular' : '';
                $statsBar .= '<span class="kutpod-stat-pill' . $popularClass . '">🎧 ' . number_format($downloads) . ' escuchas</span>';
            }
            $statsBar .= '</div>';
        }

        // Construir HTML del reproductor
        $playerHtml = '
        <div class="kutpod-player-container">
          <iframe id="kutpod-player-frame" src="' . htmlspecialchars($embedSrc) . '" class="kutpod-player-frame" allow="autoplay" loading="lazy"></iframe>
          ' . $statsBar . '
        </div>';

        // Procesar Timestamps Interactivos en el texto
        if (!empty($cfg['enable_timestamps'])) {
            $content = preg_replace_callback(
                '/(?:^|[\(\[\s])(\d{1,2}:\d{2}(?::\d{2})?)(?:[\)\]\s])\s*([-–—:]?\s*)([^\n<]+)/u',
                function($m) {
                    $timeStr = $m[1];
                    $sep = $m[2];
                    $title = $m[3];

                    $parts = array_map('intval', explode(':', $timeStr));
                    $secs = (count($parts) === 3) ? ($parts[0] * 3600 + $parts[1] * 60 + $parts[2]) : ($parts[0] * 60 + $parts[1]);

                    return ' <button type="button" class="kp-timestamp" data-time="' . $secs . '" title="Saltar al minuto ' . htmlspecialchars($timeStr) . '">'
                         . '<svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg> '
                         . htmlspecialchars($timeStr) . '</button> ' . $sep . $title;
                },
                $content
            );
        }

        // Transcripción colapsable (SEO)
        $transcriptHtml = '';
        if (!empty($cfg['enable_transcripts']) && !empty($meta['transcript'])) {
            $transcriptHtml = '
            <details class="kutpod-transcript-details">
              <summary class="kutpod-transcript-summary">
                <span>📝 Transcripción completa del episodio</span>
                <span style="font-size: 0.8rem; opacity: 0.7;">Click para expandir</span>
              </summary>
              <div class="kutpod-transcript-body">' . htmlspecialchars($meta['transcript']) . '</div>
            </details>';
        }

        // Limpiar la URL raw del contenido si el usuario la pegó sola en un párrafo o línea
        if (!empty($foundUrl) && empty($post['kutpod_url'])) {
            $content = preg_replace('#<p>\s*' . preg_quote($foundUrl, '#') . '\s*</p>#i', '', $content);
            $content = preg_replace('#^\s*' . preg_quote($foundUrl, '#') . '\s*$#m', '', $content);
        }

        // Posicionar el reproductor
        $pos = $cfg['player_position'] ?? 'top';
        if ($pos === 'manual' && str_contains($content, '[kutpod]')) {
            $content = str_replace('[kutpod]', $playerHtml, $content);
        } elseif (str_contains($content, '[kutpod]')) {
            $content = str_replace('[kutpod]', $playerHtml, $content);
        } elseif ($pos === 'bottom') {
            $content = $content . $playerHtml . $transcriptHtml;
        } else {
            // Top por defecto
            $content = $playerHtml . $content . $transcriptHtml;
        }

        return $content;
    }

    // 2. Caso Artículo Estándar (No Podcast) -> Mostrar Episodio Relacionado al pie
    if (!empty($cfg['enable_related'])) {
        $related = kutpod_find_related_episode($post);
        if ($related) {
            $content .= kutpod_render_related_box($related, $cfg);
        }
    }

    return $content;
}

/**
 * Busca un episodio relacionado con este artículo según categorías o etiquetas
 */
function kutpod_find_related_episode(array $post): ?array {
    $postCats = array_map('strtolower', $post['categories'] ?? []);
    $postTags = array_map('strtolower', $post['tags'] ?? []);
    if (empty($postCats) && empty($postTags)) return null;

    $all = list_content('articles', true, 1, 9999)['items'] ?? [];
    foreach ($all as $item) {
        if (empty($item['podcast_meta']['is_podcast']) && empty($item['podcast_meta']['audio_url'])) {
            continue;
        }
        if (($item['slug'] ?? '') === ($post['slug'] ?? '')) continue;

        $itemCats = array_map('strtolower', $item['categories'] ?? []);
        $itemTags = array_map('strtolower', $item['tags'] ?? []);

        $commonCats = array_intersect($postCats, $itemCats);
        $commonTags = array_intersect($postTags, $itemTags);

        if (!empty($commonCats) || !empty($commonTags)) {
            return $item;
        }
    }
    return null;
}

/**
 * Renderiza el bloque de episodio relacionado
 */
function kutpod_render_related_box(array $ep, array $cfg): string {
    $base = base_url();
    $instanceUrl = rtrim($cfg['instance_url'] ?: 'http://localhost:8000', '/');
    $meta = $ep['podcast_meta'] ?? [];
    $pSlug = $meta['podcast_slug'] ?? 'default';
    $epSlug = $meta['episode_slug'] ?? $ep['slug'];
    $durFmt = $meta['duration_fmt'] ?: ($meta['duration_secs'] ? gmdate('H:i:s', $meta['duration_secs']) : '');
    $cover = $ep['featured_image'] ?: ($base . '/plugins/kutpod/assets/cover-placeholder.png');
    $embedSrc = $instanceUrl . "/embed/@" . rawurlencode($pSlug) . "/" . rawurlencode($epSlug) . "?theme=auto";

    return '
    <div class="kutpod-related-card">
      <div class="kutpod-related-header">
        <span>🎙️ ¿Te apasiona este tema? Escucha el episodio en nuestro Podcast</span>
      </div>
      <div class="kutpod-related-content">
        <img src="' . htmlspecialchars($cover) . '" alt="' . htmlspecialchars($ep['title']) . '" class="kutpod-related-cover">
        <div class="kutpod-related-info">
          <h4 class="kutpod-related-title">
            <a href="' . $base . '/article/' . htmlspecialchars($ep['slug']) . '">' . htmlspecialchars($ep['title']) . '</a>
          </h4>
          <div class="kutpod-related-meta">
            ' . ($durFmt ? '⏱️ ' . htmlspecialchars($durFmt) . ' · ' : '') . '
            <a href="' . $base . '/article/' . htmlspecialchars($ep['slug']) . '" style="color:var(--kp-accent, #ff5f7e); font-weight:600; text-decoration:none;">Escuchar y ver notas completas →</a>
          </div>
        </div>
      </div>
      <div style="margin-top:12px; border-radius:10px; overflow:hidden;">
        <iframe src="' . htmlspecialchars($embedSrc) . '" style="width:100%; height:135px; border:none;" allow="autoplay" loading="lazy"></iframe>
      </div>
    </div>';
}

// ── Inyección de Scripts y Metadatos en Header y Footer ────────────────────────
function kutpod_theme_header(string $template = '', array $vars = []): void {
    $base = base_url();
    echo "\n  <!-- KutPod Podcast Integration Styles -->\n";
    echo "  <link rel=\"stylesheet\" href=\"{$base}/plugins/kutpod/assets/kutpod-blog.css\">\n";

    // Si es un post con podcast, inyectar Schema.org PodcastEpisode y OpenGraph Audio
    $post = $vars['post'] ?? null;
    if ($post && !empty($post['podcast_meta']['is_podcast'])) {
        $meta = $post['podcast_meta'];
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'PodcastEpisode',
            'name'          => $post['title'],
            'description'   => $post['excerpt'] ?: strip_tags($post['content']),
            'datePublished' => $post['created_at'],
            'url'           => $base . '/article/' . $post['slug']
        ];
        if (!empty($meta['audio_url'])) {
            $schema['associatedMedia'] = [
                '@type'         => 'AudioObject',
                'contentUrl'    => $meta['audio_url'],
                'duration'      => !empty($meta['duration_secs']) ? 'PT' . $meta['duration_secs'] . 'S' : null
            ];
            echo "  <meta property=\"og:audio\" content=\"" . htmlspecialchars($meta['audio_url']) . "\">\n";
            echo "  <meta property=\"og:audio:type\" content=\"audio/mpeg\">\n";
        }
        echo "  <script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}

function kutpod_theme_footer(string $template = '', array $vars = []): void {
    $base = base_url();
    echo "\n  <!-- KutPod Interactive Reader Script -->\n";
    echo "  <script src=\"{$base}/plugins/kutpod/assets/kutpod-blog.js\" defer></script>\n";
}
