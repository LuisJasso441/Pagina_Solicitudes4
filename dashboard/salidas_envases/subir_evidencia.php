<?php
/**
 * Handler: subir evidencia (imagen) a una SEC
 *
 * Ubicación: dashboard/salidas_envases/subir_evidencia.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!es_almacen_residuos()) {
    establecer_alerta('error', 'Sólo Almacén de Residuos puede subir evidencias.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id = (int)($_POST['sec_id'] ?? 0);
if ($sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

// Verificar que la SEC exista y esté en estado que permita subir
$sec = obtener_sec_por_id($sec_id);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}
if (in_array($sec['estado'], ['cerrada', 'cancelada'], true)) {
    establecer_alerta('error', 'No se pueden subir evidencias a una SEC ' . $sec['estado'] . '.');
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id");
}

if (!isset($_FILES['evidencia'])) {
    $_SESSION['sec_errores'] = ['No se recibió ningún archivo.'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion");
}

$usuario_id = (int)$_SESSION['usuario_id'];
$resultado = subir_evidencia_sec($sec_id, $_FILES['evidencia'], $usuario_id);

if ($resultado['success']) {
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=evidencia_subida");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion");
}