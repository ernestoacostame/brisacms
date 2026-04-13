<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

if (!defined('MEDIA_PATH')) define('MEDIA_PATH', ROOT_PATH . '/media');
if (!is_dir(MEDIA_PATH)) mkdir(MEDIA_PATH, 0755, true);

$csrf  = generate_csrf();
$msg   = '';
$error = '';

// File type classification
function media_type(string $ext): string {
    $audio = ['mp3','ogg','oga','wav','m4a','weba','flac','aac'];
    $video = ['mp4','m4v','webm','ogv','mov','avi'];
    $image = ['jpg','jpeg','png','gif','webp','svg','avif'];
    $ext   = strtolower($ext);
    if (in_array($ext, $audio)) return 'audio';
    if (in_array($ext, $video)) return 'video';
    if (in_array($ext, $image)) return 'image';
    return 'other';
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    if ($_POST['action'] ?? '' === 'delete') {
        // Support subfolder paths like 2025/04/file.mp3
        $rel  = ltrim(str_replace(['..','//'], '', $_POST['file'] ?? ''), '/');
        $path = MEDIA_PATH . '/' . $rel;
        if ($rel && file_exists($path) && is_file($path)) {
            unlink($path);
            $msg = 'Archivo eliminado.';
        }
    } elseif (!empty($_FILES['upload']['tmp_name'])) {
        // Quick upload from media manager
        $file = $_FILES['upload'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_media = [
            'image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/avif',
            'audio/mpeg','audio/mp4','audio/ogg','audio/wav','audio/webm','audio/flac',
            'video/mp4','video/webm','video/ogg','video/quicktime',
        ];

        if (!in_array($mime, $allowed_media)) {
            $error = "Tipo no permitido: $mime";
        } else {
            $type = explode('/', $mime)[0];
            $max  = ['image'=>10,'audio'=>200,'video'=>2048][$type] ?? 10;
            if ($file['size'] > $max * 1024 * 1024) {
                $error = "El archivo supera el límite de {$max} MB";
            } else {
                $date_dir = MEDIA_PATH . '/' . date('Y/m');
                if (!is_dir($date_dir)) mkdir($date_dir, 0755, true);
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $clean    = trim(preg_replace('/-+/','-',preg_replace('/[^a-z0-9_-]/','-',strtolower(pathinfo($file['name'],PATHINFO_FILENAME)))),'-');
                $filename = $clean . '-' . substr(md5(uniqid()),0,6) . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $date_dir . '/' . $filename);
                $msg = "Subido: $filename";
            }
        }
    }
}

// Collect all media files recursively
$files = [];
$exts  = '{jpg,jpeg,png,gif,webp,svg,avif,mp3,ogg,oga,wav,m4a,flac,mp4,m4v,webm,ogv,mov}';
$base_url = rtrim(base_url(), '/');

$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEDIA_PATH, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower($f->getExtension());
    $mt  = media_type($ext);
    if ($mt === 'other') continue;
    $rel = str_replace(MEDIA_PATH . '/', '', $f->getPathname());
    $files[] = [
        'name' => $f->getFilename(),
        'rel'  => $rel,
        'size' => $f->getSize(),
        'time' => $f->getMTime(),
        'url'  => $base_url . '/media/' . $rel,
        'ext'  => $ext,
        'type' => $mt,
    ];
}
usort($files, fn($a, $b) => $b['time'] - $a['time']);

// Stats
$total_size = array_sum(array_column($files, 'size'));
$by_type    = array_count_values(array_column($files, 'type'));

// Filter
$filter = $_GET['type'] ?? 'all';
$visible = $filter === 'all' ? $files : array_values(array_filter($files, fn($f) => $f['type'] === $filter));

