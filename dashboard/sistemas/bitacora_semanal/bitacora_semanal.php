<?php
/**
 * Bitácora Semanal de Actividades (Solicitudes de Atención)
 * dashboard/sistemas/bitacora_semanal/bitacora_semanal.php
 *
 * Acceso: solo Sistemas / TI
 *
 * - Por defecto muestra las actividades abiertas (Pendientes + En proceso)
 *   de la semana seleccionada (lunes a domingo).
 * - "Abiertas" = solicitudes creadas antes de que termine la semana y que
 *   siguen pendientes o en proceso (incluye las arrastradas de semanas previas).
 * - "Finalizadas" = solicitudes finalizadas dentro de la semana.
 * - Vistas: Lista (tabla) y Tablero (columnas por estado).
 * - Botón Imprimir con formato limpio para archivar la bitácora.
 *
 * v1.0
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_ti = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas'], true)
         || (function_exists('es_usuario_ti') && es_usuario_ti());

if (!$es_ti) {
    establecer_alerta('error', 'Solo el departamento de Sistemas tiene acceso a la Bitácora Semanal.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// =====================================================================
// SEMANA SELECCIONADA (lunes a domingo)
// =====================================================================
$semana_param = $_GET['semana'] ?? '';
$fecha_base = DateTime::createFromFormat('!Y-m-d', $semana_param);
if (!$fecha_base || $fecha_base->format('Y-m-d') !== $semana_param) {
    $fecha_base = new DateTime('today');
}
$lunes = (clone $fecha_base)->modify('monday this week');
$domingo = (clone $lunes)->modify('+6 days');
$fin_semana = (clone $lunes)->modify('+7 days');         // límite exclusivo

$ini_sql = $lunes->format('Y-m-d 00:00:00');
$fin_sql = $fin_semana->format('Y-m-d 00:00:00');

$lunes_actual = (new DateTime('today'))->modify('monday this week');
$es_semana_actual = ($lunes->format('Y-m-d') === $lunes_actual->format('Y-m-d'));
$es_semana_futura = ($lunes > $lunes_actual);

$semana_anterior  = (clone $lunes)->modify('-7 days')->format('Y-m-d');
$semana_siguiente = (clone $lunes)->modify('+7 days')->format('Y-m-d');

$meses_cortos = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
function bs_fecha_corta(DateTime $d, array $meses) {
    return (int) $d->format('j') . ' ' . $meses[(int) $d->format('n')];
}
$etiqueta_semana = 'Semana ' . (int) $lunes->format('W') . ' · '
    . bs_fecha_corta($lunes, $meses_cortos) . ' – ' . bs_fecha_corta($domingo, $meses_cortos)
    . ' ' . $domingo->format('Y');

// =====================================================================
// FILTROS
// =====================================================================
$estados_filtro = [
    'activas'     => 'Pendientes + En proceso',
    'pendiente'   => 'Pendientes',
    'en_proceso'  => 'En proceso',
    'finalizadas' => 'Finalizadas en la semana',
    'todas'       => 'Todas (abiertas + finalizadas)',
];
$filtro_estado = $_GET['estado'] ?? 'activas';
if (!isset($estados_filtro[$filtro_estado])) $filtro_estado = 'activas';

$prioridades = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$filtro_prioridad = $_GET['prioridad'] ?? '';
if (!isset($prioridades[$filtro_prioridad])) $filtro_prioridad = '';

$filtro_tipo = $_GET['tipo'] ?? '';
if (!in_array($filtro_tipo, ['Apoyo', 'Problema'], true)) $filtro_tipo = '';

$filtro_depto   = (int) ($_GET['departamento'] ?? 0);
$filtro_tecnico = $_GET['tecnico'] ?? '';                 // id, 'sin' o ''
if ($filtro_tecnico !== 'sin') {
    $filtro_tecnico = ((int) $filtro_tecnico > 0) ? (string) (int) $filtro_tecnico : '';
}

$busqueda = trim($_GET['buscar'] ?? '');
$vista = (($_GET['vista'] ?? '') === 'tablero') ? 'tablero' : 'lista';

$hay_filtros_extra = ($filtro_prioridad || $filtro_tipo || $filtro_depto || $filtro_tecnico !== '' || $busqueda !== '');

// =====================================================================
// CONSULTA PRINCIPAL
// =====================================================================
// Bloques reutilizables (placeholders posicionales: compatibles con EMULATE_PREPARES = false)
$cond_abiertas    = "(s.estado IN ('pendiente','en_proceso') AND s.fecha_creacion < ?)";
$cond_finalizadas = "(s.estado = 'finalizada' AND s.fecha_atencion >= ? AND s.fecha_atencion < ?)";

$where  = [];
$params = [];

switch ($filtro_estado) {
    case 'pendiente':
    case 'en_proceso':
        $where[]  = "(s.estado = ? AND s.fecha_creacion < ?)";
        $params[] = $filtro_estado;
        $params[] = $fin_sql;
        break;
    case 'finalizadas':
        $where[]  = $cond_finalizadas;
        array_push($params, $ini_sql, $fin_sql);
        break;
    case 'todas':
        $where[]  = "($cond_abiertas OR $cond_finalizadas)";
        array_push($params, $fin_sql, $ini_sql, $fin_sql);
        break;
    default: // activas
        $where[]  = $cond_abiertas;
        $params[] = $fin_sql;
}

if ($filtro_prioridad) {
    $where[]  = "s.prioridad = ?";
    $params[] = $filtro_prioridad;
}
if ($filtro_tipo) {
    $where[]  = "s.tipo_soporte = ?";
    $params[] = $filtro_tipo;
}
if ($filtro_depto) {
    $where[]  = "COALESCE(s.departamento_id, u.departamento_id) = ?";
    $params[] = $filtro_depto;
}
if ($filtro_tecnico === 'sin') {
    $where[] = "s.atendido_por IS NULL";
} elseif ($filtro_tecnico !== '') {
    $where[]  = "s.atendido_por = ?";
    $params[] = (int) $filtro_tecnico;
}
if ($busqueda !== '') {
    $where[] = "(s.folio LIKE ? OR s.descripcion LIKE ? OR COALESCE(s.nombre_solicitante, u.nombre_completo) LIKE ?
                 OR s.tipo_apoyo LIKE ? OR s.tipo_problema LIKE ?)";
    $like = '%' . $busqueda . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$LIMITE = 500;
$sql = "SELECT s.id, s.folio, s.estado, s.prioridad, s.tipo_soporte, s.tipo_apoyo, s.tipo_problema,
               s.descripcion, s.comentarios_ti, s.fecha_creacion, s.fecha_atencion, s.fecha_actualizacion,
               COALESCE(s.nombre_solicitante, u.nombre_completo) AS solicitante,
               COALESCE(d.nombre, s.departamento)               AS depto_nombre,
               ut.nombre_completo                               AS tecnico_nombre,
               (SELECT MAX(h.fecha_cambio) FROM historial_estados h
                 WHERE h.solicitud_id = s.id AND h.estado_nuevo = 'en_proceso') AS fecha_en_proceso
        FROM solicitudes_atencion s
        INNER JOIN usuarios u       ON s.usuario_id = u.id
        LEFT JOIN departamentos d   ON d.id = COALESCE(s.departamento_id, u.departamento_id)
        LEFT JOIN usuarios ut       ON s.atendido_por = ut.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            CASE s.estado WHEN 'en_proceso' THEN 1 WHEN 'pendiente' THEN 2 ELSE 3 END,
            CASE s.prioridad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END,
            s.fecha_creacion ASC
        LIMIT " . ($LIMITE + 1);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
$recortado = count($actividades) > $LIMITE;
if ($recortado) array_pop($actividades);

// =====================================================================
// INDICADORES DE LA SEMANA (sin filtros adicionales)
// =====================================================================
$sql_kpi = "SELECT
    SUM(CASE WHEN estado = 'pendiente'  AND fecha_creacion < ? THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'en_proceso' AND fecha_creacion < ? THEN 1 ELSE 0 END) AS en_proceso,
    SUM(CASE WHEN fecha_creacion >= ? AND fecha_creacion < ? THEN 1 ELSE 0 END)  AS nuevas,
    SUM(CASE WHEN estado = 'finalizada' AND fecha_atencion >= ? AND fecha_atencion < ? THEN 1 ELSE 0 END) AS finalizadas,
    SUM(CASE WHEN estado IN ('pendiente','en_proceso') AND fecha_creacion < ?
              AND prioridad IN ('critica','alta') THEN 1 ELSE 0 END) AS urgentes,
    MAX(CASE WHEN estado IN ('pendiente','en_proceso') AND fecha_creacion < ?
              THEN DATEDIFF(LEAST(NOW(), ?), fecha_creacion) END) AS mas_antigua
FROM solicitudes_atencion";
$stmt = $pdo->prepare($sql_kpi);
$stmt->execute([$fin_sql, $fin_sql, $ini_sql, $fin_sql, $ini_sql, $fin_sql, $fin_sql, $fin_sql, $fin_sql]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
foreach (['pendientes', 'en_proceso', 'nuevas', 'finalizadas', 'urgentes', 'mas_antigua'] as $k) {
    $kpi[$k] = (int) ($kpi[$k] ?? 0);
}

// =====================================================================
// CATÁLOGOS PARA FILTROS
// =====================================================================
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")
                     ->fetchAll(PDO::FETCH_ASSOC);
$tecnicos = $pdo->query("SELECT DISTINCT u.id, u.nombre_completo
                         FROM solicitudes_atencion s
                         INNER JOIN usuarios u ON u.id = s.atendido_por
                         ORDER BY u.nombre_completo")
                ->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// CONFIGURACIÓN VISUAL
// =====================================================================
$badges_estado = [
    'pendiente'  => ['bg-warning text-dark', 'Pendiente'],
    'en_proceso' => ['bg-primary', 'En proceso'],
    'finalizada' => ['bg-success', 'Finalizada'],
    'cancelada'  => ['bg-secondary', 'Cancelada'],
];
$badges_prioridad = [
    'critica' => 'bg-danger',
    'alta'    => 'bg-warning text-dark',
    'media'   => 'bg-info text-dark',
    'baja'    => 'bg-success',
];

// Días de antigüedad (abiertas: hasta hoy / fin de semana; finalizadas: hasta su cierre)
function bs_dias_antiguedad(array $a, DateTime $fin_semana) {
    $inicio = new DateTime($a['fecha_creacion']);
    if ($a['estado'] === 'finalizada' && !empty($a['fecha_atencion'])) {
        $fin = new DateTime($a['fecha_atencion']);
    } else {
        $hoy = new DateTime();
        $fin = ($hoy < $fin_semana) ? $hoy : $fin_semana;
    }
    return max(0, (int) $inicio->diff($fin)->days);
}
function bs_clase_antiguedad($dias) {
    if ($dias > 7) return 'bg-danger';
    if ($dias > 3) return 'bg-warning text-dark';
    return 'bg-light text-dark border';
}
function bs_detalle_tipo(array $a) {
    $detalle = $a['tipo_soporte'] === 'Apoyo' ? ($a['tipo_apoyo'] ?? '') : ($a['tipo_problema'] ?? '');
    return trim((string) $detalle);
}

// Construye URL conservando filtros
function bs_url(array $cambios = []) {
    $q = array_merge($_GET, $cambios);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) unset($q[$k]);
    }
    return '?' . http_build_query($q);
}

// Agrupar para el tablero
$columnas = ['pendiente' => [], 'en_proceso' => [], 'finalizada' => []];
foreach ($actividades as $a) {
    if (isset($columnas[$a['estado']])) $columnas[$a['estado']][] = $a;
}
$mostrar_finalizadas = in_array($filtro_estado, ['finalizadas', 'todas'], true);

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora Semanal - TI / Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">

    <style>
        /* ===== Navegador de semana ===== */
        .bs-semana {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 0.6rem 0.75rem;
        }
        .bs-semana-titulo { font-weight: 600; font-size: 1.05rem; }
        .bs-semana-sub { font-size: 0.75rem; color: #6c757d; }

        /* ===== KPIs ===== */
        .bs-kpi {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            padding: 0.6rem 0.75rem;
            height: 100%;
        }
        .bs-kpi .num { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
        .bs-kpi .lbl {
            font-size: 0.7rem; color: #6c757d;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .bs-kpi.k-pend  { border-top: 3px solid #ffc107; }
        .bs-kpi.k-proc  { border-top: 3px solid #0d6efd; }
        .bs-kpi.k-new   { border-top: 3px solid #6f42c1; }
        .bs-kpi.k-fin   { border-top: 3px solid #198754; }
        .bs-kpi.k-urg   { border-top: 3px solid #dc3545; }
        .bs-kpi.k-old   { border-top: 3px solid #6c757d; }

        /* ===== Chips de estado ===== */
        .bs-chips .btn { font-size: 0.8rem; border-radius: 50rem; }

        /* ===== Tabla ===== */
        .bs-tabla td, .bs-tabla th { vertical-align: middle; font-size: 0.83rem; }
        .bs-tabla .folio { font-family: 'Courier New', monospace; font-weight: 600; white-space: nowrap; }
        .bs-desc { max-width: 320px; }
        .bs-desc small { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bs-fila-proceso { box-shadow: inset 3px 0 0 #0d6efd; }
        .bs-fila-critica { box-shadow: inset 3px 0 0 #dc3545; }
        .badge-nueva { background: #ede9fe; color: #5b21b6; font-weight: 600; }

        /* ===== Tablero ===== */
        .bs-col {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 0.6rem;
            min-height: 160px;
        }
        .bs-col-head { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; }
        .bs-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.55rem 0.65rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }
        .bs-card.p-critica { border-left: 4px solid #dc3545; }
        .bs-card.p-alta    { border-left: 4px solid #ffc107; }
        .bs-card.p-media   { border-left: 4px solid #0dcaf0; }
        .bs-card.p-baja    { border-left: 4px solid #198754; }
        .bs-card .folio { font-family: 'Courier New', monospace; font-weight: 600; font-size: 0.78rem; }

        /* ===== Encabezado sólo para impresión ===== */
        .bs-print-header { display: none; }

        @media print {
            .sidebar, .hamburger-btn, .sidebar-overlay, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            .bs-print-header { display: block; margin-bottom: 0.75rem; }
            .bs-tabla td, .bs-tabla th { font-size: 0.72rem; }
            .bs-desc small { white-space: normal; }
            .card, .bs-kpi { box-shadow: none !important; }
            a { text-decoration: none; color: #000; }
            .badge { border: 1px solid #999; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado de impresión -->
            <div class="bs-print-header">
                <h5 class="mb-0">Bitácora Semanal de Actividades — Sistemas</h5>
                <small><?php echo htmlspecialchars($etiqueta_semana); ?> · Filtro: <?php echo htmlspecialchars($estados_filtro[$filtro_estado]); ?>
                    · Impreso el <?php echo date('d/m/Y H:i'); ?> por <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ''); ?></small>
            </div>

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
                <div>
                    <h4 class="mb-0"><i class="bi bi-journal-check me-2"></i>Bitácora Semanal</h4>
                    <small class="text-muted">Actividades (solicitudes de atención) pendientes y en proceso por semana</small>
                </div>
                <div class="d-flex gap-2">
                    <div class="btn-group" role="group" aria-label="Vista">
                        <a href="<?php echo bs_url(['vista' => 'lista']); ?>"
                           class="btn btn-sm <?php echo $vista === 'lista' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-list-ul me-1"></i>Lista
                        </a>
                        <a href="<?php echo bs_url(['vista' => 'tablero']); ?>"
                           class="btn btn-sm <?php echo $vista === 'tablero' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-kanban me-1"></i>Tablero
                        </a>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/maestro/exportar_bitacora_semanal.php<?php echo bs_url(['semana' => $lunes->format('Y-m-d'), 'vista' => '']); ?>"
                       class="btn btn-sm btn-outline-success" title="Descargar en Excel con los filtros actuales">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Imprimir
                    </button>
                </div>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <!-- Navegador de semana -->
            <div class="bs-semana d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?php echo bs_url(['semana' => $semana_anterior]); ?>" title="Semana anterior">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <div class="text-center px-1">
                        <div class="bs-semana-titulo"><?php echo htmlspecialchars($etiqueta_semana); ?></div>
                        <div class="bs-semana-sub">
                            <?php if ($es_semana_actual): ?>
                                <i class="bi bi-dot text-success"></i>Semana actual
                            <?php elseif ($es_semana_futura): ?>
                                Semana futura
                            <?php else: ?>
                                Semana pasada · las abiertas muestran su estado actual
                            <?php endif; ?>
                        </div>
                    </div>
                    <a class="btn btn-outline-secondary btn-sm" href="<?php echo bs_url(['semana' => $semana_siguiente]); ?>" title="Semana siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php if (!$es_semana_actual): ?>
                        <a class="btn btn-link btn-sm" href="<?php echo bs_url(['semana' => '']); ?>">Ir a esta semana</a>
                    <?php endif; ?>
                </div>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <?php foreach ($_GET as $k => $v): if ($k === 'semana' || !is_string($v)) continue; ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
                    <?php endforeach; ?>
                    <label class="small text-muted mb-0" for="irSemana">Ir a la semana del</label>
                    <input type="date" id="irSemana" name="semana" class="form-control form-control-sm" style="width:auto;"
                           value="<?php echo $lunes->format('Y-m-d'); ?>" onchange="this.form.submit()">
                </form>
            </div>

            <!-- KPIs -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-pend"><div class="num text-warning"><?php echo $kpi['pendientes']; ?></div><div class="lbl">Pendientes</div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-proc"><div class="num text-primary"><?php echo $kpi['en_proceso']; ?></div><div class="lbl">En proceso</div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-new"><div class="num" style="color:#6f42c1;"><?php echo $kpi['nuevas']; ?></div><div class="lbl">Nuevas en la semana</div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-fin"><div class="num text-success"><?php echo $kpi['finalizadas']; ?></div><div class="lbl">Finalizadas en la semana</div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-urg"><div class="num text-danger"><?php echo $kpi['urgentes']; ?></div><div class="lbl">Abiertas críticas / altas</div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="bs-kpi k-old">
                        <div class="num text-secondary"><?php echo $kpi['mas_antigua']; ?> <small class="fs-6 fw-normal">días</small></div>
                        <div class="lbl">Abierta más antigua</div>
                    </div>
                </div>
            </div>

            <!-- Filtro especial de Estado -->
            <div class="bs-chips d-flex flex-wrap gap-2 mb-2 no-print">
                <?php foreach ($estados_filtro as $clave => $texto): ?>
                    <a href="<?php echo bs_url(['estado' => $clave === 'activas' ? '' : $clave]); ?>"
                       class="btn btn-sm <?php echo $filtro_estado === $clave ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                        <?php echo htmlspecialchars($texto); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="card mb-3 no-print">
                <div class="card-body py-2">
                    <form method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="semana" value="<?php echo $lunes->format('Y-m-d'); ?>">
                        <?php if ($filtro_estado !== 'activas'): ?>
                            <input type="hidden" name="estado" value="<?php echo $filtro_estado; ?>">
                        <?php endif; ?>
                        <?php if ($vista === 'tablero'): ?>
                            <input type="hidden" name="vista" value="tablero">
                        <?php endif; ?>

                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1">Prioridad</label>
                            <select name="prioridad" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($prioridades as $k => $n): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $filtro_prioridad === $k ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="Apoyo" <?php echo $filtro_tipo === 'Apoyo' ? 'selected' : ''; ?>>Apoyo</option>
                                <option value="Problema" <?php echo $filtro_tipo === 'Problema' ? 'selected' : ''; ?>>Problema</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1">Departamento</label>
                            <select name="departamento" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?php echo (int) $d['id']; ?>" <?php echo $filtro_depto === (int) $d['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1">Atendido por</label>
                            <select name="tecnico" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="sin" <?php echo $filtro_tecnico === 'sin' ? 'selected' : ''; ?>>Sin asignar</option>
                                <?php foreach ($tecnicos as $t): ?>
                                    <option value="<?php echo (int) $t['id']; ?>" <?php echo $filtro_tecnico === (string) $t['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-8 col-md-3">
                            <label class="form-label small mb-1">Buscar (folio, solicitante, descripción)</label>
                            <input type="text" name="buscar" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Escriba para buscar...">
                        </div>
                        <div class="col-4 col-md-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                        </div>
                        <?php if ($hay_filtros_extra): ?>
                            <div class="col-12">
                                <a href="?semana=<?php echo $lunes->format('Y-m-d'); ?><?php echo $filtro_estado !== 'activas' ? '&estado=' . $filtro_estado : ''; ?><?php echo $vista === 'tablero' ? '&vista=tablero' : ''; ?>"
                                   class="btn btn-link btn-sm p-0"><i class="bi bi-x-circle me-1"></i>Limpiar filtros</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($recortado): ?>
                <div class="alert alert-warning py-2 small">
                    Se muestran las primeras <?php echo $LIMITE; ?> actividades. Usa los filtros para acotar la lista.
                </div>
            <?php endif; ?>

            <?php if ($vista === 'lista'): ?>
            <!-- =========================== VISTA LISTA =========================== -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-list-task me-2"></i><?php echo htmlspecialchars($estados_filtro[$filtro_estado]); ?>
                        <span class="badge bg-secondary ms-1"><?php echo count($actividades); ?></span>
                    </h6>
                    <small class="text-muted no-print">Orden: En proceso → Pendiente · prioridad · más antiguas primero</small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($actividades)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-emoji-smile" style="font-size:2.5rem;"></i>
                            <p class="mt-2 mb-0">No hay actividades con estos criterios en la semana seleccionada.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover bs-tabla mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Solicitante / Depto.</th>
                                        <th>Actividad</th>
                                        <th class="text-center">Prioridad</th>
                                        <th class="text-center">Estado</th>
                                        <th>Atendido por</th>
                                        <th>Creada</th>
                                        <th class="text-center">Antigüedad</th>
                                        <th class="text-center no-print">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($actividades as $a):
                                    $dias = bs_dias_antiguedad($a, $fin_semana);
                                    $es_nueva = ($a['fecha_creacion'] >= $ini_sql && $a['fecha_creacion'] < $fin_sql);
                                    [$cls_estado, $txt_estado] = $badges_estado[$a['estado']] ?? ['bg-secondary', $a['estado']];
                                    $detalle = bs_detalle_tipo($a);
                                    $fila_cls = $a['estado'] === 'en_proceso' ? 'bs-fila-proceso'
                                              : ($a['prioridad'] === 'critica' && $a['estado'] === 'pendiente' ? 'bs-fila-critica' : '');
                                ?>
                                    <tr class="<?php echo $fila_cls; ?>">
                                        <td>
                                            <a class="folio" href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($a['folio']); ?>">
                                                <?php echo htmlspecialchars($a['folio']); ?>
                                            </a>
                                            <?php if ($es_nueva): ?>
                                                <br><span class="badge badge-nueva mt-1">Nueva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="fw-semibold d-block"><?php echo htmlspecialchars($a['solicitante'] ?? '—'); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($a['depto_nombre'] ?? '—'); ?></small>
                                        </td>
                                        <td class="bs-desc">
                                            <small class="fw-semibold">
                                                <i class="bi <?php echo $a['tipo_soporte'] === 'Apoyo' ? 'bi-hand-thumbs-up' : 'bi-bug'; ?> me-1 text-muted"></i>
                                                <?php echo htmlspecialchars($a['tipo_soporte'] . ($detalle !== '' ? ' · ' . $detalle : '')); ?>
                                            </small>
                                            <small class="text-muted" title="<?php echo htmlspecialchars($a['descripcion']); ?>">
                                                <?php echo htmlspecialchars($a['descripcion']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $badges_prioridad[$a['prioridad']] ?? 'bg-secondary'; ?>">
                                                <?php echo $prioridades[$a['prioridad']] ?? ucfirst($a['prioridad']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $cls_estado; ?>"><?php echo $txt_estado; ?></span>
                                            <?php if ($a['estado'] === 'en_proceso' && !empty($a['fecha_en_proceso'])): ?>
                                                <br><small class="text-muted">desde <?php echo date('d/m H:i', strtotime($a['fecha_en_proceso'])); ?></small>
                                            <?php elseif ($a['estado'] === 'finalizada' && !empty($a['fecha_atencion'])): ?>
                                                <br><small class="text-muted"><?php echo date('d/m H:i', strtotime($a['fecha_atencion'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['tecnico_nombre'])): ?>
                                                <small><?php echo htmlspecialchars($a['tecnico_nombre']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted fst-italic">Sin asignar</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($a['fecha_creacion'])); ?></small></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo bs_clase_antiguedad($dias); ?>"><?php echo $dias; ?> d</span>
                                        </td>
                                        <td class="text-center no-print text-nowrap">
                                            <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($a['folio']); ?>"
                                               class="btn btn-sm btn-outline-primary" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                            <?php if (in_array($a['estado'], ['pendiente', 'en_proceso'], true)): ?>
                                                <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($a['folio']); ?>"
                                                   class="btn btn-sm btn-outline-success" title="Atender / cambiar estado"><i class="bi bi-arrow-repeat"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- =========================== VISTA TABLERO =========================== -->
            <?php
            $titulos_col = [
                'pendiente'  => ['Pendientes', 'bi-hourglass-split text-warning'],
                'en_proceso' => ['En proceso', 'bi-gear-wide-connected text-primary'],
                'finalizada' => ['Finalizadas en la semana', 'bi-check2-circle text-success'],
            ];
            $cols_visibles = $mostrar_finalizadas ? array_keys($titulos_col) : ['pendiente', 'en_proceso'];
            if ($filtro_estado === 'pendiente')   $cols_visibles = ['pendiente'];
            if ($filtro_estado === 'en_proceso')  $cols_visibles = ['en_proceso'];
            if ($filtro_estado === 'finalizadas') $cols_visibles = ['finalizada'];
            $ancho = 12 / max(1, count($cols_visibles));
            ?>
            <div class="row g-3">
                <?php foreach ($cols_visibles as $col): [$tit, $ico] = $titulos_col[$col]; ?>
                    <div class="col-md-<?php echo (int) $ancho; ?>">
                        <div class="bs-col">
                            <div class="bs-col-head d-flex justify-content-between">
                                <span><i class="bi <?php echo $ico; ?> me-1"></i><?php echo $tit; ?></span>
                                <span class="badge bg-secondary"><?php echo count($columnas[$col]); ?></span>
                            </div>
                            <?php if (empty($columnas[$col])): ?>
                                <div class="text-center text-muted small py-4">Sin actividades</div>
                            <?php endif; ?>
                            <?php foreach ($columnas[$col] as $a):
                                $dias = bs_dias_antiguedad($a, $fin_semana);
                                $es_nueva = ($a['fecha_creacion'] >= $ini_sql && $a['fecha_creacion'] < $fin_sql);
                                $detalle = bs_detalle_tipo($a);
                            ?>
                                <div class="bs-card p-<?php echo htmlspecialchars($a['prioridad']); ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <a class="folio" href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($a['folio']); ?>">
                                            <?php echo htmlspecialchars($a['folio']); ?>
                                        </a>
                                        <span class="badge <?php echo bs_clase_antiguedad($dias); ?>"><?php echo $dias; ?> d</span>
                                    </div>
                                    <div class="fw-semibold mt-1">
                                        <?php echo htmlspecialchars($a['tipo_soporte'] . ($detalle !== '' ? ' · ' . $detalle : '')); ?>
                                    </div>
                                    <div class="text-muted text-truncate" title="<?php echo htmlspecialchars($a['descripcion']); ?>">
                                        <?php echo htmlspecialchars($a['descripcion']); ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1 flex-wrap gap-1">
                                        <small><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($a['solicitante'] ?? '—'); ?>
                                            <span class="text-muted">· <?php echo htmlspecialchars($a['depto_nombre'] ?? '—'); ?></span></small>
                                        <span>
                                            <?php if ($es_nueva): ?><span class="badge badge-nueva">Nueva</span><?php endif; ?>
                                            <span class="badge <?php echo $badges_prioridad[$a['prioridad']] ?? 'bg-secondary'; ?>">
                                                <?php echo $prioridades[$a['prioridad']] ?? ucfirst($a['prioridad']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted">
                                            <i class="bi bi-headset me-1"></i><?php echo htmlspecialchars($a['tecnico_nombre'] ?? 'Sin asignar'); ?>
                                        </small>
                                        <?php if (in_array($a['estado'], ['pendiente', 'en_proceso'], true)): ?>
                                            <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($a['folio']); ?>"
                                               class="btn btn-sm btn-outline-success py-0 px-2 no-print" title="Atender / cambiar estado">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="small text-muted mt-3 mb-0 no-print">
                <i class="bi bi-info-circle me-1"></i>
                Las actividades abiertas incluyen las creadas en semanas anteriores que siguen sin cerrarse.
                La antigüedad se cuenta desde la creación de la solicitud.
            </p>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>