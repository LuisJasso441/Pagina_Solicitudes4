<?php
/**
 * Handler: Crear nueva SEC
 *
 * Ubicación: dashboard/salidas_envases/guardar_sec.php
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

if (!puede_crear_sec()) {
    establecer_alerta('error', 'No tienes permisos para crear Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$dept       = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

$datos = [
    'fecha_documento'      => $_POST['fecha_documento']    ?? '',
    'solicita_nombre'      => $_POST['solicita_nombre']    ?? '',
    'solicita_firma'       => $_POST['solicita_firma']     ?? '',
    'empresa_destino'      => $_POST['empresa_destino']    ?? '',
    'condiciones_envase'   => $_POST['condiciones_envase'] ?? '',
    'departamento_creador' => $dept,
];

// Reconstruir líneas desde POST
$total_lineas = (int)($_POST['total_lineas'] ?? 0);
$lineas = [];
for ($i = 0; $i < $total_lineas; $i++) {
    $lineas[] = [
        'cantidad'    => (int)($_POST["linea_{$i}_cantidad"]    ?? 0),
        'tipo_envase' => $_POST["linea_{$i}_tipo_envase"] ?? '',
        'slot_id'     => (int)($_POST["linea_{$i}_slot_id"]     ?? 0),
    ];
}

$resultado = crear_sec($datos, $lineas, $usuario_id);

if ($resultado['success']) {
    $folio = urlencode($resultado['folio']);
    redirigir(URL_BASE . "dashboard/salidas_envases/salidas_envases.php?msg=creada&folio=$folio");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    // Conservar lo que el usuario tipeó (solo campos seguros, NO la firma)
    $_SESSION['sec_datos_previos'] = [
        'fecha_documento'    => $datos['fecha_documento'],
        'solicita_nombre'    => $datos['solicita_nombre'],
        'empresa_destino'    => $datos['empresa_destino'],
        'condiciones_envase' => $datos['condiciones_envase'],
    ];
    redirigir(URL_BASE . 'dashboard/salidas_envases/nueva_sec.php');
}