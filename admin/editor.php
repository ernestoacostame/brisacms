<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

// Collect existing categories and tags for autocomplete
$all_cats = [];
$all_tags_list = [];
foreach (['articles', 'pages'] as $_ctype) {
    $_dir = CONTENT_PATH . '/' . $_ctype;
    if (!is_dir($_dir)) continue;
    foreach (glob("$_dir/*.json") as $_f) {
        $_p = json_decode(file_get_contents($_f), true);
        foreach ($_p['categories'] ?? [] as $_c) if ($_c) $all_cats[$_c] = true;
        foreach ($_p['tags'] ?? [] as $_t) if ($_t) $all_tags_list[$_t] = true;
    }
}
$all_cats = array_keys($all_cats);
$all_tags_list = array_keys($all_tags_list);
sort($all_cats);
sort($all_tags_list);

$type = in_array($_GET['type'] ?? '', ['articles', 'pages']) ? $_GET['type'] : 'articles';
$slug = $_GET['slug'] ?? '';
$post = $slug ? get_content($type, $slug) : null;
$is_new = !$post;
$csrf = generate_csrf();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { $error = 'Security error. Refresh and try again.'; }
    else {
        $data = [
            'title'         => trim($_POST['title'] ?? ''),
            'content'       => $_POST['content'] ?? '',
            'excerpt'       => trim($_POST['excerpt'] ?? ''),
            'slug'          => sanitize_filename(trim($_POST['custom_slug'] ?? '') ?: slug_from_title($_POST['title'] ?? '')),
            'status'        => in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'draft',
            'categories'    => array_filter(array_map('trim', explode(',', $_POST['categories'] ?? ''))),
            'tags'          => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
            'featured_image'  => trim($_POST['featured_image'] ?? ''),
            'content_format'  => in_array($_POST['content_format'] ?? '', ['html', 'markdown']) ? $_POST['content_format'] : 'html',
            'mastodon_url'    => trim($_POST['mastodon_url'] ?? ''),
            'original_slug' => $slug,
            'created_at'    => $post['created_at'] ?? '',
        ];
        if (!$data['title']) { $error = __("editor_error_title"); }
        else {
            $new_slug = save_content($type, $data);
            $msg = __("editor_saved");
            $slug = $new_slug;
            $post = get_content($type, $new_slug);
            $is_new = false;
        }
    }
}

$label = $type === 'articles' ? __raw('nav_articles') : __raw('nav_pages');
$content_format = 'html'; // Always HTML format now
admin_header(($is_new ? __raw('editor_new_article') : __raw('editor_edit_article')), $type);
?>
      <?php if ($post && $post['status'] === 'published'): ?>
      <a href="<?= base_url() ?>/<?= $type === 'articles' ? 'article' : 'page' ?>/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">View Live ↗</a>
      <?php endif; ?>
      <button form="editor-form" name="status" value="draft" class="btn btn-secondary">Guardar borrador</button>
      <button form="editor-form" name="status" value="published" class="btn btn-primary" id="publish-btn">
        <?= $post && $post['status'] === 'published' ? __("editor_update") : __("editor_publish") ?>
      </button>
    </div>
  </div>
  <div class="page-body">
    <?php if ($msg): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form id="editor-form" method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="content" id="content-input" value="<?= htmlspecialchars($post['content'] ?? '') ?>">
      <input type="hidden" name="content_format" id="content-format-input" value="html">

      <div class="editor-layout">
        <!-- Main editor area -->
        <div class="editor-main">
          <!-- Format Bar -->
          <div class="format-bar" id="toolbar">
            <!-- HTML mode toolbar -->
            <div id="html-toolbar" style="display: flex; align-items: center; gap: 2px; flex-shrink: 0; overflow-x: auto;">
              <select class="fmt-select" onchange="formatBlock(this.value); this.value='p'" title="Formato de bloque">
                <option value="p">Párrafo</option>
                <option value="h1">Encabezado 1</option>
                <option value="h2">Encabezado 2</option>
                <option value="h3">Encabezado 3</option>
                <option value="pre">Código</option>
                <option value="blockquote">Cita</option>
              </select>
              <div class="fmt-sep"></div>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('bold')" title="<?= __raw("tb_bold") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('italic')" title="<?= __raw("tb_italic") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('underline')" title="<?= __raw("tb_underline") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path><line x1="4" y1="21" x2="20" y2="21"></line></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); execStrikeThrough()" title="<?= __raw("tb_strike") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('insertHorizontalRule')" title="Separador horizontal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line></svg>
              </button>
              <div class="fmt-sep"></div>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('insertUnorderedList')" title="<?= __raw("tb_ul") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3" y2="6"></line><line x1="3" y1="12" x2="3" y2="12"></line><line x1="3" y1="18" x2="3" y2="18"></line></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('insertOrderedList')" title="<?= __raw("tb_ol") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
              </button>
              <div class="fmt-sep"></div>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); insertLink()" title="<?= __raw("tb_link") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); insertImage()" title="<?= __raw("tb_image") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
              </button>
              <button type="button" class="fmt-btn tb-upload" id="upload-img-html" title="Subir imagen">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); insertVideo()" title="URL de video">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); insertAudio()" title="URL de audio">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
              </button>
              <div class="fmt-sep"></div>
              <button type="button" class="fmt-btn" id="font-size-down" title="Disminuir tamaño de fuente">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              </button>
              <span id="font-size-label" class="font-size-label">16</span>
              <button type="button" class="fmt-btn" id="font-size-up" title="Aumentar tamaño de fuente">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              </button>
              <div class="fmt-sep"></div>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('undo')" title="<?= __raw("tb_undo") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path></svg>
              </button>
              <button type="button" class="fmt-btn" onmousedown="event.preventDefault(); exec('redo')" title="<?= __raw("tb_redo") ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"></path><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"></path></svg>
              </button>
            </div>
            
            <!-- Mode switcher and other controls -->
            <div id="toolbar-right" style="margin-left: auto; display: flex; align-items: center; gap: 8px; position: relative;">
              <button type="button" class="fmt-btn mode-switch active" id="switch-visual" title="Editor visual HTML">Visual</button>
              <button type="button" class="fmt-btn mode-switch" id="switch-raw" title="Editor HTML RAW">RAW</button>
              <button type="button" class="fmt-btn" id="preview-btn" title="<?= __raw("tb_preview_article") ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <button type="button" class="fmt-btn" id="focus-btn" title="<?= __raw("tb_focus") ?>" onclick='if(window.toggleFocus)window.toggleFocus()'><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg></button>
            </div>
          </div>
          
          <div class="title-area">
            <input type="text" name="title" id="post-title" placeholder="<?= __raw("editor_title_ph") ?>"
              value="<?= htmlspecialchars($post['title'] ?? '') ?>" required autocomplete="off">
          </div>
          
          <!-- Editor panes -->
          <div class="editor-wrap">
            <!-- HTML visual editor (contentEditable div) -->
            <div id="editor" class="prose-editor" contenteditable="true"><?= $post['content'] ?? '' ?></div>
            <!-- Hidden textarea to store content for form submission -->
            <textarea id="editor-hidden" name="content" style="display:none"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
            <!-- RAW HTML textarea -->
            <textarea id="raw-editor" class="raw-editor" style="display:none"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
          </div>
          
        </div>

        <!-- Sidebar panel -->
        <div class="editor-sidebar">
          <div class="panel">
            <div class="panel-title">Status</div>
            <select name="status" id="status-select">
              <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= __("status_draft") ?></option>
              <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= __("status_published") ?></option>
            </select>
          </div>

          <div class="panel">
            <div class="panel-title">URL Slug</div>
            <input type="text" name="custom_slug" id="custom-slug"
              value="<?= htmlspecialchars($post['slug'] ?? '') ?>"
              placeholder="<?= __raw("panel_slug") ?>">
            <div class="panel-hint"><?= __("panel_slug_hint") ?></div>
          </div>

          <div class="panel">
            <div class="panel-title">Excerpt</div>
            <textarea name="excerpt" rows="3" placeholder="<?= __raw("panel_excerpt_ph") ?>"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
          </div>

          <?php if ($type === 'articles'): ?>
          <div class="panel">
            <div class="panel-title">Categorías</div>
            <div class="tag-input-wrap" id="cat-wrap">
              <div class="tag-chips" id="cat-chips"></div>
              <input type="text" id="cat-input" placeholder="<?= __raw("panel_cat_ph") ?>" autocomplete="off">
              <input type="hidden" name="categories" id="cat-hidden"
                value="<?= htmlspecialchars(implode(', ', $post['categories'] ?? [])) ?>">
            </div>
            <?php if (!empty($all_cats)): ?>
            <div class="tag-suggestions" id="cat-suggestions">
              <?php foreach ($all_cats as $c): ?>
              <span class="tag-sug" data-target="cat"><?= htmlspecialchars($c) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-title">Etiquetas</div>
            <div class="tag-input-wrap" id="tag-wrap" style="position:relative">
              <div class="tag-chips" id="tag-chips"></div>
              <input type="text" id="tag-input" placeholder="<?= __raw("panel_tag_ph") ?>" autocomplete="off">
              <input type="hidden" name="tags" id="tag-hidden"
                value="<?= htmlspecialchars(implode(', ', $post['tags'] ?? [])) ?>">
            </div>
            <!-- Inline autocomplete dropdown (hidden by default) -->
            <div id="tag-dropdown" class="tag-dropdown" style="display:none">
              <div id="tag-dropdown-list"></div>
            </div>
            <div class="panel-hint">Escribe y selecciona de las sugerencias o presiona Enter para añadir.</div>
            <!-- Hidden data list for JS -->
            <div id="all-tags-data" style="display:none"><?php
              foreach ($all_tags_list as $t) echo '<span>' . htmlspecialchars($t) . '</span>';
            ?></div>
          </div>
          <?php endif; ?>

          <div class="panel">
            <div class="panel-title">Imagen Destacada</div>
            <input type="text" name="featured_image" id="featured_image_input" placeholder="https://…"
              value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>">
            <div style="margin-top:0.5rem;display:flex;gap:0.5rem;align-items:center">
              <label class="btn btn-secondary btn-sm" style="cursor:pointer;font-size:0.78rem">
                <?= __("panel_featured_upload") ?>
                <input type="file" id="feat-upload" accept="image/*" style="display:none">
              </label>
              <span style="font-size:0.72rem;color:var(--muted)"><?= __("panel_featured_or") ?></span>
            </div>
            <div id="featured-preview" style="<?= empty($post['featured_image']) ? 'display:none' : '' ?>">
              <img src="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" style="width:100%;border-radius:6px;margin-top:0.6rem;max-height:120px;object-fit:cover;" alt="" id="featured-img">
            </div>
            <div id="feat-upload-progress" style="display:none;font-size:0.78rem;color:var(--muted);margin-top:0.35rem"></div>
          </div>

          <div class="panel">
            <div class="panel-title">URL de Mastodon</div>
            <input type="text" name="mastodon_url" placeholder="<?= __raw("panel_mastodon_ph") ?>"
              value="<?= htmlspecialchars($post['mastodon_url'] ?? '') ?>">
            <div class="panel-hint"><?= __("panel_mastodon_hint") ?></div>
          </div>

          <?php if ($post && function_exists('get_content_backups')): 
              $backups = get_content_backups($type, $slug);
              if (!empty($backups)): ?>
          <div class="panel">
            <div class="panel-title" style="color:var(--accent)">Version History</div>
            <div style="font-size:0.8rem; color:var(--muted); margin-bottom:0.5rem">
              Last 10 backups are kept. Click to preview.
            </div>
            <div style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:4px; padding:0.5rem;">
              <?php foreach ($backups as $backup): ?>
              <div style="padding:0.5rem; border-bottom:1px solid var(--border2); font-size:0.8rem;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <span><?= htmlspecialchars($backup['date']) ?></span>
                  <button type="button" class="btn btn-secondary btn-sm" 
                          onclick="previewBackup('<?= htmlspecialchars($backup['timestamp']) ?>', '<?= htmlspecialchars($type) ?>', '<?= htmlspecialchars($slug) ?>')"
                          style="padding:0.1rem 0.5rem; font-size:0.7rem;">
                    Preview
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; endif; ?>

          <?php if ($post): ?>
          <div class="panel">
            <div class="panel-title" style="color:var(--red)">Danger Zone</div>
            <a href="<?= base_url() ?>/admin/delete.php?type=<?= $type ?>&slug=<?= htmlspecialchars($slug) ?>&csrf=<?= $csrf ?>"
               class="btn btn-danger" style="width:100%;justify-content:center"
               onclick="return confirm('<?= __raw("confirm_delete_article") ?>')">
              Delete <?= $label ?>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>


