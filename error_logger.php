<?php
/**
 * Sistema de logs de errores para producción
 * Registra fallos y eventos críticos del sistema
 */

class ErrorLogger {
    private static $logFile;
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        self::$logFile = __DIR__ . '/logs/system.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Proteger archivo de logs
        if (!file_exists($logDir . '/.htaccess')) {
            file_put_contents($logDir . '/.htaccess', "Order deny,allow\nDeny from all");
        }
        
        self::$initialized = true;
    }
    
    /**
     * Registra un error en el log
     * @param string $message Mensaje del error
     * @param string $level Nivel del error (ERROR, WARNING, INFO)
     * @param array $context Contexto adicional
     */
    public static function log($message, $level = 'ERROR', $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $user = isset($_SESSION['usuario']) ? $_SESSION['usuario']['nombre'] : 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'user' => $user,
            'ip' => $ip,
            'context' => $context
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        
        // Escribir en archivo
        error_log($logLine, 3, self::$logFile);
        
        // Rotar logs si son muy grandes (máximo 10MB)
        self::rotateLogs();
    }
    
    /**
     * Registra errores de base de datos
     */
    public static function logDatabaseError($query, $error, $params = []) {
        self::log("Error en base de datos", 'ERROR', [
            'query' => $query,
            'error' => $error,
            'params' => $params
        ]);
    }
    
    /**
     * Registra errores de seguridad
     */
    public static function logSecurity($event, $details = []) {
        self::log("Evento de seguridad: $event", 'WARNING', $details);
    }
    
    /**
     * Registra errores críticos del sistema
     */
    public static function logCritical($event, $details = []) {
        self::log("Error crítico: $event", 'CRITICAL', $details);
    }
    
    /**
     * Rota los logs cuando son muy grandes
     */
    private static function rotateLogs() {
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (file_exists(self::$logFile) && filesize(self::$logFile) > $maxSize) {
            $backupFile = str_replace('.log', '_' . date('Y-m-d_H-i-s') . '.log', self::$logFile);
            rename(self::$logFile, $backupFile);
            
            // Mantener solo los últimos 5 archivos de log
            $logDir = dirname(self::$logFile);
            $files = glob($logDir . '/*.log');
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            if (count($files) > 5) {
                $filesToDelete = array_slice($files, 5);
                foreach ($filesToDelete as $file) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Obtiene los logs recientes
     */
    public static function getRecentLogs($lines = 100) {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        $lines = array_filter(explode("\n", $content));
        
        // Tomar las últimas líneas
        $lines = array_slice($lines, -$lines);
        
        $logs = [];
        foreach ($lines as $line) {
            if (!empty($line)) {
                $logs[] = json_decode($line, true);
            }
        }
        
        return array_reverse($logs); // Más recientes primero
    }
    
    /**
     * Limpia logs antiguos
     */
    public static function clearOldLogs($days = 30) {
        self::init();
        
        $logDir = dirname(self::$logFile);
        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < strtotime("-$days days")) {
                unlink($file);
            }
        }
    }
}

// Configurar manejador de errores personalizado
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    ErrorLogger::log("PHP Error: $message", 'ERROR', [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
});

// Configurar manejador de excepciones no capturadas
set_exception_handler(function($exception) {
    ErrorLogger::log("Uncaught Exception: " . $exception->getMessage(), 'CRITICAL', [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});

// Registrar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        ErrorLogger::log("Fatal Error: " . $error['message'], 'CRITICAL', $error);
    }
});
?>
