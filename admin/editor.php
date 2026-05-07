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
              <button type="button" class="fmt-btn" id="focus-btn" title="<?= __raw("tb_focus") ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg></button>
            </div>
          </div>
          
          <div class="title-area">
            <input type="text" name="title" id="post-title" placeholder="<?= __raw("editor_title_ph") ?>"
              value="<?= htmlspecialchars($post['title'] ?? '') ?>" required autocomplete="off">
          </div>
          
          <!-- Editor panes -->
          <div class="editor-wrap">
            <div id="editor" class="prose-editor" contenteditable="true"><?= $post['content'] ?? '' ?></div>
            <textarea id="editor-hidden" name="content" style="display:none"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
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
            <div id="tag-dropdown" class="tag-dropdown" style="display:none">
              <div id="tag-dropdown-list"></div>
            </div>
            <div class="panel-hint">Escribe y selecciona de las sugerencias o presiona Enter para añadir.</div>
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

<!-- External CSS -->
<link rel="stylesheet" href="<?= base_url() ?>/admin/assets/css/editor.css">

<!-- Hidden file input for image uploads -->
<input type="file" id="editor-image-upload" accept="image/*" multiple style="display:none">

<!-- Focus mode exit hint -->
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

<!-- Pass PHP variables to JS (single source, single CSRF token) -->
<script>
window.BRISA = {
  baseUrl: <?= json_encode(base_url()) ?>,
  csrf: <?= json_encode($csrf) ?>,
  type: <?= json_encode($type) ?>,
  slug: <?= json_encode($slug ?: '') ?>,
  isNew: <?= $is_new ? 'true' : 'false' ?>,
  i18n: {
    promptLinkUrl: <?= json_encode(__raw('prompt_link_url')) ?>,
    editorUpdate: <?= json_encode(__raw('editor_update')) ?>,
    editorPublish: <?= json_encode(__raw('editor_publish')) ?>
  }
};
</script>

<!-- External JS (defer ensures DOM is ready) -->
<script src="<?= base_url() ?>/admin/assets/js/editor.js" defer></script>

</body></html>
