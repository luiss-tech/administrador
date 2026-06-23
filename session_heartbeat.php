<?php
/**
 * Endpoint para manejar heartbeat y detección de cierre del programa
 */

require_once 'session_manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? '';
$timestamp = $_POST['timestamp'] ?? time();

switch ($action) {
    case 'heartbeat':
        handleHeartbeat();
        break;
        
    case 'close':
        handleClose();
        break;
        
    case 'backup_check':
        handleBackupCheck();
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}

function handleHeartbeat() {
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(['status' => 'not_logged_in']);
        return;
    }
    
    try {
        // Actualizar heartbeat
        SessionManager::updateHeartbeat();
        
        // Verificar múltiples sesiones
        if (SessionManager::checkMultipleSessions()) {
            // La función ya maneja el logout
            return;
        }
        
        // Verificar inactividad
        SessionManager::checkInactivity();
        
        echo json_encode([
            'status' => 'active',
            'timestamp' => time(),
            'last_activity' => $_SESSION['last_activity'] ?? time()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function handleClose() {
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(['status' => 'not_logged_in']);
        return;
    }
    
    try {
        $empleado_id = $_SESSION['usuario']['id'];
        $session_id = session_id();
        
        // Registrar cierre del programa
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE sessiones_activas 
            SET status = 'expired', 
                logout_time = NOW(),
                observaciones = 'Programa cerrado inesperadamente'
            WHERE session_id = ? AND empleado_id = ? AND status = 'active'
        ");
        $stmt->execute([$session_id, $empleado_id]);
        
        // Registrar en logs
        error_log("Programa cerrado - Empleado ID: $empleado_id, Session ID: $session_id");
        
        // Marcar sesión como cerrada
        $_SESSION['program_closed'] = true;
        
        echo json_encode(['status' => 'closed', 'message' => 'Cierre del programa registrado']);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function handleBackupCheck() {
    try {
        require_once 'backup_utils.php';
        $backup = new BackupManager();
        $result = $backup->runScheduledBackups();
        
        echo json_encode([
            'status' => 'success',
            'backup_result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
