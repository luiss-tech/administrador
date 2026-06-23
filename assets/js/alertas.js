// Sistema de alertas personalizado
function mostrarAlerta(mensaje, tipo = 'info', tiempo = 5000) {
    // Crear contenedor de alertas si no existe
    let contenedor = document.getElementById('alertas-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'alertas-container';
        contenedor.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(contenedor);
    }

    // Crear alerta
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show shadow-sm`;
    alerta.style.cssText = `
        margin-bottom: 10px;
        animation: slideInRight 0.3s ease-out;
        border-left: 4px solid;
    `;
    
    // Iconos según tipo
    const iconos = {
        'success': 'fas fa-check-circle',
        'danger': 'fas fa-exclamation-triangle',
        'warning': 'fas fa-exclamation-circle',
        'info': 'fas fa-info-circle'
    };

    alerta.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="${iconos[tipo] || iconos.info} me-2"></i>
            <div class="flex-grow-1">${mensaje}</div>
            <button type="button" class="btn-close ms-2" onclick="cerrarAlerta(this)"></button>
        </div>
    `;

    // Agregar al contenedor
    contenedor.appendChild(alerta);

    // Auto-cerrar después del tiempo especificado
    setTimeout(() => {
        if (alerta.parentNode) {
            cerrarAlerta(alerta.querySelector('.btn-close'));
        }
    }, tiempo);
}

function cerrarAlerta(boton) {
    const alerta = boton.closest('.alert');
    if (alerta) {
        alerta.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            if (alerta.parentNode) {
                alerta.remove();
            }
        }, 300);
    }
}

// Agregar animaciones CSS
const estilo = document.createElement('style');
estilo.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .alert {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
    }

    .alert-success {
        background-color: #d1e7dd;
        border-color: #badbcc;
        color: #0f5132;
    }

    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c2c7;
        color: #842029;
    }

    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffecb5;
        color: #664d03;
    }

    .alert-info {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        color: #055160;
    }
`;
document.head.appendChild(estilo);

// Reemplazar alert() global
window.alert = function(mensaje) {
    mostrarAlerta(mensaje, 'warning', 4000);
};

// Funciones de conveniencia
window.alertaSuccess = function(mensaje, tiempo = 4000) {
    mostrarAlerta(mensaje, 'success', tiempo);
};

window.alertaDanger = function(mensaje, tiempo = 6000) {
    mostrarAlerta(mensaje, 'danger', tiempo);
};

window.alertaWarning = function(mensaje, tiempo = 5000) {
    mostrarAlerta(mensaje, 'warning', tiempo);
};

window.alertaInfo = function(mensaje, tiempo = 4000) {
    mostrarAlerta(mensaje, 'info', tiempo);
};
