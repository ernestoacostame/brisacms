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
$content_format = $post['content_format'] ?? 'html';
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
      <input type="hidden" name="content" id="content-input">
      <input type="hidden" name="content_format" id="content-format-input" value="<?= $content_format ?>">

      <div class="editor-layout">
        <!-- Main editor area -->
        <div class="editor-main">
          <div class="title-area">
            <input type="text" name="title" id="post-title" placeholder="<?= __raw("editor_title_ph") ?>"
              value="<?= htmlspecialchars($post['title'] ?? '') ?>" required autocomplete="off">
          </div>

          <!-- Toolbar -->
          <div class="toolbar" id="toolbar">
            <!-- HTML toolbar -->
            <div id="html-toolbar" style="display:flex;align-items:center;gap:2px;flex-wrap:wrap;flex:1">
              <div class="toolbar-group">
                <button type="button" class="tb" data-cmd="formatBlock" data-val="h2" title="<?= __raw("tb_h2") ?>">H2</button>
                <button type="button" class="tb" data-cmd="formatBlock" data-val="h3" title="<?= __raw("tb_h3") ?>">H3</button>
                <button type="button" class="tb" data-cmd="formatBlock" data-val="p" title="<?= __raw("tb_p") ?>">¶</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb" data-cmd="bold" title="<?= __raw("tb_bold") ?>"><b>B</b></button>
                <button type="button" class="tb" data-cmd="italic" title="<?= __raw("tb_italic") ?>"><i>I</i></button>
                <button type="button" class="tb" data-cmd="underline" title="<?= __raw("tb_underline") ?>"><u>U</u></button>
                <button type="button" class="tb" data-cmd="strikeThrough" title="<?= __raw("tb_strike") ?>"><s>S</s></button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb" data-cmd="insertUnorderedList" title="<?= __raw("tb_ul") ?>">≡</button>
                <button type="button" class="tb" data-cmd="insertOrderedList" title="<?= __raw("tb_ol") ?>">1.</button>
                <button type="button" class="tb" data-cmd="formatBlock" data-val="blockquote" title="<?= __raw("tb_quote") ?>">"</button>
                <button type="button" class="tb" data-cmd="formatBlock" data-val="pre" title="<?= __raw("tb_code") ?>">&lt;/&gt;</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb" id="link-btn" title="<?= __raw("tb_link") ?>">🔗</button>
                <button type="button" class="tb" id="img-btn" title="<?= __raw("tb_image") ?>">🖼</button>
                <button type="button" class="tb" id="audio-btn" title="<?= __raw("tb_audio") ?>">🎵</button>
                <button type="button" class="tb" id="video-btn" title="<?= __raw("tb_video") ?>">🎬</button>
                <button type="button" class="tb" data-cmd="insertHorizontalRule" title="<?= __raw("tb_hr") ?>">—</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb" data-cmd="undo">↩</button>
                <button type="button" class="tb" data-cmd="redo">↪</button>
              </div>
              <div class="toolbar-sep"></div>
              <button type="button" class="tb tb-mode" id="html-raw-toggle" title="<?= __raw("tb_html") ?>">&lt;/&gt; HTML</button>
            </div>

            <!-- Markdown toolbar -->
            <div id="md-toolbar" style="display:none;align-items:center;gap:2px;flex-wrap:wrap;flex:1">
              <div class="toolbar-group">
                <button type="button" class="tb md-btn" data-md="## " title="<?= __raw("tb_h2") ?>">H2</button>
                <button type="button" class="tb md-btn" data-md="### " title="<?= __raw("tb_h3") ?>">H3</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb md-btn" data-wrap="**" title="<?= __raw("tb_bold") ?>"><b>B</b></button>
                <button type="button" class="tb md-btn" data-wrap="*" title="<?= __raw("tb_italic") ?>"><i>I</i></button>
                <button type="button" class="tb md-btn" data-wrap="~~" title="<?= __raw("tb_strike") ?>"><s>S</s></button>
                <button type="button" class="tb md-btn" data-wrap="`" title="Inline code">` `</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb md-btn" data-md="- " title="<?= __raw("tb_ul") ?>">≡</button>
                <button type="button" class="tb md-btn" data-md="1. " title="<?= __raw("tb_ol") ?>">1.</button>
                <button type="button" class="tb md-btn" data-md="> " title="<?= __raw("tb_quote") ?>">"</button>
                <button type="button" class="tb md-btn" data-block="```\n\n```" title="<?= __raw("tb_code") ?>">&lt;/&gt;</button>
              </div>
              <div class="toolbar-sep"></div>
              <div class="toolbar-group">
                <button type="button" class="tb" id="md-link-btn" title="<?= __raw("tb_link") ?>">🔗</button>
                <button type="button" class="tb" id="md-img-btn" title="<?= __raw("tb_image") ?>">🖼</button>
                <button type="button" class="tb md-btn" data-md="---" title="<?= __raw("tb_hr") ?>">—</button>
              </div>
              <div class="toolbar-sep"></div>
              <button type="button" class="tb tb-mode" id="md-preview-btn"><?= __("tb_preview") ?></button>
            </div>

            <!-- Mode switcher + extras (always visible) -->
            <div class="toolbar-right" style="margin-left:auto;display:flex;gap:4px;padding-left:8px;border-left:1px solid var(--border2)">
              <button type="button" class="tb mode-switch <?= $content_format === 'html' ? 'active' : '' ?>" id="switch-html" title="<?= __raw("tb_html_mode") ?>">HTML</button>
              <button type="button" class="tb mode-switch <?= $content_format === 'markdown' ? 'active' : '' ?>" id="switch-md" title="<?= __raw("tb_md_mode") ?>">MD</button>
              <!-- Font size control -->
              <div style="display:flex;align-items:center;gap:1px;border:1px solid var(--border2);border-radius:5px;overflow:hidden;margin:0 2px">
                <button type="button" class="tb" id="font-size-down" title="Reducir tamaño de fuente" style="padding:0.3rem 0.45rem;border-radius:0;border:none">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <span id="font-size-label" style="font-size:0.7rem;color:var(--muted);min-width:26px;text-align:center;user-select:none;padding:0 2px">16</span>
                <button type="button" class="tb" id="font-size-up" title="Aumentar tamaño de fuente" style="padding:0.3rem 0.45rem;border-radius:0;border:none">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
              </div>
              <div style="width:1px;height:18px;background:var(--border2);margin:0 2px;align-self:center"></div>
              <!-- Floating toolbar toggle -->
              <button type="button" class="tb" id="float-toolbar-btn" title="<?= __raw("tb_float") ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="4" rx="1"/><line x1="7" y1="10" x2="17" y2="10"/><line x1="7" y1="14" x2="14" y2="14"/></svg>
              </button>
              <!-- Article preview -->
              <button type="button" class="tb" id="preview-btn" title="<?= __raw("tb_preview_article") ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
              <!-- Focus mode -->
              <button type="button" class="tb" id="focus-btn" title="<?= __raw("tb_focus") ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
              </button>
            </div>
          </div>

          <!-- Floating selection toolbar -->
          <div id="float-toolbar" class="float-toolbar" style="display:none">
            <button type="button" class="ftb" data-cmd="bold"><b>B</b></button>
            <button type="button" class="ftb" data-cmd="italic"><i>I</i></button>
            <button type="button" class="ftb" data-cmd="underline"><u>U</u></button>
            <button type="button" class="ftb" data-cmd="strikeThrough"><s>S</s></button>
            <div class="ftb-sep"></div>
            <button type="button" class="ftb" data-cmd="formatBlock" data-val="h2">H2</button>
            <button type="button" class="ftb" data-cmd="formatBlock" data-val="h3">H3</button>
            <div class="ftb-sep"></div>
            <button type="button" class="ftb" id="ft-link">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>
          </div>

          <!-- Editor panes -->
          <div class="editor-wrap">
            <!-- WYSIWYG (HTML mode) -->
            <div id="editor" contenteditable="true" class="prose-editor"
              style="<?= $content_format === 'markdown' ? 'display:none' : '' ?>"><?= $content_format !== 'markdown' ? ($post['content'] ?? '') : '' ?></div>
            <!-- Raw HTML textarea -->
            <textarea id="html-editor" class="html-editor" style="display:none"></textarea>
            <!-- Markdown textarea -->
            <textarea id="md-editor" class="md-editor"
              style="<?= $content_format === 'markdown' ? '' : 'display:none' ?>"><?= $content_format === 'markdown' ? htmlspecialchars($post['content'] ?? '') : '' ?></textarea>
            <!-- Markdown preview -->
            <div id="md-preview" class="prose-editor md-preview-pane" style="display:none"></div>
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
.editor-layout {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 1.5rem;
  align-items: start;
}
.editor-main {
  display: flex; flex-direction: column;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius);
  height: calc(100vh - 130px);
  min-height: 500px;
  position: sticky;
  top: 57px;
}
.title-area { padding: 1.25rem 1.5rem 0; }
.title-area input {
  background: transparent; border: none; padding: 0;
  font-size: 1.6rem; font-weight: 700; color: var(--text);
  letter-spacing: -0.03em; width: 100%;
}
.title-area input::placeholder { color: var(--muted); }
.title-area input:focus { outline: none; }

