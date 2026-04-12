<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

define('MEDIA_PATH', ROOT_PATH . '/media');
if (!is_dir(MEDIA_PATH)) mkdir(MEDIA_PATH, 0755, true);

$csrf = generate_csrf();
$msg  = '';
$error = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Security error.';
    } elseif ($_POST['action'] ?? '' === 'delete') {
        $file = basename($_POST['file'] ?? '');
        $path = MEDIA_PATH . '/' . $file;
        if ($file && file_exists($path) && is_file($path)) {
            unlink($path);
            $msg = 'Archivo eliminado.';
        }
    } elseif (!empty($_FILES['upload']['tmp_name'])) {
        $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/avif'];
        $file = $_FILES['upload'];

        if (!in_array($file['type'], $allowed_mime)) {
            $error = 'Tipo de archivo no permitido.';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $error = 'El archivo supera 10MB.';
        } else {
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $clean    = preg_replace('/[^a-z0-9_-]/', '-', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
            $filename = $clean . '-' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            move_uploaded_file($file['tmp_name'], MEDIA_PATH . '/' . $filename);
            $msg = 'Subido: ' . $filename;
        }
    }
}

// List media files
$files = [];
foreach (glob(MEDIA_PATH . '/*.{jpg,jpeg,png,gif,webp,svg,avif}', GLOB_BRACE) as $f) {
    $files[] = [
        'name' => basename($f),
        'size' => filesize($f),
        'time' => filemtime($f),
        'url'  => base_url() . '/media/' . basename($f),
    ];
}
usort($files, fn($a, $b) => $b['time'] - $a['time']);

admin_header('Media', 'media');
?>
      <label class="btn btn-primary" style="cursor:pointer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Subir imagen
        <input type="file" name="upload" form="upload-form" accept="image/*" style="display:none" onchange="document.getElementById('upload-form').submit()">
      </label>
    </div>
  </div>
  <div class="page-body">
    <?php if ($msg): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form id="upload-form" method="POST" enctype="multipart/form-data" style="display:none">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
    </form>

    <?php if (empty($files)): ?>
    <div style="text-align:center;padding:4rem;color:var(--muted)">
      <div style="font-size:2.5rem;margin-bottom:1rem">🖼</div>
      <p>No hay imágenes todavía. Sube tu primera imagen o usa <a href="<?= base_url() ?>/admin/import_images.php" style="color:var(--accent)">Importar Imágenes</a>.</p>
    </div>
    <?php else: ?>
    <div style="margin-bottom:0.75rem;color:var(--muted);font-size:0.82rem"><?= count($files) ?> archivos · <?= round(array_sum(array_column($files,'size')) / 1024 / 1024, 1) ?> MB en total</div>
    <div class="media-grid" id="media-grid">
      <?php foreach ($files as $f): ?>
      <div class="media-item" data-url="<?= htmlspecialchars($f['url']) ?>">
        <div class="media-thumb">
          <img src="<?= htmlspecialchars($f['url']) ?>" alt="<?= htmlspecialchars($f['name']) ?>" loading="lazy">
          <div class="media-overlay">
            <button class="media-copy" onclick="copyUrl('<?= htmlspecialchars($f['url'], ENT_QUOTES) ?>')" title="Copiar URL">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este archivo?')">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?= htmlspecialchars($f['name']) ?>">
              <button type="submit" class="media-del" title="Eliminar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
            </form>
          </div>
        </div>
        <div class="media-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
        <div class="media-meta"><?= round($f['size'] / 1024) ?> KB</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Copy notification -->
    <div id="copy-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;background:var(--green);color:#fff;padding:0.6rem 1.2rem;border-radius:8px;font-size:0.875rem;z-index:9999">
      ✓ URL copiada
    </div>
  </div>
</div>

<style>
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 1rem;
}
.media-item { cursor: pointer; }
.media-thumb {
  position: relative;
  aspect-ratio: 1;
  background: var(--surface2);
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
}
.media-thumb img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform 0.2s;
}
.media-item:hover .media-thumb img { transform: scale(1.04); }
.media-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.55);
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  opacity: 0; transition: opacity 0.2s;
}
.media-item:hover .media-overlay { opacity: 1; }
.media-copy, .media-del {
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff; border-radius: 6px; padding: 0.4rem;
  cursor: pointer; display: flex; align-items: center; transition: background 0.15s;
}
.media-copy:hover { background: rgba(255,255,255,0.3); }
.media-del:hover { background: rgba(248,113,113,0.5); border-color: var(--red); }
.media-name {
  font-size: 0.75rem; color: var(--text2); margin-top: 0.35rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.media-meta { font-size: 0.7rem; color: var(--muted); }
</style>

<script>
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    const t = document.getElementById('copy-toast');
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2000);
  });
}
// Drag & drop upload
const grid = document.getElementById('media-grid') || document.body;
document.body.addEventListener('dragover', e => { e.preventDefault(); document.body.style.outline = '3px dashed var(--accent)'; });
document.body.addEventListener('dragleave', () => { document.body.style.outline = ''; });
document.body.addEventListener('drop', e => {
  e.preventDefault();
  document.body.style.outline = '';
  const file = e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  const form = document.getElementById('upload-form');
  const dt = new DataTransfer();
  dt.items.add(file);
  form.querySelector('[name=upload]').files = dt.files;
  form.submit();
});
</script>
</body></html>
