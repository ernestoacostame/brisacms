<?php
// Opciones del Plugin de Fediverso para BrisaCMS

if (!defined('CMS_NAME')) {
    exit;
}

require_once __DIR__ . '/activitypub.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Security error.';
    } else {
        $action = $_POST['_form'] ?? '';

        if ($action === 'fediverse_settings') {
            $config = cms_config();
            $handle = trim($_POST['admin_mastodon_handle'] ?? '');
            $url = trim($_POST['admin_mastodon_url'] ?? '');
            $fed_username = sanitize_filename(trim($_POST['fediverse_username'] ?? 'blog'));

            // Clean rel="me" html if pasted directly
            if (preg_match('/href=["\']([^"\']+)["\']/', $url, $m)) {
                $url = $m[1];
            }

            $config['mastodon_url'] = $url;
            $config['fediverse_username'] = $fed_username;

            if ($handle) {
                $actor = ap_resolve_webfinger($handle);
                if ($actor && !empty($actor['inbox'])) {
                    $config['admin_mastodon_handle'] = $handle;
                    $config['admin_mastodon_actor']  = $actor['id'];
                    $config['admin_mastodon_inbox']  = $actor['inbox'];
                    $msg = 'Cuenta Mastodon vinculada correctamente.';
                } else {
                    $error = 'Error: No se pudo resolver la cuenta de Mastodon en el Fediverso.';
                }
            } else {
                $config['admin_mastodon_handle'] = '';
                $config['admin_mastodon_actor']  = '';
                $config['admin_mastodon_inbox']  = '';
                $msg = $url ? 'Ajustes de verificación actualizados.' : 'Cuenta Mastodon desvinculada.';
            }

            cms_save_config($config);
        }

        if ($action === 'fediverse_block') {
            $domain = strtolower(trim($_POST['block_domain'] ?? ''));
            if ($domain) {
                ap_exec("INSERT OR IGNORE INTO ap_blocks (domain) VALUES (?)", [$domain]);
                $msg = "Dominio '$domain' bloqueado.";
            }
        }

        if ($action === 'fediverse_unblock') {
            $domain = $_POST['unblock_domain'] ?? '';
            if ($domain) {
                ap_exec("DELETE FROM ap_blocks WHERE domain = ?", [$domain]);
                $msg = "Dominio '$domain' desbloqueado.";
            }
        }

        if ($action === 'fediverse_reset') {
            $pdo = ap_db();
            $pdo->exec("DELETE FROM ap_followers");
            $pdo->exec("DELETE FROM ap_outbox");
            $pdo->exec("DELETE FROM ap_delivery");
            $pdo->exec("DELETE FROM ap_keys");
            $pdo->exec("DELETE FROM ap_comments");
            $pdo->exec("DELETE FROM ap_interactions");
            $msg = 'Fediverso reiniciado correctamente (seguidores, claves y outbox purgados).';
        }
    }
}

$config = cms_config();
$fed_username = $config['fediverse_username'] ?? 'blog';
$admin_mastodon_handle = $config['admin_mastodon_handle'] ?? '';
$admin_mastodon_url = $config['mastodon_url'] ?? '';

$followers = ap_q("SELECT * FROM ap_followers ORDER BY followed_at DESC");
$blocks = ap_q("SELECT * FROM ap_blocks ORDER BY created_at DESC");
$actor_url = ap_base_url() . "/users/" . $fed_username;
$handle_domain = ap_handle_domain();
$full_handle = "@" . $fed_username . "@" . $handle_domain;
?>

