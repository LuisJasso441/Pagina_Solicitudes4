<?php
/**
 * Handler POST: firmar recibe de una SEC.
 * dashboard/salidas_envases/sec/firmar_recibe.php
 *
 * Firma opcional. Se puede firmar en en_ruta / cerrada / cerrada_con_devolucion.
 * NO cambia el estado. Cualquier depto autorizado puede registrarla.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'ventas', 'almacen_residuos'], true)) {
    establecer_alerta('error', 'No tienes permisos para firmar recibe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec_id     = (int) ($_POST['sec_id'] ?? 0);
$nombre     = $_POST['nombre']    ?? '';
$firma_svg  = $_POST['firma_svg'] ?? '';
$usuario_id = (int) $_SESSION['usuario_id'];

$res = firmar_recibe_sec($sec_id, $nombre, $firma_svg, $usuario_id);

if (!$res['success']) {
    $_SESSION['sec_ver_errores'] = $res['errores'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id);
}

$sec = obtener_sec_por_id($sec_id);
if ($sec) notificar_sec_firmada_recibe($sec);

redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '&msg=firma_recibe');