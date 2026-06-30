<?php
/**
 * Handler: Crear / Actualizar Unidad de Transporte
 *
 * Ubicación: dashboard/salidas_envases/guardar_unidad_transporte.php
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

// Solo Logística
if (!es_logistica()) {
    establecer_alerta('error', 'No tienes permisos para gestionar Unidades de Transporte.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/unidades_transporte.php');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$accion     = $_POST['accion'] ?? '';
$id         = (int)($_POST['id'] ?? 0);

$datos = [
    'nombre'           => $_POST['nombre']           ?? '',
    'placas'           => $_POST['placas']           ?? '',
    'capacidad_tmb'    => $_POST['capacidad_tmb']    ?? 0,
    'capacidad_tote'   => $_POST['capacidad_tote']   ?? 0,
    'capacidad_gfa'    => $_POST['capacidad_gfa']    ?? 0,
    'capacidad_jaula'  => $_POST['capacidad_jaula']  ?? 0,
];

$base_url = URL_BASE . 'dashboard/salidas_envases/unidades_transporte.php';

if ($accion === 'crear') {

    $resultado = crear_unidad_transporte($datos, $usuario_id);

    if ($resultado['success']) {
        redirigir($base_url . '?msg=creada');
    } else {
        $_SESSION['unidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error_validacion');
    }

} elseif ($accion === 'editar') {

    if ($id <= 0) {
        redirigir($base_url . '?msg=error');
    }

    $existente = obtener_unidad_transporte_por_id($id);
    if (!$existente) {
        establecer_alerta('error', 'La unidad de transporte no existe.');
        redirigir($base_url);
    }

    $resultado = actualizar_unidad_transporte($id, $datos, $usuario_id);

    if ($resultado['success']) {
        redirigir($base_url . '?msg=actualizada');
    } else {
        $_SESSION['unidad_errores'] = $resultado['errores'];
        redirigir($base_url . '?msg=error_validacion');
    }

} else {

    redirigir($base_url . '?msg=error');
}