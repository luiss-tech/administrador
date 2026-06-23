<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$empleado_id = $_SESSION['usuario']['id'];
$usuario_rol = $_SESSION['usuario']['rol'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener caja abierta actual
$stmt = $db->prepare("
    SELECT * FROM cajas 
    WHERE estado = 'abierta' 
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt->execute();
$caja_actual = $stmt->fetch();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ABRIR CAJA
    if ($action === 'abrir') {
        if ($caja_actual) {
            $mensaje = 'Ya existe una caja abierta';
            $tipo_mensaje = 'warning';
        } else {
            $monto = floatval($_POST['monto_apertura'] ?? 0);
            $stmt = $db->prepare("INSERT INTO cajas (empleado_id, monto_apertura) VALUES (?, ?)");
            $stmt->execute([$empleado_id, $monto]);
            $mensaje = 'Caja abierta correctamente';
            $tipo_mensaje = 'success';
            // Recargar caja actual
            $caja_actual = $db->query("SELECT * FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1")->fetch();
        }
    }
    
    // REGISTRAR MOVIMIENTO (INGRESO/RETIRO)
    elseif ($action === 'movimiento' && $caja_actual) {
        $tipo = $_POST['tipo'] ?? 'ingreso';
        $monto = floatval($_POST['monto'] ?? 0);
        $concepto = $_POST['concepto'] ?? '';
        
        if ($monto > 0 && !empty($concepto)) {
            $stmt = $db->prepare("
                INSERT INTO movimientos_caja (caja_id, tipo, monto, concepto, empleado_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$caja_actual['id'], $tipo, $monto, $concepto, $empleado_id]);
            
            // Actualizar totales en caja
            if ($tipo === 'ingreso') {
                $db->prepare("UPDATE cajas SET total_ingresos = total_ingresos + ? WHERE id = ?")
                   ->execute([$monto, $caja_actual['id']]);
            } else {
                $db->prepare("UPDATE cajas SET total_retiros = total_retiros + ? WHERE id = ?")
                   ->execute([$monto, $caja_actual['id']]);
            }
            
            $mensaje = ucfirst($tipo) . ' registrado correctamente';
            $tipo_mensaje = 'success';
            // Recargar caja actual para actualizar datos en pantalla
            $stmt = $db->prepare("SELECT * FROM cajas WHERE id = ?");
            $stmt->execute([$caja_actual['id']]);
            $caja_actual = $stmt->fetch();
        }
    }
    
    // CERRAR CAJA
    elseif ($action === 'cerrar' && $caja_actual) {
        $monto_cierre = floatval($_POST['monto_cierre'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';
        
        // Calcular totales de ventas
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END), 0) as ventas_efectivo,
                COALESCE(SUM(CASE WHEN metodo_pago = 'tarjeta' THEN total ELSE 0 END), 0) as ventas_tarjeta,
                COALESCE(SUM(CASE WHEN metodo_pago = 'yape' THEN total ELSE 0 END), 0) as ventas_yape,
                COALESCE(SUM(CASE WHEN metodo_pago = 'transferencia' THEN total ELSE 0 END), 0) as ventas_transferencia
            FROM ventas 
            WHERE caja_id = ?
        ");
        $stmt->execute([$caja_actual['id']]);
        $totales = $stmt->fetch();
        
        // Calcular efectivo esperado
        $efectivo_esperado = $caja_actual['monto_apertura'] 
                           + $totales['ventas_efectivo'] 
                           + $caja_actual['total_ingresos'] 
                           - $caja_actual['total_retiros'];
        $diferencia = $monto_cierre - $efectivo_esperado;
        
        $stmt = $db->prepare("
            UPDATE cajas 
            SET estado = 'cerrada',
                fecha_cierre = NOW(),
                empleado_cierre_id = ?,
                ventas_efectivo = ?,
                ventas_tarjeta = ?,
                ventas_yape = ?,
                ventas_transferencia = ?,
                total_ventas = ?,
                total_ingresos = ?,
                total_retiros = ?,
                monto_cierre = ?,
                efectivo_esperado = ?,
                tipo_cierre = 'manual',
                observaciones = CONCAT(IFNULL(observaciones, ''), ' ', ?)
            WHERE id = ?
        ");
        $stmt->execute([
            $empleado_id,
            $totales['ventas_efectivo'], 
            $totales['ventas_tarjeta'],
            $totales['ventas_yape'],
            $totales['ventas_transferencia'],
            $diferencia,
            $caja_actual['total_ingresos'],
            $caja_actual['total_retiros'],
            $monto_cierre,
            $efectivo_esperado,
            $observaciones,
            $caja_actual['id']
        ]);
        
        $mensaje = 'Caja cerrada manualmente. Diferencia: $' . number_format($diferencia, 2);
        $tipo_mensaje = $diferencia == 0 ? 'success' : ($diferencia > 0 ? 'info' : 'warning');
        $caja_actual = null;
    }
    
    // CERRAR CAJA FORZADO (por admin o cambio de turno)
    elseif ($action === 'cerrar_forzado') {
        require_once 'caja_utils.php';
        $motivo = $_POST['motivo'] ?? 'Cierre forzado';
        
        $caja_estado = verificarCajaAbierta();
        if ($caja_estado['abierta']) {
            if (cerrarCajaForzada($caja_estado['caja_id'], $empleado_id, $motivo)) {
                $mensaje = 'Caja cerrada forzosamente. Se ha registrado el evento.';
                $tipo_mensaje = 'warning';
                $caja_actual = null;
                $alerta_caja = null;
            } else {
                $mensaje = 'Error al cerrar la caja forzosamente.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Calcular saldo actual si hay caja abierta
$saldo_actual = 0;
$movimientos = [];
$totales_ventas = ['ventas_efectivo' => 0, 'ventas_tarjeta' => 0, 'ventas_yape' => 0, 'ventas_transferencia' => 0];

// Solo obtener datos de saldo si hay caja abierta
if ($caja_actual) {
    // Calcular totales de ventas
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END), 0) as ventas_efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago = 'tarjeta' THEN total ELSE 0 END), 0) as ventas_tarjeta,
            COALESCE(SUM(CASE WHEN metodo_pago = 'yape' THEN total ELSE 0 END), 0) as ventas_yape,
            COALESCE(SUM(CASE WHEN metodo_pago = 'transferencia' THEN total ELSE 0 END), 0) as ventas_transferencia
        FROM ventas 
        WHERE caja_id = ?
    ");
    $stmt->execute([$caja_actual['id']]);
    $totales_ventas = $stmt->fetch();
    
    // Calcular saldo
    $saldo_actual = $caja_actual['monto_apertura'] 
                  + $totales_ventas['ventas_efectivo'] 
                  + $caja_actual['total_ingresos'] 
                  - $caja_actual['total_retiros'];
}

// Obtener movimientos según rol
if ($usuario_rol === 'administrador') {
    // Admin ve TODOS los movimientos de TODAS las cajas
    $stmt = $db->query("
        SELECT m.*, e.nombre as empleado_nombre, c.estado as caja_estado, c.fecha_apertura
        FROM movimientos_caja m
        JOIN empleados e ON m.empleado_id = e.id
        JOIN cajas c ON m.caja_id = c.id
        ORDER BY m.fecha DESC
        LIMIT 10
    ");
    $movimientos = $stmt->fetchAll();
} else {
    // Empleado solo ve movimientos de la caja actual (si hay abierta)
    if ($caja_actual) {
        $stmt = $db->prepare("
            SELECT m.*, e.nombre as empleado_nombre
            FROM movimientos_caja m
            JOIN empleados e ON m.empleado_id = e.id
            WHERE m.caja_id = ? AND m.empleado_id = ?
            ORDER BY m.fecha DESC
            LIMIT 10
        ");
        $stmt->execute([$caja_actual['id'], $empleado_id]);
        $movimientos = $stmt->fetchAll();
    }
}

// Obtener historial de cajas cerradas (solo para administradores)
$historial_cajas = [];
if ($usuario_rol === 'administrador') {
    $stmt = $db->query("
        SELECT c.*, 
               e.nombre as empleado_nombre,
               e_cierre.nombre as empleado_cierre_nombre
        FROM cajas c
        JOIN empleados e ON c.empleado_id = e.id
        LEFT JOIN empleados e_cierre ON c.empleado_cierre_id = e_cierre.id
        WHERE c.estado = 'cerrada'
        ORDER BY c.fecha_cierre DESC
        LIMIT 10
    ");
    $historial_cajas = $stmt->fetchAll();
}

// Verificar si hay alerta de caja de otro usuario (viene de auth.php o session)
$alerta_caja = null;
require_once 'caja_utils.php';
$caja_estado = verificarCajaAbierta();

if (isset($_GET['alerta']) && $_GET['alerta'] === 'caja_otro_usuario') {
    // Solo mostrar alerta si hay caja abierta Y pertenece a otro usuario
    if ($caja_estado['abierta'] && $caja_estado['empleado_id'] != $empleado_id) {
        $alerta_caja = $caja_estado;
    }
}
// También verificar si hay alerta en sesión (para admins)
elseif (isset($_SESSION['caja_alerta']) && $_SESSION['caja_alerta']['abierta']) {
    // Verificar si la caja de la sesión sigue abierta
    $caja_sesion = $_SESSION['caja_alerta'];
    if ($caja_estado['abierta'] && $caja_estado['caja_id'] == $caja_sesion['caja_id']) {
        // La caja sigue abierta, mostrar alerta si es de otro usuario
        if ($caja_estado['empleado_id'] != $empleado_id) {
            $alerta_caja = $caja_estado;
        }
    } else {
        // La caja ya se cerró, limpiar la sesión
        unset($_SESSION['caja_alerta']);
    }
}

// Procesar descarte manual de alerta
if (isset($_GET['descartar_alerta'])) {
    unset($_SESSION['caja_alerta']);
    $alerta_caja = null;
    // Redirigir para limpiar URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Caja - Sistema de Gestión</title>
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
        .table-scrollable {
            max-height: 400px;
            overflow-y: auto;
        }
        .table-scrollable thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
        }
        .saldo-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cash-register text-success"></i> Control de Caja</h1>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($alerta_caja && $alerta_caja['abierta']): ?>
                    <!-- ALERTA: Caja abierta por otro usuario -->
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Caja abierta por otro usuario</h5>
                        <p class="mb-2">
                            <strong><?php echo htmlspecialchars($alerta_caja['empleado_nombre']); ?></strong> 
                            abrió la caja el <?php echo date('d/m/Y', strtotime($alerta_caja['fecha_apertura'])); ?> 
                            a las <?php echo date('H:i', strtotime($alerta_caja['fecha_apertura'])); ?>.
                        </p>
                        <p class="mb-2">
                            <?php if ($usuario['rol'] === 'administrador'): ?>
                                Como administrador, puedes continuar o cerrar esta caja.
                                <button class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalCerrarForzado">
                                    <i class="fas fa-exclamation-triangle"></i> Cerrar Caja (Forzado)
                                </button>
                            <?php else: ?>
                                Debes cerrar esta caja antes de continuar.
                                <button class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalCerrarForzado">
                                    <i class="fas fa-door-closed"></i> Cerrar Caja
                                </button>
                            <?php endif; ?>
                        </p>
                        <p class="mb-0 text-end">
                            <small class="text-muted">
                                <a href="?descartar_alerta=1" class="text-muted"><i class="fas fa-times"></i> Descartar alerta</a>
                            </small>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!$caja_actual): ?>
                    <!-- CAJA CERRADA - Mostrar botón para abrir -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-store-slash fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">Caja Cerrada</h3>
                            <p class="text-muted">No hay una caja abierta actualmente</p>
                            <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalAbrir">
                                <i class="fas fa-door-open"></i> Abrir Caja
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($caja_actual): ?>
                    <!-- CAJA ABIERTA - Mostrar saldo y controles -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="saldo-box">
                                <h6 class="text-white-50 mb-2">Efectivo en Caja</h6>
                                <h1 class="mb-0">$<?php echo number_format($saldo_actual, 2); ?></h1>
                                <small class="text-white-50">
                                    Apertura: $<?php echo number_format($caja_actual['monto_apertura'], 2); ?> | 
                                    <?php echo date('d/m/Y H:i', strtotime($caja_actual['fecha_apertura'])); ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                                        <h5>$<?php echo number_format($totales_ventas['ventas_efectivo'], 2); ?></h5>
                                        <small class="text-muted">Ventas Efectivo</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                                        <h5>$<?php echo number_format($totales_ventas['ventas_tarjeta'], 2); ?></h5>
                                        <small class="text-muted">Ventas Tarjeta</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-mobile-alt fa-2x text-purple mb-2" style="color: #7c3aed;"></i>
                                        <h5>$<?php echo number_format($totales_ventas['ventas_yape'], 2); ?></h5>
                                        <small class="text-muted">Ventas Yape</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-exchange-alt fa-2x text-secondary mb-2"></i>
                                        <h5>$<?php echo number_format($totales_ventas['ventas_transferencia'], 2); ?></h5>
                                        <small class="text-muted">Transferencias</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-arrow-down fa-2x text-info mb-2"></i>
                                        <h5>$<?php echo number_format($caja_actual['total_ingresos'], 2); ?></h5>
                                        <small class="text-muted">Ingresos</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card text-center">
                                        <i class="fas fa-arrow-up fa-2x text-warning mb-2"></i>
                                        <h5>$<?php echo number_format($caja_actual['total_retiros'], 2); ?></h5>
                                        <small class="text-muted">Retiros</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="mb-4">
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalIngreso">
                            <i class="fas fa-plus-circle"></i> Registrar Ingreso
                        </button>
                        <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#modalRetiro">
                            <i class="fas fa-minus-circle"></i> Registrar Retiro
                        </button>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalCerrar">
                            <i class="fas fa-door-closed"></i> Cerrar Caja
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($usuario_rol === 'administrador' || $caja_actual): ?>
                <!-- Movimientos de Caja -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Movimientos de Caja <?php if ($usuario_rol !== 'administrador') echo '(Mis movimientos)'; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($movimientos)): ?>
                                <p class="text-muted text-center">No hay movimientos registrados</p>
                            <?php else: ?>
                                <div class="table-responsive table-scrollable">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <?php if ($usuario_rol === 'administrador'): ?>
                                                <th>Caja</th>
                                                <?php endif; ?>
                                                <th>Tipo</th>
                                                <th>Concepto</th>
                                                <th>Monto</th>
                                                <?php if ($usuario_rol === 'administrador'): ?>
                                                <th>Empleado</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimientos as $m): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                                                    <?php if ($usuario_rol === 'administrador'): ?>
                                                    <td><span class="badge bg-<?php echo $m['caja_estado'] === 'abierta' ? 'success' : 'secondary'; ?>">#<?php echo $m['caja_id']; ?></span></td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <span class="badge bg-<?php echo $m['tipo'] === 'ingreso' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($m['tipo']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($m['concepto']); ?></td>
                                                    <td class="fw-bold">$<?php echo number_format($m['monto'], 2); ?></td>
                                                    <?php if ($usuario_rol === 'administrador'): ?>
                                                    <td><?php echo htmlspecialchars($m['empleado_nombre']); ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($usuario_rol === 'administrador'): ?>
                <!-- Historial de Cajas (Solo Administradores) -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Historial de Cajas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial_cajas)): ?>
                            <p class="text-muted text-center">No hay cajas cerradas en el historial</p>
                        <?php else: ?>
                            <div class="table-responsive table-scrollable">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Apertura</th>
                                            <th>Cierre</th>
                                            <th>Tipo</th>
                                            <th>Efectivo Ventas</th>
                                            <th>Otros Métodos</th>
                                            <th>Cierre Real</th>
                                            <th>Diferencia</th>
                                            <th>Abierta Por</th>
                                            <th>Cerrada Por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial_cajas as $c): 
                                            $tipo_cierre_label = [
                                                'manual' => ['label' => 'Manual', 'class' => 'success'],
                                                'automatico' => ['label' => 'Auto', 'class' => 'warning'],
                                                'forzado' => ['label' => 'Forzado', 'class' => 'danger']
                                            ][$c['tipo_cierre']] ?? ['label' => 'N/A', 'class' => 'secondary'];
                                            $otros_metodos = $c['ventas_tarjeta'] + $c['ventas_yape'] + $c['ventas_transferencia'];
                                        ?>
                                            <tr>
                                                <td><?php echo $c['id']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_apertura'])); ?></td>
                                                <td><?php echo $c['fecha_cierre'] ? date('d/m/Y H:i', strtotime($c['fecha_cierre'])) : '-'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $tipo_cierre_label['class']; ?>">
                                                        <?php echo $tipo_cierre_label['label']; ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($c['ventas_efectivo'], 2); ?></td>
                                                <td>$<?php echo number_format($otros_metodos, 2); ?></td>
                                                <td><?php echo $c['monto_cierre'] ? '$' . number_format($c['monto_cierre'], 2) : '-'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($c['diferencia'] === null) ? 'secondary' : ($c['diferencia'] == 0 ? 'success' : ($c['diferencia'] > 0 ? 'info' : 'danger')); ?>">
                                                        <?php echo ($c['diferencia'] === null) ? 'N/A' : '$' . number_format($c['diferencia'], 2); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($c['empleado_nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($c['empleado_cierre_nombre'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal Abrir Caja -->
    <div class="modal fade" id="modalAbrir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-door-open"></i> Abrir Caja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="abrir">
                        <div class="mb-3">
                            <label class="form-label">Monto de Apertura (Efectivo en caja)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto_apertura" class="form-control form-control-lg" 
                                       step="0.01" min="0" required autofocus>
                            </div>
                            <small class="text-muted">Ingrese la cantidad de efectivo con la que inicia la caja</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Abrir Caja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ingreso -->
    <div class="modal fade" id="modalIngreso" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Registrar Ingreso</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="movimiento">
                        <input type="hidden" name="tipo" value="ingreso">
                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Concepto</label>
                            <input type="text" name="concepto" class="form-control" placeholder="Ej: Ingreso de cambio, pago de deuda..." required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Registrar Ingreso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Retiro -->
    <div class="modal fade" id="modalRetiro" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-minus-circle"></i> Registrar Retiro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="movimiento">
                        <input type="hidden" name="tipo" value="retiro">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Efectivo disponible: <strong>$<?php echo number_format($saldo_actual, 2); ?></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" class="form-control" step="0.01" min="0.01" max="<?php echo $saldo_actual; ?>" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Concepto</label>
                            <input type="text" name="concepto" class="form-control" placeholder="Ej: Pago a proveedor, retiro de cambio..." required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Registrar Retiro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cerrar Caja -->
    <div class="modal fade" id="modalCerrar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-door-closed"></i> Cerrar Caja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cerrar">
                        
                        <div class="alert alert-info mb-3">
                            <h6 class="mb-2">Resumen de la caja:</h6>
                            <div class="d-flex justify-content-between"><span>Apertura:</span> <strong>$<?php echo number_format($caja_actual['monto_apertura'], 2); ?></strong></div>
                            <div class="d-flex justify-content-between"><span>Ventas Efectivo:</span> <strong>$<?php echo number_format($totales_ventas['ventas_efectivo'], 2); ?></strong></div>
                            <div class="d-flex justify-content-between"><span>Ingresos:</span> <strong>$<?php echo number_format($caja_actual['total_ingresos'], 2); ?></strong></div>
                            <div class="d-flex justify-content-between"><span>Retiros:</span> <strong>$<?php echo number_format($caja_actual['total_retiros'], 2); ?></strong></div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between text-success"><span>Efectivo esperado:</span> <strong>$<?php echo number_format($saldo_actual, 2); ?></strong></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Monto de Cierre (Conteo físico)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto_cierre" class="form-control form-control-lg" 
                                       step="0.01" min="0" value="<?php echo $saldo_actual; ?>" required autofocus>
                            </div>
                            <small class="text-muted">Ingrese la cantidad de efectivo que hay físicamente en caja</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Cualquier anotación sobre el cierre..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Cerrar Caja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cierre Forzado -->
    <div class="modal fade" id="modalCerrarForzado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Cierre Forzado de Caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cerrar_forzado">
                        
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-circle"></i> 
                            <strong>Atención:</strong> Está cerrando la caja de otro usuario. 
                            Este cierre se marcará como "forzado" y quedará registrado en el sistema.
                        </div>
                        
                        <?php if ($alerta_caja): ?>
                        <div class="alert alert-info mb-3">
                            <h6 class="mb-2">Información de la caja:</h6>
                            <div class="d-flex justify-content-between"><span>Abierta por:</span> <strong><?php echo htmlspecialchars($alerta_caja['empleado_nombre']); ?></strong></div>
                            <div class="d-flex justify-content-between"><span>Fecha:</span> <strong><?php echo date('d/m/Y H:i', strtotime($alerta_caja['fecha_apertura'])); ?></strong></div>
                            <div class="d-flex justify-content-between"><span>Monto apertura:</span> <strong>$<?php echo number_format($alerta_caja['monto_apertura'], 2); ?></strong></div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Motivo del cierre forzado</label>
                            <textarea name="motivo" class="form-control" rows="2" placeholder="Ej: Cambio de turno, usuario no disponible, cierre de local..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Confirmar Cierre Forzado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="heartbeat.js"></script>
</body>
</html>
