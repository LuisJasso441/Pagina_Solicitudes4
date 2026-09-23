<?php
/**
 * Inventario de Envases — v2 (estética temática SEC)
 * dashboard/salidas_envases/inventario/inventario.php
 *
 * Permisos:
 *   - Almacén de Residuos: puede registrar movimientos.
 *   - Logística y Ventas: solo lectura.
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
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$puede_editar = puede_administrar_inventario();

$filtro_tipo_id  = (int) ($_GET['tipo_id'] ?? 0);
$filtro_busqueda = trim($_GET['busqueda'] ?? '');

$filtros_activos = [];
if ($filtro_tipo_id > 0)     $filtros_activos['tipo_id']  = $filtro_tipo_id;
if ($filtro_busqueda !== '') $filtros_activos['busqueda'] = $filtro_busqueda;

$tipos      = obtener_tipos_envase(false);
$inventario = obtener_inventario($filtros_activos);

$stock_map = [];
foreach ($inventario as $item) {
    $stock_map[(int) $item['especificacion_id']] = (int) $item['cantidad_actual'];
}

$total_specs         = count($inventario);
$total_envases       = array_sum(array_map(static fn($i) => (int) $i['cantidad_actual'], $inventario));
$specs_con_stock     = count(array_filter($inventario, static fn($i) => (int) $i['cantidad_actual'] > 0));
$specs_sin_registro  = count(array_filter($inventario, static fn($i) => (int) $i['tiene_registro'] === 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Envases | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
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
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>

    <style>
        /* Variables SEC en scope global — los modales de Bootstrap se
           mueven al final del <body>, así que necesitan estas vars ahí. */
        body {
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
        }

        .sec-page {
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
        .sec-btn i { font-size: 1rem; }

        /* Stats */
        .sec-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .sec-stat {
            --accent: var(--sec-verde);
            --accent-soft: var(--sec-verde-soft);
            position: relative;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            padding: 1rem 1.1rem 0.95rem;
            box-shadow: var(--sec-shadow-sm);
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .sec-stat::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--accent);
            border-radius: 10px 10px 0 0;
        }
        .sec-stat:hover { transform: translateY(-2px); box-shadow: var(--sec-shadow-md); border-color: var(--accent); }
        .sec-stat__row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.35rem;
        }
        .sec-stat__icon {
            width: 40px; height: 40px;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .sec-stat__label {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sec-text-muted);
            text-align: right;
        }
        .sec-stat__num {
            margin: 0.45rem 0 0;
            font-size: 1.85rem;
            font-weight: 700;
            line-height: 1;
            color: var(--sec-text);
            font-variant-numeric: tabular-nums;
        }
        .sec-stat--verde  { --accent: var(--sec-verde); --accent-soft: var(--sec-verde-soft); }
        .sec-stat--azul   { --accent: var(--sec-azul); --accent-soft: var(--sec-azul-soft); }
        .sec-stat--ambar  { --accent: var(--sec-ambar); --accent-soft: var(--sec-ambar-soft); }
        .sec-stat--tierra { --accent: var(--sec-tierra); --accent-soft: var(--sec-tierra-soft); }

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

        /* Form controls */
        .sec-form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-muted);
            margin-bottom: 0.35rem;
            display: block;
        }
        .sec-input, .sec-select, .sec-textarea {
            width: 100%;
            padding: 0.55rem 0.85rem;
            font-size: 0.9rem;
            font-family: inherit;
            color: var(--sec-text);
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 6px;
            transition: border-color .15s, box-shadow .15s;
        }
        .sec-input:focus, .sec-select:focus, .sec-textarea:focus {
            outline: none;
            border-color: var(--sec-verde);
            box-shadow: 0 0 0 3px var(--sec-verde-soft);
        }
        .sec-textarea { resize: vertical; }

        /* Tabla */
        .sec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.87rem;
        }
        .sec-table th, .sec-table td {
            padding: 0.7rem 1.2rem;
            border-bottom: 1px solid var(--sec-border-soft);
            text-align: left;
            vertical-align: middle;
        }
        .sec-table thead th {
            background: var(--sec-bg-base);
            color: var(--sec-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.06em;
        }
        .sec-table tbody tr:hover td { background: var(--sec-hover-bg); }
        .sec-table tbody tr:last-child td { border-bottom: none; }
        .sec-tipo-cell { font-weight: 600; color: var(--sec-text); }
        .sec-spec-cell { color: var(--sec-text); }
        .sec-stock {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .sec-stock--alto  { color: var(--sec-verde); }
        .sec-stock--medio { color: var(--sec-ambar); }
        .sec-stock--cero  { color: var(--sec-text-muted); font-weight: 500; }
        .sec-badge-sinreg {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--sec-border-soft);
            color: var(--sec-text-muted);
            border-radius: 4px;
            margin-left: 6px;
        }
        .sec-cell-meta { font-size: 0.78rem; color: var(--sec-text-muted); }
        .sec-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            border-radius: 6px;
            background: transparent;
            color: var(--sec-text-muted);
            border: 1px solid var(--sec-border);
            text-decoration: none;
            transition: all .15s;
        }
        .sec-action-btn:hover {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border-color: var(--sec-verde);
        }
        .sec-action-btn--primary {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border-color: var(--sec-verde);
        }
        .sec-action-btn--primary:hover { background: var(--sec-verde); color: #fff; }

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

        /* ========= Modales de Bootstrap con paleta SEC ========= */
        .modal-content {
            background: var(--sec-card);
            color: var(--sec-text);
            border: 1px solid var(--sec-border);
            border-radius: 12px;
            box-shadow: var(--sec-shadow-md);
        }
        .modal-header {
            border-bottom: 1px solid var(--sec-border);
            background: var(--sec-bg-base);
            padding: 0.9rem 1.2rem;
            border-radius: 12px 12px 0 0;
        }
        .modal-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--sec-text);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-title i { color: var(--sec-verde); font-size: 1.15rem; }
        .modal-body { padding: 1.15rem 1.2rem; }
        .modal-footer {
            border-top: 1px solid var(--sec-border);
            background: var(--sec-bg-base);
            padding: 0.75rem 1.2rem;
            border-radius: 0 0 12px 12px;
            gap: 0.5rem;
        }
        .modal-footer > * { margin: 0; }

        /* Preview de cálculo */
        .sec-preview {
            background: var(--sec-bg-base);
            border: 1px solid var(--sec-border);
            border-radius: 8px;
            padding: 0.85rem;
        }
        .sec-preview__row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            text-align: center;
        }
        .sec-preview__label {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sec-text-muted);
            margin-bottom: 3px;
        }
        .sec-preview__val {
            font-size: 1.1rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--sec-text);
        }

        /* Radio "tipo movimiento" */
        .sec-radio-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .sec-radio-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.85rem 0.5rem;
            border: 1.5px solid var(--sec-border);
            border-radius: 8px;
            cursor: pointer;
            background: var(--sec-card);
            color: var(--sec-text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            transition: all .15s;
            gap: 0.3rem;
        }
        .sec-radio-tile i { font-size: 1.3rem; }
        .sec-radio-tile input[type="radio"] { display: none; }
        .sec-radio-tile:hover { border-color: var(--sec-verde); }
        .sec-radio-tile.selected--verde  { background: var(--sec-verde-soft); color: var(--sec-verde); border-color: var(--sec-verde); }
        .sec-radio-tile.selected--ambar  { background: var(--sec-ambar-soft); color: var(--sec-ambar); border-color: var(--sec-ambar); }

        /* Responsive */
        @media (max-width: 992px) {
            .sec-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .sec-page-head { padding-bottom: 0.85rem; }
            .sec-page-head h1 { font-size: 1.15rem; }
            .sec-page-head__stamp { width: 42px; height: 42px; font-size: 1.3rem; }
            .sec-grid { gap: 0.65rem; }
            .sec-stat { padding: 0.85rem 0.9rem; }
            .sec-stat__num { font-size: 1.55rem; }
            .sec-stat__icon { width: 36px; height: 36px; font-size: 1.1rem; }
            .sec-panel__head { padding: 0.7rem 1rem; }
            .sec-panel__body { padding: 0.85rem 1rem; }
            .sec-table th, .sec-table td { padding: 0.55rem 0.85rem; font-size: 0.82rem; }
            .sec-radio-group { grid-template-columns: 1fr; }
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
                            <div class="sec-page-head__stamp"><i class="bi bi-boxes"></i></div>
                            <div>
                                <h1>Inventario de Envases</h1>
                                <p class="sec-page-head__subtitle">
                                    <?php echo $puede_editar ? 'Gestión de stock y movimientos.' : 'Consulta el inventario (solo lectura).'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="sec-actions-row">
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php" class="sec-btn">
                                <i class="bi bi-clock-history"></i> Historial
                            </a>
                            <?php if ($puede_editar): ?>
                            <button type="button" class="sec-btn sec-btn--primary" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                                <i class="bi bi-plus-circle"></i> Registrar movimiento
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="sec-grid">
                        <div class="sec-stat sec-stat--azul">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-tags"></i></div>
                                <p class="sec-stat__label">Especifica-<br>ciones</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo $total_specs; ?></h2>
                        </div>
                        <div class="sec-stat sec-stat--verde">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-boxes"></i></div>
                                <p class="sec-stat__label">Total<br>envases</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo number_format($total_envases); ?></h2>
                        </div>
                        <div class="sec-stat sec-stat--tierra">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-check-circle"></i></div>
                                <p class="sec-stat__label">Con<br>stock</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo $specs_con_stock; ?></h2>
                        </div>
                        <div class="sec-stat sec-stat--ambar">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-dash-circle"></i></div>
                                <p class="sec-stat__label">Sin<br>registro</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo $specs_sin_registro; ?></h2>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-funnel"></i> Filtros</h3>
                        </div>
                        <div class="sec-panel__body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="sec-form-label">Buscar</label>
                                    <input type="text" class="sec-input" name="busqueda"
                                           value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                                           placeholder="Buscar tipo o especificación…">
                                </div>
                                <div class="col-md-4">
                                    <label class="sec-form-label">Tipo de envase</label>
                                    <select class="sec-select" name="tipo_id">
                                        <option value="0">Todos los tipos</option>
                                        <?php foreach ($tipos as $t): ?>
                                        <option value="<?php echo (int) $t['id']; ?>"
                                            <?php echo $filtro_tipo_id === (int) $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="sec-btn sec-btn--primary sec-btn--sm flex-fill">
                                        <i class="bi bi-funnel"></i> Filtrar
                                    </button>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php" class="sec-btn sec-btn--sm">
                                        Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-list-ul"></i> Especificaciones registradas</h3>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem;">
                                <?php echo $total_specs; ?> resultado<?php echo $total_specs === 1 ? '' : 's'; ?>
                            </small>
                        </div>
                        <div class="sec-panel__body sec-panel__body--compact">
                            <?php if (empty($inventario)): ?>
                                <div class="sec-empty">
                                    <i class="bi bi-inbox"></i>
                                    No hay especificaciones registradas
                                    <?php echo !empty($filtros_activos) ? 'con los filtros seleccionados' : 'aún'; ?>.
                                    <?php if ($total_specs === 0 && empty($filtros_activos)): ?>
                                        <br><small>Solicita a Almacén que agregue tipos de envase.</small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <table class="sec-table">
                                        <thead>
                                            <tr>
                                                <th style="min-width:130px;">Tipo</th>
                                                <th style="min-width:200px;">Especificación</th>
                                                <th style="text-align:right; min-width:110px;">Stock actual</th>
                                                <th style="min-width:160px;">Actualización</th>
                                                <th style="min-width:150px;">Por</th>
                                                <th style="text-align:center; min-width:100px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventario as $item):
                                                $stock = (int) $item['cantidad_actual'];
                                                $tiene_reg = (int) $item['tiene_registro'] === 1;
                                                $cls = $stock === 0 ? 'sec-stock--cero' : ($stock < 10 ? 'sec-stock--medio' : 'sec-stock--alto');
                                            ?>
                                            <tr>
                                                <td class="sec-tipo-cell"><?php echo htmlspecialchars($item['tipo_nombre']); ?></td>
                                                <td class="sec-spec-cell">
                                                    <?php echo htmlspecialchars($item['especificacion_nombre']); ?>
                                                    <?php if (!$tiene_reg): ?>
                                                        <span class="sec-badge-sinreg">Sin registro</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:right;">
                                                    <span class="sec-stock <?php echo $cls; ?>">
                                                        <?php echo number_format($stock); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="sec-cell-meta">
                                                        <?php echo $item['actualizado_en'] ? date('d/m/Y H:i', strtotime($item['actualizado_en'])) : '—'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="sec-cell-meta">
                                                        <?php echo htmlspecialchars($item['actualizado_por_nombre'] ?? '—'); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align:center;">
                                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php?especificacion_id=<?php echo (int) $item['especificacion_id']; ?>"
                                                       class="sec-action-btn" title="Ver historial">
                                                        <i class="bi bi-clock-history"></i>
                                                    </a>
                                                    <?php if ($puede_editar): ?>
                                                    <button type="button" class="sec-action-btn sec-action-btn--primary"
                                                            onclick="abrirMovimientoPara(<?php echo (int) $item['especificacion_id']; ?>)"
                                                            title="Registrar movimiento">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
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
                </div>
            </div>
        </main>
    </div>

    <?php if ($puede_editar): ?>
    <!-- MODAL: Registrar movimiento -->
    <div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/guardar_movimiento_inventario.php" id="formMovimiento">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Registrar movimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="sec-form-label">Tipo de movimiento *</label>
                            <div class="sec-radio-group">
                                <label class="sec-radio-tile" data-tipo="entrada">
                                    <input type="radio" name="tipo_movimiento" value="entrada" checked>
                                    <i class="bi bi-arrow-down-circle"></i>
                                    Entrada
                                </label>
                                <label class="sec-radio-tile" data-tipo="ajuste">
                                    <input type="radio" name="tipo_movimiento" value="ajuste">
                                    <i class="bi bi-sliders"></i>
                                    Ajuste
                                </label>
                            </div>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem; display: block; margin-top: 0.55rem;" id="tipoAyuda">
                                <strong>Entrada:</strong> agrega envases al stock actual.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="sec-form-label">Especificación *</label>
                            <select class="sec-select" name="especificacion_id" id="selectEspec" required>
                                <option value="">Seleccione especificación…</option>
                                <?php
                                $agrupados = [];
                                foreach ($inventario as $item) {
                                    if ((int) $item['especificacion_activa'] === 0) continue;
                                    if ((int) $item['tipo_activo'] === 0) continue;
                                    $agrupados[$item['tipo_nombre']][] = $item;
                                }
                                foreach ($agrupados as $tipo_nombre => $items):
                                ?>
                                <optgroup label="<?php echo htmlspecialchars($tipo_nombre); ?>">
                                    <?php foreach ($items as $it): ?>
                                    <option value="<?php echo (int) $it['especificacion_id']; ?>"
                                            data-stock="<?php echo (int) $it['cantidad_actual']; ?>">
                                        <?php echo htmlspecialchars($it['especificacion_nombre']); ?>
                                        (stock: <?php echo (int) $it['cantidad_actual']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="sec-form-label" id="labelCantidad">Cantidad a agregar *</label>
                            <input type="number" class="sec-input" name="cantidad" id="inputCantidad" min="0" step="1" required>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem;" id="cantidadAyuda">
                                Ingresa un número entero mayor a 0.
                            </small>
                        </div>

                        <div class="sec-preview mb-3" id="previewCalc" style="display:none;">
                            <div class="sec-preview__row">
                                <div>
                                    <div class="sec-preview__label">Stock actual</div>
                                    <div class="sec-preview__val" id="previewActual">—</div>
                                </div>
                                <div>
                                    <div class="sec-preview__label">Cambio</div>
                                    <div class="sec-preview__val" id="previewCambio">—</div>
                                </div>
                                <div>
                                    <div class="sec-preview__label">Resultante</div>
                                    <div class="sec-preview__val" id="previewResultante">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="sec-form-label">Motivo / referencia</label>
                            <textarea class="sec-textarea" name="motivo" id="inputMotivo" rows="2" maxlength="500"
                                      placeholder="Ej. Compra a proveedor XYZ, conteo físico mensual, baja por daño…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="sec-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="sec-btn sec-btn--primary" id="btnGuardar">
                            <i class="bi bi-check-circle"></i> Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast flash -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="toast show" role="alert">
            <div class="toast-header bg-<?php echo $_SESSION['flash_tipo'] ?? 'primary'; ?> text-white">
                <strong class="me-auto">
                    <?php echo ($_SESSION['flash_tipo'] ?? '') === 'success' ? 'Éxito' : (($_SESSION['flash_tipo'] ?? '') === 'danger' ? 'Error' : 'Aviso'); ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"><?php echo htmlspecialchars($_SESSION['flash_msg']); ?></div>
        </div>
    </div>
    <?php unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        // Inventario siempre en tema claro
        document.body.setAttribute('data-theme', 'light');
    </script>

    <?php if ($puede_editar): ?>
    <script>
    (function () {
        const selectEspec  = document.getElementById('selectEspec');
        const inputCant    = document.getElementById('inputCantidad');
        const labelCant    = document.getElementById('labelCantidad');
        const cantAyuda    = document.getElementById('cantidadAyuda');
        const tipoAyuda    = document.getElementById('tipoAyuda');
        const preview      = document.getElementById('previewCalc');
        const prevActual   = document.getElementById('previewActual');
        const prevCambio   = document.getElementById('previewCambio');
        const prevResult   = document.getElementById('previewResultante');
        const btnGuardar   = document.getElementById('btnGuardar');
        const radioTiles   = document.querySelectorAll('.sec-radio-tile');

        const tileColorMap = { entrada: 'verde', ajuste: 'ambar' };

        function getTipoSeleccionado() {
            return document.querySelector('input[name="tipo_movimiento"]:checked').value;
        }
        function getStockActual() {
            const opt = selectEspec.options[selectEspec.selectedIndex];
            if (!opt || !opt.value) return null;
            return parseInt(opt.dataset.stock || '0', 10);
        }
        function marcarTileSeleccionada() {
            const tipo = getTipoSeleccionado();
            radioTiles.forEach(t => {
                t.classList.remove('selected--verde', 'selected--ambar');
                if (t.dataset.tipo === tipo) {
                    t.classList.add('selected--' + tileColorMap[tipo]);
                }
            });
        }
        function actualizarLabels() {
            const tipo = getTipoSeleccionado();
            switch (tipo) {
                case 'entrada':
                    labelCant.textContent = 'Cantidad a agregar *';
                    cantAyuda.textContent = 'Ingresa un número entero mayor a 0.';
                    tipoAyuda.innerHTML = '<strong>Entrada:</strong> agrega envases al stock actual.';
                    break;
                case 'ajuste':
                    labelCant.textContent = 'Nuevo stock (cantidad final) *';
                    cantAyuda.textContent = 'Ingresa la cantidad final que quedará en inventario (puede ser 0).';
                    tipoAyuda.innerHTML = '<strong>Ajuste:</strong> corrige el stock actual (típicamente tras un conteo físico).';
                    break;
            }
        }
        function actualizarPreview() {
            const stock = getStockActual();
            const cant  = parseInt(inputCant.value, 10);
            if (stock === null || isNaN(cant)) {
                preview.style.display = 'none';
                btnGuardar.disabled = false;
                return;
            }
            const tipo = getTipoSeleccionado();
            let resultante = stock, cambio = 0, valido = true, errorMsg = '';
            switch (tipo) {
                case 'entrada':
                    if (cant <= 0) { valido = false; errorMsg = 'Debe ser > 0'; }
                    resultante = stock + cant; cambio = cant; break;
                case 'ajuste':
                    if (cant < 0) { valido = false; errorMsg = 'No negativo'; }
                    resultante = cant; cambio = cant - stock; break;
            }
            preview.style.display = '';
            prevActual.textContent = stock.toLocaleString();
            prevCambio.textContent = (cambio >= 0 ? '+' : '') + cambio.toLocaleString();
            prevCambio.style.color = cambio > 0 ? 'var(--sec-verde)' : (cambio < 0 ? 'var(--sec-rojo)' : 'var(--sec-text-muted)');
            prevResult.textContent = valido ? resultante.toLocaleString() : '⚠ ' + errorMsg;
            prevResult.style.color = valido ? 'var(--sec-verde)' : 'var(--sec-rojo)';
            btnGuardar.disabled = !valido;
        }

        radioTiles.forEach(t => {
            t.addEventListener('click', () => { marcarTileSeleccionada(); actualizarLabels(); actualizarPreview(); });
        });
        selectEspec.addEventListener('change', actualizarPreview);
        inputCant.addEventListener('input', actualizarPreview);

        marcarTileSeleccionada();
        actualizarLabels();

        window.abrirMovimientoPara = function (especId) {
            selectEspec.value = especId;
            inputCant.value = '';
            document.getElementById('inputMotivo').value = '';
            actualizarPreview();
            new bootstrap.Modal(document.getElementById('modalMovimiento')).show();
        };

        document.getElementById('modalMovimiento').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formMovimiento').reset();
            document.querySelector('input[name="tipo_movimiento"][value="entrada"]').checked = true;
            marcarTileSeleccionada();
            actualizarLabels();
            preview.style.display = 'none';
            btnGuardar.disabled = false;
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>