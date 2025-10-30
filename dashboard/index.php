<?php
/**
 * Redirección a dashboard según tipo de usuario
 * ACTUALIZADO: Redirige correctamente a colaborativos
 */

// Activar errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config/config.php';

// Verificar sesión activa
require_once __DIR__ . '/../auth/verificar_sesion.php';

// DEBUG: Mostrar información del usuario
echo "<!-- DEBUG INFO -->";
echo "<!-- Usuario ID: " . $_SESSION['usuario_id'] . " -->";
echo "<!-- Departamento: " . $_SESSION['departamento'] . " -->";
echo "<!-- Es TI: " . (es_usuario_ti() ? 'SI' : 'NO') . " -->";
echo "<!-- Es Colaborativo: " . (es_usuario_colaborativo() ? 'SI' : 'NO') . " -->";
echo "<!-- /DEBUG INFO -->";

// Redirigir según tipo de usuario
if (es_usuario_ti()) {
    // Usuario de TI/Sistemas
    echo "<!-- Redirigiendo a TI/Sistemas -->";
    redirigir(URL_BASE . 'dashboard/ti_sistemas.php');
} elseif (es_usuario_colaborativo()) {
    // Usuario de departamento colaborativo (Normatividad, Ventas, Laboratorio)
    echo "<!-- Redirigiendo a Colaborativo -->";
    redirigir(URL_BASE . 'dashboard/colaborativo.php');
} else {
    // Usuario de departamento normal
    echo "<!-- Redirigiendo a Departamento Normal -->";
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

?>