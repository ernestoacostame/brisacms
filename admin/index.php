<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$articles = list_content('articles', false, 1, 9999);
$pages = list_content('pages', false, 1, 9999);
$published_articles = array_filter($articles['items'], fn($p) => $p['status'] === 'published');
$recent = array_slice($articles['items'], 0, 8);

admin_header(__("dash_title"), 'dashboard');
?>
    </div>
  </div>
  <div class="page-body">
    <div class="stats-grid">
      <a href="<?= base_url() ?>/admin/articles.php" class="stat-card interactive-stat">
        <div class="stat-label">Total Articles</div>
        <div class="stat-value accent"><?= $articles['total'] ?></div>
      </a>
      <a href="<?= base_url() ?>/admin/articles.php?status=published" class="stat-card interactive-stat">
        <div class="stat-label">Published</div>
        <div class="stat-value" style="color:var(--green)"><?= count($published_articles) ?></div>
      </a>
      <a href="<?= base_url() ?>/admin/articles.php?status=draft" class="stat-card interactive-stat">
        <div class="stat-label">Drafts</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $articles['total'] - count($published_articles) ?></div>
      </a>
      <a href="<?= base_url() ?>/admin/pages.php" class="stat-card interactive-stat">
        <div class="stat-label">Pages</div>
        <div class="stat-value"><?= $pages['total'] ?></div>
      </a>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Recent Articles</span>
        <a href="<?= base_url() ?>/admin/articles.php" class="btn btn-secondary btn-sm">View All</a>
      </div>
      <?php if (empty($recent)): ?>
        <div class="card-body" style="text-align:center; color:var(--muted); padding: 2.5rem;">
          <?= __("dash_no_articles") ?> <a href="<?= base_url() ?>/admin/editor.php?type=articles" style="color:var(--accent)"><?= __("dash_create_first") ?></a>
        </div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $post): ?>
          <tr>
            <td><a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>"><?= htmlspecialchars($post['title'] ?? 'Sin título') ?></a></td>
            <td>
              <?php if ($post['status'] === 'published'): ?>
                <span class="badge badge-green">Published</span>
              <?php else: ?>
                <span class="badge badge-yellow">Draft</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($post['created_at'] ?? 'now')) ?></td>
            <td>
              <a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>" class="btn btn-secondary btn-sm">Edit</a>
              <?php if ($post['status'] === 'published'): ?>
              <a href="<?= base_url() ?>/article/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">View</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>
.interactive-stat {
  display: block;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: all 0.2s;
}
.interactive-stat:hover {
  background: var(--surface2);
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>
</body></html>
