<?php
// ============================================================================
// admin/update.php — Sistema de actualización de BrisaCMS
// ============================================================================
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/updater.php';
require_once __DIR__ . '/layout.php';

require_login();

$hasToken = !empty(cms_setting('update_github_token', ''));
$rollbackVersion = cms_setting('update_rollback_version', '');
$rollbackPath = cms_setting('update_rollback_path', '');
$hasRollback = $rollbackVersion && $rollbackPath && file_exists($rollbackPath);

$rollbacks = cms_update_get_available_rollbacks();
$hasAnyRollback = !empty($rollbacks);

$history = cms_setting('update_history', []);
// Reverse history to show newest first
$history = array_reverse($history);

admin_header(__('update_title', 'Actualizaciones'), 'update');
?>
    </div>
  </div>
  <div class="page-body" style="max-width:960px; margin: 0 auto;">
    
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.8rem; font-weight: 700; letter-spacing: -0.03em; margin-bottom: 0.25rem;">
        <?= __('update_title', 'Actualizaciones') ?>
      </h1>
      <p style="color: var(--text2); font-size: 0.9rem;">
        <?= __('update_sub', 'Mantén BrisaCMS al día · actualización con un click · rollback instantáneo') ?>
      </p>
    </div>

    <!-- Estado actual -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
      <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
          <div style="flex: 1; min-width: 0;">
            <div class="stat-label"><?= __('update_installed_version', 'Versión instalada') ?></div>
            <div class="stat-value accent" style="font-size: 1.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">v<?= CMS_VERSION ?></div>
          </div>
          <div style="background: rgba(var(--accent-rgb), 0.1); color: var(--accent); padding: 0.5rem; border-radius: 8px; display: flex; flex-shrink: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
          </div>
        </div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:0.5rem;"><?= __('update_current_compilation', 'Compilación actual') ?></div>
      </div>

      <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
          <div style="flex: 1; min-width: 0;">
            <div class="stat-label"><?= __('update_github_token', 'Token GitHub') ?></div>
            <div class="stat-value" style="color: <?= $hasToken ? 'var(--green)' : 'var(--red)' ?>; font-size: 1.3rem; line-height: 1.25; margin-top: 0.4rem; font-weight: 700; word-wrap: break-word;">
              <?= $hasToken ? __('update_token_configured', 'Configurado') : __('update_token_not_configured', 'No configurado') ?>
            </div>
          </div>
          <div style="background: <?= $hasToken ? 'rgba(52, 211, 153, 0.1)' : 'rgba(248, 113, 113, 0.1)' ?>; color: <?= $hasToken ? 'var(--green)' : 'var(--red)' ?>; padding: 0.5rem; border-radius: 8px; display: flex; flex-shrink: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          </div>
        </div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:0.5rem;">
          <?= $hasToken ? __('update_token_ready', 'Listo para buscar') : __('update_token_configure_below', 'Configúralo abajo') ?>
        </div>
      </div>

      <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
          <div style="flex: 1; min-width: 0;">
            <div class="stat-label"><?= __('update_rollback', 'Rollback') ?></div>
            <div class="stat-value" style="color: <?= $hasRollback ? 'var(--yellow)' : 'var(--muted)' ?>; font-size: 1.3rem; line-height: 1.25; margin-top: 0.4rem; font-weight: 700; word-wrap: break-word;">
              <?= $hasRollback ? 'v' . $rollbackVersion : __('update_rollback_not_available', 'No disponible') ?>
            </div>
          </div>
          <div style="background: rgba(251, 191, 36, 0.1); color: var(--yellow); padding: 0.5rem; border-radius: 8px; display: flex; flex-shrink: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
          </div>
        </div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:0.5rem;">
          <?= $hasRollback ? __('update_rollback_version_saved', 'Versión anterior guardada') : __('update_rollback_created_before', 'Se crea antes de cada update') ?>
        </div>
      </div>

      <div class="stat-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
          <div style="flex: 1; min-width: 0;">
            <div class="stat-label"><?= __('update_applied_total_title', 'Actualizaciones') ?></div>
            <div class="stat-value" style="font-size: 1.8rem;"><?= count($history) ?></div>
          </div>
          <div style="background: rgba(var(--accent-rgb), 0.1); color: var(--accent); padding: 0.5rem; border-radius: 8px; display: flex; flex-shrink: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </div>
        </div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:0.5rem;"><?= __('update_applied_total', 'aplicadas en total') ?></div>
      </div>
    </div>

    <!-- Buscar actualización -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="card-title" style="font-size: 1rem;"><?= __('update_check_title', 'Buscar actualización') ?></span>
          <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
            <?= __('update_check_desc', 'Consulta el repositorio de GitHub para verificar si hay una nueva versión disponible.') ?>
          </div>
        </div>
        <button class="btn btn-primary" id="btn-check" type="button" onclick="cmsUpdateCheck()" <?= $hasToken ? '' : 'disabled title="Configura el token primero"' ?>>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <?= __('update_check_btn', 'Buscar actualizaciones') ?>
        </button>
      </div>
      
      <div class="card-body" style="padding: 0;">
        <!-- Estado del chequeo / Errores -->
        <div id="update-status" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border); font-size: 13.5px;"></div>

        <!-- Panel de actualización disponible -->
        <div id="update-available" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border);">
          <div style="background: var(--surface2); border: 1px solid var(--border2); border-radius: 8px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
              <div>
                <div style="font-size: 1.25rem; font-weight: 600; letter-spacing: -0.02em;" id="update-version-label"></div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;" id="update-date-label"></div>
              </div>
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a id="update-gh-link" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 12px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 3px; vertical-align: middle;"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
                  <?= __('update_btn_view_gh', 'Ver en GitHub') ?>
                </a>
                <button class="btn btn-primary btn-sm" id="btn-apply" onclick="cmsUpdateApply()">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 3px; vertical-align: middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                  <?= __('update_btn_apply', 'Actualizar ahora') ?>
                </button>
              </div>
            </div>

            <!-- Changelog -->
            <div id="update-changelog" style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border);">
              <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 0.5rem;">
                <?= __('update_changelog_title', 'Notas de la versión') ?>
              </div>
              <div id="update-changelog-body" style="font-size: 13.5px; line-height: 1.6; color: var(--text2); white-space: pre-wrap; max-height: 250px; overflow-y: auto; background: var(--bg); padding: 0.85rem; border-radius: 6px; border: 1px solid var(--border2);"></div>
            </div>
          </div>
        </div>

        <!-- Barra de progreso -->
        <div id="update-progress" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border);">
          <div style="background: var(--surface2); border: 1px solid var(--border2); border-radius: 8px; padding: 1.25rem;">
            <div style="font-size: 14px; font-weight: 500; margin-bottom: 0.75rem;" id="progress-label">Preparando...</div>
            <div style="background: var(--border); border-radius: 4px; height: 8px; overflow: hidden;">
              <div id="progress-bar" style="background: var(--accent); height: 100%; border-radius: 4px; width: 0%; transition: width 0.4s ease;"></div>
            </div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 0.5rem;" id="progress-sub"></div>
          </div>
        </div>

        <!-- Resultado de la actualización -->
        <div id="update-result" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border);">
          <div style="border-radius: 8px; padding: 1.25rem;" id="update-result-card">
            <div style="font-size: 16px; font-weight: 600;" id="result-title"></div>
            <div style="font-size: 13.5px; margin-top: 0.5rem;" id="result-body"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Configuración del Token -->
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header"><span class="card-title"><?= __('update_token_settings_title', 'Token de Acceso de GitHub') ?></span></div>
      <div class="card-body">
        <form id="form-token" onsubmit="cmsSaveToken(event)">
          <div class="form-group">
            <label class="form-label" for="update_token_input"><?= __('update_token_field', 'Token Personal de GitHub (PAT)') ?></label>
            <div style="display: flex; gap: 0.5rem;">
              <input type="password" id="update_token_input" value="<?= htmlspecialchars(cms_setting('update_github_token', '')) ?>" placeholder="ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" style="flex: 1;">
              <button type="submit" class="btn btn-primary" id="btn-save-token"><?= __('save', 'Guardar') ?></button>
            </div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 0.5rem;">
              <?= __('update_token_hint', 'Se requiere un Token de Acceso Personal para poder consultar los releases del repositorio privado en GitHub.') ?>
              <a href="https://github.com/settings/tokens" target="_blank" rel="noopener" style="color: var(--accent); text-decoration: none;"><?= __('update_token_create_link', 'Crear token en GitHub ↗') ?></a>
            </div>
          </div>
        </form>
        <div id="token-status" style="display: none; font-size: 13px; margin-top: 0.5rem;"></div>
      </div>
    </div>

    <!-- Rollback -->
    <?php if ($hasAnyRollback): ?>
    <div class="card" style="margin-bottom: 2rem;">
      <div class="card-header"><span class="card-title"><?= __('update_revert_title', 'Revertir actualización') ?></span></div>
      <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
          <div style="flex: 1; min-width: 250px;">
            <div style="font-size: 12.5px; color: var(--text2); line-height: 1.5; margin-bottom: 1rem;">
              <?= __('update_revert_subtitle', 'Restaura una versión previamente guardada de BrisaCMS. Tus artículos, páginas y configuración no se verán afectados.') ?>
            </div>
            <div>
              <label class="form-label" for="rollback-select"><?= __('update_select_backup', 'Seleccionar copia de seguridad:') ?></label>
              <select id="rollback-select" style="max-width: 100%; width: 320px;">
                <?php foreach ($rollbacks as $rb): 
                  $isSelected = ($hasRollback && $rb['path'] === $rollbackPath) ? 'selected' : '';
                ?>
                  <option value="<?= htmlspecialchars($rb['filename']) ?>" <?= $isSelected ?>>
                    v<?= htmlspecialchars($rb['version']) ?> (<?= htmlspecialchars($rb['date']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn btn-secondary" id="btn-rollback" onclick="cmsUpdateRollback()" style="color: var(--yellow); border-color: rgba(251, 191, 36, 0.3);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
            <?= __('update_revert_btn', 'Revertir a la versión seleccionada') ?>
          </button>
        </div>
        <div id="rollback-status" style="display: none; margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 13.5px;"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Historial -->
    <div class="card">
      <div class="card-header"><span class="card-title"><?= __('update_history_title', 'Historial de actualizaciones') ?></span></div>
      <div class="card-body" style="padding: 0;">
        <?php if (empty($history)): ?>
          <div style="padding: 3rem; text-align: center; color: var(--muted);">
            <?= __('update_history_empty', 'Sin actualizaciones registradas todavía.') ?>
          </div>
        <?php else: ?>
          <table class="table" style="width: 100%;">
            <thead>
              <tr>
                <th style="padding-left: 1.5rem;"><?= __('update_history_col_version', 'De → A') ?></th>
                <th><?= __('update_history_col_status', 'Estado') ?></th>
                <th><?= __('update_history_col_date', 'Fecha') ?></th>
                <th style="padding-right: 1.5rem; text-align: right;"><?= __('update_history_col_backup', 'Backup') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h):
                $statusColor = ($h['status'] === 'applied') ? 'var(--green)' : 'var(--yellow)';
                $statusBg = ($h['status'] === 'applied') ? 'rgba(52, 211, 153, 0.1)' : 'rgba(251, 191, 36, 0.1)';
                $statusLabel = ($h['status'] === 'applied') ? __('update_status_applied', 'Aplicada') : __('update_status_reverted', 'Revertida');
              ?>
                <tr>
                  <td style="padding-left: 1.5rem;">
                    <span style="color: var(--muted);">v<?= htmlspecialchars($h['from_version']) ?></span>
                    <span style="color: var(--muted); margin: 0 4px;">→</span>
                    <span style="font-weight: 600;">v<?= htmlspecialchars($h['to_version']) ?></span>
                  </td>
                  <td>
                    <span class="badge" style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; border-radius: 4px; padding: 0.15rem 0.4rem; font-size: 0.72rem; font-weight: 600;">
                      <?= htmlspecialchars($statusLabel) ?>
                    </span>
                  </td>
                  <td style="color: var(--text2); font-size: 13px;">
                    <?= htmlspecialchars($h['applied_at']) ?>
                  </td>
                  <td style="padding-right: 1.5rem; text-align: right; font-size: 12px; color: var(--muted); font-family: monospace;">
                    <?= (!empty($h['backup_path']) && file_exists($h['backup_path'])) ? __('update_backup_saved', '✓ guardado') : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
let _updateData = null;

function cmsSaveToken(e) {
  e.preventDefault();
  const token = document.getElementById('update_token_input').value.trim();
  const btn = document.getElementById('btn-save-token');
  const status = document.getElementById('token-status');

  btn.disabled = true;
  status.style.display = 'block';
  status.style.color = 'var(--text2)';
  status.textContent = 'Guardando...';

  fetch('<?= base_url() ?>/api/update.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'save_token', token: token })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        status.style.color = 'var(--green)';
        status.textContent = '✓ Token guardado correctamente. Recargando...';
        setTimeout(() => window.location.reload(), 1000);
      } else {
        status.style.color = 'var(--red)';
        status.textContent = 'Error al guardar el token.';
        btn.disabled = false;
      }
    })
    .catch(err => {
      status.style.color = 'var(--red)';
      status.textContent = 'Error de conexión: ' + err.message;
      btn.disabled = false;
    });
}