<style>
:root {
  --surface2-rgb: 40, 40, 50;
  /* Añadir si no existen */
  --surface2: #2d2d3d;
  --border-soft: #3a3a4a;
  --text-muted: #a0a0b0;
  --text-faint: #6a6a7a;
  --accent-light: #4a8bf5;
  --accent-bg: rgba(74, 139, 245, 0.15);
}
.editor-layout {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 1.5rem;
  align-items: start;
}
.editor-main {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  height: calc(100vh - 130px);
  min-height: 500px;
  overflow: hidden;
}

/* ── Format Bar ───────────────────────────────────── */
.format-bar {
  height: 36px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  padding: 0 8px;
  gap: 2px;
  flex-shrink: 0;
  z-index: 1000;
  flex-wrap: nowrap;
  overflow-x: auto;
  position: sticky;
  top: 0;
}

.title-area {
  padding: 1.25rem 1.5rem 0;
  border-bottom: 1px solid var(--border-soft);
}

.title-area input {
  background: transparent;
  border: none;
  padding: 0;
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.03em;
  width: 100%;
  margin-bottom: 1rem;
}

.title-area input::placeholder {
  color: var(--muted);
}

.title-area input:focus {
  outline: none;
}

/* Ajustar el editor-wrap para que ocupe el espacio restante */
.editor-wrap {
  flex: 1;
  overflow-y: auto;
  position: relative;
  min-height: 0;
  padding: 0;
}

.fmt-select {
  background: none;
  border: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 5px;
  outline: none;
  transition: all .12s;
  min-height: 34px;
  max-width: 140px; /* Nuevo: reducir ancho */
}

/* Ajustar para pantallas más pequeñas */
@media (max-width: 768px) {
  .fmt-select {
    max-width: 110px;
    font-size: 12px;
    padding: 3px 5px;
    min-height: 30px;
  }
}

@media (max-width: 480px) {
  .fmt-select {
    max-width: 90px;
    font-size: 11px;
    padding: 3px 4px;
    min-height: 28px;
  }
}

.fmt-select:hover {
  background: var(--surface2);
  color: var(--text);
}

.fmt-btn {
  background: none;
  border: none;
  border-radius: 5px;
  min-width: 36px; /* Cambiar de width a min-width */
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 15px;
  font-family: 'DM Sans', sans-serif;
  font-weight: 500;
  transition: all .12s;
  padding: 10px; /* Añadir padding de 10px */
  box-sizing: border-box; /* Para incluir padding en el tamaño total */
}

.fmt-btn.active {
  background: var(--accent-bg);
  color: var(--accent);
  padding: 2px 20px !important; /* Padding específico para active */
}

/* Ajustar el hover */
.fmt-btn:hover {
  background: var(--surface2);
  color: var(--text);
}

/* Fix for markdown preview button */
#md-preview-btn {
  min-width: 36px !important;
  width: 36px !important;
  height: 34px !important;
  padding: 8px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

#md-preview-btn svg {
  width: 16px !important;
  height: 16px !important;
  stroke: currentColor !important;
  fill: none !important;
  display: block !important;
}

/* Ensure SVG icons are properly centered in buttons */
.fmt-btn svg {
  display: block;
  margin: 0 auto;
}

/* Ajustar para responsive */
@media (max-width: 768px) {
  .fmt-btn {
    padding: 8px;
    min-width: 32px;
    height: 30px;
    font-size: 14px;
  }
  
  .fmt-btn.active {
    padding: 1px 16px !important;
  }
  
  #md-preview-btn {
    min-width: 32px !important;
    width: 32px !important;
    height: 30px !important;
    padding: 6px !important;
  }
  
  #md-preview-btn svg {
    width: 14px !important;
    height: 14px !important;
  }
}

@media (max-width: 480px) {
  .fmt-btn {
    padding: 6px;
    min-width: 30px;
    height: 28px;
    font-size: 13px;
  }
  
  .fmt-btn.active {
    padding: 1px 12px !important;
  }
  
  #md-preview-btn {
    min-width: 30px !important;
    width: 30px !important;
    height: 28px !important;
    padding: 5px !important;
  }
  
  #md-preview-btn svg {
    width: 12px !important;
    height: 12px !important;
  }
}

/* Font size controls */
.font-size-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    min-width: 24px;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    user-select: none;
}

/* Asegurar que los botones con texto tengan suficiente espacio */
.fmt-btn[title*="Cita"],
.fmt-btn[title*="Código"],
.fmt-btn[title*="Lista"] {
  min-width: 40px;
}

/* Responsive adjustments for font size controls */
@media (max-width: 768px) {
    .font-size-label {
        font-size: 12px;
        min-width: 22px;
    }
}

@media (max-width: 480px) {
    .font-size-label {
        font-size: 11px;
        min-width: 20px;
    }
}

/* Ajustar iconos específicos */
.fmt-btn b,
.fmt-btn i,
.fmt-btn u,
.fmt-btn s,
.fmt-btn code {
  font-style: normal;
  font-weight: normal;
  text-decoration: none;
}

/* Asegurar que los separadores se ajusten a la nueva altura */
.fmt-sep {
  height: 22px;
  align-self: center;
}

/* Añadir estilos para los botones mode-switch */
.mode-switch {
  min-width: 50px !important;
  padding: 10px !important;
}

.mode-switch.active {
  padding: 2px 20px !important;
}

.fmt-sep {
  width: 1px;
  height: 22px;
  background: var(--border);
  margin: 0 6px;
}

/* Responsive design para format-bar */
@media (max-width: 768px) {
  .format-bar {
    padding: 0 6px;
    gap: 2px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  #html-toolbar, #md-toolbar {
    gap: 2px;
    flex-wrap: nowrap;
  }
  
  .fmt-btn {
    width: 32px;
    height: 30px;
    font-size: 14px;
  }
  
  .fmt-select {
    font-size: 12px;
    padding: 3px 5px;
    min-height: 30px;
  }
  
  .fmt-sep {
    margin: 0 4px;
    height: 20px;
  }
  
  #toolbar-right {
    gap: 6px;
  }
  
  .mode-switch {
    font-size: 12px;
    padding: 3px 8px;
  }
}

@media (max-width: 480px) {
  .format-bar {
    height: 38px;
  }
  
  .fmt-btn {
    width: 30px;
    height: 28px;
    font-size: 13px;
  }
  
  .fmt-select {
    font-size: 11px;
    max-width: 85px;
    padding: 3px 4px;
    min-height: 28px;
  }
  
  #toolbar-right .mode-switch {
    display: none;
  }
  
  .fmt-sep {
    margin: 0 3px;
    height: 18px;
  }
}

/* ── TITLEBAR ─────────────────────────────────────── */
#titlebar {
  height: 44px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 12px;
  gap: 8px;
  flex-shrink: 0;
  user-select: none;
}
#titlebar .logo {
  font-family: 'Lora', serif;
  font-size: 15px;
  font-weight: 500;
  color: var(--text);
  letter-spacing: -0.3px;
}
#titlebar .sep { flex: 1; }
#titlebar .site-badge {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; color: var(--text-muted);
  background: var(--surface2); border: 1px solid var(--border);
  border-radius: 20px; padding: 3px 10px; gap: 6px; cursor: pointer;
  transition: all .15s;
}
#titlebar .site-badge:hover { border-color: var(--accent); color: var(--accent); }
#titlebar .site-badge .dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--accent);
}
.tb-btn {
  background: none; border: 1px solid transparent;
  border-radius: 6px; padding: 5px 8px;
  color: var(--text-muted); cursor: pointer; font-size: 12px;
  display: flex; align-items: center; gap: 5px;
  font-family: 'DM Sans', sans-serif; transition: all .15s;
}
.tb-btn:hover { background: var(--surface2); border-color: var(--border); color: var(--text); }
.tb-btn.primary {
  background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 500;
}
.tb-btn.primary:hover { background: var(--accent-light); }

/* ── FORMATTING TOOLBAR ───────────────────────────── */
#format-bar {
  height: 36px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  display: flex; align-items: center; padding: 0 8px; gap: 2px; flex-shrink: 0;
}
.fmt-btn {
  background: none; border: none; border-radius: 4px;
  width: 28px; height: 26px; display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); cursor: pointer; font-size: 13px;
  font-family: 'DM Sans', sans-serif; font-weight: 500; transition: all .12s;
}
.fmt-btn:hover { background: var(--surface2); color: var(--text); }
.fmt-btn.active { background: var(--accent-bg); color: var(--accent); }
.fmt-sep { width: 1px; height: 18px; background: var(--border); margin: 0 4px; }
.fmt-select {
  background: none; border: none; font-family: 'DM Sans', sans-serif;
  font-size: 12px; color: var(--text-muted); cursor: pointer; padding: 2px 4px;
  border-radius: 4px; outline: none; transition: all .12s;
}
.fmt-select:hover { background: var(--surface2); color: var(--text); }

/* Actualizar variables CSS para mantener el estilo actual */
:root {
  --surface2: #2d2d3d;
  --border-soft: #3a3a4a;
  --text-muted: #a0a0b0;
  --text-faint: #6a6a7a;
  --accent-light: #4a8bf5;
  --accent-bg: rgba(74, 139, 245, 0.15);
}

/* Ajustar el layout principal */
.page-body {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 57px);
  overflow: visible;
}

.editor-layout {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 1.5rem;
  align-items: start;
  flex: 1;
  min-height: 0;
  height: auto;
}

.editor-main {
  display: flex; 
  flex-direction: column;
  background: var(--surface); 
  border: 1px solid var(--border);
  border-radius: var(--radius);
  height: auto;
  min-height: 500px;
  overflow: visible;
}

/* Ajustar el área del editor */
.editor-wrap { 
  flex: 1; 
  overflow-y: auto; 
  position: relative; 
  min-height: 0; 
  display: flex;
  flex-direction: column;
  max-height: 70vh; /* Limit height for editor area */
}

/* Asegurar que las toolbars internas funcionen correctamente */
#html-toolbar, #md-toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
  overflow-x: auto;
}

/* Responsive */
@media (max-width: 768px) {
  #titlebar {
    padding: 0 8px;
    gap: 4px;
    height: 40px;
  }
  .tb-btn {
    padding: 4px 6px;
    font-size: 11px;
  }
  #format-bar {
    height: 32px;
    padding: 0 4px;
    overflow-x: auto;
  }
  .fmt-btn {
    width: 26px;
    height: 24px;
    font-size: 12px;
  }
  .fmt-select {
    font-size: 11px;
  }
}
  background: none;
  border: none;
  color: var(--text2);
  padding: 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.825rem;
  font-family: inherit;
  transition: all 0.1s;
  line-height: 1;
  white-space: nowrap;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  min-height: 40px;
}
.tb:hover {
  background: var(--surface2);
  color: var(--text);
}
.tb.active {
  background: rgba(var(--accent-rgb), 0.15);
  color: var(--accent);
}
.tb-icon {
  min-width: 40px;
  min-height: 40px;
  padding: 0.5rem;
}
.tb-icon svg {
  width: 18px;
  height: 18px;
}
.tb-mode {
  font-size: 0.75rem;
  font-weight: 600;
  border: 1px solid var(--border2) !important;
  border-radius: 4px !important;
  padding: 0.4rem 0.75rem !important;
  min-width: auto;
}
.tb-mode.active {
  background: var(--accent) !important;
  color: #fff !important;
  border-color: var(--accent) !important;
}
@media (max-width: 768px) {
  .toolbar {
    padding: 0.4rem 0.6rem;
    overflow-x: auto;
  }
  .tb {
    min-width: 36px;
    min-height: 36px;
    padding: 0.4rem;
  }
  .tb-icon svg {
    width: 16px;
    height: 16px;
  }
  .toolbar-sep {
    height: 20px;
    margin: 0 4px;
  }
}

/* Mobile toolbar */
@media (max-width: 768px) {
  .toolbar {
    overflow-x: auto;
    padding: 0.4rem 0.5rem;
    -webkit-overflow-scrolling: touch;
    flex-wrap: nowrap;
  }
  #html-toolbar, #md-toolbar {
    gap: 2px;
    flex-wrap: nowrap;
  }
  .tb {
    min-width: 36px;
    min-height: 36px;
    padding: 0.3rem;
    flex-shrink: 0;
  }
  .toolbar-sep {
    height: 20px;
    margin: 0 3px;
    flex-shrink: 0;
  }
  .tb-mode {
    font-size: 0.7rem;
    padding: 0.3rem 0.5rem !important;
    flex-shrink: 0;
  }
  /* Hide some buttons on very small screens */
  @media (max-width: 480px) {
    .tb:nth-child(n+8) {
      display: none;
    }
  }
}

