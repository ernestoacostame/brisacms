  </main><!-- /.site-main -->

  <!-- SIDEBAR -->
  <aside class="site-sidebar">

    <!-- Categories -->
    <?php
    $all_arts_sidebar = list_content('articles', true, 1, 9999);
    $all_cats = [];
    foreach ($all_arts_sidebar['items'] as $a) {
      foreach ($a['categories'] ?? [] as $c) {
        $all_cats[$c] = ($all_cats[$c] ?? 0) + 1;
      }
    }
    arsort($all_cats);
    if (!empty($all_cats)):
    ?>
    <div class="sidebar-widget">
      <div class="widget-title">Categorías</div>
      <div class="widget-body">
        <ul class="cat-list">
          <?php foreach ($all_cats as $cat => $count): ?>
          <li>
            <a href="<?= $base ?>/category/<?= urlencode($cat) ?>">
              <?= htmlspecialchars($cat) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

  </aside><!-- /.site-sidebar -->
</div><!-- /.site-wrap -->

<footer class="site-footer">
  <div class="footer-inner">
    <nav class="footer-nav">
      <a href="<?= $base ?>/">Home</a>
      <?php foreach ($nav_pages['items'] as $np): ?>
      <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= $base ?>/feed.xml" class="rss-link" title="RSS Feed" aria-label="RSS Feed">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="rss-icon" style="width: 12px; height: 12px; vertical-align: middle; display: inline-block; margin-right: 4px; margin-top: -2px;"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>RSS
      </a>
    </nav>
    <div class="footer-text">
      <?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . ' | Powered by BrisaCMS')) ?>
    </div>
  </div>
</footer>

<script>
const btn = document.getElementById('menu-btn');
const nav = document.getElementById('nav-dropdown');
btn.addEventListener('click', (e) => {
  e.stopPropagation();
  const open = nav.classList.toggle('open');
  btn.classList.toggle('open', open);
  btn.setAttribute('aria-expanded', open);
  nav.setAttribute('aria-hidden', !open);
});
document.addEventListener('click', e => {
  if (!btn.contains(e.target) && !nav.contains(e.target)) {
    nav.classList.remove('open');
    btn.classList.remove('open');
    btn.setAttribute('aria-expanded', false);
  }
});
</script>
</body>
</html>
