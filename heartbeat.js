/**
 * Sistema de Heartbeat para detección de actividad del usuario
 * Envía pulsos regulares al servidor para mantener la sesión activa
 */

class HeartbeatManager {
    constructor() {
        this.interval = null;
        this.heartbeatInterval = 5 * 60 * 1000; // 5 minutos
        this.warningTimeout = 25 * 60 * 1000; // 25 minutos (aviso antes de cierre)
        this.maxInactivity = 30 * 60 * 1000; // 30 minutos (cierre automático)
        this.lastActivity = Date.now();
        this.warningShown = false;
        
        this.init();
    }
    
    init() {
        // Iniciar heartbeat si el usuario está logueado
        if (window.location.pathname !== '/' && window.location.pathname !== '/index.php') {
            this.startHeartbeat();
            this.setupActivityListeners();
            this.setupVisibilityListeners();
        }
    }
    
    startHeartbeat() {
        // Enviar heartbeat inmediato
        this.sendHeartbeat();
        
        // Configurar intervalo regular
        this.interval = setInterval(() => {
            this.sendHeartbeat();
            this.checkInactivity();
            this.checkBackupSchedule(); // Verificar backups automáticos
        }, this.heartbeatInterval);
    }
    
    stopHeartbeat() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }
    
    sendHeartbeat() {
        fetch('session_heartbeat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'heartbeat',
                timestamp: Date.now()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'expired') {
                this.handleSessionExpired();
            } else if (data.status === 'multiple_sessions') {
                this.handleMultipleSessions();
            }
        })
        .catch(error => {
            console.error('Error en heartbeat:', error);
        });
    }
    
    setupActivityListeners() {
        // Detectar actividad del usuario
        const events = [
            'mousedown', 'mousemove', 'keypress', 'scroll', 
            'touchstart', 'click', 'focus', 'blur'
        ];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateLastActivity();
            }, true);
        });
        
        // Actividad en inputs específicos
        document.addEventListener('input', () => {
            this.updateLastActivity();
        }, true);
        
        document.addEventListener('change', () => {
            this.updateLastActivity();
        }, true);
    }
    
    setupVisibilityListeners() {
        // Detectar cuando el usuario cambia de pestaña o minimiza
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Usuario cambió de pestaña
                console.log('Usuario cambió de pestaña');
            } else {
                // Usuario volvió a la pestaña
                this.updateLastActivity();
                console.log('Usuario volvió a la pestaña');
            }
        });
        
        // Detectar cuando la ventana pierde o gana foco
        window.addEventListener('blur', () => {
            console.log('Ventana perdió foco');
        });
        
        window.addEventListener('focus', () => {
            this.updateLastActivity();
            console.log('Ventana ganó foco');
        });
        
        // Detectar antes de cerrar la ventana
        window.addEventListener('beforeunload', (e) => {
            this.sendCloseNotification();
        });
        
        // Detectar cuando el navegador se está cerrando
        window.addEventListener('unload', () => {
            // Usar navigator.sendBeacon para envío síncrono
            this.sendCloseNotification();
        });
    }
    
    updateLastActivity() {
        this.lastActivity = Date.now();
        this.warningShown = false;
    }
    
    checkInactivity() {
        const now = Date.now();
        const inactiveTime = now - this.lastActivity;
        
        // Si está inactivo por más tiempo del permitido
        if (inactiveTime >= this.maxInactivity) {
            this.handleInactivity();
        }
        // Si está cerca del límite y no se ha mostrado aviso
        else if (inactiveTime >= this.warningTimeout && !this.warningShown) {
            this.showInactivityWarning();
        }
    }
    
    showInactivityWarning() {
        this.warningShown = true;
        
        const timeRemaining = Math.ceil((this.maxInactivity - (Date.now() - this.lastActivity)) / 1000 / 60);
        
        if (confirm(`⚠️ Sesión por expirar\n\nTu sesión expirará en ${timeRemaining} minutos por inactividad.\n\n¿Deseas mantener la sesión activa?`)) {
            this.updateLastActivity();
            this.sendHeartbeat();
        }
    }
    
    handleInactivity() {
        this.stopHeartbeat();
        
        // Mostrar mensaje de cierre
        const message = document.createElement('div');
        message.innerHTML = `
            <div style="
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.8); color: white;
                display: flex; align-items: center; justify-content: center;
                z-index: 9999; font-family: Arial, sans-serif;
            ">
                <div style="text-align: center; padding: 20px;">
                    <h2>⏰ Sesión Expirada</h2>
                    <p>Tu sesión ha expirado por inactividad.</p>
                    <p>Serás redirigido a la página de login...</p>
                    <div style="margin-top: 20px;">
                        <div class="spinner-border text-light" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(message);
        
        // Redirigir después de 3 segundos
        setTimeout(() => {
            window.location.href = 'index.php?logout_reason=inactividad&message=Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.';
        }, 3000);
    }
    
    handleSessionExpired() {
        this.stopHeartbeat();
        window.location.href = 'index.php?logout_reason=expired&message=Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
    }
    
    handleMultipleSessions() {
        this.stopHeartbeat();
        window.location.href = 'index.php?logout_reason=multiple_sessions&message=Se detectó otra sesión activa. Esta sesión ha sido cerrada por seguridad.';
    }
    
    sendCloseNotification() {
        // Usar sendBeacon para asegurar que el mensaje se envíe
        const data = new FormData();
        data.append('action', 'close');
        data.append('timestamp', Date.now());
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('session_heartbeat.php', data);
        } else {
            // Fallback para navegadores antiguos
            fetch('session_heartbeat.php', {
                method: 'POST',
                body: data,
                keepalive: true
            });
        }
    }
    
    checkBackupSchedule() {
        // Verificar backups automáticos cada 5 minutos (menos frecuente que heartbeat)
        if (!this.lastBackupCheck || Date.now() - this.lastBackupCheck > 5 * 60 * 1000) {
            const data = new FormData();
            data.append('action', 'backup_check');
            data.append('timestamp', Date.now());
            
            fetch('session_heartbeat.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.backup_result && data.backup_result.success) {
                    console.log('✅ Backup automático ejecutado:', data.backup_result.message);
                    // Opcional: mostrar notificación al usuario
                    this.showBackupNotification(data.backup_result.message);
                }
                this.lastBackupCheck = Date.now();
            })
            .catch(error => {
                console.log('Error verificando backups automáticos:', error);
                this.lastBackupCheck = Date.now();
            });
        }
    }
    
    showBackupNotification(message) {
        // Usar el sistema de notificaciones global si está disponible
        if (typeof showNotification === 'function') {
            showNotification('success', message);
        } else {
            // Fallback si la función no está disponible
            console.log('✅ Backup automático ejecutado:', message);
        }
    }
}

// Inicializar el sistema cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.heartbeatManager = new HeartbeatManager();
});

// Manejar errores no capturados
window.addEventListener('error', (e) => {
    console.error('Error no capturado:', e.error);
});

// Manejar promesas rechazadas no capturadas
window.addEventListener('unhandledrejection', (e) => {
    console.error('Promesa rechazada no capturada:', e.reason);
});