/* ── Floating toolbar ── */
.float-toolbar {
  position: fixed;
  background: #1e1e2e;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  padding: 4px 6px;
  display: flex; align-items: center; gap: 2px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.4);
  z-index: 9000;
  animation: ftb-pop 0.12s ease;
}
@keyframes ftb-pop {
  from { opacity:0; transform: translateY(4px) scale(0.97); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}
.ftb {
  background: none; border: none; color: #cdd6f4;
  padding: 0.3rem 0.45rem; border-radius: 5px;
  cursor: pointer; font-size: 0.82rem; font-family: inherit;
  line-height: 1; transition: background 0.1s;
}
.ftb:hover { background: rgba(255,255,255,0.12); }
.ftb-sep { width: 1px; height: 16px; background: rgba(255,255,255,0.15); margin: 0 3px; }
/* Arrow */
.float-toolbar::after {
  content: '';
  position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%);
  width: 8px; height: 8px;
  background: #1e1e2e; border-right: 1px solid rgba(255,255,255,0.12);
  border-bottom: 1px solid rgba(255,255,255,0.12);
  transform: translateX(-50%) rotate(45deg);
}

/* ── Editor panes ── */
.editor-wrap { 
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  position: relative;
}
.prose-editor {
  min-height: 100%;
  padding: 1.5rem; 
  outline: none; 
  line-height: 1.8;
  color: var(--text); 
  font-size: 1rem;
}
/* TinyMCE container */
#editor {
  flex: 1;
  min-height: 100%;
  width: 100%;
  border: none;
  background: var(--surface);
  color: var(--text);
  font-size: 1rem;
  line-height: 1.8;
  padding: 1.5rem;
  resize: none;
  overflow-y: auto;
}
/* Ensure TinyMCE iframe fills the container */
.tox-tinymce {
  border: none !important;
  border-radius: 0 !important;
  height: 100% !important;
}
.tox-editor-container {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.tox-sidebar-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.tox .tox-edit-area {
  flex: 1;
}
.tox .tox-edit-area__iframe {
  flex: 1;
}
.prose-editor h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5rem 0 0.75rem; }
.prose-editor h3 { font-size: 1.2rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
#editor p, #editor > p, [contenteditable] p { margin-top: 0 !important; margin-bottom: 1em !important; display: block !important; }
.prose-editor p { margin-top: 0 !important; margin-bottom: 1em !important; }
.prose-editor a { color: var(--accent); }
.prose-editor blockquote { border-left: 3px solid var(--accent); padding: 0.5rem 1rem; margin: 1rem 0; color: var(--text2); font-style: italic; background: var(--surface2); border-radius: 0 8px 8px 0; }
.prose-editor pre { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; font-family: monospace; font-size: 0.875rem; overflow-x: auto; margin: 1rem 0; }
.prose-editor ul, .prose-editor ol { padding-left: 1.5rem; margin: 0 0 1rem; }
.prose-editor li { margin-bottom: 0.25rem; }
.prose-editor img { max-width: 100%; border-radius: 8px; margin: 0.5rem 0; }
.prose-editor hr { border: none; border-top: 1px solid var(--border); margin: 2rem 0; }
.prose-editor code { background: var(--surface2); padding: 0.1rem 0.35rem; border-radius: 3px; font-size: 0.875em; }
.prose-editor strong { font-weight: 700; }
.prose-editor em { font-style: italic; }
.prose-editor del { text-decoration: line-through; color: var(--muted); }

.html-editor {
  width: 100%; min-height: 100%; padding: 1.5rem;
  background: #1a1a24; color: #a8e6cf;
  font-family: 'Fira Code', 'Cascadia Code', 'Courier New', monospace;
  font-size: 0.9rem; border: none; resize: vertical; line-height: 1.7;
  tab-size: 2;
}
.html-editor:focus, .md-editor:focus { outline: none; }

/* RAW HTML editor styling */
.raw-editor {
  width: 100%;
  min-height: 100%;
  padding: 1.5rem;
  background: #1a1a24;
  color: #a8e6cf;
  font-family: 'Fira Code', 'Cascadia Code', 'Courier New', monospace;
  font-size: 1rem;
  border: none;
  resize: none;
  line-height: 1.7;
  tab-size: 2;
  overflow-y: auto;
  overflow-x: hidden;
  box-sizing: border-box;
  flex: 1;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.raw-editor:focus {
  outline: none;
  box-shadow: 0 0 0 2px var(--accent);
}

/* Syntax highlighting for RAW editor */
.raw-editor .tag { color: #ff79c6; }
.raw-editor .attr { color: #50fa7b; }
.raw-editor .value { color: #f1fa8c; }
.raw-editor .comment { color: #6272a4; }


/* ── Focus mode ── */
body.focus-mode .sidebar,
body.focus-mode .topbar,
body.focus-mode .editor-sidebar,
body.focus-mode .alert { display: none !important; }
body.focus-mode .main { margin-left: 0 !important; }
body.focus-mode .page-body { padding: 0; }
body.focus-mode .editor-layout { grid-template-columns: 1fr; }
body.focus-mode .editor-main { border-radius: 0; border-left: none; border-right: none; border-top: none; height: 100vh !important; top: 0 !important; }
body.focus-mode .toolbar { top: 0; border-top: none; }
body.focus-mode .prose-editor, body.focus-mode .md-editor { max-width: 760px; margin: 0 auto; }
body.focus-mode #focus-btn { color: var(--accent); }
.focus-exit-hint { display: none; position: fixed; bottom: 1rem; left: 50%; transform: translateX(-50%); z-index: 9999; width: 100%; max-width: 300px; }
body.focus-mode .focus-exit-hint { display: flex; justify-content: center; align-items: center; }
.focus-exit-btn {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 50px;
  padding: 1rem 2rem;
  font-size: 1rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(0,0,0,0.3);
  transition: all 0.2s;
  white-space: nowrap;
  text-align: center;
}
.focus-exit-btn:hover {
  opacity: 0.9;
  transform: scale(1.05);
}
.focus-exit-btn:active {
  transform: scale(0.98);
}
body.focus-mode .floating-buttons-container { display: none !important; }

/* ── Tag chips ── */
.tag-input-wrap {
  background: var(--bg); border: 1px solid var(--border2); border-radius: 7px;
  padding: 0.4rem 0.5rem; min-height: 38px; display: flex; flex-wrap: wrap; gap: 0.3rem;
  align-items: center; cursor: text;
}
.tag-input-wrap:focus-within { border-color: var(--accent); }
.tag-chip {
  display: inline-flex; align-items: center; gap: 0.3rem;
  background: rgba(var(--accent-rgb),0.15); color: var(--accent);
  padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.78rem; font-weight: 500;
}
.tag-chip button { background: none; border: none; color: inherit; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; opacity: 0.7; }
.tag-chip button:hover { opacity: 1; }
.tag-input-wrap input { background: none; border: none; outline: none; font-family: inherit; font-size: 0.85rem; color: var(--text); padding: 0; min-width: 80px; flex: 1; }
.tag-suggestions { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.5rem; }
.tag-sug { background: var(--surface2); border: 1px solid var(--border2); color: var(--text2); padding: 0.15rem 0.55rem; border-radius: 20px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
.tag-sug:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
.tag-sug.used { opacity: 0.35; pointer-events: none; }

/* Tag autocomplete dropdown */
.tag-dropdown {
  background: var(--surface2); border: 1px solid var(--border2);
  border-radius: 0 0 7px 7px; max-height: 180px; overflow-y: auto;
  margin-top: -1px;
}
.tag-dropdown-item {
  padding: 0.45rem 0.75rem; font-size: 0.82rem; cursor: pointer;
  color: var(--text2); transition: background 0.1s;
  display: flex; align-items: center; justify-content: space-between;
}
.tag-dropdown-item:hover, .tag-dropdown-item.focused {
  background: rgba(var(--accent-rgb), 0.15); color: var(--accent);
}
.tag-dropdown-item .tag-count {
  font-size: 0.7rem; color: var(--muted); background: var(--bg);
  padding: 0.1rem 0.4rem; border-radius: 20px;
}
.tag-dropdown-empty {
  padding: 0.5rem 0.75rem; font-size: 0.8rem; color: var(--muted); font-style: italic;
}

/* ── Sidebar panels ── */
.editor-sidebar { 
  display: flex; 
  flex-direction: column; 
  gap: 0.75rem; 
  max-height: calc(100vh - 150px); /* Limit sidebar height */
  overflow-y: auto; /* Enable scrolling */
  position: sticky;
  top: 20px; /* Stick to top with some offset */
}

/* Custom scrollbar for sidebar */
.editor-sidebar::-webkit-scrollbar {
  width: 6px;
}

.editor-sidebar::-webkit-scrollbar-track {
  background: var(--surface2);
  border-radius: 3px;
}

.editor-sidebar::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 3px;
}

.editor-sidebar::-webkit-scrollbar-thumb:hover {
  background: var(--accent);
}

/* For Firefox */
.editor-sidebar {
  scrollbar-width: thin;
  scrollbar-color: var(--border) var(--surface2);
}
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; }
.panel-title { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 0.65rem; }
.panel-hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.4rem; }
.panel input, .panel select, .panel textarea { margin-top: 0; font-size: 0.85rem; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  /* Prevenir scroll horizontal en todo */
  html, body {
    overflow-x: hidden !important;
    position: relative;
    height: auto;
    min-height: 100vh;
  }
  
  /* Corregir el page-body principal */
  .page-body {
    display: block !important;
    height: auto !important;
    min-height: 100vh !important;
    overflow-y: visible !important;
    padding: 0.75rem !important;
    position: relative;
  }
  
  /* Cambiar completamente el layout del editor en móvil */
  .editor-layout {
    display: flex !important;
    flex-direction: column !important;
    grid-template-columns: 1fr !important;
    gap: 1rem !important;
    min-height: 100vh !important;
    width: 100% !important;
    max-width: 100% !important;
    position: relative;
  }
  
  /* Ajustar el editor principal */
  .editor-main {
    order: 1;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 50vh !important;
    max-height: none !important;
    position: relative !important;
    margin: 0 !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius) !important;
    box-sizing: border-box;
    overflow: visible !important;
  }
  
  /* Ajustar el wrapper del editor */
  .editor-wrap {
    height: auto !important;
    min-height: 300px !important;
    max-height: none !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 1rem 0.75rem !important;
    position: relative;
  }
  
  /* Ajustar el título */
  .title-area {
    padding: 1rem 0.75rem 0 !important;
    margin: 0 !important;
    width: 100% !important;
    box-sizing: border-box;
  }
  
  /* IMPORTANTE: Arreglar el sidebar */
  .editor-sidebar {
    order: 2;
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 1rem 0 120px !important; /* Más espacio para botones flotantes */
    display: block !important;
    position: relative !important;
    overflow: visible !important;
    z-index: 10;
    box-sizing: border-box;
    max-height: none !important; /* Remove height limit on mobile */
    overflow-y: visible !important; /* Allow content to flow naturally */
    position: static !important; /* Remove sticky on mobile */
  }
  
  /* Ajustar los paneles del sidebar */
  .editor-sidebar .panel {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 0 1rem 0 !important;
    padding: 1.25rem !important;
    box-sizing: border-box;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    overflow: visible;
    left: 0 !important;
    right: 0 !important;
  }
  
  /* Asegurar que el último panel tenga espacio extra */
  .editor-sidebar .panel:last-child {
    margin-bottom: 2rem !important;
  }
  
  /* Ensure Danger Zone is visible */
  .editor-sidebar .panel[style*="color:var(--red)"] {
    margin-top: 2rem !important;
    border: 1px solid var(--red) !important;
    background: rgba(255, 0, 0, 0.05) !important;
  }
  
  /* Add a visual indicator for scrollable content */
  .editor-sidebar::before {
    content: "↓ Desplázate para ver más opciones ↓";
    display: block;
    text-align: center;
    font-size: 12px;
    color: var(--accent);
    padding: 10px;
    margin: 10px 0;
    background: var(--surface2);
    border-radius: 8px;
    border: 1px dashed var(--border);
  }
  
  /* Ajustar inputs dentro de paneles */
  .panel input[type="text"],
  .panel input[type="url"],
  .panel textarea,
  .panel select {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
    font-size: 16px !important;
    padding: 12px !important;
    border-radius: 8px;
    border: 1px solid var(--border2);
    background: var(--surface2);
    color: var(--text);
    margin-top: 0.5rem;
  }
  
  /* Fix para el área de categorías y tags */
  .tag-input-wrap {
    width: 100% !important;
    max-width: 100% !important;
    min-height: 46px;
    padding: 8px !important;
    box-sizing: border-box;
    overflow: visible !important;
  }
  
  .tag-chips {
    max-width: 100% !important;
    overflow-x: auto !important;
    flex-wrap: wrap !important;
    -webkit-overflow-scrolling: touch !important;
  }
  
  /* Ajustar botones flotantes */
  .floating-buttons-container {
    position: fixed !important;
    bottom: 20px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 90% !important;
    max-width: 400px !important;
    z-index: 10050 !important;
    background: var(--surface) !important;
    border: 1px solid var(--border) !important;
    box-shadow: 0 -2px 20px rgba(0,0,0,0.2) !important;
    border-radius: 50px !important;
    padding: 6px !important;
  }
  
  /* Asegurar que el formulario se pueda scroll */
  #editor-form {
    min-height: 100vh !important;
    height: auto !important;
    overflow: visible !important;
    position: relative;
  }
  
  /* Arreglar cualquier problema de overflow */
  .prose-editor, #editor, .md-editor {
    max-width: 100% !important;
    overflow-wrap: break-word !important;
    word-wrap: break-word !important;
  }
  
  /* Asegurar que todos los paneles sean visibles */
  .editor-sidebar .panel {
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
  }
  
  /* Asegurar que los botones dentro de los paneles sean tocables */
  .panel .btn {
    min-height: 44px;
    padding: 12px 20px;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Asegurar que las imágenes destacadas se vean bien */
  #featured-preview {
    max-width: 100%;
    overflow: hidden;
  }
  
  #featured-img {
    max-width: 100%;
    height: auto;
    max-height: 150px;
  }
  
  /* Arreglar el área de sugerencias de tags */
  .tag-suggestions {
    margin-top: 8px;
    flex-wrap: wrap;
  }
  
  .tag-sug {
    font-size: 13px;
    padding: 5px 10px;
    margin: 3px;
  }
  
  .tag-chip {
    font-size: 14px;
    padding: 6px 10px;
    margin: 2px;
  }
  
  /* Asegurar que los botones de upload sean fácilmente tocables */
  label[for="feat-upload"] {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    min-width: 44px;
    padding: 10px 15px;
    margin: 5px 0;
  }
  
  /* Arreglar el spacing en el panel de imagen destacada */
  #feat-upload-progress {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.4;
  }
  
  .topbar-actions .btn span { display: none; }
  
  /* Ensure toolbar has proper spacing */
  .toolbar {
    padding: 0.4rem 0.75rem;
    margin-left: 0;
    margin-right: 0;
    width: 100%;
    box-sizing: border-box;
  }
  
  .tb-primary {
    padding-left: 0;
    padding-right: 0;
  }
}

