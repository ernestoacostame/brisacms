<?php
/**
 * KutPod Plugin - BrisaCMS Settings in KutPod Admin
 */

if (!defined('KUTPOD_VERSION')) {
    exit;
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brisacms'])) {
    kp_csrf_verify();
    kp_set_setting('brisacms_webhook_url', trim($_POST['brisacms_webhook_url'] ?? ''));
    kp_set_setting('brisacms_secret', trim($_POST['brisacms_secret'] ?? ''));
    $saved = true;
}

$webhookUrl = kp_setting('brisacms_webhook_url', '');
$secret = kp_setting('brisacms_secret', '');

?>

<div class="card" style="max-width: 700px; margin: 2rem auto;">
  <div class="card-header">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
      🌐 Publicador Automático hacia BrisaCMS
    </h2>
  </div>
  <div class="card-body">
    <?php if ($saved): ?>
      <div class="alert alert-success" style="margin-bottom: 1.5rem;">✓ Configuración guardada correctamente.</div>
    <?php endif; ?>

    <form method="POST">
      <?= kp_csrf_field() ?>
      <input type="hidden" name="save_brisacms" value="1">

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 6px;">URL del Webhook de BrisaCMS</label>
        <input type="url" name="brisacms_webhook_url" value="<?= htmlspecialchars($webhookUrl) ?>" class="form-control" placeholder="https://tublog.com/api/kutpod/webhook" required>
        <small style="color: var(--text-3); display: block; margin-top: 4px;">
          Copia esta URL desde los ajustes del plugin KutPod en tu panel de administración de BrisaCMS.
        </small>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 6px;">Token Secreto (Secret Token)</label>
        <input type="text" name="brisacms_secret" value="<?= htmlspecialchars($secret) ?>" class="form-control" placeholder="Token secreto configurado en BrisaCMS">
        <small style="color: var(--text-3); display: block; margin-top: 4px;">
          Debe coincidir exactamente con el token configurado en BrisaCMS.
        </small>
      </div>

      <div style="display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary">Guardar Configuración</button>
      </div>
    </form>
  </div>
</div>
