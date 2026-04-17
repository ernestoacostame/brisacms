<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/content.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = $_GET['status'] ?? null;
$published_only = false;
if ($status_filter === 'published') {
    $published_only = true;
} elseif ($status_filter === 'draft') {
    $published_only = false; // pero necesitamos filtrar solo drafts
}
// Obtener todos los artículos sin filtrar por página para contar
$all_items = list_content('articles', false, 1, 9999);
$filtered_items = $all_items['items'];
if ($status_filter === 'published') {
    $filtered_items = array_filter($filtered_items, fn($p) => $p['status'] === 'published');
} elseif ($status_filter === 'draft') {
    $filtered_items = array_filter($filtered_items, fn($p) => $p['status'] === 'draft');
}
// Paginación manual
$per_page = 5;
$total = count($filtered_items);
$pages = (int)ceil($total / $per_page);
$offset = ($page - 1) * $per_page;
$items = array_slice($filtered_items, $offset, $per_page);
$result = ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];

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
        <span class="card-title">
            <?php if ($status_filter === 'published'): ?>
                <?= $result['total'] ?> Published Articles
            <?php elseif ($status_filter === 'draft'): ?>
                <?= $result['total'] ?> Draft Articles
            <?php else: ?>
                <?= $result['total'] ?> <?= __("nav_articles") ?>
            <?php endif; ?>
        </span>
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
          // Show First button if not on first page
          if ($page > 1): ?>
          <a href="?page=1<?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">«</a>
          <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">‹</a>
          <?php endif; ?>
          
          <?php
          // Show only 5 page numbers around current page
          $start_page = max(1, min($page - 2, $result['pages'] - 4));
          $end_page = min($result['pages'], $start_page + 4);
          
          if ($start_page > 1) {
              echo '<span style="color:var(--muted);padding:0 0.25rem">…</span>';
          }
          
          for ($i = $start_page; $i <= $end_page; $i++): ?>
          <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          
          <?php if ($end_page < $result['pages']): ?>
          <span style="color:var(--muted);padding:0 0.25rem">…</span>
          <?php endif; ?>
          
          <?php
          // Show Last and Next buttons if not on last page
          if ($page < $result['pages']): ?>
          <a href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">›</a>
          <a href="?page=<?= $result['pages'] ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">»</a>
          <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body></html>
