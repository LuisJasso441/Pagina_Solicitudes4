<?php
/**
 * Handler POST: crea o actualiza una unidad de transporte con sus capacidades.
 * dashboard/salidas_envases/unidades/guardar_unidad_transporte.php
 *
 * Un solo handler para crear (id vacío) y editar (id > 0).
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
    $_SESSION['flash_msg']  = 'Solo Logística puede gestionar unidades de transporte.';
    $_SESSION['flash_tipo'] = 'danger';
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
    exit;
}

// ---- Recolectar datos ----
$id_raw = trim($_POST['id'] ?? '');
$id     = ($id_raw === '') ? null : (int) $id_raw;

$datos = [
    'nombre'    => $_POST['nombre']    ?? '',
    'matricula' => $_POST['matricula'] ?? '',
    'notas'     => $_POST['notas']     ?? '',
    'activo'    => isset($_POST['activo']) ? 1 : 0,
];

// Capacidades: array asociativo indexado, cada elemento con especificacion_id y capacidad_maxima
$capacidades_raw = $_POST['capacidades'] ?? [];
$capacidades = [];
if (is_array($capacidades_raw)) {
    foreach ($capacidades_raw as $cap) {
        if (!is_array($cap)) continue;
        $capacidades[] = [
            'especificacion_id' => (int) ($cap['especificacion_id'] ?? 0),
            'capacidad_maxima'  => (int) ($cap['capacidad_maxima']  ?? 0),
        ];
    }
}

$resultado = guardar_unidad_transporte($id, $datos, $capacidades, (int) $_SESSION['usuario_id']);

$_SESSION['flash_msg']  = $resultado['msg'];
$_SESSION['flash_tipo'] = $resultado['ok'] ? 'success' : 'danger';
header('Location: ' . URL_BASE . 'dashboard/salidas_envases/unidades/unidades_transporte.php');
exit;