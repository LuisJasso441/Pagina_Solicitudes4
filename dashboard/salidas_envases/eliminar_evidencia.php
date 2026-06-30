<?php
/**
 * Handler: eliminar evidencia de una SEC
 *
 * Ubicación: dashboard/salidas_envases/eliminar_evidencia.php
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
    establecer_alerta('error', 'Sólo Almacén de Residuos puede eliminar evidencias.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id       = (int)($_POST['sec_id']       ?? 0);
$evidencia_id = (int)($_POST['evidencia_id'] ?? 0);

if ($sec_id <= 0 || $evidencia_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

// Validar que la SEC esté en estado activo
$sec = obtener_sec_por_id($sec_id);
if (!$sec || in_array($sec['estado'], ['cerrada', 'cancelada'], true)) {
    establecer_alerta('error', 'No se pueden eliminar evidencias en este estado.');
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id");
}

$resultado = eliminar_evidencia_sec($evidencia_id);

if ($resultado['success']) {
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=evidencia_eliminada");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion");
}