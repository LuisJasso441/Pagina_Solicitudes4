<?php
/**
 * Handler POST: elimina una vuelta y renumera las restantes de (unidad, fecha).
 * dashboard/salidas_envases/vueltas/eliminar_vuelta.php
 *
 * Responde JSON.
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
    echo json_encode(['ok' => false, 'msg' => 'Solo Logística puede eliminar vueltas.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

$resultado = eliminar_vuelta((int) ($_POST['id'] ?? 0));
echo json_encode($resultado);