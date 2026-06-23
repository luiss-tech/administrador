-- Sistema de Ventas - Backup generado: 2026-06-13 23:07:27
-- Descripción: Backup automático diario (ejecución diferida)
-- Generado por: Administrador

SET FOREIGN_KEY_CHECKS=0;

-- Estructura de la tabla `cajas`
DROP TABLE IF EXISTS `cajas`;
CREATE TABLE `cajas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `fecha_apertura` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `monto_apertura` decimal(10,2) NOT NULL,
  `monto_cierre` decimal(10,2) DEFAULT NULL,
  `ventas_efectivo` decimal(10,2) DEFAULT 0.00 COMMENT 'Total ventas en efectivo',
  `ventas_tarjeta` decimal(10,2) DEFAULT 0.00 COMMENT 'Total ventas con tarjeta',
  `total_ingresos` decimal(10,2) DEFAULT 0.00,
  `total_retiros` decimal(10,2) DEFAULT 0.00,
  `estado` enum('abierta','cerrada') DEFAULT 'abierta',
  `diferencia` decimal(10,2) DEFAULT NULL,
  `efectivo_esperado` decimal(10,2) DEFAULT 0.00 COMMENT 'Efectivo esperado según cálculos',
  `observaciones` text DEFAULT NULL,
  `empleado_cierre_id` int(11) DEFAULT NULL,
  `tipo_cierre` enum('manual','automatico','forzado') DEFAULT NULL,
  `ventas_yape` decimal(10,2) DEFAULT 0.00 COMMENT 'Total ventas con Yape',
  `ventas_transferencia` decimal(10,2) DEFAULT 0.00 COMMENT 'Total ventas por transferencia',
  `total_ventas` decimal(10,2) DEFAULT 0.00 COMMENT 'Total general de todas las ventas',
  PRIMARY KEY (`id`),
  KEY `empleado_id` (`empleado_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_apertura`),
  CONSTRAINT `cajas_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `cajas`
INSERT INTO `cajas` VALUES
('1', '1', '2026-05-11 13:42:55', '2026-05-11 14:09:25', '2000.00', '1700.00', '0.00', '0.00', '100.00', '400.00', 'cerrada', NULL, '1700.00', ' ', '1', 'manual', '119.00', '0.00', '0.00');


