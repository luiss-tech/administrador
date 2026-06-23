<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Restringir acceso solo a administradores
if ($_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: dashboard_mysql.php?error=acceso_denegado');
    exit();
}

require_once 'config/database.php';
require_once 'trazabilidad_utils.php';

$db = getDB();

// Asegurar que exista tabla de historial
asegurarTablaHistorial();
$mensaje = '';
$tipo_mensaje = '';

// Obtener lista de productos
$productos = $db->query("
    SELECT p.*, e.nombre as empleado_creador, 
           COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual,
           COUNT(l.id) as total_lotes
    FROM productos p
    LEFT JOIN empleados e ON p.empleado_id = e.id
    LEFT JOIN lotes l ON p.id = l.producto_id AND l.activo = TRUE
    GROUP BY p.id
    ORDER BY p.nombre
")->fetchAll();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $empleado_id = $_SESSION['usuario']['id'];
    
    if ($_POST['action'] === 'editar_producto') {
        $producto_id = intval($_POST['producto_id']);
        $nombre = trim($_POST['nombre']);
        $categoria = trim($_POST['categoria']);
        $precio_venta = floatval($_POST['precio_venta']);
        $stock_minimo = intval($_POST['stock_minimo']);
        $descripcion = trim($_POST['descripcion']);
        $codigo_barras = trim($_POST['codigo_barras']) ?: null;
        
        try {
            // Obtener valores anteriores para trazabilidad
            $valores_anteriores = obtenerValoresActuales('productos', $producto_id);
            
            $stmt = $db->prepare("
                UPDATE productos 
                SET nombre = ?, categoria = ?, precio_venta = ?, 
                    stock_minimo = ?, descripcion = ?, codigo_barras = ?,
                    empleado_id = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $nombre, $categoria, $precio_venta, 
                $stock_minimo, $descripcion, $codigo_barras,
                $empleado_id, $producto_id
            ]);
            
            // Obtener valores nuevos
            $valores_nuevos = obtenerValoresActuales('productos', $producto_id);
            
            // Registrar trazabilidad
            $cambios = compararCambios($valores_anteriores, $valores_nuevos);
            $descripcion = generarDescripcionCambios($cambios, 'producto');
            
            registrarTrazabilidad(
                'productos', 
                $producto_id, 
                'editar', 
                $empleado_id, 
                $valores_anteriores, 
                $valores_nuevos, 
                $descripcion
            );
            
            $mensaje = "Producto actualizado correctamente";
            $tipo_mensaje = "success";
            
            // Recargar productos
            $productos = $db->query("
                SELECT p.*, e.nombre as empleado_creador, 
                       COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual,
                       COUNT(l.id) as total_lotes
                FROM productos p
                LEFT JOIN empleados e ON p.empleado_id = e.id
                LEFT JOIN lotes l ON p.id = l.producto_id AND l.activo = TRUE
                GROUP BY p.id
                ORDER BY p.nombre
            ")->fetchAll();
            
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar producto: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'cambiar_estado') {
        $producto_id = intval($_POST['producto_id']);
        $nuevo_estado = $_POST['estado'] === 'true';
        
        try {
            // Obtener valores anteriores para trazabilidad
            $valores_anteriores = obtenerValoresActuales('productos', $producto_id);
            
            // Verificar si el producto tiene ventas
            $stmt = $db->prepare("SELECT COUNT(*) FROM detalle_venta WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
            $tiene_ventas = $stmt->fetchColumn() > 0;
            
            if ($tiene_ventas && $nuevo_estado === false) {
                // Si tiene ventas, solo desactivar
                $stmt = $db->prepare("
                    UPDATE productos 
                    SET activo = ?, empleado_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_estado, $empleado_id, $producto_id]);
                
                $mensaje = "Producto desactivado. Se conservará el historial de ventas";
                $tipo_mensaje = "warning";
            } else {
                $stmt = $db->prepare("
                    UPDATE productos 
                    SET activo = ?, empleado_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_estado, $empleado_id, $producto_id]);
                
                $mensaje = $nuevo_estado ? "Producto activado correctamente" : "Producto desactivado correctamente";
                $tipo_mensaje = "success";
            }
            
            // Obtener valores nuevos y registrar trazabilidad
            $valores_nuevos = obtenerValoresActuales('productos', $producto_id);
            
            registrarTrazabilidad(
                'productos', 
                $producto_id, 
                $nuevo_estado ? 'activar' : 'desactivar', 
                $empleado_id, 
                $valores_anteriores, 
                $valores_nuevos, 
                "Producto " . ($nuevo_estado ? 'activado' : 'desactivado') . ($tiene_ventas && $nuevo_estado === false ? ' (con historial de ventas)' : '')
            );
            
            // Recargar productos
            $productos = $db->query("
                SELECT p.*, e.nombre as empleado_creador, 
                       COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual,
                       COUNT(l.id) as total_lotes
                FROM productos p
                LEFT JOIN empleados e ON p.empleado_id = e.id
                LEFT JOIN lotes l ON p.id = l.producto_id AND l.activo = TRUE
                GROUP BY p.id
                ORDER BY p.nombre
            ")->fetchAll();
            
        } catch (PDOException $e) {
            $mensaje = "Error al cambiar estado: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
    
    elseif ($_POST['action'] === 'editar_lote') {
        $lote_id = intval($_POST['lote_id']);
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        
        // Validar fecha de vencimiento
        if ($fecha_vencimiento) {
            $fecha_vencimiento_obj = new DateTime($fecha_vencimiento);
            $hoy = new DateTime();
            $hoy->setTime(0, 0, 0);
            
            if ($fecha_vencimiento_obj < $hoy) {
                $mensaje = "Error: La fecha de vencimiento no puede ser anterior a hoy";
                $tipo_mensaje = "danger";
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE lotes 
                        SET fecha_vencimiento = ?, empleado_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$fecha_vencimiento, $empleado_id, $lote_id]);
                    
                    $mensaje = "Lote actualizado correctamente";
                    $tipo_mensaje = "success";
                } catch (PDOException $e) {
                    $mensaje = "Error al actualizar lote: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            }
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE lotes 
                    SET fecha_vencimiento = ?, empleado_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([null, $empleado_id, $lote_id]);
                
                $mensaje = "Lote actualizado correctamente";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar lote: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

$usuario = $_SESSION['usuario'];

// API para obtener datos de producto
if (isset($_GET['action']) && $_GET['action'] === 'get_producto' && isset($_GET['id'])) {
    $producto_id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($producto);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Productos - Sistema de Gestión</title>
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
        .producto-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .producto-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .producto-inactivo {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        .stock-badge {
            font-size: 0.9rem;
            padding: 4px 8px;
        }
        .lote-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid #007bff;
        }
        .lotes-container {
            max-height: 150px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .lotes-container::-webkit-scrollbar {
            width: 6px;
        }
        .lotes-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .lotes-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .lotes-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .btn-action {
            padding: 4px 8px;
            font-size: 0.8rem;
            margin: 2px;
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
                    <h2><i class="fas fa-edit"></i> Editor de Productos</h2>
                    <div>
                        <a href="productos_mysql.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nuevo Producto
                        </a>
                        <a href="ingresos.php" class="btn btn-primary">
                            <i class="fas fa-box"></i> Agregar Stock
                        </a>
                    </div>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Lista de Productos -->
                <div class="row">
                    <?php foreach ($productos as $producto): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="producto-card <?php echo !$producto['activo'] ? 'producto-inactivo' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($producto['codigo']); ?></small>
                                    </div>
                                    <div>
                                        <?php if ($producto['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <small class="text-muted d-block">Stock</small>
                                        <span class="stock-badge badge <?php echo $producto['stock_actual'] <= $producto['stock_minimo'] ? 'bg-danger' : 'bg-primary'; ?>">
                                            <?php echo $producto['stock_actual']; ?>
                                        </span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Precio</small>
                                        <strong>$<?php echo number_format($producto['precio_venta'], 2); ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Lotes</small>
                                        <strong><?php echo $producto['total_lotes']; ?></strong>
                                    </div>
                                </div>

                                <?php if ($producto['categoria']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Categoría:</small>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($producto['categoria']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <small class="text-muted">Creado por:</small>
                                    <span class="ms-1"><?php echo htmlspecialchars($producto['empleado_creador'] ?? 'Sistema'); ?></span>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary btn-action" 
                                                onclick="editarProducto(<?php echo $producto['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?php echo $producto['activo'] ? 'warning' : 'success'; ?> btn-action" 
                                                onclick="cambiarEstado(<?php echo $producto['id']; ?>, <?php echo $producto['activo'] ? 'false' : 'true'; ?>)">
                                            <i class="fas fa-<?php echo $producto['activo'] ? 'pause' : 'play'; ?>"></i> 
                                            <?php echo $producto['activo'] ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-info btn-action" 
                                                onclick="verLotes(<?php echo $producto['id']; ?>)">
                                            <i class="fas fa-boxes"></i> Lotes
                                        </button>
                                    </div>
                                </div>

                                <!-- Lotes (oculto por defecto) -->
                                <div id="lotes-<?php echo $producto['id']; ?>" class="mt-3" style="display: none;">
                                    <hr>
                                    <h6 class="mb-3">Lotes del Producto</h6>
                                    <div class="lotes-container">
                                        <?php
                                        $lotes = $db->prepare("
                                            SELECT l.*, e.nombre as empleado_registro
                                            FROM lotes l
                                            LEFT JOIN empleados e ON l.empleado_id = e.id
                                            WHERE l.producto_id = ? AND l.activo = TRUE
                                            ORDER BY l.fecha_ingreso DESC
                                        ");
                                        $lotes->execute([$producto['id']]);
                                        $lotes_producto = $lotes->fetchAll();
                                        
                                        foreach ($lotes_producto as $lote):
                                        ?>
                                            <div class="lote-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Disponible: <?php echo $lote['cantidad_disponible']; ?></strong> / <?php echo $lote['cantidad']; ?>
                                                        <br><small class="text-muted">
                                                            Ingreso: <?php echo date('d/m/Y', strtotime($lote['fecha_ingreso'])); ?>
                                                            <?php if ($lote['empleado_registro']): ?>
                                                                • Por: <?php echo htmlspecialchars($lote['empleado_registro']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                        <?php if ($lote['fecha_vencimiento']): ?>
                                                            <br><small class="text-muted">
                                                                Vence: <?php echo date('d/m/Y', strtotime($lote['fecha_vencimiento'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-outline-warning btn-action" 
                                                                onclick="editarLote(<?php echo $lote['id']; ?>, '<?php echo $lote['fecha_vencimiento']; ?>')">
                                                            <i class="fas fa-calendar"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarProducto" method="POST">
                    <input type="hidden" name="action" value="editar_producto">
                    <input type="hidden" id="edit_producto_id" name="producto_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <input type="text" class="form-control" id="edit_categoria" name="categoria">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Precio de Venta</label>
                                    <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Mínimo</label>
                                    <input type="number" class="form-control" id="edit_stock_minimo" name="stock_minimo" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código de Barras</label>
                            <input type="text" class="form-control" id="edit_codigo_barras" name="codigo_barras">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Lote -->
    <div class="modal fade" id="modalEditarLote" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Fecha de Vencimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarLote" method="POST">
                    <input type="hidden" name="action" value="editar_lote">
                    <input type="hidden" id="edit_lote_id" name="lote_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" id="edit_fecha_vencimiento" name="fecha_vencimiento">
                            <small class="text-muted">Deje en blanco si no tiene fecha de vencimiento</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        function editarProducto(productoId) {
            // Cargar datos del producto
            fetch(`editar_productos.php?action=get_producto&id=${productoId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_producto_id').value = data.id;
                    document.getElementById('edit_nombre').value = data.nombre;
                    document.getElementById('edit_categoria').value = data.categoria || '';
                    document.getElementById('edit_precio_venta').value = data.precio_venta;
                    document.getElementById('edit_stock_minimo').value = data.stock_minimo;
                    document.getElementById('edit_codigo_barras').value = data.codigo_barras || '';
                    document.getElementById('edit_descripcion').value = data.descripcion || '';
                    
                    new bootstrap.Modal(document.getElementById('modalEditarProducto')).show();
                })
                .catch(error => {
                    alertaDanger('Error al cargar datos del producto');
                });
        }

        function cambiarEstado(productoId, nuevoEstado) {
            const confirmacion = nuevoEstado ? 
                '¿Activar este producto? Podrá usarse en ventas nuevamente.' :
                '¿Desactivar este producto? No aparecerá en ventas nuevas.';
            
            // Crear modal de confirmación personalizado
            const modalHtml = `
                <div class="modal fade" id="modalConfirmarEstado" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirmar Cambio de Estado</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>${confirmacion}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-${nuevoEstado ? 'success' : 'warning'}" onclick="confirmarCambioEstado(${productoId}, ${nuevoEstado})">
                                    ${nuevoEstado ? 'Activar' : 'Desactivar'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal existente si hay
            const modalExistente = document.getElementById('modalConfirmarEstado');
            if (modalExistente) {
                modalExistente.remove();
            }
            
            // Agregar nuevo modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEstado'));
            modal.show();
        }

        function confirmarCambioEstado(productoId, nuevoEstado) {
            // Cerrar modal
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEstado')).hide();
            
            // Enviar formulario
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="cambiar_estado">
                <input type="hidden" name="producto_id" value="${productoId}">
                <input type="hidden" name="estado" value="${nuevoEstado}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function verLotes(productoId) {
            const lotesDiv = document.getElementById(`lotes-${productoId}`);
            lotesDiv.style.display = lotesDiv.style.display === 'none' ? 'block' : 'none';
        }

        function editarLote(loteId, fechaVencimiento) {
            document.getElementById('edit_lote_id').value = loteId;
            document.getElementById('edit_fecha_vencimiento').value = fechaVencimiento || '';
            new bootstrap.Modal(document.getElementById('modalEditarLote')).show();
        }

    </script>
    <script src="heartbeat.js"></script>
</body>
</html>
