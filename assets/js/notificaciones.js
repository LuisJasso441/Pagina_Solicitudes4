/**
 * Sistema de notificaciones - VERSIÓN COMPLETA
 */

console.log('🔔 Script notificaciones.js cargado');

class SistemaNotificaciones {
    constructor() {
        console.log('🔔 Constructor llamado');
        this.eventSource = null;
        this.reconnectInterval = 5000;
        this.reconnectTimer = null;
        this.notificacionesActivas = new Map();
        this.sonidoActivado = true;
        
        console.log('🔔 Llamando a init()...');
        this.init();
    }
    
    async init() {
        console.log('🔔 Inicializando sistema de notificaciones...');
        
        try {
            // 1. Verificar y solicitar permisos
            console.log('📝 PASO 1: Solicitando permisos...');
            await this.solicitarPermisos();
            
            // 2. Registrar Service Worker
            console.log('📝 PASO 2: Registrando Service Worker...');
            await this.registrarServiceWorker();
            
            // 3. Conectar al servidor SSE
            console.log('📝 PASO 3: Conectando SSE...');
            this.conectarSSE();
            
            // 4. Configurar UI
            console.log('📝 PASO 4: Configurando UI...');
            this.configurarUI();
            
            console.log('✅ Sistema de notificaciones iniciado completamente');
            
        } catch (error) {
            console.error('❌ Error en init():', error);
        }
    }
    
    async solicitarPermisos() {
        console.log('→ Verificando soporte de notificaciones...');
        
        if (!('Notification' in window)) {
            console.warn('❌ Este navegador NO soporta notificaciones de escritorio');
            return false;
        }
        
        console.log('✅ Navegador soporta notificaciones');
        console.log('→ Permiso actual:', Notification.permission);
        
        if (Notification.permission === 'granted') {
            console.log('✅ Permisos ya otorgados');
            return true;
        }
        
        if (Notification.permission === 'denied') {
            console.warn('❌ Permisos denegados por el usuario');
            return false;
        }
        
        console.log('→ Solicitando permisos al usuario...');
        try {
            const permission = await Notification.requestPermission();
            console.log('→ Usuario respondió:', permission);
            
            if (permission === 'granted') {
                console.log('✅ Permisos otorgados');
                this.mostrarNotificacionBienvenida();
                return true;
            } else {
                console.warn('❌ Usuario denegó los permisos');
                return false;
            }
        } catch (error) {
            console.error('❌ Error al solicitar permisos:', error);
            return false;
        }
    }
    
    async registrarServiceWorker() {
        console.log('→ Verificando soporte de Service Worker...');
        
        if (!('serviceWorker' in navigator)) {
            console.warn('❌ Este navegador NO soporta Service Workers');
            return false;
        }
        
        console.log('✅ Navegador soporta Service Workers');
        
        try {
            const swUrl = '/Pagina_Solicitudes4/sw.js';
            console.log('→ Registrando SW en:', swUrl);
            
            const registration = await navigator.serviceWorker.register(swUrl, {
                scope: '/Pagina_Solicitudes4/'
            });
            
            console.log('✅ Service Worker registrado');
            console.log('→ Scope:', registration.scope);
            console.log('→ Estado:', registration.active ? 'activo' : 'pendiente');
            
            return registration;
            
        } catch (error) {
            console.error('❌ Error al registrar Service Worker:', error);
            return false;
        }
    }
    
    conectarSSE() {
        console.log('→ Iniciando conexión SSE...');
        
        if (this.eventSource) {
            console.log('→ Cerrando conexión anterior...');
            this.eventSource.close();
        }
        
        const sseUrl = '/Pagina_Solicitudes4/notificaciones/stream.php';
        console.log('→ Conectando a:', sseUrl);
        
        try {
            this.eventSource = new EventSource(sseUrl);
            
            this.eventSource.addEventListener('connected', (e) => {
                const data = JSON.parse(e.data);
                console.log('✅ SSE Conectado:', data.message);
                this.mostrarEstadoConexion(true);
            });
            
            this.eventSource.addEventListener('notificacion', (e) => {
                const notificacion = JSON.parse(e.data);
                console.log('📬 Nueva notificación recibida:', notificacion);
                this.procesarNotificacion(notificacion);
            });
            
            this.eventSource.addEventListener('heartbeat', (e) => {
                console.log('💓 Heartbeat recibido');
            });
            
            this.eventSource.onerror = (error) => {
                console.error('❌ Error en conexión SSE:', error);
                console.log('→ ReadyState:', this.eventSource.readyState);
                this.mostrarEstadoConexion(false);
                this.eventSource.close();
                
                console.log('→ Intentando reconectar en 5 segundos...');
                this.reconnectTimer = setTimeout(() => {
                    this.conectarSSE();
                }, this.reconnectInterval);
            };
            
            console.log('✅ EventSource creado');
            
        } catch (error) {
            console.error('❌ Error al crear EventSource:', error);
        }
    }
    
    async procesarNotificacion(notificacion) {
        console.log('→ Procesando notificación:', notificacion);
        
        await this.mostrarNotificacionEscritorio(notificacion);
        this.actualizarContador();
        this.agregarNotificacionUI(notificacion);
    }
    
