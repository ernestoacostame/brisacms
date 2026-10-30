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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        // Support subfolder paths like 2025/04/file.mp3
        $rel  = ltrim(str_replace(['..','//'], '', $_POST['file'] ?? ''), '/');
        $path = MEDIA_PATH . '/' . $rel;
        if ($rel && file_exists($path) && is_file($path)) {
            unlink($path);
            media_delete_meta($rel);
            $msg = 'Archivo eliminado.';
        }
    } elseif ($action === 'save_meta') {
        // Save alt text, title, caption, description and rename file
        $rel = ltrim(str_replace(['..','//'], '', $_POST['file'] ?? ''), '/');
        $alt = trim($_POST['alt'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $newFilename = trim($_POST['filename'] ?? '');

        $renamedInfo = null;
        if (!empty($newFilename)) {
            $renamedInfo = media_rename_file($rel, $newFilename);
            if (!empty($renamedInfo['error'])) {
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => $renamedInfo['error']]);
                    exit;
                }
                $error = $renamedInfo['error'];
            } elseif (!empty($renamedInfo['rel'])) {
                $rel = $renamedInfo['rel'];
            }
        }

        $saved = media_save_meta($rel, [
            'alt'         => $alt,
            'title'       => $title,
            'caption'     => $caption,
            'description' => $description,
        ]);

        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => $saved,
                'meta' => media_get_meta($rel),
                'renamed' => $renamedInfo
            ]);
            exit;
        }

        $msg = __('media_meta_saved');
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
                
                // If alt text was supplied upon upload
                if (!empty($_POST['upload_alt'])) {
                    $rel_new = date('Y/m') . '/' . $filename;
                    media_save_meta($rel_new, ['alt' => $_POST['upload_alt']]);
                }

                $msg = "Subido: $filename";
            }
        }
    }
}

// Collect all media metadata
$all_meta = media_get_all_meta();

// Collect all media files recursively
$files = [];
$base_url = rtrim(base_url(), '/');

