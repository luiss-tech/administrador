<?php
require_once 'session_manager.php';
require_once 'config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Solo administradores pueden acceder al dashboard completo
if ($_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: ventas_mysql.php');
    exit();
}

$db = getDB();
$usuario = $_SESSION['usuario'];

// Estadísticas principales
$stats = [];

// Total ventas del día
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as ventas_dia, COALESCE(SUM(ganancia), 0) as ganancia_dia, COUNT(*) as num_ventas FROM ventas WHERE DATE(fecha) = CURDATE()");
$stmt->execute();
$stats = array_merge($stats, $stmt->fetch());

// Total ventas del mes
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as ventas_mes, COALESCE(SUM(ganancia), 0) as ganancia_mes FROM ventas WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())");
$stmt->execute();
$stats = array_merge($stats, $stmt->fetch());

// Total productos y stock bajo
$stmt = $db->query("SELECT COUNT(*) as total_productos FROM productos WHERE activo = 1");
$stats['total_productos'] = $stmt->fetchColumn();

// Productos con stock bajo (vista_stock)
$stmt = $db->query("SELECT COUNT(*) as stock_bajo FROM vista_stock WHERE estado_stock = 'bajo'");
$stats['stock_bajo'] = $stmt->fetchColumn();

// ALERTAS
$alertas = [];

// Productos vencidos
$stmt = $db->query("SELECT * FROM vista_vencimientos WHERE estado_vencimiento = 'vencido' ORDER BY fecha_vencimiento ASC LIMIT 10");
$alertas['vencidos'] = $stmt->fetchAll();

// Productos próximos a vencer (7 días)
$stmt = $db->query("SELECT * FROM vista_vencimientos WHERE estado_vencimiento = 'critico' ORDER BY fecha_vencimiento ASC LIMIT 10");
$alertas['proximos_vencer'] = $stmt->fetchAll();

// Productos con stock bajo (detalle)
$stmt = $db->query("SELECT * FROM vista_stock WHERE estado_stock = 'bajo' ORDER BY stock_actual ASC LIMIT 10");
$alertas['stock_bajo'] = $stmt->fetchAll();

