<?php
/**
 * Sistema de Reset Completo del Sistema
 * Función: Limpiar toda la base de datos manteniendo estructura básica
 * Seguridad: Solo administradores con confirmación múltiple
 */

require_once 'session_manager.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: index.php');
    exit();
}

// Verificación adicional de seguridad - solo accesible si se escribe la URL directamente
// No debe aparecer en ningún menú
$acceso_directo = strpos($_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_HOST']) === false;

require_once 'config/database.php';
require_once 'backup_utils.php';

$db = getDB();

$message = '';
$messageType = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'verify_admin':
            $password = $_POST['password'] ?? '';
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT password FROM empleados WHERE id = 1 AND rol = 'administrador'");
                $stmt->execute();
                $admin = $stmt->fetch();
            } catch (Exception $e) {
                $message = 'Error de conexión a la base de datos';
                $messageType = 'danger';
                break;
            }
            
            if ($admin) {
                // Verificar contraseña (compatibilidad con password_hash)
                if (password_verify($password, $admin['password'])) {
                    header('Location: reset_system.php?step=2');
                    exit();
                }
                // Fallback para contraseñas en texto plano (migración desde CSV)
                if ($admin['password'] === $password) {
                    header('Location: reset_system.php?step=2');
                    exit();
                }
            }
            
            $message = 'Contraseña incorrecta. No se puede continuar.';
            $messageType = 'danger';
            break;
            
        case 'create_backup':
            $backup = new BackupManager();
            $result = $backup->createBackup('Backup previo al reset del sistema');
            
            if ($result['success']) {
                $_SESSION['backup_created'] = $result['filename'];
                header('Location: reset_system.php?step=3');
                exit();
            } else {
                $message = 'Error al crear backup: ' . $result['message'];
                $messageType = 'danger';
            }
            break;
            
        case 'execute_reset':
            $confirmation = $_POST['confirmation'] ?? '';
            
            if ($confirmation !== 'RESET_COMPLETO') {
                $message = 'Debe escribir exactamente "RESET_COMPLETO" para continuar';
                $messageType = 'danger';
                break;
            }
            
            // Ejecutar el reset
            try {
                $db = getDB();
                
                // PASO 1: Eliminar datos con transacción
                $db->beginTransaction();
                
                // Desactivar claves foráneas
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Limpiar datos
                $db->exec("DELETE FROM lotes");
                $db->exec("DELETE FROM productos");
                $db->exec("DELETE FROM detalle_venta");
                $db->exec("DELETE FROM ventas");
                $db->exec("DELETE FROM movimientos_caja");
                $db->exec("DELETE FROM cajas");
                $db->exec("DELETE FROM historial_cambios");
                $db->exec("DELETE FROM sessiones_activas");
                $db->exec("DELETE FROM empleados WHERE id > 1");
                
                // Reactivar claves foráneas
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                // Confirmar transacción de DELETE
                $db->commit();
                
                // PASO 2: Reiniciar contadores SIN transacción (ALTER TABLE no funciona bien en transacciones)
                try {
                    $db->exec("ALTER TABLE lotes AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE productos AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE ventas AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE detalle_venta AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE cajas AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE movimientos_caja AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE historial_cambios AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE sessiones_activas AUTO_INCREMENT = 1");
                    $db->exec("ALTER TABLE empleados AUTO_INCREMENT = 2");
                } catch (Exception $alterEx) {
                    error_log("Error en ALTER TABLE (no crítico): " . $alterEx->getMessage());
                    // Continuar aunque fallen los ALTER TABLE
                }
                
                // PASO 3: Limpiar backups antiguos, manteniendo solo el backup del reset
                try {
                    $backupDir = __DIR__ . '/backups';
                    $backupCreado = $_SESSION['backup_created'] ?? null;
                    
                    error_log("DEBUG: Iniciando limpieza de backups - Directorio: $backupDir - Backup creado: " . ($backupCreado ?? 'None'));
                    
                    if (is_dir($backupDir)) {
                        error_log("DEBUG: Directorio de backups existe");
                        
                        // Listar todos los archivos en el directorio
                        $todosArchivos = scandir($backupDir);
                        error_log("DEBUG: Archivos en directorio: " . implode(', ', $todosArchivos));
                        
                        $archivos = glob($backupDir . '/*.sql');
                        error_log("DEBUG: Encontrados " . count($archivos) . " archivos .sql");
                        
                        $eliminados = 0;
                        $omitidos = 0;
                        
                        // Recopilar backups que sí existen físicamente
                        $backupsExistentes = [];
                        
                        foreach ($archivos as $archivo) {
                            $nombreArchivo = basename($archivo);
                            error_log("DEBUG: Procesando archivo: $nombreArchivo");
                            
                            // No eliminar el backup creado durante el reset
                            if ($backupCreado && $nombreArchivo === $backupCreado) {
                                error_log("DEBUG: Omitiendo backup del reset: $nombreArchivo");
                                $omitidos++;
                                $backupsExistentes[] = $nombreArchivo;
                                continue;
                            }
                            
                            // Eliminar backup antiguo
                            error_log("DEBUG: Intentando eliminar: $archivo");
                            if (file_exists($archivo)) {
                                if (unlink($archivo)) {
                                    error_log("DEBUG: Eliminado exitosamente: $nombreArchivo");
                                    $eliminados++;
                                } else {
                                    error_log("DEBUG: Falló eliminar: $nombreArchivo - Verificar permisos");
                                }
                            } else {
                                error_log("DEBUG: Archivo no existe: $archivo");
                            }
                        }
                        
                        // PASO 4: Actualizar backup_info.json para que coincida con archivos existentes
                        try {
                            $infoFile = $backupDir . 'backup_info.json';
                            if (file_exists($infoFile)) {
                                // Leer información existente
                                $backupsInfo = json_decode(file_get_contents($infoFile), true) ?: [];
                                
                                // Filtrar solo los backups que existen físicamente
                                $backupsFiltrados = array_filter($backupsInfo, function($backup) use ($backupsExistentes) {
                                    return in_array($backup['filename'], $backupsExistentes);
                                });
                                
                                // Reindexar array y guardar
                                $backupsFiltrados = array_values($backupsFiltrados);
                                file_put_contents($infoFile, json_encode($backupsFiltrados, JSON_PRETTY_PRINT));
                                
                                error_log("BACKUP_INFO_JSON actualizado: " . count($backupsFiltrados) . " backups registrados");
                            }
                        } catch (Exception $jsonEx) {
                            error_log("Error actualizando backup_info.json: " . $jsonEx->getMessage());
                        }
                        
                        error_log("BACKUPS ELIMINADOS: $eliminados archivos antiguos - Omitidos: $omitidos - Backup mantenido: " . ($backupCreado ?? 'Ninguno'));
                    } else {
                        error_log("DEBUG: Directorio de backups NO existe: $backupDir");
                    }
                } catch (Exception $backupEx) {
                    error_log("Error limpiando backups antiguos: " . $backupEx->getMessage());
                    error_log("DEBUG: Exception trace: " . $backupEx->getTraceAsString());
                    // Continuar aunque falle la limpieza de backups
                }
                
                // Registrar en logs
                error_log("RESET COMPLETO DEL SISTEMA - Administrador: " . $_SESSION['usuario']['nombre'] . " - Fecha: " . date('Y-m-d H:i:s'));
                
                header('Location: reset_system.php?step=4');
                exit();
                
            } catch (Exception $e) {
                // Solo hacer rollback si hay transacción activa
                if (isset($db) && $db->inTransaction()) {
                    try {
                        $db->rollBack();
                    } catch (Exception $rollbackEx) {
                        error_log("Error en rollback: " . $rollbackEx->getMessage());
                    }
                }
                
                $message = 'Error durante el reset: ' . $e->getMessage();
                $messageType = 'danger';
                error_log("RESET ERROR: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset del Sistema - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            margin: 50px auto;
            overflow: hidden;
        }
        .reset-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .reset-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #dc3545;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .checklist {
            list-style: none;
            padding: 0;
        }
        .checklist li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .checklist li:before {
            content: "✓";
            color: #dc3545;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h2>Reset del Sistema</h2>
            <p>Proceso de limpieza completa de la base de datos</p>
        </div>
        
        <div class="reset-body">
            <!-- Indicador de pasos -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <!-- Paso 1: Verificación de administrador -->
                <h4><i class="fas fa-shield-alt"></i> Paso 1: Verificación de Seguridad</h4>
                
                <div class="warning-box">
                    <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
                    <p>Este proceso eliminará TODOS los datos del sistema excepto:</p>
                    <ul class="checklist">
                        <li>Usuario administrador principal</li>
                        <li>Categorías básicas</li>
                        <li>Estructura de la base de datos</li>
                        <li>Backup creado durante el reset</li>
                    </ul>
                    <p class="mb-0"><strong>Adicionalmente se eliminarán:</strong></p>
                    <ul class="checklist">
                        <li>Todos los backups anteriores (solo se mantiene el del reset)</li>
                    </ul>
                </div>
                
                <div class="danger-box">
                    <h5><i class="fas fa-exclamation-triangle"></i> ¡ADVERTENCIA!</h5>
                    <p><strong>Se eliminarán permanentemente:</strong></p>
                    <ul class="checklist">
                        <li>Todos los lotes de inventario</li>
                        <li>Todas las ventas y detalles</li>
                        <li>Todo el historial de cajas</li>
                        <li>Todos los empleados (excepto admin)</li>
                        <li>Todo el historial de cambios</li>
                        <li>Todas las sesiones activas</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="verify_admin">
                    
                    <div class="mb-3">
                        <label class="form-label">Contraseña de Administrador</label>
                        <input type="password" class="form-control" name="password" required 
                               placeholder="Ingrese su contraseña para continuar">
                        <small class="text-muted">Esta verificación es necesaria para proteger el sistema</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="dashboard_mysql.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-shield-alt"></i> Verificar y Continuar
                        </button>
                    </div>
                </form>
            
            <?php elseif ($step == 2): ?>
                <!-- Paso 2: Crear backup -->
                <h4><i class="fas fa-database"></i> Paso 2: Backup de Seguridad</h4>
                
                <div class="warning-box">
                    <h5><i class="fas fa-info-circle"></i> Backup Automático</h5>
                    <p>Antes de proceder con el reset, crearemos un backup completo de todos los datos actuales.</p>
                    <p>Este backup se guardará en la carpeta de backups y podrá ser restaurado si es necesario.</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-download fa-4x text-primary mb-3"></i>
                        <h5>Backup de Seguridad</h5>
                        <p>Se creará un backup completo antes de limpiar la base de datos</p>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="reset_system.php?step=1" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download"></i> Crear Backup
                        </button>
                    </div>
                </form>
            
            <?php elseif ($step == 3): ?>
                <!-- Paso 3: Confirmación final -->
                <h4><i class="fas fa-exclamation-triangle"></i> Paso 3: Confirmación Final</h4>
                
                <div class="danger-box">
                    <h5><i class="fas fa-skull-crossbones"></i> ÚLTIMA ADVERTENCIA</h5>
                    <p>Está a punto de eliminar permanentemente todos los datos del sistema.</p>
                    <p><strong>Esta acción NO se puede deshacer.</strong></p>
                    
                    <?php if (isset($_SESSION['backup_created'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check"></i> Backup creado: <?php echo $_SESSION['backup_created']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="execute_reset">
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmación Final</label>
                        <input type="text" class="form-control" name="confirmation" required 
                               placeholder="Escribe RESET_COMPLETO para confirmar">
                        <small class="text-muted">Debe escribir exactamente "RESET_COMPLETO" para continuar</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmUnderstand" required>
                            <label class="form-check-label" for="confirmUnderstand">
                                Entiendo que esta acción eliminará permanentemente todos los datos y no se puede deshacer
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="reset_system.php?step=2" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Atrás
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Ejecutar Reset Completo
                        </button>
                    </div>
                </form>
            
            <?php elseif ($step == 4): ?>
                <!-- Paso 4: Reset completado -->
                <h4><i class="fas fa-check-circle"></i> Reset Completado</h4>
                
                <div class="alert alert-success">
                    <h5><i class="fas fa-check"></i> ¡Proceso Completado Exitosamente!</h5>
                    <p>El sistema ha sido reseteado y todos los datos han sido eliminados.</p>
                </div>
                
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5>Sistema Reiniciado</h5>
                    <p>La base de datos está limpia y lista para uso</p>
                </div>
                
                <div class="warning-box">
                    <h5><i class="fas fa-info-circle"></i> Próximos Pasos Recomendados</h5>
                    <ol>
                        <li>Cambiar la contraseña del administrador</li>
                        <li>Crear nuevos empleados</li>
                        <li>Registrar productos</li>
                        <li>Ingresar lotes de inventario</li>
                        <li>Abrir caja inicial</li>
                    </ol>
                </div>
                
                <div class="d-flex justify-content-center">
                    <a href="dashboard_mysql.php" class="btn btn-success btn-lg">
                        <i class="fas fa-home"></i> Ir al Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
