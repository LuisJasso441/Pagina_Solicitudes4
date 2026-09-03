<?php
/**
 * Handler: firma externa de Recibe
 *
 * Ubicación: dashboard/salidas_envases/firmar_recibe_externo.php
 *
 * Flujo:
 * - Usuario A (Almacén) opera el dispositivo y "pasa" la firma a una persona B.
 * - B escribe su nombre y firma manualmente.
 * - En BD queda:
 *     recibe_nombre     = nombre escrito por B
 *     recibe_firma      = firma manuscrita de B
 *     recibe_usuario_id = ID del usuario A (operador del dispositivo)
 * - Historial marca el origen como 'firma_externa'.
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

// Sólo Almacén de Residuos puede iniciar el flujo de firma externa
if (!es_almacen_residuos()) {
    establecer_alerta('error', 'Sólo Almacén de Residuos puede iniciar una firma externa.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id     = (int)($_POST['sec_id'] ?? 0);
$usuario_id = (int)$_SESSION['usuario_id'];   // el usuario A logueado (operador)
$nombre     = $_POST['recibe_nombre'] ?? '';  // nombre que escribió B
$firma      = $_POST['recibe_firma']  ?? '';  // firma manuscrita de B
$condiciones = [
    'b1' => !empty($_POST['cond_b1']),
    'r2' => !empty($_POST['cond_r2']),
    'a3' => !empty($_POST['cond_a3']),
    'c4' => !empty($_POST['cond_c4']),
];

if ($sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

// Llamada con $es_externo = true
$resultado = firmar_recibe_sec($sec_id, $nombre, $firma, $usuario_id, $condiciones, true);

if ($resultado['success']) {
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=recibe_firmada");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion");
}