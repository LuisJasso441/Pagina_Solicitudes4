<?php
/**
 * Handler POST: elimina una unidad de transporte (y sus capacidades en cascada).
 * dashboard/salidas_envases/unidades/eliminar_unidad_transporte.php
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/unidades_transporte_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}
if (!es_logistica()) {
    $_SESSION['flash_msg']  = 'Solo Logística puede eliminar unidades de transporte.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_msg']  = 'ID inválido.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
    exit;
}

$resultado = eliminar_unidad_transporte($id);

$_SESSION['flash_msg']  = $resultado['msg'];
$_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'danger';
header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
exit;