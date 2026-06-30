<?php
/**
 * Handler: Crear / Actualizar Disponibilidad de Unidad
 *
 * Ubicación: dashboard/salidas_envases/guardar_disponibilidad.php
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
    establecer_alerta('error', 'No tienes permisos para gestionar Disponibilidad.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/disponibilidad_unidades.php');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$accion     = $_POST['accion'] ?? '';
$id         = (int)($_POST['id'] ?? 0);
$base_url   = URL_BASE . 'dashboard/salidas_envases/disponibilidad_unidades.php';

$datos = [
    'unidad_transporte_id' => $_POST['unidad_transporte_id'] ?? 0,
    'fecha'                => $_POST['fecha'] ?? '',
    'hora_inicio_ruta'     => $_POST['hora_inicio_ruta'] ?? '',
    'hora_termino_ruta'    => $_POST['hora_termino_ruta'] ?? '',
    'slots_inicio'         => $_POST['slots_inicio'] ?? [],
    'slots_fin'            => $_POST['slots_fin']    ?? [],
];

if ($accion === 'crear') {

    $resultado = crear_disponibilidad($datos, $usuario_id);
    if ($resultado['success']) {
        redirigir($base_url . '?msg=creada');
    } else {
        $_SESSION['disponibilidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error_validacion');
    }

} elseif ($accion === 'editar') {

    if ($id <= 0) redirigir($base_url . '?msg=error');

    $resultado = actualizar_disponibilidad($id, $datos, $usuario_id);
    if ($resultado['success']) {
        redirigir($base_url . '?msg=actualizada');
    } else {
        $_SESSION['disponibilidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error_validacion');
    }

} else {
    redirigir($base_url . '?msg=error');
}