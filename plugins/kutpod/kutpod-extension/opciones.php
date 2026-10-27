<?php
// ============================================================================
// KutPod · Opciones del Plugin BrisaCMS
// ============================================================================

// Evitar acceso directo
if (!defined('KUTPOD_VERSION')) {
    exit;
}

// Procesar el guardado si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kp_update_setting('brisacms_webhook_url', trim($_POST['brisacms_webhook_url'] ?? ''));
    kp_update_setting('brisacms_secret', trim($_POST['brisacms_secret'] ?? ''));
    
    setcookie('kp_flash', 'Configuración de BrisaCMS guardada con éxito', time() + 30, '/');
    header('Location: ' . admin_url('plugins?action=settings&plugin=brisacms'));
    exit;
}

$webhookUrl = kp_setting('brisacms_webhook_url', '');
$secret = kp_setting('brisacms_secret', '');

?>

<div class="card span-12" style="max-width:800px; margin:0 auto">
  <div class="card-head" style="margin-bottom:18px">
    <div>
      <h2 style="margin:0">Publicador Automático hacia BrisaCMS</h2>
      <p class="help" style="margin:4px 0 0 0">Envía automáticamente episodios a tu blog BrisaCMS cuando se publiquen o actualicen.</p>
    </div>
  </div>

  <form method="POST">
    <?= kp_csrf_field() ?>
    
    <div class="field-row">
      <div class="field">
        <label class="label">URL del Webhook de BrisaCMS</label>
        <input class="input" type="url" name="brisacms_webhook_url" value="<?= e($webhookUrl) ?>" placeholder="https://tublog.com/api/kutpod/webhook" required>
        <div class="help" style="margin-top:4px">La URL del endpoint webhook de BrisaCMS (ej. <code>https://tublog.com/api/kutpod/webhook</code>).</div>
      </div>
    </div>

    <div class="field-row" style="margin-top:14px">
      <div class="field">
        <label class="label">Token Secreto (Secret Token)</label>
        <input class="input" type="text" name="brisacms_secret" value="<?= e($secret) ?>" placeholder="Token secreto configurado en BrisaCMS" style="font-family:ui-monospace,monospace">
        <div class="help" style="margin-top:4px">Debe coincidir exactamente con el token secreto configurado en el panel de BrisaCMS.</div>
      </div>
    </div>

    <div style="font-size:12.5px;color:var(--text-3);margin-top:18px;line-height:1.5">
      Cada vez que publiques o actualices un episodio en cualquiera de tus podcasts, KutPod notificará a BrisaCMS para crear o actualizar el artículo correspondiente.
    </div>

    <div style="margin-top:24px; display:flex; gap:12px">
      <button class="btn btn-primary" type="submit">Guardar Configuración</button>
      <a class="btn" href="<?= admin_url('plugins') ?>">Volver a Plugins</a>
    </div>
  </form>
</div>
