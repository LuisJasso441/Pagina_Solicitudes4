<?php
/**
 * Endpoint JSON: lista de vueltas para una (unidad, fecha).
 * dashboard/salidas_envases/api/vueltas_por_dia.php
 *
 * GET params:
 *   unidad_id=int (obligatorio)
 *   fecha=YYYY-MM-DD (obligatorio)
 *
 * Response: { ok, vueltas: [ {id, numero, notas, creado_en} ] }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/vueltas_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'almacen_residuos', 'ventas'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$unidad_id = (int) ($_GET['unidad_id'] ?? 0);
$fecha     = $_GET['fecha'] ?? '';

if ($unidad_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$vueltas = obtener_vueltas_unidad_fecha($unidad_id, $fecha);

echo json_encode([
    'ok'      => true,
    'vueltas' => array_map(static fn($v) => [
        'id'         => (int) $v['id'],
        'numero'     => (int) $v['numero'],
        'notas'      => $v['notas'],
        'creado_en'  => $v['creado_en'],
    ], $vueltas),
]);