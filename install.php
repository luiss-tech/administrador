<?php
/**
 * Instalador del Sistema de Ventas e Inventario
 * Proceso simple para configuración inicial
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$message = '';
$error = '';

// Verificar si el sistema ya está instalado
if (file_exists('config/installed.lock')) {
    die('<div class="alert alert-warning">El sistema ya está instalado. Para reinstalar, elimina el archivo config/installed.lock</div>');
}

// Procesar cada paso
switch ($step) {
    case 2:
        $message = procesarPaso2();
        break;
    case 3:
        $message = procesarPaso3();
        break;
    case 4:
        $message = procesarPaso4();
        break;
}

function procesarPaso2() {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    try {
        // Probar conexión
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Guardar configuración
        $config = "<?php\n";
        $config .= "define('DB_HOST', '$db_host');\n";
        $config .= "define('DB_NAME', '$db_name');\n";
        $config .= "define('DB_USER', '$db_user');\n";
        $config .= "define('DB_PASS', '$db_pass');\n";
        $config .= "define('DB_CHARSET', 'utf8mb4');\n";
        $config .= "define('DB_COLLATE', 'utf8mb4_unicode_ci');\n";
        
        if (!file_exists('config')) {
            mkdir('config', 0755, true);
        }
        
        file_put_contents('config/database.php', $config);
        
        return ['success' => true, 'message' => 'Conexión a base de datos configurada correctamente'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()];
    }
}

function procesarPaso3() {
    try {
        require_once 'config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Ejecutar script de instalación
        $sql = file_get_contents('database_completa.sql');
        $pdo->exec($sql);
        
        // Crear usuario administrador
        $admin_name = $_POST['admin_name'] ?? '';
        $admin_user = $_POST['admin_user'] ?? '';
        $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        $admin_email = $_POST['admin_email'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO empleados (nombre, usuario, password, email, rol, activo) VALUES (?, ?, ?, ?, 'administrador', 1)");
        $stmt->execute([$admin_name, $admin_user, $admin_pass, $admin_email]);
        
        return ['success' => true, 'message' => 'Base de datos creada y administrador configurado'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al crear base de datos: ' . $e->getMessage()];
    }
}

function procesarPaso4() {
    try {
        // Crear archivo de bloqueo
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        return ['success' => true, 'message' => '¡Instalación completada!'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al finalizar instalación: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .form-floating {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="text-center mb-4">
            <h1><i class="fas fa-store text-primary"></i></h1>
            <h2>Sistema de Ventas</h2>
            <p class="text-muted">Asistente de Instalación</p>
        </div>

        <!-- Indicador de Pasos -->
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'completed' : 'active'; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? 'completed' : ($step == 2 ? 'active' : ''); ?>">2</div>
            <div class="step <?php echo $step >= 3 ? 'completed' : ($step == 3 ? 'active' : ''); ?>">3</div>
            <div class="step <?php echo $step >= 4 ? 'completed' : ($step == 4 ? 'active' : ''); ?>">4</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo $message['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Paso 1: Bienvenida -->
        <?php if ($step == 1): ?>
            <div class="text-center">
                <h3>Bienvenido al Sistema de Ventas</h3>
                <p class="text-muted mb-4">
                    Este asistente te guiará paso a paso en la configuración inicial del sistema.
                </p>
                <p class="mb-4">
                    <strong>Requisitos:</strong><br>
                    ✅ PHP 7.4 o superior<br>
                    ✅ MySQL 5.7 o superior<br>
                    ✅ Extensiones: PDO, MySQL, JSON
                </p>
                <a href="?step=2" class="btn btn-primary btn-lg">
                    <i class="fas fa-arrow-right"></i> Comenzar Instalación
                </a>
            </div>

        <!-- Paso 2: Configuración de Base de Datos -->
        <?php elseif ($step == 2): ?>
            <h3>Configuración de Base de Datos</h3>
            <p class="text-muted mb-4">Ingresa los datos de tu base de datos MySQL</p>
            
            <form method="POST" action="?step=3">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                    <label>Host de la Base de Datos</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="db_name" required>
                    <label>Nombre de la Base de Datos</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="db_user" required>
                    <label>Usuario de la Base de Datos</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" name="db_pass" required>
                    <label>Contraseña de la Base de Datos</label>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="?step=1" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Atrás
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Continuar
                    </button>
                </div>
            </form>

        <!-- Paso 3: Crear Administrador -->
        <?php elseif ($step == 3): ?>
            <h3>Crear Cuenta de Administrador</h3>
            <p class="text-muted mb-4">Configura la cuenta principal del sistema</p>
            
            <form method="POST" action="?step=4">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="admin_name" required>
                    <label>Nombre Completo</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="admin_user" required>
                    <label>Usuario</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" name="admin_email" required>
                    <label>Correo Electrónico</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" name="admin_pass" required minlength="6">
                    <label>Contraseña (mínimo 6 caracteres)</label>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="?step=2" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Atrás
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Finalizar Instalación
                    </button>
                </div>
            </form>

        <!-- Paso 4: Completado -->
        <?php elseif ($step == 4): ?>
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                </div>
                <h3>¡Instalación Completada!</h3>
                <p class="text-muted mb-4">
                    El sistema está listo para usar. Se ha creado la base de datos y el usuario administrador.
                </p>
                
                <div class="alert alert-info">
                    <strong>Recomendaciones de seguridad:</strong><br>
                    🔒 Elimina el archivo install.php después de la instalación<br>
                    🔒 Cambia permisos del directorio config/ a 755<br>
                    🔒 Realiza backups regularmente
                </div>
                
                <a href="index.php" class="btn btn-success btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
