<?php
/**
 * estadisticas_solicitudes.php
 * Endpoint JSON que alimenta las gráficas dinámicas del Dashboard TI/Sistemas.
 *
 * Ubicación: dashboard/sistemas/estadisticas_solicitudes.php (junto a ti_sistemas.php)
 *
 * Parámetros GET (todos opcionales):
 *   desde, hasta   Y-m-d — por defecto, el mes actual
 *   departamento   clave tal como está en BD (ej. almacen_residuos)
 *   tipo_soporte   Apoyo | Problema
 *   prioridad      critica | alta | media | baja
 *   estado         pendiente | en_proceso | finalizada | cancelada
 *   tecnico        id del usuario en atendido_por
 *   catalogos=1    incluye las listas para llenar los <select> de filtros
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sis_responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Seguridad: respuestas JSON en lugar de redirecciones ----------
if (empty($_SESSION['usuario_id']) || sesion_expirada()) {
    sis_responder_json(['ok' => false, 'error' => 'sesion'], 401);
}
if (!es_usuario_ti()) {
    sis_responder_json(['ok' => false, 'error' => 'Sin acceso a estas estadísticas'], 403);
}
actualizar_sesion();

// ---------- Etiquetas de departamento ----------
const SIS_DEPARTAMENTOS = [
    'sistemas'            => 'TI / Sistemas',
    'laboratorio'         => 'Laboratorio',
    'normatividad'        => 'Normatividad',
    'ventas'              => 'Ventas',
    'calidad'             => 'Calidad',
    'atencion_clientes'   => 'Atención a Clientes',
    'direccion'           => 'Dirección',
    'tesoreria'           => 'Tesorería',
    'mantenimiento'       => 'Mantenimiento',
    'almacen'             => 'Almacén',
    'almacen_refacciones' => 'Almacén Refacciones',
    'almacen_residuos'    => 'Almacén Residuos',
    'compras'             => 'Compras',
    'contabilidad'        => 'Contabilidad',
    'logistica'           => 'Logística',
    'gestion_talento'     => 'Gestión de Talento',
    'recursos_humanos'    => 'Recursos Humanos',
];

function sis_depto_label(string $clave): string
{
    return SIS_DEPARTAMENTOS[$clave] ?? ucwords(str_replace('_', ' ', $clave));
}

function sis_fecha(?string $valor): ?DateTime
{
    if (!$valor) {
        return null;
    }
    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $fecha : null;
}

// ---------- Filtros (placeholders únicos: ATTR_EMULATE_PREPARES = false en producción) ----------
$desde = sis_fecha($_GET['desde'] ?? null) ?? new DateTime(date('Y-m-01'));
$hasta = sis_fecha($_GET['hasta'] ?? null) ?? new DateTime(date('Y-m-t'));
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$condiciones = ['s.fecha_creacion BETWEEN :f_desde AND :f_hasta'];
$params = [
    ':f_desde' => $desde->format('Y-m-d') . ' 00:00:00',
    ':f_hasta' => $hasta->format('Y-m-d') . ' 23:59:59',
];

$depto = trim((string)($_GET['departamento'] ?? ''));
if ($depto !== '' && preg_match('/^[a-z0-9_]{1,60}$/i', $depto)) {
    $condiciones[] = 's.departamento = :f_depto';
    $params[':f_depto'] = $depto;
}

$tipo = (string)($_GET['tipo_soporte'] ?? '');
if (in_array($tipo, ['Apoyo', 'Problema'], true)) {
    $condiciones[] = 's.tipo_soporte = :f_tipo';
    $params[':f_tipo'] = $tipo;
}

$prioridad = (string)($_GET['prioridad'] ?? '');
if (in_array($prioridad, ['critica', 'alta', 'media', 'baja'], true)) {
    $condiciones[] = 's.prioridad = :f_prio';
    $params[':f_prio'] = $prioridad;
}

$estado = (string)($_GET['estado'] ?? '');
if (in_array($estado, ['pendiente', 'en_proceso', 'finalizada', 'cancelada'], true)) {
    $condiciones[] = 's.estado = :f_estado';
    $params[':f_estado'] = $estado;
}

$tecnico = filter_var($_GET['tecnico'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($tecnico) {
    $condiciones[] = 's.atendido_por = :f_tecnico';
    $params[':f_tecnico'] = $tecnico;
}

$where = implode(' AND ', $condiciones);

try {
    $pdo = conectarDB();

    $consultar = function (string $sql) use ($pdo, $params): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    // 1) KPIs y conteo por estado
    $k = $consultar("
        SELECT
            COUNT(*)                                  AS total,
            COALESCE(SUM(s.estado = 'pendiente'), 0)  AS pendientes,
            COALESCE(SUM(s.estado = 'en_proceso'), 0) AS en_proceso,
            COALESCE(SUM(s.estado = 'finalizada'), 0) AS finalizadas,
            COALESCE(SUM(s.estado = 'cancelada'), 0)  AS canceladas,
            COALESCE(SUM(s.prioridad = 'critica'), 0) AS criticas,
            AVG(CASE WHEN s.estado = 'finalizada' AND s.fecha_atencion IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, s.fecha_creacion, s.fecha_atencion) END) AS prom_resolucion_min
        FROM solicitudes_atencion s
        WHERE $where
    ")[0];

    // 2) Por departamento
    $filasDepto = $consultar("
        SELECT s.departamento, COUNT(*) AS total
        FROM solicitudes_atencion s
        WHERE $where
        GROUP BY s.departamento
        ORDER BY total DESC, s.departamento ASC
    ");

    // 3) Cruce prioridad × tipo de soporte (también da el total por tipo)
    $filasCruce = $consultar("
        SELECT s.prioridad, s.tipo_soporte, COUNT(*) AS total
        FROM solicitudes_atencion s
        WHERE $where
        GROUP BY s.prioridad, s.tipo_soporte
    ");

    $prioridades = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
    $matriz  = array_fill_keys(array_keys($prioridades), ['Apoyo' => 0, 'Problema' => 0]);
    $porTipo = ['Apoyo' => 0, 'Problema' => 0];

    foreach ($filasCruce as $fila) {
        $p = $fila['prioridad'];
        $t = $fila['tipo_soporte'];
        if (isset($matriz[$p][$t])) {
            $matriz[$p][$t] = (int)$fila['total'];
        }
        if (isset($porTipo[$t])) {
            $porTipo[$t] += (int)$fila['total'];
        }
    }

    $respuesta = [
        'ok'      => true,
        'periodo' => ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')],
        'kpis'    => [
            'total'               => (int)$k['total'],
            'pendientes'          => (int)$k['pendientes'],
            'en_proceso'          => (int)$k['en_proceso'],
            'finalizadas'         => (int)$k['finalizadas'],
            'canceladas'          => (int)$k['canceladas'],
            'criticas'            => (int)$k['criticas'],
            'prom_resolucion_min' => $k['prom_resolucion_min'] !== null ? round((float)$k['prom_resolucion_min']) : null,
        ],
        'por_estado' => [
            'claves'  => ['pendiente', 'en_proceso', 'finalizada', 'cancelada'],
            'labels'  => ['Pendiente', 'En Proceso', 'Finalizada', 'Cancelada'],
            'valores' => [(int)$k['pendientes'], (int)$k['en_proceso'], (int)$k['finalizadas'], (int)$k['canceladas']],
        ],
        'por_tipo' => [
            'claves'  => array_keys($porTipo),
            'labels'  => array_keys($porTipo),
            'valores' => array_values($porTipo),
        ],
        'por_departamento' => [
            'claves'  => array_column($filasDepto, 'departamento'),
            'labels'  => array_map('sis_depto_label', array_column($filasDepto, 'departamento')),
            'valores' => array_map('intval', array_column($filasDepto, 'total')),
        ],
        'tipo_prioridad' => [
            'claves'   => array_keys($prioridades),
            'labels'   => array_values($prioridades),
            'apoyo'    => array_column($matriz, 'Apoyo'),
            'problema' => array_column($matriz, 'Problema'),
        ],
    ];

    // Catálogos para los filtros (solo en la primera carga)
    if (!empty($_GET['catalogos'])) {
        $claves = $pdo->query("
            SELECT DISTINCT departamento
            FROM solicitudes_atencion
            WHERE departamento IS NOT NULL AND departamento <> ''
        ")->fetchAll(PDO::FETCH_COLUMN);

        $departamentos = array_map(fn($c) => ['clave' => $c, 'label' => sis_depto_label($c)], $claves);
        usort($departamentos, fn($a, $b) => strcmp($a['label'], $b['label']));

        $tecnicos = $pdo->query("
            SELECT DISTINCT u.id, u.nombre_completo
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON u.id = s.atendido_por
            ORDER BY u.nombre_completo
        ")->fetchAll(PDO::FETCH_ASSOC);

        $respuesta['catalogos'] = [
            'departamentos' => $departamentos,
            'tecnicos'      => $tecnicos,
        ];
    }

    sis_responder_json($respuesta);

} catch (Exception $e) {
    error_log('Error en estadisticas_solicitudes.php: ' . $e->getMessage());
    sis_responder_json(['ok' => false, 'error' => 'Error al consultar las estadísticas'], 500);
}