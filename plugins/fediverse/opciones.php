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
            $fed_name = trim($_POST['fediverse_name'] ?? '');
            $fed_bio = trim($_POST['fediverse_bio'] ?? '');
            $fed_avatar = trim($_POST['fediverse_avatar'] ?? '');
            $fed_cover = trim($_POST['fediverse_cover'] ?? '');

            // Clean rel="me" html if pasted directly
            if (preg_match('/href=["\']([^"\']+)["\']/', $url, $m)) {
                $url = $m[1];
            }

            $profile_changed = (
                ($config['fediverse_name'] ?? '') !== $fed_name ||
                ($config['fediverse_bio'] ?? '') !== $fed_bio ||
                ($config['fediverse_avatar'] ?? '') !== $fed_avatar ||
                ($config['fediverse_cover'] ?? '') !== $fed_cover ||
                ($config['fediverse_username'] ?? 'blog') !== $fed_username
            );

            $config['mastodon_url'] = $url;
            $config['fediverse_username'] = $fed_username;
            $config['fediverse_name'] = $fed_name;
            $config['fediverse_bio'] = $fed_bio;
            $config['fediverse_avatar'] = $fed_avatar;
            $config['fediverse_cover'] = $fed_cover;

            if ($handle) {
                $actor = ap_resolve_webfinger($handle);
                if ($actor && !empty($actor['inbox'])) {
                    $config['admin_mastodon_handle'] = $handle;
                    $config['admin_mastodon_actor']  = $actor['id'];
                    $config['admin_mastodon_inbox']  = $actor['inbox'];
                    $msg = 'Ajustes guardados. Cuenta Mastodon vinculada y perfil actualizado.';
                } else {
                    $error = 'Error: No se pudo resolver la cuenta de Mastodon en el Fediverso. Perfil local actualizado.';
                }
            } else {
                $config['admin_mastodon_handle'] = '';
                $config['admin_mastodon_actor']  = '';
                $config['admin_mastodon_inbox']  = '';
                $msg = 'Ajustes y perfil del Fediverso actualizados.';
            }

            cms_save_config($config);

            if ($profile_changed) {
                ap_update_actor();
            }
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
$avatar_url = $config['fediverse_avatar'] ?? '';
$cover_url = $config['fediverse_cover'] ?? '';
$disp_name = !empty($config['fediverse_name']) ? $config['fediverse_name'] : ($config['site_title'] ?? 'BrisaCMS');
$bio = !empty($config['fediverse_bio']) ? $config['fediverse_bio'] : ($config['tagline'] ?? '');

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
    
    <!-- Local Actor Profile Card (Premium Layout) -->
    <div class="card" style="overflow:hidden; border-radius:12px; border:1px solid var(--border); box-shadow:0 4px 12px rgba(0,0,0,0.15)">
      <div id="profile-cover-bg" style="height:140px; background:<?= $cover_url ? 'url('.htmlspecialchars($cover_url).')' : 'linear-gradient(135deg, var(--accent), var(--border))' ?>; background-size:cover; background-position:center; transition:background 0.3s ease">
      </div>
      <div style="padding:1rem 1.25rem 1.25rem; position:relative">
        <div style="display:flex; align-items:flex-end; gap:1rem; margin-top:-55px; margin-bottom:1rem">
          <img id="profile-avatar-img" src="<?= $avatar_url ? htmlspecialchars($avatar_url) : 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 80%22><rect width=%2280%22 height=%2280%22 fill=%22%23555%22/><text x=%2240%22 y=%2250%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2232%22 font-weight=%22bold%22>' . strtoupper(substr($fed_username, 0, 1)) . '</text></svg>' ?>" 
               style="width:80px; height:80px; border-radius:50%; border:3px solid var(--surface); object-fit:cover; background:var(--surface2); box-shadow:0 4px 8px rgba(0,0,0,0.2)">
          <div style="padding-bottom:5px">
            <div id="profile-name-text" style="font-weight:700; font-size:1.15rem; color:var(--text); line-height:1.2"><?= htmlspecialchars($disp_name) ?></div>
            <div id="profile-handle-text" style="font-size:0.85rem; color:var(--accent); font-family:monospace; user-select:all"><?= htmlspecialchars($full_handle) ?></div>
          </div>
        </div>
        
        <p id="profile-bio-text" style="font-size:0.88rem; color:var(--text); line-height:1.4; margin-bottom:1rem; background:var(--surface2); padding:0.6rem 0.8rem; border-radius:6px; border-left:3px solid var(--accent); white-space:pre-wrap"><?= htmlspecialchars($bio) ?></p>
        
        <p style="font-size:0.82rem; color:var(--text2); line-height:1.4; margin-bottom:1rem">
          Cualquier usuario del Fediverso (ej. Mastodon) puede buscar y seguir esta dirección para recibir tus nuevos artículos directamente en su timeline y comentarlos de forma nativa.
        </p>
        <div style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:0.75rem; font-size:0.8rem; color:var(--text2)">
          <strong>Endpoint del Actor:</strong><br>
          <a href="<?= htmlspecialchars($actor_url) ?>" target="_blank" style="color:var(--accent); font-family:monospace; word-break:break-all"><?= htmlspecialchars($actor_url) ?></a>
        </div>
      </div>
    </div>

    <!-- Configuration Form Card -->
    <div class="card">
      <div class="card-header"><span class="card-title">Ajustes del Perfil Federado</span></div>
      <div class="card-body">
        <form method="POST" id="fediverse-settings-form">
          <input type="hidden" name="csrf" value="<?= generate_csrf() ?>">
          <input type="hidden" name="_form" value="fediverse_settings">

          <div class="form-group">
            <label class="form-label">Usuario Federado (Slug)</label>
            <input type="text" name="fediverse_username" id="fediverse-username-input" value="<?= htmlspecialchars($fed_username) ?>" required pattern="[a-zA-Z0-9_-]+" placeholder="blog" oninput="updateProfileHandle(this.value)">
            <div class="panel-hint" style="margin-top:0.35rem">El slug para la dirección federada (ej. `blog` en `@blog@tudominio.com`).</div>
          </div>

          <div class="form-group">
            <label class="form-label">Nombre a mostrar (Display Name)</label>
            <input type="text" name="fediverse_name" value="<?= htmlspecialchars($config['fediverse_name'] ?? '') ?>" placeholder="<?= htmlspecialchars($config['site_title'] ?? 'BrisaCMS') ?>" oninput="document.getElementById('profile-name-text').innerText = this.value.trim() || <?= json_encode($config['site_title'] ?? 'BrisaCMS') ?>;">
            <div class="panel-hint" style="margin-top:0.35rem">El nombre público de tu perfil. Por defecto usa el título del sitio.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Biografía (Bio)</label>
            <textarea name="fediverse_bio" rows="3" placeholder="<?= htmlspecialchars($config['tagline'] ?? '') ?>" style="width:100%; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); padding:0.5rem; font-family:inherit" oninput="document.getElementById('profile-bio-text').innerText = this.value.trim() || <?= json_encode($config['tagline'] ?? '') ?>;"><?= htmlspecialchars($config['fediverse_bio'] ?? '') ?></textarea>
            <div class="panel-hint" style="margin-top:0.35rem">Descripción de tu perfil. Por defecto usa la descripción corta del blog.</div>
          </div>

          <!-- Avatar Upload -->
          <div class="form-group" style="margin-top:1.25rem">
            <label class="form-label">Avatar (Imagen de Perfil)</label>
            <div style="display:flex; gap:1rem; align-items:center">
              <div style="position:relative; width:64px; height:64px; border-radius:50%; border:1px solid var(--border); overflow:hidden; background:var(--surface2)">
                <img id="avatar-preview-img" src="<?= $avatar_url ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect width=%2264%22 height=%2264%22 fill=%22%23555%22/><text x=%2232%22 y=%2240%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22>?</text></svg>' ?>" style="width:100%; height:100%; object-fit:cover">
              </div>
              <div style="flex:1">
                <input type="text" name="fediverse_avatar" id="fediverse-avatar-input" value="<?= htmlspecialchars($avatar_url) ?>" placeholder="https://..." style="margin-bottom:0.4rem; font-family:monospace; font-size:0.85rem" oninput="document.getElementById('avatar-preview-img').src = this.value; document.getElementById('profile-avatar-img').src = this.value;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="triggerFileUpload('avatar')">Subir Imagen</button>
                <input type="file" id="avatar-file-input" accept="image/*" style="display:none" onchange="handleFileChange(this, 'avatar')">
              </div>
            </div>
          </div>

          <!-- Cover Upload -->
          <div class="form-group" style="margin-top:1.25rem">
            <label class="form-label">Portada (Banner de Perfil)</label>
            <div style="display:flex; flex-direction:column; gap:0.5rem">
              <div style="position:relative; width:100%; height:80px; border-radius:6px; border:1px solid var(--border); overflow:hidden; background:var(--surface2)">
                <img id="cover-preview-img" src="<?= $cover_url ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 300 80%22><rect width=%22300%22 height=%2280%22 fill=%22%23444%22/><text x=%22150%22 y=%2245%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22>Sin Portada</text></svg>' ?>" style="width:100%; height:100%; object-fit:cover">
              </div>
              <div style="display:flex; gap:0.5rem; align-items:center">
                <input type="text" name="fediverse_cover" id="fediverse-cover-input" value="<?= htmlspecialchars($cover_url) ?>" placeholder="https://..." style="flex:1; font-family:monospace; font-size:0.85rem" oninput="document.getElementById('cover-preview-img').src = this.value; document.getElementById('profile-cover-bg').style.background = 'url(' + this.value + ')';">
                <button type="button" class="btn btn-secondary btn-sm" onclick="triggerFileUpload('cover')">Subir Imagen</button>
                <input type="file" id="cover-file-input" accept="image/*" style="display:none" onchange="handleFileChange(this, 'cover')">
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-top:1.5rem">
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
            <button type="submit" class="btn btn-primary">Guardar y Actualizar Perfil</button>
            <?php if ($admin_mastodon_handle): ?>
              <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementsByName('admin_mastodon_handle')[0].value=''; this.form.submit();">
                Desvincular DMs
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

