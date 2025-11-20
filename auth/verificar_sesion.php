<?php
/**
 * Sistema de verificación de sesión
 * Compatible con functions.php existente
 */

// Asegurarse de que functions.php esté cargado
if (!function_exists('sesion_activa')) {
    require_once __DIR__ . '/../includes/functions.php';
}

/**
 * Verifica que el usuario tenga una sesión activa
 * Redirige al login si no está autenticado
 */
function verificar_sesion() {
    // Verificar si la sesión está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si el usuario está autenticado usando la función existente
    if (!sesion_activa()) {
        // Redirigir al login
        if (defined('URL_BASE')) {
            header('Location: ' . URL_BASE . 'index.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
    
    return true;
}
?>