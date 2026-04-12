<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/theme.php';
require_once __DIR__ . '/layout.php';
require_login();

$csrf = generate_csrf();
$config = cms_config();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { $error = 'Security error.'; }
    else {
        $action = $_POST['action'] ?? '';

        if ($action === 'general') {
            $config['site_title'] = trim($_POST['site_title'] ?? '');
            $config['tagline'] = trim($_POST['tagline'] ?? '');
            $config['base_url'] = rtrim(trim($_POST['base_url'] ?? ''), '/');
            $config['posts_per_page'] = max(1, (int)($_POST['posts_per_page'] ?? 8));
            $config['footer_text'] = trim($_POST['footer_text'] ?? '');
            cms_save_config($config);
            $msg = __("saved_general");
        }

        if ($action === 'appearance') {
            $config['theme'] = sanitize_filename($_POST['theme'] ?? 'default');
            $config['theme_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['theme_color'] ?? '') ? $_POST['theme_color'] : '#6366f1';
            cms_save_config($config);
            $msg = 'Appearance saved!';
        }


        if ($action === 'mastodon') {
            $config['mastodon_handle'] = trim($_POST['mastodon_handle'] ?? '');
            $config['mastodon_url']    = trim($_POST['mastodon_url'] ?? '');
            cms_save_config($config);
            $msg = 'Mastodon settings saved!';
        }

        if ($action === 'security') {
            $new_pass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $current = $_POST['current_password'] ?? '';
            
            if (!verify_password($current, $config['admin_pass'])) {
                $error = __("err_wrong_pass");
            } elseif ($new_pass !== $confirm) {
                $error = __("err_pass_mismatch");
            } elseif (strlen($new_pass) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $new_user = trim($_POST['admin_user'] ?? $config['admin_user']);
                $config['admin_user'] = $new_user;
                if ($new_pass) $config['admin_pass'] = hash_password($new_pass);
                cms_save_config($config);
                $_SESSION['username'] = $new_user;
                $msg = 'Security settings saved!';
            }
        }
        $config = cms_config();
    }
}