<script>
const DOMAIN = <?= json_encode($handle_domain) ?>;

function updateProfileHandle(username) {
    const clean = username.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
    document.getElementById('profile-handle-text').innerText = '@' + (clean || 'blog') + '@' + DOMAIN;
}

function triggerFileUpload(type) {
    document.getElementById(type + '-file-input').click();
}

async function handleFileChange(input, type) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const csrf = document.querySelector('input[name="csrf"]').value;
    
    const formData = new FormData();
    formData.append('csrf', csrf);
    formData.append('upload', file);
    
    const previewImg = document.getElementById(type + '-preview-img');
    const profileImg = type === 'avatar' ? document.getElementById('profile-avatar-img') : null;
    const profileBg = type === 'cover' ? document.getElementById('profile-cover-bg') : null;
    
    const originalOpacity = previewImg.style.opacity;
    previewImg.style.opacity = '0.4';
    
    try {
        const response = await fetch('upload_media.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.error) {
            alert('Error al subir: ' + result.error);
            previewImg.style.opacity = originalOpacity || '1';
        } else if (result.url) {
            document.getElementById('fediverse-' + type + '-input').value = result.url;
            previewImg.src = result.url;
            previewImg.style.opacity = '1';
            
            if (type === 'avatar' && profileImg) {
                profileImg.src = result.url;
            }
            if (type === 'cover' && profileBg) {
                profileBg.style.backgroundImage = 'url(' + result.url + ')';
            }
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Error de red al subir la imagen.');
        previewImg.style.opacity = originalOpacity || '1';
    }
}
</script>
