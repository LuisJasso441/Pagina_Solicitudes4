<?php
/**
 * Listado de Solicitudes de Mantenimiento (Nuevo Flujo)
 * dashboard/sistemas/ti_sistemas/mantenimientos.php
 *
 * - Sistemas ve todas las solicitudes
 * - Otros usuarios ven solo sus propias solicitudes (donde son destinatarios)
 *
 * v3.2 - Stat card "Esperando Firma" para Sistemas
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$pdo = conectarDB();

$departamento     = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti            = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

// ====================================================================
// FILTROS
// ====================================================================
$filtro_estado    = $_GET['estado']    ?? '';
$filtro_grupo     = $_GET['grupo']     ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$busqueda         = trim($_GET['buscar'] ?? '');
$pagina           = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina       = 10;

$estados_validos = ['pendiente', 'en_proceso', 'finalizada', 'cerrada', 'cancelada'];
$grupos_validos  = ['computo', 'perifericos', 'impresoras', 'red', 'otros'];
$prioridades_validas = ['alta', 'media', 'baja'];

$where  = [];
$params = [];

if (!$es_ti) {
    $where[] = "sm.usuario_id = ?";
    $params[] = $_SESSION['usuario_id'];
}

if ($filtro_estado && in_array($filtro_estado, $estados_validos)) {
    $where[] = "sm.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_grupo && in_array($filtro_grupo, $grupos_validos)) {
    $where[] = "sm.grupo_equipo = ?";
    $params[] = $filtro_grupo;
}

if ($filtro_prioridad && in_array($filtro_prioridad, $prioridades_validas)) {
    $where[] = "sm.prioridad = ?";
    $params[] = $filtro_prioridad;
}

if ($busqueda) {
    $where[] = "(sm.folio LIKE ? OR sm.descripcion_problema LIKE ? OR u.nombre_completo LIKE ?)";
    $b = "%$busqueda%";
    $params[] = $b;
    $params[] = $b;
    $params[] = $b;
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// ====================================================================
// CONTAR TOTAL
// ====================================================================
$sql_count = "SELECT COUNT(*) FROM solicitudes_mantenimiento_ti sm
              LEFT JOIN usuarios u ON sm.usuario_id = u.id
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_registros = intval($stmt->fetchColumn());
$total_paginas = max(1, ceil($total_registros / $por_pagina));

// ====================================================================
// OBTENER REGISTROS DE LA PAGINA
// ====================================================================
$offset = ($pagina - 1) * $por_pagina;
$sql = "SELECT sm.*,
               u.nombre_completo AS solicitante,
               d.nombre          AS departamento_nombre,
               ua.nombre_completo AS atendido_por_nombre,
               ie.hostname       AS equipo_hostname,
               ie.codigo_interno AS equipo_codigo
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN usuarios u      ON sm.usuario_id = u.id
        LEFT JOIN departamentos d ON sm.departamento_id = d.id
        LEFT JOIN usuarios ua     ON sm.atendido_por = ua.id
        LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
        $where_sql
        ORDER BY
            CASE sm.estado
                WHEN 'pendiente'  THEN 1
                WHEN 'en_proceso' THEN 2
                WHEN 'finalizada' THEN 3
                WHEN 'cerrada'    THEN 4
                WHEN 'cancelada'  THEN 5
                ELSE 6
            END,
            CASE sm.prioridad
                WHEN 'alta'  THEN 1
                WHEN 'media' THEN 2
                WHEN 'baja'  THEN 3
                ELSE 4
            END,
            sm.fecha_solicitud DESC
        LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====================================================================
// CONTADORES PARA STATS CARDS (sin filtros, solo el de usuario si aplica)
// ====================================================================
$sql_cont = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
    SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) AS finalizadas,
    SUM(CASE WHEN estado = 'cerrada'    THEN 1 ELSE 0 END) AS cerradas,
    SUM(CASE WHEN estado = 'cancelada'  THEN 1 ELSE 0 END) AS canceladas
FROM solicitudes_mantenimiento_ti sm";

if (!$es_ti) {
    $sql_cont .= " WHERE sm.usuario_id = ?";
    $stmt_c = $pdo->prepare($sql_cont);
    $stmt_c->execute([$_SESSION['usuario_id']]);
} else {
    $stmt_c = $pdo->query($sql_cont);
}
$contadores = $stmt_c->fetch(PDO::FETCH_ASSOC);
foreach (['total','pendientes','en_proceso','finalizadas','cerradas','canceladas'] as $k) {
    $contadores[$k] = intval($contadores[$k] ?? 0);
}

// ====================================================================
// SOLICITUDES CON FIRMA "ATASCADA" (Sistemas: > 48h en finalizada)
// Para mostrar alerta visual en la stat card
// ====================================================================
$finalizadas_atascadas = 0;
if ($es_ti && $contadores['finalizadas'] > 0) {
    $sql_atascadas = "SELECT COUNT(*) FROM solicitudes_mantenimiento_ti
                      WHERE estado = 'finalizada'
                        AND fecha_finalizacion IS NOT NULL
                        AND TIMESTAMPDIFF(HOUR, fecha_finalizacion, NOW()) >= 48";
    $finalizadas_atascadas = intval($pdo->query($sql_atascadas)->fetchColumn());
}

// ====================================================================
// CONFIGURACIONES DE VISUALIZACION
// ====================================================================
$badges_estado = [
    'pendiente'  => 'bg-warning text-dark',
    'en_proceso' => 'bg-primary',
    'finalizada' => 'bg-success',
    'cerrada'    => 'bg-secondary',
    'cancelada'  => 'bg-danger',
];
$nombres_estado = [
    'pendiente'  => 'Pendiente',
    'en_proceso' => 'En Proceso',
    'finalizada' => 'Finalizada',
    'cerrada'    => 'Cerrada',
    'cancelada'  => 'Cancelada',
];
$badges_prioridad = [
    'alta'  => 'bg-danger',
    'media' => 'bg-warning text-dark',
    'baja'  => 'bg-success',
];
$nombres_prioridad = [
    'alta'  => 'Alta',
    'media' => 'Media',
    'baja'  => 'Baja',
];
$grupos_nombres = [
    'computo'     => 'Cómputo',
    'perifericos' => 'Periféricos',
    'impresoras'  => 'Impresoras',
    'red'         => 'Red',
    'otros'       => 'Otros',
];
$grupos_iconos = [
    'computo'     => 'bi-pc-display',
    'perifericos' => 'bi-mouse',
    'impresoras'  => 'bi-printer',
    'red'         => 'bi-wifi',
    'otros'       => 'bi-three-dots',
];

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $es_ti ? 'Gestión de Mantenimientos' : 'Mis Mantenimientos'; ?> - TI / Sistemas</title>
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
        .stat-card {
            cursor: pointer;
            transition: all 0.15s;
            border: 1px solid #dee2e6;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .stat-card.active {
            box-shadow: 0 0 0 3px rgba(13,110,253,0.25);
            border-color: #0d6efd;
        }
        .stat-card .stat-num {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .stat-card .stat-sublabel {
            font-size: 0.7rem;
            color: #92400e;
            display: block;
            margin-top: 0.15rem;
            font-weight: 500;
        }
        .stat-card.b-warning   { border-top: 3px solid #ffc107; }
        .stat-card.b-primary   { border-top: 3px solid #0d6efd; }
        .stat-card.b-success   { border-top: 3px solid #198754; }
        .stat-card.b-secondary { border-top: 3px solid #6c757d; }
        .stat-card.b-danger    { border-top: 3px solid #dc3545; }
        .stat-card.b-dark      { border-top: 3px solid #212529; }
        .stat-card.b-firma     { border-top: 3px solid #f59e0b; }

        /* Stat card "Esperando Firma" - alerta visual cuando hay pendientes */
        .stat-card.firma-alert {
            background: linear-gradient(135deg, #fff8e1 0%, #fffbeb 100%);
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px #f59e0b40;
        }
        .stat-card.firma-alert .stat-num { color: #b45309 !important; }
        .stat-card.firma-alert .pulse-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #f59e0b;
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0%   { box-shadow: 0 0 0 0 rgba(245,158,11,0.6); }
            70%  { box-shadow: 0 0 0 8px rgba(245,158,11,0); }
            100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        }
        .stat-card.firma-alert .stat-icon-firma {
            color: #f59e0b;
        }

        .table-mant td, .table-mant th { vertical-align: middle; font-size: 0.85rem; }
        .table-mant .folio { font-weight: 600; color: #0d6efd; font-family: 'Courier New', monospace; }
        .row-highlight { background-color: #fff8e1; }
        .row-finalizada-mia { background-color: #fff3cd; }

        /* Resaltado para Sistemas viendo solicitudes en finalizada (esperando firma) */
        .row-esperando-firma {
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
        }
        .row-esperando-firma td:first-child {
            position: relative;
        }
        .badge-aging {
            font-size: 0.65rem;
            background: #f59e0b;
            color: #fff;
            padding: 0.15rem 0.4rem;
            border-radius: 10rem;
            margin-left: 0.25rem;
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php
    if ($es_mantenimiento) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
    } elseif ($es_ti) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
    } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
    } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
    } else {
        include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
    }
    ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-tools me-2"></i>
                        <?php echo $es_ti ? 'Gestión de Mantenimientos' : 'Mis Mantenimientos'; ?>
                    </h4>
                    <small class="text-muted">
                        <?php echo $es_ti ? 'Todas las solicitudes del sistema' : 'Solicitudes asignadas a tus equipos'; ?>
                    </small>
                </div>
                <?php if ($es_ti): ?>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php"
                       class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Nueva Solicitud
                    </a>
                <?php endif; ?>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <!-- ============================================================
                 STATS CARDS
                 - Para Sistemas: Total / Pendientes / En Proceso / Esperando Firma / Cerradas / Canceladas
                 - Para Usuarios: Total / Pendientes / En Proceso / Finalizadas / Cerradas / Canceladas
                 ============================================================ -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['estado'=>1,'pagina'=>1])); ?>"
                       class="text-decoration-none">
                        <div class="card stat-card b-dark <?php echo empty($filtro_estado) ? 'active' : ''; ?>">
                            <div class="card-body text-center py-2">
                                <div class="stat-num text-dark"><?php echo $contadores['total']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, ['pagina'=>1]), ['estado' => 'pendiente'])); ?>"
                       class="text-decoration-none">
                        <div class="card stat-card b-warning <?php echo $filtro_estado === 'pendiente' ? 'active' : ''; ?>">
                            <div class="card-body text-center py-2">
                                <div class="stat-num text-warning"><?php echo $contadores['pendientes']; ?></div>
                                <div class="stat-label">Pendientes</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, ['pagina'=>1]), ['estado' => 'en_proceso'])); ?>"
                       class="text-decoration-none">
                        <div class="card stat-card b-primary <?php echo $filtro_estado === 'en_proceso' ? 'active' : ''; ?>">
                            <div class="card-body text-center py-2">
                                <div class="stat-num text-primary"><?php echo $contadores['en_proceso']; ?></div>
                                <div class="stat-label">En Proceso</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Card 4: Para Sistemas → "Esperando Firma" (alerta), Para Usuarios → "Finalizadas" (necesitan firma) -->
                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, ['pagina'=>1]), ['estado' => 'finalizada'])); ?>"
                       class="text-decoration-none">
                        <?php if ($es_ti): ?>
                            <div class="card stat-card b-firma <?php echo $contadores['finalizadas'] > 0 ? 'firma-alert' : ''; ?> <?php echo $filtro_estado === 'finalizada' ? 'active' : ''; ?>">
                                <?php if ($contadores['finalizadas'] > 0): ?>
                                    <span class="pulse-dot"></span>
                                <?php endif; ?>
                                <div class="card-body text-center py-2">
                                    <div class="stat-num">
                                        <i class="bi bi-pen stat-icon-firma me-1" style="font-size: 0.9rem;"></i><?php echo $contadores['finalizadas']; ?>
                                    </div>
                                    <div class="stat-label">Esperando Firma</div>
                                    <?php if ($finalizadas_atascadas > 0): ?>
                                        <span class="stat-sublabel">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <?php echo $finalizadas_atascadas; ?> con +48h sin firmar
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card stat-card b-success <?php echo $filtro_estado === 'finalizada' ? 'active' : ''; ?>">
                                <div class="card-body text-center py-2">
                                    <div class="stat-num text-success"><?php echo $contadores['finalizadas']; ?></div>
                                    <div class="stat-label">Finalizadas</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, ['pagina'=>1]), ['estado' => 'cerrada'])); ?>"
                       class="text-decoration-none">
                        <div class="card stat-card b-secondary <?php echo $filtro_estado === 'cerrada' ? 'active' : ''; ?>">
                            <div class="card-body text-center py-2">
                                <div class="stat-num text-secondary"><?php echo $contadores['cerradas']; ?></div>
                                <div class="stat-label">Cerradas</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, ['pagina'=>1]), ['estado' => 'cancelada'])); ?>"
                       class="text-decoration-none">
                        <div class="card stat-card b-danger <?php echo $filtro_estado === 'cancelada' ? 'active' : ''; ?>">
                            <div class="card-body text-center py-2">
                                <div class="stat-num text-danger"><?php echo $contadores['canceladas']; ?></div>
                                <div class="stat-label">Canceladas</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-3">
                <div class="card-body py-2">
                    <form method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="estado" value="<?php echo htmlspecialchars($filtro_estado); ?>">

                        <div class="col-md-3">
                            <label class="form-label small mb-1">Tipo de Equipo</label>
                            <select name="grupo" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($grupos_nombres as $key => $nombre): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $filtro_grupo === $key ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Prioridad</label>
                            <select name="prioridad" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($nombres_prioridad as $key => $nombre): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $filtro_prioridad === $key ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small mb-1">Buscar (folio, descripción, usuario)</label>
                            <input type="text" name="buscar" class="form-control form-control-sm"
                                   placeholder="Escriba para buscar..."
                                   value="<?php echo htmlspecialchars($busqueda); ?>">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                        </div>

                        <?php if ($filtro_grupo || $filtro_prioridad || $busqueda || $filtro_estado): ?>
                            <div class="col-12">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php"
                                   class="btn btn-link btn-sm p-0">
                                    <i class="bi bi-x-circle me-1"></i>Limpiar todos los filtros
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Solicitudes
                        <span class="badge bg-secondary ms-1"><?php echo $total_registros; ?></span>
                    </h6>
                    <small class="text-muted">
                        Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
                    </small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3 mb-0">
                                <?php if ($filtro_estado || $filtro_grupo || $filtro_prioridad || $busqueda): ?>
                                    No hay solicitudes con los filtros aplicados.
                                <?php else: ?>
                                    Aún no hay solicitudes de mantenimiento.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-mant mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Tipo</th>
                                        <?php if ($es_ti): ?>
                                            <th>Usuario</th>
                                            <th>Departamento</th>
                                        <?php endif; ?>
                                        <th>Equipo</th>
                                        <th class="text-center">Prioridad</th>
                                        <th class="text-center">Estado</th>
                                        <th>Programada</th>
                                        <th>Creada</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $s):
                                        $grupo  = $s['grupo_equipo'] ?? '';
                                        $estado = $s['estado'] ?? 'pendiente';
                                        $prio   = $s['prioridad'] ?? 'media';

                                        // Resaltado contextual de filas
                                        $row_class = '';
                                        if (!$es_ti && $estado === 'finalizada') {
                                            // Usuario destinatario: necesita firmar
                                            $row_class = 'row-finalizada-mia';
                                        } elseif ($es_ti && $estado === 'finalizada') {
                                            // Sistemas: esperando firma del usuario
                                            $row_class = 'row-esperando-firma';
                                        } elseif ($es_ti && $estado === 'pendiente' && $prio === 'alta') {
                                            $row_class = 'row-highlight';
                                        }

                                        // Para Sistemas: calcular tiempo desde finalizacion (aging)
                                        $horas_sin_firmar = null;
                                        if ($es_ti && $estado === 'finalizada' && !empty($s['fecha_finalizacion'])) {
                                            $finalizada_dt = new DateTime($s['fecha_finalizacion']);
                                            $ahora_dt      = new DateTime();
                                            $horas_sin_firmar = floor(($ahora_dt->getTimestamp() - $finalizada_dt->getTimestamp()) / 3600);
                                        }
                                    ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td>
                                                <span class="folio"><?php echo htmlspecialchars($s['folio']); ?></span>
                                            </td>
                                            <td>
                                                <i class="bi <?php echo $grupos_iconos[$grupo] ?? 'bi-question-circle'; ?> me-1 text-muted"></i>
                                                <small><?php echo $grupos_nombres[$grupo] ?? '—'; ?></small>
                                            </td>
                                            <?php if ($es_ti): ?>
                                                <td>
                                                    <small><?php echo htmlspecialchars($s['solicitante'] ?? '—'); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($s['departamento_nombre'] ?? '—'); ?></small>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <small>
                                                    <?php
                                                    $eq = $s['equipo_hostname'] ?: $s['equipo_codigo'];
                                                    echo $eq ? htmlspecialchars($eq) : '<span class="text-muted">—</span>';
                                                    ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $badges_prioridad[$prio] ?? 'bg-secondary'; ?>">
                                                    <?php echo $nombres_prioridad[$prio] ?? ucfirst($prio); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $badges_estado[$estado] ?? 'bg-secondary'; ?>">
                                                    <?php echo $nombres_estado[$estado] ?? ucfirst($estado); ?>
                                                </span>
                                                <?php if (!$es_ti && $estado === 'finalizada'): ?>
                                                    <br><small class="text-warning"><i class="bi bi-pen me-1"></i>Falta tu firma</small>
                                                <?php elseif ($es_ti && $estado === 'finalizada'): ?>
                                                    <br><small class="text-warning">
                                                        <i class="bi bi-pen me-1"></i>Esperando firma
                                                        <?php if ($horas_sin_firmar !== null): ?>
                                                            <?php if ($horas_sin_firmar < 24): ?>
                                                                <span class="badge-aging" style="background:#94a3b8;"><?php echo $horas_sin_firmar; ?>h</span>
                                                            <?php elseif ($horas_sin_firmar < 48): ?>
                                                                <span class="badge-aging" style="background:#f59e0b;"><?php echo floor($horas_sin_firmar / 24); ?>d</span>
                                                            <?php else: ?>
                                                                <span class="badge-aging" style="background:#dc2626;" title="<?php echo $horas_sin_firmar; ?> horas sin firmar"><?php echo floor($horas_sin_firmar / 24); ?>d</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php
                                                    if ($s['fecha_deseada']) {
                                                        echo date('d/m/Y', strtotime($s['fecha_deseada']));
                                                        if ($s['hora_deseada']) {
                                                            echo ' ' . date('H:i', strtotime($s['hora_deseada']));
                                                        }
                                                    } else {
                                                        echo '<span class="text-muted">—</span>';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_mantenimiento.php?id=<?php echo $s['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginacion -->
                <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php
                                $base_query = array_diff_key($_GET, ['pagina' => 1]);
                                $build_url = function($p) use ($base_query) {
                                    $q = array_merge($base_query, ['pagina' => $p]);
                                    return '?' . http_build_query($q);
                                };
                                ?>

                                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $build_url(max(1, $pagina - 1)); ?>">Anterior</a>
                                </li>

                                <?php
                                $rango_inicio = max(1, $pagina - 2);
                                $rango_fin = min($total_paginas, $pagina + 2);
                                for ($p = $rango_inicio; $p <= $rango_fin; $p++):
                                ?>
                                    <li class="page-item <?php echo $p == $pagina ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo $build_url($p); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $build_url(min($total_paginas, $pagina + 1)); ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

</body>
</html>