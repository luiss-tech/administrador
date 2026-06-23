-- =================================================================
-- SISTEMA DE VENTAS E INVENTARIO - BASE DE DATOS COMPLETA
-- Versión: 1.0 - Producción
-- Descripción: Sistema completo con FIFO, trazabilidad, cajas y backups
-- =================================================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS gestion_inventario 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE gestion_inventario;

-- =================================================================
-- TABLA DE EMPLEADOS/USUARIOS
-- Gestiona el acceso al sistema con roles y permisos
-- =================================================================
CREATE TABLE IF NOT EXISTS empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del empleado',
    usuario VARCHAR(50) NOT NULL UNIQUE COMMENT 'Usuario para inicio de sesión',
    password VARCHAR(255) NOT NULL COMMENT 'Contraseña encriptada (bcrypt)',
    rol ENUM('administrador', 'empleado') DEFAULT 'empleado' COMMENT 'Rol del usuario',
    email VARCHAR(100) NULL COMMENT 'Correo electrónico',
    telefono VARCHAR(20) NULL COMMENT 'Teléfono de contacto',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Estado del usuario',
    ultimo_login TIMESTAMP NULL COMMENT 'Último inicio de sesión',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de actualización',
    
    INDEX idx_usuario (usuario),
    INDEX idx_rol (rol),
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Tabla de empleados y usuarios del sistema';

-- =================================================================
-- TABLA DE CATEGORÍAS
-- Clasificación de productos para mejor organización
-- =================================================================
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nombre único de la categoría',
    descripcion TEXT NULL COMMENT 'Descripción detallada de la categoría',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de actualización',
    
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB COMMENT='Categorías de productos';

-- =================================================================
-- TABLA DE PRODUCTOS
-- Catálogo de productos con control de stock y precios
-- =================================================================
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código interno único',
    codigo_barras VARCHAR(50) NULL COMMENT 'Código de barras EAN-13',
    nombre VARCHAR(150) NOT NULL COMMENT 'Nombre del producto',
    categoria VARCHAR(50) NULL COMMENT 'Categoría del producto',
    precio_venta DECIMAL(10,2) NOT NULL COMMENT 'Precio de venta actual',
    stock_minimo INT DEFAULT 10 COMMENT 'Stock mínimo para alertas',
    descripcion TEXT NULL COMMENT 'Descripción detallada',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Producto activo/inactivo',
    empleado_id INT NULL COMMENT 'ID del empleado que lo registró',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de actualización',
    
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
    INDEX idx_codigo_barras (codigo_barras),
    INDEX idx_nombre (nombre),
    INDEX idx_categoria (categoria),
    INDEX idx_activo (activo)
) ENGINE=InnoDB COMMENT='Catálogo de productos del sistema';

-- =================================================================
-- TABLA DE LOTES (ENTRADAS DE INVENTARIO)
-- Control de stock por lotes con FIFO y vencimientos
-- =================================================================
CREATE TABLE IF NOT EXISTS lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL COMMENT 'ID del producto asociado',
    cantidad INT NOT NULL COMMENT 'Cantidad inicial del lote',
    cantidad_disponible INT NOT NULL COMMENT 'Cantidad disponible para venta',
    precio_compra DECIMAL(10,2) NOT NULL COMMENT 'Precio de compra unitario',
    fecha_vencimiento DATE NULL COMMENT 'Fecha de vencimiento (si aplica)',
    fecha_ingreso TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de ingreso al inventario',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Lote activo/inactivo',
    empleado_id INT NULL COMMENT 'ID del empleado que registró el lote',
    proveedor VARCHAR(100) NULL COMMENT 'Nombre del proveedor',
    factura VARCHAR(50) NULL COMMENT 'Número de factura de compra',
    
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
    INDEX idx_producto (producto_id),
    INDEX idx_vencimiento (fecha_vencimiento),
    INDEX idx_activo (activo, cantidad_disponible),
    INDEX idx_fecha_ingreso (fecha_ingreso)
) ENGINE=InnoDB COMMENT='Control de lotes de inventario con sistema FIFO';