@media (max-width: 480px) {
  .page-body {
    padding: 0.5rem !important;
  }
  
  .editor-sidebar {
    margin: 1rem 0 110px !important;
  }
  
  .editor-sidebar .panel {
    padding: 1rem !important;
  }
  
  .panel-title {
    font-size: 13px !important;
    margin-bottom: 0.75rem !important;
  }
  
  .panel-hint {
    font-size: 12px !important;
    line-height: 1.4 !important;
  }
  
  /* Ajustar botones flotantes en pantallas muy pequeñas */
  .floating-buttons-container {
    width: 95% !important;
    bottom: 10px !important;
    padding: 5px !important;
  }
  
  .floating-draft-btn,
  .floating-focus-btn,
  .floating-preview-btn,
  .floating-publish-btn {
    padding: 10px 12px !important;
    font-size: 0.9rem !important;
    min-height: 44px !important;
  }
  
  .floating-draft-btn span,
  .floating-focus-btn span,
  .floating-preview-btn span,
  .floating-publish-btn span {
    display: inline !important; /* Mostrar texto siempre */
  }
  
  /* Asegurar que los tag inputs sean compactos */
  .tag-input-wrap {
    min-height: 42px !important;
    padding: 6px !important;
  }
  
  .tag-chip {
    font-size: 13px !important;
    padding: 5px 8px !important;
    margin: 2px !important;
  }
  
  .tag-chip button {
    width: 18px;
    height: 18px;
    font-size: 16px;
  }
  
  /* Asegurar que el dropdown de tags se vea bien */
  .tag-dropdown {
    width: 100% !important;
    left: 0 !important;
    right: 0 !important;
    max-width: none !important;
    box-sizing: border-box !important;
  }
  
  /* Ajustar el textarea del excerpt */
  .panel textarea[name="excerpt"] {
    min-height: 80px;
    font-size: 14px;
  }
  
  .prose-editor { 
    padding: 1rem 0.75rem; 
    margin: 0;
  }
  
  .editor-main {
    border-left: 0;
    border-right: 0;
  }
  
  .editor-wrap {
    padding: 0 0.5rem;
  }
  
  #editor, .md-editor {
    padding: 1rem 0.5rem;
  }
  
  .title-area {
    padding: 1rem 0.5rem 0;
  }
  
  .toolbar {
    padding: 0.4rem 0.5rem;
  }
}
/* More toolbar dropdown */
.more-tb-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 8px;
    padding: 0.5rem;
    z-index: 10000;
    max-width: 300px;
    max-height: 400px;
    overflow-y: auto;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    margin-top: 4px;
    flex-wrap: wrap;
    gap: 4px;
}
.more-tb-dropdown .tb {
    min-width: 36px;
    min-height: 36px;
    font-size: 0.8rem;
}

/* Mobile dropdown menu */
.mobile-dropdown-menu {
  display: none;
  position: fixed;
  top: 100%;
  right: 0;
  background: rgba(var(--surface2-rgb, 40, 40, 50), 0.98);
  border: 1px solid var(--border2);
  border-radius: 8px;
  padding: 0.5rem;
  z-index: 10050;
  max-width: 300px;
  max-height: 400px;
  overflow-y: auto;
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  margin-top: 4px;
  flex-wrap: wrap;
  gap: 4px;
  transform: translateZ(0);
  -webkit-transform: translateZ(0);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  /* Safari specific fixes */
  -webkit-overflow-scrolling: touch;
  will-change: transform, opacity;
  isolation: isolate; /* Create new stacking context */
}

/* Add a wrapper to ensure proper stacking */
.mobile-dropdown-menu::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: -1;
  background: transparent;
}

.mobile-dropdown-menu .tb {
  min-width: 36px;
  min-height: 36px;
  font-size: 0.8rem;
}

/* Mobile dropdown button */
.mobile-dropdown-btn {
  display: none !important;
}

/* Ensure the toolbar has proper stacking context */
.toolbar {
  position: relative;
  z-index: 10000;
  isolation: isolate;
}

/* Fix for Safari's stacking context issues */
#editor, .md-editor, .prose-editor {
  position: relative;
  z-index: 1;
  isolation: isolate;
}

/* Ensure editor-sidebar has lower z-index */
.editor-sidebar {
  position: relative;
  z-index: 1;
}

/* Add this for Safari to properly handle fixed positioning */
@supports (-webkit-touch-callout: none) {
  .mobile-dropdown-menu {
    position: absolute;
    transform: translate3d(0, 0, 0);
    -webkit-transform: translate3d(0, 0, 0);
  }
  
  .toolbar {
    position: sticky;
    top: 0;
    z-index: 10000;
  }
}

/* Desktop view - hide mobile dropdown elements */
@media (min-width: 769px) {
  .mobile-dropdown-menu {
    display: none !important;
  }
  
  .mobile-dropdown-btn {
    display: none !important;
  }
  
  .mobile-hidden-tools {
    display: flex !important;
  }
}

/* For screens between 481px and 768px, show all tools */
@media (min-width: 481px) and (max-width: 768px) {
  .mobile-hidden-tools {
    display: flex !important;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
  }
  
  .mobile-dropdown-btn {
    display: none !important;
  }
  
  .mobile-dropdown-menu {
    display: none !important;
  }
}

/* Mobile view */
@media (max-width: 480px) {
  .mobile-hidden-tools {
    display: none !important;
  }
  
  .mobile-dropdown-btn {
    display: flex !important;
  }
  
  /* Adjust toolbar spacing for mobile */
  #html-toolbar, #md-toolbar {
    gap: 2px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  /* Ensure the dropdown button appears after the link button */
  #html-toolbar .mobile-dropdown-btn,
  #md-toolbar .mobile-dropdown-btn {
    margin-left: auto;
    flex-shrink: 0;
  }
  
  /* Hide specific buttons that are in the dropdown */
  #html-toolbar .tb:nth-child(n+15):not(.mobile-dropdown-btn),
  #md-toolbar .tb:nth-child(n+13):not(.mobile-dropdown-btn) {
    display: none !important;
  }
  
  /* Safari specific mobile fixes */
  @supports (-webkit-touch-callout: none) {
    .mobile-dropdown-menu {
      position: fixed;
      z-index: 10060;
      transform: translate3d(0, 0, 0);
      -webkit-transform: translate3d(0, 0, 0);
    }
    
    .toolbar {
      position: sticky;
      top: 0;
      z-index: 10050;
      background: var(--surface);
    }
    
    /* Prevent body scroll when dropdown is open */
    body.dropdown-open {
      overflow: hidden;
      position: fixed;
      width: 100%;
      height: 100%;
    }

    .editor-sidebar .panel {
      -webkit-overflow-scrolling: touch;
      overflow: visible;
    }
    
    .panel input[type="text"],
    .panel input[type="url"],
    .panel textarea,
    .panel select {
      font-size: 16px;
      line-height: 1.5;
    }
  }
}

@media (max-width: 768px) {
    /* hide unwanted right toolbar items */
    #toolbar-right .mode-switch,
    #font-size-group,
    #more-tb-btn,
    #focus-btn,
    #preview-btn {
        display: none !important;
    }
}
@media (min-width: 769px) {
    #more-tb-btn {
        display: none;
    }
}

/* Ensure editor elements have proper z-index */
#editor, .md-editor, .prose-editor {
  position: relative;
  z-index: 1;
}

/* Fix for Safari textarea/editor z-index */
#editor, #md-editor {
  isolation: isolate;
}

/* SVG icon styles for toolbar */
.fmt-btn svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  fill: none;
  vertical-align: middle;
}

/* Ensure buttons with SVG maintain proper spacing */
.fmt-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
}

/* Adjust for smaller screens */
@media (max-width: 768px) {
  .fmt-btn svg {
    width: 14px;
    height: 14px;
  }
}

@media (max-width: 480px) {
  .fmt-btn svg {
    width: 12px;
    height: 12px;
  }
}

/* Añadir un indicador visual de que hay más contenido abajo */
@media (max-width: 768px) {
  .editor-sidebar::after {
    content: "↓ Hay más contenido abajo";
    display: block;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
    padding: 10px 0 20px 0;
    opacity: 0.7;
    border-top: 1px solid var(--border2);
    margin-top: 1rem;
  }
  
  /* Ocultar en pantallas muy pequeñas */
  @media (max-width: 480px) {
    .editor-sidebar::after {
      display: none;
    }
  }
}

/* Asegurar que no haya contenido oculto */
@media (max-width: 768px) {
  .editor-sidebar > *:not(.panel) {
    display: none !important;
  }
}

/* Fix para el height del contenedor en iOS */
@media (max-width: 768px) {
  html, body {
    height: 100%;
  }
  
  body {
    display: flex;
    flex-direction: column;
  }
  
  .main {
    flex: 1;
    overflow-y: auto;
  }
}

/* Fix strikethrough styling */
.prose-editor del {
  text-decoration: line-through !important;
  color: var(--text);
  background: rgba(255, 0, 0, 0.1);
  padding: 0.1rem 0.2rem;
  border-radius: 3px;
  text-decoration-color: var(--red);
  text-decoration-thickness: 2px;
}

/* Ensure strikethrough works properly in headers */
.prose-editor h1 del,
.prose-editor h2 del,
.prose-editor h3 del,
.prose-editor h4 del {
  text-decoration: line-through !important;
  text-decoration-color: var(--red);
  text-decoration-thickness: 3px;
  background: rgba(255, 0, 0, 0.15);
}

/* Fix for inline strikethrough */
.prose-editor del,
#editor del,
[contenteditable] del {
  text-decoration: line-through !important;
  text-decoration-color: var(--red) !important;
  text-decoration-thickness: 2px !important;
  position: relative;
  display: inline;
}

/* Fix text decoration inheritance */
.prose-editor * {
  text-decoration: none !important;
}

.prose-editor del {
  text-decoration: line-through !important;
}

