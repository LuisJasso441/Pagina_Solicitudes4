<?php
/**
 * Handler POST: registra un movimiento de inventario.
 * dashboard/salidas_envases/inventario/guardar_movimiento_inventario.php
 *
 * Solo Almacén de Residuos puede ejecutar movimientos manuales.
 * Los movimientos automáticos (SEC/devolución) se registrarán en bloques
 * futuros llamando directamente a registrar_movimiento() desde el handler
 * correspondiente, no por este endpoint.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/inventario_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}
if (!puede_administrar_inventario()) {
    $_SESSION['flash_msg']  = 'Solo Almacén de Residuos puede registrar movimientos de inventario.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/inventario/inventario.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/inventario/inventario.php');
    exit;
}

$espec_id = (int) ($_POST['especificacion_id'] ?? 0);
$tipo     = $_POST['tipo_movimiento'] ?? '';
$cantidad = (int) ($_POST['cantidad'] ?? -1);
$motivo   = $_POST['motivo'] ?? '';

// Solo permitimos tipos manuales desde este handler
if (!in_array($tipo, ['entrada','salida','ajuste'], true)) {
    $_SESSION['flash_msg']  = 'Tipo de movimiento no permitido desde esta acción.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/inventario/inventario.php');
    exit;
}

$resultado = registrar_movimiento([
    'especificacion_id' => $espec_id,
    'tipo_movimiento'   => $tipo,
    'cantidad'          => $cantidad,
    'motivo'            => $motivo,
    'usuario_id'        => (int) $_SESSION['usuario_id'],
]);

$_SESSION['flash_msg']  = $resultado['msg'];
$_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'danger';
header('Location: ' . URL_BASE . 'dashboard/salidas_envases/inventario/inventario.php');
exit;