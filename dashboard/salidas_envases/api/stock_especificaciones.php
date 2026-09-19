<?php
/**
 * Endpoint JSON: stock actual por especificación.
 * dashboard/salidas_envases/api/stock_especificaciones.php
 *
 * GET params (opcionales):
 *   ids=1,2,3  (filtra por lista de especificacion_id; si se omite, devuelve todas las activas)
 *
 * Response: { ok, stock: { "1": 45, "2": 120, ... } }
 *
 * Se usa desde nueva_sec.php / editar_sec.php para pintar en rojo las
 * cantidades que exceden el stock disponible mientras el usuario escribe.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/inventario_funciones.php';

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

// Filtro opcional por lista de ids
$ids_raw = trim($_GET['ids'] ?? '');
$ids_filtro = [];
if ($ids_raw !== '') {
    foreach (explode(',', $ids_raw) as $x) {
        $x = (int) trim($x);
        if ($x > 0) $ids_filtro[] = $x;
    }
}

try {
    $pdo = conectarDB();
    if (!empty($ids_filtro)) {
        $ph = implode(',', array_fill(0, count($ids_filtro), '?'));
        $sql = "
            SELECT e.id AS especificacion_id, COALESCE(i.cantidad_actual, 0) AS cantidad_actual
            FROM sec_especificaciones e
            LEFT JOIN sec_inventario i ON i.especificacion_id = e.id
            WHERE e.id IN ({$ph})
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids_filtro);
    } else {
        // Todas las especificaciones activas
        $sql = "
            SELECT e.id AS especificacion_id, COALESCE(i.cantidad_actual, 0) AS cantidad_actual
            FROM sec_especificaciones e
            INNER JOIN sec_tipos_envase t ON t.id = e.tipo_envase_id
            LEFT  JOIN sec_inventario i   ON i.especificacion_id = e.id
            WHERE e.activo = 1 AND t.activo = 1
        ";
        $stmt = $pdo->query($sql);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mapa = [];
    foreach ($rows as $r) {
        $mapa[(int) $r['especificacion_id']] = (int) $r['cantidad_actual'];
    }
    echo json_encode(['ok' => true, 'stock' => (object) $mapa]);
} catch (Exception $e) {
    error_log('stock_especificaciones: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno']);
}