function cmsUpdateCheck() {
  const btn = document.getElementById('btn-check');
  const status = document.getElementById('update-status');
  const available = document.getElementById('update-available');

  btn.disabled = true;
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle; animation: spin 1.5s linear infinite;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Buscando...';
  status.style.display = 'block';
  status.style.background = 'var(--surface2)';
  status.style.color = 'var(--text2)';
  status.textContent = 'Consultando GitHub...';
  available.style.display = 'none';

  fetch('<?= base_url() ?>/api/update.php?action=check')
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        status.style.background = 'rgba(248, 113, 113, 0.1)';
        status.style.color = 'var(--red)';
        status.innerHTML = '❌ ' + data.error;
        return;
      }
      if (data.up_to_date) {
        status.style.background = 'rgba(52, 211, 153, 0.1)';
        status.style.color = 'var(--green)';
        status.innerHTML = '✅ BrisaCMS está actualizado — v' + data.version;
        return;
      }
      if (data.available) {
        _updateData = data;
        status.style.display = 'none';
        available.style.display = 'block';
        document.getElementById('update-version-label').textContent = data.name || ('v' + data.new_version);
        document.getElementById('update-date-label').textContent = data.published_at
          ? 'Publicado el ' + new Date(data.published_at).toLocaleDateString('es', {year:'numeric', month:'long', day:'numeric'})
          : '';
        document.getElementById('update-changelog-body').textContent = data.changelog || 'Sin notas de la versión.';
        document.getElementById('update-gh-link').href = data.html_url || '#';
      }
    })
    .catch(e => {
      status.style.background = 'rgba(248, 113, 113, 0.1)';
      status.style.color = 'var(--red)';
      status.innerHTML = '❌ Error de conexión: ' + e.message;
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Buscar actualizaciones';
    });
}

