<?php
// Opciones del Plugin Importador de WordPress para KutBlog

if (!defined('CMS_NAME') && !defined('ROOT_PATH')) {
    exit;
}

require_once __DIR__ . '/wp-import.php';
require_once dirname(__DIR__, 2) . '/core/content.php';

$active_tab = $_GET['tab'] ?? 'xml';
if (!in_array($active_tab, ['xml', 'images'])) {
    $active_tab = 'xml';
}

$csrf     = generate_csrf();
$xml_log  = [];
$xml_done = false;
$img_log  = [];
$img_done = false;
$error    = '';
$msg      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Error de seguridad (CSRF inválido).';
    } else {
        $subaction = $_POST['importer_action'] ?? '';

        if ($subaction === 'import_wxr') {
            $active_tab = 'xml';
            if (!empty($_FILES['wxr']['tmp_name']) && $_FILES['wxr']['error'] === UPLOAD_ERR_OK) {
                @set_time_limit(300);
                @ini_set('memory_limit', '256M');

                $xml_content   = file_get_contents($_FILES['wxr']['tmp_name']);
                $download_imgs = ($_POST['download_images'] ?? '1') === '1';
                $xml_log       = import_wordpress_xml($xml_content, $download_imgs);
                $xml_done      = true;
            } else {
                $upload_err = $_FILES['wxr']['error'] ?? -1;
                $error = match($upload_err) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande. Aumenta upload_max_filesize en PHP.',
                    UPLOAD_ERR_NO_FILE => 'No seleccionaste ningún archivo.',
                    default => 'Error al subir el archivo (código: ' . $upload_err . ').',
                };
            }
        } elseif ($subaction === 'import_images') {
            $active_tab = 'images';
            $res = wp_import_process_all_images();
            $img_log  = $res['log'];
            $img_done = true;
            $msg = "Proceso finalizado. {$res['count']} imágenes descargadas.";
        }
    }
}
?>

