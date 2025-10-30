<?php
/**
 * Cerrar sesión del usuario
 * ACTUALIZADO: Redirige a index.php
 */

// Iniciar sesión
session_start();

// Cargar configuración
require_once __DIR__ . '/../config/config.php';

// Destruir sesión
destruir_sesion();

// Reiniciar sesión para mensaje
session_start();

// Establecer mensaje de despedida
establecer_alerta('success', 'Ha cerrado sesión exitosamente');

// Redirigir al index principal
redirigir(URL_BASE . 'index.php');

?>