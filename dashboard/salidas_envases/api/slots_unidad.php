<?php
/**
 * Endpoint JSON: slots libres de una unidad en una fecha
 *
 * Ubicación: dashboard/salidas_envases/api/slots_unidad.php
 *
 * GET ?unidad_id=N&fecha=YYYY-MM-DD
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

verificar_sesion();

if (!puede_leer_sec()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin acceso']);
    exit;
}

$unidad_id      = (int)($_GET['unidad_id']      ?? 0);
$fecha          = $_GET['fecha'] ?? '';
$sec_id_excluir = (int)($_GET['sec_id_excluir'] ?? 0);

if ($unidad_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

// En modo edición: incluir los slots ocupados por la propia SEC para que el usuario
// pueda conservar la asignación actual.
if ($sec_id_excluir > 0) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT e.id, e.hora_inicio, e.hora_fin,
                   d.id AS disponibilidad_id,
                   d.hora_inicio_ruta, d.hora_termino_ruta
            FROM sec_espacios_disponibles e
            INNER JOIN sec_disponibilidad_unidades d ON d.id = e.disponibilidad_id
            WHERE d.unidad_transporte_id = ?
              AND d.fecha = ?
              AND (e.ocupado = 0 OR e.sec_id = ?)
            ORDER BY e.hora_inicio ASC
        ");
        $stmt->execute([$unidad_id, $fecha, $sec_id_excluir]);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error slots_unidad (edición): " . $e->getMessage());
        $slots = [];
    }
} else {
    $slots = obtener_slots_libres_unidad_fecha($unidad_id, $fecha);
}

echo json_encode($slots, JSON_UNESCAPED_UNICODE);