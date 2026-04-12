<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/content.php';

if (!is_installed()) { http_response_code(404); exit; }

$base   = rtrim(base_url(), '/');
$result = list_content('articles', true, 1, 9999);
$pages  = list_content('pages', true, 1, 999);

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($base) ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <?php foreach ($pages['items'] as $page): ?>
  <url>
    <loc><?= htmlspecialchars($base) ?>/page/<?= rawurlencode($page['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($page['updated_at'] ?? $page['created_at'] ?? 'now')) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <?php endforeach; ?>
  <?php foreach ($result['items'] as $post): ?>
  <url>
    <loc><?= htmlspecialchars($base) ?>/article/<?= rawurlencode($post['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($post['updated_at'] ?? $post['created_at'] ?? 'now')) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>
</urlset>
