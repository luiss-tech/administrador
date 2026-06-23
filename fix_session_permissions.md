# Corrección de Permisos de Sesión en XAMPP

## Problema
```
Warning: session_start(): open(C:\xampp\tmp\sess_i02n9812ebu4nfrk934dnsfumf, O_RDWR) failed: Permission denied (13)
Warning: session_start(): Failed to read session data: files (path: C:\xampp\tmp)
```

## Causa
El directorio `C:\xampp\tmp` no tiene permisos de escritura para el usuario del servidor web.

## Soluciones

### Opción 1: Corregir permisos manualmente (Recomendado)
1. **Abrir CMD como Administrador**
2. **Ejecutar los siguientes comandos:**
   ```cmd
   icacls "C:\xampp\tmp" /grant Everyone:F /T
   ```
   
3. **O alternativamente:**
   ```cmd
   attrib -r "C:\xampp\tmp\*.*"
   ```

### Opción 2: Cambiar ruta de sesión
Agregar al inicio de `session_manager.php`:
```php
// Configurar ruta de sesión con permisos
session_save_path('C:\xampp\htdocs\Sistema Ventas\sessions');
mkdir('C:\xampp\htdocs\Sistema Ventas\sessions', 0777, true);
```

### Opción 3: Reiniciar servicios XAMPP
1. **Detener Apache** desde el panel de control XAMPP
2. **Reiniciar como Administrador**
3. **Verificar que el servicio Apache se ejecute con permisos adecuados**

## Verificación
Después de aplicar la solución, verificar que los warnings desaparezcan al:
1. Acceder a cualquier página del sistema
2. Realizar una acción que requiera sesión
3. Verificar que no aparezcan más warnings de permisos

## Nota Importante
Este problema es común en Windows con XAMPP cuando los servicios no se ejecutan con permisos de administrador.
