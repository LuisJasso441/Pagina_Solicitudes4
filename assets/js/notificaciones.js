/**
 * Sistema de notificaciones en tiempo real - VERSIÓN CORREGIDA
 * Con rutas absolutas y mejor manejo de errores
 */

console.log('🔔 Script notificaciones.js cargado');

class SistemaNotificaciones {
    constructor() {
        console.log('🔔 Constructor llamado');
        this.eventSource = null;
        this.reconnectInterval = 5000;
        this.reconnectTimer = null;
        this.maxReconnectAttempts = 5; // Máximo intentos de reconexión
        this.reconnectAttempts = 0;
        this.notificacionesActivas = new Map();
        this.sonidoActivado = true;
        
        // URL BASE - HARDCODEADA para evitar problemas de detección
        this.URL_BASE = '/Pagina_Solicitudes4/';
        console.log('→ URL_BASE configurada:', this.URL_BASE);
        
        console.log('🔔 Llamando a init()...');
        this.init();
    }
    
    async init() {
        console.log('🔔 Inicializando sistema de notificaciones...');
        
        try {
            // 1. Verificar y solicitar permisos
            console.log('🔳 PASO 1: Solicitando permisos...');
            await this.solicitarPermisos();
            
            // 2. Registrar Service Worker (opcional, no bloquea si falla)
            console.log('🔳 PASO 2: Registrando Service Worker...');
            await this.registrarServiceWorker();
            
            // 3. Conectar al servidor SSE
            console.log('🔳 PASO 3: Conectando SSE...');
            this.conectarSSE();
            
            // 4. Configurar UI
            console.log('🔳 PASO 4: Configurando UI...');
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
            const swUrl = this.URL_BASE + 'sw.js';
            console.log('→ Registrando SW en:', swUrl);
            
            const registration = await navigator.serviceWorker.register(swUrl, {
                scope: this.URL_BASE
            });
            
            console.log('✅ Service Worker registrado');
            console.log('→ Scope:', registration.scope);
            console.log('→ Estado:', registration.active ? 'activo' : 'pendiente');
            
            return registration;
            
        } catch (error) {
            // No es crítico si falla el SW, las notificaciones aún funcionan
            console.warn('⚠️ Service Worker no disponible (no crítico):', error.message);
            return false;
        }
    }
    
    conectarSSE() {
        console.log('→ Iniciando conexión SSE...');
        
        // Verificar si ya alcanzamos el máximo de intentos
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.warn('⚠️ Máximo de intentos de reconexión alcanzado. Deteniendo SSE.');
            this.mostrarEstadoConexion(false);
            return;
        }
        
        if (this.eventSource) {
            console.log('→ Cerrando conexión anterior...');
            this.eventSource.close();
        }
        
        const sseUrl = this.URL_BASE + 'notificaciones/stream.php';
        console.log('→ Conectando a:', sseUrl);
        