/* ── Toolbar ── */
.toolbar {
  display: flex; align-items: center; gap: 2px;
  padding: 0.65rem 0.85rem;
  border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
  margin-top: 1rem; flex-wrap: wrap;
  background: var(--surface);
  flex-shrink: 0;
}
.toolbar-group { display: flex; gap: 1px; }
.toolbar-sep { width: 1px; height: 20px; background: var(--border2); margin: 0 4px; flex-shrink: 0; }
.toolbar-right { margin-left: auto; }
.tb {
  background: none; border: none; color: var(--text2);
  padding: 0.35rem 0.55rem; border-radius: 5px; cursor: pointer;
  font-size: 0.825rem; font-family: inherit; transition: all 0.1s;
  line-height: 1; white-space: nowrap;
}
.tb:hover { background: var(--surface2); color: var(--text); }
.tb.active { background: rgba(var(--accent-rgb), 0.15); color: var(--accent); }
.mode-switch {
  font-size: 0.72rem; font-weight: 600;
  border: 1px solid var(--border2) !important;
  border-radius: 4px !important; padding: 0.25rem 0.55rem !important;
}
.mode-switch.active { background: var(--accent) !important; color: #fff !important; border-color: var(--accent) !important; }

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
.editor-wrap { flex: 1; overflow-y: auto; position: relative; min-height: 0; }
.prose-editor {
  min-height: 100%;
  padding: 1.5rem; outline: none; line-height: 1.8;
  color: var(--text); font-size: 1rem;
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

.html-editor, .md-editor {
  width: 100%; min-height: 100%; padding: 1.5rem;
  background: #1a1a24; color: #a8e6cf;
  font-family: 'Fira Code', 'Cascadia Code', 'Courier New', monospace;
  font-size: 0.9rem; border: none; resize: vertical; line-height: 1.7;
  tab-size: 2;
}
.html-editor:focus, .md-editor:focus { outline: none; }
.md-preview-pane { background: var(--surface); border-top: 2px solid var(--accent); }

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
.focus-exit-hint { display: none; position: fixed; bottom: 1rem; right: 1rem; background: var(--surface2); color: var(--muted); font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 6px; z-index: 9999; }
body.focus-mode .focus-exit-hint { display: block; }

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
.editor-sidebar { display: flex; flex-direction: column; gap: 0.75rem; }
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; }
.panel-title { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 0.65rem; }
.panel-hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.4rem; }
.panel input, .panel select, .panel textarea { margin-top: 0; font-size: 0.85rem; }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .editor-layout {
    grid-template-columns: 1fr;
  }
  .editor-sidebar { order: 2; }
  /* On mobile: natural height, no sticky constraint */
  .editor-main {
    order: 1;
    height: auto !important;
    min-height: 60vh;
    position: static !important;
  }
  .editor-wrap { overflow-y: visible !important; }

  .title-area input { font-size: 1.2rem; }

  .toolbar {
    overflow-x: auto; flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    flex-shrink: 0;
  }
  .toolbar::-webkit-scrollbar { display: none; }
  .toolbar-right { flex-shrink: 0; }

  .editor-sidebar .panel { padding: 0.75rem; }
  .topbar-actions .btn span { display: none; }
}

