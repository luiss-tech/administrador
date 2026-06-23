<?php
/**
 * Sidebar Dinámico - Muestra menú según rol del usuario
 * Incluir en todas las páginas protegidas
 */

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

$rol = $_SESSION['usuario']['rol'];
$es_admin = ($rol === 'administrador');
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="user-info text-center p-3">
            <i class="fas fa-user-circle fa-3x mb-2"></i>
            <h6><?php echo htmlspecialchars($_SESSION['usuario']['nombre'] ?? 'Usuario'); ?></h6>
            <small><?php echo htmlspecialchars($_SESSION['usuario']['rol'] ?? 'empleado'); ?></small>
        </div>
        <ul class="nav flex-column">
            
            <?php if ($es_admin): ?>
                <!-- MENÚ ADMINISTRADOR -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'dashboard_mysql.php' ? 'active' : ''; ?>" href="dashboard_mysql.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'ventas_mysql.php' ? 'active' : ''; ?>" href="ventas_mysql.php">
                        <i class="fas fa-shopping-cart"></i> Ventas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'ingresos.php' ? 'active' : ''; ?>" href="ingresos.php">
                        <i class="fas fa-plus-circle"></i> Ingresar Stock
                    </a>
                </li>
                                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'productos_mysql.php' ? 'active' : ''; ?>" href="productos_mysql.php">
                        <i class="fas fa-plus"></i> Registrar Producto
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'editar_productos.php' ? 'active' : ''; ?>" href="editar_productos.php">
                        <i class="fas fa-edit"></i> Editar Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'empleados_mysql.php' ? 'active' : ''; ?>" href="empleados_mysql.php">
                        <i class="fas fa-users"></i> Empleados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'backup_manager.php' ? 'active' : ''; ?>" href="backup_manager.php">
                        <i class="fas fa-database"></i> Backups
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'configuracion_backup.php' ? 'active' : ''; ?>" href="configuracion_backup.php">
                        <i class="fas fa-cog"></i> Config. Backups
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'logs_viewer.php' ? 'active' : ''; ?>" href="logs_viewer.php">
                        <i class="fas fa-file-alt"></i> Logs del Sistema
                    </a>
                </li>
                                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'caja.php' ? 'active' : ''; ?>" href="caja.php">
                        <i class="fas fa-cash-register"></i> Control de Caja
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'reportes_mysql.php' ? 'active' : ''; ?>" href="reportes_mysql.php">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a>
                </li>
                
            <?php else: ?>
                <!-- MENÚ EMPLEADO (VENDEDOR) -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'ventas_mysql.php' ? 'active' : ''; ?>" href="ventas_mysql.php">
                        <i class="fas fa-shopping-cart"></i> Nueva Venta
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'productos_mysql.php' ? 'active' : ''; ?>" href="productos_mysql.php">
                        <i class="fas fa-box"></i> Registrar Producto
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'ingresos.php' ? 'active' : ''; ?>" href="ingresos.php">
                        <i class="fas fa-plus-circle"></i> Ingresar Stock
                    </a>
                </li>
                                <li class="nav-item">
                    <a class="nav-link <?php echo $pagina_actual == 'caja.php' ? 'active' : ''; ?>" href="caja.php">
                        <i class="fas fa-cash-register"></i> Control de Caja
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </div>
</nav>
