<?php
/**
 * API - Eventos del Calendario de Mantenimientos (Solo Sistemas)
 * dashboard/sistemas/ti_sistemas/mantenimientos/api_calendario_mantenimientos.php
 *
 * GET:
 *   inicio          YYYY-MM-DD (incluido)
 *   fin             YYYY-MM-DD (excluido)
 *   estados         lista separada por comas (pendiente,en_proceso,finalizada,cerrada,cancelada)
 *   departamento_id opcional
 *   historicos      1 = incluir registros históricos (default), 0 = excluirlos
 *
 * Respuesta: arreglo de eventos en formato FullCalendar.
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function cal_responder(int $codigo, $datos): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    cal_responder(401, ['success' => false, 'message' => 'La sesión expiró. Vuelve a iniciar sesión.']);
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if (!in_array($departamento, ['ti', 'sistemas', 'ti_sistemas'])) {
    cal_responder(403, ['success' => false, 'message' => 'Sin permisos.']);
}

// ------- Parametros -------
$regex_fecha = '/^\d{4}-\d{2}-\d{2}$/';
$inicio = substr(trim($_GET['inicio'] ?? ''), 0, 10);
$fin    = substr(trim($_GET['fin'] ?? ''), 0, 10);

if (!preg_match($regex_fecha, $inicio) || !preg_match($regex_fecha, $fin)) {
    cal_responder(400, ['success' => false, 'message' => 'Rango de fechas no válido.']);
}

$estados_validos = ['pendiente', 'en_proceso', 'finalizada', 'cerrada', 'cancelada'];
$estados = array_values(array_intersect(
    array_map('trim', explode(',', $_GET['estados'] ?? '')),
    $estados_validos
));
if (empty($estados)) {
    cal_responder(200, []);   // ningún estado seleccionado
}

$departamento_id = intval($_GET['departamento_id'] ?? 0);
$con_historicos  = ($_GET['historicos'] ?? '1') === '1';

// ------- Consulta -------
$ph = implode(',', array_fill(0, count($estados), '?'));

$sql = "SELECT sm.id, sm.folio, sm.tipo_mantenimiento, sm.estado, sm.prioridad,
               sm.fecha_deseada, sm.hora_deseada, sm.es_historico, sm.tipo_equipo,
               sm.descripcion_problema,
               u.nombre_completo  AS usuario_nombre,
               d.nombre           AS departamento_nombre,
               ie.codigo_interno  AS equipo_codigo,
               ie.hostname        AS equipo_hostname,
               ie.marca           AS equipo_marca,
               ie.modelo          AS equipo_modelo
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN usuarios u            ON sm.usuario_id = u.id
        LEFT JOIN departamentos d       ON sm.departamento_id = d.id
        LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
        WHERE sm.fecha_deseada >= ?
          AND sm.fecha_deseada < ?
          AND sm.estado IN ($ph)";
$params = array_merge([$inicio, $fin], $estados);

if ($departamento_id) {
    $sql .= " AND sm.departamento_id = ?";
    $params[] = $departamento_id;
}
if (!$con_historicos) {
    $sql .= " AND sm.es_historico = 0";
}
$sql .= " ORDER BY sm.fecha_deseada, sm.hora_deseada, sm.folio";

try {
    $pdo  = conectarDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error en api_calendario_mantenimientos: ' . $e->getMessage());
    cal_responder(500, ['success' => false, 'message' => 'Error al obtener los mantenimientos.']);
}

// ------- Armar eventos -------
$colores = [
    'fisico' => '#0d6efd',
    'logico' => '#198754',
];
$hoy = date('Y-m-d');
$base_ver = URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/';

$eventos = [];
foreach ($filas as $f) {
    $codigo  = trim($f['equipo_codigo'] ?? '');
    $host    = trim($f['equipo_hostname'] ?? '');
    $equipo  = $codigo !== '' ? $codigo : ($host !== '' ? $host : 'Sin equipo');
    $usuario = $f['usuario_nombre'] ?: 'Sin usuario';
    $color   = $colores[$f['tipo_mantenimiento']] ?? '#6c757d';
    $vencido = ($f['estado'] === 'pendiente' && $f['fecha_deseada'] < $hoy);
    $hora    = !empty($f['hora_deseada']) ? substr($f['hora_deseada'], 0, 5) : '';

    $clases = ['mant-evento', 'mant-estado-' . $f['estado']];
    if ($vencido)              $clases[] = 'mant-vencido';
    if ($f['es_historico'])    $clases[] = 'mant-historico';

    $eventos[] = [
        'id'              => (string)$f['id'],
        'title'           => $equipo . ' · ' . $usuario,
        'start'           => $f['fecha_deseada'] . ($hora !== '' ? 'T' . $hora . ':00' : ''),
        'allDay'          => $hora === '',
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'textColor'       => '#ffffff',
        'classNames'      => $clases,
        'extendedProps'   => [
            'folio'        => $f['folio'],
            'tipo_mant'    => $f['tipo_mantenimiento'],
            'estado'       => $f['estado'],
            'prioridad'    => $f['prioridad'],
            'fecha'        => $f['fecha_deseada'],
            'hora'         => $hora,
            'vencido'      => $vencido,
            'es_historico' => (bool)$f['es_historico'],
            'equipo'       => $equipo,
            'hostname'     => ($host !== '' && strcasecmp($host, $codigo) !== 0) ? $host : '',
            'tipo_equipo'  => $f['tipo_equipo'],
            'marca_modelo' => trim(($f['equipo_marca'] ?? '') . ' ' . ($f['equipo_modelo'] ?? '')),
            'usuario'      => $usuario,
            'departamento' => $f['departamento_nombre'] ?: '—',
            'descripcion'  => $f['descripcion_problema'],
            'url_detalle'  => $base_ver
                              . ($f['es_historico'] ? 'ver_mantenimiento_historico.php' : 'ver_mantenimiento.php')
                              . '?id=' . intval($f['id']),
        ],
    ];
}

cal_responder(200, $eventos);