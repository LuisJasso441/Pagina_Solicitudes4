<?php
/**
 * Handler: Desactivar / Reactivar Unidad de Transporte (soft delete)
 *
 * Ubicación: dashboard/salidas_envases/eliminar_unidad_transporte.php
 *
 * NOTA: No hay borrado físico. Las unidades sólo se marcan activa=0
 *       para preservar el histórico en SECs ya emitidas.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/unidades_transporte_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!es_logistica()) {
    establecer_alerta('error', 'No tienes permisos para gestionar Unidades de Transporte.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/unidades_transporte.php');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$accion     = $_POST['accion'] ?? '';
$id         = (int)($_POST['id'] ?? 0);
$base_url   = URL_BASE . 'dashboard/salidas_envases/unidades_transporte.php';

if ($id <= 0) {
    redirigir($base_url . '?msg=error');
}

$unidad = obtener_unidad_transporte_por_id($id);
if (!$unidad) {
    establecer_alerta('error', 'La unidad de transporte no existe.');
    redirigir($base_url);
}

if ($accion === 'desactivar') {
    $resultado = desactivar_unidad_transporte($id, $usuario_id);
    if ($resultado['success']) {
        redirigir($base_url . '?msg=desactivada');
    } else {
        $_SESSION['unidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error');
    }
} elseif ($accion === 'reactivar') {
    $resultado = reactivar_unidad_transporte($id, $usuario_id);
    if ($resultado['success']) {
        redirigir($base_url . '?msg=reactivada');
    } else {
        $_SESSION['unidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error');
    }
} else {
    redirigir($base_url . '?msg=error');
}