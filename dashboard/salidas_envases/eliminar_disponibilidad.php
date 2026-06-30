<?php
/**
 * Handler: Eliminar Disponibilidad de Unidad
 *
 * Ubicación: dashboard/salidas_envases/eliminar_disponibilidad.php
 *
 * Sólo se permite si el bloque NO tiene slots ocupados.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/disponibilidad_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!es_logistica()) {
    establecer_alerta('error', 'No tienes permisos para eliminar Disponibilidad.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/disponibilidad_unidades.php');
}

$id = (int)($_POST['id'] ?? 0);
$base_url = URL_BASE . 'dashboard/salidas_envases/disponibilidad_unidades.php';

if ($id <= 0) {
    redirigir($base_url . '?msg=error');
}

$resultado = eliminar_disponibilidad($id);
if ($resultado['success']) {
    redirigir($base_url . '?msg=eliminada');
} else {
    $_SESSION['disponibilidad_errores'] = $resultado['errores'];
    redirigir($base_url . '?msg=error_validacion');
}