$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEDIA_PATH, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower($f->getExtension());
    $mt  = media_type($ext);
    if ($mt === 'other') continue;
    $rel = str_replace(MEDIA_PATH . '/', '', $f->getPathname());
    $meta = $all_meta[$rel] ?? ['alt' => '', 'title' => '', 'caption' => '', 'description' => ''];
    $files[] = [
        'name' => $f->getFilename(),
        'rel'  => $rel,
        'size' => $f->getSize(),
        'time' => $f->getMTime(),
        'url'  => $base_url . '/media/' . $rel,
        'ext'  => $ext,
        'type' => $mt,
        'meta' => $meta,
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
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= __("media_upload") ?>
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

    <!-- Filter tabs & Stats -->
    <div style="display:flex;gap:0.6rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center">
      <?php foreach (['all'=>__("media_filter_all"),'image'=>__("media_filter_image"),'audio'=>__("media_filter_audio"),'video'=>__("media_filter_video")] as $key=>$label): ?>
      <a href="?type=<?= $key ?>" class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $label ?>
        <span style="opacity:0.75;font-size:0.78rem;font-weight:600">(<?= $key === 'all' ? count($files) : ($by_type[$key] ?? 0) ?>)</span>
      </a>
      <?php endforeach; ?>
      <span style="margin-left:auto;color:var(--muted);font-size:0.86rem;font-weight:500">
        <?= count($files) ?> archivos · <?= round($total_size/1024/1024,1) ?> MB
      </span>
    </div>

    <?php if (empty($visible)): ?>
    <div style="text-align:center;padding:4.5rem 2rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);color:var(--muted)">
      <div style="font-size:3rem;margin-bottom:1rem">📂</div>
      <p style="font-size:1.05rem;font-weight:500"><?= __("media_empty") ?></p>
    </div>
    <?php else: ?>
    <div class="media-grid" id="media-grid">
      <?php foreach ($visible as $f): 
        $has_alt = !empty($f['meta']['alt']);
        $has_title = !empty($f['meta']['title']);
      ?>
      <div class="media-item" 
           data-rel="<?= htmlspecialchars($f['rel']) ?>"
           data-name="<?= htmlspecialchars($f['name']) ?>"
           data-url="<?= htmlspecialchars($f['url']) ?>"
           data-type="<?= $f['type'] ?>"
           data-size="<?= round($f['size']/1024) ?> KB"
           data-date="<?= date('Y-m-d H:i', $f['time']) ?>"
           data-alt="<?= htmlspecialchars($f['meta']['alt'] ?? '') ?>"
           data-title="<?= htmlspecialchars($f['meta']['title'] ?? '') ?>"
           data-caption="<?= htmlspecialchars($f['meta']['caption'] ?? '') ?>"
           data-description="<?= htmlspecialchars($f['meta']['description'] ?? '') ?>"
           onclick="openMediaModal(this)">
        <div class="media-thumb">

          <?php if ($f['type'] === 'image'): ?>
            <img src="<?= htmlspecialchars(image_cache_url($f['url'], 340, 340)) ?>" alt="<?= htmlspecialchars($f['meta']['alt'] ?: $f['name']) ?>" loading="lazy">
            <?php if ($has_alt): ?>
              <span class="media-badge-alt" title="Alt: <?= htmlspecialchars($f['meta']['alt']) ?>">ALT ✓</span>
            <?php else: ?>
              <span class="media-badge-noalt" title="Sin texto alternativo">Sin ALT</span>
            <?php endif; ?>

          <?php elseif ($f['type'] === 'audio'): ?>
            <div class="media-audio-thumb">
              <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="color:var(--accent)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
              <span class="media-type-badge"><?= strtoupper($f['ext']) ?></span>
            </div>

          <?php else: ?>
            <div class="media-video-thumb">
              <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="color:var(--accent)"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
              <span class="media-type-badge"><?= strtoupper($f['ext']) ?></span>
            </div>
          <?php endif; ?>

          <div class="media-overlay" onclick="event.stopPropagation()">
            <button class="media-copy" onclick="copyUrl(<?= htmlspecialchars(json_encode($f['url'])) ?>)" title="<?= __raw('media_copy_url') ?>">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <?php if ($f['type'] === 'audio'): ?>
            <button class="media-preview-btn" onclick="previewAudio(<?= htmlspecialchars(json_encode($f['url'])) ?>)" title="<?= __raw('media_preview') ?>">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </button>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm(<?= json_encode(__raw('media_confirm_del')) ?>)">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?= htmlspecialchars($f['rel']) ?>">
              <button type="submit" class="media-del" title="<?= __raw('media_delete') ?>">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </form>
          </div>
        </div>
        <div class="media-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
        <div class="media-meta">
          <span><?= round($f['size']/1024) ?> KB</span>
          <?php if (!empty($f['meta']['alt'])): ?>
            <span style="color:var(--green);font-size:0.75rem;font-weight:600">· Alt configurado</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Media Inspector Modal -->
    <div id="media-modal" class="modal-backdrop" style="display:none" onclick="if(event.target===this)closeMediaModal()">
      <div class="modal-card media-modal-dialog">
        <div class="media-modal-header">
          <div>
            <h3 class="modal-title" style="margin:0;font-size:1.25rem"><?= __('media_modal_title') ?></h3>
            <span id="modal-filename" style="font-size:0.86rem;color:var(--muted);font-weight:500"></span>
          </div>
          <button type="button" class="modal-close-btn" onclick="closeMediaModal()">✕</button>
        </div>

        <div class="media-modal-body">
          <!-- Left: Preview & File Details -->
          <div class="media-modal-preview-pane">
            <div id="modal-preview-container" class="media-modal-thumb-box"></div>
            <div class="media-meta-specs">
              <div class="spec-row">
                <span class="spec-label">Ruta:</span>
                <span class="spec-value" id="modal-rel-path"></span>
              </div>
              <div class="spec-row">
                <span class="spec-label">Tamaño:</span>
                <span class="spec-value" id="modal-size"></span>
              </div>
              <div class="spec-row">
                <span class="spec-label">Fecha:</span>
                <span class="spec-value" id="modal-date"></span>
              </div>
            </div>

            <!-- Quick copy actions -->
            <div class="media-quick-actions">
              <button type="button" class="btn btn-secondary btn-sm" onclick="copyModalUrl()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <?= __('media_copy_url') ?>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="copyModalMarkdown()">
                <?= __('media_copy_md') ?>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="copyModalHtml()">
                <?= __('media_copy_html') ?>
              </button>
            </div>
          </div>

          <!-- Right: Editable Metadata Form -->
          <form id="media-meta-form" class="media-modal-form-pane" onsubmit="saveMediaMetadata(event)">
            <input type="hidden" id="modal-input-rel" name="file" value="">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="save_meta">

            <div class="form-group" style="margin-bottom:1.15rem">
              <label for="modal-input-filename" class="form-label" style="font-weight:700">
                <?= __('media_filename', 'Nombre del archivo') ?>
              </label>
              <input type="text" id="modal-input-filename" name="filename" class="form-control" placeholder="nombre-del-archivo.jpg" style="font-size:0.95rem">
              <small style="display:block;margin-top:0.35rem;color:var(--muted);font-size:0.8rem">
                Puedes renombrar el archivo en el servidor. La extensión original se conserva de forma segura.
              </small>
            </div>

            <div class="form-group" style="margin-bottom:1.15rem">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
                <label for="modal-input-alt" class="form-label" style="margin-bottom:0;font-weight:700">
                  <?= __('media_alt') ?>
                </label>
                <span class="badge" style="background:rgba(16,185,129,0.12);color:var(--green);font-size:0.75rem;padding:0.2rem 0.5rem;border-radius:6px;font-weight:600">SEO & Accesibilidad</span>
              </div>
              <input type="text" id="modal-input-alt" name="alt" class="form-control" placeholder="Ej: Atardecer dorado en el malecón con palmeras" style="font-size:0.95rem">
              <small style="display:block;margin-top:0.35rem;color:var(--muted);font-size:0.8rem">
                <?= __('media_alt_desc') ?>
              </small>
            </div>

            <div class="form-group" style="margin-bottom:1.15rem">
              <label for="modal-input-title" class="form-label" style="font-weight:600">
                <?= __('media_title_field') ?>
              </label>
              <input type="text" id="modal-input-title" name="title" class="form-control" placeholder="Título que aparece al pasar el cursor" style="font-size:0.95rem">
            </div>

            <div class="form-group" style="margin-bottom:1.15rem">
              <label for="modal-input-caption" class="form-label" style="font-weight:600">
                <?= __('media_caption') ?>
              </label>
              <input type="text" id="modal-input-caption" name="caption" class="form-control" placeholder="Leyenda o pie de imagen visible" style="font-size:0.95rem">
            </div>

            <div class="form-group" style="margin-bottom:1.35rem">
              <label for="modal-input-description" class="form-label" style="font-weight:600">
                <?= __('media_description') ?>
              </label>
              <textarea id="modal-input-description" name="description" rows="3" class="form-control" placeholder="Detalles adicionales o notas sobre este medio…" style="resize:vertical;font-size:0.92rem"></textarea>
            </div>

            <div style="display:flex;gap:0.75rem;align-items:center;justify-content:flex-end;margin-top:auto;padding-top:1rem;border-top:1px solid var(--border)">
              <span id="modal-save-feedback" style="display:none;font-size:0.88rem;color:var(--green);font-weight:600;margin-right:auto">
                ✓ Guardado correctamente
              </span>
              <button type="button" class="btn btn-secondary" onclick="closeMediaModal()"><?= __('users_cancel') ?></button>
              <button type="submit" id="modal-save-btn" class="btn btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= __('media_save_meta') ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Audio preview player (hidden, shown on play) -->
    <div id="audio-player-bar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:var(--sidebar);border-top:1px solid var(--border);padding:1rem 2rem;z-index:500;align-items:center;gap:1.25rem;box-shadow:0 -4px 20px rgba(0,0,0,0.25)">
      <span style="font-size:0.95rem;font-weight:600;color:var(--text);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="audio-player-name">—</span>
      <audio id="audio-player" controls style="flex:1;min-width:200px;height:40px"></audio>
      <button onclick="document.getElementById('audio-player-bar').style.display='none';document.getElementById('audio-player').pause()"
        style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.35rem;padding:0.4rem;display:flex;align-items:center;justify-content:center">✕</button>
    </div>

    <div id="copy-toast" style="display:none;position:fixed;bottom:2rem;right:2rem;background:var(--green);color:#fff;padding:0.8rem 1.4rem;border-radius:10px;font-size:0.95rem;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,0.25);z-index:9999">
      ✓ <?= __("media_copied", "Copiado al portapapeles") ?>
    </div>
  </div>
</div>

<style>
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(195px, 1fr)); gap: 1.4rem; }
.media-item { cursor: pointer; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); user-select: none; }
.media-item:hover { transform: translateY(-3px); }
.media-thumb {
  position: relative; aspect-ratio: 1; background: var(--surface2);
  border-radius: 14px; overflow: hidden; border: 1px solid var(--border);
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.media-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.25s ease; }
.media-item:hover .media-thumb img { transform: scale(1.05); }

