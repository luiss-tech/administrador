<?php
require_once 'session_manager.php';
require_once 'config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Solo administradores pueden gestionar empleados
if ($_SESSION['usuario']['rol'] !== 'administrador') {
    header('Location: dashboard_mysql.php');
    exit();
}

$db = getDB();
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'guardar') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = $_POST['nombre'];
        $usuario = $_POST['usuario'];
        $rol = $_POST['rol'];
        
        try {
            if ($id > 0) {
                // Actualizar
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE empleados SET nombre = ?, usuario = ?, password = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombre, $usuario, $password, $rol, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE empleados SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombre, $usuario, $rol, $id]);
                }
                $mensaje = "Empleado actualizado";
            } else {
                // Nuevo
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO empleados (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $usuario, $password, $rol]);
                $mensaje = "Empleado creado";
            }
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
    
    if ($_POST['action'] === 'eliminar') {
        try {
            $stmt = $db->prepare("UPDATE empleados SET activo = FALSE WHERE id = ?");
            $stmt->execute([intval($_POST['id'])]);
            $mensaje = "Empleado desactivado";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Cargar empleados (excluyendo al administrador principal)
$empleados = $db->query("SELECT * FROM empleados WHERE activo = TRUE AND id != 1 ORDER BY nombre")->fetchAll();

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Sistema de Gestión</title>
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
                    <h1 class="h2"><i class="fas fa-users text-primary"></i> Empleados</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmpleado" onclick="nuevoEmpleado()">
                        <i class="fas fa-plus"></i> Nuevo Empleado
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $e): ?>
                                        <tr>
                                            <td><?php echo $e['id']; ?></td>
                                            <td><?php echo htmlspecialchars($e['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($e['usuario']); ?></td>
                                            <td><span class="badge bg-<?php echo $e['rol'] == 'administrador' ? 'danger' : 'primary'; ?>"><?php echo $e['rol']; ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editarEmpleado(<?php echo htmlspecialchars(json_encode($e)); ?>)" data-bs-toggle="modal" data-bs-target="#modalEmpleado">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar empleado?')">
                                                    <input type="hidden" name="action" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Modal -->
    <div class="modal fade" id="modalEmpleado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModal">Nuevo Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="guardar">
                        <input type="hidden" id="empleadoId" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="empleadoNombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" class="form-control" id="empleadoUsuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña <?php echo $id ?? '<small class="text-muted">(dejar en blanco para mantener)</small>'; ?></label>
                            <input type="password" class="form-control" id="empleadoPassword" name="password">
                            <small class="text-muted" id="passwordHelp">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" id="empleadoRol" name="rol" required>
                                <option value="">Seleccionar</option>
                                <option value="administrador">Administrador</option>
                                <option value="empleado">Empleado</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function nuevoEmpleado() {
            document.getElementById('tituloModal').textContent = 'Nuevo Empleado';
            document.getElementById('empleadoId').value = '';
            document.getElementById('empleadoNombre').value = '';
            document.getElementById('empleadoUsuario').value = '';
            document.getElementById('empleadoPassword').value = '';
            document.getElementById('empleadoPassword').required = true;
            document.getElementById('empleadoRol').value = '';
        }
        
        function editarEmpleado(empleado) {
            document.getElementById('tituloModal').textContent = 'Editar Empleado';
            document.getElementById('empleadoId').value = empleado.id;
            document.getElementById('empleadoNombre').value = empleado.nombre;
            document.getElementById('empleadoUsuario').value = empleado.usuario;
            document.getElementById('empleadoPassword').value = '';
            document.getElementById('empleadoPassword').required = false;
            document.getElementById('passwordHelp').textContent = 'Dejar en blanco para mantener actual';
            document.getElementById('empleadoRol').value = empleado.rol;
        }
    </script>
    <script src="heartbeat.js"></script>
</body>
</html>