-- =================================================================
-- TABLA DE CAJAS
-- Control de efectivo y movimientos de caja
-- =================================================================
CREATE TABLE IF NOT EXISTS cajas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL COMMENT 'ID del empleado que abrió la caja',
    empleado_cierre_id INT NULL COMMENT 'ID del empleado que cerró la caja',
    fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de apertura',
    fecha_cierre TIMESTAMP NULL COMMENT 'Fecha y hora de cierre',
    monto_apertura DECIMAL(10,2) NOT NULL COMMENT 'Monto inicial de apertura',
    monto_cierre DECIMAL(10,2) NULL COMMENT 'Monto real al cierre',
    total_ventas_efectivo DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ventas en efectivo',
    total_ventas_tarjeta DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ventas con tarjeta',
    total_ventas_yape DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ventas con Yape',
    total_ventas_transferencia DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ventas por transferencia',
    total_ingresos DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ingresos manuales',
    total_retiros DECIMAL(10,2) DEFAULT 0 COMMENT 'Total retiros manuales',
    estado ENUM('abierta', 'cerrada') DEFAULT 'abierta' COMMENT 'Estado de la caja',
    tipo_cierre ENUM('manual', 'forzado') DEFAULT 'manual' COMMENT 'Tipo de cierre: manual o forzado por admin',
    diferencia DECIMAL(10,2) NULL COMMENT 'Diferencia entre esperado y real',
    observaciones TEXT NULL COMMENT 'Observaciones del cierre',
    
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE RESTRICT,
    FOREIGN KEY (empleado_cierre_id) REFERENCES empleados(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_apertura),
    INDEX idx_empleado (empleado_id)
) ENGINE=InnoDB COMMENT='Control de cajas del sistema';

-- =================================================================
-- TABLA DE MOVIMIENTOS DE CAJA
-- Registro de ingresos y retiros manuales
-- =================================================================
CREATE TABLE IF NOT EXISTS movimientos_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caja_id INT NOT NULL COMMENT 'ID de la caja asociada',
    tipo ENUM('ingreso', 'retiro') NOT NULL COMMENT 'Tipo de movimiento',
    monto DECIMAL(10,2) NOT NULL COMMENT 'Monto del movimiento',
    concepto VARCHAR(255) NOT NULL COMMENT 'Concepto o motivo del movimiento',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha del movimiento',
    empleado_id INT NOT NULL COMMENT 'ID del empleado que realizó el movimiento',
    
    FOREIGN KEY (caja_id) REFERENCES cajas(id) ON DELETE CASCADE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE RESTRICT,
    INDEX idx_caja (caja_id),
    INDEX idx_tipo (tipo),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB COMMENT='Movimientos manuales de caja';

-- =================================================================
-- TABLA DE VENTAS
-- Registro principal de ventas con métodos de pago
-- =================================================================
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) NOT NULL UNIQUE COMMENT 'Número único de venta',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la venta',
    total DECIMAL(10,2) NOT NULL COMMENT 'Total de la venta',
    costo_total DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Costo total de productos',
    ganancia DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Ganancia total de la venta',
    cliente VARCHAR(100) DEFAULT 'Cliente General' COMMENT 'Nombre del cliente',
    dni VARCHAR(20) NULL COMMENT 'DNI del cliente',
    direccion VARCHAR(200) NULL COMMENT 'Dirección de entrega',
    telefono VARCHAR(20) NULL COMMENT 'Teléfono del cliente',
    empleado_id INT NULL COMMENT 'ID del vendedor',
    metodo_pago ENUM('efectivo', 'tarjeta', 'yape', 'transferencia') DEFAULT 'efectivo' COMMENT 'Método de pago',
    tarjeta_ultimos4 VARCHAR(4) NULL COMMENT 'Últimos 4 dígitos de tarjeta',
    yape_numero VARCHAR(20) NULL COMMENT 'Número de Yape',
    transferencia_banco VARCHAR(50) NULL COMMENT 'Banco de transferencia',
    transferencia_operacion VARCHAR(50) NULL COMMENT 'Número de operación',
    caja_id INT NULL COMMENT 'ID de la caja asociada',
    factura_emitida BOOLEAN DEFAULT FALSE COMMENT 'Factura emitida',
    factura_numero VARCHAR(50) NULL COMMENT 'Número de factura',
    
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
    FOREIGN KEY (caja_id) REFERENCES cajas(id) ON DELETE SET NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_caja (caja_id),
    INDEX idx_metodo_pago (metodo_pago),
    INDEX idx_numero (numero),
    INDEX idx_cliente (cliente)
) ENGINE=InnoDB COMMENT='Registro de ventas del sistema';

