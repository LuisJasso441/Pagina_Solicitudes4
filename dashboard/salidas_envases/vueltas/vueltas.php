<?php
/**
 * Vueltas — v2 (estética temática SEC)
 * dashboard/salidas_envases/vueltas/vueltas.php
 *
 * Permisos:
 *   - Logística: CRUD completo (agregar/eliminar vueltas por día).
 *   - Almacén de Residuos y Ventas: solo lectura.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/unidades_transporte_funciones.php';

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

$puede_editar = es_logistica();
$unidades     = obtener_unidades_transporte(false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vueltas | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
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

        /* ========= Modales de Bootstrap con paleta SEC ========= */
        .modal-content {
            background: var(--sec-card);
            color: var(--sec-text);
            border: 1px solid var(--sec-border);
            border-radius: 12px;
            box-shadow: var(--sec-shadow-md);
            font-family: 'Poppins', sans-serif;
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
        .sec-filter-inline {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

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
        .sec-btn--danger {
            background: var(--sec-card);
            border-color: var(--sec-rojo);
            color: var(--sec-rojo);
        }
        .sec-btn--danger:hover { background: var(--sec-rojo-soft); color: var(--sec-rojo); }
        .sec-btn--sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; }
        .sec-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .sec-btn i { font-size: 1rem; }

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
        .sec-input, .sec-select {
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
        .sec-input:focus, .sec-select:focus {
            outline: none;
            border-color: var(--sec-verde);
            box-shadow: 0 0 0 3px var(--sec-verde-soft);
        }

        /* Alert */
        .sec-alert {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.85rem 1.1rem;
            background: var(--sec-ambar-soft);
            border: 1px solid var(--sec-ambar);
            border-left: 4px solid var(--sec-ambar);
            border-radius: 8px;
            color: var(--sec-text);
            font-size: 0.9rem;
        }
        .sec-alert i { color: var(--sec-ambar); font-size: 1.2rem; flex-shrink: 0; }

        /* Contenedor del calendario */
        .sec-cal-wrap {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            box-shadow: var(--sec-shadow-sm);
            padding: 1.2rem;
            overflow: hidden;
        }

        /* ============ FullCalendar — temática SEC ============ */
        #calendario {
            font-family: 'Poppins', sans-serif;
            color: var(--sec-text);
        }
        /* Toolbar */
        .fc .fc-toolbar {
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .fc .fc-toolbar-title {
            font-size: 1.15rem !important;
            font-weight: 700;
            color: var(--sec-text);
            text-transform: capitalize;
        }
        /* Botones del calendario */
        .fc .fc-button-primary {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            color: var(--sec-text);
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            box-shadow: var(--sec-shadow-sm);
            text-transform: capitalize;
            transition: all .15s;
        }
        .fc .fc-button-primary:hover {
            background: var(--sec-hover-bg);
            border-color: var(--sec-verde);
            color: var(--sec-verde);
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background: var(--sec-verde);
            border-color: var(--sec-verde);
            color: #fff;
            box-shadow: var(--sec-shadow-md);
        }
        .fc .fc-button-primary:focus {
            box-shadow: 0 0 0 3px var(--sec-verde-soft);
        }
        .fc .fc-button:disabled {
            opacity: 0.5;
        }
        .fc .fc-today-button {
            background: var(--sec-verde-soft);
            border-color: var(--sec-verde);
            color: var(--sec-verde);
        }
        .fc .fc-today-button:disabled {
            background: var(--sec-border-soft);
            border-color: var(--sec-border);
            color: var(--sec-text-muted);
            opacity: 0.6;
        }

        /* Headers de días */
        .fc .fc-col-header-cell {
            background: var(--sec-bg-base);
            border-color: var(--sec-border);
            padding: 0.5rem 0;
        }
        .fc .fc-col-header-cell-cushion {
            color: var(--sec-text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.35rem;
        }

        /* Celdas de días */
        .fc .fc-daygrid-day {
            transition: background .12s;
        }
        .fc .fc-daygrid-day:hover {
            background: var(--sec-verde-soft);
            cursor: pointer;
        }
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: var(--sec-border);
        }
        .fc-theme-standard .fc-scrollgrid {
            border-color: var(--sec-border);
        }
        .fc .fc-daygrid-day-frame {
            min-height: 100px;
            padding: 4px;
        }
        .fc .fc-daygrid-day-number {
            color: var(--sec-text);
            font-weight: 600;
            font-size: 0.85rem;
            font-variant-numeric: tabular-nums;
            padding: 6px 8px 0;
        }

        /* Día actual */
        .fc .fc-day-today {
            background: var(--sec-verde-soft) !important;
        }
        .fc .fc-day-today .fc-daygrid-day-number {
            background: var(--sec-verde);
            color: #fff;
            border-radius: 6px;
            padding: 3px 8px;
            margin: 4px 4px 0 auto;
            display: inline-block;
            font-weight: 700;
        }

        /* Días de otro mes */
        .fc .fc-day-other .fc-daygrid-day-number {
            color: var(--sec-text-muted);
            opacity: 0.55;
        }

        /* Eventos */
        .fc .fc-event {
            cursor: pointer;
            border: none;
            border-left: 3px solid rgba(0,0,0,0.15);
            border-radius: 4px;
            padding: 3px 6px;
            font-size: 0.78rem;
            font-weight: 500;
            margin: 1px 3px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: transform .1s, box-shadow .1s;
        }
        .fc .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.12);
        }
        .fc .fc-event-title {
            font-weight: 600;
        }
        .fc .fc-daygrid-event-dot {
            display: none;
        }

        /* Vista semanal */
        .fc .fc-daygrid-week-number {
            background: var(--sec-bg-base);
            color: var(--sec-text-muted);
        }

        /* Modal */
        .sec-modal-panel {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .sec-modal-panel__head {
            padding: 0.7rem 1rem;
            background: var(--sec-bg-base);
            border-bottom: 1px solid var(--sec-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .sec-modal-panel__title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--sec-text);
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin: 0;
        }
        .sec-modal-panel__title i { color: var(--sec-verde); }
        .sec-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--sec-verde);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .sec-modal-panel--add { border-color: var(--sec-verde); }
        .sec-modal-panel--add .sec-modal-panel__head {
            background: var(--sec-verde-soft);
        }

        /* Lista de vueltas (slips) */
        .sec-vuelta-list {
            display: flex;
            flex-direction: column;
        }
        .sec-vuelta-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 1rem;
            border-bottom: 1px dashed var(--sec-border);
        }
        .sec-vuelta-item:last-child { border-bottom: none; }
        .sec-vuelta-item__left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
            flex: 1;
        }
        .sec-vuelta-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            border-radius: 8px;
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border: 1.5px solid var(--sec-verde);
            font-weight: 700;
            font-size: 0.9rem;
            font-variant-numeric: tabular-nums;
            flex-shrink: 0;
        }
        .sec-vuelta-info { min-width: 0; }
        .sec-vuelta-info strong {
            display: block;
            font-size: 0.9rem;
            color: var(--sec-text);
        }
        .sec-vuelta-notas {
            display: block;
            color: var(--sec-text-muted);
            font-size: 0.78rem;
            margin-top: 2px;
        }
        .sec-empty-mini {
            text-align: center;
            color: var(--sec-text-muted);
            padding: 1rem;
            font-size: 0.85rem;
            font-style: italic;
        }
        .sec-mini-btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            border-radius: 6px;
            background: transparent;
            border: 1px solid var(--sec-border);
            color: var(--sec-text-muted);
            cursor: pointer;
            transition: all .15s;
            flex-shrink: 0;
        }
        .sec-mini-btn-danger:hover {
            background: var(--sec-rojo-soft);
            color: var(--sec-rojo);
            border-color: var(--sec-rojo);
        }

        /* Responsive */
        @media (max-width: 640px) {
            .sec-page-head { padding-bottom: 0.85rem; }
            .sec-page-head h1 { font-size: 1.15rem; }
            .sec-page-head__stamp { width: 42px; height: 42px; font-size: 1.3rem; }
            .sec-cal-wrap { padding: 0.75rem; }
            .fc .fc-toolbar { flex-direction: column; align-items: stretch; }
            .fc .fc-toolbar-title { text-align: center; font-size: 1rem !important; }
            .fc .fc-daygrid-day-frame { min-height: 70px; }
            .fc .fc-button-primary { font-size: 0.75rem; padding: 0.35rem 0.55rem; }
            .fc .fc-daygrid-day-number { font-size: 0.75rem; padding: 4px 5px 0; }
            .fc .fc-event { font-size: 0.7rem; padding: 2px 4px; }
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
                            <div class="sec-page-head__stamp"><i class="bi bi-arrow-repeat"></i></div>
                            <div>
                                <h1>Vueltas</h1>
                                <p class="sec-page-head__subtitle">
                                    <?php echo $puede_editar
                                        ? 'Programa vueltas por unidad y día. Click en un día del calendario para gestionar.'
                                        : 'Consulta las vueltas programadas por unidad (solo lectura).'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="sec-filter-inline">
                            <label class="sec-form-label" for="filtroUnidad">Filtrar por unidad</label>
                            <select class="sec-select" id="filtroUnidad" style="min-width: 220px;">
                                <option value="">Todas las unidades</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($unidades)): ?>
                    <div class="sec-alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            No hay unidades de transporte activas. Registra unidades en
                            <strong>Unidades de Transporte</strong> antes de programar vueltas.
                        </div>
                    </div>
                    <?php else: ?>

                    <!-- Calendario -->
                    <div class="sec-cal-wrap">
                        <div id="calendario"></div>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de gestión de vueltas del día -->
    <div class="modal fade" id="modalVueltas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-day"></i>
                        Vueltas del <span id="modalFechaLabel"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Selector de unidad -->
                    <div class="mb-3">
                        <label class="sec-form-label">Unidad</label>
                        <select class="sec-select" id="modalUnidad">
                            <option value="">Seleccione unidad…</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lista de vueltas actuales -->
                    <div class="sec-modal-panel mb-3" id="cardListaVueltas" style="display:none;">
                        <div class="sec-modal-panel__head">
                            <h6 class="sec-modal-panel__title"><i class="bi bi-list-check"></i> Vueltas programadas</h6>
                            <span class="sec-count-badge" id="badgeTotalVueltas">0</span>
                        </div>
                        <div id="listaVueltas">
                            <div class="sec-empty-mini">Selecciona una unidad…</div>
                        </div>
                    </div>

                    <?php if ($puede_editar): ?>
                    <!-- Agregar vueltas -->
                    <div class="sec-modal-panel sec-modal-panel--add" id="cardAgregar" style="display:none;">
                        <div class="sec-modal-panel__head">
                            <h6 class="sec-modal-panel__title"><i class="bi bi-plus-circle"></i> Agregar vueltas</h6>
                        </div>
                        <div style="padding: 1rem;">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="sec-form-label">Cantidad</label>
                                    <input type="number" class="sec-input" id="inputCantidad"
                                           min="1" max="20" value="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="sec-form-label">Notas (opcional)</label>
                                    <input type="text" class="sec-input" id="inputNotas"
                                           maxlength="500" placeholder="Opcional">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="sec-btn sec-btn--primary w-100" id="btnAgregarVueltas">
                                        <i class="bi bi-plus-circle"></i> Agregar
                                    </button>
                                </div>
                            </div>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem; display: block; margin-top: 0.6rem;">
                                Las vueltas se numeran automáticamente comenzando desde el siguiente número disponible.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sec-btn" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/es.global.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        // Página siempre en tema claro
        document.body.setAttribute('data-theme', 'light');
    </script>

    <?php if (!empty($unidades)): ?>
    <script>
    (function () {
        const URL_BASE      = <?php echo json_encode(URL_BASE); ?>;
        const puedeEditar   = <?php echo $puede_editar ? 'true' : 'false'; ?>;

        let calendario      = null;
        let modalVueltas    = null;
        let fechaModal      = null;

        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('calendario');
            if (!el) return;

            calendario = new FullCalendar.Calendar(el, {
                locale: 'es',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                },
                buttonText: {
                    today: 'Hoy',
                    week: 'Semana',
                    month: 'Mes'
                },
                firstDay: 1,
                height: 'auto',
                events: function (info, success, failure) {
                    const filtroUnidad = document.getElementById('filtroUnidad').value;
                    const params = new URLSearchParams({
                        start: info.startStr,
                        end: info.endStr,
                    });
                    if (filtroUnidad) params.set('unidad_id', filtroUnidad);
                    fetch(URL_BASE + 'dashboard/salidas_envases/api/eventos_vueltas.php?' + params.toString())
                        .then(r => r.json())
                        .then(data => success(data))
                        .catch(err => { console.error(err); failure(err); });
                },
                dateClick: function (info) {
                    abrirModalDelDia(info.dateStr);
                },
                eventClick: function (info) {
                    const fecha = info.event.extendedProps.fecha || info.event.startStr;
                    const unidadId = info.event.extendedProps.unidad_id;
                    abrirModalDelDia(fecha, unidadId);
                }
            });
            calendario.render();

            document.getElementById('filtroUnidad').addEventListener('change', () => {
                calendario.refetchEvents();
            });

            modalVueltas = new bootstrap.Modal(document.getElementById('modalVueltas'));
        });

        function abrirModalDelDia(fecha, unidadIdPreseleccion = null) {
            fechaModal = fecha;
            const partes = fecha.split('-');
            const d = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
            const opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            document.getElementById('modalFechaLabel').textContent = d.toLocaleDateString('es-MX', opts);

            const selUnidad = document.getElementById('modalUnidad');
            selUnidad.value = unidadIdPreseleccion || '';
            document.getElementById('cardListaVueltas').style.display = 'none';
            const cardAgregar = document.getElementById('cardAgregar');
            if (cardAgregar) cardAgregar.style.display = 'none';

            modalVueltas.show();

            if (unidadIdPreseleccion) {
                cargarVueltasDelDia();
            }
        }

        document.getElementById('modalUnidad').addEventListener('change', cargarVueltasDelDia);

        function cargarVueltasDelDia() {
            const unidadId = document.getElementById('modalUnidad').value;
            const cardLista = document.getElementById('cardListaVueltas');
            const cardAgregar = document.getElementById('cardAgregar');
            const lista = document.getElementById('listaVueltas');

            if (!unidadId) {
                cardLista.style.display = 'none';
                if (cardAgregar) cardAgregar.style.display = 'none';
                return;
            }

            cardLista.style.display = '';
            if (cardAgregar) cardAgregar.style.display = '';
            lista.innerHTML = '<div class="sec-empty-mini"><i class="bi bi-hourglass-split"></i> Cargando…</div>';

            const params = new URLSearchParams({ unidad_id: unidadId, fecha: fechaModal });
            fetch(URL_BASE + 'dashboard/salidas_envases/api/vueltas_por_dia.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.error || 'Error');
                    renderListaVueltas(data.vueltas);
                })
                .catch(err => {
                    console.error(err);
                    lista.innerHTML = '<div class="sec-empty-mini" style="color: var(--sec-rojo);">Error al cargar las vueltas.</div>';
                });
        }

        function renderListaVueltas(vueltas) {
            const lista = document.getElementById('listaVueltas');
            const badge = document.getElementById('badgeTotalVueltas');
            badge.textContent = vueltas.length;
            if (vueltas.length === 0) {
                lista.innerHTML = '<div class="sec-empty-mini">Sin vueltas programadas para esta unidad en esta fecha.</div>';
                return;
            }
            let html = '<div class="sec-vuelta-list">';
            vueltas.forEach(v => {
                html += '<div class="sec-vuelta-item">';
                html += '  <div class="sec-vuelta-item__left">';
                html += '    <span class="sec-vuelta-num">' + v.numero + '</span>';
                html += '    <div class="sec-vuelta-info">';
                html += '      <strong>Vuelta ' + v.numero + '</strong>';
                if (v.notas) {
                    html += '      <span class="sec-vuelta-notas">' + escapeHtml(v.notas) + '</span>';
                }
                html += '    </div>';
                html += '  </div>';
                if (puedeEditar) {
                    html += '  <button type="button" class="sec-mini-btn-danger" onclick="eliminarVuelta(' + v.id + ', ' + v.numero + ')" title="Eliminar vuelta">';
                    html += '    <i class="bi bi-trash"></i>';
                    html += '  </button>';
                }
                html += '</div>';
            });
            html += '</div>';
            lista.innerHTML = html;
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s || '';
            return div.innerHTML;
        }

        <?php if ($puede_editar): ?>
        document.getElementById('btnAgregarVueltas').addEventListener('click', function () {
            const unidadId = document.getElementById('modalUnidad').value;
            const cantidad = parseInt(document.getElementById('inputCantidad').value, 10);
            const notas    = document.getElementById('inputNotas').value;

            if (!unidadId) { alert('Selecciona una unidad.'); return; }
            if (isNaN(cantidad) || cantidad < 1) { alert('Cantidad inválida.'); return; }

            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const fd = new FormData();
            fd.append('unidad_id', unidadId);
            fd.append('fecha',     fechaModal);
            fd.append('cantidad',  cantidad);
            fd.append('notas',     notas);

            fetch(URL_BASE + 'dashboard/salidas_envases/vueltas/guardar_vueltas.php', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        document.getElementById('inputCantidad').value = '1';
                        document.getElementById('inputNotas').value = '';
                        cargarVueltasDelDia();
                        if (calendario) calendario.refetchEvents();
                    } else {
                        alert(data.msg || 'Error al agregar.');
                    }
                })
                .catch(err => { console.error(err); alert('Error de red.'); })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                });
        });

        window.eliminarVuelta = function (id, numero) {
            if (!confirm('¿Eliminar la Vuelta ' + numero + '?\n\nLas vueltas posteriores se renumerarán automáticamente.')) {
                return;
            }
            const fd = new FormData();
            fd.append('id', id);
            fetch(URL_BASE + 'dashboard/salidas_envases/vueltas/eliminar_vuelta.php', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        cargarVueltasDelDia();
                        if (calendario) calendario.refetchEvents();
                    } else {
                        alert(data.msg || 'Error al eliminar.');
                    }
                })
                .catch(err => { console.error(err); alert('Error de red.'); });
        };
        <?php endif; ?>
    })();
    </script>
    <?php endif; ?>
</body>
</html>