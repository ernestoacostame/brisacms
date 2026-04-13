<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$result = list_content('pages', false, 1, 100);
admin_header(__("nav_pages"), 'pages');
?>
      <a href="<?= base_url() ?>/admin/editor.php?type=pages" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= __('list_new_page') ?>
      </a>
    </div>
  </div>
  <div class="page-body">
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Page deleted.</div><?php endif; ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><?= $result['total'] ?> <?= __("nav_pages") ?></span></div>
      <?php if (empty($result['items'])): ?>
        <div class="card-body" style="text-align:center;color:var(--muted);padding:3rem">
          <?= __("list_no_pages") ?> <a href="<?= base_url() ?>/admin/editor.php?type=pages" style="color:var(--accent)">Create your first page →</a>
        </div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($result['items'] as $post): ?>
          <tr>
            <td><a href="<?= base_url() ?>/admin/editor.php?type=pages&slug=<?= htmlspecialchars($post['slug']) ?>"><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></a></td>
            <td style="color:var(--muted);font-size:0.8rem">/<?= htmlspecialchars($post['slug']) ?></td>
            <td>
              <?php if ($post['status'] === 'published'): ?>
                <span class="badge badge-green"><?= __('badge_published') ?></span>
              <?php else: ?>
                <span class="badge badge-yellow"><?= __('badge_draft') ?></span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($post['updated_at'] ?? $post['created_at'] ?? 'now')) ?></td>
            <td>
              <a href="<?= base_url() ?>/admin/editor.php?type=pages&slug=<?= htmlspecialchars($post['slug']) ?>" class="btn btn-secondary btn-sm"><?= __("edit") ?></a>
              <?php if ($post['status'] === 'published'): ?>
              <a href="<?= base_url() ?>/page/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm"><?= __("view") ?></a>
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
</body></html>
