<?php
require_once 'session_manager.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: index.php');
    exit();
}

require_once 'error_logger.php';

$logs = ErrorLogger::getRecentLogs(200);
$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'clear':
            ErrorLogger::clearOldLogs(0); // Limpiar todos
            $message = "Logs limpiados correctamente";
            $messageType = "success";
            $logs = []; // Actualizar lista
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs del Sistema - Sistema de Ventas</title>
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
        .log-entry {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #dee2e6;
            transition: all 0.2s;
        }
        .log-entry:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .log-error { border-left-color: #dc3545; }
        .log-warning { border-left-color: #ffc107; }
        .log-critical { border-left-color: #6f42c1; }
        .log-info { border-left-color: #17a2b8; }
        
        .log-content {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .log-filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
                    <h2><i class="fas fa-file-alt"></i> Logs del Sistema</h2>
                    <div>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Actualizar
                        </button>
                        <button class="btn btn-outline-danger" onclick="confirmarLimpiar()">
                            <i class="fas fa-trash"></i> Limpiar Logs
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="log-filters">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Nivel</label>
                            <select class="form-select" id="filterLevel" onchange="filtrarLogs()">
                                <option value="">Todos</option>
                                <option value="ERROR">Errores</option>
                                <option value="WARNING">Advertencias</option>
                                <option value="CRITICAL">Críticos</option>
                                <option value="INFO">Información</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="filterUser" placeholder="Filtrar por usuario" onkeyup="filtrarLogs()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="filterDate" onchange="filtrarLogs()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="filterSearch" placeholder="Buscar en mensajes" onkeyup="filtrarLogs()">
                        </div>
                    </div>
                </div>

                <!-- Logs -->
                <div id="logsContainer">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="text-muted">No hay logs recientes</h4>
                            <p class="text-muted">El sistema está funcionando correctamente</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry log-<?php echo strtolower($log['level']); ?>" 
                                 data-level="<?php echo $log['level']; ?>" 
                                 data-user="<?php echo htmlspecialchars($log['user']); ?>" 
                                 data-date="<?php echo date('Y-m-d', strtotime($log['timestamp'])); ?>"
                                 data-message="<?php echo htmlspecialchars($log['message']); ?>">
                                
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $log['level'] === 'ERROR' ? 'danger' : 
                                                ($log['level'] === 'WARNING' ? 'warning' : 
                                                ($log['level'] === 'CRITICAL' ? 'dark' : 'info')); 
                                        ?>">
                                            <?php echo $log['level']; ?>
                                        </span>
                                        <small class="text-muted ms-2">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $log['timestamp']; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($log['user']); ?>
                                            <span class="ms-2">
                                                <i class="fas fa-network-wired"></i>
                                                <?php echo htmlspecialchars($log['ip']); ?>
                                            </span>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="log-content">
                                    <strong><?php echo htmlspecialchars($log['message']); ?></strong>
                                    
                                    <?php if (!empty($log['context'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Contexto:</small>
                                            <pre class="mt-1 p-2 bg-light rounded" style="font-size: 0.8rem;">
                                                <?php echo htmlspecialchars(json_encode($log['context'], JSON_PRETTY_PRINT)); ?>
                                            </pre>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Limpiar Logs -->
    <div class="modal fade" id="modalLimpiar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Limpieza</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="clear">
                    <div class="modal-body">
                        <p>¿Estás seguro de eliminar todos los logs del sistema?</p>
                        <p class="text-muted">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Limpiar Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        function confirmarLimpiar() {
            new bootstrap.Modal(document.getElementById('modalLimpiar')).show();
        }

        function filtrarLogs() {
            const level = document.getElementById('filterLevel').value.toLowerCase();
            const user = document.getElementById('filterUser').value.toLowerCase();
            const date = document.getElementById('filterDate').value;
            const search = document.getElementById('filterSearch').value.toLowerCase();
            
            const logs = document.querySelectorAll('.log-entry');
            
            logs.forEach(log => {
                const logLevel = log.dataset.level.toLowerCase();
                const logUser = log.dataset.user.toLowerCase();
                const logDate = log.dataset.date;
                const logMessage = log.dataset.message.toLowerCase();
                
                const matchLevel = !level || logLevel.includes(level);
                const matchUser = !user || logUser.includes(user);
                const matchDate = !date || logDate === date;
                const matchSearch = !search || logMessage.includes(search);
                
                if (matchLevel && matchUser && matchDate && matchSearch) {
                    log.style.display = 'block';
                } else {
                    log.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