admin_header(__("media_title"), 'media');
?>
      <label class="btn btn-primary" style="cursor:pointer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Subir archivo
        <input type="file" name="upload" form="upload-form"
          accept="image/*,audio/*,video/*,.mp3,.ogg,.wav,.m4a,.mp4,.webm,.ogv,.flac"
          style="display:none" onchange="document.getElementById('upload-form').submit()">
      </label>
    </div>
  </div>
  <div class="page-body">
    <?php if ($msg): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form id="upload-form" method="POST" enctype="multipart/form-data" style="display:none">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
    </form>

    <!-- Filter tabs -->
    <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center">
      <?php foreach (['all'=>__("media_filter_all"),'image'=>__("media_filter_image"),'audio'=>__("media_filter_audio"),'video'=>__("media_filter_video")] as $key=>$label): ?>
      <a href="?type=<?= $key ?>" class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $label ?>
        <span style="opacity:0.7;font-size:0.75rem">(<?= $key === 'all' ? count($files) : ($by_type[$key] ?? 0) ?>)</span>
      </a>
      <?php endforeach; ?>
      <span style="margin-left:auto;color:var(--muted);font-size:0.82rem">
        <?= count($files) ?> archivos · <?= round($total_size/1024/1024,1) ?> MB
      </span>
    </div>

    <?php if (empty($visible)): ?>
    <div style="text-align:center;padding:4rem;color:var(--muted)">
      <div style="font-size:2.5rem;margin-bottom:1rem">📂</div>
      <p>No hay archivos<?= $filter !== 'all' ? " de tipo «$filter»" : '' ?> todavía.</p>
    </div>
    <?php else: ?>
    <div class="media-grid" id="media-grid">
      <?php foreach ($visible as $f): ?>
      <div class="media-item" data-type="<?= $f['type'] ?>">
        <div class="media-thumb">

          <?php if ($f['type'] === 'image'): ?>
            <img src="<?= htmlspecialchars($f['url']) ?>" alt="<?= htmlspecialchars($f['name']) ?>" loading="lazy">

          <?php elseif ($f['type'] === 'audio'): ?>
            <div class="media-audio-thumb">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--accent)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
              <span class="media-type-badge"><?= strtoupper($f['ext']) ?></span>
            </div>

          <?php else: ?>
            <div class="media-video-thumb">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--accent)"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
              <span class="media-type-badge"><?= strtoupper($f['ext']) ?></span>
            </div>
          <?php endif; ?>

          <div class="media-overlay">
            <button class="media-copy" onclick="copyUrl(<?= htmlspecialchars(json_encode($f['url'])) ?>)" title="<?= __raw('media_copy_url') ?>">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <?php if ($f['type'] === 'audio'): ?>
            <button class="media-preview-btn" onclick="previewAudio(<?= htmlspecialchars(json_encode($f['url'])) ?>)" title="<?= __raw('media_preview') ?>">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </button>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm(<?= json_encode(__raw('media_confirm_del')) ?>)">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?= htmlspecialchars($f['rel']) ?>">
              <button type="submit" class="media-del" title="<?= __raw('media_delete') ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </form>
          </div>
        </div>
        <div class="media-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
        <div class="media-meta"><?= round($f['size']/1024) ?> KB</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Audio preview player (hidden, shown on play) -->
    <div id="audio-player-bar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:var(--sidebar);border-top:1px solid var(--border);padding:0.75rem 1.5rem;z-index:500;display:none;align-items:center;gap:1rem">
      <span style="font-size:0.85rem;color:var(--text2);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="audio-player-name">—</span>
      <audio id="audio-player" controls style="flex:1;min-width:200px;height:36px"></audio>
      <button onclick="document.getElementById('audio-player-bar').style.display='none';document.getElementById('audio-player').pause()"
        style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem;padding:0.25rem">✕</button>
    </div>

    <div id="copy-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;background:var(--green);color:#fff;padding:0.6rem 1.2rem;border-radius:8px;font-size:0.875rem;z-index:9999">
      ✓ URL copiada
    </div>
  </div>
</div>

<style>
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
.media-item { cursor: pointer; }
.media-thumb {
  position: relative; aspect-ratio: 1; background: var(--surface2);
  border-radius: 8px; overflow: hidden; border: 1px solid var(--border);
}
.media-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s; }
.media-item:hover .media-thumb img { transform: scale(1.04); }

.media-audio-thumb, .media-video-thumb {
  width: 100%; height: 100%; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 0.5rem;
}
.media-type-badge {
  font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;
  background: rgba(var(--accent-rgb),0.15); color: var(--accent);
  padding: 0.15rem 0.5rem; border-radius: 20px;
}

.media-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.55);
  display: flex; align-items: center; justify-content: center; gap: 0.4rem;
  opacity: 0; transition: opacity 0.2s;
}
.media-item:hover .media-overlay { opacity: 1; }
.media-copy, .media-del, .media-preview-btn {
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
  color: #fff; border-radius: 6px; padding: 0.4rem; cursor: pointer;
  display: flex; align-items: center; transition: background 0.15s;
}
.media-copy:hover, .media-preview-btn:hover { background: rgba(255,255,255,0.3); }
.media-del:hover { background: rgba(248,113,113,0.5); }

.media-name { font-size: 0.72rem; color: var(--text2); margin-top: 0.35rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-meta { font-size: 0.68rem; color: var(--muted); }
</style>

<script>
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    const t = document.getElementById('copy-toast');
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2000);
  });
}

function previewAudio(url) {
  const bar    = document.getElementById('audio-player-bar');
  const player = document.getElementById('audio-player');
  const name   = document.getElementById('audio-player-name');
  player.src   = url;
  name.textContent = url.split('/').pop();
  bar.style.display = 'flex';
  player.play();
}

// Drag & drop upload
document.body.addEventListener('dragover', e => { e.preventDefault(); document.body.style.outline = '3px dashed var(--accent)'; });
document.body.addEventListener('dragleave', () => { document.body.style.outline = ''; });
document.body.addEventListener('drop', e => {
  e.preventDefault();
  document.body.style.outline = '';
  const file = e.dataTransfer.files[0];
  if (!file) return;
  const form  = document.getElementById('upload-form');
  const input = document.createElement('input');
  input.type  = 'file';
  input.name  = 'upload';
  input.style.display = 'none';
  const dt = new DataTransfer();
  dt.items.add(file);
  // Replace existing file input
  form.querySelectorAll('input[type=file]').forEach(i => i.remove());
  form.appendChild(input);
  input.files = dt.files;
  form.submit();
});
</script>
</body></html>
