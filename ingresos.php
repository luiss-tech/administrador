<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$mensaje = '';
$tipo_mensaje = '';

// API para buscar productos
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar') {
    $q = '%' . ($_GET['q'] ?? '') . '%';
    $stmt = $db->prepare("
        SELECT id, codigo, nombre, precio_venta 
        FROM productos 
        WHERE activo = 1 
        AND (nombre LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ?)
        ORDER BY nombre
        LIMIT 10
    ");
    $stmt->execute([$q, $q, $q]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

// Procesar ingreso de lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ingresar') {
        $producto_id = intval($_POST['producto_id']);
        $cantidad = intval($_POST['cantidad']);
        $precio_compra = floatval($_POST['precio_compra']);
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        
        // Validar fecha de vencimiento
        if ($fecha_vencimiento) {
            $fecha_vencimiento_obj = new DateTime($fecha_vencimiento);
            $hoy = new DateTime();
            $hoy->setTime(0, 0, 0); // Ignorar hora para comparación
            
            if ($fecha_vencimiento_obj < $hoy) {
                $mensaje = "Error: La fecha de vencimiento no puede ser anterior a hoy";
                $tipo_mensaje = "danger";
            } else {
                try {
                    // Obtener ID del empleado actual
                    $empleado_id = $_SESSION['usuario']['id'] ?? null;
                    
                    // Insertar el lote con empleado_id
                    $stmt = $db->prepare("INSERT INTO lotes (producto_id, cantidad, cantidad_disponible, precio_compra, fecha_vencimiento, empleado_id) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $producto_id,
                        $cantidad,
                        $cantidad,
                        $precio_compra,
                        $fecha_vencimiento,
                        $empleado_id
                    ]);
                    
                    // Actualizar precio de venta si se proporcionó
                    if (!empty($_POST['precio_venta'])) {
                        $precio_venta = floatval($_POST['precio_venta']);
                        $stmt = $db->prepare("UPDATE productos SET precio_venta = ? WHERE id = ?");
                        $stmt->execute([$precio_venta, $producto_id]);
                        $mensaje = "Lote ingresado y precio de venta actualizado";
                    } else {
                        $mensaje = "Lote ingresado correctamente";
                    }
                    $tipo_mensaje = "success";
                } catch (PDOException $e) {
                    $mensaje = "Error al ingresar lote: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            }
        } else {
            // Si no hay fecha de vencimiento, procesar normalmente
            try {
                // Obtener ID del empleado actual
                $empleado_id = $_SESSION['usuario']['id'] ?? null;
                
                // Insertar el lote con empleado_id
                $stmt = $db->prepare("INSERT INTO lotes (producto_id, cantidad, cantidad_disponible, precio_compra, fecha_vencimiento, empleado_id) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $producto_id,
                    $cantidad,
                    $cantidad,
                    $precio_compra,
                    $fecha_vencimiento,
                    $empleado_id
                ]);
                
                // Actualizar precio de venta si se proporcionó
                if (!empty($_POST['precio_venta'])) {
                    $precio_venta = floatval($_POST['precio_venta']);
                    $stmt = $db->prepare("UPDATE productos SET precio_venta = ? WHERE id = ?");
                    $stmt->execute([$precio_venta, $producto_id]);
                    $mensaje = "Lote ingresado y precio de venta actualizado";
                } else {
                    $mensaje = "Lote ingresado correctamente";
                }
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al ingresar lote: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Cargar últimos lotes ingresados
$usuario_rol = $_SESSION['usuario']['rol'];
if ($usuario_rol === 'administrador') {
    $lotes = $db->query("
        SELECT l.*, p.nombre as producto_nombre, p.codigo, e.nombre as empleado_nombre
        FROM lotes l 
        JOIN productos p ON l.producto_id = p.id 
        LEFT JOIN empleados e ON l.empleado_id = e.id
        WHERE l.activo = TRUE 
        ORDER BY l.fecha_ingreso DESC 
        LIMIT 50
    ")->fetchAll();
} else {
    // Empleado solo ve sus propios lotes
    $empleado_id = $_SESSION['usuario']['id'];
    $lotes = $db->query("
        SELECT l.*, p.nombre as producto_nombre, p.codigo 
        FROM lotes l 
        JOIN productos p ON l.producto_id = p.id 
        WHERE l.activo = TRUE AND l.empleado_id = $empleado_id
        ORDER BY l.fecha_ingreso DESC 
        LIMIT 50
    ")->fetchAll();
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso de Productos - Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        .search-result-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
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
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .lote-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .badge-vencimiento {
            font-size: 0.85rem;
        }
        .lotes-scrollable {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .lotes-scrollable::-webkit-scrollbar {
            width: 8px;
        }
        .lotes-scrollable::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .lotes-scrollable::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .lotes-scrollable::-webkit-scrollbar-thumb:hover {
            background: #555;
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-plus-circle text-success"></i> Ingreso de Productos</h1>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Formulario de ingreso -->
                    <div class="col-lg-5">
                        <div class="form-card mb-4">
                            <h4 class="mb-4">Nuevo Ingreso</h4>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="ingresar">
                                
                                <div class="mb-3 position-relative">
                                    <label class="form-label">Buscar Producto *</label>
                                    <input type="text" id="buscarProducto" class="form-control" 
                                           placeholder="Escribe nombre, código o código de barras..." autocomplete="off">
                                    <input type="hidden" name="producto_id" id="producto_id" required>
                                    <div id="resultadosBusqueda" class="search-results d-none"></div>
                                    <div id="productoSeleccionado" class="mt-2 d-none">
                                        <span class="badge bg-success"><i class="fas fa-check"></i> <span id="nombreProducto"></span></span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Cantidad *</label>
                                    <input type="number" name="cantidad" class="form-control" min="1" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Precio de Compra (unitario) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="precio_compra" class="form-control" step="0.01" min="0.01" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nuevo Precio de Venta <small class="text-muted">(opcional - dejar en blanco para mantener actual)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="precio_venta" id="precio_venta" class="form-control" step="0.01" min="0.01">
                                    </div>
                                    <small class="text-muted">Precio actual: $<span id="precio_venta_actual">-</span></small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Fecha de Vencimiento</label>
                                    <input type="date" name="fecha_vencimiento" class="form-control">
                                    <small class="text-muted">Opcional, pero recomendado para control de lotes</small>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-save"></i> Guardar Ingreso
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de lotes recientes -->
                    <div class="col-lg-7">
                        <h4 class="mb-3">Últimos Ingresos</h4>
                        <div class="lotes-scrollable">
                        <?php foreach ($lotes as $lote): 
                            $dias_para_vencer = $lote['fecha_vencimiento'] ? 
                                floor((strtotime($lote['fecha_vencimiento']) - time()) / 86400) : null;
                            $badge_class = 'bg-success';
                            $estado = '';
                            if ($dias_para_vencer !== null) {
                                if ($dias_para_vencer < 0) {
                                    $badge_class = 'bg-danger';
                                    $estado = 'Vencido';
                                } elseif ($dias_para_vencer <= 7) {
                                    $badge_class = 'bg-warning text-dark';
                                    $estado = "Vence en {$dias_para_vencer} días";
                                }
                            }
                        ?>
                            <div class="lote-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($lote['producto_nombre']); ?></h5>
                                        <small class="text-muted"><?php echo $lote['codigo']; ?> • Ingresado: <?php echo date('d/m/Y', strtotime($lote['fecha_ingreso'])); ?></small>
                                        <?php if ($usuario_rol === 'administrador' && isset($lote['empleado_nombre'])): ?>
                                            <br><small class="text-muted"><i class="fas fa-user"></i> Por: <?php echo htmlspecialchars($lote['empleado_nombre']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($estado): ?>
                                        <span class="badge <?php echo $badge_class; ?> badge-vencimiento"><?php echo $estado; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-4">
                                        <small class="text-muted">Cantidad</small>
                                        <p class="mb-0 fw-bold"><?php echo $lote['cantidad']; ?></p>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Disponible</small>
                                        <p class="mb-0 fw-bold <?php echo $lote['cantidad_disponible'] < $lote['cantidad'] ? 'text-warning' : 'text-success'; ?>">
                                            <?php echo $lote['cantidad_disponible']; ?>
                                        </p>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Precio Compra</small>
                                        <p class="mb-0 fw-bold">$<?php echo number_format($lote['precio_compra'], 2); ?></p>
                                    </div>
                                </div>
                                <?php if ($lote['fecha_vencimiento']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> 
                                        Vence: <?php echo date('d/m/Y', strtotime($lote['fecha_vencimiento'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        // Búsqueda de productos
        const buscarInput = document.getElementById('buscarProducto');
        const resultadosDiv = document.getElementById('resultadosBusqueda');
        const productoIdInput = document.getElementById('producto_id');
        const productoSeleccionadoDiv = document.getElementById('productoSeleccionado');
        const nombreProductoSpan = document.getElementById('nombreProducto');
        const precioVentaActualSpan = document.getElementById('precio_venta_actual');

        let productoSeleccionado = null;

        buscarInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 2) {
                resultadosDiv.classList.add('d-none');
                return;
            }

            fetch('ingresos.php?ajax=buscar&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    mostrarResultados(data);
                });
        });

        function mostrarResultados(productos) {
            if (productos.length === 0) {
                resultadosDiv.innerHTML = '<div class="p-3 text-muted">No se encontraron productos</div>';
            } else {
                resultadosDiv.innerHTML = productos.map(p => `
                    <div class="search-result-item" onclick="seleccionarProducto(${p.id}, '${p.nombre.replace(/'/g, "\\'")}', ${p.precio_venta})">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${p.nombre}</strong>
                                <br><small class="text-muted">${p.codigo}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary">$${parseFloat(p.precio_venta).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            resultadosDiv.classList.remove('d-none');
        }

        function seleccionarProducto(id, nombre, precioVenta) {
            productoIdInput.value = id;
            buscarInput.value = '';
            resultadosDiv.classList.add('d-none');
            
            productoSeleccionado = { id, nombre, precioVenta };
            nombreProductoSpan.textContent = nombre;
            productoSeleccionadoDiv.classList.remove('d-none');
            precioVentaActualSpan.textContent = precioVenta.toFixed(2);
        }

        // Cerrar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#buscarProducto') && !e.target.closest('#resultadosBusqueda')) {
                resultadosDiv.classList.add('d-none');
            }
        });

        // Validar que se haya seleccionado un producto antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!productoIdInput.value) {
                e.preventDefault();
                alertaWarning('Por favor, busca y selecciona un producto');
                buscarInput.focus();
            }
        });
    </script>
    <script src="heartbeat.js"></script>
</body>
</html>
