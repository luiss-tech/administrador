<?php
/**
 * Utilidades para el control de caja
 * Funciones compartidas entre auth.php, caja.php y otros archivos
 */

require_once 'config/database.php';

/**
 * Verifica si hay una caja abierta en el sistema
 * @return array ['abierta' => bool, 'caja_id' => int|null, 'empleado_id' => int|null, 'datos' => array|null]
 */
function verificarCajaAbierta() {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT c.*, e.nombre as empleado_nombre, e.usuario as empleado_usuario
        FROM cajas c
        JOIN empleados e ON c.empleado_id = e.id
        WHERE c.estado = 'abierta'
        ORDER BY c.id DESC
        LIMIT 1
    ");
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($caja) {
        return [
            'abierta' => true,
            'caja_id' => $caja['id'],
            'empleado_id' => $caja['empleado_id'],
            'empleado_nombre' => $caja['empleado_nombre'],
            'empleado_usuario' => $caja['empleado_usuario'],
            'fecha_apertura' => $caja['fecha_apertura'],
            'monto_apertura' => $caja['monto_apertura'],
            'datos' => $caja
        ];
    }
    
    return [
        'abierta' => false,
        'caja_id' => null,
        'empleado_id' => null,
        'empleado_nombre' => null,
        'empleado_usuario' => null,
        'fecha_apertura' => null,
        'monto_apertura' => null,
        'datos' => null
    ];
}

/**
 * Cierra una caja de forma forzada (por admin o por cambio de turno)
 * @param int $caja_id ID de la caja a cerrar
 * @param int $empleado_id ID del empleado que cierra forzosamente
 * @param string $motivo Motivo del cierre forzado
 * @return bool true si se cerró correctamente
 */
function cerrarCajaForzada($caja_id, $empleado_id, $motivo = '') {
    $db = getDB();
    
    // Obtener datos de la caja
    $stmt = $db->prepare("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'");
    $stmt->execute([$caja_id]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        return false;
    }
    
    // Calcular totales de ventas
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END), 0) as ventas_efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago = 'tarjeta' THEN total ELSE 0 END), 0) as ventas_tarjeta,
            COALESCE(SUM(CASE WHEN metodo_pago = 'yape' THEN total ELSE 0 END), 0) as ventas_yape,
            COALESCE(SUM(CASE WHEN metodo_pago = 'transferencia' THEN total ELSE 0 END), 0) as ventas_transferencia
        FROM ventas 
        WHERE caja_id = ?
    ");
    $stmt->execute([$caja_id]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular efectivo esperado
    $efectivo_esperado = $caja['monto_apertura'] 
                       + $totales['ventas_efectivo'] 
                       + $caja['total_ingresos'] 
                       - $caja['total_retiros'];
    
    $observaciones = 'Cierre forzado.';
    if (!empty($motivo)) {
        $observaciones .= ' Motivo: ' . $motivo;
    }
    
    $stmt = $db->prepare("
        UPDATE cajas 
        SET estado = 'cerrada',
            fecha_cierre = NOW(),
            monto_cierre = ?,
            empleado_cierre_id = ?,
            total_ventas_efectivo = ?,
            total_ventas_tarjeta = ?,
            total_ventas_yape = ?,
            total_ventas_transferencia = ?,
            tipo_cierre = 'forzado',
            diferencia = 0,
            observaciones = CONCAT(IFNULL(observaciones, ''), ' ', ?)
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $efectivo_esperado,
        $empleado_id,
        $totales['ventas_efectivo'], 
        $totales['ventas_tarjeta'],
        $totales['ventas_yape'],
        $totales['ventas_transferencia'],
        $observaciones,
        $caja_id
    ]);
    
    // Si el cierre fue exitoso, crear backup automático
    if ($result) {
        crearBackupCierreCaja($caja_id, $empleado_id, $efectivo_esperado, $totales);
    }
    
    return $result;
}

/**
 * Crea un backup automático al cerrar caja
 * @param int $caja_id ID de la caja cerrada
 * @param int $empleado_id ID del empleado que cierra
 * @param float $efectivo_cierre Monto de efectivo en cierre
 * @param array $totales Totales de ventas
 */
function crearBackupCierreCaja($caja_id, $empleado_id, $efectivo_cierre, $totales) {
    try {
        // Incluir backup utils si no está incluido
        if (!class_exists('BackupManager')) {
            require_once __DIR__ . '/backup_utils.php';
        }
        
        $backup = new BackupManager();
        
        // Generar descripción detallada del backup
        $descripcion = "Backup automático - Cierre de caja #{$caja_id}\n";
        $descripcion .= "Empleado: " . obtenerNombreEmpleado($empleado_id) . "\n";
        $descripcion .= "Fecha: " . date('d/m/Y H:i') . "\n";
        $descripcion .= "Efectivo: S/. " . number_format($efectivo_cierre, 2) . "\n";
        $descripcion .= "Ventas totales: S/. " . number_format(array_sum($totales), 2) . "\n";
        $descripcion .= "Métodos: Efectivo(S/. " . number_format($totales['ventas_efectivo'], 2) . ") ";
        $descripcion .= "Tarjeta(S/. " . number_format($totales['ventas_tarjeta'], 2) . ") ";
        $descripcion .= "Yape(S/. " . number_format($totales['ventas_yape'], 2) . ") ";
        $descripcion .= "Transferencia(S/. " . number_format($totales['ventas_transferencia'], 2) . ")";
        
        // Crear backup
        $result = $backup->createBackup($descripcion);
        
        // Registrar en logs si hay error
        if (!$result['success']) {
            error_log("Error al crear backup automático de cierre de caja: " . $result['message']);
        }
        
    } catch (Exception $e) {
        error_log("Excepción al crear backup automático de cierre de caja: " . $e->getMessage());
    }
}

/**
 * Obtiene el nombre del empleado por ID
 */
function obtenerNombreEmpleado($empleado_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT nombre FROM empleados WHERE id = ?");
        $stmt->execute([$empleado_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['nombre'] : 'Desconocido';
    } catch (Exception $e) {
        return 'Desconocido';
    }
}

/**
 * Obtiene el saldo actual de efectivo en caja
 * @param array $caja Datos de la caja
 * @param array $totales_ventas Totales de ventas por método de pago
 * @return float Saldo de efectivo
 */
function calcularSaldoCaja($caja, $totales_ventas) {
    return $caja['monto_apertura'] 
           + $totales_ventas['ventas_efectivo'] 
           + $caja['total_ingresos'] 
           - $caja['total_retiros'];
}
