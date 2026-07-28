<?php
/**
 * Handler: actualizar líneas de SEC (sólo estado = enviada)
 *
 * Ubicación: dashboard/salidas_envases/actualizar_sec.php
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
    establecer_alerta('error', 'No tienes permisos para editar Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id     = (int)($_POST['sec_id'] ?? 0);
$usuario_id = (int)$_SESSION['usuario_id'];

if ($sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$empresa_destino    = $_POST['empresa_destino']    ?? '';
$condiciones_envase = $_POST['condiciones_envase'] ?? '';

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

$resultado = actualizar_lineas_sec($sec_id, $lineas, $usuario_id, $empresa_destino, $condiciones_envase);

$resultado = actualizar_lineas_sec($sec_id, $lineas, $usuario_id);

if ($resultado['success']) {
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=actualizada");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/editar_sec.php?id=$sec_id");
}