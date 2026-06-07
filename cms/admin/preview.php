<?php
// BrisaCMS - Article/Page Preview for admin
// Serves any article (including drafts) to logged-in admins
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once dirname(__DIR__) . '/core/markdown.php';

require_login();

$type = $_GET['type'] ?? 'articles';
$slug = $_GET['slug'] ?? '';

if (!$slug || !in_array($type, ['articles', 'pages'])) {
    http_response_code(400);
    die('Missing type or slug.');
}

$post = get_content($type, $slug);
if (!$post) {
    http_response_code(404);
    die('Article not found.');
}

// Render markdown if needed
if (($post['content_format'] ?? 'html') === 'markdown' && function_exists('flux_markdown')) {
    $post['content'] = flux_markdown($post['content'] ?? '');
}

// Auto-embed YouTube URLs
$post['content'] = preg_replace_callback(
    '#(?:<p>)?\s*(https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([\w\-]{11})[^\s<]*)\s*(?:</p>)?#i',
    function ($m) {
        return '<div class="yt-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . $m[2]
             . '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>';
    },
    $post['content'] ?? ''
);

// Add a draft banner if the article is not published
$is_draft = ($post['status'] ?? 'draft') !== 'published';

// Render using the active theme — same as the public site
// The theme will see $post, $type, $base, $site_title, $config, etc.
// We pass an extra var to show the preview banner
$_GET['preview'] = '1';

ob_start();
render_theme('single', [
    'post'       => $post,
    'type'       => $type,
    'is_preview' => true,
    'is_draft'   => $is_draft,
]);
$html = ob_get_clean();

// ── Inject preview banner + CSS override ─────────────────────────────────
$banner_h      = 36; // px height of the banner
$status_label  = $is_draft ? 'BORRADOR' : 'PUBLICADO';
$status_color  = $is_draft ? '#f59e0b' : '#34d399';
$status_text   = $is_draft ? '#000' : '#fff';
$editor_url    = htmlspecialchars(base_url() . '/admin/editor.php?type=' . $type . '&slug=' . $slug);
$post_title    = htmlspecialchars($post['title'] ?? '');

// CSS injected into <head>: pushes any sticky/fixed headers down by banner height
$head_css = <<<CSS
<style id="brisa-preview-css">
/* Preview banner height compensation */
.site-header,
header.site-header,
[class*="site-header"],
[id*="site-header"] {
  top: {$banner_h}px !important;
  margin-top: 0 !important;
}
/* Make sure body doesn't hide under the fixed banner */
body { padding-top: {$banner_h}px !important; }
/* Nav dropdowns that are fixed need to move too */
.nav-dropdown,
[id="nav-dropdown"] {
  top: calc({$banner_h}px + 56px) !important;
}
</style>
CSS;

// Banner HTML fixed at top
$banner_html = <<<HTML
<div id="brisa-preview-bar" style="
  position:fixed;top:0;left:0;right:0;z-index:999999;height:{$banner_h}px;
  background:{$status_color};color:{$status_text};
  font-family:system-ui,sans-serif;font-size:0.78rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;gap:1rem;
  padding:0 1rem;letter-spacing:0.03em;box-shadow:0 2px 6px rgba(0,0,0,0.2);">
  <span>👁 VISTA PREVIA &nbsp;·&nbsp; {$status_label} &nbsp;—&nbsp; {$post_title}</span>
  <a href="{$editor_url}" style="
    background:rgba(0,0,0,0.18);color:inherit;text-decoration:none;
    padding:0.2rem 0.65rem;border-radius:4px;font-size:0.73rem;white-space:nowrap;">
    ← Volver al editor
  </a>
</div>
HTML;

// Inject CSS into <head> and banner right after <body>
$html = preg_replace('/(<\/head>)/i',  $head_css . '$1', $html, 1);
$html = preg_replace('/(<body[^>]*>)/i', '$1' . $banner_html, $html, 1);

echo $html;
