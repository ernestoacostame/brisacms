<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$themes = available_themes();
$current = active_theme();
admin_header('Themes', 'themes');
?>
    </div>
  </div>
  <div class="page-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;margin-bottom:2rem">
      <?php foreach ($themes as $key => $theme): ?>
      <div class="card" style="<?= $key === $current ? 'border-color:var(--accent)' : '' ?>">
        <div style="height:140px;background:<?= $key === 'dark' ? '#0f0f13' : '#f8f8fb' ?>;border-radius:8px 8px 0 0;overflow:hidden;display:flex;align-items:center;justify-content:center;border-bottom:1px solid var(--border)">
          <?php if ($key === 'default'): ?>
          <div style="width:85%;background:#fff;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.1);padding:12px">
            <div style="height:8px;background:#e5e7eb;border-radius:4px;width:60%;margin-bottom:8px"></div>
            <div style="height:5px;background:#e5e7eb;border-radius:4px;margin-bottom:4px"></div>
            <div style="height:5px;background:#e5e7eb;border-radius:4px;width:80%;margin-bottom:4px"></div>
            <div style="height:5px;background:#e5e7eb;border-radius:4px;width:70%"></div>
          </div>
          <?php elseif ($key === 'dark'): ?>
          <div style="width:85%;background:#17171e;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.4);padding:12px;border:1px solid #252530">
            <div style="height:8px;background:#2a2a35;border-radius:4px;width:60%;margin-bottom:8px"></div>
            <div style="height:5px;background:#2a2a35;border-radius:4px;margin-bottom:4px"></div>
            <div style="height:5px;background:#2a2a35;border-radius:4px;width:80%;margin-bottom:4px"></div>
            <div style="height:5px;background:#2a2a35;border-radius:4px;width:70%"></div>
          </div>
          <?php else: ?>
          <div style="color:var(--muted);font-size:0.85rem"><?= htmlspecialchars($theme['label'] ?? $key) ?></div>
          <?php endif; ?>
        </div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
          <div>
            <div style="font-weight:600;font-size:0.9rem"><?= htmlspecialchars($theme['label'] ?? ucfirst($key)) ?></div>
            <?php if (!empty($theme['description'])): ?>
            <div style="font-size:0.78rem;color:var(--muted);margin-top:2px"><?= htmlspecialchars($theme['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($theme['author'])): ?>
            <div style="font-size:0.75rem;color:var(--muted)">by <?= htmlspecialchars($theme['author']) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($key === $current): ?>
          <span class="badge badge-green">Active</span>
          <?php else: ?>
          <form method="POST" action="<?= base_url() ?>/admin/settings.php">
            <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
            <input type="hidden" name="action" value="appearance">
            <input type="hidden" name="theme" value="<?= htmlspecialchars($key) ?>">
            <input type="hidden" name="theme_color" value="<?= htmlspecialchars(theme_color()) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- How to create themes -->
    <div class="card">
      <div class="card-header"><span class="card-title">Creating Custom Themes</span></div>
      <div class="card-body">
        <p style="color:var(--text2);font-size:0.9rem;margin-bottom:1rem">Creating a theme is simple — just create a folder with these files:</p>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:1.25rem;font-family:monospace;font-size:0.85rem;color:var(--green);line-height:1.8">
          themes/<br>
          └── my-theme/<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── theme.json &nbsp;&nbsp;&nbsp;← Theme info<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── header.php &nbsp;&nbsp;← HTML head &amp; nav<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── footer.php &nbsp;&nbsp;← Footer HTML<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── index.php &nbsp;&nbsp;&nbsp;← Blog listing<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── single.php &nbsp;&nbsp;← Article/page view<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── 404.php &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;← 404 page<br>
          &nbsp;&nbsp;&nbsp;&nbsp;├── search.php &nbsp;&nbsp;← Search results<br>
          &nbsp;&nbsp;&nbsp;&nbsp;└── style.css &nbsp;&nbsp;&nbsp;← (optional)
        </div>
        <p style="color:var(--text2);font-size:0.85rem;margin-top:1rem">Available PHP variables in templates: <code style="background:var(--bg);padding:1px 6px;border-radius:4px">$post</code>, <code style="background:var(--bg);padding:1px 6px;border-radius:4px">$posts</code>, <code style="background:var(--bg);padding:1px 6px;border-radius:4px">$site_title</code>, <code style="background:var(--bg);padding:1px 6px;border-radius:4px">$theme_color</code>, <code style="background:var(--bg);padding:1px 6px;border-radius:4px">$base</code></p>
      </div>
    </div>
  </div>
</div>
</body></html>
