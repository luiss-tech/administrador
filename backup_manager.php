<?php
require_once 'session_manager.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: index.php');
    exit();
}

require_once 'backup_utils.php';

$backup = new BackupManager();
$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $description = trim($_POST['description'] ?? '');
            $result = $backup->createBackup($description);
            
            if ($result['success']) {
                $message = "Backup '{$result['filename']}' creado correctamente ({$result['size']})";
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
            break;
            
        case 'delete':
            $filename = $_POST['filename'] ?? '';
            $result = $backup->deleteBackup($filename);
            
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
            break;
            
        case 'restore':
            $filename = $_POST['filename'] ?? '';
            $confirmacion = $_POST['confirmacion'] ?? '';
            
            // Verificación de seguridad adicional
            if ($confirmacion !== 'RESTAURAR') {
                $message = 'Error: Debes escribir RESTAURAR para confirmar';
                $messageType = 'danger';
                break;
            }
            
            // Verificar integridad antes de restaurar
            $verify = $backup->verifyBackup($filename);
            if (!$verify['valid']) {
                $message = 'Error: ' . $verify['message'];
                $messageType = 'danger';
                break;
            }
            
            $result = $backup->restoreBackup($filename);
            
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
            break;
    }
}

$backups = $backup->listBackups();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Backups - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 15px 20px;
            border-radius: 8px;
            margin: 5px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .backup-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .backup-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .backup-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }
        .backup-card:hover .backup-actions {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Dinámico -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-database"></i> Gestión de Backups</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBackup">
                        <i class="fas fa-plus"></i> Crear Backup
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php if (empty($backups)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-archive fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No hay backups disponibles</h4>
                                <p class="text-muted">Crea tu primer backup para proteger tus datos</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="backup-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="fas fa-file-archive text-primary"></i>
                                                <?php echo htmlspecialchars($backup['filename']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($backup['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($backup['created_by'] !== 'Sistema'): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($backup['created_by']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($backup['description']): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-comment"></i>
                                                <?php echo htmlspecialchars($backup['description']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    
                                    <div class="backup-actions">
                                        <div class="btn-group w-100" role="group">
                                            <a href="backups/<?php echo $backup['filename']; ?>" 
                                               class="btn btn-outline-primary" download>
                                                <i class="fas fa-download"></i> Descargar
                                            </a>
                                            <button class="btn btn-outline-warning" 
                                                    onclick="confirmarRestaurar('<?php echo $backup['filename']; ?>', '<?php echo date('d/m/Y H:i', strtotime($backup['created_at'])); ?>')">
                                                <i class="fas fa-undo"></i> Restaurar
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmarEliminar('<?php echo $backup['filename']; ?>')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Crear Backup -->
    <div class="modal fade" id="modalBackup" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" id="backupDescription" rows="3" 
                                  placeholder="Ej: Backup antes de actualizar precios"></textarea>
                        <small class="text-muted">
                            Describe el motivo de este backup para futura referencia
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="crearBackup()">
                        <i class="fas fa-save"></i> Crear Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Backup -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de eliminar este backup?</p>
                    <p class="text-muted">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="eliminarBackup()">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Restaurar Backup -->
    <div class="modal fade" id="modalRestaurar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Restaurar Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formRestaurar">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="filename" id="restoreFilename">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>¡Atención!</strong> Esta acción sobrescribirá todos los datos actuales.
                        </div>
                        
                        <p>Estás a punto de restaurar el backup:</p>
                        <p class="fw-bold text-primary" id="restoreBackupName"></p>
                        
                        <div class="alert alert-danger">
                            <small>
                                <strong>Advertencias:</strong>
                                <ul class="mb-0">
                                    <li>Todos los datos actuales serán reemplazados</li>
                                    <li>Las ventas, productos, lotes y movimientos actuales se perderán</li>
                                    <li>Esta acción NO se puede deshacer</li>
                                    <li>Se recomienda crear un backup actual antes de continuar</li>
                                </ul>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Para confirmar, escribe <strong>RESTAURAR</strong>:</label>
                            <input type="text" class="form-control" name="confirmacion" 
                                   placeholder="Escribe RESTAURAR aquí" required
                                   pattern="RESTAURAR" title="Debes escribir exactamente RESTAURAR">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo"></i> Sí, Restaurar Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        let filenameToDelete = '';
        
        function confirmarEliminar(filename) {
            filenameToDelete = filename;
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }
        
        function confirmarRestaurar(filename, fecha) {
            document.getElementById('restoreFilename').value = filename;
            document.getElementById('restoreBackupName').textContent = filename + ' (' + fecha + ')';
            new bootstrap.Modal(document.getElementById('modalRestaurar')).show();
        }
        
        function crearBackup() {
            const description = document.getElementById('backupDescription').value;
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('description', description);
            
            fetch('backup_utils.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('modalBackup')).hide();
                
                // Limpiar textarea
                document.getElementById('backupDescription').value = '';
                
                // Mostrar notificación personalizada
                if (data.success) {
                    const message = `Backup creado: ${data.filename} (${data.size})`;
                    showNotification('success', message);
                    // Recargar la página después de un corto tiempo para actualizar la lista
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error al crear el backup');
                bootstrap.Modal.getInstance(document.getElementById('modalBackup')).hide();
            });
        }
        
        function eliminarBackup() {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('filename', filenameToDelete);
            
            fetch('backup_utils.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
                
                // Mostrar notificación personalizada
                if (data.success) {
                    showNotification('success', data.message);
                    // Recargar la página después de un corto tiempo para actualizar la lista
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error al eliminar el backup');
                bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
            });
        }
        
        // Sistema de notificaciones (mismo que en configuracion_backup.php)
        function showNotification(type, message) {
            let notificationContainer = document.getElementById('notification-container');
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.id = 'notification-container';
                notificationContainer.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 400px;
                `;
                document.body.appendChild(notificationContainer);
            }
            
            const notification = document.createElement('div');
            const typeConfig = {
                success: {
                    bg: '#28a745',
                    icon: 'fa-check-circle',
                    title: 'Éxito'
                },
                error: {
                    bg: '#dc3545',
                    icon: 'fa-exclamation-triangle',
                    title: 'Error'
                },
                info: {
                    bg: '#17a2b8',
                    icon: 'fa-info-circle',
                    title: 'Información'
                },
                warning: {
                    bg: '#ffc107',
                    icon: 'fa-exclamation-triangle',
                    title: 'Advertencia'
                }
            };
            
            const config = typeConfig[type] || typeConfig.info;
            
            notification.style.cssText = `
                background: ${config.bg};
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                line-height: 1.4;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                cursor: pointer;
                max-width: 100%;
            `;
            
            notification.innerHTML = `
                <i class="fas ${config.icon}" style="font-size: 18px; flex-shrink: 0;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 2px;">${config.title}</div>
                    <div style="opacity: 0.9;">${message}</div>
                </div>
                <i class="fas fa-times" style="font-size: 14px; opacity: 0.7; flex-shrink: 0;"></i>
            `;
            
            notificationContainer.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            notification.addEventListener('click', () => {
                closeNotification(notification);
            });
            
            setTimeout(() => {
                closeNotification(notification);
            }, 5000);
        }
        
        function closeNotification(notification) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    </script>
</body>
</html>
