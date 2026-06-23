-- =================================================================
-- SISTEMA DE VENTAS E INVENTARIO - RESET COMPLETO DE BASE DE DATOS
-- Versión: 1.0 - Producción
-- ADVERTENCIA: ESTE SCRIPT ELIMINARÁ TODOS LOS DATOS
-- =================================================================

-- =================================================================
-- INSTRUCCIONES IMPORTANTES
-- =================================================================

/*
⚠️  ADVERTENCIA IMPORTANTE ⚠️

ESTE SCRIPT ELIMINARÁ COMPLETAMENTE:
• Todos los productos y lotes
• Todas las ventas y detalles
• Todo el historial de cajas
• Todos los usuarios excepto el administrador principal
• Todo el historial de cambios
• Todas las sesiones activas

NO SE ELIMINARÁ:
• La estructura de las tablas
• El usuario administrador principal
• Las categorías básicas

PARA USAR ESTE SCRIPT:
1. HACER BACKUP COMPLETO ANTES DE EJECUTAR
2. EJECUTAR SOLO SI ESTÁ SEGURO DE ELIMINAR TODO
3. EL SCRIPT SE EJECUTARÁ AUTOMÁTICAMENTE

PARA CANCELAR:
• Cierra esta ventana sin ejecutar
• Comenta las líneas DELETE si quieres conservar datos

*/

-- =================================================================
-- INICIO DEL PROCESO DE RESET
-- =================================================================

-- Desactivar temporalmente las verificaciones de claves foráneas
SET FOREIGN_KEY_CHECKS = 0;

-- =================================================================
-- LIMPIEZA DE DATOS (MANTENIENDO ESTRUCTURA)
-- =================================================================

-- Eliminar todos los lotes de inventario
DELETE FROM lotes;

-- Eliminar todos los productos excepto los básicos
-- (Se conservarán los productos de ejemplo)
DELETE FROM productos WHERE id > 10;

-- Eliminar todas las ventas y detalles
DELETE FROM detalle_venta;
DELETE FROM ventas;

-- Eliminar todo el historial de cajas
DELETE FROM movimientos_caja;
DELETE FROM cajas;

-- Eliminar todo el historial de cambios
DELETE FROM historial_cambios;

-- Eliminar todas las sesiones activas
DELETE FROM sessiones_activas;

-- Eliminar todos los empleados excepto el administrador principal
DELETE FROM empleados WHERE id > 1;

-- =================================================================
-- REINICIALIZAR CONTADORES AUTO_INCREMENT
-- =================================================================

-- Reiniciar contador de lotes
ALTER TABLE lotes AUTO_INCREMENT = 1;

-- Reiniciar contador de productos (conservando los primeros 10)
ALTER TABLE productos AUTO_INCREMENT = 11;

-- Reiniciar contador de ventas
ALTER TABLE ventas AUTO_INCREMENT = 1;

-- Reiniciar contador de detalle de ventas
ALTER TABLE detalle_venta AUTO_INCREMENT = 1;

-- Reiniciar contador de cajas
ALTER TABLE cajas AUTO_INCREMENT = 1;

-- Reiniciar contador de movimientos de caja
ALTER TABLE movimientos_caja AUTO_INCREMENT = 1;

-- Reiniciar contador de historial de cambios
ALTER TABLE historial_cambios AUTO_INCREMENT = 1;

-- Reiniciar contador de sesiones activas
ALTER TABLE sessiones_activas AUTO_INCREMENT = 1;

-- Reiniciar contador de empleados (conservando el admin)
ALTER TABLE empleados AUTO_INCREMENT = 2;

-- =================================================================
-- VERIFICACIÓN Y ESTADO FINAL
-- =================================================================