@media (max-width: 480px) {
  .prose-editor { padding: 1rem; }
  .page-body { padding: 0.75rem !important; }
}
</style>

<script>
// ── State ──────────────────────────────────────────────────────────────────
const editor       = document.getElementById('editor');
const htmlEditor   = document.getElementById('html-editor');
const mdEditor     = document.getElementById('md-editor');
const mdPreview    = document.getElementById('md-preview');
const contentInput = document.getElementById('content-input');
const fmtInput     = document.getElementById('content-format-input');
const titleInput   = document.getElementById('post-title');
const slugInput    = document.getElementById('custom-slug');
const htmlToolbar  = document.getElementById('html-toolbar');
const mdToolbar    = document.getElementById('md-toolbar');

let mode       = <?= json_encode($content_format) ?>;  // 'html' | 'markdown'

// Inject paragraph spacing directly — guarantees it overrides any reset CSS
(function() {
  const style = document.createElement('style');
  style.textContent = '#editor p { margin-top: 0 !important; margin-bottom: 1em !important; }';
  document.head.appendChild(style);
})();

// Wrap loose text nodes in <p> so the first paragraph is always tagged
function wrapLooseNodes() {
  if (!editor) return;
  let changed = false;
  // Collect child nodes that are bare text or inline elements (not block-level)
  const blockTags = new Set(['P','DIV','H1','H2','H3','H4','H5','H6',
                              'BLOCKQUOTE','PRE','UL','OL','LI',
                              'TABLE','FIGURE','HR','SECTION','ARTICLE']);
  const nodes = Array.from(editor.childNodes);
  let group = [];

  function flushGroup() {
    if (!group.length) return;
    // Only wrap if there's actual content (not just whitespace)
    const hasContent = group.some(n =>
      n.nodeType === 3 ? n.textContent.trim() !== '' : true
    );
    if (hasContent) {
      const p = document.createElement('p');
      group[0].parentNode.insertBefore(p, group[0]);
      group.forEach(n => p.appendChild(n));
      changed = true;
    }
    group = [];
  }

  nodes.forEach(node => {
    if (node.nodeType === 3) {
      // Text node — add to group
      group.push(node);
    } else if (node.nodeType === 1) {
      const tag = node.tagName.toUpperCase();
      if (blockTags.has(tag)) {
        flushGroup(); // flush any preceding loose nodes first
      } else {
        // Inline element (span, b, i, a, img…) — add to group
        group.push(node);
      }
    }
  });
  flushGroup(); // flush any trailing loose nodes
}

