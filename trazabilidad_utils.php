<?php
/**
 * Utilidades de trazabilidad para el sistema
 * Registra todas las acciones críticas con auditoría completa
 */

require_once 'config/database.php';

/**
 * Registra un cambio en el historial de trazabilidad
 * @param string $tabla Tabla afectada (productos, lotes, cajas, etc.)
 * @param int $registro_id ID del registro modificado
 * @param string $accion Tipo de acción (crear, editar, desactivar, eliminar)
 * @param int $empleado_id ID del empleado que realiza la acción
 * @param array $valores_anteriores Valores antes del cambio
 * @param array $valores_nuevos Valores después del cambio
 * @param string $descripcion Descripción detallada del cambio
 * @return bool
 */
function registrarTrazabilidad($tabla, $registro_id, $accion, $empleado_id, $valores_anteriores = null, $valores_nuevos = null, $descripcion = '') {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO historial_cambios 
            (tabla_afectada, registro_id, tipo_accion, empleado_id, valores_anteriores, valores_nuevos, descripcion, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        return $stmt->execute([
            $tabla,
            $registro_id,
            $accion,
            $empleado_id,
            $valores_anteriores ? json_encode($valores_anteriores) : null,
            $valores_nuevos ? json_encode($valores_nuevos) : null,
            $descripcion,
            $ip
        ]);
        
    } catch (PDOException $e) {
        error_log("Error al registrar trazabilidad: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene valores actuales de un registro para comparación
 * @param string $tabla Nombre de la tabla
 * @param int $registro_id ID del registro
 * @return array|null
 */
function obtenerValoresActuales($tabla, $registro_id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("SELECT * FROM $tabla WHERE id = ?");
        $stmt->execute([$registro_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error al obtener valores actuales: " . $e->getMessage());
        return null;
    }
}

/**
 * Compara dos arrays y devuelve las diferencias
 * @param array $anteriores Valores anteriores
 * @param array $nuevos Valores nuevos
 * @return array Cambios detectados
 */
function compararCambios($anteriores, $nuevos) {
    $cambios = [];
    
    foreach ($nuevos as $campo => $valor_nuevo) {
        $valor_anterior = $anteriores[$campo] ?? null;
        
        if ($valor_anterior !== $valor_nuevo) {
            $cambios[$campo] = [
                'anterior' => $valor_anterior,
                'nuevo' => $valor_nuevo
            ];
        }
    }
    
    return $cambios;
}

/**
 * Genera descripción automática de cambios
 * @param array $cambios Array de cambios detectados
 * @param string $tabla Nombre de la tabla
 * @return string
 */
function generarDescripcionCambios($cambios, $tabla) {
    if (empty($cambios)) {
        return "Sin cambios detectados";
    }
    
    $descripciones = [];
    
    foreach ($cambios as $campo => $cambio) {
        $campo_formateado = ucwords(str_replace('_', ' ', $campo));
        
        if ($cambio['anterior'] === null) {
            $descripciones[] = "$campo_formateado: establecido en '{$cambio['nuevo']}'";
        } elseif ($cambio['nuevo'] === null) {
            $descripciones[] = "$campo_formateado: eliminado (era '{$cambio['anterior']}')";
        } else {
            $descripciones[] = "$campo_formateado: '{$cambio['anterior']}' → '{$cambio['nuevo']}'";
        }
    }
    
    return "Modificación en $tabla: " . implode(', ', $descripciones);
}

/**
 * Verifica si la tabla de historial existe
 * @return bool
 */
function tablaHistorialExiste() {
    $db = getDB();
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'historial_cambios'");
        return $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crea la tabla de historial si no existe (fallback)
 * @return bool
 */
function asegurarTablaHistorial() {
    if (tablaHistorialExiste()) {
        return true;
    }
    
    $db = getDB();
    
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS historial_cambios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tabla_afectada VARCHAR(50) NOT NULL,
                registro_id INT NOT NULL,
                tipo_accion ENUM('crear', 'editar', 'desactivar', 'eliminar') NOT NULL,
                empleado_id INT NOT NULL,
                fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                valores_anteriores JSON NULL,
                valores_nuevos JSON NULL,
                descripcion TEXT NULL,
                ip_address VARCHAR(45) NULL,
                INDEX idx_tabla_registro (tabla_afectada, registro_id),
                INDEX idx_empleado (empleado_id),
                INDEX idx_fecha (fecha)
            )
        ";
        
        $db->exec($sql);
        return true;
        
    } catch (PDOException $e) {
        error_log("Error al crear tabla de historial: " . $e->getMessage());
        return false;
    }
}
?>
