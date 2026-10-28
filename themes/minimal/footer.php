  </div><!-- /.site-container -->
</main><!-- /.site-main -->

<footer class="site-footer">
  <div class="site-container footer-inner">
    <nav class="footer-nav" aria-label="Navegación del pie">
      <a href="<?= $base ?>/">Inicio</a>
      <?php if (!empty($nav_pages['items'])): ?>
        <?php foreach ($nav_pages['items'] as $np): ?>
        <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
      <a href="<?= $base ?>/rss.xml" class="rss-link" title="RSS Feed" aria-label="RSS Feed">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>RSS
      </a>
    </nav>
    <div class="footer-text">
      <?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . ' · Powered by KutBlog')) ?>
    </div>
  </div>
</footer>

<script>
(function() {
  // ── 1. Theme Toggle (Light / Dark) ──────────────────────────────────────────
  const toggleBtn = document.getElementById('theme-toggle-btn');
  const iconSun   = document.getElementById('icon-sun');
  const iconMoon  = document.getElementById('icon-moon');

  function updateThemeIcons(theme) {
    if (theme === 'dark') {
      iconSun.style.display = 'block';
      iconMoon.style.display = 'none';
    } else {
      iconSun.style.display = 'none';
      iconMoon.style.display = 'block';
    }
  }

  function getActiveTheme() {
    return document.documentElement.getAttribute('data-theme') || 
           (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }

  // Set initial icon state
  updateThemeIcons(getActiveTheme());

  toggleBtn.addEventListener('click', () => {
    const current = getActiveTheme();
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('kut_theme', next);
    updateThemeIcons(next);
  });

  // ── 2. Search Dropdown ───────────────────────────────────────────────────────
  const searchBtn   = document.getElementById('search-toggle-btn');
  const searchModal = document.getElementById('search-modal');
  const searchInput = document.getElementById('search-input');

  if (searchBtn && searchModal) {
    searchBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = searchModal.classList.toggle('open');
      searchBtn.setAttribute('aria-expanded', isOpen);
      searchModal.setAttribute('aria-hidden', !isOpen);
      if (isOpen && searchInput) {
        setTimeout(() => searchInput.focus(), 50);
      }
    });

    document.addEventListener('click', (e) => {
      if (!searchModal.contains(e.target) && !searchBtn.contains(e.target)) {
        searchModal.classList.remove('open');
        searchBtn.setAttribute('aria-expanded', 'false');
        searchModal.setAttribute('aria-hidden', 'true');
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        searchModal.classList.remove('open');
        searchBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // ── 3. Mobile Navigation Drawer ─────────────────────────────────────────────
  const menuBtn = document.getElementById('mobile-menu-btn');
  const drawer  = document.getElementById('mobile-drawer');

  if (menuBtn && drawer) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = drawer.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', isOpen);
      drawer.setAttribute('aria-hidden', !isOpen);
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    drawer.addEventListener('click', (e) => {
      if (e.target === drawer) {
        drawer.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      }
    });
  }
})();
</script>

</body>
</html>
