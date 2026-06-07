<?php
// ============================================================================
// BrisaCMS · API endpoint para actualizaciones
// ============================================================================
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/updater.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión admin
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Debes iniciar sesión como administrador.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // Guardar el Token de GitHub
    case 'save_token':
        $token = trim($_POST['token'] ?? '');
        cms_set_setting('update_github_token', $token);
        echo json_encode(['success' => true]);
        break;

    // Comprobar si hay actualizaciones disponibles
    case 'check':
        $result = cms_update_check();
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // Descargar el ZIP del release
    case 'download':
        set_time_limit(300);
        $zipUrlRaw = $_POST['zip_url'] ?? '';
        $zipUrl = $zipUrlRaw ? base64_decode($zipUrlRaw) : '';
        
        if (!$zipUrl) {
            echo json_encode(['error' => 'Faltan parámetros (zip_url).']);
            break;
        }

        // Validar que la URL sea de GitHub
        if (!preg_match('#^https://(?:api\.)?github\.com/#i', $zipUrl)) {
            echo json_encode(['error' => 'URL de descarga no válida. Solo se permiten URLs de GitHub.']);
            break;
        }

        $dl = cms_update_download($zipUrl);
        echo json_encode($dl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // Aplicar la actualización (creando un backup pre-update automático)
    case 'apply':
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        $zipPath = $_POST['zip_path'] ?? '';
        $newVersion = $_POST['new_version'] ?? '';

        if (!$zipPath || !$newVersion) {
            echo json_encode(['error' => 'Faltan parámetros (zip_path, new_version).']);
            break;
        }

        // Evitar path traversal en zip_path
        $realZipPath = realpath($zipPath);
        $realUpdateDir = realpath(cms_update_dir());
        if (!$realZipPath || !$realUpdateDir || !str_starts_with($realZipPath, $realUpdateDir)) {
            echo json_encode(['error' => 'Ruta del ZIP no válida o fuera del directorio de actualizaciones.']);
            break;
        }

        // Sanitizar versión
        $newVersion = preg_replace('/[^0-9.]/', '', $newVersion);

        $result = cms_update_apply($zipPath, $newVersion);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // Revertir a una versión guardada en el historial
    case 'rollback':
        set_time_limit(120);
        $backupFile = $_POST['backup_file'] ?? $_GET['backup_file'] ?? null;
        $result = cms_update_rollback($backupFile);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    // Eliminar copia de seguridad
    case 'delete_backup':
        $backupFile = $_POST['backup_file'] ?? '';
        if (!$backupFile) {
            echo json_encode(['error' => 'Falta el parámetro backup_file.']);
            break;
        }
        $result = cms_update_delete_backup($backupFile);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
        break;
}
