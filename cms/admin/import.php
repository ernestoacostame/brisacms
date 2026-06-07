<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$csrf = generate_csrf();
$log  = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $log[] = '❌ Security error.';
    } elseif (!empty($_FILES['wxr']['tmp_name']) && $_FILES['wxr']['error'] === UPLOAD_ERR_OK) {
        // Raise limits for potentially long imports
        @set_time_limit(300);
        @ini_set('memory_limit', '256M');

        $xml            = file_get_contents($_FILES['wxr']['tmp_name']);
        $download_imgs  = ($_POST['download_images'] ?? '1') === '1';
        $log            = import_wordpress_xml($xml, $download_imgs);
        $done           = true;
    } else {
        $upload_err = $_FILES['wxr']['error'] ?? -1;
        $log[] = match($upload_err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '❌ El archivo es demasiado grande. Aumenta upload_max_filesize en PHP.',
            UPLOAD_ERR_NO_FILE => '❌ No seleccionaste ningún archivo.',
            default => '❌ Error al subir el archivo (código: ' . $upload_err . ').',
        };
    }
}

admin_header('Importar desde WordPress', 'import');
?>
    </div>
  </div>
  <div class="page-body">
    <div style="max-width:680px">
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><span class="card-title">Importar contenido de WordPress</span></div>
        <div class="card-body">
          <p style="color:var(--text2);font-size:0.9rem;margin-bottom:1.25rem">
            En tu WordPress: <strong>Herramientas → Exportar → Todo el contenido → Descargar</strong>.
            Sube el archivo <code style="background:var(--bg);padding:1px 6px;border-radius:4px">.xml</code> aquí.
          </p>

          <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:1rem;font-size:0.85rem;color:var(--text2);margin-bottom:1.25rem;line-height:1.7">
            <strong style="color:var(--text)">¿Qué se importa?</strong><br>
            ✓ Posts → Artículos &nbsp;·&nbsp; Páginas → Páginas<br>
            ✓ Categorías y etiquetas<br>
            ✓ Fecha de publicación y estado (publicado / borrador)<br>
            ✓ Imágenes incrustadas en el contenido → descargadas a <code>/media/</code><br>
            ✓ Imagen destacada (featured image)<br>
            ✗ Comentarios, usuarios, plugins
          </div>

          <form method="POST" enctype="multipart/form-data" id="import-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <div class="form-group">
              <label class="form-label">Archivo de exportación WordPress (.xml)</label>
              <input type="file" name="wxr" accept=".xml,text/xml"
                style="background:var(--bg);border:2px dashed var(--border2);border-radius:8px;padding:1.5rem;cursor:pointer;width:100%;color:var(--text2);font-family:inherit"
                onchange="document.getElementById('file-name').textContent = this.files[0]?.name || ''">
              <div id="file-name" style="font-size:0.78rem;color:var(--accent);margin-top:0.4rem"></div>
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:0.85rem">
              <input type="checkbox" name="download_images" value="1" id="dl-imgs" checked
                style="width:16px;height:16px;accent-color:var(--accent);flex-shrink:0">
              <div>
                <label for="dl-imgs" style="font-weight:500;cursor:pointer">Descargar imágenes automáticamente</label>
                <div style="font-size:0.78rem;color:var(--muted);margin-top:2px">
                  Descarga todas las imágenes del contenido a <code>/media/</code> preservando la estructura de carpetas
                  (ej: <code>/media/2025/02/imagen.jpg</code>) y reescribe las URLs en los artículos.
                </div>
              </div>
            </div>

            <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:0.85rem;font-size:0.82rem;color:var(--yellow);margin-bottom:1rem">
              ⚠ Si tienes muchos artículos con imágenes, el proceso puede tardar varios minutos.
              No cierres la página mientras importa.
            </div>

            <button type="submit" class="btn btn-primary" id="import-btn" onclick="showProgress()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Iniciar importación
            </button>
          </form>

          <div id="progress" style="display:none;margin-top:1rem;padding:1rem;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--muted);font-size:0.875rem;text-align:center">
            <div style="display:inline-block;width:18px;height:18px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;margin-right:0.5rem;vertical-align:middle"></div>
            Importando… No cierres esta página.
          </div>
        </div>
      </div>

      <?php if (!empty($log)): ?>
      <div class="card">
        <div class="card-header" style="gap:0.75rem">
          <span class="card-title">Resultado de la importación</span>
          <?php if ($done): ?><span class="badge badge-green">Completado</span><?php endif; ?>
        </div>
        <div class="card-body">
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:1rem;font-family:monospace;font-size:0.8rem;line-height:1.85;max-height:420px;overflow-y:auto">
            <?php foreach ($log as $line): ?>
            <div style="color:<?= str_starts_with($line, '✓') ? 'var(--green)' :
              (str_starts_with($line, '  ↓') ? '#60a5fa' :
              (str_starts_with($line, '  ⚠') ? 'var(--yellow)' :
              (str_starts_with($line, '❌') ? 'var(--red)' :
              (str_starts_with($line, '✅') ? 'var(--green)' :
              'var(--text2)')))) ?>">
              <?= htmlspecialchars($line) ?>
            </div>
            <?php endforeach; ?>
          </div>

          <?php
          $last = end($log);
          if ($done && str_contains($last, 'Completado')):
          ?>
          <div style="display:flex;gap:0.75rem;margin-top:1rem">
            <a href="<?= base_url() ?>/admin/articles.php" class="btn btn-primary">Ver artículos →</a>
            <a href="<?= base_url() ?>/admin/media.php" class="btn btn-secondary">Ver media</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
function showProgress() {
  setTimeout(() => {
    document.getElementById('progress').style.display = '';
    document.getElementById('import-btn').disabled = true;
  }, 100);
}
</script>
</body></html>
