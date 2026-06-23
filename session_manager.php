<?php
/**
 * Gestor avanzado de sesiones con cierre automático
 * Maneja inactividad, detección de cierre del programa y heartbeat
 */

require_once 'config/database.php';

class SessionManager {
    private static $inactivity_timeout = 30 * 60; // 30 minutos en segundos
    private static $heartbeat_interval = 5 * 60;    // 5 minutos en segundos
    private static $cleanup_interval = 60 * 60;     // 1 hora en segundos
    
    /**
     * Inicializa el gestor de sesiones
     */
    public static function init() {
        // Configurar tiempo de vida de la sesión
        ini_set('session.gc_maxlifetime', self::$inactivity_timeout);
        ini_set('session.cookie_lifetime', self::$inactivity_timeout);
        
        // Iniciar sesión si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar si la sesión debe cerrarse por inactividad
        self::checkInactivity();
        
        // Actualizar heartbeat
        self::updateHeartbeat();
        
        // Limpiar sesiones expiradas
        self::cleanupExpiredSessions();
    }
    
    /**
     * Verifica si la sesión debe cerrarse por inactividad
     */
    public static function checkInactivity() {
        if (!isset($_SESSION['usuario'])) {
            return;
        }
        
        $last_activity = $_SESSION['last_activity'] ?? time();
        $current_time = time();
        
        // Si ha pasado más tiempo del permitido, cerrar sesión
        if ($current_time - $last_activity > self::$inactivity_timeout) {
            self::forceLogout('inactividad');
        }
    }
    
    /**
     * Actualiza el heartbeat de la sesión
     */
    public static function updateHeartbeat() {
        if (!isset($_SESSION['usuario'])) {
            return;
        }
        
        $_SESSION['last_activity'] = time();
        $_SESSION['heartbeat'] = time();
        
        // Actualizar en base de datos para detección de cierre
        self::updateSessionHeartbeat();
    }
    
    /**
     * Actualiza el heartbeat en la base de datos
     */
    private static function updateSessionHeartbeat() {
        try {
            $db = getDB();
            $session_id = session_id();
            $empleado_id = $_SESSION['usuario']['id'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            // Intentar actualizar registro existente
            $stmt = $db->prepare("
                UPDATE sessiones_activas 
                SET last_heartbeat = NOW(), 
                    ip_address = ?,
                    user_agent = ?
                WHERE session_id = ? AND empleado_id = ?
            ");
            $result = $stmt->execute([$ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $session_id, $empleado_id]);
            
            // Si no se actualizó, insertar nuevo registro
            if ($result === false || ($result instanceof PDOStatement && $result->rowCount() === 0)) {
                $stmt = $db->prepare("
                    INSERT INTO sessiones_activas 
                    (session_id, empleado_id, login_time, last_heartbeat, ip_address, user_agent)
                    VALUES (?, ?, NOW(), NOW(), ?, ?)
                ");
                $stmt->execute([$session_id, $empleado_id, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
            }
            
        } catch (Exception $e) {
            // Si la tabla no existe, crearla
            self::createSessionTable();
            self::updateSessionHeartbeat();
        }
    }
    
    /**
     * Crea la tabla de sesiones si no existe
     */
    private static function createSessionTable() {
        try {
            $db = getDB();
            $sql = "
                CREATE TABLE IF NOT EXISTS sessiones_activas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(255) NOT NULL,
                    empleado_id INT NOT NULL,
                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    logout_time TIMESTAMP NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    status ENUM('active', 'expired', 'logout') DEFAULT 'active',
                    INDEX idx_session (session_id),
                    INDEX idx_empleado (empleado_id),
                    INDEX idx_heartbeat (last_heartbeat),
                    INDEX idx_status (status),
                    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
                ) ENGINE=InnoDB COMMENT='Sesiones activas del sistema'
            ";
            $db->exec($sql);
        } catch (Exception $e) {
            error_log("Error al crear tabla de sesiones: " . $e->getMessage());
        }
    }
    
    /**
     * Cierra sesión forzosamente
     */
    public static function forceLogout($reason = 'manual') {
        $empleado_id = $_SESSION['usuario']['id'] ?? null;
        $session_id = session_id();
        
        // Registrar logout en base de datos
        if ($empleado_id) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    UPDATE sessiones_activas 
                    SET status = 'expired', 
                        logout_time = NOW()
                    WHERE session_id = ? AND empleado_id = ?
                ");
                $stmt->execute([$session_id, $empleado_id]);
                
                // Registrar en logs
                error_log("Sesión cerrada automáticamente - Empleado ID: $empleado_id, Razón: $reason");
                
            } catch (Exception $e) {
                error_log("Error al registrar logout automático: " . $e->getMessage());
            }
        }
        
        // Destruir sesión
        session_unset();
        session_destroy();
        
        // Redirigir a login con mensaje
        $message = match($reason) {
            'inactividad' => 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.',
            'program_cerrado' => 'El sistema se cerró inesperadamente. Por seguridad, tu sesión ha sido finalizada.',
            'multiple_sessions' => 'Se detectó otra sesión activa. Esta sesión ha sido cerrada por seguridad.',
            default => 'Has cerrado sesión correctamente.'
        };
        
        header("Location: index.php?logout_reason=" . urlencode($reason) . "&message=" . urlencode($message));
        exit;
    }
    
    /**
     * Limpia sesiones expiradas
     */
    public static function cleanupExpiredSessions() {
        try {
            $db = getDB();
            
            // Marcar como expiradas las sesiones inactivas por más tiempo
            $stmt = $db->prepare("
                UPDATE sessiones_activas 
                SET status = 'expired', 
                    logout_time = NOW()
                WHERE status = 'active' 
                AND last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([self::$inactivity_timeout]);
            
            // Eliminar sesiones muy antiguas (más de 7 días)
            $stmt = $db->prepare("
                DELETE FROM sessiones_activas 
                WHERE logout_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error al limpiar sesiones expiradas: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica si hay múltiples sesiones activas
     */
    public static function checkMultipleSessions() {
        if (!isset($_SESSION['usuario'])) {
            return false;
        }
        
        try {
            $db = getDB();
            $empleado_id = $_SESSION['usuario']['id'];
            $current_session = session_id();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM sessiones_activas 
                WHERE empleado_id = ? 
                AND session_id != ? 
                AND status = 'active'
                AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute([$empleado_id, $current_session]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                self::forceLogout('multiple_sessions');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error al verificar múltiples sesiones: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene sesiones activas actuales
     */
    public static function getActiveSessions() {
        try {
            $db = getDB();
            
            $stmt = $db->query("
                SELECT sa.*, e.nombre, e.usuario
                FROM sessiones_activas sa
                JOIN empleados e ON sa.empleado_id = e.id
                WHERE sa.status = 'active'
                ORDER BY sa.last_heartbeat DESC
            ");
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error al obtener sesiones activas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cierra sesión de un empleado específico
     */
    public static function logoutEmployee($empleado_id, $reason = 'admin') {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                UPDATE sessiones_activas 
                SET status = 'expired', 
                    logout_time = NOW()
                WHERE empleado_id = ? AND status = 'active'
            ");
            $stmt->execute([$empleado_id]);
            
            error_log("Administrador cerró sesión del empleado $empleado_id - Razón: $reason");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error al cerrar sesión del empleado: " . $e->getMessage());
            return false;
        }
    }
}

// Inicializar el gestor de sesiones
SessionManager::init();
?>
