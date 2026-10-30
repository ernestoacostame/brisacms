<?php
/**
 * KutPod Plugin - Public Multi-Podcast Directory / Archive (/podcasts)
 */

if (!defined('ROOT_PATH')) exit;

$cfg = kutpod_config();
$allArticles = list_content('articles', true, 1, 9999)['items'] ?? [];

// Filtrar artículos que sean episodios de podcast
$podcastEpisodes = array_values(array_filter($allArticles, function($p) {
    return !empty($p['podcast_meta']['is_podcast']) 
        || in_array('podcast', array_map('strtolower', $p['categories'] ?? []))
        || in_array('podcast', array_map('strtolower', $p['tags'] ?? []))
        || !empty($p['podcast_meta']['audio_url']);
}));

// Extraer podcasts y temporadas únicos
$shows = [];
$seasons = [];
foreach ($podcastEpisodes as $ep) {
    $meta = $ep['podcast_meta'] ?? [];
    $pSlug = $meta['podcast_slug'] ?? 'default';
    $pTitle = $meta['podcast_title'] ?: ucfirst($pSlug);
    if (!empty($pSlug)) {
        $shows[$pSlug] = $pTitle;
    }
    $s = (int)($meta['season'] ?? 1);
    if ($s > 0) $seasons[$s] = true;
}
ksort($shows);
ksort($seasons);

$base = base_url();
$siteTitle = site_title();
$instanceUrl = rtrim($cfg['instance_url'] ?: 'http://localhost:8000', '/');

