<?php
/**
 * Endpoint JSON: capacidades configuradas de una unidad.
 * dashboard/salidas_envases/api/capacidades_unidad.php
 *
 * GET params:
 *   unidad_id=int (obligatorio)
 *
 * Response: { ok, capacidades: { "1": 20, "3": 15, ... } }
 *   Mapa especificacion_id → capacidad_maxima.
 *   Si una especificación NO aparece en la respuesta, la unidad NO
 *   está configurada para transportarla.
 *
 * Se usa desde nueva_sec.php / editar_sec.php para validación en vivo
 * al cambiar la unidad seleccionada.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

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
if ($unidad_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT especificacion_id, capacidad_maxima
        FROM unidades_capacidades
        WHERE unidad_id = ?
    ");
    $stmt->execute([$unidad_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mapa = [];
    foreach ($rows as $r) {
        $mapa[(int) $r['especificacion_id']] = (int) $r['capacidad_maxima'];
    }
    echo json_encode(['ok' => true, 'capacidades' => (object) $mapa]);
} catch (Exception $e) {
    error_log('capacidades_unidad: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno']);
}