.media-badge-alt {
  position: absolute; top: 8px; left: 8px;
  background: rgba(16, 185, 129, 0.92); color: #fff;
  font-size: 0.68rem; font-weight: 800; letter-spacing: 0.05em;
  padding: 0.2rem 0.5rem; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  z-index: 2; pointer-events: none;
}
.media-badge-noalt {
  position: absolute; top: 8px; left: 8px;
  background: rgba(0, 0, 0, 0.5); color: rgba(255,255,255,0.75);
  font-size: 0.65rem; font-weight: 700;
  padding: 0.15rem 0.45rem; border-radius: 6px;
  z-index: 2; pointer-events: none;
}

.media-audio-thumb, .media-video-thumb {
  width: 100%; height: 100%; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 0.85rem;
}
.media-type-badge {
  font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em;
  background: rgba(var(--accent-rgb),0.18); color: var(--accent);
  padding: 0.25rem 0.75rem; border-radius: 20px;
}

.media-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.6);
  display: flex; align-items: center; justify-content: center; gap: 0.6rem;
  opacity: 0; backdrop-filter: blur(2px); transition: opacity 0.2s;
  z-index: 3;
}
.media-item:hover .media-overlay { opacity: 1; }
.media-copy, .media-del, .media-preview-btn {
  background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);
  color: #fff; border-radius: 9px; width: 38px; height: 38px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: all 0.15s;
}
.media-copy:hover, .media-preview-btn:hover { background: rgba(255,255,255,0.38); transform: scale(1.08); }
.media-del:hover { background: rgba(244,63,94,0.85); transform: scale(1.08); }

