<?php
/**
 * KutPod Plugin for BrisaCMS
 * Main Bootstrapper and Extension Manager
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
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
    $isPodcast = !empty($meta['is_podcast']) || !empty($meta['audio_url']) || !empty($post['kutpod_url']);

    // Si no tiene meta explícito, verificar si contiene URL de KutPod para auto-embed
    if (!$isPodcast) {
        if (preg_match('#https?://[^\s]+/embed/@([a-z0-9\-]+)/([a-z0-9\-]+)|https?://[^\s]+/@([a-z0-9\-]+)/([a-z0-9\-]+)#i', $content, $m)) {
            $isPodcast = true;
            $meta['podcast_slug'] = !empty($m[1]) ? $m[1] : $m[3];
            $meta['episode_slug'] = !empty($m[2]) ? $m[2] : $m[4];
        }
    }

    // 1. Caso Artículo de Podcast
    if ($isPodcast) {
        $instanceUrl = rtrim($cfg['instance_url'] ?: 'http://localhost:8000', '/');
        $pSlug = $meta['podcast_slug'] ?? 'default';
        $epSlug = $meta['episode_slug'] ?? $post['slug'];
        $theme = $cfg['player_theme'] ?? 'auto';
        $embedSrc = $instanceUrl . "/embed/@" . rawurlencode($pSlug) . "/" . rawurlencode($epSlug) . "?theme=" . urlencode($theme);

        // Construir barra de estadísticas
        $statsBar = '';
        if (!empty($cfg['enable_stats_badge'])) {
            $downloads = (int)($meta['downloads'] ?? 0);
            $durFmt = $meta['duration_fmt'] ?: ($meta['duration_secs'] ? gmdate('H:i:s', $meta['duration_secs']) : '');
            $season = (int)($meta['season'] ?? 0);
            $number = (int)($meta['number'] ?? 0);

            $statsBar = '<div class="kutpod-stats-bar">';
            if ($season > 0 && $number > 0) {
                $statsBar .= '<span class="kutpod-stat-pill">🎙️ T' . $season . ' · Ep ' . $number . '</span>';
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

        // Posicionar el reproductor
        $pos = $cfg['player_position'] ?? 'top';
        if ($pos === 'manual' && str_contains($content, '[kutpod]')) {
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
