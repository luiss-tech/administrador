<?php
/**
 * Script de ejecución automática de backups (Web Cron)
 * Se puede ejecutar via URL, cron job, o task scheduler
 */

// Evitar acceso directo no autorizado
$allowed_ips = ['127.0.0.1', '::1']; // Solo localhost
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    die('Acceso denegado');
}

// Iniciar sesión para el backup (sesión de sistema)
session_start();
$_SESSION['usuario'] = [
    'id' => 0,
    'nombre' => 'Sistema Automático',
    'rol' => 'administrador'
];

require_once 'backup_utils.php';

$backup = new BackupManager();
$result = $backup->runScheduledBackups();

// Registrar resultado
$log_message = date('Y-m-d H:i:s') . ' - ' . $result['message'];
file_put_contents(__DIR__ . '/logs/cron_backup.log', $log_message . PHP_EOL, FILE_APPEND);

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'success' => $result['success'],
    'message' => $result['message']
]);
?>
