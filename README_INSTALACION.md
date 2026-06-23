# Sistema de Ventas e Inventario - Guía de Instalación

## 🚀 Instalación Rápida

### Requisitos del Servidor
- ✅ PHP 7.4 o superior
- ✅ MySQL 5.7 o superior / MariaDB 10.2+
- ✅ Extensiones PHP: PDO, MySQL, JSON, mbstring
- ✅ Servidor web (Apache, Nginx, etc.)

### Pasos de Instalación

#### 1. 📁 Subir Archivos
Sube todos los archivos del sistema a tu servidor web en la carpeta raíz.

#### 2. 🔧 Ejecutar Instalador
Abre tu navegador y accede a:
```
http://tu-dominio.com/install.php
```

#### 3. 📊 Configurar Base de Datos
- **Paso 1**: Requisitos del sistema
- **Paso 2**: Datos de conexión MySQL
  - Host: usualmente `localhost`
  - Nombre de la base de datos
  - Usuario y contraseña
- **Paso 3**: Crear administrador
  - Nombre completo
  - Usuario de acceso
  - Contraseña (mínimo 6 caracteres)
  - Correo electrónico
- **Paso 4**: ¡Instalación completada!

#### 4. 🔒 Seguridad Post-Instalación
```bash
# Eliminar instalador
rm install.php

# Ajustar permisos (Linux)
chmod 755 config/
chmod 644 config/database.php
```

## 🗄️ Base de Datos

### Ejecutar Scripts SQL (si migras desde versión anterior)
1. **Categorías**: `actualizar_categorias.sql`
2. **Empleado ID**: `actualizar_empleado_id.sql`
3. **Historial**: `crear_historial_cambios.sql`

### Crear Base de Datos Nueva
```sql
-- Si no tienes base de datos, crea una con:
CREATE DATABASE sistema_ventas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 🔧 Configuración Adicional

### Archivos de Configuración
- `config/database.php` - Conexión a base de datos
- `config/installed.lock` - Bloqueo de instalación (eliminar para reinstalar)

### Directorios Importantes
```
Sistema Ventas/
├── config/          # Configuración (proteger)
├── backups/          # Respaldos automáticos (creado al usar)
├── logs/             # Logs del sistema (creado automáticamente)
├── assets/           # Recursos estáticos
└── uploads/          # Archivos subidos (si aplica)
```

## 🚀 Primer Uso

### 1. Iniciar Sesión
Accede a `http://tu-dominio.com/` con las credenciales de administrador creadas.

### 2. Configuración Inicial
1. **Abrir caja**: Control de Caja → Abrir Caja
2. **Registrar productos**: Productos → Registrar Producto
3. **Ingresar stock**: Ingresar Stock → Agregar lotes
4. **Realizar ventas**: Ventas → Nueva Venta

## 🛡️ Mantenimiento

### Backups Automáticos
- **Ubicación**: `backups/` (protegido con .htaccess)
- **Acceso**: Menú → Backups
- **Límite**: 10 backups automáticos
- **Recomendación**: Descargar backups regularmente

### Logs del Sistema
- **Ubicación**: `logs/system.log` (protegido)
- **Acceso**: Menú → Logs del Sistema
- **Rotación**: Automática cada 10MB
- **Niveles**: ERROR, WARNING, CRITICAL, INFO

### Actualizaciones
1. **Backup completo** antes de actualizar
2. **Probar en entorno de desarrollo**
3. **Aplicar cambios gradualmente**
4. **Verificar compatibilidad**

## 🔍 Solución de Problemas

### Errores Comunes

#### "Error de conexión a base de datos"
```bash
# Verificar credenciales en config/database.php
# Probar conexión manualmente:
mysql -h localhost -u usuario -p nombre_db
```

#### "Permission denied" en Linux
```bash
# Ajustar permisos
chmod -R 755 /ruta/al/sistema/
chown -R www-data:www-data /ruta/al/sistema/
```

#### "No se puede escribir en logs/backups"
```bash
# Crear directorios y ajustar permisos
mkdir logs backups
chmod 755 logs backups
chown www-data:www-data logs backups
```

### Rendimiento

#### Optimización MySQL
```sql
-- Índices recomendados
CREATE INDEX idx_productos_nombre ON productos(nombre);
CREATE INDEX idx_lotes_producto_fecha ON lotes(producto_id, fecha_ingreso);
CREATE INDEX idx_ventas_fecha ON ventas(fecha);
```

#### Configuración PHP
```ini
; php.ini recomendado
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
```

## 📞 Soporte

### Información del Sistema
- **Versión**: 1.0
- **PHP Requerido**: 7.4+
- **MySQL Requerido**: 5.7+
- **Navegadores**: Chrome 80+, Firefox 75+, Safari 13+

### Características Principales
- ✅ Gestión de inventario con lotes (FIFO)
- ✅ Control de caja con múltiples métodos de pago
- ✅ Sistema de roles (Administrador/Empleado)
- ✅ Trazabilidad completa de cambios
- ✅ Backups automáticos y manuales
- ✅ Logs de errores y seguridad
- ✅ Reportes y estadísticas
- ✅ Validaciones y seguridad

### Contacto y Ayuda
Para soporte técnico o reportar problemas:
1. **Revisar logs**: Sistema → Logs del Sistema
2. **Verificar configuración**: Archivos `config/`
3. **Documentación**: Este archivo README
4. **Backups**: Siempre mantener respaldos recientes

---

**⚠️ Advertencia Importante**
- Mantén siempre backups actualizados
- No expongas archivos de configuración
- Actualiza regularmente el sistema
- Revisa logs periódicamente

**🎯 Listo para Producción**
Una vez completada la instalación, el sistema está listo para uso en un entorno real de negocio.
