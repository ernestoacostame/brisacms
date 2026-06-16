</main>
<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-text">
      <?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . '. Powered by BrisaCMS.')) ?>
    </span>
    <div class="footer-links">
      <a href="<?= $base ?>/">Home</a>
      <?php
      $nav_pages = list_content('pages', true, 1, 20);
      foreach ($nav_pages['items'] as $np):
      ?>
      <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= $base ?>/feed.xml" class="rss-link" title="RSS Feed" aria-label="RSS Feed">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="rss-icon" style="width: 14px; height: 14px; vertical-align: middle; display: inline-block; margin-top: -2px;"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
      </a>
    </div>
  </div>
</footer>
</body>
</html>