/* Audio player styling */
.media-audio {
  margin: 1.5rem 0;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.media-audio::before {
  content: "🎵";
  font-size: 1.5rem;
  flex-shrink: 0;
}

.media-audio audio {
  width: 100%;
  height: 36px;
  flex: 1;
}

/* Audio player in preview */
.md-preview-pane .media-audio {
  margin: 1.5rem 0;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.md-preview-pane .media-audio::before {
  content: "🎵";
  font-size: 1.5rem;
  flex-shrink: 0;
}

.md-preview-pane .media-audio audio {
  width: 100%;
  height: 36px;
  flex: 1;
}

/* Fix for contenteditable areas */
#editor del,
[contenteditable] del {
  text-decoration: line-through !important;
}

/* Ensure strikethrough is visible in all contexts */
del, s, strike {
  text-decoration: line-through !important;
  position: relative;
}

/* Fix for Safari strikethrough rendering */
@supports (-webkit-touch-callout: none) {
  .prose-editor del {
    -webkit-text-decoration-line: line-through;
    -webkit-text-decoration-color: var(--red);
    -webkit-text-decoration-thickness: 2px;
  }
}

/* Style for horizontal rule button */
.fmt-btn[title="Separador horizontal"] svg {
  /* No rotation needed for HR icon */
  transform: none;
}

/* Markdown preview strikethrough */
.md-preview-pane del {
  text-decoration: line-through !important;
  color: var(--text);
  background: rgba(255, 0, 0, 0.1);
  padding: 0.1rem 0.2rem;
  border-radius: 3px;
  text-decoration-color: var(--red);
  text-decoration-thickness: 2px;
}

.md-preview-pane s {
  text-decoration: line-through !important;
}

/* Ensure markdown strikethrough ~~text~~ is rendered properly */
.md-preview-pane s,
.md-preview-pane del {
  text-decoration: line-through !important;
}

</style>
<input type="file" id="editor-image-upload" accept="image/*" multiple style="display:none">

<script>
// ── State ──────────────────────────────────────────────────────────────────
const rawEditor    = document.getElementById('raw-editor');
const contentInput = document.getElementById('content-input');
const fmtInput     = document.getElementById('content-format-input');
const titleInput   = document.getElementById('post-title');
const slugInput    = document.getElementById('custom-slug');
const htmlToolbar  = document.getElementById('html-toolbar');

let mode       = 'visual';  // 'visual' | 'raw'
let slugManuallySet = <?= $is_new ? 'false' : 'true' ?>;


// ── Submit sync ────────────────────────────────────────────────────────────
document.getElementById('editor-form').addEventListener('submit', function(e) {
  console.log('Form submission started, mode:', mode);
  
  // Prevent double submission
  if (this.classList.contains('submitting')) {
    e.preventDefault();
    console.log('Prevented double submission');
    return;
  }
  
  // Always sync content before submission
  syncContentBeforeSave();
  
  // Verify content was captured
  const capturedContent = contentInput.value;
  console.log('Captured content length:', capturedContent.length);
  
  if (capturedContent.length === 0) {
    console.warn('Warning: Empty content captured!');
  }
  
  // Add a small delay to ensure content is captured
  setTimeout(() => {
    this.classList.add('submitting');
    console.log('Form marked as submitting');
  }, 100);
});

// ── Autosave ──────────────────────────────────────────────────────────────
const AUTOSAVE_KEY  = 'brisa_autosave_' + <?= json_encode($type) ?> + '_' + <?= json_encode($slug ?: 'new') ?>;
const AUTOSAVE_URL  = <?= json_encode(base_url() . '/admin/autosave.php') ?>;
const AUTOSAVE_CSRF = <?= json_encode(generate_csrf()) ?>;

let autosaveSlug    = <?= json_encode($slug) ?>;
let lastSavedHash   = null;
let serverSyncTimer = null;

const autosaveBar = document.createElement('div');
autosaveBar.id = 'autosave-bar';
autosaveBar.style.cssText = [
  'position:fixed','bottom:1rem','left:50%','transform:translateX(-50%)',
  'background:var(--surface2)','border:1px solid var(--border2)',
  'color:var(--muted)','font-size:0.72rem','padding:0.3rem 0.85rem',
  'border-radius:20px','z-index:8000','opacity:0','transition:opacity 0.3s',
  'pointer-events:none','font-family:inherit',
].join(';');
document.body.appendChild(autosaveBar);

function showAutosaveMsg(msg, color) {
  autosaveBar.textContent = msg;
  autosaveBar.style.color = color || 'var(--muted)';
  autosaveBar.style.opacity = '1';
  clearTimeout(autosaveBar._t);
  autosaveBar._t = setTimeout(() => autosaveBar.style.opacity = '0', 3000);
}

function getCurrentContent() {
  if (mode === 'raw') {
    // Get content from raw HTML editor
    return rawEditor ? rawEditor.value : '';
  } else {
    // For visual editor, get the innerHTML
    const editor = document.getElementById('editor');
    return editor ? editor.innerHTML : '';
  }
}

function getFormData() {
  return {
    title:          document.getElementById('post-title').value,
    content:        getCurrentContent(),
    content_format: mode,
    excerpt:        document.querySelector('[name="excerpt"]')?.value || '',
    categories:     document.getElementById('cat-hidden')?.value || '',
    tags:           document.getElementById('tag-hidden')?.value || '',
    featured_image: document.getElementById('featured_image_input')?.value || '',
    mastodon_url:   document.querySelector('[name="mastodon_url"]')?.value || '',
  };
}

function simpleHash(str) {
  let h = 0;
  for (let i = 0; i < str.length; i++) h = Math.imul(31, h) + str.charCodeAt(i) | 0;
  return h;
}

let localSaveTimer;
function scheduleLocalSave() {
  clearTimeout(localSaveTimer);
  localSaveTimer = setTimeout(() => {
    try {
      const data = getFormData();
      if (!data.title && !data.content) return;
      localStorage.setItem(AUTOSAVE_KEY, JSON.stringify({
        ...data,
        slug: autosaveSlug,
        savedAt: Date.now(),
      }));
    } catch(e) {}
  }, 2000);
}

async function syncToServer() {
  const data = getFormData();
  if (!data.title) return;

  const hash = simpleHash(data.title + data.content);
  if (hash === lastSavedHash) return;

  try {
    const fd = new FormData();
    fd.append('csrf',           AUTOSAVE_CSRF);
    fd.append('type',           <?= json_encode($type) ?>);
    fd.append('slug',           autosaveSlug);
    fd.append('title',          data.title);
    fd.append('content',        data.content);
    fd.append('content_format', data.content_format);
    fd.append('excerpt',        data.excerpt);
    fd.append('categories',     data.categories);
    fd.append('tags',           data.tags);
    fd.append('featured_image', data.featured_image);
    fd.append('mastodon_url',   data.mastodon_url);

    const res  = await fetch(AUTOSAVE_URL, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.ok) {
      lastSavedHash = hash;
      if (json.new && json.slug && !autosaveSlug) {
        autosaveSlug = json.slug;
        const url = new URL(location.href);
        url.searchParams.set('slug', json.slug);
        history.replaceState({}, '', url);
      }
      showAutosaveMsg('✓ Borrador guardado automáticamente', 'var(--green)');
      try { localStorage.removeItem(AUTOSAVE_KEY); } catch(e) {}
      
      // Also update the hidden form fields to keep them in sync
      contentInput.value = data.content;
      document.getElementById('editor-hidden').value = data.content;
      fmtInput.value = data.content_format;
      
      console.log('Autosave completed, content synced');
    }
  } catch(e) {
    console.error('Autosave error:', e);
  }
}

serverSyncTimer = setInterval(syncToServer, 60000);

[rawEditor, document.getElementById('editor')].forEach(el => {
  el?.addEventListener('input', scheduleLocalSave);
});
document.getElementById('post-title')?.addEventListener('input', scheduleLocalSave);

document.getElementById('editor-form').addEventListener('submit', () => {
  try { localStorage.removeItem(AUTOSAVE_KEY); } catch(e) {}
  clearInterval(serverSyncTimer);
  lastSavedHash = null;
}, true);

// Function to sync content before save
function syncContentBeforeSave() {
  console.log('Syncing content before save, mode:', mode);
  
  if (mode === 'raw') {
    const rawContent = rawEditor ? rawEditor.value : '';
    console.log('Raw HTML content length:', rawContent.length);
    
    // Update both hidden fields
    contentInput.value = rawContent;
    document.getElementById('editor-hidden').value = rawContent;
  } else {
    const editor = document.getElementById('editor');
    const htmlContent = editor ? editor.innerHTML : '';
    console.log('Visual HTML content length:', htmlContent.length);
    
    // Update both hidden fields
    contentInput.value = htmlContent;
    document.getElementById('editor-hidden').value = htmlContent;
  }
  
  fmtInput.value = 'html'; // Always HTML format
  console.log('Content format set to: html');
  
  // Force a DOM update
  contentInput.dispatchEvent(new Event('change', { bubbles: true }));
}

// ── Mode switch Visual ↔ RAW ───────────────────────────────────────────────
document.getElementById('switch-visual').addEventListener('click', function(e) {
  e.preventDefault();
  switchMode('visual');
  this.classList.add('active');
  document.getElementById('switch-raw').classList.remove('active');
});

document.getElementById('switch-raw').addEventListener('click', function(e) {
  e.preventDefault();
  switchMode('raw');
  this.classList.add('active');
  document.getElementById('switch-visual').classList.remove('active');
});

function switchMode(newMode) {
  if (newMode === mode) return;

  if (newMode === 'raw') {
    // Convert visual HTML to raw HTML text
    const visualEditor = document.getElementById('editor');
    const rawEditor = document.getElementById('raw-editor');
    
    // Get HTML content and escape it for the textarea
    let htmlContent = visualEditor.innerHTML;
    
    // Clean up the HTML for better readability
    htmlContent = htmlContent
      .replace(/&nbsp;/g, ' ')
      .replace(/<br\s*\/?>/gi, '\n')
      .replace(/<\/p>/gi, '</p>\n')
      .replace(/<\/div>/gi, '</div>\n')
      .replace(/<\/h[1-6]>/gi, '</h$1>\n')
      .replace(/<\/li>/gi, '</li>\n')
      .replace(/<\/ul>/gi, '</ul>\n')
      .replace(/<\/ol>/gi, '</ol>\n')
      .replace(/<\/blockquote>/gi, '</blockquote>\n')
      .replace(/<\/pre>/gi, '</pre>\n');
    
    // Set raw editor content
    rawEditor.value = htmlContent;
    
    // Switch to raw mode
    visualEditor.style.display = 'none';
    rawEditor.style.display = '';
    htmlToolbar.style.display = 'none';
  } else {
    // Convert raw HTML text to visual editor
    const rawEditor = document.getElementById('raw-editor');
    const visualEditor = document.getElementById('editor');
    
    // Get raw HTML content
    let rawContent = rawEditor.value;
    
    // Clean up line breaks and whitespace
    rawContent = rawContent
      .replace(/\n\s*\n/g, '\n')
      .trim();
    
    // Set visual editor content
    visualEditor.innerHTML = rawContent;
    
    // Switch to visual mode
    rawEditor.style.display = 'none';
    visualEditor.style.display = '';
    htmlToolbar.style.display = 'flex';
  }

  mode = newMode;
  fmtInput.value = 'html'; // Always HTML format now
  document.getElementById('switch-visual').classList.toggle('active', mode === 'visual');
  document.getElementById('switch-raw').classList.toggle('active', mode === 'raw');
}

// ── HTML Editor Toolbar ──────────────────────────────────────────────────
// Función para manejar el dropdown de formatos (HTML mode)
function formatBlock(tag) {
  const editor = document.getElementById('editor');
  if (mode !== 'html' || !editor) return;
  
  editor.focus();
  document.execCommand('formatBlock', false, tag);
}

function exec(cmd, val = null) {
  if (mode !== 'html') return;
  const editor = document.getElementById('editor');
  editor.focus();
  
  // Special handling for strikethrough to ensure it works properly
  if (cmd === 'strikeThrough') {
    execStrikeThrough();
    return;
  }
  
  // Special handling for horizontal rule
  if (cmd === 'insertHorizontalRule') {
    document.execCommand('insertHTML', false, '<hr>');
    return;
  }
  
  // Use normal execCommand for other commands
  document.execCommand(cmd, false, val);
}

function execStrikeThrough() {
  if (mode !== 'html') return;
  const editor = document.getElementById('editor');
  editor.focus();
  
  // Check if we're in a strikethrough already
  const selection = window.getSelection();
  if (selection.rangeCount === 0) return;
  
  const range = selection.getRangeAt(0);
  const ancestor = range.commonAncestorContainer;
  
  // Check if the selection is already inside a <del> element
  let delElement = ancestor.nodeType === 3 ? ancestor.parentElement : ancestor;
  let foundDel = null;
  
  // Find the nearest <del>, <s>, or <strike> element
  while (delElement && delElement !== editor) {
    if (delElement.tagName === 'DEL' || delElement.tagName === 'S' || delElement.tagName === 'STRIKE') {
      foundDel = delElement;
      break;
    }
    delElement = delElement.parentElement;
  }
  
  if (foundDel) {
    // Remove strikethrough - unwrap the element
    const parent = foundDel.parentNode;
    
    // Create a document fragment to hold the children
    const fragment = document.createDocumentFragment();
    while (foundDel.firstChild) {
      fragment.appendChild(foundDel.firstChild);
    }
    
    // Replace the del element with its children
    parent.replaceChild(fragment, foundDel);
    
    // Restore selection
    const newRange = document.createRange();
    newRange.setStart(fragment.firstChild || parent, 0);
    newRange.setEnd(fragment.lastChild || parent, fragment.lastChild ? fragment.lastChild.length || 0 : 0);
    selection.removeAllRanges();
    selection.addRange(newRange);
  } else {
    // Apply strikethrough
    const selectedText = range.toString();
    if (selectedText) {
      // Wrap selected text with <del> tag
      const del = document.createElement('del');
      del.textContent = selectedText;
      range.deleteContents();
      range.insertNode(del);
      
      // Move cursor after the inserted element
      const newRange = document.createRange();
      newRange.setStartAfter(del);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    } else {
      // If no text selected, insert a <del> element with placeholder
      const del = document.createElement('del');
      del.textContent = 'texto tachado';
      range.insertNode(del);
      
      // Select the inserted text
      const newRange = document.createRange();
      newRange.selectNodeContents(del);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }
  }
  
  // Force update
  editor.dispatchEvent(new Event('input'));
}

function insertLink() {
  const url = prompt(<?= json_encode(__raw('prompt_link_url')) ?>);
  if (url) {
    const editor = document.getElementById('editor');
    editor.focus();
    document.execCommand('createLink', false, url);
  }
}

function insertImage() {
  const url = prompt('URL de la imagen:');
  if (url) {
    const editor = document.getElementById('editor');
    editor.focus();
    document.execCommand('insertHTML', false, `<img src="${url}" alt="" style="max-width:100%;">`);
  }
}


function insertAudio() {
  const url = prompt('URL del audio (MP3, OGG, WAV, etc.):');
  if (url) {
    if (mode === 'html') {
      const editor = document.getElementById('editor');
      editor.focus();
      document.execCommand('insertHTML', false, `<div class="media-audio"><audio controls src="${url}" style="width:100%;"></audio></div>`);
    } else {
      // For markdown mode, we'll use HTML since markdown doesn't have native audio support
      const ta = mdEditor;
      const start = ta.selectionStart, end = ta.selectionEnd;
      const audioHtml = `<div class="media-audio"><audio controls src="${url}" style="width:100%;"></audio></div>`;
      ta.value = ta.value.substring(0, start) + audioHtml + ta.value.substring(end);
      ta.setSelectionRange(start + audioHtml.length, start + audioHtml.length);
      ta.focus();
      if (mdPreviewing) updateMdPreview();
    }
  }
}

function insertVideo() {
  const url = prompt('URL del video:');
  if (url) {
    const editor = document.getElementById('editor');
    editor.focus();
    document.execCommand('insertHTML', false, `<video controls src="${url}" style="max-width:100%;"></video>`);
  }
}

// Connect toolbar buttons
function setupToolbarButtons() {
  // Font size buttons
  const fontSizeUp = document.getElementById('font-size-up');
  if (fontSizeUp) {
    fontSizeUp.addEventListener('click', e => {
      e.preventDefault();
      applyEditorFontSize(editorFontSize + 1);
    });
  }

  const fontSizeDown = document.getElementById('font-size-down');
  if (fontSizeDown) {
    fontSizeDown.addEventListener('click', e => {
      e.preventDefault();
      applyEditorFontSize(editorFontSize - 1);
    });
  }

  // Preview button
  const previewBtn = document.getElementById('preview-btn');
  if (previewBtn) {
    previewBtn.addEventListener('click', e => {
      e.preventDefault();
      openPreview();
    });
  }

  // Focus button
  const focusBtn = document.getElementById('focus-btn');
  if (focusBtn) {
    focusBtn.addEventListener('click', e => {
      e.preventDefault();
      toggleFocus();
    });
  }
}

// Initialize toolbar buttons when the page loads
document.addEventListener('DOMContentLoaded', function() {
  setupToolbarButtons();
  
  // Also set up mode switchers
  document.getElementById('switch-visual').addEventListener('click', () => switchMode('visual'));
  document.getElementById('switch-raw').addEventListener('click', () => switchMode('raw'));
  
  // Update hidden textarea before form submission for all buttons
  const form = document.getElementById('editor-form');
  const draftBtn = form.querySelector('button[name="status"][value="draft"]');
  const publishBtn = document.getElementById('publish-btn');
  
  if (draftBtn) {
    draftBtn.addEventListener('click', function(e) {
      // Sync content before saving
      syncContentBeforeSave();
    });
  }
  
  if (publishBtn) {
    publishBtn.addEventListener('click', function(e) {
      // Sync content before saving
      syncContentBeforeSave();
    });
  }
  
  // Also update the form submit handler to be more robust
  form.addEventListener('submit', function(e) {
    // Sync content one more time before submission
    syncContentBeforeSave();
  });
});


// ── Slug from title ────────────────────────────────────────────────────────
titleInput.addEventListener('input', () => {
  if (!slugManuallySet) {
    slugInput.value = titleInput.value
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
  }
});
slugInput.addEventListener('input', () => { slugManuallySet = true; });

// ── Featured image preview ─────────────────────────────────────────────────
document.getElementById('featured_image_input').addEventListener('input', function() {
  const preview = document.getElementById('featured-preview');
  const img = document.getElementById('featured-img');
  if (this.value) {
    img.src = this.value;
    preview.style.display = '';
  } else {
    preview.style.display = 'none';
  }
});

// ── Tag/Category chip input ───────────────────────────────────────────────
function initTagInput(inputId, chipsId, hiddenId, suggestionsId) {
  const input    = document.getElementById(inputId);
  const chips    = document.getElementById(chipsId);
  const hidden   = document.getElementById(hiddenId);
  const sugArea  = document.getElementById(suggestionsId);
  if (!input) return;

  let values = hidden.value.split(',').map(v => v.trim()).filter(Boolean);
  values.forEach(v => addChip(v));

  function addChip(val) {
    val = val.trim();
    if (!val || values.includes(val)) return;
    values.push(val);
    updateHidden();

    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.innerHTML = `${val}<button type="button" title="Eliminar">×</button>`;
    chip.querySelector('button').addEventListener('click', () => {
      values = values.filter(v => v !== val);
      chip.remove();
      updateHidden();
      if (sugArea) {
        sugArea.querySelectorAll('.tag-sug').forEach(s => {
          if (s.textContent.trim() === val) s.classList.remove('used');
        });
      }
    });
    chips.appendChild(chip);

    if (sugArea) {
      sugArea.querySelectorAll('.tag-sug').forEach(s => {
        if (s.textContent.trim() === val) s.classList.add('used');
      });
    }
  }

  function updateHidden() {
    hidden.value = values.join(', ');
  }

  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addChip(input.value);
      input.value = '';
    } else if (e.key === 'Backspace' && !input.value && values.length) {
      const last = chips.lastElementChild;
      if (last) {
        values.pop();
        last.remove();
        updateHidden();
      }
    }
  });

  input.addEventListener('blur', () => {
    if (input.value.trim()) { addChip(input.value); input.value = ''; }
  });

  document.getElementById(chipsId.replace('-chips','-wrap'))
    ?.addEventListener('click', () => input.focus());

  if (sugArea) {
    sugArea.querySelectorAll('.tag-sug').forEach(sug => {
      if (values.includes(sug.textContent.trim())) sug.classList.add('used');
      sug.addEventListener('click', () => {
        addChip(sug.textContent.trim());
        input.focus();
      });
    });
  }
}

