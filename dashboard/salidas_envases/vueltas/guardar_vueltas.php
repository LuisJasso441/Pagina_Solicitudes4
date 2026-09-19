<?php
/**
 * Handler POST: agrega N vueltas para (unidad, fecha).
 * dashboard/salidas_envases/vueltas/guardar_vueltas.php
 *
 * Responde JSON (usado desde el modal por fetch, no redirect).
 *
 * Body (form data):
 *   unidad_id, fecha (YYYY-MM-DD), cantidad, notas
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/vueltas_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}
if (!es_logistica()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Solo Logística puede gestionar vueltas.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

$resultado = agregar_vueltas(
    (int) ($_POST['unidad_id'] ?? 0),
    $_POST['fecha']    ?? '',
    (int) ($_POST['cantidad'] ?? 0),
    $_POST['notas']    ?? '',
    (int) $_SESSION['usuario_id']
);

echo json_encode($resultado);