<div style="max-width:860px; margin:0 auto">

  <!-- Header with tabs -->
  <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem">
    <div>
      <h2 style="font-size:1.4rem; font-weight:700; margin:0 0 0.25rem; color:var(--text)">Importador de WordPress</h2>
      <p style="font-size:0.88rem; color:var(--text2); margin:0">Herramientas para migrar tu contenido y descargar imágenes hacia KutBlog.</p>
    </div>
    
    <!-- Tab selector -->
    <div style="display:inline-flex; background:var(--surface2); padding:4px; border-radius:10px; border:1px solid var(--border)">
      <button type="button" class="tab-btn <?= $active_tab === 'xml' ? 'active' : '' ?>" onclick="switchWpTab('xml')" id="btn-tab-xml">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Importar XML (.xml)
      </button>
      <button type="button" class="tab-btn <?= $active_tab === 'images' ? 'active' : '' ?>" onclick="switchWpTab('images')" id="btn-tab-images">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Descargar imágenes
      </button>
    </div>
  </div>

  <?php if (!empty($msg)): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Tab 1: XML Import -->
  <div id="tab-content-xml" style="display:<?= $active_tab === 'xml' ? 'block' : 'none' ?>">
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header"><span class="card-title">Importar contenido desde WordPress (.xml)</span></div>
      <div class="card-body">
        <p style="color:var(--text2); font-size:0.9rem; margin-bottom:1.25rem">
          En tu sitio de WordPress ve a: <strong>Herramientas → Exportar → Todo el contenido → Descargar el archivo de exportación</strong>.
          Luego sube aquí el archivo <code style="background:var(--bg); padding:1px 6px; border-radius:4px">.xml</code> generado.
        </p>

        <div style="background:var(--surface2); border:1px solid var(--border2); border-radius:8px; padding:1rem; font-size:0.85rem; color:var(--text2); margin-bottom:1.25rem; line-height:1.7">
          <strong style="color:var(--text)">¿Qué se importa?</strong><br>
          ✓ Posts → Artículos &nbsp;·&nbsp; Páginas → Páginas<br>
          ✓ Categorías y etiquetas<br>
          ✓ Fecha de publicación y estado (publicado / borrador)<br>
          ✓ Imágenes incrustadas en el contenido → descargadas automáticamente a <code>/media/</code><br>
          ✓ Imagen destacada (featured image)<br>
          ✗ Comentarios, usuarios, plugins (se omiten)
        </div>

        <form method="POST" enctype="multipart/form-data" id="import-xml-form" action="<?= base_url() ?>/admin/plugins.php?action=settings&plugin=wp-import&tab=xml">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="importer_action" value="import_wxr">

          <div class="form-group" style="margin-bottom:1.25rem">
            <label class="form-label" style="font-weight:600; margin-bottom:0.5rem; display:block">Archivo de exportación (.xml / WXR)</label>
            <input type="file" name="wxr" accept=".xml,text/xml" id="wxr-file-input" required
              style="background:var(--bg); border:2px dashed var(--border2); border-radius:8px; padding:1.5rem; cursor:pointer; width:100%; color:var(--text2); font-family:inherit"
              onchange="document.getElementById('xml-file-name').textContent = this.files[0] ? ('Archivo seleccionado: ' + this.files[0].name + ' (' + Math.round(this.files[0].size/1024) + ' KB)') : ''">
            <div id="xml-file-name" style="font-size:0.82rem; color:var(--accent); margin-top:0.5rem; font-weight:500"></div>
          </div>

          <div class="form-group" style="display:flex; align-items:center; gap:0.75rem; background:var(--surface2); border:1px solid var(--border2); border-radius:8px; padding:0.85rem; margin-bottom:1.25rem">
            <input type="checkbox" name="download_images" value="1" id="dl-imgs" checked
              style="width:18px; height:18px; accent-color:var(--accent); flex-shrink:0; cursor:pointer">
            <div>
              <label for="dl-imgs" style="font-weight:500; cursor:pointer; color:var(--text)">Descargar imágenes automáticamente</label>
              <div style="font-size:0.78rem; color:var(--muted); margin-top:2px">
                Descarga todas las imágenes del contenido a <code>/media/</code> preservando la estructura de carpetas
                (ej: <code>/media/2025/02/imagen.jpg</code>) y reescribe las URLs en los artículos importados.
              </div>
            </div>
          </div>

          <div style="background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.2); border-radius:8px; padding:0.85rem; font-size:0.82rem; color:var(--yellow); margin-bottom:1.25rem">
            ⚠ Si el archivo contiene muchos artículos o imágenes externas, el proceso puede demorar varios minutos. Por favor no cierres la página.
          </div>

          <button type="submit" class="btn btn-primary" id="import-xml-btn" onclick="startXmlProgress()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Iniciar importación
          </button>
        </form>

        <div id="xml-progress" style="display:none; margin-top:1.25rem; padding:1.25rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text2); font-size:0.875rem; text-align:center">
          <div style="display:inline-block; width:20px; height:20px; border:2px solid var(--accent); border-top-color:transparent; border-radius:50%; animation:wpSpin 0.8s linear infinite; margin-right:0.6rem; vertical-align:middle"></div>
          Importando contenido de WordPress… Por favor espera sin cerrar esta página.
        </div>
      </div>
    </div>

    <?php if (!empty($xml_log)): ?>
    <div class="card">
      <div class="card-header" style="gap:0.75rem">
        <span class="card-title">Resultado de la importación</span>
        <?php if ($xml_done): ?><span class="badge badge-green">Completado</span><?php endif; ?>
      </div>
      <div class="card-body">
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:1rem; font-family:monospace; font-size:0.8rem; line-height:1.85; max-height:420px; overflow-y:auto">
          <?php foreach ($xml_log as $line): ?>
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
        $last = end($xml_log);
        if ($xml_done && str_contains($last, 'Completado')):
        ?>
        <div style="display:flex; gap:0.75rem; margin-top:1.25rem; flex-wrap:wrap">
          <a href="<?= base_url() ?>/admin/articles.php" class="btn btn-primary">Ver artículos →</a>
          <a href="<?= base_url() ?>/admin/pages.php" class="btn btn-secondary">Ver páginas</a>
          <a href="<?= base_url() ?>/admin/media.php" class="btn btn-secondary">Ver multimedia</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tab 2: External Images -->
  <div id="tab-content-images" style="display:<?= $active_tab === 'images' ? 'block' : 'none' ?>">
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header"><span class="card-title">Descargar imágenes externas a /media/</span></div>
      <div class="card-body">
        <p style="color:var(--text2); font-size:0.9rem; margin-bottom:1rem">
          Escanea todos los artículos y páginas existentes de KutBlog, descarga a <code style="background:var(--bg); padding:1px 6px; border-radius:4px">/media/</code> las imágenes que aún apunten a dominios externos y reescribe automáticamente las URLs locales.
        </p>
        <p style="color:var(--text2); font-size:0.85rem; margin-bottom:1.25rem">
          Ideal si importaste un archivo XML desactivando la opción de descarga automática o si migraste contenido externamente.
        </p>

        <div style="background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.2); border-radius:8px; padding:0.85rem; font-size:0.85rem; color:var(--yellow); margin-bottom:1.25rem">
          ⚠ Este proceso puede tardar varios minutos si existen muchas imágenes pendientes. No cierres la ventana.
        </div>

        <form method="POST" id="import-images-form" action="<?= base_url() ?>/admin/plugins.php?action=settings&plugin=wp-import&tab=images" onsubmit="startImagesProgress()">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="importer_action" value="import_images">
          <button type="submit" class="btn btn-primary" id="import-images-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Iniciar descarga de imágenes
          </button>
        </form>

        <div id="images-progress" style="display:none; margin-top:1.25rem; padding:1.25rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text2); font-size:0.875rem; text-align:center">
          <div style="display:inline-block; width:20px; height:20px; border:2px solid var(--accent); border-top-color:transparent; border-radius:50%; animation:wpSpin 0.8s linear infinite; margin-right:0.6rem; vertical-align:middle"></div>
          Escaneando y descargando imágenes a /media/… Por favor espera.
        </div>
      </div>
    </div>

    <?php if (!empty($img_log)): ?>
    <div class="card">
      <div class="card-header" style="gap:0.75rem">
        <span class="card-title">Resultado de la descarga de imágenes</span>
        <?php if ($img_done): ?><span class="badge badge-green">Completado</span><?php endif; ?>
      </div>
      <div class="card-body">
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:1rem; font-family:monospace; font-size:0.8rem; line-height:1.9; max-height:350px; overflow-y:auto">
          <?php foreach ($img_log as [$t, $log_text]): ?>
          <div style="color:<?= $t === 'ok' ? 'var(--green)' : ($t === 'done' ? 'var(--green)' : 'var(--red)') ?>">
            <?= htmlspecialchars($log_text) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<style>
