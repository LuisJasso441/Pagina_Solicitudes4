<?php
/**
 * Dashboard SEC — Salidas de Envases (refactor v2)
 * Lista agrupada por día, formato similar al Excel de control.
 *
 * Ubicación: dashboard/salidas_envases/sec/salidas_envases.php
 *
 * Acceso: Logística, Ventas, Almacén de Residuos, Dirección
 *         (cualquier usuario con permisos_sec.lector = 1)
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_filtros_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al módulo de Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/inicio.php');
}

$puede_crear = puede_crear_sec();
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

// Filtros (los avanzados vienen del Bloque 8.1)
$estados_seleccionados = $_GET['estados'] ?? [];
if (!is_array($estados_seleccionados)) $estados_seleccionados = $estados_seleccionados ? [$estados_seleccionados] : [];

$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'estados'     => $estados_seleccionados,
    'empresa'     => $_GET['empresa']    ?? '',
    'unidad_id'   => $_GET['unidad_id']  ?? '',
    'creador_id'  => $_GET['creador_id'] ?? '',
    'busqueda'    => $_GET['busqueda']   ?? '',
];

$agrupadas    = obtener_sec_filtradas_agrupadas($filtros);
$total_secs   = contar_sec_filtradas($filtros);
$n_filtros    = contar_filtros_activos($filtros);
$panel_abierto = $n_filtros > 0;

// Datos para los dropdowns de los filtros
$empresas_filtro  = obtener_empresas_para_filtro();
$unidades_filtro  = obtener_unidades_para_filtro();
$creadores_filtro = obtener_creadores_para_filtro();

// Query string actual (para el botón exportar)
$query_string_export = http_build_query(array_filter([
    'fecha_desde' => $filtros['fecha_desde'] ?: null,
    'fecha_hasta' => $filtros['fecha_hasta'] ?: null,
    'empresa'     => $filtros['empresa']     ?: null,
    'unidad_id'   => $filtros['unidad_id']   ?: null,
    'creador_id'  => $filtros['creador_id']  ?: null,
    'busqueda'    => $filtros['busqueda']    ?: null,
]));
if (!empty($filtros['estados'])) {
    foreach ($filtros['estados'] as $e) {
        $query_string_export .= ($query_string_export ? '&' : '') . 'estados[]=' . urlencode($e);
    }
}

// Mensajes flash
$mensajes = [
    'creada'      => ['success', 'SEC creada correctamente.'],
    'actualizada' => ['success', 'SEC actualizada correctamente.'],
    'enviada'     => ['success', 'SEC enviada a firma de entrega.'],
    'cancelada'   => ['warning', 'SEC cancelada.'],
    'cerrada'     => ['success', 'SEC cerrada exitosamente.'],
    'firmada'     => ['success', 'Firma registrada correctamente.'],
    'error'       => ['danger',  'Ocurrió un error al procesar la solicitud.'],
];
$msg_flash   = $_GET['msg']   ?? null;
$folio_flash = $_GET['folio'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salidas de Envases | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
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
        /* Paleta base para el header de cada día */
        .dia-header {
            background: linear-gradient(90deg, #f8f9fa 0%, #fff 100%);
            border-left: 6px solid var(--dia-color, #14b8a6);
        }
        .dia-header .accordion-button { color: #212529; }
        .dia-header .accordion-button i.bi-calendar3 {
            color: var(--dia-color, #14b8a6);
            font-size: 1.05rem;
        }
        .dia-header .accordion-button:not(.collapsed) {
            background: var(--dia-bg-open, #f0fdfa);
            color: var(--dia-color-dark, #0f766e);
        }
        .dia-header .accordion-button:focus { box-shadow: none; }

        /* Colores por día de la semana */
        .dia-lun { --dia-color: #3b82f6; --dia-color-dark: #1e40af; --dia-bg-open: #eff6ff; }
        .dia-mar { --dia-color: #f97316; --dia-color-dark: #9a3412; --dia-bg-open: #fff7ed; }
        .dia-mie { --dia-color: #22c55e; --dia-color-dark: #166534; --dia-bg-open: #f0fdf4; }
        .dia-jue { --dia-color: #a855f7; --dia-color-dark: #6b21a8; --dia-bg-open: #faf5ff; }
        .dia-vie { --dia-color: #ec4899; --dia-color-dark: #9d174d; --dia-bg-open: #fdf2f8; }
        .dia-sab { --dia-color: #eab308; --dia-color-dark: #854d0e; --dia-bg-open: #fefce8; }

        /* Chips de estado (multi-select) — Bloque 8.1 */
        .chips-estado {
            display: flex; flex-wrap: wrap; gap: 6px;
        }
        .chip-estado {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border: 1.5px solid #dee2e6;
            border-radius: 20px;
            background: #fff;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
        }
        .chip-estado input[type="checkbox"] {
            display: none;
        }
        .chip-estado:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }
        /* Chip activo: color según estado */
        .chip-estado.chip-activo {
            font-weight: 600;
            color: #fff;
        }
        .chip-warning.chip-activo { background: #ffc107; border-color: #ffc107; color: #212529; }
        .chip-primary.chip-activo { background: #0d6efd; border-color: #0d6efd; }
        .chip-success.chip-activo { background: #198754; border-color: #198754; }
        .chip-danger.chip-activo  { background: #dc3545; border-color: #dc3545; }
        .chip-secondary.chip-activo { background: #6c757d; border-color: #6c757d; }
        .dia-dom { --dia-color: #ef4444; --dia-color-dark: #991b1b; --dia-bg-open: #fef2f2; }

        /* Dark mode */
        [data-theme="dark"] .dia-header {
            background: linear-gradient(90deg, #2d3339 0%, #262c31 100%);
        }
        [data-theme="dark"] .dia-header .accordion-button { color: #e0e6ed; }
        [data-theme="dark"] .dia-header .accordion-button:not(.collapsed) {
            background: var(--dia-bg-open-dark, rgba(20,184,166,0.12));
            color: var(--dia-color-light, #14b8a6);
        }
        [data-theme="dark"] .dia-lun { --dia-color-light: #60a5fa; --dia-bg-open-dark: rgba(59,130,246,0.15); }
        [data-theme="dark"] .dia-mar { --dia-color-light: #fb923c; --dia-bg-open-dark: rgba(249,115,22,0.15); }
        [data-theme="dark"] .dia-mie { --dia-color-light: #4ade80; --dia-bg-open-dark: rgba(34,197,94,0.15); }
        [data-theme="dark"] .dia-jue { --dia-color-light: #c084fc; --dia-bg-open-dark: rgba(168,85,247,0.15); }
        [data-theme="dark"] .dia-vie { --dia-color-light: #f472b6; --dia-bg-open-dark: rgba(236,72,153,0.15); }
        [data-theme="dark"] .dia-sab { --dia-color-light: #facc15; --dia-bg-open-dark: rgba(234,179,8,0.15); }
        [data-theme="dark"] .dia-dom { --dia-color-light: #f87171; --dia-bg-open-dark: rgba(239,68,68,0.15); }

        .tabla-sec { font-size: 0.85rem; }
        .tabla-sec th {
            background-color: #f1f3f5;
            font-weight: 600;
            vertical-align: middle;
            font-size: 0.80rem;
            padding: 8px 6px;
        }
        .tabla-sec td { vertical-align: middle; padding: 6px 6px; }
        .tabla-sec tbody td { vertical-align: middle; }

        /* Row (SEC) clickeable */
        .sec-clickable { cursor: pointer; transition: background 0.12s; }
        .sec-clickable:hover tr { background-color: #f0fdfa !important; }
        .sec-clickable:hover .indicador-btn {
            border-color: #14b8a6;
            background: #14b8a6;
            color: #fff;
        }
        [data-theme="dark"] .sec-clickable:hover tr { background-color: rgba(20,184,166,0.10) !important; }

        .pendiente { color: #6c757d; font-style: italic; font-size: 0.78rem; }
        .estado-badge { font-size: 0.72rem; padding: 4px 8px; }
        .sec-row-first td { border-top: 2px solid #dee2e6; }
        .filtros-card .form-control,
        .filtros-card .form-select { font-size: 0.85rem; }

        .empresa-cell { font-weight: 500; }
        .tipo-espec { color: #495057; }
        [data-theme="dark"] .tipo-espec { color: #b8c1cc; }
        .tipo-espec .tipo { font-weight: 600; }
        .tipo-espec .espec { font-size: 0.78rem; color: #6c757d; }
        [data-theme="dark"] .tipo-espec .espec { color: #9aa4b2; }

        .cantidad-badge {
            display: inline-block;
            min-width: 42px;
            padding: 2px 8px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 4px;
            font-weight: 700;
            text-align: center;
        }
        [data-theme="dark"] .cantidad-badge {
            background: rgba(3,105,161,0.20);
            color: #7dd3fc;
        }

        .unidad-cell { line-height: 1.2; }
        .unidad-cell .vuelta {
            font-size: 0.72rem;
            color: #6c757d;
            font-weight: 600;
        }
        .unidad-cell .matr {
            font-family: 'Courier New', monospace;
            font-size: 0.72rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        if (in_array($dept, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php';
        } elseif ($dept === 'ventas') {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-box-arrow-right"></i> Salidas de Envases</h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Control de salidas de envases hacia empresas destino
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($total_secs > 0): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/exportar_sec.php<?php echo $query_string_export ? '?' . $query_string_export : ''; ?>"
                                   class="btn btn-outline-success" title="Descargar Excel con los filtros actuales">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/vueltas/vueltas.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-repeat"></i> Vueltas
                            </a>
                            <?php if ($dept === 'logistica'): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades/unidades_transporte.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-truck"></i> Unidades
                                </a>
                            <?php endif; ?>
                            <?php if ($puede_crear): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/nueva_sec.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Nueva SEC
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Mensaje flash -->
                <?php if ($msg_flash && isset($mensajes[$msg_flash])): ?>
                    <div class="alert alert-<?php echo $mensajes[$msg_flash][0]; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($mensajes[$msg_flash][1]); ?>
                        <?php if ($folio_flash): ?>
                            <strong><?php echo htmlspecialchars($folio_flash); ?></strong>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- FILTROS AVANZADOS (Bloque 8.1) -->
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#panelFiltros"
                                aria-expanded="<?php echo $panel_abierto ? 'true' : 'false'; ?>">
                            <i class="bi bi-funnel"></i> Filtros
                            <?php if ($n_filtros > 0): ?>
                                <span class="badge bg-primary ms-1"><?php echo $n_filtros; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="text-muted small">
                            <i class="bi bi-list-ul"></i>
                            <strong><?php echo number_format($total_secs); ?></strong>
                            SEC<?php echo $total_secs === 1 ? '' : 's'; ?> encontrada<?php echo $total_secs === 1 ? '' : 's'; ?>
                            <?php if ($n_filtros > 0): ?>
                                <span class="ms-2">·</span>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/salidas_envases.php"
                                   class="ms-1 text-decoration-none">
                                    <i class="bi bi-x-circle"></i> Limpiar filtros
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="collapse <?php echo $panel_abierto ? 'show' : ''; ?>" id="panelFiltros">
                        <div class="card filtros-card">
                            <div class="card-body">
                                <form method="GET" id="formFiltros">
                                    <!-- Búsqueda + Empresa -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">
                                                <i class="bi bi-search"></i> Búsqueda
                                            </label>
                                            <input type="text" name="busqueda" class="form-control form-control-sm"
                                                   value="<?php echo htmlspecialchars($filtros['busqueda']); ?>"
                                                   placeholder="Folio, solicita o chofer">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">
                                                <i class="bi bi-building"></i> Empresa destino
                                            </label>
                                            <select name="empresa" class="form-select form-select-sm">
                                                <option value="">Todas las empresas</option>
                                                <?php foreach ($empresas_filtro as $emp): ?>
                                                    <option value="<?php echo htmlspecialchars($emp); ?>"
                                                        <?php echo $filtros['empresa'] === $emp ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($emp); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">
                                                <i class="bi bi-truck"></i> Unidad de transporte
                                            </label>
                                            <select name="unidad_id" class="form-select form-select-sm">
                                                <option value="">Todas las unidades</option>
                                                <?php foreach ($unidades_filtro as $u): ?>
                                                    <option value="<?php echo (int) $u['id']; ?>"
                                                        <?php echo (int) $filtros['unidad_id'] === (int) $u['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($u['nombre'] . ' — ' . $u['matricula']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Fechas con presets + Creador -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">
                                                <i class="bi bi-calendar-range"></i> Rango de fecha
                                            </label>
                                            <select id="fechaPreset" class="form-select form-select-sm">
                                                <option value="">Personalizado</option>
                                                <option value="hoy">Hoy</option>
                                                <option value="7dias">Últimos 7 días</option>
                                                <option value="mes_actual">Este mes</option>
                                                <option value="mes_pasado">Mes pasado</option>
                                                <option value="año_actual">Este año</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Desde</label>
                                            <input type="date" name="fecha_desde" id="fechaDesde"
                                                   class="form-control form-control-sm"
                                                   value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Hasta</label>
                                            <input type="date" name="fecha_hasta" id="fechaHasta"
                                                   class="form-control form-control-sm"
                                                   value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">
                                                <i class="bi bi-person"></i> Creador
                                            </label>
                                            <select name="creador_id" class="form-select form-select-sm">
                                                <option value="">Todos</option>
                                                <?php foreach ($creadores_filtro as $c): ?>
                                                    <option value="<?php echo (int) $c['id']; ?>"
                                                        <?php echo (int) $filtros['creador_id'] === (int) $c['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['nombre_completo']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Chips de estado (multi-select) -->
                                    <div class="mb-3">
                                        <label class="form-label small mb-2">
                                            <i class="bi bi-tag"></i> Estado (múltiples)
                                        </label>
                                        <div class="chips-estado">
                                            <?php
                                            $estados_todos = [
                                                'pendiente_firma_entrega',
                                                'en_ruta',
                                                'cerrada',
                                                'cerrada_con_devolucion',
                                                'cancelada',
                                            ];
                                            foreach ($estados_todos as $e):
                                                $meta = estado_sec_meta($e);
                                                $activo = in_array($e, $filtros['estados'], true);
                                            ?>
                                                <label class="chip-estado chip-<?php echo $meta['clase']; ?> <?php echo $activo ? 'chip-activo' : ''; ?>">
                                                    <input type="checkbox" name="estados[]" value="<?php echo $e; ?>"
                                                           <?php echo $activo ? 'checked' : ''; ?>>
                                                    <i class="bi bi-<?php echo $meta['icono']; ?>"></i>
                                                    <?php echo $meta['label']; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Acciones -->
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/salidas_envases.php"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                                        </a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-funnel"></i> Aplicar filtros
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listado agrupado por día -->
                <?php if (empty($agrupadas)): ?>
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0">No hay SEC registradas con los filtros actuales.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="accordionDias">
                        <?php
                        // Mapeo día de la semana (0=Dom, 1=Lun, ..., 6=Sab) a clase CSS
                        $dia_clases = [0 => 'dia-dom', 1 => 'dia-lun', 2 => 'dia-mar', 3 => 'dia-mie', 4 => 'dia-jue', 5 => 'dia-vie', 6 => 'dia-sab'];
                        ?>
                        <?php $dia_idx = 0; foreach ($agrupadas as $fecha => $secs_del_dia): $dia_idx++; ?>
                            <?php
                                $fecha_formato = sec_fecha_larga_es($fecha);
                                $dia_semana    = (int) date('w', strtotime($fecha));
                                $dia_clase     = $dia_clases[$dia_semana] ?? 'dia-lun';
                            ?>
                            <div class="accordion-item mb-2 <?php echo $dia_clase; ?>">
                                <h2 class="accordion-header dia-header">
                                    <button class="accordion-button <?php echo $dia_idx > 1 ? 'collapsed' : ''; ?>" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#diaCollapse<?php echo $dia_idx; ?>">
                                        <i class="bi bi-calendar3 me-2"></i>
                                        <strong><?php echo $fecha_formato; ?></strong>
                                        <span class="ms-3 badge bg-secondary"><?php echo count($secs_del_dia); ?> SEC</span>
                                    </button>
                                </h2>
                                <div id="diaCollapse<?php echo $dia_idx; ?>" class="accordion-collapse collapse <?php echo $dia_idx === 1 ? 'show' : ''; ?>">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover tabla-sec mb-0 align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Folio</th>
                                                        <th>Unidad / Vuelta</th>
                                                        <th>Empresa destino</th>
                                                        <th>Tipo / Especificación</th>
                                                        <th class="text-center" style="width: 90px;">Cantidad</th>
                                                        <th>Condiciones</th>
                                                        <th>Solicita</th>
                                                        <th>Entrega</th>
                                                        <th>Recibe</th>
                                                        <th class="text-center">Estado</th>
                                                        <th class="text-center" style="width: 60px;"></th>
                                                    </tr>
                                                </thead>
                                                <?php foreach ($secs_del_dia as $sec):
                                                    $lineas = $sec['lineas'] ?? [];
                                                    $n_lineas = max(1, count($lineas));
                                                    $info_estado = info_estado_sec($sec['estado']);
                                                    $primera = true;
                                                    $ver_url = URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . (int) $sec['id'];
                                                ?>
                                                <tbody class="sec-clickable" data-href="<?php echo htmlspecialchars($ver_url); ?>">
                                                    <?php if (empty($lineas)):
                                                        // SEC sin líneas (edge case, no debería pasar excepto en borrador vacío)
                                                    ?>
                                                        <tr class="sec-row-first">
                                                            <td><strong><?php echo htmlspecialchars($sec['folio']); ?></strong></td>
                                                            <td class="unidad-cell">
                                                                <?php echo htmlspecialchars($sec['unidad_nombre']); ?>
                                                                <?php if ($sec['unidad_matricula']): ?>
                                                                    <div class="matr"><?php echo htmlspecialchars($sec['unidad_matricula']); ?></div>
                                                                <?php endif; ?>
                                                                <div class="vuelta">Vuelta <?php echo (int) $sec['vuelta_numero']; ?></div>
                                                            </td>
                                                            <td colspan="4" class="text-muted fst-italic">— Sin líneas —</td>
                                                            <td>
                                                                <?php echo $sec['solicita_nombre'] ? htmlspecialchars($sec['solicita_nombre']) : '<span class="pendiente">—</span>'; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($sec['entrega_nombre']): ?>
                                                                    <?php echo htmlspecialchars($sec['entrega_nombre']); ?>
                                                                    <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                <?php else: ?>
                                                                    <span class="pendiente">Pendiente</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($sec['recibe_nombre']): ?>
                                                                    <?php echo htmlspecialchars($sec['recibe_nombre']); ?>
                                                                    <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                <?php else: ?>
                                                                    <span class="pendiente">Pendiente</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge estado-badge <?php echo $info_estado[0]; ?>"><?php echo $info_estado[1]; ?></span>
                                                            </td>
                                                            <td class="text-center">
                                                                <a href="<?php echo $ver_url; ?>" class="btn btn-sm btn-outline-primary indicador-btn" title="Ver detalle">
                                                                    <i class="bi bi-chevron-right"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($lineas as $idx => $linea): ?>
                                                            <tr class="<?php echo $primera ? 'sec-row-first' : ''; ?>">
                                                                <?php if ($primera): ?>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <strong><?php echo htmlspecialchars($sec['folio']); ?></strong>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>" class="unidad-cell">
                                                                        <?php echo htmlspecialchars($sec['unidad_nombre']); ?>
                                                                        <?php if (!empty($sec['unidad_matricula'])): ?>
                                                                            <div class="matr"><?php echo htmlspecialchars($sec['unidad_matricula']); ?></div>
                                                                        <?php endif; ?>
                                                                        <div class="vuelta">Vuelta <?php echo (int) $sec['vuelta_numero']; ?></div>
                                                                    </td>
                                                                <?php endif; ?>

                                                                <td class="empresa-cell"><?php echo htmlspecialchars($linea['empresa_nombre']); ?></td>
                                                                <td class="tipo-espec">
                                                                    <div class="tipo"><?php echo htmlspecialchars($linea['tipo_nombre']); ?></div>
                                                                    <div class="espec"><?php echo htmlspecialchars($linea['especificacion_nombre']); ?></div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="cantidad-badge"><?php echo (int) $linea['cantidad']; ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($linea['condiciones_envase'])): ?>
                                                                        <small><?php echo htmlspecialchars($linea['condiciones_envase']); ?></small>
                                                                    <?php else: ?>
                                                                        <span class="pendiente">—</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <?php if ($primera): ?>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php echo $sec['solicita_nombre'] ? htmlspecialchars($sec['solicita_nombre']) : '<span class="pendiente">—</span>'; ?>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php if ($sec['entrega_nombre']): ?>
                                                                            <?php echo htmlspecialchars($sec['entrega_nombre']); ?>
                                                                            <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                        <?php else: ?>
                                                                            <span class="pendiente">Pendiente</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php if ($sec['recibe_nombre']): ?>
                                                                            <?php echo htmlspecialchars($sec['recibe_nombre']); ?>
                                                                            <i class="bi bi-check-circle-fill text-success" title="Firmado<?php echo (int)($sec['recibe_es_externo'] ?? 0) === 1 ? ' (externo)' : ''; ?>"></i>
                                                                        <?php else: ?>
                                                                            <span class="pendiente">Pendiente</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>" class="text-center">
                                                                        <span class="badge estado-badge <?php echo $info_estado[0]; ?>"><?php echo $info_estado[1]; ?></span>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>" class="text-center">
                                                                        <a href="<?php echo $ver_url; ?>" class="btn btn-sm btn-outline-primary indicador-btn" title="Ver detalle">
                                                                            <i class="bi bi-chevron-right"></i>
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                            <?php $primera = false; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        // Navegación por click en cualquier parte de la SEC
        document.querySelectorAll('.sec-clickable').forEach(tbody => {
            tbody.addEventListener('click', (e) => {
                if (e.target.closest('a, button')) return;
                if (window.getSelection().toString()) return;
                const url = tbody.dataset.href;
                if (url) window.location = url;
            });
        });

        // ==========================================================
        // Bloque 8.1 — Filtros: presets de fecha y chips de estado
        // ==========================================================

        // Presets de fecha
        const preset     = document.getElementById('fechaPreset');
        const fechaDesde = document.getElementById('fechaDesde');
        const fechaHasta = document.getElementById('fechaHasta');

        if (preset && fechaDesde && fechaHasta) {
            const hoy = new Date();
            const fmt = (d) => d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');

            preset.addEventListener('change', () => {
                let desde = null, hasta = null;
                const p = preset.value;
                if (p === 'hoy') {
                    desde = hasta = fmt(hoy);
                } else if (p === '7dias') {
                    const d = new Date(); d.setDate(d.getDate() - 6);
                    desde = fmt(d); hasta = fmt(hoy);
                } else if (p === 'mes_actual') {
                    desde = fmt(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
                    hasta = fmt(hoy);
                } else if (p === 'mes_pasado') {
                    desde = fmt(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1));
                    hasta = fmt(new Date(hoy.getFullYear(), hoy.getMonth(), 0));
                } else if (p === 'año_actual') {
                    desde = fmt(new Date(hoy.getFullYear(), 0, 1));
                    hasta = fmt(hoy);
                } else {
                    return; // Personalizado — no toca
                }
                fechaDesde.value = desde;
                fechaHasta.value = hasta;
            });

            // Cuando el usuario cambia una fecha manualmente, volver a "Personalizado"
            [fechaDesde, fechaHasta].forEach(inp => {
                inp.addEventListener('change', () => { preset.value = ''; });
            });
        }

        // Chips: toggle visual al hacer click (el checkbox oculto ya cambia solo)
        document.querySelectorAll('.chip-estado').forEach(chip => {
            const cb = chip.querySelector('input[type="checkbox"]');
            if (!cb) return;
            chip.addEventListener('click', () => {
                // Después de que el checkbox interno cambie, sincronizar la clase visual
                setTimeout(() => {
                    chip.classList.toggle('chip-activo', cb.checked);
                }, 0);
            });
        });
    </script>
</body>
</html>