<?php if (!empty($msg)): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="settings-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; align-items:start">

  <!-- Column 1: Info & Settings -->
  <div style="display:flex; flex-direction:column; gap:1.5rem">
    
    <!-- Local Actor Profile Card -->
    <div class="card">
      <div class="card-header"><span class="card-title">Perfil del Blog en el Fediverso</span></div>
      <div class="card-body">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem">
          <div style="width:48px; height:48px; border-radius:50%; background:var(--accent); color:#fff; display:grid; place-items:center; font-weight:700; font-size:1.25rem">
            <?= strtoupper(substr($fed_username, 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:600; font-size:1.05rem; color:var(--text)"><?= htmlspecialchars($config['site_title'] ?? 'BrisaCMS') ?></div>
            <div style="font-size:0.85rem; color:var(--accent); font-family:monospace; user-select:all"><?= htmlspecialchars($full_handle) ?></div>
          </div>
        </div>
        <p style="font-size:0.85rem; color:var(--text2); line-height:1.4; margin-bottom:1rem">
          Cualquier usuario del Fediverso (ej. Mastodon) puede buscar y seguir esta dirección para recibir tus nuevos artículos directamente en su timeline y comentarlos.
        </p>
        <div style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:0.75rem; font-size:0.8rem; color:var(--text2)">
          <strong>Endpoint del Actor:</strong><br>
          <a href="<?= htmlspecialchars($actor_url) ?>" target="_blank" style="color:var(--accent); font-family:monospace; word-break:break-all"><?= htmlspecialchars($actor_url) ?></a>
        </div>
      </div>
    </div>

    <!-- Configuration Form Card -->
    <div class="card">
      <div class="card-header"><span class="card-title">Ajustes de la Federación</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
          <input type="hidden" name="_form" value="fediverse_settings">

          <div class="form-group">
            <label class="form-label">Usuario Federado (Slug)</label>
            <input type="text" name="fediverse_username" value="<?= htmlspecialchars($fed_username) ?>" required pattern="[a-zA-Z0-9_-]+" placeholder="blog">
            <div class="panel-hint" style="margin-top:0.35rem">El slug para la dirección federada (ej. `blog` en `@blog@tudominio.com`).</div>
          </div>

          <div class="form-group">
            <label class="form-label">Tu cuenta Mastodon personal (Handle)</label>
            <input type="text" name="admin_mastodon_handle" value="<?= htmlspecialchars($admin_mastodon_handle) ?>" placeholder="@usuario@mastodon.social">
            <div class="panel-hint" style="margin-top:0.35rem">
              Se utilizará para enviarte Mensajes Directos (DMs) cada vez que alguien comente en tus artículos federados.
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">URL de tu Perfil Mastodon personal</label>
            <input type="url" name="admin_mastodon_url" value="<?= htmlspecialchars($admin_mastodon_url) ?>" placeholder="https://mastodon.social/@usuario">
            <div class="panel-hint" style="margin-top:0.35rem">
              Para agregar la etiqueta invisible <code>rel="me"</code> en las cabeceras públicas y verificar tu web.
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:0.5rem; margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Guardar y Vincular</button>
            <?php if ($admin_mastodon_handle): ?>
              <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementsByName('admin_mastodon_handle')[0].value=''; this.form.submit();">
                Desvincular
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Blocklist Card -->
    <div class="card">
      <div class="card-header"><span class="card-title">Bloquear Dominios del Fediverso</span></div>
      <div class="card-body">
        <form method="POST" style="display:flex; gap:0.5rem; margin-bottom:1rem">
          <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
          <input type="hidden" name="_form" value="fediverse_block">
          <input type="text" name="block_domain" placeholder="ej. spammer.social" required style="flex:1">
          <button type="submit" class="btn btn-primary">Bloquear</button>
        </form>

        <?php if (empty($blocks)): ?>
          <p style="font-size:0.82rem; color:var(--muted); text-align:center; padding:0.5rem 0">No hay dominios bloqueados actualmente.</p>
        <?php else: ?>
          <div style="max-height:150px; overflow-y:auto; border:1px solid var(--border); border-radius:6px">
            <?php foreach ($blocks as $b): ?>
              <div style="display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0.75rem; border-bottom:1px solid var(--border2)">
                <span style="font-size:0.85rem; color:var(--text)"><?= htmlspecialchars($b['domain']) ?></span>
                <form method="POST" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
                  <input type="hidden" name="_form" value="fediverse_unblock">
                  <input type="hidden" name="unblock_domain" value="<?= htmlspecialchars($b['domain']) ?>">
                  <button type="submit" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem; font-size:0.7rem">Desbloquear</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Column 2: Followers & Danger Zone -->
  <div style="display:flex; flex-direction:column; gap:1.5rem">
    
    <!-- Followers Card -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Seguidores en el Fediverso (<?= count($followers) ?>)</span>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($followers)): ?>
          <div style="text-align:center; padding:3rem; color:var(--muted)">
            <p style="font-size:0.875rem">Aún no tienes seguidores. Cuando alguien te siga desde Mastodon, aparecerá en esta lista.</p>
          </div>
        <?php else: ?>
          <div style="max-height:380px; overflow-y:auto; display:flex; flex-direction:column">
            <?php foreach ($followers as $f): ?>
              <div style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-bottom:1px solid var(--border)">
                <img src="<?= htmlspecialchars($f['avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 40 40%22><rect width=%2240%22 height=%2240%22 fill=%22%23555%22/><text x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22>' . substr($f['name'] ?: '?', 0, 1) . '</text></svg>') ?>"
                     style="width:34px; height:34px; border-radius:50%; object-fit:cover; background:var(--surface2)">
                <div style="flex:1; min-width:0">
                  <div style="font-weight:600; font-size:0.85rem; color:var(--text); text-overflow:ellipsis; overflow:hidden; white-space:nowrap">
                    <?= htmlspecialchars($f['name'] ?: $f['handle']) ?>
                  </div>
                  <div style="font-size:0.75rem; color:var(--muted); font-family:monospace; text-overflow:ellipsis; overflow:hidden; white-space:nowrap">
                    <a href="<?= htmlspecialchars($f['actor_url']) ?>" target="_blank" style="color:inherit"><?= htmlspecialchars($f['handle']) ?></a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Danger Zone Card -->
    <div class="card" style="border-color:rgba(248,113,113,0.3)">
      <div class="card-header" style="border-bottom-color:rgba(248,113,113,0.3)"><span class="card-title" style="color:var(--red)">Zona de Peligro: Reiniciar Federación</span></div>
      <div class="card-body">
        <p style="font-size:0.85rem; color:var(--text2); line-height:1.4; margin-bottom:1rem">
          Al reiniciar la federación:
          <br>• Se eliminarán todos tus seguidores actuales en el Fediverso.
          <br>• Se borrará el historial de publicaciones del outbox y cola de envíos.
          <br>• Se regenerarán tus claves de firma criptográfica RSA para el actor.
        </p>
        <form method="POST" onsubmit="return confirm('¿PELIGRO! ¿Seguro que deseas reiniciar la federación de este blog? Se eliminarán permanentemente todos los seguidores y claves.');">
          <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
          <input type="hidden" name="_form" value="fediverse_reset">
          <button type="submit" class="btn btn-danger" style="width:100%; justify-content:center">Resetear Federación Completa</button>
        </form>
      </div>
    </div>
  </div>

</div>