.media-name { font-size: 0.9rem; font-weight: 600; color: var(--text); margin-top: 0.6rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-meta { font-size: 0.8rem; font-weight: 500; color: var(--muted); margin-top: 0.15rem; display: flex; align-items: center; gap: 0.4rem; }

/* Modal Styles */
.media-modal-dialog {
  width: 900px; max-width: 95vw; max-height: 90vh;
  display: flex; flex-direction: column; padding: 0; overflow: hidden;
}
.media-modal-header {
  padding: 1.25rem 1.6rem; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  background: var(--surface);
}
.media-modal-body {
  display: grid; grid-template-columns: 360px 1fr;
  flex: 1; overflow-y: auto;
}
@media (max-width: 820px) {
  .media-modal-body { grid-template-columns: 1fr; }
}
.media-modal-preview-pane {
  padding: 1.6rem; background: var(--surface2);
  border-right: 1px solid var(--border); display: flex; flex-direction: column; gap: 1.2rem;
}
.media-modal-thumb-box {
  width: 100%; aspect-ratio: 4/3; max-height: 250px;
  background: var(--bg); border: 1px solid var(--border); border-radius: 12px;
  display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.media-modal-thumb-box img {
  max-width: 100%; max-height: 100%; object-fit: contain;
}
.media-meta-specs {
  display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.82rem;
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 0.85rem 1rem;
}
.spec-row { display: flex; justify-content: space-between; gap: 0.5rem; }
.spec-label { color: var(--muted); font-weight: 600; }
.spec-value { color: var(--text); font-weight: 500; word-break: break-all; }

.media-quick-actions {
  display: flex; flex-direction: column; gap: 0.5rem;
}
.media-quick-actions .btn {
  justify-content: center; font-size: 0.85rem; padding: 0.55rem 0.85rem;
}
.media-modal-form-pane {
  padding: 1.6rem; display: flex; flex-direction: column; background: var(--surface);
}
.modal-close-btn {
  background: none; border: none; font-size: 1.35rem; color: var(--muted); cursor: pointer;
  padding: 0.35rem 0.5rem; border-radius: 8px; transition: background 0.15s;
}
.modal-close-btn:hover { background: var(--surface2); color: var(--text); }
</style>

<script>
let currentModalItem = null;

function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    showToast('✓ URL copiada al portapapeles');
  });
}

function showToast(text) {
  const t = document.getElementById('copy-toast');
  t.textContent = text;
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2400);
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

function openMediaModal(itemEl) {
  currentModalItem = itemEl;
  const rel = itemEl.dataset.rel;
  const name = itemEl.dataset.name;
  const url = itemEl.dataset.url;
  const type = itemEl.dataset.type;
  const size = itemEl.dataset.size;
  const date = itemEl.dataset.date;
  const alt = itemEl.dataset.alt || '';
  const title = itemEl.dataset.title || '';
  const caption = itemEl.dataset.caption || '';
  const description = itemEl.dataset.description || '';

  document.getElementById('modal-filename').textContent = name;
  document.getElementById('modal-rel-path').textContent = rel;
  document.getElementById('modal-size').textContent = size;
  document.getElementById('modal-date').textContent = date;

  document.getElementById('modal-input-rel').value = rel;
  document.getElementById('modal-input-filename').value = name;
  document.getElementById('modal-input-alt').value = alt;
  document.getElementById('modal-input-title').value = title;
  document.getElementById('modal-input-caption').value = caption;
  document.getElementById('modal-input-description').value = description;

  const container = document.getElementById('modal-preview-container');
  container.innerHTML = '';
  if (type === 'image') {
    const img = document.createElement('img');
    img.src = url;
    img.alt = alt || name;
    container.appendChild(img);
  } else if (type === 'audio') {
    container.innerHTML = `<audio controls src="${url}" style="width:90%"></audio>`;
  } else if (type === 'video') {
    container.innerHTML = `<video controls src="${url}" style="max-width:100%;max-height:100%"></video>`;
  }

  document.getElementById('modal-save-feedback').style.display = 'none';
  document.getElementById('media-modal').style.display = 'flex';
}