// Run on every input event in the WYSIWYG editor
editor.addEventListener('input', () => {
  if (mode === 'html' && !rawHtml) wrapLooseNodes();
});

// Also run once on load in case existing content has bare text
document.addEventListener('DOMContentLoaded', () => {
  if (mode === 'html' && !rawHtml) wrapLooseNodes();
});
let rawHtml    = false;
let mdPreviewing = false;
let slugManuallySet = <?= $is_new ? 'false' : 'true' ?>;

// ── Submit sync ────────────────────────────────────────────────────────────
document.getElementById('editor-form').addEventListener('submit', () => {
  if (mode === 'markdown') {
    contentInput.value = mdEditor.value;
  } else if (rawHtml) {
    contentInput.value = htmlEditor.value;
  } else {
    contentInput.value = editor.innerHTML;
  }
  fmtInput.value = mode;
});

// ── Mode switch HTML ↔ MD ─────────────────────────────────────────────────
document.getElementById('switch-html').addEventListener('click', () => switchMode('html'));
document.getElementById('switch-md').addEventListener('click',   () => switchMode('markdown'));

function switchMode(newMode) {
  if (newMode === mode) return;

  if (newMode === 'markdown') {
    // Convert current HTML content to a notice (keep raw HTML as-is if switching)
    if (!mdEditor.value) {
      mdEditor.value = editor.innerHTML
        ? '<!-- Content converted from HTML — clean up as needed -->\n' + editor.innerHTML
        : '';
    }
    editor.style.display = 'none';
    htmlEditor.style.display = 'none';
    mdEditor.style.display = '';
    mdPreview.style.display = 'none';
    htmlToolbar.style.display = 'none';
    mdToolbar.style.display = 'flex';
    rawHtml = false;
    mdPreviewing = false;
    document.getElementById('md-preview-btn').textContent = '👁 Preview';
  } else {
    editor.style.display = '';
    htmlEditor.style.display = 'none';
    mdEditor.style.display = 'none';
    mdPreview.style.display = 'none';
    htmlToolbar.style.display = 'flex';
    mdToolbar.style.display = 'none';
    rawHtml = false;
  }

  mode = newMode;
  fmtInput.value = mode;
  document.getElementById('switch-html').classList.toggle('active', mode === 'html');
  document.getElementById('switch-md').classList.toggle('active', mode === 'markdown');
}

// ── HTML WYSIWYG toolbar ───────────────────────────────────────────────────
document.querySelectorAll('.tb[data-cmd]').forEach(btn => {
  btn.addEventListener('mousedown', e => {
    e.preventDefault();
    if (mode !== 'html' || rawHtml) return;
    document.execCommand(btn.dataset.cmd, false, btn.dataset.val || null);
    editor.focus();
    updateToolbar();
  });
});

