<?php
/**
 * Handler POST: eliminar evidencia propia.
 * dashboard/salidas_envases/sec/eliminar_evidencia.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_evidencias_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al módulo.');
    redirigir(URL_BASE . 'dashboard/inicio.php');
}

// Solo Almacén de Residuos puede eliminar evidencias de una SEC.
if (!es_almacen_residuos()) {
    establecer_alerta('error', 'Solo Almacén de Residuos puede eliminar evidencias de una SEC.');
    $sec_id_redir = (int) ($_POST['sec_id'] ?? 0);
    if ($sec_id_redir > 0) {
        redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id_redir . '#evidencias');
    }
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$evidencia_id = (int) ($_POST['evidencia_id'] ?? 0);
$sec_id       = (int) ($_POST['sec_id']       ?? 0);
$usuario_id   = (int) $_SESSION['usuario_id'];

$res = eliminar_evidencia_sec($evidencia_id, $usuario_id);

if (!$res['ok']) {
    $_SESSION['sec_ver_errores'] = [$res['msg']];
}

$destino = $res['ok'] ? ((int) ($res['sec_id'] ?? $sec_id)) : $sec_id;
redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $destino . '#evidencias');