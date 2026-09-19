<?php
/**
 * Endpoint JSON para FullCalendar: eventos agrupados por (unidad, fecha).
 * dashboard/salidas_envases/api/eventos_vueltas.php
 *
 * GET params:
 *   start=YYYY-MM-DD  (obligatorio, FullCalendar lo envía siempre)
 *   end=YYYY-MM-DD    (obligatorio, exclusivo según FullCalendar)
 *   unidad_id=int     (opcional, filtrar por unidad)
 *
 * Response: array de eventos con:
 *   - id, title, start, allDay
 *   - color, textColor
 *   - extendedProps: {unidad_id, unidad_nombre, total_vueltas}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/vueltas_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'almacen_residuos', 'ventas'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$start = $_GET['start'] ?? '';
$end   = $_GET['end']   ?? '';
$unidad_id = (int) ($_GET['unidad_id'] ?? 0);

// Normalizar fechas: FullCalendar envía YYYY-MM-DD o YYYY-MM-DDTHH:MM:SS
$start = substr($start, 0, 10);
$end   = substr($end, 0, 10);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas']);
    exit;
}

// FullCalendar envía end como exclusivo; restamos 1 día para nuestra query BETWEEN
$fecha_hasta = date('Y-m-d', strtotime($end . ' -1 day'));

$resumen = obtener_resumen_vueltas($start, $fecha_hasta, $unidad_id > 0 ? $unidad_id : null);

// Función simple para asignar color a cada unidad basada en su id
function color_para_unidad($id) {
    $paleta = [
        '#038C73', '#5B9BD5', '#7030A0', '#C55A11',
        '#2E75B6', '#548235', '#BF9000', '#843C0C',
        '#385723', '#1F3864', '#9C4A00', '#4472C4',
    ];
    return $paleta[$id % count($paleta)];
}

$eventos = [];
foreach ($resumen as $r) {
    $total = (int) $r['total_vueltas'];
    $color = color_para_unidad((int) $r['unidad_id']);
    $eventos[] = [
        'id'    => 'u' . $r['unidad_id'] . '-' . $r['fecha'],
        'title' => htmlspecialchars_decode($r['unidad_nombre']) . ' · ' . $total . ' vuelta' . ($total === 1 ? '' : 's'),
        'start' => $r['fecha'],
        'allDay' => true,
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'textColor'       => '#ffffff',
        'extendedProps' => [
            'unidad_id'     => (int) $r['unidad_id'],
            'unidad_nombre' => $r['unidad_nombre'],
            'total_vueltas' => $total,
            'fecha'         => $r['fecha'],
        ],
    ];
}

echo json_encode($eventos);