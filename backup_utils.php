<?php
/**
 * Sistema de respaldo automático y restauración de base de datos
 * Para uso en producción del sistema de ventas e inventario
 */

require_once 'session_manager.php';
require_once 'config/database.php';

class BackupManager {
    private $db;
    private $backupDir;
    private $maxBackups = 10;
    
    public function __construct() {
        $this->db = getDB();
        $this->backupDir = __DIR__ . '/backups/';
        
        // Crear directorio de backups si no existe
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
            
            // Crear .htaccess para proteger el directorio
            $htaccess = "Order deny,allow\nDeny from all";
            file_put_contents($this->backupDir . '.htaccess', $htaccess);
        }
    }
    
    /**
     * Genera un respaldo completo de la base de datos
     * @param string $description Descripción opcional del backup
     * @return array Resultado de la operación
     */
    public function createBackup($description = '') {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $filepath = $this->backupDir . $filename;
            
            // Obtener solo tablas reales (excluir vistas)
            $stmt = $this->db->query("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_TYPE = 'BASE TABLE'
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $sql = "-- Sistema de Ventas - Backup generado: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Descripción: {$description}\n";
            $sql .= "-- Generado por: " . ($_SESSION['usuario']['nombre'] ?? 'Sistema') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $sql .= $this->getTableStructure($table);
                $sql .= $this->getTableData($table);
                $sql .= "\n";
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Guardar archivo
            if (file_put_contents($filepath, $sql)) {
                // Guardar información del backup
                $this->saveBackupInfo($filename, $description);
                
                // Limpiar backups antiguos
                $this->cleanupOldBackups();
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'size' => $this->formatBytes(filesize($filepath)),
                    'message' => 'Backup creado correctamente'
                ];
            }
            
            return ['success' => false, 'message' => 'Error al guardar el archivo de backup'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al generar backup: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene la estructura de una tabla
     */
    private function getTableStructure($table) {
        try {
            $stmt = $this->db->prepare("SHOW CREATE TABLE `{$table}`");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // La columna puede llamarse 'Create Table' o 'CreateTable' dependiendo de la versión
            $createTable = null;
            if (isset($result['Create Table'])) {
                $createTable = $result['Create Table'];
            } elseif (isset($result['CreateTable'])) {
                $createTable = $result['CreateTable'];
            } elseif (isset($result[1])) {
                $createTable = $result[1]; // Índice numérico
            }
            
            if ($createTable === null) {
                throw new Exception("No se pudo obtener la estructura de la tabla '{$table}'");
            }
            
            $sql = "-- Estructura de la tabla `{$table}`\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createTable . ";\n\n";
            
            return $sql;
            
        } catch (Exception $e) {
            // Si la tabla no existe o hay error, devolver comentario
            return "-- Error al obtener estructura de `{$table}`: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * Obtiene los datos de una tabla
     */
    private function getTableData($table) {
        $stmt = $this->db->prepare("SELECT * FROM `{$table}`");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return "-- Datos de la tabla `{$table}` (vacía)\n\n";
        }
        
        $sql = "-- Datos de la tabla `{$table}`\n";
        $sql .= "INSERT INTO `{$table}` VALUES\n";
        
        $values = [];
        foreach ($rows as $row) {
            $escapedValues = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $escapedValues[] = 'NULL';
                } else {
                    $escapedValues[] = "'" . addslashes($value) . "'";
                }
            }
            $values[] = '(' . implode(', ', $escapedValues) . ')';
        }
        
        $sql .= implode(",\n", $values) . ";\n\n";
        
        return $sql;
    }
    
    /**
     * Guarda información del backup en archivo JSON
     */
    private function saveBackupInfo($filename, $description) {
        // Obtener solo tablas reales (excluir vistas)
        $stmt = $this->db->query("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_TYPE = 'BASE TABLE'
        ");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $info = [
            'filename' => $filename,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['usuario']['nombre'] ?? 'Sistema',
            'user_id' => $_SESSION['usuario']['id'] ?? null,
            'tables' => $tables
        ];
        
        $infoFile = $this->backupDir . 'backup_info.json';
        $backups = [];
        
        if (file_exists($infoFile)) {
            $backups = json_decode(file_get_contents($infoFile), true) ?: [];
        }
        
        $backups[] = $info;
        file_put_contents($infoFile, json_encode($backups, JSON_PRETTY_PRINT));
    }
    
    /**
     * Limpia backups antiguos manteniendo solo los más recientes
     */
    private function cleanupOldBackups() {
        $files = glob($this->backupDir . 'backup_*.sql');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        if (count($files) > $this->maxBackups) {
            $filesToDelete = array_slice($files, $this->maxBackups);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Ejecuta backups automáticos según configuración (con sistema recuperativo)
     */
    public function runScheduledBackups() {
        try {
            $configFile = __DIR__ . '/config/backup_config.json';
            
            if (!file_exists($configFile)) {
                return ['success' => false, 'message' => 'No hay configuración de backups automáticos'];
            }
            
            $config = json_decode(file_get_contents($configFile), true) ?: [];
            $currentHour = date('H:i');
            $currentDay = date('w'); // 0 = Domingo, 6 = Sábado
            $currentDate = date('Y-m-d');
            $executed = [];
            
            // Obtener última ejecución registrada
            $lastExecution = $this->getLastBackupExecution();
            
            // Backup diario (con sistema recuperativo)
            if ($config['backup_diario']) {
                $backupTime = $config['hora_backup_diario'];
                $shouldExecute = false;
                $backupType = 'diario';
                
                // Si es exactamente la hora programada
                if ($currentHour === $backupTime) {
                    $shouldExecute = true;
                    $backupDescription = 'Backup automático diario';
                }
                // Si ya pasó la hora y no se ejecutó hoy (sistema recuperativo)
                elseif ($currentHour > $backupTime && (!$lastExecution['diario'] || $lastExecution['diario'] !== $currentDate)) {
                    $shouldExecute = true;
                    $backupDescription = 'Backup automático diario (ejecución diferida)';
                }
                
                if ($shouldExecute) {
                    $result = $this->createBackup($backupDescription);
                    if ($result['success']) {
                        $executed[] = 'Backup diario creado: ' . $result['filename'];
                        $this->updateLastBackupExecution('diario', $currentDate);
                    }
                }
            }
            
            // Backup semanal (con sistema recuperativo)
            if ($config['backup_semanal']) {
                $backupTime = $config['hora_backup_diario'];
                $shouldExecute = false;
                $backupType = 'semanal';
                
                // Si es domingo y exactamente la hora programada
                if ($currentDay == 0 && $currentHour === $backupTime) {
                    $shouldExecute = true;
                    $backupDescription = 'Backup automático semanal';
                }
                // Si ya es domingo, pasó la hora y no se ejecutó esta semana (sistema recuperativo)
                elseif ($currentDay == 0 && $currentHour > $backupTime && (!$lastExecution['semanal'] || $this->getWeekNumber($lastExecution['semanal']) !== $this->getWeekNumber($currentDate))) {
                    $shouldExecute = true;
                    $backupDescription = 'Backup automático semanal (ejecución diferida)';
                }
                
                if ($shouldExecute) {
                    $result = $this->createBackup($backupDescription);
                    if ($result['success']) {
                        $executed[] = 'Backup semanal creado: ' . $result['filename'];
                        $this->updateLastBackupExecution('semanal', $currentDate);
                    }
                }
            }
            
            if (!empty($executed)) {
                return [
                    'success' => true, 
                    'message' => 'Backups automáticos ejecutados: ' . implode(', ', $executed)
                ];
            }
            
            return ['success' => false, 'message' => 'No hay backups programados para este momento'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error en backups automáticos: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene la última ejecución de backups
     */
    private function getLastBackupExecution() {
        $logFile = $this->backupDir . 'backup_execution_log.json';
        
        if (!file_exists($logFile)) {
            return ['diario' => null, 'semanal' => null];
        }
        
        $log = json_decode(file_get_contents($logFile), true) ?: [];
        return $log['last_execution'] ?? ['diario' => null, 'semanal' => null];
    }
    
    /**
     * Actualiza la última ejecución de backup
     */
    private function updateLastBackupExecution($type, $date) {
        $logFile = $this->backupDir . 'backup_execution_log.json';
        
        $log = [];
        if (file_exists($logFile)) {
            $log = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        if (!isset($log['last_execution'])) {
            $log['last_execution'] = ['diario' => null, 'semanal' => null];
        }
        
        $log['last_execution'][$type] = $date;
        $log['last_updated'] = date('Y-m-d H:i:s');
        
        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
    }
    
    /**
     * Obtiene el número de semana de una fecha
     */
    private function getWeekNumber($date) {
        return date('W', strtotime($date));
    }
    
    /**
     * Lista todos los backups disponibles
     */
    public function listBackups() {
        $infoFile = $this->backupDir . 'backup_info.json';
        
        if (!file_exists($infoFile)) {
            return [];
        }
        
        $backups = json_decode(file_get_contents($infoFile), true) ?: [];
        
        // Ordenar por fecha descendente
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Elimina un backup específico
     */
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (file_exists($filepath)) {
            unlink($filepath);
            
            // Actualizar información
            $this->removeBackupInfo($filename);
            
            return ['success' => true, 'message' => 'Backup eliminado correctamente'];
        }
        
        return ['success' => false, 'message' => 'Backup no encontrado'];
    }
    
    /**
     * Elimina información de un backup del archivo JSON
     */
    private function removeBackupInfo($filename) {
        $infoFile = $this->backupDir . 'backup_info.json';
        
        if (!file_exists($infoFile)) {
            return;
        }
        
        $backups = json_decode(file_get_contents($infoFile), true) ?: [];
        
        $backups = array_filter($backups, function($backup) use ($filename) {
            return $backup['filename'] !== $filename;
        });
        
        file_put_contents($infoFile, json_encode(array_values($backups), JSON_PRETTY_PRINT));
    }
    
    /**
     * Formatea bytes a formato legible
     */
    private function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Configura backup automático diario (opcional)
     */
    public function setupAutoBackup() {
        // Esta función puede ser llamada desde un cron job
        return $this->createBackup('Backup automático diario');
    }
    
    /**
     * Restaura un backup de base de datos
     * @param string $filename Nombre del archivo de backup
     * @return array Resultado de la operación
     */
    public function restoreBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            // Verificar que el archivo existe
            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'Archivo de backup no encontrado'];
            }
            
            // Verificar extensión
            if (!str_ends_with($filename, '.sql')) {
                return ['success' => false, 'message' => 'El archivo debe ser .sql'];
            }
            
            // Leer contenido del backup
            $sql = file_get_contents($filepath);
            
            if (empty($sql)) {
                return ['success' => false, 'message' => 'El archivo de backup está vacío'];
            }
            
            // Desactivar foreign keys temporalmente
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Ejecutar el SQL del backup
            $this->db->exec($sql);
            
            // Reactivar foreign keys
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            return [
                'success' => true,
                'message' => 'Backup restaurado correctamente. La base de datos ha sido restaurada al estado del backup seleccionado.'
            ];
            
        } catch (PDOException $e) {
            // Reactivar foreign keys en caso de error
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            return ['success' => false, 'message' => 'Error al restaurar backup: ' . $e->getMessage()];
        } catch (Exception $e) {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            return ['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verifica la integridad de un backup antes de restaurar
     * @param string $filename Nombre del archivo
     * @return array Información del backup
     */
    public function verifyBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                return ['valid' => false, 'message' => 'Archivo no encontrado'];
            }
            
            $content = file_get_contents($filepath);
            
            // Verificaciones básicas
            $checks = [
                'size' => filesize($filepath),
                'has_structure' => strpos($content, 'CREATE TABLE') !== false,
                'has_data' => strpos($content, 'INSERT INTO') !== false,
                'has_header' => strpos($content, 'Sistema de Ventas - Backup') !== false,
                'tables_count' => substr_count($content, 'CREATE TABLE')
            ];
            
            return [
                'valid' => $checks['has_structure'] || $checks['has_data'],
                'checks' => $checks,
                'message' => $checks['has_structure'] ? 'Backup válido' : 'Backup parece incompleto'
            ];
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => 'Error al verificar: ' . $e->getMessage()];
        }
    }
}

// Manejo de solicitudes AJAX específicas de backup
if (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST' && 
    in_array($_POST['action'], ['test_backup', 'create', 'list', 'delete'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'administrador') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit();
    }
    
    $backup = new BackupManager();
    $result = ['success' => false, 'message' => 'Acción no válida'];
    
    switch ($_POST['action']) {
        case 'test_backup':
            $result = $backup->runScheduledBackups();
            break;
            
        case 'create':
            $description = $_POST['description'] ?? '';
            $result = $backup->createBackup($description);
            break;
            
        case 'list':
            $result = ['success' => true, 'backups' => $backup->listBackups()];
            break;
            
        case 'delete':
            $filename = $_POST['filename'] ?? '';
            $result = $backup->deleteBackup($filename);
            break;
    }
    
    echo json_encode($result);
    exit;
}
?>