-- =================================================================
-- TABLA DE DETALLE DE VENTAS
-- Detalle de productos vendidos con trazabilidad de lotes
-- =================================================================
CREATE TABLE IF NOT EXISTS detalle_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL COMMENT 'ID de la venta principal',
    producto_id INT NOT NULL COMMENT 'ID del producto vendido',
    lote_id INT NOT NULL COMMENT 'ID del lote consumido',
    cantidad INT NOT NULL COMMENT 'Cantidad vendida',
    precio_venta DECIMAL(10,2) NOT NULL COMMENT 'Precio de venta unitario',
    costo_unitario DECIMAL(10,2) NOT NULL COMMENT 'Costo unitario del lote',
    ganancia DECIMAL(10,2) NOT NULL COMMENT 'Ganancia unitaria',
    descuento DECIMAL(10,2) DEFAULT 0 COMMENT 'Descuento aplicado',
    
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE RESTRICT,
    INDEX idx_venta (venta_id),
    INDEX idx_producto (producto_id),
    INDEX idx_lote (lote_id)
) ENGINE=InnoDB COMMENT='Detalle de ventas con trazabilidad FIFO';

-- =================================================================
-- TABLA DE HISTORIAL DE CAMBIOS
-- Trazabilidad completa de todas las modificaciones
-- =================================================================
CREATE TABLE IF NOT EXISTS historial_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(50) NOT NULL COMMENT 'Tabla que fue modificada',
    registro_id INT NOT NULL COMMENT 'ID del registro modificado',
    tipo_accion ENUM('crear', 'editar', 'desactivar', 'eliminar') NOT NULL COMMENT 'Tipo de acción realizada',
    empleado_id INT NOT NULL COMMENT 'ID del empleado que realizó la acción',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora del cambio',
    valores_anteriores JSON NULL COMMENT 'Valores antes del cambio',
    valores_nuevos JSON NULL COMMENT 'Valores después del cambio',
    descripcion TEXT NULL COMMENT 'Descripción detallada del cambio',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del usuario',
    
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE RESTRICT,
    INDEX idx_tabla_registro (tabla_afectada, registro_id),
    INDEX idx_empleado (empleado_id),
    INDEX idx_fecha (fecha),
    INDEX idx_tipo_accion (tipo_accion)
) ENGINE=InnoDB COMMENT='Historial de cambios del sistema';

-- =================================================================
-- TABLA DE SESIONES ACTIVAS
-- Control de sesiones de usuarios con heartbeat y cierre automático
-- =================================================================
CREATE TABLE IF NOT EXISTS sessiones_activas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL COMMENT 'ID único de sesión PHP',
    empleado_id INT NOT NULL COMMENT 'ID del empleado dueño de la sesión',
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de inicio de sesión',
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Último heartbeat recibido',
    logout_time TIMESTAMP NULL COMMENT 'Fecha y hora de cierre de sesión',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del usuario',
    user_agent TEXT NULL COMMENT 'Navegador y sistema del usuario',
    status ENUM('active', 'expired', 'logout', 'closed') DEFAULT 'active' COMMENT 'Estado actual de la sesión',
    observaciones TEXT NULL COMMENT 'Notas adicionales sobre el cierre',
    
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_empleado (empleado_id),
    INDEX idx_heartbeat (last_heartbeat),
    INDEX idx_status (status),
    INDEX idx_login_time (login_time),
    INDEX idx_logout_time (logout_time)
) ENGINE=InnoDB COMMENT='Sesiones activas del sistema con control de heartbeat';

-- =================================================================
-- VISTAS OPTIMIZADAS
-- Vistas para consultas frecuentes y reportes
-- =================================================================

-- Vista de stock actual con estado
CREATE OR REPLACE VIEW vista_stock AS
SELECT 
    p.id AS producto_id,
    p.codigo,
    p.codigo_barras,
    p.nombre,
    p.categoria,
    p.precio_venta,
    p.stock_minimo,
    p.descripcion,
    COALESCE(SUM(l.cantidad_disponible), 0) AS stock_actual,
    COUNT(l.id) AS total_lotes,
    CASE 
        WHEN COALESCE(SUM(l.cantidad_disponible), 0) = 0 THEN 'sin_stock'
        WHEN COALESCE(SUM(l.cantidad_disponible), 0) <= p.stock_minimo THEN 'bajo'
        ELSE 'ok'
    END AS estado_stock,
    p.acto,
    p.created_at
FROM productos p
LEFT JOIN lotes l ON p.id = l.producto_id AND l.activo = TRUE AND l.cantidad_disponible > 0
WHERE p.activo = TRUE
GROUP BY p.id, p.codigo, p.codigo_barras, p.nombre, p.categoria, p.precio_venta, p.stock_minimo, p.descripcion, p.activo, p.created_at;