-- Mostrar estado final de las tablas
SELECT 'Productos' as tabla, COUNT(*) as registros FROM productos
UNION ALL
SELECT 'Lotes' as tabla, COUNT(*) as registros FROM lotes
UNION ALL
SELECT 'Ventas' as tabla, COUNT(*) as registros FROM ventas
UNION ALL
SELECT 'Detalle Ventas' as tabla, COUNT(*) as registros FROM detalle_venta
UNION ALL
SELECT 'Cajas' as tabla, COUNT(*) as registros FROM cajas
UNION ALL
SELECT 'Movimientos Caja' as tabla, COUNT(*) as registros FROM movimientos_caja
UNION ALL
SELECT 'Empleados' as tabla, COUNT(*) as registros FROM empleados
UNION ALL
SELECT 'Historial Cambios' as tabla, COUNT(*) as registros FROM historial_cambios
UNION ALL
SELECT 'Sesiones Activas' as tabla, COUNT(*) as registros FROM sessiones_activas;

-- Reactivar verificaciones de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- =================================================================
-- MENSAJE FINAL
-- =================================================================

SELECT 'RESET COMPLETADO' as mensaje,
       'Base de datos limpiada exitosamente' as estado,
       NOW() as fecha_reset;

-- =================================================================
-- DATOS QUE SE CONSERVAN
-- =================================================================

/*
QUÉ SE MANTIENE DESPUÉS DEL RESET:

1. USUARIO ADMINISTRADOR:
   - ID: 1
   - Usuario: admin
   - Contraseña: password (CAMBIAR DESPUÉS DEL RESET)
   - Rol: administrador

2. CATEGORÍAS BÁSICAS:
   - Bebidas, Snacks, Lácteos, Panadería, Carnes
   - Verduras, Frutas, Limpieza, Aseo Personal
   - Granos, Aceites, Endulzantes, Bebidas Calientes, Otros

3. PRODUCTOS DE EJEMPLO (IDs 1-10):
   - Arroz Premium 1kg
   - Aceite Vegetal 1L
   - Leche Entera 1L
   - Azúcar Blanca 1kg
   - Café Molido 500g
   - Y otros 5 productos básicos

4. ESTRUCTURA COMPLETA:
   - Todas las tablas con sus índices
   - Todas las relaciones foráneas
   - Todas las vistas definidas
   - Todas las configuraciones

QUÉ SE ELIMINÓ COMPLETAMENTE:

1. TODOS LOS LOTES:
   - Entradas de inventario
   - Control de vencimientos
   - Cantidades y precios de compra

2. TODAS LAS VENTAS:
   - Historial completo de ventas
   - Detalles de productos vendidos
   - Métodos de pago utilizados
   - Clientes y montos

3. TODA LA CAJA:
   - Aperturas y cierres
   - Movimientos de ingresos/retiros
   - Diferencias y observaciones

4. TODOS LOS EMPLEADOS (excepto admin):
   - Usuarios creados manualmente
   - Roles asignados
   - Historial de accesos

5. TODO EL HISTORIAL:
   - Cambios en productos
   - Modificaciones de lotes
   - Auditoría completa

6. TODAS LAS SESIONES:
   - Sesiones activas
   - Heartbeats registrados
   - Historial de accesos

ACCIONES RECOMENDADAS DESPUÉS DEL RESET:

1. CAMBIAR CONTRASEÑA DEL ADMIN:
   UPDATE empleados SET password = '$2y$10$NUEVA_CONTRASEÑA_ENCRYPTADA' WHERE id = 1;

2. CREAR NUEVOS EMPLEADOS:
   INSERT INTO empleados (nombre, usuario, password, rol) VALUES (...);

3. REGISTRAR NUEVOS PRODUCTOS:
   INSERT INTO productos (codigo, nombre, categoria, precio_venta, ...) VALUES (...);

4. INGRESAR LOTES INICIALES:
   INSERT INTO lotes (producto_id, cantidad, precio_compra, ...) VALUES (...);

5. ABRIR PRIMERA CAJA:
   INSERT INTO cajas (empleado_id, monto_apertura) VALUES (1, 100.00);

*/

-- =================================================================
-- FIN DEL SCRIPT DE RESET
-- =================================================================
