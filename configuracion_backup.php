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

// Obtener configuración actual
$configFile = __DIR__ . '/config/backup_config.json';
$config = [
    'backup_diario' => false,
    'backup_semanal' => false,
    'max_backups' => 10,
    'hora_backup_diario' => '23:59'
];

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: $config;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'save_config':
            $config = [
                'backup_diario' => isset($_POST['backup_diario']),
                'backup_semanal' => isset($_POST['backup_semanal']),
                'max_backups' => intval($_POST['max_backups']),
                'hora_backup_diario' => $_POST['hora_backup_diario']
            ];
            
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            
            $message = 'Configuración guardada correctamente';
            $messageType = 'success';
            break;
            
        case 'test_backup':
            $result = $backup->createBackup('Backup de prueba - Configuración');
            if ($result['success']) {
                $message = 'Backup de prueba creado correctamente';
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
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
    <title>Configuración de Backups - Sistema de Ventas</title>
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
        .config-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        .feature-icon {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 1rem;
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
                    <h2><i class="fas fa-cog"></i> Configuración de Backups</h2>
                    <a href="backup_manager.php" class="btn btn-outline-primary">
                        <i class="fas fa-database"></i> Ver Backups
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="config-card">
                            <h4 class="mb-4"><i class="fas fa-shield-alt text-primary"></i> Configuración Automática</h4>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="save_config">
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-calendar-day feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">Backup Diario</h5>
                                                <small class="text-muted">Crea un backup todos los días a una hora específica</small>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="backup_diario" 
                                                   id="backup_diario" <?php echo $config['backup_diario'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="backup_diario">
                                                Activar backup diario
                                            </label>
                                        </div>
                                        <div class="input-group">
                                            <input type="time" class="form-control" name="hora_backup_diario" 
                                                   value="<?php echo $config['hora_backup_diario']; ?>" 
                                                   <?php echo !$config['backup_diario'] ? 'disabled' : ''; ?>>
                                            <span class="input-group-text">Hora</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-calendar-week feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">Backup Semanal</h5>
                                                <small class="text-muted">Crea un backup una vez por semana</small>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="backup_semanal" 
                                                   id="backup_semanal" <?php echo $config['backup_semanal'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="backup_semanal">
                                                Activar backup semanal
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-archive feature-icon me-3"></i>
                                            <div>
                                                <h5 class="mb-1">Límite de Backups</h5>
                                                <small class="text-muted">Máximo número de backups a conservar</small>
                                            </div>
                                        </div>
                                        <input type="number" class="form-control" name="max_backups" 
                                               min="5" max="50" value="<?php echo $config['max_backups']; ?>">
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Guardar Configuración
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="testBackup()">
                                        <i class="fas fa-play"></i> Probar Backup
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="config-card">
                            <h5 class="mb-3"><i class="fas fa-info-circle text-info"></i> Información</h5>
                                                        
                            <div class="alert alert-warning">
                                <h6>⚠️ Importante</h6>
                                <p class="mb-0">Los backups automáticos ocupan espacio en el servidor. Ajusta el límite según tus necesidades.</p>
                            </div>
                        </div>
                        
                        <div class="config-card">
                            <h5 class="mb-3"><i class="fas fa-chart-line text-success"></i> Estadísticas</h5>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h3 class="text-primary"><?php echo count($backup->listBackups()); ?></h3>
                                        <small class="text-muted">Backups totales</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h3 class="text-success"><?php echo $config['max_backups']; ?></h3>
                                        <small class="text-muted">Límite actual</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Habilitar/deshabilitar hora según backup diario
        document.getElementById('backup_diario').addEventListener('change', function() {
            document.querySelector('input[name="hora_backup_diario"]').disabled = !this.checked;
        });
        
        // Función para probar backup automático
        function testBackup() {
            const formData = new FormData();
            formData.append('action', 'test_backup');
            
            fetch('backup_utils.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin', // Incluir cookies de sesión
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error de conexión al probar backup');
            });
        }
        
        // Sistema de notificaciones personalizadas
        function showNotification(type, message) {
            // Crear contenedor de notificaciones si no existe
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
            
            // Crear notificación
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
            
            // Agregar al contenedor
            notificationContainer.appendChild(notification);
            
            // Animar entrada
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Cerrar al hacer clic
            notification.addEventListener('click', () => {
                closeNotification(notification);
            });
            
            // Cerrar automáticamente después de 5 segundos
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