function cmsUpdateApply() {
  if (!_updateData) return;
  if (!confirm('¿Actualizar BrisaCMS de v' + _updateData.current_version + ' a v' + _updateData.new_version + '?\n\nSe creará un backup automáticamente antes de aplicar los cambios.')) return;

  const btn = document.getElementById('btn-apply');
  const progress = document.getElementById('update-progress');
  const progressBar = document.getElementById('progress-bar');
  const progressLabel = document.getElementById('progress-label');
  const progressSub = document.getElementById('progress-sub');
  const result = document.getElementById('update-result');
  const resultCard = document.getElementById('update-result-card');
  const available = document.getElementById('update-available');

  const showError = (msg) => {
    progress.style.display = 'none';
    result.style.display = 'block';
    resultCard.style.background = 'rgba(248, 113, 113, 0.1)';
    resultCard.style.border = '1px solid rgba(248, 113, 113, 0.2)';
    resultCard.style.color = 'var(--red)';
    document.getElementById('result-title').textContent = '❌ Error';
    document.getElementById('result-body').textContent = msg;
    btn.disabled = false;
    available.style.display = 'block';
  };

  btn.disabled = true;
  available.style.display = 'none';
  result.style.display = 'none';
  progress.style.display = 'block';

  // Paso 1: Descargar
  progressLabel.textContent = 'Paso 1/2: Descargando actualización...';
  progressSub.textContent = 'Obteniendo v' + _updateData.new_version + ' desde GitHub';
  progressBar.style.width = '25%';

  const dlBody = new URLSearchParams({
    action: 'download',
    zip_url: btoa(_updateData.zip_url)
  });

  fetch('<?= base_url() ?>/api/update.php', { method: 'POST', body: dlBody })
    .then(async r => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch(e) {
        throw new Error('El servidor devolvió un error (HTTP ' + r.status + '):\n' + text.substring(0, 150) + '...');
      }
    })
    .then(dlData => {
      if (dlData.error) return showError(dlData.error);

      // Paso 2: Aplicar
      progressLabel.textContent = 'Paso 2/2: Creando respaldo y aplicando...';
      progressSub.textContent = 'Respaldando y copiando ficheros nuevos';
      progressBar.style.width = '65%';

      const applyBody = new URLSearchParams({
        action: 'apply',
        zip_path: dlData.path,
        new_version: _updateData.new_version
      });

      return fetch('<?= base_url() ?>/api/update.php', { method: 'POST', body: applyBody })
        .then(async r => {
          const text = await r.text();
          try {
            return JSON.parse(text);
          } catch(e) {
            throw new Error('El servidor devolvió un error (HTTP ' + r.status + '):\n' + text.substring(0, 150) + '...');
          }
        })
        .then(data => {
          if (data.error) return showError(data.error);
          if (data.success) {
            progressBar.style.width = '100%';
            progress.style.display = 'none';
            result.style.display = 'block';
            resultCard.style.background = 'rgba(52, 211, 153, 0.1)';
            resultCard.style.border = '1px solid rgba(52, 211, 153, 0.2)';
            resultCard.style.color = 'var(--green)';
            document.getElementById('result-title').textContent = '✅ Actualización completada';
            document.getElementById('result-body').innerHTML =
              'BrisaCMS actualizado de <strong>v' + data.from + '</strong> a <strong>v' + data.to + '</strong>. ' +
              data.files_updated + ' ficheros actualizados.<br><br>' +
              '<a href="<?= base_url() ?>/admin/update.php" class="btn btn-primary" style="margin-top:8px">Recargar página</a>';
          }
        });
    })
    .catch(e => showError(e.message));
}

