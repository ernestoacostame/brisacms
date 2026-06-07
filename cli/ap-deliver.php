#!/usr/bin/env php
<?php
// cli/ap-deliver.php
// Run this worker via Cron to process the ActivityPub delivery queue.
// Example cron job (every minute):
// * * * * * php /var/www/html/cli/ap-deliver.php > /dev/null 2>&1

require_once dirname(__DIR__) . '/core/config.php';

// Prevent execution if the plugin is not active
if (!cms_plugin_is_active('fediverse')) {
    exit("El plugin del Fediverso no está activo.\n");
}

require_once dirname(__DIR__) . '/plugins/fediverse/activitypub.php';

// Process delivery queue
$sent = ap_deliver_pending();

if ($sent > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Entregados $sent mensajes de ActivityPub.\n";
}
