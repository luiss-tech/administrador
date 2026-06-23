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

// Cargar categorías existentes y categorías usadas
$categorias_db = [];
$categorias_usadas = [];

try {
    // Cargar categorías de la tabla
    $stmt = $db->query("SELECT nombre FROM categorias ORDER BY nombre");
    $categorias_db = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Cargar categorías usadas en productos
    $stmt = $db->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
    $categorias_usadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Combinar y eliminar duplicados
    $todas_categorias = array_unique(array_merge($categorias_db, $categorias_usadas));
    sort($todas_categorias);
} catch (PDOException $e) {
    // Si la tabla categorías no existe, usar solo las de productos
    $stmt = $db->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
    $todas_categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($todas_categorias);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'guardar') {
        // Validar fecha de vencimiento si se proporciona y hay stock inicial
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        $stock_inicial = intval($_POST['stock'] ?? 0);
        
        if ($fecha_vencimiento && $stock_inicial > 0) {
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
                    
                    // Insertar producto con empleado_id
            $stmt = $db->prepare("INSERT INTO productos (codigo, nombre, categoria, precio_venta, stock_minimo, descripcion, codigo_barras, empleado_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['nombre'],
                $_POST['categoria'],
                floatval($_POST['precio_venta']),
                intval($_POST['stock_minimo']),
                $_POST['descripcion'] ?? '',
                $_POST['codigo_barras'] ?? null,
                $empleado_id
            ]);
            
            $producto_id = $db->lastInsertId();
            
            // Si hay stock inicial, crear lote con empleado_id
            $stock_inicial = intval($_POST['stock']);
            if ($stock_inicial > 0) {
                $stmt = $db->prepare("INSERT INTO lotes (producto_id, cantidad, cantidad_disponible, precio_compra, fecha_vencimiento, empleado_id) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $producto_id,
                    $stock_inicial,
                    $stock_inicial,
                    floatval($_POST['precio_compra']),
                    !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
                    $empleado_id
                ]);
            }
            
            $mensaje = "Producto guardado correctamente";
            $tipo_mensaje = "success";
                } catch (PDOException $e) {
                    $mensaje = "Error: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            }
        } else {
            // Si no hay fecha de vencimiento o no hay stock, solo crear el producto
            try {
                // Obtener ID del empleado actual
                $empleado_id = $_SESSION['usuario']['id'] ?? null;
                
                // Insertar producto con empleado_id
                $stmt = $db->prepare("INSERT INTO productos (codigo, nombre, categoria, precio_venta, stock_minimo, descripcion, codigo_barras, empleado_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['codigo'],
                    $_POST['nombre'],
                    $_POST['categoria'],
                    floatval($_POST['precio_venta']),
                    intval($_POST['stock_minimo']),
                    $_POST['descripcion'] ?? '',
                    $_POST['codigo_barras'] ?? null,
                    $empleado_id
                ]);
                
                $producto_id = $db->lastInsertId();
                
                // Si hay stock inicial sin fecha de vencimiento, crear lote
                if ($stock_inicial > 0) {
                    $stmt = $db->prepare("INSERT INTO lotes (producto_id, cantidad, cantidad_disponible, precio_compra, fecha_vencimiento, empleado_id) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $producto_id,
                        $stock_inicial,
                        $stock_inicial,
                        floatval($_POST['precio_compra']),
                        null,
                        $empleado_id
                    ]);
                }
                
                $mensaje = "Producto guardado correctamente";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Generar nuevo código