function closeMediaModal() {
  document.getElementById('media-modal').style.display = 'none';
  currentModalItem = null;
}

function saveMediaMetadata(e) {
  e.preventDefault();
  const form = document.getElementById('media-meta-form');
  const fd = new FormData(form);
  fd.append('ajax', '1');

  const btn = document.getElementById('modal-save-btn');
  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Guardando…';

  fetch('media.php', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = origText;
    if (data.ok) {
      const fb = document.getElementById('modal-save-feedback');
      fb.style.display = 'inline';
      setTimeout(() => fb.style.display = 'none', 3000);

      // Handle rename update
      if (data.renamed && data.renamed.renamed) {
        if (currentModalItem) {
          currentModalItem.dataset.rel = data.renamed.rel;
          currentModalItem.dataset.name = data.renamed.filename;
          currentModalItem.dataset.url = data.renamed.url;
          const nameEl = currentModalItem.querySelector('.media-name');
          if (nameEl) {
            nameEl.textContent = data.renamed.filename;
            nameEl.title = data.renamed.filename;
          }
        }
        document.getElementById('modal-filename').textContent = data.renamed.filename;
        document.getElementById('modal-rel-path').textContent = data.renamed.rel;
        document.getElementById('modal-input-rel').value = data.renamed.rel;
        document.getElementById('modal-input-filename').value = data.renamed.filename;
      }

      // Update data attributes in DOM element
      if (currentModalItem) {
        currentModalItem.dataset.alt = data.meta.alt || '';
        currentModalItem.dataset.title = data.meta.title || '';
        currentModalItem.dataset.caption = data.meta.caption || '';
        currentModalItem.dataset.description = data.meta.description || '';

        // Update badge
        const thumb = currentModalItem.querySelector('.media-thumb');
        let altBadge = thumb.querySelector('.media-badge-alt, .media-badge-noalt');
        if (data.meta.alt) {
          if (!altBadge) {
            altBadge = document.createElement('span');
            thumb.appendChild(altBadge);
          }
          altBadge.className = 'media-badge-alt';
          altBadge.textContent = 'ALT ✓';
          altBadge.title = 'Alt: ' + data.meta.alt;
        } else if (altBadge) {
          altBadge.className = 'media-badge-noalt';
          altBadge.textContent = 'Sin ALT';
          altBadge.title = 'Sin texto alternativo';
        }
      }
      showToast('✓ Metadatos guardados correctamente');
    } else {
      alert(data.error || 'Error al guardar los metadatos.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = origText;
    alert('Error al guardar los metadatos.');
  });
}

function copyModalUrl() {
  if (!currentModalItem) return;
  copyUrl(currentModalItem.dataset.url);
}

function copyModalMarkdown() {
  if (!currentModalItem) return;
  const url = currentModalItem.dataset.url;
  const alt = document.getElementById('modal-input-alt').value.trim() || currentModalItem.dataset.name;
  const md = `![${alt}](${url})`;
  navigator.clipboard.writeText(md).then(() => {
    showToast('✓ Markdown copiado: ' + md);
  });
}

function copyModalHtml() {
  if (!currentModalItem) return;
  const url = currentModalItem.dataset.url;
  const alt = document.getElementById('modal-input-alt').value.trim() || currentModalItem.dataset.name;
  const title = document.getElementById('modal-input-title').value.trim();
  const titleAttr = title ? ` title="${title.replace(/"/g, '&quot;')}"` : '';
  const html = `<img src="${url}" alt="${alt.replace(/"/g, '&quot;')}"${titleAttr}>`;
  navigator.clipboard.writeText(html).then(() => {
    showToast('✓ HTML copiado al portapapeles');
  });
}

// ESC closes modal
window.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('media-modal').style.display !== 'none') {
    closeMediaModal();
  }
});

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
  form.querySelectorAll('input[type=file]').forEach(i => i.remove());
  form.appendChild(input);
  input.files = dt.files;
  form.submit();
});
</script>
</body></html>
