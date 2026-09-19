<?php
/**
 * Handler POST: cierre manual de una SEC en en_ruta.
 * dashboard/salidas_envases/sec/cerrar_sec.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_crear_sec()) {
    establecer_alerta('error', 'No tienes permisos para cerrar SECs.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec_id     = (int) ($_POST['sec_id'] ?? 0);
$usuario_id = (int) $_SESSION['usuario_id'];

$res = cerrar_sec($sec_id, $usuario_id);

if (!$res['success']) {
    $_SESSION['sec_ver_errores'] = $res['errores'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id);
}

$sec = obtener_sec_por_id($sec_id);
if ($sec) notificar_sec_cerrada($sec);

redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '&msg=cerrada');