-- Vista de vencimientos próximos
CREATE OR REPLACE VIEW vista_vencimientos AS
SELECT 
    p.id AS producto_id,
    p.codigo,
    p.nombre,
    p.categoria,
    l.id AS lote_id,
    l.cantidad_disponible,
    l.precio_compra,
    l.fecha_vencimiento,
    l.fecha_ingreso,
    DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer,
    CASE 
        WHEN l.fecha_vencimiento < CURDATE() THEN 'vencido'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 7 THEN 'critico'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 'proximo'
        ELSE 'ok'
    END AS estado_vencimiento,
    CASE 
        WHEN l.fecha_vencimiento < CURDATE() THEN 'danger'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 7 THEN 'warning'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 'info'
        ELSE 'success'
    END AS color_estado
FROM productos p
INNER JOIN lotes l ON p.id = l.producto_id
WHERE l.activo = TRUE 
    AND l.cantidad_disponible > 0 
    AND l.fecha_vencimiento IS NOT NULL
ORDER BY l.fecha_vencimiento ASC;

-- Vista de resumen de ventas diarias
CREATE OR REPLACE VIEW vista_ventas_diarias AS
SELECT 
    DATE(fecha) AS fecha,
    COUNT(*) AS total_ventas,
    SUM(total) AS total_ingresos,
    SUM(costo_total) AS total_costos,
    SUM(ganancia) AS total_ganancias,
    COUNT(DISTINCT empleado_id) AS vendedores_activos,
    COUNT(DISTINCT caja_id) AS cajas_usadas,
    SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END) AS efectivo,
    SUM(CASE WHEN metodo_pago = 'tarjeta' THEN total ELSE 0 END) AS tarjeta,
    SUM(CASE WHEN metodo_pago = 'yape' THEN total ELSE 0 END) AS yape,
    SUM(CASE WHEN metodo_pago = 'transferencia' THEN total ELSE 0 END) AS transferencia
FROM ventas
GROUP BY DATE(fecha)
ORDER BY fecha DESC;

-- Vista de sesiones activas y su estado
CREATE OR REPLACE VIEW vista_sesiones_activas AS
SELECT 
    sa.id,
    sa.session_id,
    sa.empleado_id,
    e.nombre as empleado_nombre,
    e.usuario as empleado_usuario,
    sa.login_time,
    sa.last_heartbeat,
    sa.logout_time,
    sa.ip_address,
    sa.status,
    sa.observaciones,
    CASE 
        WHEN sa.status = 'active' THEN 'Activa'
        WHEN sa.status = 'expired' THEN 'Expirada'
        WHEN sa.status = 'logout' THEN 'Cerrada'
        WHEN sa.status = 'closed' THEN 'Cerrada abruptamente'
        ELSE 'Desconocido'
    END AS estado_descripcion,
    CASE 
        WHEN sa.status = 'active' THEN 'success'
        WHEN sa.status = 'expired' THEN 'warning'
        WHEN sa.status = 'logout' THEN 'info'
        WHEN sa.status = 'closed' THEN 'danger'
        ELSE 'secondary'
    END AS color_estado,
    TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) AS minutos_inactividad,
    CASE 
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 30 THEN 'Inactividad prolongada'
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 25 THEN 'Por expirar'
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 15 THEN 'Inactividad media'
        ELSE 'Activa'
    END AS nivel_inactividad,
    CASE 
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 30 THEN 'danger'
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 25 THEN 'warning'
        WHEN TIMESTAMPDIFF(MINUTE, sa.last_heartbeat, NOW()) >= 15 THEN 'info'
        ELSE 'success'
    END AS color_inactividad
FROM sessiones_activas sa
JOIN empleados e ON sa.empleado_id = e.id
ORDER BY sa.last_heartbeat DESC;

-- =================================================================
-- DATOS INICIALES
-- Insertar datos básicos para funcionamiento inicial
-- =================================================================

-- Insertar categorías básicas
INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
('Bebidas', 'Refrescos, jugos, aguas y otras bebidas'),
('Snacks', 'Papas fritas, galletas y snacks varios'),
('Lácteos', 'Leche, yogurt, quesos y derivados'),
('Panadería', 'Pan, pasteles y productos de panadería'),
('Carnes', 'Carnes rojas, blancas y procesadas'),
('Verduras', 'Vegetales frescos y verduras'),
('Frutas', 'Frutas frescas y naturales'),
('Limpieza', 'Productos de limpieza del hogar'),
('Aseo Personal', 'Productos de higiene y cuidado personal'),
('Granos', 'Arroz, frijoles, lentejas y otros granos'),
('Aceites', 'Aceites vegetales y grasas'),
('Endulzantes', 'Azúcar, miel y endulzantes'),
('Bebidas Calientes', 'Café, té y otras bebidas calientes'),
('Otros', 'Categoría general para productos no clasificados');