document.getElementById('link-btn').addEventListener('mousedown', e => {
  e.preventDefault();
  const url = prompt(<?= json_encode(__raw('prompt_link_url')) ?>);
  if (url) document.execCommand('createLink', false, url);
  editor.focus();
});
// ── Media upload helper ──────────────────────────────────────────────────
function uploadMediaFile(accept, onSuccess) {
  const picker   = document.createElement('input');
  picker.type    = 'file';
  picker.accept  = accept;
  picker.onchange = async () => {
    const file = picker.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('upload', file);
    fd.append('csrf', <?= json_encode(generate_csrf()) ?>);
    try {
      const res  = await fetch(<?= json_encode(base_url() . '/admin/upload_media.php') ?>, { method:'POST', body:fd });
      const data = await res.json();
      if (data.url) onSuccess(data.url, data.type);
      else alert('Error: ' + (data.error || 'desconocido'));
    } catch(e) { alert('Error de red al subir'); }
  };
  picker.click();
}

function insertAudioHtml(url) {
  return `<figure class="media-audio"><audio controls preload="metadata"><source src="${url}">Tu navegador no soporta audio HTML5.</audio></figure>`;
}
function insertVideoHtml(url) {
  return `<figure class="media-video"><video controls preload="metadata" style="max-width:100%"><source src="${url}">Tu navegador no soporta vídeo HTML5.</video></figure>`;
}

// Image
document.getElementById('img-btn').addEventListener('mousedown', e => {
  e.preventDefault();
  const choice = confirm('¿Subir imagen desde tu ordenador?\n\nOK = subir archivo  ·  Cancelar = pegar URL');
  if (choice) {
    uploadMediaFile('image/*', (url) => {
      document.execCommand('insertHTML', false, `<img src="${url}" alt="">`);
      editor.focus();
    });
  } else {
    const url = prompt(<?= json_encode(__raw('prompt_img_url')) ?>);
    if (url) document.execCommand('insertHTML', false, `<img src="${url}" alt="">`);
    editor.focus();
  }
});

// Audio
document.getElementById('audio-btn')?.addEventListener('mousedown', e => {
  e.preventDefault();
  const choice = confirm('¿Subir audio desde tu ordenador?\nFormatos: MP3, OGG, WAV, M4A, FLAC\n\nOK = subir archivo  ·  Cancelar = pegar URL');
  if (choice) {
    uploadMediaFile('audio/*,.mp3,.ogg,.wav,.m4a,.flac', (url) => {
      document.execCommand('insertHTML', false, insertAudioHtml(url));
      editor.focus();
    });
  } else {
    const url = prompt(<?= json_encode(__raw('prompt_audio_url')) ?>);
    if (url) document.execCommand('insertHTML', false, insertAudioHtml(url));
    editor.focus();
  }
});

// Video
document.getElementById('video-btn')?.addEventListener('mousedown', e => {
  e.preventDefault();
  const choice = confirm('¿Subir vídeo desde tu ordenador?\nFormatos: MP4, WebM, OGV\n\nOK = subir archivo  ·  Cancelar = pegar URL');
  if (choice) {
    uploadMediaFile('video/*,.mp4,.webm,.ogv,.mov', (url) => {
      document.execCommand('insertHTML', false, insertVideoHtml(url));
      editor.focus();
    });
  } else {
    const url = prompt(<?= json_encode(__raw('prompt_video_url')) ?>);
    if (url) document.execCommand('insertHTML', false, insertVideoHtml(url));
    editor.focus();
  }
});

// Raw HTML toggle
document.getElementById('html-raw-toggle').addEventListener('click', () => {
  rawHtml = !rawHtml;
  if (rawHtml) {
    htmlEditor.value = editor.innerHTML;
    editor.style.display = 'none';
    htmlEditor.style.display = '';
    document.getElementById('html-raw-toggle').classList.add('active');
  } else {
    editor.innerHTML = htmlEditor.value;
    htmlEditor.style.display = 'none';
    editor.style.display = '';
    document.getElementById('html-raw-toggle').classList.remove('active');
  }
});

function updateToolbar() {
  ['bold','italic','underline','strikeThrough'].forEach(cmd => {
    const btn = document.querySelector(`.tb[data-cmd="${cmd}"]`);
    if (btn) btn.classList.toggle('active', document.queryCommandState(cmd));
  });
}
editor.addEventListener('keyup', updateToolbar);
editor.addEventListener('mouseup', updateToolbar);

editor.addEventListener('paste', e => {
  e.preventDefault();
  const html = e.clipboardData.getData('text/html');
  const txt  = e.clipboardData.getData('text/plain');
  let clean  = html || txt.replace(/\n\n+/g, '</p><p>').replace(/\n/g, '<br>');
  clean = clean
    .replace(/<script[^>]*>.*?<\/script>/gi, '')
    .replace(/\s+style="[^"]*"/gi, '')
    .replace(/\s+class="[^"]*"/gi, '')
    // Convert divs to paragraphs on paste too
    .replace(/<div><br\s*\/?><\/div>/gi, '')
    .replace(/<div>/gi, '<p>')
    .replace(/<\/div>/gi, '</p>');
  document.execCommand('insertHTML', false, clean);
});