        try {
            this.eventSource = new EventSource(sseUrl);
            
            this.eventSource.addEventListener('connected', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    console.log('✅ SSE Conectado:', data.message);
                    this.mostrarEstadoConexion(true);
                    this.reconnectAttempts = 0; // Reset intentos en conexión exitosa
                } catch (err) {
                    console.log('✅ SSE Conectado');
                }
            });
            
            this.eventSource.addEventListener('notificacion', (e) => {
                try {
                    const notificacion = JSON.parse(e.data);
                    console.log('📬 Nueva notificación recibida:', notificacion);
                    this.procesarNotificacion(notificacion);
                } catch (err) {
                    console.error('Error procesando notificación:', err);
                }
            });
            
            this.eventSource.addEventListener('heartbeat', (e) => {
                // Solo log cada 5 heartbeats para no saturar la consola
                if (Math.random() < 0.2) {
                    console.log('💓 Heartbeat recibido');
                }
            });
            
            this.eventSource.onopen = () => {
                console.log('✅ Conexión SSE abierta');
                this.mostrarEstadoConexion(true);
                this.reconnectAttempts = 0;
            };
            
            this.eventSource.onerror = (error) => {
                console.error('❌ Error en conexión SSE:', error);
                console.log('→ ReadyState:', this.eventSource.readyState);
                this.mostrarEstadoConexion(false);
                this.eventSource.close();
                
                this.reconnectAttempts++;
                
                if (this.reconnectAttempts < this.maxReconnectAttempts) {
                    const delay = this.reconnectInterval * this.reconnectAttempts;
                    console.log(`→ Intento ${this.reconnectAttempts}/${this.maxReconnectAttempts}. Reconectando en ${delay/1000} segundos...`);
                    this.reconnectTimer = setTimeout(() => {
                        this.conectarSSE();
                    }, delay);
                } else {
                    console.warn('⚠️ SSE deshabilitado tras múltiples fallos. Las notificaciones en tiempo real no estarán disponibles.');
                }
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
            const notification = new Notification(notificacion.titulo || 'Nueva notificación', {
                body: notificacion.mensaje || '',
                icon: this.URL_BASE + 'assets/img/notification-icon.png',
                tag: `notif-${notificacion.id || Date.now()}`,
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
            const response = await fetch(this.URL_BASE + 'notificaciones/contar.php');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
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
            console.warn('⚠️ No se pudo actualizar contador:', error.message);
        }
    }
    
    agregarNotificacionUI(notificacion) {
        const contenedor = document.getElementById('notificaciones-lista');
        if (!contenedor) return;
        
        const elemento = document.createElement('div');
        elemento.className = 'notificacion-item nueva';
        elemento.dataset.id = notificacion.id;
        elemento.innerHTML = `
            <div class="notificacion-icono">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div class="notificacion-contenido">
                <strong>${notificacion.titulo || 'Notificación'}</strong>
                <p>${notificacion.mensaje || ''}</p>
                <small class="text-muted">${new Date().toLocaleTimeString()}</small>
            </div>
            <button class="btn btn-sm btn-link" onclick="sistemaNotificaciones.marcarComoLeida(${notificacion.id})">
                <i class="bi bi-check"></i>
            </button>
        `;
        
        contenedor.insertBefore(elemento, contenedor.firstChild);
        
        // Remover clase 'nueva' después de animación
        setTimeout(() => {
            elemento.classList.remove('nueva');
        }, 3000);
    }
    
    mostrarEstadoConexion(conectado) {
        console.log('→ Estado conexión:', conectado ? 'CONECTADO' : 'DESCONECTADO');
        const indicador = document.getElementById('conexion-estado');
        if (indicador) {
            indicador.className = 'conexion-estado ' + (conectado ? 'conectado' : 'desconectado');
            indicador.title = conectado ? 'Conectado al servidor' : 'Desconectado';
        }
    }
    
    configurarUI() {
        console.log('→ Configurando UI...');
        // La UI se configura automáticamente al cargar la página
    }
    
    mostrarNotificacionBienvenida() {
        console.log('→ Mostrando notificación de bienvenida...');
        try {
            new Notification('🔔 Notificaciones Activadas', {
                body: 'Recibirás notificaciones en tiempo real',
                icon: this.URL_BASE + 'assets/img/notification-icon.png'
            });
        } catch (error) {
            console.error('Error al mostrar bienvenida:', error);
        }
    }
    
    async marcarComoLeida(id) {
        console.log('→ Marcando como leída:', id);
        
        try {
            const response = await fetch(this.URL_BASE + 'notificaciones/marcar_leida.php', {
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
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
        if (this.eventSource) {
            this.eventSource.close();
            console.log('🔌 Conexión SSE cerrada');
        }
    }
}

// Instancia global
let sistemaNotificaciones = null;

console.log('→ Esperando DOMContentLoaded...');

document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ DOM cargado, creando instancia...');
    
    // Pequeño delay para asegurar que Bootstrap esté cargado
    setTimeout(() => {
        sistemaNotificaciones = new SistemaNotificaciones();
    }, 100);
});

window.addEventListener('beforeunload', () => {
    if (sistemaNotificaciones) {
        sistemaNotificaciones.destruir();
    }
});

console.log('📄 Fin del script notificaciones.js');