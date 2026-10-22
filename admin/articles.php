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
    $published_only = false;
}
$all_items = list_content('articles', false, 1, 9999);
$filtered_items = $all_items['items'];
if ($status_filter === 'published') {
    $filtered_items = array_filter($filtered_items, fn($p) => $p['status'] === 'published');
} elseif ($status_filter === 'draft') {
    $filtered_items = array_filter($filtered_items, fn($p) => $p['status'] === 'draft');
}
$per_page = 10;
$total = count($filtered_items);
$pages = (int)ceil($total / $per_page);
$offset = ($page - 1) * $per_page;
$items = array_slice($filtered_items, $offset, $per_page);
$result = ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];

admin_header(__("nav_articles"), 'articles');
?>
      <a href="<?= base_url() ?>/admin/editor.php?type=articles" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= __('list_new_article') ?>
      </a>
    </div>
  </div>
  <div class="page-body">
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">✓ <?= __("editor_saved") ?></div><?php endif; ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">
            <?php if ($status_filter === 'published'): ?>
                <?= $result['total'] ?> <?= __("dash_published", "Publicados") ?>
            <?php elseif ($status_filter === 'draft'): ?>
                <?= $result['total'] ?> <?= __("dash_drafts", "Borradores") ?>
            <?php else: ?>
                <?= $result['total'] ?> <?= __("nav_articles") ?>
            <?php endif; ?>
        </span>
        <div style="display:flex;gap:0.4rem;align-items:center">
          <a href="articles.php" class="btn btn-sm <?= !$status_filter ? 'btn-primary' : 'btn-secondary' ?>"><?= __("all", "Todos") ?></a>
          <a href="articles.php?status=published" class="btn btn-sm <?= $status_filter === 'published' ? 'btn-primary' : 'btn-secondary' ?>"><?= __("dash_published", "Publicados") ?></a>
          <a href="articles.php?status=draft" class="btn btn-sm <?= $status_filter === 'draft' ? 'btn-primary' : 'btn-secondary' ?>"><?= __("dash_drafts", "Borradores") ?></a>
        </div>
      </div>
      <?php if (empty($result['items'])): ?>
        <div class="card-body" style="text-align:center;color:var(--muted);padding:3.5rem 1.5rem">
          <div style="font-size:2rem;margin-bottom:0.75rem;opacity:0.6">📝</div>
          <p style="font-size:1rem;margin-bottom:0.75rem"><?= __("list_no_articles") ?></p>
          <a href="<?= base_url() ?>/admin/editor.php?type=articles" class="btn btn-primary btn-sm"><?= __("dash_create_first") ?></a>
        </div>
      <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th><?= __("list_title_col", "Título") ?></th>
            <th><?= __("list_status_col", "Estado") ?></th>
            <th><?= __("list_categories_col", "Categorías") ?></th>
            <th><?= __("list_date_col", "Fecha") ?></th>
            <th style="text-align:right"><?= __("list_actions_col", "Acciones") ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['items'] as $post): ?>
          <tr>
            <td>
              <a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>" style="font-weight:600">
                <?= htmlspecialchars($post['title'] ?? 'Sin título') ?>
              </a>
            </td>
            <td>
              <?php if ($post['status'] === 'published'): ?>
                <span class="badge badge-green">✓ <?= __('badge_published', 'Publicado') ?></span>
              <?php else: ?>
                <span class="badge badge-yellow">● <?= __('badge_draft', 'Borrador') ?></span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text2);font-size:0.88rem"><?= htmlspecialchars(implode(', ', $post['categories'] ?? [])) ?: '<span style="color:var(--muted)">—</span>' ?></td>
            <td style="color:var(--muted);font-size:0.88rem"><?= date('M j, Y', strtotime($post['created_at'] ?? 'now')) ?></td>
            <td style="text-align:right">
              <a href="<?= base_url() ?>/admin/editor.php?type=articles&slug=<?= htmlspecialchars($post['slug']) ?>" class="btn btn-secondary btn-sm"><?= __("edit", "Editar") ?></a>
              <?php if ($post['status'] === 'published'): ?>
              <a href="<?= base_url() ?>/article/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm"><?= __("view", "Ver") ?> ↗</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($result['pages'] > 1): ?>
      <div style="padding:1.25rem 1.6rem;display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;border-top:1px solid var(--border)">
          <?php if ($page > 1): ?>
          <a href="?page=1<?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">«</a>
          <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn">‹</a>
          <?php endif; ?>
          
          <?php
          $start_page = max(1, min($page - 2, $result['pages'] - 4));
          $end_page = min($result['pages'], $start_page + 4);
          
          if ($start_page > 1) {
              echo '<span style="color:var(--muted);padding:0 0.4rem">…</span>';
          }
          
          for ($i = $start_page; $i <= $end_page; $i++): ?>
          <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . htmlspecialchars($status_filter) : '' ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          
          <?php if ($end_page < $result['pages']): ?>
          <span style="color:var(--muted);padding:0 0.4rem">…</span>
          <?php endif; ?>
          
          <?php if ($page < $result['pages']): ?>
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
