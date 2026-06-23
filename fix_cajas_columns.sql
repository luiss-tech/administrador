-- =================================================================
-- SCRIPT PARA CORREGIR ESTRUCTURA DE TABLA CAJAS
-- Agrega columnas faltantes y modifica nombres existentes
-- =================================================================

-- Usar la base de datos
USE gestion_inventario;

-- 1. Agregar columna total_ventas (suma de todos los métodos de pago)
ALTER TABLE cajas 
ADD COLUMN total_ventas DECIMAL(10,2) DEFAULT 0 
COMMENT 'Total general de todas las ventas' 
AFTER total_ventas_transferencia;

-- 2. Agregar columna efectivo_esperado (para cálculos internos)
ALTER TABLE cajas 
ADD COLUMN efectivo_esperado DECIMAL(10,2) DEFAULT 0 
COMMENT 'Efectivo esperado según cálculos' 
AFTER diferencia;

-- 3. Renombrar columnas de ventas para que coincidan con el código
-- Opción A: Renombrar columnas existentes (recomendado)
ALTER TABLE cajas 
CHANGE COLUMN total_ventas_efectivo ventas_efectivo DECIMAL(10,2) DEFAULT 0 
COMMENT 'Total ventas en efectivo';

ALTER TABLE cajas 
CHANGE COLUMN total_ventas_tarjeta ventas_tarjeta DECIMAL(10,2) DEFAULT 0 
COMMENT 'Total ventas con tarjeta';

ALTER TABLE cajas 
CHANGE COLUMN total_ventas_yape ventas_yape DECIMAL(10,2) DEFAULT 0 
COMMENT 'Total ventas con Yape';

ALTER TABLE cajas 
CHANGE COLUMN total_ventas_transferencia ventas_transferencia DECIMAL(10,2) DEFAULT 0 
COMMENT 'Total ventas por transferencia';

-- =================================================================
-- COMENTARIOS:
-- 
-- Este script realiza las siguientes correcciones:
-- 1. Agrega la columna 'total_ventas' que faltaba
-- 2. Agrega la columna 'efectivo_esperado' para cálculos
-- 3. Renombra las columnas para que coincidan con el código PHP
-- 
-- Después de ejecutar este script, el código de caja.php funcionará sin errores
-- =================================================================
