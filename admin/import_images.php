<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

if (!defined('MEDIA_PATH')) define('MEDIA_PATH', ROOT_PATH . '/media');
if (!is_dir(MEDIA_PATH)) mkdir(MEDIA_PATH, 0755, true);

$csrf  = generate_csrf();
$log   = [];
$done  = false;

function import_download_image(string $url): ?string {
    if (!preg_match('#^https?://#i', $url)) return null;

    $parsed   = parse_url($url);
    $url_path = $parsed['path'] ?? '';

    if (preg_match('#/uploads/(.+)$#', $url_path, $m)) {
        $rel_path = $m[1];
    } else {
        $rel_path = ltrim($url_path, '/');
    }

    $parts    = explode('/', $rel_path);
    $parts    = array_map(fn($p) => preg_replace('/[^a-zA-Z0-9._\-]/', '-', $p), $parts);
    $rel_path = implode('/', $parts);

    $dest_dir  = MEDIA_PATH . '/' . dirname($rel_path);
    $dest_file = MEDIA_PATH . '/' . $rel_path;

    if (file_exists($dest_file)) return $rel_path;
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    $ctx  = stream_context_create(['http' => [
        'timeout'         => 15,
        'follow_location' => true,
        'header'          => "User-Agent: BrisaCMS/1.0\r\n",
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 100) return null;

    file_put_contents($dest_file, $data);
    return $rel_path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    @set_time_limit(300);
    $base_url = rtrim(base_url(), '/');
    $count    = 0;

    foreach (['articles', 'pages'] as $type) {
        $dir = CONTENT_PATH . '/' . $type;
        if (!is_dir($dir)) continue;

        foreach (glob("$dir/*.json") as $file) {
            $post = json_decode(file_get_contents($file), true);
            if (empty($post['content'])) continue;

            $original  = $post['content'];
            $changed   = false;

            // Rewrite <img src="..."> tags
            $post['content'] = preg_replace_callback(
                '/(<img[^>]+\ssrc=")([^"]+)("[^>]*>)/i',
                function($m) use ($base_url, &$count, &$changed) {
                    $src = $m[2];
                    if (!preg_match('#^https?://#i', $src)) return $m[0];
                    $host = parse_url($base_url, PHP_URL_HOST) ?? '';
                    if ($host && str_contains($src, $host)) return $m[0];
                    $rel = import_download_image($src);
                    if (!$rel) return $m[0];
                    $changed = true;
                    $count++;
                    return $m[1] . $base_url . '/media/' . $rel . $m[3];
                },
                $post['content']
            );

            // Rewrite featured_image
            if (!empty($post['featured_image'])) {
                $src = $post['featured_image'];
                $host = parse_url($base_url, PHP_URL_HOST) ?? '';
                if (preg_match('#^https?://#i', $src) && !str_contains($src, $host)) {
                    $rel = import_download_image($src);
                    if ($rel) {
                        $post['featured_image'] = $base_url . '/media/' . $rel;
                        $changed = true;
                        $count++;
                    }
                }
            }

            if ($changed) {
                file_put_contents($file, json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $log[] = ['ok', "Actualizado: " . ($post['title'] ?? basename($file))];
            }
        }
    }

    clear_cache();
    $log[] = ['done', "Completado. $count imágenes descargadas y URLs reescritas."];
    $done  = true;
}

admin_header('Importar Imágenes', 'import_images');
?>
    </div>
  </div>
  <div class="page-body">
    <div style="max-width:600px">
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">Descargar imágenes externas a /media/</span></div>
        <div class="card-body">
          <p style="color:var(--text2);font-size:0.9rem;margin-bottom:1rem">
            Escanea todos los artículos y páginas, descarga las imágenes que apuntan a dominios
            externos y reescribe las URLs para que apunten a <code style="background:var(--bg);padding:1px 6px;border-radius:4px">/media/</code>.
            Útil si ya importaste artículos sin descargar las imágenes.
          </p>
          <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:0.85rem;font-size:0.85rem;color:var(--yellow);margin-bottom:1.25rem">
            ⚠ Puede tardar varios minutos si hay muchas imágenes. No cierres la página.
          </div>
          <form method="POST" onsubmit="document.getElementById('prog').style.display='block';this.querySelector('button').disabled=true">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <button type="submit" class="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Iniciar descarga de imágenes
            </button>
          </form>
          <div id="prog" style="display:none;margin-top:1rem;color:var(--muted);font-size:0.875rem">
            <div style="display:inline-block;width:14px;height:14px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;margin-right:0.5rem;vertical-align:middle"></div>
            Descargando imágenes…
          </div>
        </div>
      </div>

      <?php if (!empty($log)): ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Resultado</span>
          <?php if ($done): ?><span class="badge badge-green">Completado</span><?php endif; ?>
        </div>
        <div class="card-body">
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:1rem;font-family:monospace;font-size:0.8rem;line-height:1.9;max-height:350px;overflow-y:auto">
            <?php foreach ($log as [$t, $msg]): ?>
            <div style="color:<?= $t === 'ok' ? 'var(--green)' : ($t === 'done' ? 'var(--green)' : 'var(--red)') ?>">
              <?= htmlspecialchars($msg) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
</body></html>