$stmt = $db->query("SELECT MAX(id) FROM productos");
$max_id = $stmt->fetchColumn() ?: 0;
$nuevo_codigo = 'P' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Productos - Sistema de Gestión</title>
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
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .preview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
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
                    <h1 class="h2"><i class="fas fa-box text-primary"></i> Registrar Nuevo Producto</h1>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <h4 class="mb-4"><i class="fas fa-plus-circle"></i> Información del Producto</h4>
                            
                            <form method="POST" action="" id="formProducto">
                                <input type="hidden" name="action" value="guardar">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Código del Producto</label>
                                        <input type="text" class="form-control" name="codigo" value="<?php echo $nuevo_codigo; ?>" readonly>
                                        <small class="text-muted">Código generado automáticamente</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Código de Barras</label>
                                        <input type="text" class="form-control" name="codigo_barras" placeholder="Ej: 7501234567890">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre del Producto *</label>
                                    <input type="text" class="form-control" name="nombre" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Categoría *</label>
                                        <div class="input-group">
                                            <select class="form-select" name="categoria" id="categoriaSelect" required>
                                                <option value="">Seleccionar</option>
                                                <?php foreach ($todas_categorias as $cat): ?>
                                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline-primary" type="button" onclick="mostrarNuevaCategoria()">
                                                <i class="fas fa-plus"></i> Nueva
                                            </button>
                                        </div>
                                        <div id="nuevaCategoriaDiv" class="mt-2" style="display: none;">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="nuevaCategoria" placeholder="Nueva categoría">
                                                <button class="btn btn-success" type="button" onclick="agregarCategoria()">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-secondary" type="button" onclick="ocultarNuevaCategoria()">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Stock Mínimo *</label>
                                        <input type="number" class="form-control" name="stock_minimo" min="1" value="5" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion" rows="2"></textarea>
                                </div>
                                
                                <h5 class="mt-4 mb-3"><i class="fas fa-warehouse"></i> Stock Inicial</h5>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" class="form-control" name="stock" min="0" value="0">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Precio Compra Unitario</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="precio_compra" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Fecha Vencimiento</label>
                                        <input type="date" class="form-control" name="fecha_vencimiento">
                                    </div>
                                </div>
                                
                                <h5 class="mt-4 mb-3"><i class="fas fa-dollar-sign"></i> Precio de Venta</h5>
                                
                                <div class="mb-4">
                                    <label class="form-label">Precio de Venta *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="precio_venta" step="0.01" min="0.01" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Producto
                                </button>
                                <a href="dashboard_mysql.php" class="btn btn-secondary btn-lg ms-2">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="preview-card">
                            <h5><i class="fas fa-lightbulb"></i> Consejos</h5>
                            <ul class="mb-0 mt-3">
                                <li>El código de barras es opcional pero útil para escáner</li>
                                <li>El stock inicial crea automáticamente un lote</li>
                                <li>Para agregar más stock posteriormente, usa "Ingresar Stock"</li>
                                <li>Define un stock mínimo realista para alertas</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/alertas.js"></script>
    <script>
        function mostrarNuevaCategoria() {
            document.getElementById('nuevaCategoriaDiv').style.display = 'block';
            document.getElementById('nuevaCategoria').focus();
        }

        function ocultarNuevaCategoria() {
            document.getElementById('nuevaCategoriaDiv').style.display = 'none';
            document.getElementById('nuevaCategoria').value = '';
        }

        function agregarCategoria() {
            const nuevaCategoria = document.getElementById('nuevaCategoria').value.trim();
            
            if (!nuevaCategoria) {
                alertaWarning('Por favor ingrese un nombre para la categoría');
                return;
            }
            
            // Verificar si ya existe
            const select = document.getElementById('categoriaSelect');
            const opciones = Array.from(select.options);
            const existe = opciones.some(opt => opt.value.toLowerCase() === nuevaCategoria.toLowerCase());
            
            if (existe) {
                alertaWarning('Esta categoría ya existe');
                return;
            }
            
            // Agregar nueva opción al select
            const option = new Option(nuevaCategoria, nuevaCategoria);
            select.add(option);
            
            // Seleccionar la nueva categoría
            select.value = nuevaCategoria;
            
            // Ocultar formulario y limpiar
            ocultarNuevaCategoria();
            
            alertaSuccess('Categoría agregada correctamente');
        }

        // Permitir agregar con Enter
        document.getElementById('nuevaCategoria').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarCategoria();
            }
        });

        // Permitir cancelar con Escape
        document.getElementById('nuevaCategoria').addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ocultarNuevaCategoria();
            }
        });
    </script>
    <script src="heartbeat.js"></script>
</body>
</html>