// Top productos más vendidos hoy
$stmt = $db->prepare("
    SELECT p.nombre, p.codigo, SUM(dv.cantidad) as cantidad_vendida, SUM(dv.ganancia) as ganancia
    FROM detalle_venta dv
    JOIN productos p ON dv.producto_id = p.id
    JOIN ventas v ON dv.venta_id = v.id
    WHERE DATE(v.fecha) = CURDATE()
    GROUP BY dv.producto_id
    ORDER BY cantidad_vendida DESC
    LIMIT 5
");
$stmt->execute();
$top_productos = $stmt->fetchAll();

// Últimas ventas
$stmt = $db->query("SELECT * FROM ventas ORDER BY fecha DESC LIMIT 5");
$ultimas_ventas = $stmt->fetchAll();

// Estado de caja actual
$stmt = $db->query("
    SELECT c.*, e.nombre as empleado_nombre,
           (c.monto_apertura + COALESCE(v.ventas_efectivo, 0) + c.total_ingresos - c.total_retiros) as saldo_actual
    FROM cajas c
    JOIN empleados e ON c.empleado_id = e.id
    LEFT JOIN (
        SELECT caja_id, SUM(total) as ventas_efectivo 
        FROM ventas 
        WHERE metodo_pago = 'efectivo'
        GROUP BY caja_id
    ) v ON c.id = v.caja_id
    WHERE c.estado = 'abierta'
    ORDER BY c.id DESC
    LIMIT 1
");
$caja_actual = $stmt->fetch();

// Verificar si hay caja abierta por otro usuario (para admins y empleados)
$alerta_caja = null;
require_once 'caja_utils.php';
$caja_estado = verificarCajaAbierta();

// Si hay alerta en sesión, verificar que la caja siga abierta
if (isset($_SESSION['caja_alerta']) && $_SESSION['caja_alerta']['abierta']) {
    $caja_sesion = $_SESSION['caja_alerta'];
    // Verificar si la caja de la sesión sigue abierta
    if ($caja_estado['abierta'] && $caja_estado['caja_id'] == $caja_sesion['caja_id']) {
        // La caja sigue abierta y es de otro usuario
        if ($caja_estado['empleado_id'] != $usuario['id']) {
            $alerta_caja = $caja_estado;
        }
    } else {
        // La caja ya se cerró, limpiar la sesión
        unset($_SESSION['caja_alerta']);
    }
}
// Si no hay alerta en sesión pero hay caja abierta de otro, guardarla
elseif ($caja_estado['abierta'] && $caja_estado['empleado_id'] != $usuario['id']) {
    $alerta_caja = $caja_estado;
    $_SESSION['caja_alerta'] = $caja_estado;
}

// Procesar descarte de alerta
if (isset($_GET['descartar_alerta'])) {
    unset($_SESSION['caja_alerta']);
    $alerta_caja = null;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gestión</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-icon { font-size: 2.5rem; opacity: 0.8; }
        .alert-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .alert-vencido { background: #f8d7da; border-left: 4px solid #dc3545; }
        .alert-critico { background: #fff3cd; border-left: 4px solid #ffc107; }
        .alert-stock { background: #cce5ff; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Dinámico -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                
                <?php if ($alerta_caja && $alerta_caja['abierta']): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Caja Abierta por Otro Usuario</h5>
                        <p class="mb-2">
                            <strong><?php echo htmlspecialchars($alerta_caja['empleado_nombre']); ?></strong> 
                            abrió la caja el <?php echo date('d/m/Y', strtotime($alerta_caja['fecha_apertura'])); ?> 
                            a las <?php echo date('H:i', strtotime($alerta_caja['fecha_apertura'])); ?>.
                        </p>
                        <p class="mb-2">
                            <?php if ($usuario['rol'] === 'administrador'): ?>
                                Como administrador, puedes continuar trabajando o 
                                <a href="caja.php" class="alert-link">ir al control de caja</a> para revisar.
                            <?php else: ?>
                                Debes ir al <a href="caja.php" class="alert-link">control de caja</a> para cerrarla antes de continuar.
                            <?php endif; ?>
                        </p>
                        <p class="mb-0 text-end">
                            <small class="text-muted">
                                <a href="?descartar_alerta=1" class="text-muted"><i class="fas fa-times"></i> Descartar alerta</a>
                            </small>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h1>
                    <span class="text-muted"><?php echo date('d/m/Y H:i'); ?></span>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Ventas Hoy</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['ventas_dia'], 2); ?></h3>
                                    <small class="text-success"><?php echo $stats['num_ventas']; ?> ventas</small>
                                </div>
                                <div class="stat-icon text-success">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card primary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Ganancia Hoy</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['ganancia_dia'], 2); ?></h3>
                                    <small class="text-primary">Margen: <?php echo $stats['ventas_dia'] > 0 ? round(($stats['ganancia_dia']/$stats['ventas_dia'])*100, 1) : 0; ?>%</small>
                                </div>
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Productos</h6>
                                    <h3 class="mb-0"><?php echo $stats['total_productos']; ?></h3>
                                    <small class="text-warning"><?php echo $stats['stock_bajo']; ?> con stock bajo</small>
                                </div>
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card <?php echo $caja_actual ? 'success' : 'danger'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">
                                        <i class="fas fa-cash-register"></i> 
                                        <?php echo $caja_actual ? 'Efectivo en Caja' : 'Caja Cerrada'; ?>
                                    </h6>
                                    <?php if ($caja_actual): ?>
                                        <h3 class="mb-0">$<?php echo number_format($caja_actual['saldo_actual'], 2); ?></h3>
                                        <small class="text-success">
                                            Abierta: <?php echo date('H:i', strtotime($caja_actual['fecha_apertura'])); ?> | 
                                            <?php echo htmlspecialchars($caja_actual['empleado_nombre']); ?>
                                        </small>
                                    <?php else: ?>
                                        <h3 class="mb-0">--</h3>
                                        <small class="text-danger">
                                            <a href="caja.php" class="alert-link">Abrir caja para vender</a>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-icon <?php echo $caja_actual ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fas fa-<?php echo $caja_actual ? 'cash-register' : 'store-slash'; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Alertas -->
                    <div class="col-lg-4">
                        <h4 class="mb-3"><i class="fas fa-bell text-warning"></i> Alertas</h4>
                        
                        <?php if (count($alertas['vencidos']) > 0): ?>
                            <h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Vencidos (<?php echo count($alertas['vencidos']); ?>)</h6>
                            <?php foreach ($alertas['vencidos'] as $item): ?>
                                <div class="alert-card alert-vencido">
                                    <strong><?php echo $item['nombre']; ?></strong>
                                    <br><small>Lote venció el <?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></small>
                                    <br><span class="badge bg-danger"><?php echo $item['cantidad_disponible']; ?> unidades</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (count($alertas['proximos_vencer']) > 0): ?>
                            <h6 class="text-warning mt-3"><i class="fas fa-clock"></i> Próximos a vencer (<?php echo count($alertas['proximos_vencer']); ?>)</h6>
                            <?php foreach ($alertas['proximos_vencer'] as $item): ?>
                                <div class="alert-card alert-critico">
                                    <strong><?php echo $item['nombre']; ?></strong>
                                    <br><small>Vence en <?php echo $item['dias_para_vencer']; ?> días</small>
                                    <br><span class="badge bg-warning text-dark"><?php echo $item['cantidad_disponible']; ?> unidades</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (count($alertas['stock_bajo']) > 0): ?>
                            <h6 class="text-primary mt-3"><i class="fas fa-box-open"></i> Stock bajo (<?php echo count($alertas['stock_bajo']); ?>)</h6>
                            <?php foreach ($alertas['stock_bajo'] as $item): ?>
                                <div class="alert-card alert-stock">
                                    <strong><?php echo $item['nombre']; ?></strong>
                                    <br><small>Stock: <?php echo $item['stock_actual']; ?> / Mín: <?php echo $item['stock_minimo']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (empty($alertas['vencidos']) && empty($alertas['proximos_vencer']) && empty($alertas['stock_bajo'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> No hay alertas pendientes
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Top Productos -->
                    <div class="col-lg-4">
                        <h4 class="mb-3"><i class="fas fa-trophy text-warning"></i> Más Vendidos Hoy</h4>
                        <?php if (count($top_productos) > 0): ?>
                            <?php foreach ($top_productos as $i => $p): ?>
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php echo $i < 3 ? 'success' : 'secondary'; ?> me-2">#<?php echo $i+1; ?></span>
                                                <strong><?php echo $p['nombre']; ?></strong>
                                            </div>
                                            <span class="badge bg-primary"><?php echo $p['cantidad_vendida']; ?></span>
                                        </div>
                                        <small class="text-muted">Ganancia: $<?php echo number_format($p['ganancia'], 2); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay ventas hoy</p>
                        <?php endif; ?>
                    </div>

                    <!-- Últimas Ventas -->
                    <div class="col-lg-4">
                        <h4 class="mb-3"><i class="fas fa-list text-info"></i> Últimas Ventas</h4>
                        <?php if (count($ultimas_ventas) > 0): ?>
                            <?php foreach ($ultimas_ventas as $v): ?>
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo $v['numero']; ?></strong>
                                                <br><small class="text-muted"><?php echo $v['cliente']; ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong>$<?php echo number_format($v['total'], 2); ?></strong>
                                                <br><small class="text-success">+$<?php echo number_format($v['ganancia'], 2); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay ventas registradas</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h4 class="mb-3">Acciones Rápidas</h4>
                        <a href="ventas_mysql.php" class="btn btn-success btn-lg me-2">
                            <i class="fas fa-shopping-cart"></i> Nueva Venta
                        </a>
                        <a href="ingresos.php" class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-plus-circle"></i> Ingresar Stock
                        </a>
                        <a href="reportes_mysql.php" class="btn btn-info btn-lg">
                            <i class="fas fa-chart-bar"></i> Ver Reportes
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script src="heartbeat.js"></script>
</body>
</html>