initTagInput('cat-input', 'cat-chips', 'cat-hidden', 'cat-suggestions');

// ── Tags autocomplete ─────────────────────────────────────────────────────
(function() {
  const input      = document.getElementById('tag-input');
  const chips      = document.getElementById('tag-chips');
  const hidden     = document.getElementById('tag-hidden');
  const dropdown   = document.getElementById('tag-dropdown');
  const dropList   = document.getElementById('tag-dropdown-list');
  const dataEl     = document.getElementById('all-tags-data');
  if (!input) return;

  const allTags = dataEl
    ? Array.from(dataEl.querySelectorAll('span')).map(s => s.textContent)
    : [];

  let values = hidden.value.split(',').map(v => v.trim()).filter(Boolean);
  let focusedIdx = -1;

  values.forEach(v => addChip(v, false));

  function addChip(val, updateList = true) {
    val = val.trim();
    if (!val || values.includes(val)) return;
    if (updateList) values.push(val);
    updateHidden();

    const chip = document.createElement('span');
    chip.className = 'tag-chip';
    chip.innerHTML = val + '<button type="button">×</button>';
    chip.querySelector('button').addEventListener('click', () => {
      values = values.filter(v => v !== val);
      chip.remove();
      updateHidden();
    });
    chips.appendChild(chip);
  }

  function updateHidden() { hidden.value = values.join(', '); }

  function showDropdown(query) {
    const q = query.toLowerCase().trim();
    if (!q) { hideDropdown(); return; }

    const matches = allTags
      .filter(t => t.toLowerCase().includes(q) && !values.includes(t))
      .slice(0, 12);

    dropList.innerHTML = '';
    focusedIdx = -1;

    if (!matches.length) {
      dropList.innerHTML = '<div class="tag-dropdown-empty">Presiona Enter para añadir "' + escHtml(query) + '"</div>';
    } else {
      matches.forEach((tag, i) => {
        const item = document.createElement('div');
        item.className = 'tag-dropdown-item';
        item.innerHTML = escHtml(tag);
        item.addEventListener('mousedown', e => {
          e.preventDefault();
          addChip(tag);
          input.value = '';
          hideDropdown();
          input.focus();
        });
        dropList.appendChild(item);
      });
    }
    dropdown.style.display = 'block';
  }

  function hideDropdown() {
    dropdown.style.display = 'none';
    focusedIdx = -1;
  }

  function moveFocus(dir) {
    const items = dropList.querySelectorAll('.tag-dropdown-item');
    if (!items.length) return;
    items[focusedIdx]?.classList.remove('focused');
    focusedIdx = Math.max(0, Math.min(focusedIdx + dir, items.length - 1));
    items[focusedIdx]?.classList.add('focused');
    items[focusedIdx]?.scrollIntoView({ block: 'nearest' });
  }

  input.addEventListener('input', () => showDropdown(input.value));
  input.addEventListener('focus', () => { if (input.value) showDropdown(input.value); });
  input.addEventListener('blur', () => setTimeout(hideDropdown, 150));

  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); moveFocus(1); return; }
    if (e.key === 'ArrowUp')   { e.preventDefault(); moveFocus(-1); return; }
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const focused = dropList.querySelector('.tag-dropdown-item.focused');
      if (focused) {
        addChip(focused.textContent);
      } else {
        addChip(input.value);
      }
      input.value = '';
      hideDropdown();
      return;
    }
    if (e.key === 'Backspace' && !input.value && values.length) {
      values.pop();
      chips.lastElementChild?.remove();
      updateHidden();
    }
    if (e.key === 'Escape') hideDropdown();
  });

  document.getElementById('tag-wrap')?.addEventListener('click', () => input.focus());

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
})();

// ── Featured image upload ─────────────────────────────────────────────────
const featUpload   = document.getElementById('feat-upload');
const featInput    = document.getElementById('featured_image_input');
const featProgress = document.getElementById('feat-upload-progress');

if (featUpload) {
  featUpload.addEventListener('change', async () => {
    const file = featUpload.files[0];
    if (!file) return;
    featProgress.textContent = 'Subiendo…';
    featProgress.style.display = '';

    const fd = new FormData();
    fd.append('upload', file);
    fd.append('csrf', <?= json_encode(generate_csrf()) ?>);

    try {
      const res  = await fetch(<?= json_encode(base_url() . '/admin/upload_media.php') ?>, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.url) {
        featInput.value = data.url;
        document.getElementById('featured-img').src = data.url;
        document.getElementById('featured-preview').style.display = '';
        featProgress.textContent = '✓ Subida correctamente';
        setTimeout(() => featProgress.style.display = 'none', 2000);
      } else {
        featProgress.textContent = '⚠ Error: ' + (data.error || 'desconocido');
      }
    } catch(e) {
      featProgress.textContent = '⚠ Error al subir';
    }
  });
}

// ── Editor font size ──────────────────────────────────────────────────────
const FONT_SIZE_KEY = 'brisa_editor_font_size';
const FONT_MIN = 12, FONT_MAX = 28, FONT_STEP = 1;
let editorFontSize = parseInt(localStorage.getItem(FONT_SIZE_KEY) || '16', 10);

function applyEditorFontSize(size) {
  editorFontSize = Math.min(FONT_MAX, Math.max(FONT_MIN, size));
  
  // Apply to both editors
  const visualEditor = document.getElementById('editor');
  const rawEditor = document.getElementById('raw-editor');
  
  if (visualEditor) visualEditor.style.fontSize = editorFontSize + 'px';
  if (rawEditor) rawEditor.style.fontSize = editorFontSize + 'px';
  
  // Update the label
  const label = document.getElementById('font-size-label');
  if (label) label.textContent = editorFontSize;
  
  localStorage.setItem(FONT_SIZE_KEY, editorFontSize);
}

// Update event listeners to handle both sets of buttons
document.getElementById('font-size-up')?.addEventListener('click', () => applyEditorFontSize(editorFontSize + FONT_STEP));
document.getElementById('font-size-down')?.addEventListener('click', () => applyEditorFontSize(editorFontSize - FONT_STEP));

// Initialize font size on page load
applyEditorFontSize(editorFontSize);

