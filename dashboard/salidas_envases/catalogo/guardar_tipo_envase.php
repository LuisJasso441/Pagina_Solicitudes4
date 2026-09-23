<?php
/**
 * Handler POST: crea un tipo nuevo con especificación (modo "nuevo")
 * o agrega una especificación a un tipo existente (modo "existente").
 *
 * dashboard/salidas_envases/catalogo/guardar_tipo_envase.php
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';

// ---- Autenticación + autorización ----
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

$modo       = $_POST['modo'] ?? '';
$usuario_id = (int) $_SESSION['usuario_id'];

if ($modo === 'nuevo') {
    $resultado = crear_tipo_con_especificacion(
        $_POST['tipo_nuevo']    ?? '',
        $_POST['especificacion'] ?? '',
        $usuario_id
    );
} elseif ($modo === 'existente') {
    $tipo_id = (int) ($_POST['tipo_id'] ?? 0);
    if ($tipo_id <= 0) {
        $resultado = ['ok' => false, 'msg' => 'Debe seleccionar un tipo existente.'];
    } else {
        $resultado = agregar_especificacion(
            $tipo_id,
            $_POST['especificacion'] ?? '',
            $usuario_id
        );
    }
} else {
    $resultado = ['ok' => false, 'msg' => 'Modo inválido.'];
}

$_SESSION['flash_msg']  = $resultado['msg'];
$_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'danger';
header('Location: ' . URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
exit;