@keyframes wpSpin { to { transform: rotate(360deg); } }
.tab-btn {
  background: transparent;
  border: none;
  border-radius: 7px;
  padding: 0.45rem 0.9rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text2);
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: inherit;
}
.tab-btn:hover {
  color: var(--text);
}
.tab-btn.active {
  background: var(--surface);
  color: var(--accent);
  box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
</style>

<script>
function switchWpTab(tab) {
  document.getElementById('tab-content-xml').style.display = tab === 'xml' ? 'block' : 'none';
  document.getElementById('tab-content-images').style.display = tab === 'images' ? 'block' : 'none';
  
  document.getElementById('btn-tab-xml').classList.toggle('active', tab === 'xml');
  document.getElementById('btn-tab-images').classList.toggle('active', tab === 'images');

  if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
  }
}

function startXmlProgress() {
  const fileInput = document.getElementById('wxr-file-input');
  if (fileInput && fileInput.files.length > 0) {
    setTimeout(() => {
      document.getElementById('xml-progress').style.display = 'block';
      const btn = document.getElementById('import-xml-btn');
      if (btn) btn.disabled = true;
    }, 100);
  }
}

function startImagesProgress() {
  setTimeout(() => {
    document.getElementById('images-progress').style.display = 'block';
    const btn = document.getElementById('import-images-btn');
    if (btn) btn.disabled = true;
  }, 100);
}
</script>