function cmsUpdateRollback() {
  const select = document.getElementById('rollback-select');
  const backupFile = select ? select.value : '';
  if (!backupFile) {
    alert('Por favor, selecciona una copia de seguridad para restaurar.');
    return;
  }

  const option = select.options[select.selectedIndex];
  const versionText = option ? option.text : 'la versión seleccionada';

  if (!confirm('¿Revertir BrisaCMS a ' + versionText + '?\n\nSe restaurarán todos los ficheros del código. Tus datos (artículos, páginas y configuración) no se verán afectados.')) return;

  const btn = document.getElementById('btn-rollback');
  const status = document.getElementById('rollback-status');
  btn.disabled = true;
  status.style.display = 'block';
  status.style.background = 'var(--surface2)';
  status.style.color = 'var(--text2)';
  status.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle; animation: spin 1.5s linear infinite;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Restaurando...';

  fetch('<?= base_url() ?>/api/update.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'rollback', backup_file: backupFile })
  })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        status.style.background = 'rgba(248, 113, 113, 0.1)';
        status.style.color = 'var(--red)';
        status.innerHTML = '❌ ' + data.error;
        btn.disabled = false;
        return;
      }
      if (data.success) {
        status.style.background = 'rgba(52, 211, 153, 0.1)';
        status.style.color = 'var(--green)';
        status.innerHTML = '✅ Rollback completado. Restaurada versión <strong>v' + data.restored_version + '</strong>.<br><br><a href="<?= base_url() ?>/admin/update.php" class="btn btn-primary" style="margin-top:8px">Recargar página</a>';
      }
    })
    .catch(e => {
      status.style.background = 'rgba(248, 113, 113, 0.1)';
      status.style.color = 'var(--red)';
      status.innerHTML = '❌ Error: ' + e.message;
      btn.disabled = false;
    });
}
</script>
<style>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
</body></html>
