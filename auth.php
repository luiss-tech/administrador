<?php
session_start();

require_once 'config/database.php';

function verificarEmpleado($usuario, $password) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM empleados WHERE usuario = ? AND activo = TRUE LIMIT 1");
        $stmt->execute([$usuario]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empleado) {
            // Verificar contraseña (compatibilidad con password_hash)
            if (password_verify($password, $empleado['password'])) {
                return [
                    'id' => $empleado['id'],
                    'nombre' => $empleado['nombre'],
                    'usuario' => $empleado['usuario'],
                    'rol' => $empleado['rol']
                ];
            }
            // Fallback para contraseñas en texto plano (migración desde CSV)
            if ($empleado['password'] === $password) {
                return [
                    'id' => $empleado['id'],
                    'nombre' => $empleado['nombre'],
                    'usuario' => $empleado['usuario'],
                    'rol' => $empleado['rol']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error en verificarEmpleado: " . $e->getMessage());
    }
    
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $empleado = verificarEmpleado($usuario, $password);
        
        if ($empleado) {
            $_SESSION['usuario'] = $empleado;
            
            // Verificar estado de caja al iniciar sesión
            require_once 'caja_utils.php';
            $caja_estado = verificarCajaAbierta();
            
            // Si hay caja abierta de otro usuario y no es admin, redirigir a caja para forzar cierre
            if ($caja_estado['abierta'] && $caja_estado['empleado_id'] != $empleado['id'] && $empleado['rol'] !== 'administrador') {
                header('Location: caja.php?alerta=caja_otro_usuario');
                exit();
            }
            
            // Si hay caja abierta de otro usuario y es admin, mostrar advertencia en dashboard
            if ($caja_estado['abierta'] && $caja_estado['empleado_id'] != $empleado['id'] && $empleado['rol'] === 'administrador') {
                $_SESSION['caja_alerta'] = $caja_estado;
            }
            
            // Verificar cierre automático (si son más de las 3 AM y hay caja abierta de ayer)
            if ($caja_estado['abierta']) {
                verificarCierreAutomatico();
            }
            
            // Redirigir según rol
            if ($empleado['rol'] === 'administrador') {
                header('Location: dashboard_mysql.php');
            } else {
                header('Location: ventas_mysql.php');
            }
            exit();
        } else {
            header('Location: index.php?error=1');
            exit();
        }
    } else {
        header('Location: index.php?error=1');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
