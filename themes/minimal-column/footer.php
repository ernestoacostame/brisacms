  </div><!-- /.site-column -->
</main><!-- /.site-main -->

<footer class="site-footer">
  <div class="site-column footer-inner">
    <div class="footer-meta">
      <nav class="footer-links" aria-label="Navegación del pie">
        <a href="<?= $base ?>/">Inicio</a>
        <?php if (!empty($nav_pages['items'])): ?>
          <?php foreach ($nav_pages['items'] as $np): ?>
          <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
        <a href="<?= $base ?>/rss.xml" class="footer-rss" title="Canal RSS" aria-label="Canal RSS">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 11a9 9 0 0 1 9 9"></path>
            <path d="M4 4a16 16 0 0 1 16 16"></path>
            <circle cx="5" cy="19" r="1"></circle>
          </svg>
          <span>RSS</span>
        </a>
      </nav>
      <div class="footer-copy">
        <?= htmlspecialchars($config['footer_text'] ?? ('© ' . date('Y') . ' ' . $site_title . ' · Impulsado por KutBlog')) ?>
      </div>
    </div>
  </div>
</footer>

<script>
(function() {
  // ── 1. Modo Claro / Oscuro ────────────────────────────────────────────────
  const toggleBtn = document.getElementById('theme-toggle-btn');
  const iconSun   = document.getElementById('icon-sun');
  const iconMoon  = document.getElementById('icon-moon');

  function updateIcons(theme) {
    if (theme === 'dark') {
      iconSun.style.display = 'block';
      iconMoon.style.display = 'none';
    } else {
      iconSun.style.display = 'none';
      iconMoon.style.display = 'block';
    }
  }

  function getTheme() {
    return document.documentElement.getAttribute('data-theme') ||
      (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }

  updateIcons(getTheme());

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const next = getTheme() === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('kut_theme', next);
      updateIcons(next);
    });
  }

  // ── 2. Buscador Desplegable ──────────────────────────────────────────────
  const searchBtn    = document.getElementById('search-toggle-btn');
  const searchDrawer = document.getElementById('search-drawer');
  const searchInput  = document.getElementById('search-input');
  const searchClose  = document.getElementById('search-close-btn');

  function closeSearch() {
    if (searchDrawer) {
      searchDrawer.classList.remove('is-open');
      searchDrawer.setAttribute('aria-hidden', 'true');
      searchBtn.setAttribute('aria-expanded', 'false');
    }
  }

  if (searchBtn && searchDrawer) {
    searchBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = searchDrawer.classList.toggle('is-open');
      searchDrawer.setAttribute('aria-hidden', !isOpen);
      searchBtn.setAttribute('aria-expanded', isOpen);
      if (isOpen && searchInput) {
        setTimeout(() => searchInput.focus(), 60);
      }
    });

    if (searchClose) {
      searchClose.addEventListener('click', closeSearch);
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeSearch();
    });
  }

  // ── 3. Menú Móvil ────────────────────────────────────────────────────────
  const menuBtn     = document.getElementById('mobile-menu-btn');
  const mobileDrawer = document.getElementById('mobile-drawer');

  if (menuBtn && mobileDrawer) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = mobileDrawer.classList.toggle('is-open');
      mobileDrawer.setAttribute('aria-hidden', !isOpen);
      menuBtn.setAttribute('aria-expanded', isOpen);
      closeSearch();
    });

    document.addEventListener('click', (e) => {
      if (!mobileDrawer.contains(e.target) && !menuBtn.contains(e.target)) {
        mobileDrawer.classList.remove('is-open');
        mobileDrawer.setAttribute('aria-hidden', 'true');
        menuBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
</script>
</body>
</html>

