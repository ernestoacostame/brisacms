<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$page = max(1, (int)($_GET['page'] ?? 1));
$result = list_content('articles', false, $page, 15);

admin_header(__("nav_articles"), 'articles');
?>
      <a href="<?= base_url() ?>/admin/editor.php?type=articles" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= __('list_new_article') ?>
      </a>
    </div>
  </div>
  <div class="page-body">
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success"><?= __("editor_saved") ?></div><?php endif; ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $result['total'] ?> <?= __("nav_articles") ?></span>
      </div>
      <?php if (empty($result['items'])): ?>
        <div class="card-body" style="text-align:center;color:var(--muted);padding:3rem">
          <?= __("list_no_articles") ?> <a href="<?= base_url() ?>/admin/editor.php?type=articles" style="color:var(--accent)"><?= __("dash_create_first") ?></a>
        </div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Title</th><th>Status</th><th>Categories</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($result['items'] as $post): ?>
          <tr>
            <td>
              <a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>">
                <?= htmlspecialchars($post['title'] ?? 'Untitled') ?>
              </a>
            </td>
            <td>
              <?php if ($post['status'] === 'published'): ?>
                <span class="badge badge-green"><?= __('badge_published') ?></span>
              <?php else: ?>
                <span class="badge badge-yellow"><?= __('badge_draft') ?></span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:0.8rem"><?= htmlspecialchars(implode(', ', $post['categories'] ?? [])) ?></td>
            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($post['created_at'] ?? 'now')) ?></td>
            <td>
              <a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>" class="btn btn-secondary btn-sm"><?= __("edit") ?></a>
              <?php if ($post['status'] === 'published'): ?>
              <a href="<?= base_url() ?>/article/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm"><?= __("view") ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($result['pages'] > 1): ?>
      <div style="padding:1rem;display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;border-top:1px solid var(--border)">
        <?php
        $show = min(10, $result['pages']);
        for ($i = 1; $i <= $show; $i++):
        ?>
        <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($result['pages'] > 10 && $page < $result['pages']): ?>
        <a href="?page=<?= $page + 1 ?>" class="page-btn">›</a>
        <a href="?page=<?= $result['pages'] ?>" class="page-btn">»</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body></html>
