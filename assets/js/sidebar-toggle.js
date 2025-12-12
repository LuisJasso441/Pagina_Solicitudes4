/**
 * Sidebar Toggle
 * Portal de Solicitudes TI - GrupoVerden
 * 
 * Maneja la funcionalidad del botón hamburguesa y el toggle del sidebar.
 * Incluye: abrir/cerrar sidebar, overlay, y cierre con tecla Escape.
 */

(function() {
    'use strict';

    // ====================================
    // VARIABLES
    // ====================================
    let hamburgerBtn = null;
    let sidebar = null;
    let overlay = null;
    let isOpen = false;

    // ====================================
    // INICIALIZACIÓN
    // ====================================
    function init() {
        // Obtener elementos del DOM
        hamburgerBtn = document.querySelector('.hamburger-btn');
        sidebar = document.querySelector('.sidebar');
        overlay = document.querySelector('.sidebar-overlay');

        // Si no existen los elementos necesarios, salir
        if (!hamburgerBtn || !sidebar) {
            console.warn('Sidebar Toggle: No se encontraron los elementos necesarios');
            return;
        }

        // Crear overlay si no existe
        if (!overlay) {
            createOverlay();
        }

        // Event listeners
        bindEvents();

        // Verificar estado inicial según el tamaño de pantalla
        checkInitialState();
    }

    // ====================================
    // CREAR OVERLAY
    // ====================================
    function createOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.appendChild(overlay);
    }

    // ====================================
    // EVENT LISTENERS
    // ====================================
    function bindEvents() {
        // Click en botón hamburguesa
        hamburgerBtn.addEventListener('click', toggleSidebar);

        // Click en overlay para cerrar
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Tecla Escape para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isOpen) {
                closeSidebar();
            }
        });

        // Resize de ventana
        window.addEventListener('resize', debounce(handleResize, 250));

        // Click en links del sidebar (cerrar en móvil)
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // Solo cerrar en móvil/tablet
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    }

    // ====================================
    // TOGGLE SIDEBAR
    // ====================================
    function toggleSidebar() {
        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // ====================================
    // ABRIR SIDEBAR
    // ====================================
    function openSidebar() {
        isOpen = true;
        
        // Añadir clases activas
        sidebar.classList.add('active');
        hamburgerBtn.classList.add('active');
        
        if (overlay) {
            overlay.classList.add('active');
        }
        
        // Prevenir scroll del body
        document.body.classList.add('sidebar-open');
        
        // Accesibilidad
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        sidebar.setAttribute('aria-hidden', 'false');
        
        // Focus en el sidebar para accesibilidad
        sidebar.focus();
    }

    // ====================================
    // CERRAR SIDEBAR
    // ====================================
    function closeSidebar() {
        isOpen = false;
        
        // Remover clases activas
        sidebar.classList.remove('active');
        hamburgerBtn.classList.remove('active');
        
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Restaurar scroll del body
        document.body.classList.remove('sidebar-open');
        
        // Accesibilidad
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        sidebar.setAttribute('aria-hidden', 'true');
    }

    // ====================================
    // VERIFICAR ESTADO INICIAL
    // ====================================
    function checkInitialState() {
        if (window.innerWidth >= 992) {
            // Desktop: sidebar siempre visible, cerrar estado móvil
            closeSidebar();
        }
    }

    // ====================================
    // MANEJAR RESIZE
    // ====================================
    function handleResize() {
        if (window.innerWidth >= 992) {
            // Al cambiar a desktop, cerrar el estado móvil
            if (isOpen) {
                closeSidebar();
            }
        }
    }

    // ====================================
    // UTILIDAD: DEBOUNCE
    // ====================================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    // ====================================
    // API PÚBLICA (opcional)
    // ====================================
    window.SidebarToggle = {
        open: openSidebar,
        close: closeSidebar,
        toggle: toggleSidebar,
        isOpen: function() { return isOpen; }
    };

    // ====================================
    // INICIAR CUANDO DOM ESTÉ LISTO
    // ====================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();