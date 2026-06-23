# 📦 Sistema de Ventas e Inventario - Guía Completa de Instalación y Uso

**Versión: 1.0 - Producción**  
**Última actualización: 11 de Mayo de 2026**

---

## 📋 Índice

1. [Descripción del Sistema](#descripción-del-sistema)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación Paso a Paso](#instalación-paso-a-paso)
4. [Configuración Inicial](#configuración-inicial)
5. [Estructura de la Base de Datos](#estructura-de-la-base-de-datos)
6. [Funcionalidades Principales](#funcionalidades-principales)
7. [Guía de Uso Detallada](#guía-de-uso-detallada)
8. [Mantenimiento y Backups](#mantenimiento-y-backups)
9. [Solución de Problemas](#solución-de-problemas)
10. [Soporte y Contacto](#soporte-y-contacto)

---

## 🎯 Descripción del Sistema

El **Sistema de Ventas e Inventario** es una solución completa para la gestión de negocios que incluye:

### 🏪 **Módulos Principales**
- **📦 Gestión de Inventario** con sistema FIFO
- **💰 Control de Caja** con múltiples métodos de pago
- **🛒 Proceso de Ventas** con trazabilidad completa
- **👥 Gestión de Empleados** con roles y permisos
- **📊 Reportes y Estadísticas** en tiempo real
- **🔐 Sistema de Backups** automático y manual

### ✨ **Características Destacadas**
- **🔄 Sistema FIFO** para consumo de stock por lotes
- **⏰ Control de Vencimientos** con alertas automáticas
- **💳 Múltiples Métodos de Pago** (Efectivo, Tarjeta, Yape, Transferencia)
- **📝 Trazabilidad Completa** de todas las operaciones
- **🔒 Roles de Usuario** (Administrador/Empleado)
- **📈 Reportes Detallados** de ventas e inventario
- **🛡️ Backups Automáticos** al cerrar caja
- **📱 Interfaz Moderna** y responsive

---

## 💻 Requisitos del Sistema

### 🔧 **Requisitos del Servidor**

#### **Mínimos**
- ✅ **PHP 7.4** o superior
- ✅ **MySQL 5.7** o superior / MariaDB 10.2+
- ✅ **2GB** de RAM
- ✅ **5GB** de espacio en disco
- ✅ **Servidor web** (Apache 2.4+, Nginx 1.16+)

#### **Recomendados**
- ✅ **PHP 8.0+** para mejor rendimiento
- ✅ **MySQL 8.0+** o MariaDB 10.5+
- ✅ **4GB** de RAM
- ✅ **20GB** de espacio en disco
- ✅ **SSL/TLS** para conexiones seguras

#### **Extensiones PHP Requeridas**
```bash
php-mysql        # Conexión MySQL
php-pdo          # Base de datos
php-json         # Manejo JSON
php-mbstring     # Cadenas multibyte
php-curl         # Peticiones HTTP
php-gd           # Imágenes (opcional)
php-zip          # Compresión (opcional)
```

### 🌐 **Requisitos del Navegador**
- **Chrome 80+** (recomendado)
- **Firefox 75+**
- **Safari 13+**
- **Edge 80+**

---

## 🚀 Instalación Paso a Paso

### 📁 **Paso 1: Preparación de Archivos**

1. **Descargar** todos los archivos del sistema
2. **Descomprimir** en una carpeta temporal
3. **Verificar** que todos los archivos estén presentes

```
Estructura esperada:
├── Sistema Ventas/
│   ├── index.php
│   ├── install.php
│   ├── database_completa.sql
│   ├── config/
│   │   └── database.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   ├── backups/          (se creará automáticamente)
│   ├── logs/             (se creará automáticamente)
│   └── [demás archivos...]
```

### 🗄️ **Paso 2: Crear Base de Datos**

#### **Opción A: Usar el Instalador Automático**
1. **Subir** los archivos al servidor web
2. **Acceder** a `http://tu-dominio.com/install.php`
3. **Seguir** el asistente de instalación (4 pasos)
4. **Listo**: El sistema queda configurado automáticamente

#### **Opción B: Instalación Manual**
1. **Crear base de datos** en phpMyAdmin:
   ```sql
   CREATE DATABASE gestion_inventario 
   CHARACTER SET utf8mb4 
   COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar** el script completo:
   - Abrir `database_completa.sql`
   - Ejecutar en phpMyAdmin
   - Verificar que todas las tablas se creen

3. **Configurar conexión**:
   - Editar `config/database.php`
   - Ingresar credenciales de la base de datos

### 🔧 **Paso 3: Configuración del Servidor**

#### **Apache (.htaccess)**
```apache
# Opcional: URLs amigables
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Seguridad
<Files "config/*">
    Require all denied
</Files>
<Files "*.sql">
    Require all denied
</Files>
```

#### **Permisos de Archivos (Linux)**
```bash
# Permisos básicos
chmod -R 755 /ruta/al/sistema/
chmod -R 644 /ruta/al/sistema/config/

# Permisos para escritura
chmod -R 755 /ruta/al/sistema/backups/
chmod -R 755 /ruta/al/sistema/logs/

# Propietario (opcional)
chown -R www-data:www-data /ruta/al/sistema/
```

### 🔒 **Paso 4: Seguridad Post-Instalación**

1. **Eliminar instalador**:
   ```bash
   rm install.php
   ```

2. **Cambiar contraseña** del administrador:
   - Iniciar sesión como `admin` / `password`
   - Ir a Empleados → Editar Administrador
   - Cambiar a una contraseña segura

3. **Proteger directorios críticos**:
   ```bash
   # .htaccess para config/
   Order deny,allow
   Deny from all
   
   # .htaccess para backups/
   Order deny,allow
   Deny from all
   ```

---

## ⚙️ Configuración Inicial

### 👤 **Primer Inicio de Sesión**

1. **Acceder** al sistema: `http://tu-dominio.com/`
2. **Credenciales por defecto**:
   - **Usuario**: `admin`
   - **Contraseña**: `password`
3. **Cambiar inmediatamente** la contraseña

### 🏪 **Configuración Básica**

#### **1. Crear Empleados**
1. **Ir a**: Empleados → Nuevo Empleado
2. **Registrar** vendedores con roles "empleado"
3. **Asignar** credenciales de acceso

#### **2. Abrir Caja Inicial**
1. **Ir a**: Control de Caja → Abrir Caja
2. **Ingresar** monto de apertura
3. **Registrar** observaciones si es necesario

#### **3. Configurar Backups**
1. **Ir a**: Config. Backups
2. **Activar** backup al cerrar caja
3. **Ajustar** límite de backups (recomendado: 10)

#### **4. Registrar Productos**
1. **Ir a**: Registrar Producto
2. **Ingresar** productos con categorías
3. **Definir** stock mínimo para alertas

---

## 🗄️ Estructura de la Base de Datos

### 📊 **Diagrama de Tablas**

```
empleados ──┬─ productos ──┬─ lotes ──┬─ detalle_venta ── ventas
    │        │           │        │                   │
    │        │           │        └─ vista_stock      │
    │        │           │                            │
    │        │           └─ vista_vencimientos       │
    │        │                                        │
    │        └─ historial_cambios                   │
    │                                                │
    ├─ cajas ──┬─ movimientos_caja                  │
    │           │                                   │
    │           └─ ventas (caja_id) ────────────────┘
    │
    └─ historial_cambios (empleado_id)
```

### 📋 **Descripción de Tablas**

#### **🔐 Tablas de Seguridad**
- **`empleados`**: Usuarios y roles del sistema
- **`historial_cambios`**: Auditoría completa de modificaciones

#### **📦 Tablas de Inventario**
- **`categorias`**: Clasificación de productos
- **`productos`**: Catálogo de productos
- **`lotes`**: Control de stock por lotes (FIFO)

#### **💰 Tablas de Ventas**
- **`ventas`**: Registro principal de ventas
- **`detalle_venta`**: Desglose con trazabilidad de lotes

#### **💳 Tablas de Caja**
- **`cajas`**: Control de efectivo y cierres
- **`movimientos_caja`**: Ingresos/retiros manuales

### 🔗 **Relaciones Clave**

- **FIFO**: `lotes.fecha_ingreso` determina orden de consumo
- **Trazabilidad**: `detalle_venta.lote_id` → `lotes.id`
- **Seguridad**: `historial_cambios.empleado_id` → `empleados.id`
- **Caja**: `ventas.caja_id` → `cajas.id`

---

## 🎯 Funcionalidades Principales

### 📦 **Gestión de Inventario**

#### **🔄 Sistema FIFO**
- **Consumo automático** por fecha de ingreso
- **Trazabilidad completa** de cada lote
- **Control de vencimientos** con alertas
- **Stock real** consolidado por producto

#### **⚰️ Control de Lotes**
- **Registro** de entradas con proveedor
- **Seguimiento** de fechas de vencimiento
- **Actualización** automática de stock disponible
- **Reportes** de lotes próximos a vencer

#### **📊 Alertas de Stock**
- **Stock bajo**: Cuando ≤ stock mínimo
- **Sin stock**: Cuando cantidad = 0
- **Vencimiento**: Próximos a vencer (7, 30 días)
- **Dashboard** con indicadores visuales

### 💰 **Control de Caja**

#### **💳 Métodos de Pago**
- **Efectivo**: Afecta saldo físico de caja
- **Tarjeta**: Registro fuera de caja física
- **Yape**: Registro electrónico
- **Transferencia**: Registro bancario

#### **🔔 Cierre de Caja**
- **Manual**: Al final del día
- **Forzado**: Por administrador
- **Automático**: Por seguridad (3:00 AM)
- **Backup automático** en cada cierre

#### **📋 Movimientos Manuales**
- **Ingresos**: Dinero extra a caja
- **Retiros**: Dinero fuera de operaciones
- **Concepto**: Motivo del movimiento
- **Autorización**: Solo administradores

### 🛒 **Proceso de Ventas**

#### **🔍 Búsqueda de Productos**
- **Por código**: Rápida y precisa
- **Por nombre**: Autocompletar
- **Por código de barras**: Con escáner
- **Filtros**: Por categoría, stock disponible

#### **🛍️ Carrito de Ventas**
- **Agregar productos** con cantidad
- **Descuentos** por item
- **Cliente**: Opcional con datos
- **Método de pago**: Selección flexible

#### **📄 Facturación**
- **Número de venta**: Automático y único
- **Detalle completo**: Con lotes consumidos
- **Ganancia real**: Por cada producto
- **Método de pago**: Registro específico

### 👥 **Gestión de Usuarios**

#### **🔐 Roles y Permisos**
- **Administrador**: Acceso completo
  - Editar productos
  - Gestión de empleados
  - Backups y configuración
  - Reportes avanzados

- **Empleado**: Acceso limitado
  - Registrar ventas
  - Ingresar stock
  - Ver reportes básicos
  - Operaciones diarias

#### **📝 Trazabilidad**
- **Registro** de cada acción
- **Usuario** que realizó la operación
- **Fecha y hora** exactas
- **Valores anteriores** y nuevos
- **IP** de origen

---

## 📖 Guía de Uso Detallada

### 🌅 **Flujo Diario de Operaciones**

#### **1. Apertura del Día**
```
1. Iniciar sesión como empleado
2. Verificar si hay caja abierta
3. Si no hay caja abierta:
   - Ir a Control de Caja → Abrir Caja
   - Ingresar monto inicial
   - Confirmar apertura
4. Revisar stock y alertas
5. Listo para comenzar ventas
```

#### **2. Proceso de Ventas**
```
1. Ir a Nueva Venta
2. Buscar productos:
   - Escanear código de barras O
   - Buscar por nombre O
   - Ingresar código manual
3. Agregar al carrito
4. Seleccionar método de pago
5. Ingresar datos del cliente (opcional)
6. Confirmar venta
7. Imprimir o enviar comprobante
```

#### **3. Gestión de Inventario**
```
1. Ingresar Stock (si es necesario):
   - Ir a Ingresar Stock
   - Seleccionar producto
   - Ingresar cantidad y precio
   - Registrar fecha de vencimiento
   - Confirmar ingreso

2. Registrar Nuevos Productos:
   - Ir a Registrar Producto
   - Completar todos los datos
   - Asignar categoría
   - Definir stock mínimo
   - Guardar producto
```

#### **4. Cierre del Día**
```
1. Ir a Control de Caja
2. Revisar totales del día
3. Contar efectivo físico
4. Ingresar monto real
5. Registrar diferencias
6. Cerrar caja
7. Backup automático creado
8. Imprimir reporte de cierre
```

### 📊 **Reportes y Estadísticas**

#### **📈 Reportes de Ventas**
- **Ventas diarias**: Resumen por fecha
- **Ventas por método**: Desglose de pagos
- **Ventas por vendedor**: Rendimiento individual
- **Productos más vendidos**: Ranking de productos
- **Ganancias**: Análisis de rentabilidad

#### **📦 Reportes de Inventario**
- **Stock actual**: Niveles de inventario
- **Lotes por vencer**: Alertas de vencimiento
- **Movimientos**: Entradas y salidas
- **Valor del inventario**: Inversión en stock
- **Rotación**: Eficiencia de gestión

#### **💰 Reportes de Caja**
- **Cierres diarios**: Resumen de caja
- **Diferencias**: Análisis de discrepancias
- **Movimientos**: Ingresos y retiros
- **Métodos de pago**: Distribución
- **Tendencias**: Evolución temporal

### 🔧 **Funciones Avanzadas**

#### **📝 Edición de Productos**
```
Solo administradores pueden:
1. Ir a Editar Productos
2. Buscar producto a modificar
3. Editar datos básicos:
   - Nombre, categoría, precios
   - Stock mínimo, descripción
4. Cambiar estado (activo/inactivo)
5. Ver historial de cambios
```

#### **🔄 Gestión de Lotes**
```
1. Ver lotes de un producto
2. Editar fecha de vencimiento
3. Ver stock disponible por lote
4. Rastrear consumo FIFO
5. Reportes de vencimientos
```

#### **👥 Gestión de Empleados**
```
Solo administradores pueden:
1. Crear nuevos empleados
2. Asignar roles y permisos
3. Activar/desactivar usuarios
4. Ver historial de accesos
5. Restablecer contraseñas
```

---

## 🔧 Mantenimiento y Backups

### 💾 **Sistema de Backups**

#### **🔄 Backups Automáticos**
- **Al cerrar caja**: Backup completo con datos del cierre
- **Diario**: Configurable a hora específica
- **Semanal**: Opcional para mantenimiento
- **Límite**: Configurable (5-50 backups)

#### **💼 Backups Manuales**
1. **Ir a**: Backups → Crear Backup
2. **Ingresar descripción** (opcional)
3. **Confirmar creación**
4. **Descargar** para guardar externamente

#### **🔍 Restauración de Backups**
```
⚠️ ADVERTENCIA: La restauración reemplaza todos los datos actuales

1. Crear backup actual (recomendado)
2. Ir a Backups
3. Seleccionar backup a restaurar
4. Confirmar escribiendo "RESTAURAR"
5. Esperar proceso de restauración
6. Verificar datos restaurados
```

### 📋 **Mantenimiento Programado**

#### **🔄 Tareas Diarias**
- [ ] **Verificar backups** automáticos
- [ ] **Revisar alertas** de stock
- [ ] **Cerrar caja** correctamente
- [ ] **Revisar logs** del sistema

#### **📅 Tareas Semanales**
- [ ] **Limpiar logs** antiguos
- [ ] **Optimizar base de datos**
- [ ] **Revisar espacio** en disco
- [ ] **Actualizar productos** si es necesario

#### **📆 Tareas Mensuales**
- [ ] **Backup completo** externo
- [ ] **Revisar rendimiento** del sistema
- [ ] **Actualizar software** del servidor
- [ ] **Capacitar empleados** si es necesario

### 🔍 **Monitoreo del Sistema**

#### **📊 Indicadores Clave**
- **Uso de disco**: Espacio disponible
- **Rendimiento**: Tiempo de respuesta
- **Errores**: Logs del sistema
- **Backups**: Estado y frecuencia

#### **📝 Logs del Sistema**
- **Ubicación**: `logs/system.log`
- **Niveles**: ERROR, WARNING, INFO
- **Acceso**: Menú → Logs del Sistema
- **Rotación**: Automática cada 10MB

---

## 🛠️ Solución de Problemas

### 🔐 **Problemas de Acceso**

#### **❌ No puedo iniciar sesión**
```
✅ Soluciones:
1. Verificar usuario y contraseña
2. Revisar que el usuario esté activo
3. Limpiar caché del navegador
4. Verificar conexión a base de datos
5. Revisar logs del sistema
```

#### **🔒 Olvidé mi contraseña**
```
✅ Soluciones:
1. Contactar al administrador
2. Restablecer desde phpMyAdmin:
   UPDATE empleados 
   SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
   WHERE usuario = 'tu_usuario';
```

### 🗄️ **Problemas de Base de Datos**

#### **❌ Error de conexión**
```
✅ Soluciones:
1. Verificar credenciales en config/database.php
2. Probar conexión manual desde phpMyAdmin
3. Verificar que el servidor MySQL esté activo
4. Revisar permisos del usuario MySQL
5. Verificar nombre de la base de datos
```

#### **📊 Tablas no existen**
```
✅ Soluciones:
1. Importar database_completa.sql
2. Verificar que no haya errores en la importación
3. Revisar charset y collation
4. Verificar permisos del usuario
```

### 📦 **Problemas de Inventario**

#### **❌ Stock no se actualiza**
```
✅ Soluciones:
1. Verificar que haya lotes activos
2. Revisar fechas de vencimiento
3. Verificar que el producto esté activo
4. Revisar consumos en detalle_venta
5. Verificar sistema FIFO
```

#### **⏰ Alertas de vencimiento no funcionan**
```
✅ Soluciones:
1. Verificar fechas de vencimiento en lotes
2. Revisar configuración de alertas
3. Verificar zona horaria del servidor
4. Actualizar vista_vencimientos
```

### 💰 **Problemas de Caja**

#### **❌ No puedo cerrar caja**
```
✅ Soluciones:
1. Verificar que no haya ventas pendientes
2. Revisar que la caja esté abierta
3. Verificar permisos del usuario
4. Revisar movimientos sin cerrar
5. Verificar conexión a base de datos
```

#### **💳 Métodos de pago no funcionan**
```
✅ Soluciones:
1. Verificar configuración en ventas
2. Revisar que los métodos estén habilitados
3. Verificar que la caja esté abierta
4. Revisar configuración de caja
```

### 🌐 **Problemas Web**

#### **❌ Páginas no cargan**
```
✅ Soluciones:
1. Verificar configuración del servidor web
2. Revisar permisos de archivos
3. Verificar configuración PHP
4. Revisar errores en logs del servidor
5. Limpiar caché del navegador
```

#### **📱 Diseño no responsive**
```
✅ Soluciones:
1. Verificar CSS y JavaScript
2. Limpiar caché del navegador
3. Probar en diferentes navegadores
4. Verificar viewport configuration
5. Revisar console errors
```

### 🔍 **Diagnóstico Avanzado**

#### **📊 Verificar Estado del Sistema**
```php
// Crear archivo diagnosticar.php
<?php
echo "PHP Version: " . phpversion() . "\n";
echo "MySQL Extension: " . (extension_loaded('mysql') ? 'Yes' : 'No') . "\n";
echo "PDO Extension: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "\n";
echo "JSON Extension: " . (extension_loaded('json') ? 'Yes' : 'No') . "\n";

// Probar conexión a base de datos
try {
    $pdo = new PDO("mysql:host=localhost", "user", "pass");
    echo "MySQL Connection: OK\n";
} catch (PDOException $e) {
    echo "MySQL Connection: " . $e->getMessage() . "\n";
}

// Verificar permisos
echo "Current Directory: " . getcwd() . "\n";
echo "File Permissions: " . substr(sprintf('%o', fileperms('.')), -4) . "\n";
?>
```

---

## 📞 Soporte y Contacto

### 🆘 **Soporte Técnico**

#### **📋 Antes de Contactar**
1. **Revisar logs** del sistema
2. **Verificar** requisitos del servidor
3. **Intentar** soluciones básicas
4. **Documentar** el error exacto
5. **Preparar** acceso al sistema

#### **📧 Información Requerida**
- **Versión del sistema**: 1.0
- **Versión PHP**: `php -v`
- **Versión MySQL**: `SELECT VERSION()`
- **Navegador**: Chrome XX, Firefox XX
- **Mensaje de error**: Exacto
- **Pasos para reproducir**: Detallados

#### **🔧 Canales de Soporte**
- **Email**: soporte@sistema.com
- **Teléfono**: +51 123 456 789
- **WhatsApp**: +51 987 654 321
- **Horario**: Lunes a Viernes 9:00-18:00

### 📚 **Recursos Adicionales**

#### **📖 Documentación**
- **Manual de Usuario**: Ver esta guía
- **API Documentation**: `docs/api.md`
- **Base de Datos**: `database_completa.sql`
- **Configuración**: `config/database.php`

#### **🎓 Capacitación**
- **Videos tutoriales**: Disponibles en YouTube
- **Webinars**: Mensuales para clientes
- **Workshops**: Presenciales (solo Lima)
- **Certificación**: Para administradores

#### **🔄 Actualizaciones**
- **Versiones menores**: Automáticas
- **Versiones mayores**: Manual con soporte
- **Security patches**: Inmediatas
- **Feature requests**: Evaluar cada trimestre

### 📋 **Checklist de Implementación**

#### **✅ Pre-Instalación**
- [ ] Servidor cumple requisitos mínimos
- [ ] Base de datos creada
- [ ] Permisos configurados
- [ ] SSL/TLS configurado (opcional)

#### **✅ Instalación**
- [ ] Archivos subidos correctamente
- [ ] Instalador ejecutado sin errores
- [ ] Base de datos importada
- [ ] Configuración verificada

#### **✅ Post-Instalación**
- [ ] Contraseña admin cambiada
- [ ] Empleados creados
- [ ] Productos registrados
- [ ] Caja de prueba abierta
- [ ] Backup de prueba creado
- [ ] Logs revisados

#### **✅ Operación**
- [ ] Ventas de prueba realizadas
- [ ] Stock ingresado y verificado
- [ ] Caja cerrada correctamente
- [ ] Reportes generados
- [ ] Backups automáticos funcionando

---

## 📄 Licencia y Términos

### 📜 **Licencia de Uso**
- **Tipo**: Licencia Comercial
- **Usuarios**: Ilimitados por instalación
- **Soporte**: Incluido por 1 año
- **Actualizaciones**: Incluidas por 1 año

### 🚫 **Restricciones**
- No redistribuir el software
- No modificar el código fuente
- No eliminar referencias del autor
- No usar para múltiples empresas

### ✅ **Permitido**
- Instalar en un servidor por licencia
- Modificar configuración
- Agregar personalización visual
- Exportar datos propios

---

## 🎉 Conclusión

El **Sistema de Ventas e Inventario** está diseñado para ser una solución completa, robusta y fácil de usar para la gestión de negocios. Con todas las funcionalidades implementadas, desde el control FIFO hasta los backups automáticos, ofrece todo lo necesario para una operación eficiente y segura.

### 🚀 **Próximos Pasos**
1. **Instalar** el sistema siguiendo esta guía
2. **Capacitar** al personal en el uso diario
3. **Configurar** las preferencias específicas
4. **Establecer** rutinas de mantenimiento
5. **Contactar soporte** si necesita ayuda adicional

### 📞 **Estamos para Ayudar**
No dude en contactarnos si necesita asistencia técnica, capacitación adicional o tiene sugerencias para mejorar el sistema.

---

**🎯 Gracias por elegir nuestro Sistema de Ventas e Inventario**

*Versión 1.0 - Producción - 11 de Mayo de 2026*
