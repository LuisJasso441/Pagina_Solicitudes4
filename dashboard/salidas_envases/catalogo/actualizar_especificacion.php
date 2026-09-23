<?php
/**
 * Handler POST: actualiza nombre y estado activo de una especificación.
 * dashboard/salidas_envases/catalogo/actualizar_especificacion.php
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}
if (!puede_administrar_tipos_envase()) {
    $_SESSION['flash_msg'] = 'No tienes permisos para modificar el catálogo.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$nombre = $_POST['nombre'] ?? '';
$activo = (int) ($_POST['activo'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_msg']  = 'ID inválido.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
    exit;
}

$resultado = actualizar_especificacion($id, $nombre, $activo);

$_SESSION['flash_msg']  = $resultado['msg'];
$_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'danger';
header('Location: ' . URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
exit;