    async mostrarNotificacionEscritorio(notificacion) {
        if (Notification.permission !== 'granted') {
            console.warn('⚠️ No se puede mostrar notificación (permisos no otorgados)');
            return;
        }
        
        console.log('→ Mostrando notificación de escritorio...');
        
        try {
            const notification = new Notification(notificacion.titulo, {
                body: notificacion.mensaje,
                icon: '/Pagina_Solicitudes4/assets/img/notification-icon.png',
                tag: `notif-${notificacion.id}`,
                data: notificacion.datos
            });
            
            notification.onclick = () => {
                console.log('→ Click en notificación');
                window.focus();
                if (notificacion.datos?.url) {
                    window.location.href = notificacion.datos.url;
                }
                notification.close();
            };
            
            console.log('✅ Notificación mostrada');
            
        } catch (error) {
            console.error('❌ Error al mostrar notificación:', error);
        }
    }
    
    async actualizarContador() {
        console.log('→ Actualizando contador...');
        
        try {
            const response = await fetch('/Pagina_Solicitudes4/notificaciones/contar.php');
            const data = await response.json();
            
            console.log('→ Notificaciones no leídas:', data.count);
            
            const badge = document.getElementById('notificaciones-badge');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            
        } catch (error) {
            console.error('❌ Error al actualizar contador:', error);
        }
    }
    
    agregarNotificacionUI(notificacion) {
        console.log('→ Agregando a UI...', notificacion);
        
        const lista = document.getElementById('notificaciones-lista');
        if (!lista) {
            console.warn('⚠️ No se encontró #notificaciones-lista');
            return;
        }
        
        // Si la lista está vacía (mensaje "No hay notificaciones"), limpiarla
        const mensajeVacio = lista.querySelector('.text-center.py-5');
        if (mensajeVacio) {
            lista.innerHTML = '';
        }
        
        // Determinar el color del badge según el tipo
        let badgeColor = 'primary';
        if (notificacion.tipo.includes('orden')) badgeColor = 'info';
        if (notificacion.tipo.includes('firma')) badgeColor = 'warning';
        if (notificacion.tipo.includes('completada')) badgeColor = 'success';
        if (notificacion.tipo.includes('devuelta')) badgeColor = 'danger';
        
        // Crear elemento de notificación
        const notifElement = document.createElement('div');
        notifElement.className = 'notificacion-item nueva';
        notifElement.dataset.id = notificacion.id;
        
        const url = notificacion.datos?.url || '#';
        const timeAgo = 'Ahora mismo';
        
        notifElement.innerHTML = `
            <div class="notificacion-content" style="padding-right: 30px;">
                <div class="notificacion-header">
                    <span class="badge bg-${badgeColor}">
                        ${notificacion.titulo}
                    </span>
                    <small class="text-muted">${timeAgo}</small>
                </div>
                <p class="notificacion-mensaje mb-2">
                    ${notificacion.mensaje}
                </p>
                ${url !== '#' ? `
                <a href="${url}" 
                   class="btn btn-sm btn-outline-primary"
                   onclick="sistemaNotificaciones.marcarComoLeida(${notificacion.id})">
                    <i class="bi bi-eye"></i> Ver detalles
                </a>
                ` : ''}
            </div>
            <button class="btn btn-sm btn-link notificacion-cerrar" 
                    onclick="event.stopPropagation(); sistemaNotificaciones.marcarComoLeida(${notificacion.id})">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        // Insertar al inicio de la lista
        lista.insertBefore(notifElement, lista.firstChild);
        
        // Quitar clase 'nueva' después de 3 segundos
        setTimeout(() => {
            notifElement.classList.remove('nueva');
        }, 3000);
        
        console.log('✅ Notificación agregada a UI');
    }
    
    mostrarEstadoConexion(conectado) {
        console.log('→ Estado conexión:', conectado ? 'CONECTADO' : 'DESCONECTADO');
        const indicador = document.getElementById('conexion-estado');
        if (indicador) {
            indicador.className = 'conexion-estado ' + (conectado ? 'conectado' : 'desconectado');
        }
    }
    
    configurarUI() {
        console.log('→ Configurando UI...');
    }
    
    mostrarNotificacionBienvenida() {
        console.log('→ Mostrando notificación de bienvenida...');
        try {
            new Notification('🔔 Notificaciones Activadas', {
                body: 'Recibirás notificaciones en tiempo real',
                icon: '/Pagina_Solicitudes4/assets/img/notification-icon.png'
            });
        } catch (error) {
            console.error('Error al mostrar bienvenida:', error);
        }
    }
    
    async marcarComoLeida(id) {
        console.log('→ Marcando como leída:', id);
        
        try {
            const response = await fetch('/Pagina_Solicitudes4/notificaciones/marcar_leida.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            });
            
            if (response.ok) {
                // Remover de UI
                const elemento = document.querySelector(`.notificacion-item[data-id="${id}"]`);
                if (elemento) {
                    elemento.style.transition = 'opacity 0.3s';
                    elemento.style.opacity = '0';
                    setTimeout(() => elemento.remove(), 300);
                }
                
                // Actualizar contador
                this.actualizarContador();
            }
        } catch (error) {
            console.error('Error al marcar como leída:', error);
        }
    }
    
    destruir() {
        if (this.eventSource) {
            this.eventSource.close();
            console.log('🔌 Conexión SSE cerrada');
        }
    }
}

// Instancia global
let sistemaNotificaciones;

console.log('→ Esperando DOMContentLoaded...');

document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ DOM cargado, creando instancia...');
    sistemaNotificaciones = new SistemaNotificaciones();
});

window.addEventListener('beforeunload', () => {
    if (sistemaNotificaciones) {
        sistemaNotificaciones.destruir();
    }
});

console.log('📄 Fin del script notificaciones.js');