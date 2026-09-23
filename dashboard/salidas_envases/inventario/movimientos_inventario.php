<?php
/**
 * Historial de Movimientos de Inventario — v2 (estética temática SEC)
 * dashboard/salidas_envases/inventario/movimientos_inventario.php
 *
 * Solo lectura para todos los departamentos autorizados.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/inventario_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'almacen_residuos', 'ventas'], true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

// ---- Filtros ----
$f_espec  = (int) ($_GET['especificacion_id'] ?? 0);
$f_tipo   = trim($_GET['tipo_movimiento'] ?? '');
$f_desde  = trim($_GET['fecha_desde'] ?? '');
$f_hasta  = trim($_GET['fecha_hasta'] ?? '');
$pagina   = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pag  = 30;

$filtros_activos = [];
if ($f_espec > 0)     $filtros_activos['especificacion_id'] = $f_espec;
if ($f_tipo !== '')   $filtros_activos['tipo_movimiento']   = $f_tipo;
if ($f_desde !== '')  $filtros_activos['fecha_desde']       = $f_desde;
if ($f_hasta !== '')  $filtros_activos['fecha_hasta']       = $f_hasta;

$total       = contar_movimientos($filtros_activos);
$total_pags  = max(1, (int) ceil($total / $por_pag));
$pagina      = min($pagina, $total_pags);
$offset      = ($pagina - 1) * $por_pag;
$movimientos = obtener_movimientos($filtros_activos, $por_pag, $offset);

$todas_specs = obtener_especificaciones(null, true);

// Mapeo de tipos con clase temática SEC
$tipos_movimiento_labels = [
    'entrada'    => ['label' => 'Entrada',    'clase' => 'verde',  'icono' => 'arrow-down-circle', 'signo' => '+'],
    'salida'     => ['label' => 'Salida',     'clase' => 'rojo',   'icono' => 'arrow-up-circle',   'signo' => '−'],
    'ajuste'     => ['label' => 'Ajuste',     'clase' => 'ambar',  'icono' => 'sliders',           'signo' => '±'],
    'devolucion' => ['label' => 'Devolución', 'clase' => 'azul',   'icono' => 'arrow-return-left', 'signo' => '+'],
    'inicial'    => ['label' => 'Inicial',    'clase' => 'tierra', 'icono' => 'flag',              'signo' => '+'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Inventario | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">

    <style>
        .sec-page {
            --sec-verde:       #2f7d5b;
            --sec-verde-osc:   #1e5a3f;
            --sec-verde-soft:  rgba(47, 125, 91, 0.10);
            --sec-ambar:       #d97706;
            --sec-ambar-soft:  rgba(217, 119, 6, 0.12);
            --sec-azul:        #1d4ed8;
            --sec-azul-soft:   rgba(29, 78, 216, 0.12);
            --sec-rojo:        #b91c1c;
            --sec-rojo-soft:   rgba(185, 28, 28, 0.12);
            --sec-tierra:      #92603a;
            --sec-tierra-soft: rgba(146, 96, 58, 0.12);

            --sec-bg-base:     #f9f6ee;
            --sec-card:        #ffffff;
            --sec-text:        #1c2b21;
            --sec-text-muted:  #6b7268;
            --sec-border:      #e3ddce;
            --sec-border-soft: #efe9dc;
            --sec-shadow-sm:   0 2px 6px rgba(30, 90, 63, 0.05);
            --sec-shadow-md:   0 8px 20px -8px rgba(30, 90, 63, 0.14);
            --sec-hover-bg:    #f9f6ee;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sec-text);
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        /* Header */
        .sec-page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--sec-verde);
        }
        .sec-page-head__title { display: flex; align-items: center; gap: 0.85rem; }
        .sec-page-head__stamp {
            width: 48px; height: 48px;
            border-radius: 10px;
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 1.5px solid var(--sec-verde);
        }
        .sec-page-head h1 { margin: 0; font-size: 1.35rem; font-weight: 700; color: var(--sec-text); }
        .sec-page-head__subtitle { margin: 0; font-size: 0.82rem; color: var(--sec-text-muted); }
        .sec-actions-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        /* Botones */
        .sec-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.95rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--sec-border);
            background: var(--sec-card);
            color: var(--sec-text);
            box-shadow: var(--sec-shadow-sm);
            cursor: pointer;
            transition: all .18s;
        }
        .sec-btn:hover { transform: translateY(-1px); box-shadow: var(--sec-shadow-md); color: var(--sec-text); }
        .sec-btn--primary {
            background: var(--sec-verde);
            border-color: var(--sec-verde);
            border-left: 4px solid var(--sec-verde-osc);
            color: #fff;
        }
        .sec-btn--primary:hover { background: var(--sec-verde-osc); color: #fff; }
        .sec-btn--sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; }
        .sec-btn--icon-only { padding: 0.4rem 0.6rem; }
        .sec-btn i { font-size: 1rem; }

        /* Form controls */
        .sec-form-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sec-text-muted);
            margin-bottom: 0.35rem;
            display: block;
        }
        .sec-input, .sec-select {
            width: 100%;
            padding: 0.5rem 0.8rem;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--sec-text);
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 6px;
            transition: border-color .15s, box-shadow .15s;
        }
        .sec-input:focus, .sec-select:focus {
            outline: none;
            border-color: var(--sec-verde);
            box-shadow: 0 0 0 3px var(--sec-verde-soft);
        }

        /* Panel */
        .sec-panel {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            box-shadow: var(--sec-shadow-sm);
            overflow: hidden;
        }
        .sec-panel__head {
            padding: 0.85rem 1.2rem;
            border-bottom: 1px solid var(--sec-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .sec-panel__title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--sec-text);
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }
        .sec-panel__title i { color: var(--sec-verde); }
        .sec-panel__body { padding: 1rem 1.2rem; }
        .sec-panel__body--compact { padding: 0; }

        .sec-total-info {
            font-size: 0.82rem;
            color: var(--sec-text-muted);
            margin: 0;
        }
        .sec-total-info strong {
            color: var(--sec-text);
            font-variant-numeric: tabular-nums;
        }

        /* Alert info */
        .sec-alert-info {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.85rem 1.1rem;
            background: var(--sec-azul-soft);
            border: 1px solid var(--sec-azul);
            border-left: 4px solid var(--sec-azul);
            border-radius: 8px;
            color: var(--sec-text);
            font-size: 0.9rem;
        }
        .sec-alert-info i { color: var(--sec-azul); font-size: 1.15rem; flex-shrink: 0; }

        /* Tabla */
        .sec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .sec-table th, .sec-table td {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--sec-border-soft);
            text-align: left;
            vertical-align: middle;
        }
        .sec-table thead th {
            background: var(--sec-bg-base);
            color: var(--sec-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.06em;
            white-space: nowrap;
        }
        .sec-table tbody tr:hover td { background: var(--sec-hover-bg); }
        .sec-table tbody tr:last-child td { border-bottom: none; }
        .sec-cell-fecha {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .sec-cell-fecha strong {
            display: block;
            color: var(--sec-text);
            font-weight: 600;
        }
        .sec-cell-fecha small {
            color: var(--sec-text-muted);
            font-size: 0.75rem;
        }
        .sec-tipo-cell {
            font-weight: 600;
            color: var(--sec-text);
        }
        .sec-spec-cell {
            display: block;
            color: var(--sec-text-muted);
            font-size: 0.78rem;
            margin-top: 2px;
        }
        .sec-num-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .sec-num-cell--strong {
            font-weight: 700;
            color: var(--sec-text);
        }
        .sec-cell-meta {
            color: var(--sec-text-muted);
            font-size: 0.78rem;
        }
        .sec-cell-ref {
            display: block;
            color: var(--sec-text-muted);
            font-size: 0.72rem;
            margin-top: 3px;
        }
        .sec-cell-ref i { font-size: 0.85rem; }

        /* Badge de tipo movimiento */
        .sec-mov-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 3px 9px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .sec-mov-badge i { font-size: 0.9rem; }
        .sec-mov-badge--verde  { background: var(--sec-verde-soft);  color: var(--sec-verde);  border: 1px solid var(--sec-verde); }
        .sec-mov-badge--rojo   { background: var(--sec-rojo-soft);   color: var(--sec-rojo);   border: 1px solid var(--sec-rojo); }
        .sec-mov-badge--ambar  { background: var(--sec-ambar-soft);  color: var(--sec-ambar);  border: 1px solid var(--sec-ambar); }
        .sec-mov-badge--azul   { background: var(--sec-azul-soft);   color: var(--sec-azul);   border: 1px solid var(--sec-azul); }
        .sec-mov-badge--tierra { background: var(--sec-tierra-soft); color: var(--sec-tierra); border: 1px solid var(--sec-tierra); }

        /* Cantidad de movimiento con signo */
        .sec-mov-cant {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .sec-mov-cant--verde  { color: var(--sec-verde); }
        .sec-mov-cant--rojo   { color: var(--sec-rojo); }
        .sec-mov-cant--ambar  { color: var(--sec-ambar); }
        .sec-mov-cant--azul   { color: var(--sec-azul); }
        .sec-mov-cant--tierra { color: var(--sec-tierra); }

        /* Empty state */
        .sec-empty {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--sec-text-muted);
            font-size: 0.9rem;
        }
        .sec-empty i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.6rem;
            opacity: 0.4;
        }

        /* Paginación */
        .sec-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            list-style: none;
            padding: 0;
            margin: 1.5rem 0 0;
        }
        .sec-pagination li { display: inline-block; }
        .sec-page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.9rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid var(--sec-border);
            background: var(--sec-card);
            color: var(--sec-text);
            text-decoration: none;
            box-shadow: var(--sec-shadow-sm);
            transition: all .15s;
            min-width: 36px;
        }
        .sec-page-btn:hover {
            background: var(--sec-verde-soft);
            border-color: var(--sec-verde);
            color: var(--sec-verde);
        }
        .sec-page-btn--disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .sec-page-info {
            padding: 0.45rem 1rem;
            font-size: 0.82rem;
            color: var(--sec-text-muted);
            font-variant-numeric: tabular-nums;
        }
        .sec-page-info strong { color: var(--sec-text); font-weight: 700; }

        /* Responsive */
        @media (max-width: 992px) {
            .sec-table th, .sec-table td { padding: 0.55rem 0.75rem; }
        }
        @media (max-width: 640px) {
            .sec-page-head { padding-bottom: 0.85rem; }
            .sec-page-head h1 { font-size: 1.15rem; }
            .sec-page-head__stamp { width: 42px; height: 42px; font-size: 1.3rem; }
            .sec-panel__head { padding: 0.7rem 1rem; }
            .sec-panel__body { padding: 0.85rem 1rem; }
            .sec-table th, .sec-table td { padding: 0.5rem 0.7rem; font-size: 0.8rem; }
            .sec-mov-badge { font-size: 0.65rem; padding: 2px 6px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="sec-page">

                    <!-- Header -->
                    <div class="sec-page-head">
                        <div class="sec-page-head__title">
                            <div class="sec-page-head__stamp"><i class="bi bi-clock-history"></i></div>
                            <div>
                                <h1>Historial de Movimientos</h1>
                                <p class="sec-page-head__subtitle">Registro completo de cambios en el inventario.</p>
                            </div>
                        </div>
                        <div class="sec-actions-row">
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php" class="sec-btn">
                                <i class="bi bi-arrow-left"></i> Volver al inventario
                            </a>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-funnel"></i> Filtros</h3>
                        </div>
                        <div class="sec-panel__body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="sec-form-label">Especificación</label>
                                    <select class="sec-select" name="especificacion_id">
                                        <option value="0">Todas</option>
                                        <?php foreach ($todas_specs as $s): ?>
                                        <option value="<?php echo (int) $s['id']; ?>"
                                            <?php echo $f_espec === (int) $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['tipo_nombre'] . ' — ' . $s['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="sec-form-label">Tipo</label>
                                    <select class="sec-select" name="tipo_movimiento">
                                        <option value="">Todos</option>
                                        <?php foreach ($tipos_movimiento_labels as $key => $data): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $f_tipo === $key ? 'selected' : ''; ?>>
                                            <?php echo $data['label']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="sec-form-label">Desde</label>
                                    <input type="date" class="sec-input" name="fecha_desde"
                                           value="<?php echo htmlspecialchars($f_desde); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="sec-form-label">Hasta</label>
                                    <input type="date" class="sec-input" name="fecha_hasta"
                                           value="<?php echo htmlspecialchars($f_hasta); ?>">
                                </div>
                                <div class="col-md-2 d-flex gap-1">
                                    <button type="submit" class="sec-btn sec-btn--primary sec-btn--sm flex-grow-1">
                                        <i class="bi bi-funnel"></i> Filtrar
                                    </button>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php"
                                       class="sec-btn sec-btn--sm">
                                        Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-list-ul"></i> Movimientos</h3>
                            <p class="sec-total-info">
                                Mostrando <strong><?php echo count($movimientos); ?></strong> de <strong><?php echo number_format($total); ?></strong> movimientos
                            </p>
                        </div>
                        <div class="sec-panel__body sec-panel__body--compact">
                            <?php if (empty($movimientos)): ?>
                                <div class="sec-empty">
                                    <i class="bi bi-inbox"></i>
                                    No hay movimientos registrados con los filtros seleccionados.
                                </div>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <table class="sec-table">
                                        <thead>
                                            <tr>
                                                <th style="min-width:120px;">Fecha</th>
                                                <th style="min-width:120px;">Tipo</th>
                                                <th style="min-width:200px;">Especificación</th>
                                                <th style="text-align:right; min-width:90px;">Anterior</th>
                                                <th style="text-align:right; min-width:100px;">Movimiento</th>
                                                <th style="text-align:right; min-width:100px;">Resultante</th>
                                                <th style="min-width:180px;">Motivo</th>
                                                <th style="min-width:130px;">Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimientos as $m):
                                                $tipoData = $tipos_movimiento_labels[$m['tipo_movimiento']] ?? [
                                                    'label' => $m['tipo_movimiento'],
                                                    'clase' => 'tierra',
                                                    'icono' => 'question-circle',
                                                    'signo' => '',
                                                ];
                                                if ($m['tipo_movimiento'] === 'ajuste') {
                                                    $delta = (int) $m['cantidad_resultante'] - (int) $m['cantidad_anterior'];
                                                    $signo = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
                                                    $cant_display = $signo . number_format(abs($delta));
                                                } else {
                                                    $cant_display = $tipoData['signo'] . number_format((int) $m['cantidad_movimiento']);
                                                }
                                            ?>
                                            <tr>
                                                <td class="sec-cell-fecha">
                                                    <strong><?php echo date('d/m/Y', strtotime($m['creado_en'])); ?></strong>
                                                    <small><?php echo date('H:i', strtotime($m['creado_en'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="sec-mov-badge sec-mov-badge--<?php echo $tipoData['clase']; ?>">
                                                        <i class="bi bi-<?php echo $tipoData['icono']; ?>"></i>
                                                        <?php echo $tipoData['label']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="sec-tipo-cell"><?php echo htmlspecialchars($m['tipo_nombre']); ?></span>
                                                    <span class="sec-spec-cell"><?php echo htmlspecialchars($m['especificacion_nombre']); ?></span>
                                                </td>
                                                <td class="sec-num-cell">
                                                    <?php echo number_format((int) $m['cantidad_anterior']); ?>
                                                </td>
                                                <td class="sec-num-cell">
                                                    <span class="sec-mov-cant sec-mov-cant--<?php echo $tipoData['clase']; ?>">
                                                        <?php echo $cant_display; ?>
                                                    </span>
                                                </td>
                                                <td class="sec-num-cell sec-num-cell--strong">
                                                    <?php echo number_format((int) $m['cantidad_resultante']); ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($m['motivo'])): ?>
                                                        <span class="sec-cell-meta"><?php echo htmlspecialchars($m['motivo']); ?></span>
                                                    <?php else: ?>
                                                        <span class="sec-cell-meta">—</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($m['referencia_tipo'])): ?>
                                                        <span class="sec-cell-ref">
                                                            <i class="bi bi-link-45deg"></i>
                                                            <?php echo htmlspecialchars($m['referencia_tipo']); ?>
                                                            <?php echo $m['referencia_id'] ? '#' . (int) $m['referencia_id'] : ''; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="sec-cell-meta"><?php echo htmlspecialchars($m['usuario_nombre'] ?? '—'); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <?php if (!empty($movimientos) && $total_pags > 1):
                        $base_url = URL_BASE . 'dashboard/salidas_envases/inventario/movimientos_inventario.php?';
                        $qs = http_build_query(array_filter([
                            'especificacion_id' => $f_espec ?: null,
                            'tipo_movimiento'   => $f_tipo ?: null,
                            'fecha_desde'       => $f_desde ?: null,
                            'fecha_hasta'       => $f_hasta ?: null,
                        ]));
                        $sep = $qs ? '&' : '';
                    ?>
                    <nav>
                        <ul class="sec-pagination">
                            <li>
                                <a class="sec-page-btn <?php echo $pagina <= 1 ? 'sec-page-btn--disabled' : ''; ?>"
                                   href="<?php echo $base_url . $qs . $sep . 'pagina=' . max(1, $pagina - 1); ?>"
                                   aria-label="Anterior">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <li>
                                <span class="sec-page-info">
                                    Página <strong><?php echo $pagina; ?></strong> de <strong><?php echo $total_pags; ?></strong>
                                </span>
                            </li>
                            <li>
                                <a class="sec-page-btn <?php echo $pagina >= $total_pags ? 'sec-page-btn--disabled' : ''; ?>"
                                   href="<?php echo $base_url . $qs . $sep . 'pagina=' . min($total_pags, $pagina + 1); ?>"
                                   aria-label="Siguiente">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        // Página siempre en tema claro
        document.body.setAttribute('data-theme', 'light');
    </script>
</body>
</html>