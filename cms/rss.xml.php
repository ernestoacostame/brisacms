<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/content.php';

if (!is_installed()) { http_response_code(404); exit; }

$config   = cms_config();
$base     = base_url();
$title    = site_title();
$tagline  = $config['tagline'] ?? '';
$per_page = (int)($config['posts_per_page'] ?? 20);

$result = list_content('articles', true, 1, $per_page);

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
  xmlns:atom="http://www.w3.org/2005/Atom"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title><?= htmlspecialchars($title) ?></title>
    <link><?= htmlspecialchars($base) ?>/</link>
    <description><?= htmlspecialchars($tagline) ?></description>
    <language>es</language>
    <lastBuildDate><?= date('r') ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($base) ?>/rss.xml" rel="self" type="application/rss+xml"/>
    <generator>BrisaCMS <?= CMS_VERSION ?></generator>

    <?php foreach ($result['items'] as $post):
      $url     = $base . '/article/' . rawurlencode($post['slug']);
      $pubdate = date('r', strtotime($post['created_at'] ?? 'now'));
      $excerpt = strip_tags($post['excerpt'] ?? substr(strip_tags($post['content'] ?? ''), 0, 300));
      $cats    = $post['categories'] ?? [];
    ?>
    <item>
      <title><?= htmlspecialchars($post['title'] ?? 'Sin título') ?></title>
      <link><?= htmlspecialchars($url) ?></link>
      <guid isPermaLink="true"><?= htmlspecialchars($url) ?></guid>
      <pubDate><?= $pubdate ?></pubDate>
      <description><?= htmlspecialchars($excerpt) ?></description>
      <content:encoded><![CDATA[<?= $post['content'] ?? '' ?>]]></content:encoded>
      <?php foreach ($cats as $cat): ?>
      <category><?= htmlspecialchars($cat) ?></category>
      <?php endforeach; ?>
      <?php if (!empty($post['featured_image'])): ?>
      <enclosure url="<?= htmlspecialchars($post['featured_image']) ?>" type="image/jpeg" length="0"/>
      <?php endif; ?>
    </item>
    <?php endforeach; ?>
  </channel>
</rss>
