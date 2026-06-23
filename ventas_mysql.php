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

// Verificar que haya una caja abierta
$stmt = $db->prepare("SELECT id FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$caja_abierta = $stmt->fetch();
$caja_id = $caja_abierta ? $caja_abierta['id'] : null;

// API para buscar productos
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar') {
    $q = '%' . ($_GET['q'] ?? '') . '%';
    $stmt = $db->prepare("
        SELECT p.id, p.codigo, p.nombre, p.precio_venta, p.codigo_barras,
               COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual
        FROM productos p
        LEFT JOIN lotes l ON p.id = l.producto_id AND l.activo = 1 AND l.cantidad_disponible > 0
        AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE())
        WHERE p.activo = 1 
        AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)
        GROUP BY p.id
        HAVING stock_actual > 0
        LIMIT 10
    ");
    $stmt->execute([$q, $q, $q]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vender') {
    // Validar que haya caja abierta
    if (!$caja_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No hay una caja abierta. Abra la caja antes de realizar ventas.']);
        exit;
    }
    
    $productos = json_decode($_POST['productos'], true);
    $total = floatval($_POST['total']);
    $cliente = $_POST['cliente'] ?? 'Cliente General';
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $empleado_id = $_SESSION['usuario']['id'];
    
    try {
        $db->beginTransaction();
        
        // Generar número de venta
        $stmt = $db->query("SELECT COUNT(*) FROM ventas");
        $num_venta = 'V' . str_pad($stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
        
        // Crear venta
        $stmt = $db->prepare("INSERT INTO ventas (numero, total, cliente, empleado_id, metodo_pago, caja_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$num_venta, $total, $cliente, $empleado_id, $metodo_pago, $caja_id]);
        $venta_id = $db->lastInsertId();
        
        $costo_total = 0;
        
        // Procesar cada producto con sistema FIFO
        foreach ($productos as $item) {
            $producto_id = $item['id'];
            $cantidad_solicitada = $item['cantidad'];
            $precio_venta = $item['precio'];
            
            // Obtener lotes disponibles ordenados por fecha de ingreso (FIFO)
            $stmt = $db->prepare("
                SELECT id, cantidad_disponible, precio_compra 
                FROM lotes 
                WHERE producto_id = ? AND activo = 1 AND cantidad_disponible > 0
                AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURDATE())
                ORDER BY fecha_ingreso ASC, id ASC
            ");
            $stmt->execute([$producto_id]);
            $lotes = $stmt->fetchAll();
            
            $cantidad_restante = $cantidad_solicitada;
            
            foreach ($lotes as $lote) {
                if ($cantidad_restante <= 0) break;
                
                $cantidad_de_lote = min($cantidad_restante, $lote['cantidad_disponible']);
                $costo_unitario = $lote['precio_compra'];
                $ganancia_unitario = $precio_venta - $costo_unitario;
                $ganancia = $ganancia_unitario * $cantidad_de_lote;
                
                // Guardar detalle de venta
                $stmt = $db->prepare("
                    INSERT INTO detalle_venta 
                    (venta_id, producto_id, lote_id, cantidad, precio_venta, costo_unitario, ganancia) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $venta_id, $producto_id, $lote['id'], $cantidad_de_lote, 
                    $precio_venta, $costo_unitario, $ganancia
                ]);
                
                // Actualizar lote
                $stmt = $db->prepare("
                    UPDATE lotes 
                    SET cantidad_disponible = cantidad_disponible - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$cantidad_de_lote, $lote['id']]);
                
                $costo_total += $costo_unitario * $cantidad_de_lote;
                $cantidad_restante -= $cantidad_de_lote;
            }
            
            if ($cantidad_restante > 0) {
                throw new Exception("Stock insuficiente para el producto");
            }
        }
        
        // Actualizar venta con costo y ganancia totales
        $ganancia_total = $total - $costo_total;
        $stmt = $db->prepare("UPDATE ventas SET costo_total = ?, ganancia = ? WHERE id = ?");
        $stmt->execute([$costo_total, $ganancia_total, $venta_id]);
        
        $db->commit();
        $mensaje = "Venta $num_venta procesada correctamente";
        $tipo_mensaje = "success";
        
        // Si es petición AJAX, devolver JSON con ID de venta
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'venta_id' => $venta_id,
                'numero' => $num_venta,
                'mensaje' => $mensaje
            ]);
            exit;
        }
    } catch (Exception $e) {
        $db->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

$usuario = $_SESSION['usuario'];

// Verificar si hay caja abierta por otro usuario
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
    <title>Ventas - Sistema de Gestión</title>
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
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .producto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .carrito-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .total-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
        }
        .search-results {
            max-height: 300px;
            overflow-y: auto;
            position: absolute;
            width: 100%;
            z-index: 1000;
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        /* Ocultar flechas nativas del input number */
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
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
                                Como administrador, puedes continuar o ir al 
                                <a href="caja.php" class="alert-link">control de caja</a> para revisar.
                            <?php else: ?>
                                Debes ir al <a href="caja.php" class="alert-link">control de caja</a> para cerrarla antes de vender.
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
                    <h1 class="h2"><i class="fas fa-shopping-cart text-primary"></i> Nueva Venta</h1>
                    <?php if ($caja_id): ?>
                        <span class="badge bg-success"><i class="fas fa-cash-register"></i> Caja Abierta #<?php echo $caja_id; ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-store-slash"></i> Caja Cerrada</span>
                    <?php endif; ?>
                </div>

                <?php if (!$caja_id && !($alerta_caja && $alerta_caja['abierta'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <strong>No hay caja abierta.</strong> 
                        Debe <a href="caja.php" class="alert-link">abrir una caja</a> antes de realizar ventas.
                    </div>
                <?php endif; ?>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Búsqueda y selección -->
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-search"></i> Buscar Producto</h5>
                            </div>
                            <div class="card-body">
                                <div class="position-relative">
                                    <input type="text" id="buscarProducto" class="form-control form-control-lg" 
                                           placeholder="Nombre, código o código de barras..." autocomplete="off">
                                    <div id="resultadosBusqueda" class="search-results d-none"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Productos encontrados -->
                        <div id="productosEncontrados"></div>
                    </div>

                    <!-- Carrito -->
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-cart-plus"></i> Carrito</h5>
                                <button class="btn btn-sm btn-light" onclick="limpiarCarrito()">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="carritoItems"></div>
                                
                                <div class="total-box mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Total a Pagar:</span>
                                        <h3 class="mb-0">$<span id="totalVenta">0.00</span></h3>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Cliente</label>
                                    <input type="text" id="cliente" class="form-control" value="Cliente General">
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Método de Pago</label>
                                    <select id="metodoPago" class="form-select">
                                        <option value="efectivo">💵 Efectivo</option>
                                        <option value="tarjeta">💳 Tarjeta</option>
                                        <option value="yape">📱 Yape</option>
                                        <option value="transferencia">🏦 Transferencia</option>
                                    </select>
                                </div>

                                <button class="btn btn-success w-100 mt-3 btn-lg" onclick="mostrarConfirmacion()" <?php echo $caja_id ? '' : 'disabled'; ?>>
                                    <i class="fas fa-check-circle"></i> Procesar Venta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de confirmación ANTES de procesar -->
    <div class="modal fade" id="modalConfirmar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-question-circle"></i> Confirmar Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Resumen de la venta:</h6>
                    <p><strong>Cliente:</strong> <span id="confirmCliente"></span></p>
                    <p><strong>Total:</strong> $<span id="confirmTotal"></span></p>
                    <p><strong>Método de Pago:</strong> <span id="confirmMetodoPago"></span></p>
                    <p><strong>Productos:</strong> <span id="confirmProductos"></span></p>
                    <hr>
                    <div id="confirmDetalle" style="max-height: 300px; overflow-y: auto; font-size: 0.9em;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" onclick="confirmarYProcesar()">
                        <i class="fas fa-check-circle"></i> Confirmar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de éxito DESPUÉS de procesar -->
    <div class="modal fade" id="modalVenta" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Venta Exitosa</h5>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4>¡Venta Procesada!</h4>
                    <p id="ventaMensaje"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="nuevaVenta()">Nueva Venta</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        let carrito = [];
        let productosCache = [];

        // Búsqueda de productos
        document.getElementById('buscarProducto').addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 2) {
                document.getElementById('resultadosBusqueda').classList.add('d-none');
                return;
            }

            fetch('ventas_mysql.php?ajax=buscar&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    productosCache = data;
                    mostrarResultados(data);
                });
        });

        function mostrarResultados(productos) {
            const container = document.getElementById('resultadosBusqueda');
            if (productos.length === 0) {
                container.innerHTML = '<div class="p-3 text-muted">No se encontraron productos</div>';
            } else {
                container.innerHTML = productos.map(p => `
                    <div class="p-3 border-bottom cursor-pointer hover-bg" onclick="agregarAlCarrito(${p.id})" style="cursor:pointer;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${p.nombre}</strong>
                                <br><small class="text-muted">${p.codigo}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success">Stock: ${p.stock_actual}</span>
                                <br><strong>$${parseFloat(p.precio_venta).toFixed(2)}</strong>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            container.classList.remove('d-none');
        }

        function agregarAlCarrito(productoId) {
            const producto = productosCache.find(p => p.id == productoId);
            if (!producto) return;

            const existente = carrito.find(item => item.id == productoId);
            if (existente) {
                if (existente.cantidad < producto.stock_actual) {
                    existente.cantidad++;
                } else {
                    alertaDanger('Stock insuficiente');
                    return;
                }
            } else {
                carrito.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    codigo: producto.codigo,
                    precio: parseFloat(producto.precio_venta),
                    cantidad: 1,
                    stock: producto.stock_actual
                });
            }

            document.getElementById('buscarProducto').value = '';
            document.getElementById('resultadosBusqueda').classList.add('d-none');
            actualizarCarrito();
        }

        function actualizarCarrito() {
            const container = document.getElementById('carritoItems');
            if (carrito.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Carrito vacío</p>';
            } else {
                container.innerHTML = carrito.map((item, index) => `
                    <div class="carrito-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${item.nombre}</strong>
                                <br><small class="text-muted">${item.codigo}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarItem(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="d-flex align-items-center" style="gap: 5px;">
                                <button class="btn btn-sm btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">-</button>
                                <input type="number" class="form-control form-control-sm text-center" 
                                       value="${item.cantidad}" min="1" max="${item.stock}"
                                       onchange="cambiarCantidadDirecta(${index}, this.value)"
                                       style="width: 70px;">
                                <button class="btn btn-sm btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">+</button>
                            </div>
                            <span class="fw-bold">$${(item.precio * item.cantidad).toFixed(2)}</span>
                        </div>
                    </div>
                `).join('');
            }

            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            document.getElementById('totalVenta').textContent = total.toFixed(2);
        }

        function cambiarCantidad(index, delta) {
            const item = carrito[index];
            const nuevaCantidad = item.cantidad + delta;
            
            if (nuevaCantidad < 1) {
                eliminarItem(index);
                return;
            }
            
            if (nuevaCantidad > item.stock) {
                alertaDanger('Stock insuficiente');
                return;
            }
            
            item.cantidad = nuevaCantidad;
            actualizarCarrito();
        }

        function cambiarCantidadDirecta(index, valor) {
            const item = carrito[index];
            const nuevaCantidad = parseInt(valor) || 1;
            
            if (nuevaCantidad < 1) {
                alertaWarning('La cantidad debe ser al menos 1');
                actualizarCarrito();
                return;
            }
            
            if (nuevaCantidad > item.stock) {
                alertaDanger(`Stock insuficiente. Máximo disponible: ${item.stock}`);
                actualizarCarrito();
                return;
            }
            
            item.cantidad = nuevaCantidad;
            actualizarCarrito();
        }

        function eliminarItem(index) {
            carrito.splice(index, 1);
            actualizarCarrito();
        }

        function limpiarCarrito() {
            if (carrito.length === 0) return;
            if (confirm('¿Vaciar carrito?')) {
                carrito = [];
                actualizarCarrito();
            }
        }

        // Mostrar modal de confirmación antes de procesar
        function mostrarConfirmacion() {
            if (carrito.length === 0) {
                alertaWarning('Carrito vacío');
                return;
            }

            const cliente = document.getElementById('cliente').value;
            const total = document.getElementById('totalVenta').textContent;
            const metodoPago = document.getElementById('metodoPago').value;
            const metodoPagoTexto = document.getElementById('metodoPago').options[document.getElementById('metodoPago').selectedIndex].text;
            
            // Generar resumen de productos
            let detalleHTML = '<table class="table table-sm">';
            detalleHTML += '<thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>';
            
            let cantidadTotal = 0;
            let totalVenta = 0;
            carrito.forEach(item => {
                const subtotal = item.cantidad * item.precio;
                detalleHTML += `<tr>
                    <td>${item.nombre}</td>
                    <td>${item.cantidad}</td>
                    <td>$${item.precio.toFixed(2)}</td>
                    <td>$${subtotal.toFixed(2)}</td>
                </tr>`;
                cantidadTotal += item.cantidad;
            });
            
            detalleHTML += '</tbody></table>';
            
            // Calcular vuelto si es efectivo
            const totalNum = parseFloat(total);
            if (metodoPago === 'efectivo') {
                detalleHTML += `
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="mb-2">
                            <label class="form-label"><strong>Efectivo recibido:</strong></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="efectivoRecibido" class="form-control form-control-lg" 
                                       step="0.01" min="${totalNum}" value="${totalNum}" onkeyup="calcularVuelto()">
                            </div>
                        </div>
                        <div class="alert alert-success mb-0" id="vueltoContainer" style="display:none;">
                            <strong>Vuelto: $<span id="vueltoMonto">0.00</span></strong>
                        </div>
                    </div>
                `;
            }
            
            // Llenar modal
            document.getElementById('confirmCliente').textContent = cliente;
            document.getElementById('confirmTotal').textContent = total;
            document.getElementById('confirmMetodoPago').textContent = metodoPagoTexto;
            document.getElementById('confirmProductos').textContent = cantidadTotal + ' items';
            document.getElementById('confirmDetalle').innerHTML = detalleHTML;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmar'));
            modal.show();
        }

        // Procesar venta después de confirmar
        function confirmarYProcesar() {
            // Cerrar modal de confirmación
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmar')).hide();
            // Procesar venta
            procesarVenta();
        }

        function procesarVenta() {
            if (carrito.length === 0) {
                alertaWarning('Carrito vacío');
                return;
            }

            const total = document.getElementById('totalVenta').textContent;
            const cliente = document.getElementById('cliente').value;
            const metodoPago = document.getElementById('metodoPago').value;

            const formData = new FormData();
            formData.append('action', 'vender');
            formData.append('productos', JSON.stringify(carrito));
            formData.append('total', total);
            formData.append('cliente', cliente);
            formData.append('metodo_pago', metodoPago);

            fetch('ventas_mysql.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then((data) => {
                if (data.success && data.venta_id) {
                    // Mostrar mensaje de éxito
                    alert('Venta registrada exitosamente. N° de venta: ' + data.numero);
                    // Limpiar carrito para nueva venta
                    carrito = [];
                    actualizarCarrito();
                    document.getElementById('cliente').value = 'Cliente General';
                } else {
                    alertaDanger('Error: ' + (data.error || 'Error al procesar venta'));
                }
            })
            .catch(err => {
                alertaDanger('Error al procesar venta: ' + err);
            });
        }

        function nuevaVenta() {
            carrito = [];
            actualizarCarrito();
            document.getElementById('cliente').value = 'Cliente General';
            document.getElementById('metodoPago').value = 'efectivo';
        }

        // Calcular vuelto para pagos en efectivo
        function calcularVuelto() {
            const total = parseFloat(document.getElementById('totalVenta').textContent);
            const recibido = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
            const vuelto = recibido - total;
            
            if (vuelto >= 0) {
                document.getElementById('vueltoMonto').textContent = vuelto.toFixed(2);
                document.getElementById('vueltoContainer').style.display = 'block';
            } else {
                document.getElementById('vueltoContainer').style.display = 'none';
            }
        }

        // Cerrar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#buscarProducto') && !e.target.closest('#resultadosBusqueda')) {
                document.getElementById('resultadosBusqueda').classList.add('d-none');
            }
        });
    </script>
    <script src="heartbeat.js"></script>
</body>
</html>
