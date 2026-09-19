<?php
/**
 * Handler POST: registrar una devolución en una SEC.
 * dashboard/salidas_envases/sec/registrar_devolucion.php
 *
 * Solo Logística y Almacén de Residuos.
 * Solo estados: en_ruta, cerrada, cerrada_con_devolucion.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_devoluciones_funciones.php';

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
if (!in_array($dept, ['logistica', 'almacen_residuos'], true)) {
    establecer_alerta('error', 'Solo Logística y Almacén de Residuos pueden registrar devoluciones.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec_id      = (int) ($_POST['sec_id']       ?? 0);
$linea_id    = (int) ($_POST['linea_id']     ?? 0);
$cantidad    = (int) ($_POST['cantidad']     ?? 0);
$motivo      = $_POST['motivo']              ?? '';
$motivo_otro = $_POST['motivo_otro']         ?? '';
$usuario_id  = (int) $_SESSION['usuario_id'];

$res = crear_devolucion_sec($sec_id, $linea_id, $cantidad, $motivo, $motivo_otro, $usuario_id);

if (!$res['success']) {
    $_SESSION['sec_ver_errores'] = $res['errores'];
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '#devoluciones');
}

// Notificar
$sec = obtener_sec_por_id($sec_id);
if ($sec) {
    // Cargar la devolución recién creada para tener empresa_nombre exacto
    $devs = obtener_devoluciones_sec($sec_id);
    $dev_creada = null;
    foreach ($devs as $d) {
        if ((int) $d['id'] === (int) $res['devolucion_id']) { $dev_creada = $d; break; }
    }
    if ($dev_creada) {
        notificar_devolucion_registrada($sec, $dev_creada);
    }
}

redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id . '&msg=devolucion#devoluciones');