// ── Enter = new paragraph, Shift+Enter = <br> ────────────────────────────
// Tell the browser to use <p> as the paragraph separator
document.execCommand('defaultParagraphSeparator', false, 'p');

editor.addEventListener('keydown', e => {
  if (rawHtml || mode !== 'html') return;

  if (e.key === 'Enter' && e.shiftKey) {
    // Shift+Enter → soft line break
    e.preventDefault();
    document.execCommand('insertLineBreak');
    return;
  }

  if (e.key === 'Enter' && !e.shiftKey) {
    // Inside <pre>/<code> let browser handle it natively
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const node  = sel.getRangeAt(0).startContainer;
      const block = node.nodeType === 3 ? node.parentElement : node;
      if (block && block.closest('pre, code')) return;
    }
    // Everywhere else: browser uses <p> via defaultParagraphSeparator — no intervention needed
  }
});

// Clean divs to paragraphs on save
function sanitizeDivsToParagraphs(html) {
  return html
    .replace(/<div>/gi, '<p>')
    .replace(/<\/div>/gi, '</p>')
    .replace(/<br\s*\/?>\s*<\/p>/gi, '</p>');
}

// Apply sanitization on submit
document.getElementById('editor-form').addEventListener('submit', function() {
  if (mode === 'html' && !rawHtml) {
    const cleaned = sanitizeDivsToParagraphs(editor.innerHTML);
    editor.innerHTML = cleaned;
  }
}, true); // capture phase so it runs before the existing submit handler

// ── Markdown toolbar ───────────────────────────────────────────────────────
document.querySelectorAll('.md-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const ta = mdEditor;
    const start = ta.selectionStart, end = ta.selectionEnd;
    const sel = ta.value.substring(start, end);

    if (btn.dataset.wrap) {
      const w = btn.dataset.wrap;
      const replacement = sel ? `${w}${sel}${w}` : `${w}text${w}`;
      insertAtCursor(ta, replacement, start, end);
    } else if (btn.dataset.md) {
      const prefix = btn.dataset.md;
      // Insert at start of line
      const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
      ta.focus();
      ta.setSelectionRange(lineStart, lineStart);
      insertAtCursor(ta, prefix, lineStart, lineStart);
    } else if (btn.dataset.block) {
      insertAtCursor(ta, '\n' + btn.dataset.block + '\n', start, end);
    }
    updateMdPreview();
  });
});

document.getElementById('md-link-btn').addEventListener('click', () => {
  const url   = prompt(<?= json_encode(__raw('prompt_link_url')) ?>) || 'https://';
  const label = prompt('Label:') || 'link';
  insertAtCursor(mdEditor, `[${label}](${url})`, mdEditor.selectionStart, mdEditor.selectionEnd);
});
document.getElementById('md-img-btn').addEventListener('click', () => {
  const url = prompt('Image URL:') || '';
  const alt = prompt('Alt text:') || '';
  insertAtCursor(mdEditor, `![${alt}](${url})`, mdEditor.selectionStart, mdEditor.selectionEnd);
});

// Markdown preview toggle
document.getElementById('md-preview-btn').addEventListener('click', () => {
  mdPreviewing = !mdPreviewing;
  if (mdPreviewing) {
    updateMdPreview();
    mdEditor.style.display = 'none';
    mdPreview.style.display = '';
    document.getElementById('md-preview-btn').textContent = '✏ Edit';
  } else {
    mdPreview.style.display = 'none';
    mdEditor.style.display = '';
    document.getElementById('md-preview-btn').textContent = '👁 Preview';
  }
});

mdEditor.addEventListener('input', () => { if (mdPreviewing) updateMdPreview(); });

function updateMdPreview() {
  // Send to PHP for rendering via AJAX
  fetch('<?= base_url() ?>/admin/markdown_preview.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'md=' + encodeURIComponent(mdEditor.value) + '&csrf=<?= $csrf ?>'
  }).then(r => r.text()).then(html => { mdPreview.innerHTML = html; });
}