$themes = available_themes();
admin_header(__("settings_title"), 'settings');
?>
      <!-- no topbar actions -->
    </div>
  </div>
  <div class="page-body">
    <?php if ($msg): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

      <!-- General Settings -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= __("settings_general") ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="general">
            <div class="form-group">
              <label class="form-label">Site Title</label>
              <input type="text" name="site_title" value="<?= htmlspecialchars($config['site_title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Tagline</label>
              <input type="text" name="tagline" value="<?= htmlspecialchars($config['tagline'] ?? '') ?>" placeholder="A short description">
            </div>
            <div class="form-group">
              <label class="form-label">Base URL</label>
              <input type="url" name="base_url" value="<?= htmlspecialchars($config['base_url'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Articles Per Page</label>
              <input type="number" name="posts_per_page" value="<?= (int)($config['posts_per_page'] ?? 8) ?>" min="1" max="50">
            </div>
            <div class="form-group">
              <label class="form-label">Footer Text</label>
              <input type="text" name="footer_text" value="<?= htmlspecialchars($config['footer_text'] ?? '') ?>" placeholder="© 2025 My Blog">
            </div>
            <button type="submit" class="btn btn-primary"><?= __("btn_save_settings") ?></button>
          </form>
        </div>
      </div>

      <!-- Appearance -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= __("settings_appearance") ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="appearance">
            <div class="form-group">
              <label class="form-label">Theme</label>
              <select name="theme">
                <?php foreach ($themes as $key => $theme): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= ($config['theme'] ?? 'default') === $key ? 'selected' : '' ?>>
                  <?= htmlspecialchars($theme['label'] ?? $key) ?>
                  <?php if (!empty($theme['description'])): ?> — <?= htmlspecialchars($theme['description']) ?><?php endif; ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div style="margin-top:0.5rem;font-size:0.78rem;color:var(--muted)">
                To add themes, create a folder in <code style="background:var(--bg);padding:1px 5px;border-radius:4px">/themes/</code> with a <code style="background:var(--bg);padding:1px 5px;border-radius:4px">theme.json</code>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Accent Color</label>
              <div style="display:flex;align-items:center;gap:0.75rem">
                <input type="color" name="theme_color" id="color-pick" value="<?= htmlspecialchars($config['theme_color'] ?? '#6366f1') ?>"
                  style="width:46px;height:38px;padding:2px;border-radius:7px;cursor:pointer;border:1px solid var(--border)">
                <input type="text" id="color-text" value="<?= htmlspecialchars($config['theme_color'] ?? '#6366f1') ?>" style="flex:1"
                  pattern="^#[0-9a-fA-F]{6}$">
              </div>
              <div style="margin-top:0.75rem;display:flex;gap:0.4rem;flex-wrap:wrap" id="swatches">
                <?php foreach (['#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#eab308','#22c55e','#14b8a6','#0ea5e9','#64748b'] as $c): ?>
                <button type="button" class="swatch" data-color="<?= $c ?>"
                  style="width:26px;height:26px;border-radius:50%;background:<?= $c ?>;border:2px solid <?= ($config['theme_color'] ?? '') === $c ? '#fff' : 'transparent' ?>;cursor:pointer"></button>
                <?php endforeach; ?>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Appearance</button>
          </form>
        </div>
      </div>


      <!-- Mastodon -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= __("settings_mastodon") ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="mastodon">
            <div class="form-group">
              <label class="form-label">Tu handle de Mastodon</label>
              <input type="text" name="mastodon_handle" value="<?= htmlspecialchars($config['mastodon_handle'] ?? '') ?>" placeholder="@usuario@mastodon.social">
              <div style="font-size:0.75rem;color:var(--muted);margin-top:0.35rem">
                Se usa para la verificación de perfil. Añade <code style="background:var(--bg);padding:1px 5px;border-radius:4px">rel="me"</code> a tu perfil de Mastodon con la URL de este sitio.
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">URL de tu perfil en Mastodon</label>
              <input type="url" name="mastodon_url" value="<?= htmlspecialchars($config['mastodon_url'] ?? '') ?>" placeholder="https://mastodon.social/@usuario">
            </div>
            <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:0.85rem;font-size:0.82rem;color:var(--text2);margin-bottom:1rem">
              <strong>Verificación:</strong> Añade este enlace a cualquier página de tu sitio con el atributo <code>rel="me"</code>:<br>
              <code style="color:var(--accent)">&lt;a rel="me" href="<?= htmlspecialchars($config['mastodon_url'] ?? 'https://mastodon.social/@usuario') ?>"&gt;Mastodon&lt;/a&gt;</code>
            </div>
            <button type="submit" class="btn btn-primary"><?= __("btn_save_mastodon") ?></button>
          </form>
        </div>
      </div>

      <!-- Security -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= __("settings_security") ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="security">
            <div class="form-group">
              <label class="form-label">Admin Username</label>
              <input type="text" name="admin_user" value="<?= htmlspecialchars($config['admin_user'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
              <label class="form-label">New Password (leave blank to keep)</label>
              <input type="password" name="new_password" autocomplete="new-password">
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Update Security</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const colorPick = document.getElementById('color-pick');
const colorText = document.getElementById('color-text');
colorPick.addEventListener('input', () => { colorText.value = colorPick.value; });
colorText.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) colorPick.value = colorText.value; });
document.querySelectorAll('.swatch').forEach(s => {
  s.addEventListener('click', () => {
    colorPick.value = s.dataset.color;
    colorText.value = s.dataset.color;
    document.querySelectorAll('.swatch').forEach(x => x.style.borderColor = 'transparent');
    s.style.borderColor = '#fff';
  });
});
</script>
</body></html>
