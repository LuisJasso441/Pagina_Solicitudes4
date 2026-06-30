<?php
/**
 * Handler: firmar Entrega de SEC
 *
 * Ubicación: dashboard/salidas_envases/firmar_entrega.php
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
    establecer_alerta('error', 'Sólo Almacén de Residuos puede firmar Entrega.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec_id     = (int)($_POST['sec_id'] ?? 0);
$usuario_id = (int)$_SESSION['usuario_id'];
$nombre     = $_POST['entrega_nombre'] ?? '';
$firma      = $_POST['entrega_firma']  ?? '';

if ($sec_id <= 0) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$resultado = firmar_entrega_sec($sec_id, $nombre, $firma, $usuario_id);

if ($resultado['success']) {
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=entrega_firmada");
} else {
    $_SESSION['sec_errores'] = $resultado['errores'];
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$sec_id&msg=error_validacion");
}