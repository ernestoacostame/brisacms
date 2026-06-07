</main>
<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-text">
      <?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . '. Powered by FluxCMS.')) ?>
    </span>
    <div class="footer-links">
      <a href="<?= $base ?>/">Home</a>
      <?php
      $nav_pages = list_content('pages', true, 1, 20);
      foreach ($nav_pages['items'] as $np):
      ?>
      <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</footer>
</body>
</html>
