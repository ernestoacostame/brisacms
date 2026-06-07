  </main>
  <aside class="site-sidebar">
    <?php
    $all_arts = list_content('articles', true, 1, 9999);
    $all_cats = [];
    foreach ($all_arts['items'] as $a) {
      foreach ($a['categories'] ?? [] as $c) { $all_cats[$c] = ($all_cats[$c] ?? 0) + 1; }
    }
    arsort($all_cats);
    if (!empty($all_cats)):
    ?>
    <div class="sidebar-widget">
      <div class="widget-title">Categorías</div>
      <div class="widget-body">
        <ul class="cat-list">
          <?php foreach ($all_cats as $cat => $count): ?>
          <li><a href="<?= $base ?>/category/<?= urlencode($cat) ?>"><?= htmlspecialchars($cat) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </aside>
</div>
<footer class="site-footer">
  <div class="footer-inner">
    <nav class="footer-nav">
      <a href="<?= $base ?>/">Home</a>
      <?php foreach ($nav_pages['items'] as $np): ?>
      <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
      <?php endforeach; ?>
      <a href="<?= $base ?>/rss.xml">RSS</a>
    </nav>
    <div class="footer-text"><?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . ' | Powered by BrisaCMS')) ?></div>
  </div>
</footer>
<script>
const btn = document.getElementById('menu-btn');
const nav = document.getElementById('nav-dropdown');
btn.addEventListener('click', e => { e.stopPropagation(); const o = nav.classList.toggle('open'); btn.classList.toggle('open', o); });
document.addEventListener('click', e => { if (!btn.contains(e.target) && !nav.contains(e.target)) { nav.classList.remove('open'); btn.classList.remove('open'); } });
</script>
</body></html>
