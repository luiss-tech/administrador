<?php
session_start();
require_once 'config/database.php';

// Verificar si hay caja abierta antes de cerrar sesión
if (isset($_SESSION['usuario'])) {
    $db = getDB();
    $stmt = $db->query("
        SELECT c.*, e.nombre as empleado_nombre 
        FROM cajas c
        JOIN empleados e ON c.empleado_id = e.id
        WHERE c.estado = 'abierta' 
        LIMIT 1
    ");
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($caja && !isset($_GET['confirmar'])) {
        // Mostrar advertencia antes de cerrar
        $es_mi_caja = ($caja['empleado_id'] == $_SESSION['usuario']['id']);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Advertencia - Caja Abierta</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body class="p-5">
            <div class="container" style="max-width: 600px;">
                <div class="alert alert-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> ¡Atención! Caja Abierta</h4>
                    <p class="mb-3">
                        <?php if ($es_mi_caja): ?>
                            Tienes una <strong>caja abierta</strong> desde el 
                            <?php echo date('d/m/Y H:i', strtotime($caja['fecha_apertura'])); ?>.
                            <br><br>
                            Se recomienda cerrar la caja antes de salir para mantener el control del efectivo.
                        <?php else: ?>
                            Hay una <strong>caja abierta</strong> por 
                            <strong><?php echo htmlspecialchars($caja['empleado_nombre']); ?></strong> 
                            desde el <?php echo date('d/m/Y H:i', strtotime($caja['fecha_apertura'])); ?>.
                        <?php endif; ?>
                    </p>
                    <div class="d-grid gap-2">
                        <?php if ($es_mi_caja): ?>
                            <a href="caja.php" class="btn btn-primary">
                                <i class="fas fa-cash-register"></i> Ir a Cerrar Caja
                            </a>
                        <?php endif; ?>
                        <a href="logout.php?confirmar=1" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión de todos modos
                        </a>
                        <a href="dashboard_mysql.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver al Sistema
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

session_destroy();
header('Location: index.php');
exit();
?>
