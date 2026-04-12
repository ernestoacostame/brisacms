<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$csrf = generate_csrf();

// Handle export download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $format = $_POST['format'] ?? 'zip';
    $include_media = ($_POST['include_media'] ?? '0') === '1';

    if ($format === 'zip') {
        $zip_file = tempnam(sys_get_temp_dir(), 'brisa_export_');
        $zip = new ZipArchive();
        $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add content
        foreach (['articles', 'pages'] as $type) {
            $dir = CONTENT_PATH . '/' . $type;
            if (!is_dir($dir)) continue;
            foreach (glob("$dir/*.json") as $file) {
                $zip->addFile($file, "content/$type/" . basename($file));
            }
        }

        // Add config (without password hash for safety)
        $config = cms_config();
        $safe_config = $config;
        unset($safe_config['admin_pass']);
        $zip->addFromString('config_export.json', json_encode($safe_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Optionally add media
        if ($include_media && is_dir(ROOT_PATH . '/media')) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH . '/media'));
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                    $rel = 'media/' . str_replace(ROOT_PATH . '/media/', '', $file->getPathname());
                    $zip->addFile($file->getPathname(), $rel);
                }
            }
        }

        $zip->close();

        $filename = 'brisacms-export-' . date('Y-m-d') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zip_file));
        readfile($zip_file);
        unlink($zip_file);
        exit;
    }
}

// Count stats for display
$articles = list_content('articles', false, 1, 9999);
$pages    = list_content('pages', false, 1, 9999);
$media_count = count(glob(ROOT_PATH . '/media/*.{jpg,jpeg,png,gif,webp,svg,avif}', GLOB_BRACE));
$media_size  = 0;
if (is_dir(ROOT_PATH . '/media')) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH . '/media')) as $f) {
        if ($f->isFile()) $media_size += $f->getSize();
    }
}

admin_header('Exportar contenido', 'export');
?>
    </div>
  </div>
  <div class="page-body">
    <div style="max-width:580px">
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">Exportar todo el contenido</span></div>
        <div class="card-body">

          <!-- Stats -->
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:1.5rem">
            <div style="background:var(--surface2);border-radius:8px;padding:0.85rem;text-align:center">
              <div style="font-size:1.5rem;font-weight:700;color:var(--accent)"><?= $articles['total'] ?></div>
              <div style="font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Artículos</div>
            </div>
            <div style="background:var(--surface2);border-radius:8px;padding:0.85rem;text-align:center">
              <div style="font-size:1.5rem;font-weight:700;color:var(--accent)"><?= $pages['total'] ?></div>
              <div style="font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Páginas</div>
            </div>
            <div style="background:var(--surface2);border-radius:8px;padding:0.85rem;text-align:center">
              <div style="font-size:1.5rem;font-weight:700;color:var(--accent)"><?= round($media_size/1024/1024,1) ?> MB</div>
              <div style="font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Media</div>
            </div>
          </div>

          <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="format" value="zip">

            <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:1rem;margin-bottom:1rem">
              <div style="font-weight:500;margin-bottom:0.5rem">El ZIP incluye:</div>
              <ul style="font-size:0.875rem;color:var(--text2);line-height:2;padding-left:1.25rem">
                <li>Todos los artículos en <code style="background:var(--bg);padding:1px 5px;border-radius:4px">content/articles/</code></li>
                <li>Todas las páginas en <code style="background:var(--bg);padding:1px 5px;border-radius:4px">content/pages/</code></li>
                <li>Configuración del sitio (sin contraseña)</li>
              </ul>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:0.85rem;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:0.75rem">
              <input type="checkbox" name="include_media" value="1" id="inc-media"
                style="width:16px;height:16px;accent-color:var(--accent);flex-shrink:0;margin-top:2px">
              <div>
                <label for="inc-media" style="font-weight:500;cursor:pointer">Incluir imágenes de /media/</label>
                <div style="font-size:0.78rem;color:var(--muted);margin-top:2px">
                  Añade <?= round($media_size/1024/1024,1) ?> MB al ZIP. Solo necesario si quieres mover el blog a otro servidor.
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Descargar ZIP
            </button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Respaldo manual</span></div>
        <div class="card-body" style="font-size:0.875rem;color:var(--text2);line-height:1.8">
          <p style="margin-bottom:0.75rem">Para mover el blog completo a otro servidor, copia estas tres cosas:</p>
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:1rem;font-family:monospace;font-size:0.82rem;line-height:2">
            <div style="color:var(--green)">/var/www/html/viyoer/content/</div>
            <div style="color:var(--green)">/var/www/html/viyoer/media/</div>
            <div style="color:var(--green)">/var/www/html/viyoer/config.json</div>
          </div>
          <p style="margin-top:0.75rem;font-size:0.82rem">Los archivos PHP del CMS se reinstalan desde cero con el ZIP de BrisaCMS.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body></html>
