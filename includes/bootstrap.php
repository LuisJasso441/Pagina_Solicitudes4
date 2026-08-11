<?php
/**
 * bootstrap.php — Arranque centralizado de páginas protegidas
 * Ubicación sugerida: includes/bootstrap.php
 *
 * Sustituye el bloque repetido en cada página:
 *   session_start(); + verificar_sesion(); + sesion_expirada(); + actualizar_sesion();
 * y provee render_sidebar() para elegir el sidebar según el rol.
 *
 * USO EN UNA PÁGINA:
 *   require_once __DIR__ . '/RUTA/includes/bootstrap.php';   // ajustar RUTA según profundidad
 *   ... (tu lógica de página) ...
 *   render_sidebar();   // donde antes iba el bloque include del sidebar
 *
 * RETROCOMPATIBLE: las páginas que aún no lo usen siguen funcionando igual.
 * ============================================================
 */

// ------------------------------------------------------------
// 1) Dependencias base (config ya hace session_start + functions.php)
// ------------------------------------------------------------
// __DIR__ es includes/, así que la config está un nivel arriba.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';

// Evita redefinir si por alguna razón el archivo se incluye dos veces
if (!defined('BOOTSTRAP_CARGADO')) {
    define('BOOTSTRAP_CARGADO', true);

    // --------------------------------------------------------
    // 2) Sesión + expiración por inactividad + renovación
    //    (Antes esto estaba copiado al inicio de cada página)
    // --------------------------------------------------------
    verificar_sesion();

    // Solo si esas funciones existen (por seguridad ante versiones distintas)
    if (function_exists('sesion_expirada') && sesion_expirada()) {
        if (function_exists('destruir_sesion')) {
            destruir_sesion();
        }
        session_start();
        if (function_exists('establecer_alerta')) {
            establecer_alerta('warning', 'Tu sesión ha expirado por inactividad. Por favor inicia sesión nuevamente.');
        }
        if (function_exists('redirigir')) {
            redirigir(URL_BASE . 'auth/InicioSesion.php');
        } else {
            header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
            exit;
        }
    }

    if (function_exists('actualizar_sesion')) {
        actualizar_sesion();
    }
}

// ------------------------------------------------------------
// 3) render_sidebar(): elige e incluye el sidebar según el rol
//    La ruta se calcula desde DIR_INCLUDES (constante absoluta de config.php),
//    por lo que NO depende de la profundidad de la página que lo llame.
// ------------------------------------------------------------
if (!function_exists('render_sidebar')) {

    /**
     * Incluye el sidebar correspondiente al rol del usuario en sesión.
     * Un solo lugar con toda la lógica: agregar/ajustar roles se hace aquí.
     */
    function render_sidebar() {
        $dir = rtrim(DIR_INCLUDES, '/\\') . '/sidebar/';

        // Helper local: incluye un sidebar por nombre, con respaldo a sidebar_normal
        $incluir = function ($archivo, $respaldo = 'sidebar_normal.php') use ($dir) {
            $ruta = $dir . $archivo;
            if (is_file($ruta)) {
                include $ruta;
            } else {
                include $dir . $respaldo;
            }
        };

        // Orden de prioridad de roles (idéntico al que ya usaban las páginas)
        if (function_exists('es_usuario_ti') && es_usuario_ti()) {
            $incluir('sidebar_ti.php');
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            $incluir('sidebar_colaborativo.php');
        } elseif (function_exists('es_usuario_epp') && es_usuario_epp()) {
            $incluir('sidebar_inventario.php');
        } elseif (function_exists('es_mantenimiento') && es_mantenimiento()) {
            $incluir('sidebar_mantenimiento.php');
        } elseif (in_array(strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''), ['logistica', 'almacen_residuos'])) {
            // El módulo SEC puede no estar desplegado aún en Producción:
            // el helper cae automáticamente a sidebar_normal.php si no existe.
            $incluir('sidebar_sec.php');
        } else {
            $incluir('sidebar_normal.php');
        }
    }
}