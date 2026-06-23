<?php
require_once 'session_manager.php';
require_once 'config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Solo administradores pueden ver reportes
if ($_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: dashboard_mysql.php');
    exit();
}

$db = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Estadísticas
$stats = [];

// Ventas en período
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_ventas,
        COALESCE(SUM(total), 0) as total_ingresos,
        COALESCE(SUM(costo_total), 0) as total_costos,
        COALESCE(SUM(ganancia), 0) as total_ganancia,
        AVG(total) as promedio_venta
    FROM ventas 
    WHERE DATE(fecha) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats = $stmt->fetch();

// Margen de ganancia
$stats['margen'] = $stats['total_ingresos'] > 0 ? 
    round(($stats['total_ganancia'] / $stats['total_ingresos']) * 100, 2) : 0;

// Inversión total (compras)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(l.cantidad * l.precio_compra), 0) as inversion_total
    FROM lotes l
    WHERE DATE(l.fecha_ingreso) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats['inversion'] = $stmt->fetchColumn();

// INVERSIÓN Y VALOR DE INVENTARIO (global, no filtrado por fecha)
$stmt = $db->query("
    SELECT COALESCE(SUM(precio_compra * cantidad), 0) as inversion_total,
           COALESCE(SUM(precio_compra * cantidad_disponible), 0) as valor_inventario_costo,
           COALESCE(SUM(cantidad_disponible), 0) as unidades_stock
    FROM lotes 
    WHERE activo = 1
");
$inversion = $stmt->fetch();
$stats = array_merge($stats, $inversion);

// Valor de venta del inventario actual
$stmt = $db->query("
    SELECT COALESCE(SUM(v.precio_venta * v.stock_actual), 0) as valor_inventario_venta
    FROM vista_stock v
");
$stats['valor_inventario_venta'] = $stmt->fetchColumn();

// Productos más vendidos
$stmt = $db->prepare("
    SELECT 
        p.nombre, 
        p.codigo,
        SUM(dv.cantidad) as cantidad_vendida,
        SUM(dv.cantidad * dv.precio_venta) as total_ventas,
        SUM(dv.ganancia) as ganancia_producto
    FROM detalle_venta dv
    JOIN productos p ON dv.producto_id = p.id
    JOIN ventas v ON dv.venta_id = v.id
    WHERE DATE(v.fecha) BETWEEN ? AND ?
    GROUP BY dv.producto_id
    ORDER BY cantidad_vendida DESC
    LIMIT 10
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$top_productos = $stmt->fetchAll();

// Producto más rentable
$stmt = $db->prepare("
    SELECT 
        p.nombre,
        p.codigo,
        SUM(dv.ganancia) as ganancia_total,
        AVG(dv.precio_venta - dv.costo_unitario) as margen_unitario
    FROM detalle_venta dv
    JOIN productos p ON dv.producto_id = p.id
    JOIN ventas v ON dv.venta_id = v.id
    WHERE DATE(v.fecha) BETWEEN ? AND ?
    GROUP BY dv.producto_id
    ORDER BY ganancia_total DESC
    LIMIT 1
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$producto_mas_rentable = $stmt->fetch();

// Detalle de ventas
$stmt = $db->prepare("
    SELECT v.*, e.nombre as empleado_nombre
    FROM ventas v
    LEFT JOIN empleados e ON v.empleado_id = e.id
    WHERE DATE(v.fecha) BETWEEN ? AND ?
    ORDER BY v.fecha DESC
    LIMIT 100
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            border-left: 4px solid;
        }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar text-info"></i> Reportes y Estadísticas</h1>
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> Filtrar</button>
                                <a href="reportes_mysql.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- KPIs Principales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card success">
                            <h6 class="text-muted mb-2">Ingresos Totales</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['total_ingresos'], 2); ?></h3>
                            <small class="text-success"><?php echo $stats['total_ventas']; ?> ventas</small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card primary">
                            <h6 class="text-muted mb-2">Ganancia Neta</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['total_ganancia'], 2); ?></h3>
                            <small class="text-primary">Margen: <?php echo $stats['margen']; ?>%</small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card warning">
                            <h6 class="text-muted mb-2">Costos Totales</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['total_costos'], 2); ?></h3>
                            <small class="text-warning">Inversión: $<?php echo number_format($stats['inversion'], 2); ?></small>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card danger">
                            <h6 class="text-muted mb-2">Venta Promedio</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['promedio_venta'], 2); ?></h3>
                            <small class="text-danger">Por transacción</small>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de Inventario e Inversión -->
                <h5 class="mb-3"><i class="fas fa-warehouse text-primary"></i> Inventario e Inversión</h5>
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-2">Inversión Total</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['inversion_total'], 2); ?></h3>
                                    <small class="text-white-50">Todo el stock comprado</small>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-money-bill-wave fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-2">Valor Inventario (Costo)</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['valor_inventario_costo'], 2); ?></h3>
                                    <small class="text-white-50"><?php echo $stats['unidades_stock']; ?> unidades</small>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-warehouse fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-2">Valor Inventario (Venta)</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['valor_inventario_venta'], 2); ?></h3>
                                    <small class="text-white-50">Si todo se vende</small>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-tags fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-2">Ganancia Potencial</h6>
                                    <h3 class="mb-0">$<?php echo number_format($stats['valor_inventario_venta'] - $stats['valor_inventario_costo'], 2); ?></h3>
                                    <small class="text-white-50">ROI: <?php echo $stats['valor_inventario_costo'] > 0 ? round((($stats['valor_inventario_venta'] - $stats['valor_inventario_costo']) / $stats['valor_inventario_costo']) * 100, 1) : 0; ?>%</small>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chart-pie fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Producto más rentable -->
                <?php if ($producto_mas_rentable): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-crown"></i> <strong>Producto más rentable:</strong> 
                    <?php echo $producto_mas_rentable['nombre']; ?> - 
                    Ganancia: $<?php echo number_format($producto_mas_rentable['ganancia_total'], 2); ?>
                </div>
                <?php endif; ?>

                <!-- Top Productos -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Productos</h5>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($top_productos as $i => $p): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="badge bg-<?php echo $i < 3 ? 'success' : 'secondary'; ?> me-1"><?php echo $i+1; ?></span>
                                        <strong><?php echo $p['nombre']; ?></strong>
                                        <br><small class="text-muted"><?php echo $p['cantidad_vendida']; ?> vendidos</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-success fw-bold">+$<?php echo number_format($p['ganancia_producto'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabla de ventas -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Detalle de Ventas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N° Venta</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Costo</th>
                                        <th>Ganancia</th>
                                        <th>Margen</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventas as $v): 
                                        $margen = $v['total'] > 0 ? round(($v['ganancia'] / $v['total']) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $v['numero']; ?></strong></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($v['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($v['cliente']); ?></td>
                                            <td>$<?php echo number_format($v['total'], 2); ?></td>
                                            <td>$<?php echo number_format($v['costo_total'], 2); ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($v['ganancia'], 2); ?></td>
                                            <td><?php echo $margen; ?>%</td>
                                            <td>
                                                <span class="text-muted">
                                                    <i class="fas fa-file-invoice"></i> Sin Boleta
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