-- Estructura de la tabla `categorias`
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `categorias`
INSERT INTO `categorias` VALUES
('1', 'Bebidas', 'Refrescos, jugos, aguas y otras bebidas', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('2', 'Snacks', 'Papas fritas, galletas y snacks varios', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('3', 'Lácteos', 'Leche, yogurt, quesos y derivados', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('4', 'Panadería', 'Pan, pasteles y productos de panadería', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('5', 'Carnes', 'Carne
 s rojas, blancas y procesadas', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('6', 'Verduras', 'Vegetales frescos y verduras', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('7', 'Frutas', 'Frutas frescas y naturales', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('8', 'Limpieza', 'Productos de limpieza del hogar', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('9', 'Aseo Personal', 'Productos de higiene y cuidado personal', '2026-05-06 02:10:45', '2026-05-06 02:10:45'),
('10', 'Otros', 'Categoría general para productos no clasificados', '2026-05-06 02:10:45', '2026-05-06 02:10:45');


-- Estructura de la tabla `detalle_venta`
DROP TABLE IF EXISTS `detalle_venta`;
CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `lote_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `ganancia` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lote_id` (`lote_id`),
  KEY `idx_venta` (`venta_id`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `detalle_venta_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_venta_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `detalle_venta_ibfk_3` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `detalle_venta`
INSERT INTO `detalle_venta` VALUES
('1', '1', '1', '1', '7', '17.00', '15.00', '14.00');


-- Estructura de la tabla `empleados`
DROP TABLE IF EXISTS `empleados`;
CREATE TABLE `empleados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','empleado') DEFAULT 'empleado',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `empleados`
INSERT INTO `empleados` VALUES
('1', 'Administrador', 'admin', 'admin123', 'administrador', '1', '2026-05-01 04:43:10'),
('2', 'Luis Sandoval', 'Luis', '$2y$10$NX/Mh7sf/udVqajuNTHWGuGRpxqyKQeewNw6UHr5d1VpNvDW66Xpm', 'empleado', '1', '2026-05-11 13:46:37');


-- Estructura de la tabla `historial_cambios`
DROP TABLE IF EXISTS `historial_cambios`;
CREATE TABLE `historial_cambios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `tipo_accion` enum('crear','editar','desactivar','eliminar') NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `valores_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_anteriores`)),
  `valores_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_nuevos`)),
  `descripcion` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tabla_registro` (`tabla_afectada`,`registro_id`),
  KEY `idx_empleado` (`empleado_id`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `historial_cambios_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `historial_cambios`
INSERT INTO `historial_cambios` VALUES
('1', 'productos', '1', 'desactivar', '1', '2026-05-11 13:45:50', '{\"id\":1,\"codigo\":\"P001\",\"codigo_barras\":\"12345678910\",\"nombre\":\"Whisky\",\"categoria\":\"Licores\",\"precio_venta\":\"22.00\",\"stock_minimo\":3,\"descripcion\":\"\",\"empleado_id\":1,\"activo\":1,\"created_at\":\"2026-05-11 13:42:13\",\"updated_at\":\"2026-05-11 13:44:44\"}', '{\"id\":1,\"codigo\":\"P001\",\"codigo_barras\":\"12345678910\",\"nombre\":\"Whisky\",\"categoria\":\"Licores\",\"precio_venta\":\"22.00\",\"stock_minimo\":3,\"descripcion\":\"\",\"empleado_id\":1,\"activo\":0,\"created_at\":\"2026-05-11 13:42:13\",\"updated_at\":\"2026-05-11 13:45:50\"}', 'Producto desactivado (con historial de ventas)', '::1'),
('2', 'productos', '1', '', '1', '2026-05-11 13:45:56', '{\"id\":1,\"codigo\":\"P001\",\"codigo_barras\":\"12345678910\",\"nombre\":\"Whisky\",\"categoria\":\"Licores\",\"precio_venta\":\"22.00\",\"stock_minimo\":3,\"descripcion\":\"\",\"empleado_id\":1,\"activo\":0,\"created_at\":\"2026-05-11 13:42:13\",\"updated_at\":\"2026-05-11 13:45:50\"}', '{\"id\":1,\"codigo\":\"P001\",\"codigo_barras\":\"12345678910\",\"nombre\":\"Whisky\",\"categoria\":\"Licores\",\"precio_venta\":\"22.00\",\"stock_minimo\":3,\"descripcion\":\"\",\"empleado_id\":1,\"activo\":1,\"created_at\":\"2026-05-11 13:42:13\",\"updated_at\":\"2026-05-11 13:45:56\"}', 'Producto activado', '::1');


-- Estructura de la tabla `lotes`
DROP TABLE IF EXISTS `lotes`;
CREATE TABLE `lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `cantidad_disponible` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_ingreso` timestamp NOT NULL DEFAULT current_timestamp(),
  `empleado_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_producto` (`producto_id`),
  KEY `idx_vencimiento` (`fecha_vencimiento`),
  KEY `idx_activo` (`activo`,`cantidad_disponible`),
  KEY `empleado_id` (`empleado_id`),
  CONSTRAINT `lotes_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lotes_ibfk_2` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `lotes`
INSERT INTO `lotes` VALUES
('1', '1', '10', '3', '15.00', NULL, '2026-05-11 13:42:13', '1', '1'),
('2', '1', '20', '20', '19.00', NULL, '2026-05-11 13:44:44', '1', '1'),
('3', '2', '50', '50', '3.50', '2026-07-13', '2026-06-13 16:00:21', '1', '1');


-- Estructura de la tabla `movimientos_caja`
DROP TABLE IF EXISTS `movimientos_caja`;
CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caja_id` int(11) NOT NULL,
  `tipo` enum('ingreso','retiro') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `empleado_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `empleado_id` (`empleado_id`),
  KEY `idx_caja` (`caja_id`),
  CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movimientos_caja_ibfk_2` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `movimientos_caja`
INSERT INTO `movimientos_caja` VALUES
('1', '1', 'ingreso', '100.00', 'dinero del dueño', '2026-05-11 13:43:08', '1'),
('2', '1', 'retiro', '400.00', 'pago de alquiler', '2026-05-11 13:43:26', '1');


-- Estructura de la tabla `productos`
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `codigo_barras` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `stock_minimo` int(11) DEFAULT 10,
  `descripcion` text DEFAULT NULL,
  `empleado_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo_barras` (`codigo_barras`),
  KEY `idx_nombre` (`nombre`),
  KEY `empleado_id` (`empleado_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `productos`
INSERT INTO `productos` VALUES
('1', 'P001', '12345678910', 'Whisky', 'Licores', '22.00', '3', '', '1', '1', '2026-05-11 13:42:13', '2026-05-11 13:45:56'),
('2', 'P002', '12345678911', 'arroz ', 'alimentos', '4.00', '5', '', '1', '1', '2026-06-13 16:00:21', '2026-06-13 16:00:21');


-- Estructura de la tabla `sessiones_activas`
DROP TABLE IF EXISTS `sessiones_activas`;
CREATE TABLE `sessiones_activas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_heartbeat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('active','expired','logout') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_empleado` (`empleado_id`),
  KEY `idx_heartbeat` (`last_heartbeat`),
  KEY `idx_status` (`status`),
  CONSTRAINT `sessiones_activas_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sesiones activas del sistema';

-- Datos de la tabla `sessiones_activas` (vacía)


-- Estructura de la tabla `ventas`
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `costo_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ganancia` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cliente` varchar(100) DEFAULT 'Cliente General',
  `empleado_id` int(11) DEFAULT NULL,
  `metodo_pago` enum('efectivo','tarjeta','yape','transferencia') DEFAULT 'efectivo',
  `caja_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `empleado_id` (`empleado_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `caja_id` (`caja_id`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de la tabla `ventas`
INSERT INTO `ventas` VALUES
('1', 'V0001', '2026-05-11 13:44:08', '119.00', '105.00', '14.00', 'Cliente General', '1', 'yape', '1');


SET FOREIGN_KEY_CHECKS=1;