-- Insertar administrador por defecto
-- Usuario: admin / Contraseña: password (CAMBIAR EN PRODUCCIÓN)
INSERT IGNORE INTO empleados (nombre, usuario, password, rol, email) VALUES
('Administrador Sistema', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', 'admin@sistema.com');

-- Insertar productos de ejemplo
INSERT IGNORE INTO productos (codigo, nombre, categoria, precio_venta, stock_minimo, descripcion) VALUES
('P001', 'Arroz Premium 1kg', 'Granos', 4.50, 20, 'Arroz de alta calidad, grano largo'),
('P002', 'Aceite Vegetal 1L', 'Aceites', 3.80, 15, 'Aceite refinado de soya'),
('P003', 'Leche Entera 1L', 'Lácteos', 2.50, 25, 'Leche pasteurizada entera'),
('P004', 'Azúcar Blanca 1kg', 'Endulzantes', 3.20, 30, 'Azúcar refinada estándar'),
('P005', 'Café Molido 500g', 'Bebidas Calientes', 5.50, 10, 'Café 100% arábica tostado'),
('P006', 'Galletas Chocolate 200g', 'Snacks', 2.80, 40, 'Galletas rellenas de chocolate'),
('P007', 'Jugo de Naranja 1L', 'Bebidas', 3.50, 20, 'Jugo 100% natural'),
('P008', 'Pan Blanco 500g', 'Panadería', 1.80, 50, 'Pan de molde blanco'),
('P009', 'Yogurt Natural 1L', 'Lácteos', 4.20, 15, 'Yogurt natural sin azúcar'),
('P010', 'Papas Fritas 150g', 'Snacks', 2.20, 60, 'Papas fritas con sal');

-- Insertar lotes de ejemplo para productos iniciales
INSERT IGNORE INTO lotes (producto_id, cantidad, cantidad_disponible, precio_compra, fecha_vencimiento, empleado_id, proveedor) VALUES
(1, 100, 100, 3.20, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 1, 'Distribuidora Nacional'),
(2, 80, 80, 2.90, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 1, 'Alimentos del Perú'),
(3, 120, 120, 1.80, DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 'Lácteos San Antonio'),
(4, 150, 150, 2.50, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 1, 'Azucarera del Norte'),
(5, 50, 50, 4.20, DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 1, 'Cafetería Andina');

-- =================================================================
-- COMENTARIOS FINALES
-- =================================================================

/*
ESTRUCTURA COMPLETA DEL SISTEMA:

1. SEGURIDAD Y ACCESO:
   - empleados: Gestión de usuarios con roles
   - historial_cambios: Trazabilidad completa
   - sessiones_activas: Control de sesiones con heartbeat

2. INVENTARIO:
   - productos: Catálogo con categorías
   - categorías: Clasificación organizada
   - lotes: Control FIFO con vencimientos

3. VENTAS:
   - ventas: Registro principal con métodos de pago
   - detalle_venta: Desglose con trazabilidad de lotes

4. CAJA:
   - cajas: Control de efectivo y cierres
   - movimientos_caja: Ingresos/retiros manuales

5. VISTAS ÚTILES:
   - vista_stock: Estado actual del inventario
   - vista_vencimientos: Control de fechas
   - vista_ventas_diarias: Reportes diarios
   - vista_sesiones_activas: Monitoreo de sesiones

CARACTERÍSTICAS IMPLEMENTADAS:
✅ Sistema FIFO completo
✅ Control de vencimientos
✅ Múltiples métodos de pago
✅ Trazabilidad total
✅ Roles y permisos
✅ Índices optimizados
✅ Integridad referencial
✅ Datos de ejemplo
✅ Sistema de sesiones con heartbeat
✅ Cierre automático por inactividad
✅ Detección de cierre del programa
✅ Control de múltiples sesiones

SISTEMA DE SESIONES:
• Heartbeat cada 5 minutos para detectar actividad
• Cierre automático por 30 minutos de inactividad
• Detección de cierre del navegador/programa
• Registro de IP y user agent
• Estados: active, expired, logout, closed
• Vista vista_sesiones_activas para monitoreo

PARA INSTALACIÓN:
1. Ejecutar este script en phpMyAdmin
2. Cambiar contraseña del admin
3. Configurar conexión en config/database.php
4. El sistema está listo para usar

NOTA: Este script crea la base de datos completa con todas las tablas,
índices, relaciones y datos iniciales necesarios para el funcionamiento
del Sistema de Ventas e Inventario, incluyendo el avanzado sistema
de gestión de sesiones con cierre automático.
*/