// ── Article Preview ───────────────────────────────────────────────────────
const previewBtn   = document.getElementById('preview-btn');
const postSlug     = <?= json_encode($post['slug'] ?? '') ?>;
const postType     = <?= json_encode($type) ?>;
const siteBase     = <?= json_encode(base_url()) ?>;

previewBtn?.addEventListener('click', openPreview);

function openPreview() {
  const adminBase = siteBase + '/admin';
  
  // Get current content
  let content = '';
  if (mode === 'raw') {
    content = rawEditor.value;
  } else {
    content = document.getElementById('editor').innerHTML;
  }
  
  const title   = document.getElementById('post-title').value || '(Sin título)';
  const excerpt = document.querySelector('[name="excerpt"]')?.value || '';
  const status  = document.getElementById('status-select')?.value || 'draft';
  
  // If we have a saved post, use the proper preview URL
  if (postSlug) {
    const url = adminBase + '/preview.php?type=' + encodeURIComponent(postType)
              + '&slug=' + encodeURIComponent(postSlug)
              + '&t=' + Date.now();
    window.open(url, '_blank');
    return;
  }
  
  // For unsaved posts, create a temporary preview
  const accent  = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#e05c1a';
  
  const html = `<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vista previa — ${title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#f1f1f1;color:#1a1a1a;font-family:'Roboto',sans-serif;font-size:16px;line-height:1.5;padding:2rem 1rem}
.banner{position:sticky;top:0;background:#f59e0b;color:#000;font-weight:700;font-size:0.78rem;text-align:center;padding:0.4rem 1rem;margin:-2rem -1rem 2rem;letter-spacing:0.04em}
.wrap{max-width:780px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;padding:2rem}
h1{font-family:'Roboto Condensed',sans-serif;font-size:clamp(1.5rem,4vw,2.2rem);font-weight:700;margin-bottom:1rem;line-height:1.2}
.excerpt{color:#555;font-size:1.05rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e0e0e0;font-style:italic}
.prose{font-size:1rem;line-height:1.8;color:#444}
.prose h2{font-family:'Roboto Condensed',sans-serif;font-size:1.4rem;font-weight:700;color:#1a1a1a;margin:2rem 0 0.75rem;border-bottom:2px solid ${accent};padding-bottom:0.3rem;text-transform:uppercase}
.prose h3{font-family:'Roboto Condensed',sans-serif;font-size:1.15rem;font-weight:700;color:#1a1a1a;margin:1.5rem 0 0.5rem}
.prose p{margin-bottom:1.25rem}
.prose a{color:${accent}}
.prose strong{color:#1a1a1a;font-weight:700}
.prose blockquote{border-left:3px solid ${accent};background:#f9f9f9;padding:0.75rem 1.25rem;margin:1.5rem 0;color:#555;font-style:italic}
.prose pre{background:#1e1e2e;color:#cdd6f4;border-radius:4px;padding:1rem;overflow-x:auto;font-size:0.875rem;margin:1.5rem 0}
.prose code{background:#f0f0f0;border:1px solid #e0e0e0;padding:.1rem .4rem;border-radius:3px;font-size:.875em;color:#c7254e}
.prose pre code{background:none;border:none;padding:0;color:inherit}
.prose ul,.prose ol{padding-left:1.75rem;margin-bottom:1.25rem}
.prose li{margin-bottom:.35rem}
.prose img{max-width:100%;border-radius:4px;margin:1.5rem 0;display:block}
.prose hr{border:none;border-top:1px solid #e0e0e0;margin:2rem 0}
.yt-embed{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:1.5rem 0}
.yt-embed iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:none}
.media-audio{margin:1.5rem 0;background:#f5f5f5;border:1px solid #e0e0e0;border-left:3px solid ${accent};border-radius:8px;padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem}
.media-audio::before{content:"🎵";font-size:1.5rem;flex-shrink:0}
.media-audio audio{width:100%;height:36px}
.media-video{margin:1.5rem 0;border-radius:8px;overflow:hidden;background:#000;line-height:0}
.media-video video{width:100%;display:block}
</style></head><body>
<div class="banner">👁 VISTA PREVIA — ${status === 'draft' ? 'BORRADOR' : 'PUBLICADO'} — guarda el artículo para ver la vista previa con el tema activo del sitio</div>
<div class="wrap">
  <h1>${title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</h1>
  ${excerpt ? '<p class="excerpt">' + excerpt.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>' : ''}
  <div class="prose">${content}</div>
</div>
</body></html>`;

  const blob = new Blob([html], { type: 'text/html' });
  const url  = URL.createObjectURL(blob);
  const tab  = window.open(url, '_blank');
  if (tab) {
    tab.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
  } else {
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }
}

// ── More toolbar dropdown ────────────────────────────────────────────────
function setupMoreDropdown() {
  const moreBtn = document.getElementById('more-tb-btn');
  const dropdown = document.getElementById('more-tb-dropdown');
  const content = document.getElementById('more-tb-content');
  if (!moreBtn || !dropdown) return;
  dropdown.style.display = 'none';

  function populateDropdown() {
    content.innerHTML = '';
    const sourceToolbar = mode === 'html' ? document.getElementById('html-toolbar') : document.getElementById('md-toolbar');
    if (!sourceToolbar) return;
    // Clone buttons except mode switchers and more button
    const btns = sourceToolbar.querySelectorAll('.tb:not(.mode-switch):not(#more-tb-btn)');
    btns.forEach(btn => {
      const clone = btn.cloneNode(true);
      // ensure click works
      clone.addEventListener('click', (e) => {
        e.preventDefault();
        btn.click();
        dropdown.style.display = 'none';
      });
      content.appendChild(clone);
    });
  }

  window.toggleMoreDropdown = function() {
    populateDropdown();
    const isVisible = dropdown.style.display === 'flex';
    dropdown.style.display = isVisible ? 'none' : 'flex';
  };

  moreBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    window.toggleMoreDropdown();
  });

  document.addEventListener('click', (e) => {
    if (!moreBtn.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
}

// ── Image upload (button & drag‑and‑drop) ────────────────────────────────
function setupImageUpload() {
  const fileInput = document.getElementById('editor-image-upload');
  const uploadHtmlBtn = document.getElementById('upload-img-html');
  const uploadMdBtn = document.getElementById('upload-img-md');

  function triggerFileInput() {
    if (fileInput) fileInput.click();
  }

  if (uploadHtmlBtn) uploadHtmlBtn.addEventListener('click', triggerFileInput);
  if (uploadMdBtn) uploadMdBtn.addEventListener('click', triggerFileInput);

  async function uploadImage(file) {
    const fd = new FormData();
    fd.append('upload', file);
    fd.append('csrf', <?= json_encode(generate_csrf()) ?>);
    try {
      const res = await fetch(<?= json_encode(base_url() . '/admin/upload_media.php') ?>, {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      return data.url || null;
    } catch (e) {
      console.error('Upload error:', e);
      return null;
    }
  }

  async function insertImageUrl(url, alt = '') {
    if (mode === 'html') {
      const editor = document.getElementById('editor');
      editor.focus();
      document.execCommand('insertHTML', false, `<img src="${url}" alt="${alt}" style="max-width:100%;">`);
    } else {
      const ta = mdEditor;
      const start = ta.selectionStart;
      const end = ta.selectionEnd;
      const md = `![${alt}](${url})`;
      ta.value = ta.value.substring(0, start) + md + ta.value.substring(end);
      ta.setSelectionRange(start + md.length, start + md.length);
      ta.focus();
      if (mdPreviewing) updateMdPreview();
    }
  }

  fileInput.addEventListener('change', async (e) => {
    const files = Array.from(e.target.files);
    for (const file of files) {
      const url = await uploadImage(file);
      if (url) {
        await insertImageUrl(url, file.name.replace(/\.[^/.]+$/, ''));
      }
    }
    fileInput.value = '';
  });

  // Drag & drop
  const dropZones = [document.getElementById('editor'), mdEditor];
  dropZones.forEach(zone => {
    if (!zone) return;
    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
    });
    zone.addEventListener('drop', async (e) => {
      e.preventDefault();
      const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
      for (const file of files) {
        const url = await uploadImage(file);
        if (url) {
          await insertImageUrl(url, file.name.replace(/\.[^/.]+$/, ''));
        }
      }
    });
  });
}

// ── Mobile Dropdown Menu ──────────────────────────────────────────────────
function setupMobileDropdown() {
  const htmlDropdownBtn = document.getElementById('mobile-dropdown-btn');
  const htmlDropdownMenu = document.getElementById('mobile-dropdown-menu');
  const htmlDropdownContent = document.getElementById('mobile-dropdown-content');
  
  const mdDropdownBtn = document.getElementById('mobile-dropdown-btn-md');
  const mdDropdownMenu = document.getElementById('mobile-dropdown-menu-md');
  const mdDropdownContent = document.getElementById('mobile-dropdown-content-md');
  
  function setupDropdown(dropdownBtn, dropdownMenu, dropdownContent, isMarkdown = false) {
    if (!dropdownBtn || !dropdownMenu) return;
    
    dropdownMenu.style.display = 'none';
    
    function populateDropdown() {
      dropdownContent.innerHTML = '';
      const sourceSelector = isMarkdown ? '.mobile-hidden-tools' : '.mobile-hidden-tools';
      const sourceToolbar = dropdownBtn.closest(isMarkdown ? '#md-toolbar' : '#html-toolbar');
      if (!sourceToolbar) return;
      
      const hiddenTools = sourceToolbar.querySelector(sourceSelector);
      if (!hiddenTools) return;
      
      // Clone all buttons from the hidden tools section
      const btns = hiddenTools.querySelectorAll('.tb');
      btns.forEach(btn => {
        const clone = btn.cloneNode(true);
        // Remove any existing event listeners and add new ones
        const newClone = clone.cloneNode(true);
        newClone.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          btn.click();
          dropdownMenu.style.display = 'none';
        });
        dropdownContent.appendChild(newClone);
      });
      
      // Also clone separators if any
      const separators = hiddenTools.querySelectorAll('.toolbar-sep');
      separators.forEach(sep => {
        const clone = sep.cloneNode(true);
        clone.style.margin = '4px 2px';
        dropdownContent.appendChild(clone);
      });
    }
    
    dropdownBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      // Check if we're on mobile (≤480px)
      if (window.innerWidth > 480) {
        return; // Don't show dropdown on desktop/tablet
      }
      
      populateDropdown();
      
      // Position the dropdown - fixed positioning for Safari compatibility
      const rect = dropdownBtn.getBoundingClientRect();
      dropdownMenu.style.position = 'fixed';
      dropdownMenu.style.top = (rect.bottom + window.scrollY) + 'px';
      dropdownMenu.style.left = Math.max(10, rect.left) + 'px';
      dropdownMenu.style.right = 'auto';
      dropdownMenu.style.maxWidth = (window.innerWidth - 20) + 'px';
      
      const isVisible = dropdownMenu.style.display === 'flex';
      dropdownMenu.style.display = isVisible ? 'none' : 'flex';
      
      // Force reflow for Safari
      dropdownMenu.offsetHeight;
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.style.display = 'none';
      }
    });
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && dropdownMenu.style.display === 'flex') {
        dropdownMenu.style.display = 'none';
      }
    });
    
    // Close dropdown on window resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 480) {
        dropdownMenu.style.display = 'none';
      }
    });
  }
  
  // Setup HTML toolbar dropdown
  setupDropdown(htmlDropdownBtn, htmlDropdownMenu, htmlDropdownContent, false);
  
  // Setup Markdown toolbar dropdown
  setupDropdown(mdDropdownBtn, mdDropdownMenu, mdDropdownContent, true);
}

// ── Mobile toolbar helpers ────────────────────────────────────────────────
window.execMobile = function(cmd, val) {
  if (mode !== 'html') return;
  exec(cmd, val);
};
window.execMobileLink = function() {
  if (mode !== 'html') return;
  insertLink();
};
window.execMobileImg = function() {
  if (mode !== 'html') return;
  insertImage();
};
window.execMobileAudio = function() {
  if (mode !== 'html') return;
  insertAudio();
};
window.execMobileVideo = function() {
  if (mode !== 'html') return;
  insertVideo();
};

// Mobile menu toggle for toolbar overflow
function setupMobileMenu() {
  const moreBtn = document.getElementById('tb-more-btn');
  const overflowMenu = document.getElementById('tb-overflow-menu');
  
  if (!moreBtn || !overflowMenu) return;
  
  // Initially hide the menu
  overflowMenu.style.display = 'none';
  
  moreBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    e.preventDefault();
    
    if (overflowMenu.style.display === 'flex') {
      overflowMenu.style.display = 'none';
    } else {
      overflowMenu.style.display = 'flex';
      // Position the menu
      const rect = moreBtn.getBoundingClientRect();
      overflowMenu.style.position = 'fixed';
      overflowMenu.style.top = rect.bottom + 'px';
      overflowMenu.style.right = (window.innerWidth - rect.right) + 'px';
      
      // Close on click outside
      setTimeout(() => {
        const clickOutside = (event) => {
          if (!moreBtn.contains(event.target) && !overflowMenu.contains(event.target)) {
            overflowMenu.style.display = 'none';
            document.removeEventListener('click', clickOutside);
          }
        };
        document.addEventListener('click', clickOutside);
      }, 10);
    }
  });
  
  // Close on escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && overflowMenu.style.display === 'flex') {
      overflowMenu.style.display = 'none';
    }
  });
}