function insertAtCursor(ta, text, start, end) {
  ta.focus();
  ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
  ta.setSelectionRange(start + text.length, start + text.length);
}

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

  // Init from existing value
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
      // Re-enable suggestion
      if (sugArea) {
        sugArea.querySelectorAll('.tag-sug').forEach(s => {
          if (s.textContent.trim() === val) s.classList.remove('used');
        });
      }
    });
    chips.appendChild(chip);

    // Mark suggestion as used
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

  // Click on wrap focuses input
  document.getElementById(chipsId.replace('-chips','-wrap'))
    ?.addEventListener('click', () => input.focus());

  // Suggestion clicks
  if (sugArea) {
    sugArea.querySelectorAll('.tag-sug').forEach(sug => {
      // Mark already-used suggestions
      if (values.includes(sug.textContent.trim())) sug.classList.add('used');
      sug.addEventListener('click', () => {
        addChip(sug.textContent.trim());
        input.focus();
      });
    });
  }
}

initTagInput('cat-input', 'cat-chips', 'cat-hidden', 'cat-suggestions');
// ── Tags: autocomplete-only (no static suggestion list) ──────────────────
(function() {
  const input      = document.getElementById('tag-input');
  const chips      = document.getElementById('tag-chips');
  const hidden     = document.getElementById('tag-hidden');
  const dropdown   = document.getElementById('tag-dropdown');
  const dropList   = document.getElementById('tag-dropdown-list');
  const dataEl     = document.getElementById('all-tags-data');
  if (!input) return;

  // Build tag list from hidden data element
  const allTags = dataEl
    ? Array.from(dataEl.querySelectorAll('span')).map(s => s.textContent)
    : [];

  let values = hidden.value.split(',').map(v => v.trim()).filter(Boolean);
  let focusedIdx = -1;

  // Init chips from existing value
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

// ── Floating toolbar ──────────────────────────────────────────────────────
const floatToolbar    = document.getElementById('float-toolbar');
const floatToolbarBtn = document.getElementById('float-toolbar-btn');
let floatEnabled = localStorage.getItem('brisa_float_toolbar') !== '0';

function updateFloatBtn() {
  if (floatToolbarBtn) {
    floatToolbarBtn.classList.toggle('active', floatEnabled);
    floatToolbarBtn.title = floatEnabled
      ? 'Toolbar flotante: ON (clic para desactivar)'
      : 'Toolbar flotante: OFF (clic para activar)';
  }
}
updateFloatBtn();

floatToolbarBtn?.addEventListener('click', () => {
  floatEnabled = !floatEnabled;
  localStorage.setItem('brisa_float_toolbar', floatEnabled ? '1' : '0');
  if (!floatEnabled) floatToolbar.style.display = 'none';
  updateFloatBtn();
});

// Show float toolbar on text selection inside WYSIWYG editor
let floatHideTimer;
document.addEventListener('mouseup', () => {
  clearTimeout(floatHideTimer);
  if (!floatEnabled || mode !== 'html' || rawHtml) {
    floatToolbar.style.display = 'none';
    return;
  }
  requestAnimationFrame(() => {
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed || sel.rangeCount === 0) {
      floatToolbar.style.display = 'none';
      return;
    }
    // Only show if selection is inside our editor
    const range = sel.getRangeAt(0);
    if (!editor.contains(range.commonAncestorContainer)) {
      floatToolbar.style.display = 'none';
      return;
    }
    // Position above the selection
    const rect = range.getBoundingClientRect();
    const tbW  = 240;
    let left   = rect.left + rect.width / 2 - tbW / 2;
    let top    = rect.top + window.scrollY - 46;
    // Keep on screen
    left = Math.max(8, Math.min(left, window.innerWidth - tbW - 8));
    if (top < window.scrollY + 8) top = rect.bottom + window.scrollY + 8;

    floatToolbar.style.display = 'flex';
    floatToolbar.style.left    = left + 'px';
    floatToolbar.style.top     = top  + 'px';
    floatToolbar.style.width   = tbW  + 'px';
  });
});

// Hide on click outside selection
document.addEventListener('mousedown', (e) => {
  if (!floatToolbar.contains(e.target)) {
    floatHideTimer = setTimeout(() => {
      const sel = window.getSelection();
      if (!sel || sel.isCollapsed) floatToolbar.style.display = 'none';
    }, 150);
  }
});

// Floating toolbar button actions
floatToolbar?.querySelectorAll('.ftb[data-cmd]').forEach(btn => {
  btn.addEventListener('mousedown', e => {
    e.preventDefault();
    document.execCommand(btn.dataset.cmd, false, btn.dataset.val || null);
    editor.focus();
  });
});

document.getElementById('ft-link')?.addEventListener('mousedown', e => {
  e.preventDefault();
  const url = prompt(<?= json_encode(__raw('prompt_link_url')) ?>);
  if (url) document.execCommand('createLink', false, url);
  editor.focus();
  floatToolbar.style.display = 'none';
});

// Hide float toolbar on scroll
document.addEventListener('scroll', () => {
  floatToolbar.style.display = 'none';
}, { passive: true });

// Also show on keyboard selection (Shift+arrows)
editor?.addEventListener('keyup', e => {
  if (e.shiftKey) {
    // Trigger the same mouseup logic
    document.dispatchEvent(new MouseEvent('mouseup'));
  } else {
    floatToolbar.style.display = 'none';
  }
});

// ── Editor font size ──────────────────────────────────────────────────────
const FONT_SIZE_KEY = 'brisa_editor_font_size';
const FONT_MIN = 12, FONT_MAX = 28, FONT_STEP = 1;
let editorFontSize = parseInt(localStorage.getItem(FONT_SIZE_KEY) || '16', 10);

function applyEditorFontSize(size) {
  editorFontSize = Math.min(FONT_MAX, Math.max(FONT_MIN, size));
  const targets = [
    document.getElementById('editor'),
    document.getElementById('html-editor'),
    document.getElementById('md-editor'),
  ];
  targets.forEach(el => { if (el) el.style.fontSize = editorFontSize + 'px'; });
  const label = document.getElementById('font-size-label');
  if (label) label.textContent = editorFontSize;
  localStorage.setItem(FONT_SIZE_KEY, editorFontSize);
}

// Apply saved size on load
applyEditorFontSize(editorFontSize);

document.getElementById('font-size-up')?.addEventListener('click', () => applyEditorFontSize(editorFontSize + FONT_STEP));
document.getElementById('font-size-down')?.addEventListener('click', () => applyEditorFontSize(editorFontSize - FONT_STEP));


// ── Article Preview ───────────────────────────────────────────────────────
const previewBtn   = document.getElementById('preview-btn');
const postSlug     = <?= json_encode($post['slug'] ?? '') ?>;
const postType     = <?= json_encode($type) ?>;
const siteBase     = <?= json_encode(base_url()) ?>;

previewBtn?.addEventListener('click', openPreview);

function openPreview() {
  const adminBase = siteBase + '/admin';

  // If article has a slug (saved at least once), use the server-side preview
  // which works for both drafts and published articles
  if (postSlug) {
    const url = adminBase + '/preview.php?type=' + encodeURIComponent(postType)
              + '&slug=' + encodeURIComponent(postSlug)
              + '&t=' + Date.now();
    window.open(url, '_blank');
    return;
  }

  // No slug yet (brand new, never saved): build a local HTML preview
  // and open it in a new tab via a blob URL
  let content = '';
  if (mode === 'markdown') {
    content = '<p style="color:#888;font-style:italic">Contenido Markdown — guarda primero para ver la vista previa completa con el tema activo.</p>'
            + '<pre style="background:#1a1a24;color:#a8e6cf;padding:1rem;border-radius:6px;font-size:0.875rem;line-height:1.6;overflow-x:auto;margin-top:1rem">'
            + (mdEditor.value || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            + '</pre>';
  } else if (rawHtml) {
    content = htmlEditor.value;
  } else {
    content = editor.innerHTML;
  }

  const title   = document.getElementById('post-title').value || '(Sin título)';
  const excerpt = document.querySelector('[name="excerpt"]')?.value || '';
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
<div class="banner">👁 VISTA PREVIA — AÚN NO GUARDADO — guarda el artículo para ver la vista previa con el tema activo del sitio</div>
<div class="wrap">
  <h1>${title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</h1>
  ${excerpt ? '<p class="excerpt">' + excerpt.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>' : ''}
  <div class="prose">${content}</div>
</div>
</body></html>`;

  const blob = new Blob([html], { type: 'text/html' });
  const url  = URL.createObjectURL(blob);
  const tab  = window.open(url, '_blank');
  // Revoke the blob URL once the new tab has loaded it
  if (tab) {
    tab.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
  } else {
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }
}


// ── Focus / distraction-free mode ────────────────────────────────────────
const focusBtn = document.getElementById('focus-btn');
focusBtn?.addEventListener('click', toggleFocus);
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.body.classList.contains('focus-mode')) toggleFocus();
  if (e.key === 'F11' && !e.altKey) { e.preventDefault(); toggleFocus(); }
});
function toggleFocus() {
  document.body.classList.toggle('focus-mode');
  const active = document.body.classList.contains('focus-mode');
  focusBtn?.setAttribute('title', active ? 'Salir del modo sin distracciones (Esc)' : 'Modo sin distracciones (F11)');
}

</script>
<!-- focus hint -->
<div class="focus-exit-hint"><?= __("focus_exit_hint") ?></div>
</body></html>
