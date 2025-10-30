/**
 * Service Worker para notificaciones push
 * Maneja notificaciones en segundo plano
 */

const CACHE_NAME = 'solicitudes-ti-v1';

// Instalación del Service Worker
self.addEventListener('install', event => {
    console.log('[SW] Instalando Service Worker...');
    self.skipWaiting();
});

// Activación del Service Worker
self.addEventListener('activate', event => {
    console.log('[SW] Service Worker activado');
    return self.clients.claim();
});

// Evento: Click en notificación
self.addEventListener('notificationclick', event => {
    console.log('[SW] Click en notificación:', event.notification.tag);
    
    event.notification.close();
    
    const accion = event.action;
    const data = event.notification.data;
    
    if (accion === 'cerrar') {
        return;
    }
    
    // Acción "ver" o click en el cuerpo
    if (data && data.url) {
        event.waitUntil(
            clients.openWindow(data.url)
        );
    }
});

// Evento: Cerrar notificación
self.addEventListener('notificationclose', event => {
    console.log('[SW] Notificación cerrada:', event.notification.tag);
});