// Initialize mobile menu
document.addEventListener('DOMContentLoaded', function() {
  setupMobileDropdown();
  setupMobileMenu();
  setupMoreDropdown();
  setupImageUpload();
  
  // Hide mobile dropdown on resize to desktop
  window.addEventListener('resize', function() {
    if (window.innerWidth > 480) {
      const dropdowns = document.querySelectorAll('.mobile-dropdown-menu');
      dropdowns.forEach(dropdown => {
        dropdown.style.display = 'none';
      });
    }
  });
});

// ── Focus mode ────────────────────────────────────────────────────────────
window.toggleFocus = function() {
  document.body.classList.toggle('focus-mode');
  const active = document.body.classList.contains('focus-mode');
  const focusBtn = document.getElementById('focus-btn');
  if (focusBtn) {
    focusBtn.setAttribute('title', active ? 'Salir del modo sin distracciones (Esc)' : 'Modo sin distracciones (F11)');
  }
};

const focusBtn = document.getElementById('focus-btn');
if (focusBtn) {
  focusBtn.addEventListener('click', toggleFocus);
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.body.classList.contains('focus-mode')) toggleFocus();
  if (e.key === 'F11' && !e.altKey) { e.preventDefault(); toggleFocus(); }
});

// Añadir al final del script existente, después de la función adjustMobileLayout()
function fixSidebarLayout() {
  if (window.innerWidth <= 768) {
    const sidebar = document.querySelector('.editor-sidebar');
    const panels = sidebar?.querySelectorAll('.panel');
    
    if (panels) {
      panels.forEach(panel => {
        // Asegurar que cada panel tenga suficiente espacio
        panel.style.maxHeight = 'none';
        panel.style.overflow = 'visible';
        
        // Arreglar cualquier input dentro del panel
        const inputs = panel.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
          input.style.maxWidth = '100%';
          input.style.boxSizing = 'border-box';
        });
      });
    }
  }
}

// Ejecutar cuando se cargue y cuando cambie el tamaño
window.addEventListener('load', fixSidebarLayout);
window.addEventListener('resize', fixSidebarLayout);

// También ejecutar cuando se modifiquen los tags o categorías
const observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
    if (mutation.type === 'childList' || mutation.type === 'characterData') {
      setTimeout(fixSidebarLayout, 100);
    }
  });
});

// Observar cambios en el sidebar
const sidebar = document.querySelector('.editor-sidebar');
if (sidebar) {
  observer.observe(sidebar, { 
    childList: true, 
    subtree: true,
    characterData: true 
  });
}

// Añadir al final del script existente
function fixIOSLayout() {
  if (window.innerWidth <= 768 && /iPhone|iPad|iPod/.test(navigator.userAgent)) {
    // Forzar el redibujado para iOS
    document.body.style.overflow = 'auto';
    document.body.style.height = 'auto';
    
    // Ajustar el height del viewport
    const viewportHeight = window.innerHeight;
    document.documentElement.style.height = viewportHeight + 'px';
    
    // Asegurar que el sidebar sea scrollable
    const sidebar = document.querySelector('.editor-sidebar');
    if (sidebar) {
      sidebar.style.height = 'auto';
      sidebar.style.maxHeight = 'none';
      sidebar.style.overflow = 'visible';
      sidebar.style.position = 'relative';
    }
    
    // Ajustar el formulario
    const form = document.getElementById('editor-form');
    if (form) {
      form.style.minHeight = '100vh';
      form.style.overflow = 'visible';
    }
    
    // Ajustar el page-body
    const pageBody = document.querySelector('.page-body');
    if (pageBody) {
      pageBody.style.height = 'auto';
      pageBody.style.minHeight = '100vh';
      pageBody.style.overflowY = 'scroll';
      pageBody.style.webkitOverflowScrolling = 'touch';
    }
  }
}

// Ejecutar cuando se cargue
window.addEventListener('load', fixIOSLayout);
window.addEventListener('DOMContentLoaded', fixIOSLayout);

// También ejecutar en resize y orientation change
window.addEventListener('resize', fixIOSLayout);
window.addEventListener('orientationchange', function() {
  setTimeout(fixIOSLayout, 300);
});

// ── Backup Preview ────────────────────────────────────────────────────────
function previewBackup(timestamp, type, slug) {
    if (!confirm('Preview this backup version? You can copy content from the preview.')) {
        return;
    }
    
    fetch(`<?= base_url() ?>/admin/backup_preview.php?type=${encodeURIComponent(type)}&slug=${encodeURIComponent(slug)}&timestamp=${encodeURIComponent(timestamp)}&csrf=<?= $csrf ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.content) {
                // Open preview in a new window
                const previewWindow = window.open('', '_blank');
                const html = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Backup Preview - ${timestamp}</title>
                        <style>
                            body { font-family: sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto; }
                            .header { background: #f0f0f0; padding: 1rem; border-radius: 4px; margin-bottom: 2rem; }
                            .content { line-height: 1.6; }
                            .actions { margin-top: 2rem; padding: 1rem; background: #f9f9f9; border-radius: 4px; }
                            button { padding: 0.5rem 1rem; margin-right: 0.5rem; cursor: pointer; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>Backup Preview</h2>
                            <p><strong>Timestamp:</strong> ${timestamp}</p>
                            <p><strong>Article:</strong> ${slug}</p>
                        </div>
                        <div class="content">${data.content}</div>
                        <div class="actions">
                            <button onclick="window.close()">Close</button>
                            <button onclick="copyToClipboard('${data.content.replace(/'/g, "\\'")}')">Copy Content</button>
                        </div>
                        <script>
                            function copyToClipboard(text) {
                                navigator.clipboard.writeText(text).then(() => {
                                    alert('Content copied to clipboard!');
                                });
                            }
                        <\/script>
                    </body>
                    </html>
                `;
                previewWindow.document.write(html);
                previewWindow.document.close();
            } else {
                alert('Error loading backup: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading backup');
        });
}

// Añadir un listener para cuando el teclado aparezca/desaparezca
let originalViewportHeight = window.innerHeight;
window.addEventListener('resize', function() {
  if (window.innerHeight < originalViewportHeight) {
    // Teclado visible
    document.body.style.paddingBottom = '300px';
  } else {
    // Teclado oculto
    document.body.style.paddingBottom = '0';
  }
});

// Prevenir comportamiento por defecto de iOS
document.addEventListener('touchmove', function(e) {
  // Permitir scroll solo en elementos scrollables
  if (e.target.classList.contains('editor-wrap') || 
      e.target.classList.contains('editor-sidebar') ||
      e.target.classList.contains('tag-chips') ||
      e.target.tagName === 'TEXTAREA') {
    return;
  }
  
  // Prevenir scroll en otros lugares si es necesario
  const scrollable = e.target.closest('.editor-sidebar');
  if (!scrollable && e.target.closest('.editor-main')) {
    e.preventDefault();
  }
}, { passive: false });

</script>
<!-- focus hint -->
<div class="focus-exit-hint">
  <button onclick="window.toggleFocus && window.toggleFocus()" class="focus-exit-btn"><?= __("focus_exit_hint") ?> (Salir)</button>
</div>

<!-- Floating buttons for mobile -->
<div id="floating-buttons-container" class="floating-buttons-container">
    <button id="floating-draft-btn" class="floating-draft-btn" type="button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <span><?= __("editor_save_draft") ?></span>
    </button>
    <button id="floating-focus-btn" class="floating-focus-btn" type="button" title="Modo sin distracción">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
    </button>
    <button id="floating-preview-btn" class="floating-preview-btn" type="button" title="Vista previa">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
    <button id="floating-publish-btn" class="floating-publish-btn" type="button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span id="floating-publish-label"><?= $post && $post['status'] === 'published' ? __("editor_update") : __("editor_publish") ?></span>
    </button>
</div>

<script>
// Floating buttons logic for mobile
(function() {
    const floatingContainer = document.getElementById('floating-buttons-container');
    const floatingDraftBtn  = document.getElementById('floating-draft-btn');
    const floatingFocusBtn  = document.getElementById('floating-focus-btn');
    const floatingPreviewBtn= document.getElementById('floating-preview-btn');
    const floatingPublishBtn= document.getElementById('floating-publish-btn');
    const publishBtn        = document.getElementById('publish-btn');
    const statusSelect      = document.getElementById('status-select');
    const form              = document.getElementById('editor-form');

    if (!floatingContainer || !floatingPublishBtn || !floatingDraftBtn) return;

    function updateFloatingLabel() {
        const isPublished = statusSelect ? statusSelect.value === 'published' : false;
        const label = isPublished ? '<?= __raw("editor_update") ?>' : '<?= __raw("editor_publish") ?>';
        document.getElementById('floating-publish-label').textContent = label;
    }

    floatingDraftBtn.addEventListener('click', function() {
        console.log('Floating draft button clicked');
        // Sync content before saving
        syncContentBeforeSave();
        
        if (statusSelect && statusSelect.value !== 'draft') {
            statusSelect.value = 'draft';
        }
        const hiddenStatus = document.createElement('input');
        hiddenStatus.type = 'hidden';
        hiddenStatus.name = 'status';
        hiddenStatus.value = 'draft';
        form.appendChild(hiddenStatus);
        form.submit();
    });

    floatingFocusBtn.addEventListener('click', function() {
        if (typeof window.toggleFocus === 'function') {
            window.toggleFocus();
        }
    });

    floatingPreviewBtn.addEventListener('click', function() {
        if (typeof window.openPreview === 'function') {
            window.openPreview();
        }
    });

    floatingPublishBtn.addEventListener('click', function() {
        console.log('Floating publish button clicked');
        // Sync content before saving
        syncContentBeforeSave();
        
        if (statusSelect && statusSelect.value !== 'published') {
            statusSelect.value = 'published';
        }
        const hiddenStatus = document.createElement('input');
        hiddenStatus.type = 'hidden';
        hiddenStatus.name = 'status';
        hiddenStatus.value = 'published';
        form.appendChild(hiddenStatus);
        form.submit();
    });

    if (statusSelect) {
        statusSelect.addEventListener('change', updateFloatingLabel);
    }
    updateFloatingLabel();

    function checkMobile() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            publishBtn.style.display = 'none';
            floatingContainer.style.display = 'flex';
        } else {
            publishBtn.style.display = '';
            floatingContainer.style.display = 'none';
        }
    }

    window.addEventListener('resize', checkMobile);
    checkMobile();
})();
</script>

<style>
.floating-buttons-container {
    display: none;
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface);
    border-radius: 50px;
    padding: 6px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.3);
    border: 1px solid var(--border);
}
.floating-draft-btn,
.floating-focus-btn,
.floating-preview-btn,
.floating-publish-btn {
    border: none;
    border-radius: 50px;
    padding: 14px 22px;
    font-size: 1rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    white-space: nowrap;
    min-height: 52px;
}
.floating-draft-btn,
.floating-focus-btn,
.floating-preview-btn {
    background: var(--surface2);
    color: var(--text);
    border: 1px solid var(--border2);
}
.floating-draft-btn:hover,
.floating-focus-btn:hover,
.floating-preview-btn:hover {
    background: var(--border);
}
.floating-draft-btn:active,
.floating-focus-btn:active,
.floating-preview-btn:active {
    transform: scale(0.98);
}
.floating-publish-btn {
    background: var(--accent);
    color: #fff;
}
.floating-publish-btn:hover {
    opacity: 0.9;
    transform: scale(1.05);
}
.floating-publish-btn:active {
    transform: scale(0.98);
}
.floating-focus-btn,
.floating-preview-btn {
    gap: 0;
}
.floating-focus-btn span,
.floating-preview-btn span {
    display: none;
}
@media (max-width: 768px) {
    #publish-btn,
    button[name="status"][value="draft"] {
        display: none !important;
    }
    .floating-buttons-container {
        display: flex;
    }
    @media (max-width: 480px) {
        .floating-buttons-container {
            padding: 5px;
        }
        .floating-draft-btn,
        .floating-focus-btn,
        .floating-preview-btn,
        .floating-publish-btn {
            padding: 12px 16px;
            font-size: 0.95rem;
            min-height: 48px;
        }
        .floating-draft-btn svg,
        .floating-focus-btn svg,
        .floating-preview-btn svg,
        .floating-publish-btn svg {
            width: 16px;
            height: 16px;
        }
    }
}
</style>
</body></html>