?>
<div class="kutpod-directory-container" style="max-width: 1100px; margin: 0 auto; padding: 2rem 1rem;">
  
  <header class="kutpod-directory-header">
    <div style="display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:20px; background:rgba(255,95,126,0.12); color:#ff3b61; font-weight:700; font-size:0.85rem; margin-bottom:1rem; text-transform:uppercase; letter-spacing:0.05em;">
      🎙️ Archivo de Podcasts
    </div>
    <h1 class="kutpod-directory-title"><?= htmlspecialchars($cfg['directory_title'] ?: 'Episodios y Podcasts') ?></h1>
    <p class="kutpod-directory-desc">
      <?= htmlspecialchars($cfg['directory_desc'] ?: 'Explora todos los episodios de nuestros podcasts, escucha directamente en el navegador y accede a notas y transcripciones completas.') ?>
    </p>

    <!-- Filtros por Show / Podcast (si hay más de 1 podcast) -->
    <?php if (count($shows) > 1): ?>
    <div class="kutpod-directory-filters" id="kp-show-filters" style="margin-bottom: 0.75rem;">
      <button type="button" class="kutpod-filter-btn active" data-show-filter="all">Todos los Podcasts (<?= count($podcastEpisodes) ?>)</button>
      <?php foreach ($shows as $sSlug => $sTitle): ?>
        <button type="button" class="kutpod-filter-btn" data-show-filter="<?= htmlspecialchars($sSlug) ?>">🎙️ <?= htmlspecialchars($sTitle) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filtros por Temporada -->
    <?php if (count($seasons) > 1): ?>
    <div class="kutpod-directory-filters" id="kp-season-filters">
      <button type="button" class="kutpod-filter-btn active" data-season-filter="all">Todas las temporadas</button>
      <?php foreach (array_keys($seasons) as $s): ?>
        <button type="button" class="kutpod-filter-btn" data-season-filter="season-<?= $s ?>">Temporada <?= $s ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

  <?php if (empty($podcastEpisodes)): ?>
    <div style="text-align: center; padding: 4rem 1rem; color: var(--muted, #64748b);">
      <div style="font-size: 3rem; margin-bottom: 1rem;">🎙️</div>
      <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;">No hay episodios sincronizados todavía</h3>
      <p>Configura y sincroniza tus episodios desde el panel de administración de KutBlog.</p>
    </div>
  <?php else: ?>
    <div class="kutpod-grid" id="kp-episode-grid">
      <?php foreach ($podcastEpisodes as $ep): 
        $meta = $ep['podcast_meta'] ?? [];
        $pSlug = $meta['podcast_slug'] ?? 'default';
        $pTitle = $meta['podcast_title'] ?? '';
        $epSlug = $meta['episode_slug'] ?? $ep['slug'];
        $season = (int)($meta['season'] ?? 1);
        $number = (int)($meta['number'] ?? 0);
        $durFmt = $meta['duration_fmt'] ?: ($meta['duration_secs'] ? gmdate('H:i:s', $meta['duration_secs']) : '');
        $cover = $ep['featured_image'] ?: ($base . '/plugins/kutpod/assets/cover-placeholder.png');
        $embedSrc = $instanceUrl . "/embed/@" . rawurlencode($pSlug) . "/" . rawurlencode($epSlug) . "?theme=auto";
        $downloads = (int)($meta['downloads'] ?? 0);
      ?>
      <article class="kutpod-grid-card" data-show="<?= htmlspecialchars($pSlug) ?>" data-season="season-<?= $season ?>">
        <div style="display: flex; gap: 14px; align-items: flex-start;">
          <?php if ($ep['featured_image']): ?>
            <img src="<?= htmlspecialchars($ep['featured_image']) ?>" alt="<?= htmlspecialchars($ep['title']) ?>" style="width: 72px; height: 72px; border-radius: 10px; object-fit: cover; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
          <?php else: ?>
            <div style="width: 72px; height: 72px; border-radius: 10px; background: rgba(255,95,126,0.15); display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0;">🎙️</div>
          <?php endif; ?>
          
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: #ff5f7e; margin-bottom: 2px;">
              <?php if ($pTitle): ?><strong><?= htmlspecialchars($pTitle) ?></strong> · <?php endif; ?>
              <?php if ($season > 0): ?>T<?= $season ?><?php endif; ?>
              <?php if ($number > 0): ?> · Ep <?= $number ?><?php endif; ?>
              <?php if ($durFmt): ?> · <?= htmlspecialchars($durFmt) ?><?php endif; ?>
            </div>
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 4px 0; line-height: 1.3;">
              <a href="<?= $base ?>/article/<?= htmlspecialchars($ep['slug']) ?>" style="color: inherit; text-decoration: none;">
                <?= htmlspecialchars($ep['title']) ?>
              </a>
            </h2>
            <div style="font-size: 0.8rem; color: var(--muted, #64748b);">
              <?= date('d M, Y', strtotime($ep['created_at'] ?? 'now')) ?>
              <?php if ($downloads > 0): ?> · 🎧 <?= number_format($downloads) ?> escuchas<?php endif; ?>
            </div>
          </div>
        </div>

        <?php if (!empty($ep['excerpt'])): ?>
          <p style="font-size: 0.88rem; color: var(--muted, #64748b); line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
            <?= htmlspecialchars($ep['excerpt']) ?>
          </p>
        <?php endif; ?>

        <!-- Reproductor incrustado directo -->
        <div style="margin-top: auto; border-radius: 12px; overflow: hidden; border: 1px solid var(--kp-border, rgba(0,0,0,0.08));">
          <iframe src="<?= htmlspecialchars($embedSrc) ?>" class="kutpod-player-frame" style="height: 175px; min-height: 175px; width: 100%; border: none;" allow="autoplay" loading="lazy"></iframe>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 6px;">
          <a href="<?= $base ?>/article/<?= htmlspecialchars($ep['slug']) ?>" style="font-size: 0.85rem; font-weight: 600; color: #ff5f7e; text-decoration: none;">
            Ver notas y transcripción →
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function() {
  let activeShow = 'all';
  let activeSeason = 'all';

  function applyFilters() {
    document.querySelectorAll('#kp-episode-grid .kutpod-grid-card').forEach(card => {
      const matchShow = (activeShow === 'all' || card.dataset.show === activeShow);
      const matchSeason = (activeSeason === 'all' || card.dataset.season === activeSeason);
      if (matchShow && matchSeason) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  document.querySelectorAll('#kp-show-filters .kutpod-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#kp-show-filters .kutpod-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeShow = btn.dataset.showFilter;
      applyFilters();
    });
  });

  document.querySelectorAll('#kp-season-filters .kutpod-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#kp-season-filters .kutpod-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeSeason = btn.dataset.seasonFilter;
      applyFilters();
    });
  });
})();
</script>
