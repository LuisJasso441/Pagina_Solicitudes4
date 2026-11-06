<?php
/**
 * Calendario de Mantenimientos (Solo Sistemas)
 * dashboard/sistemas/ti_sistemas/mantenimientos/calendario_mantenimientos.php
 *
 * Vista de calendario (FullCalendar) de las solicitudes de mantenimiento.
 * - Colores por tipo: Físico (azul) / Lógico (verde)
 * - Pendientes con fecha pasada: marcados como vencidos
 * - Clic en un evento: detalle en modal + botón para abrir ver_mantenimiento.php
 * - Eventos desde api_calendario_mantenimientos.php
 *
 * GET opcional: fecha=YYYY-MM-DD (fecha inicial del calendario)
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas  = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'Solo el departamento de Sistemas puede ver el calendario de mantenimientos.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$pdo = conectarDB();

$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")
                     ->fetchAll(PDO::FETCH_ASSOC);

$fecha_inicial = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicial)) {
    $fecha_inicial = date('Y-m-d');
}

$estados_filtro = [
    'pendiente'  => ['nombre' => 'Pendiente',  'default' => true],
    'en_proceso' => ['nombre' => 'En Proceso', 'default' => true],
    'finalizada' => ['nombre' => 'Finalizada', 'default' => true],
    'cerrada'    => ['nombre' => 'Cerrada',    'default' => true],
    'cancelada'  => ['nombre' => 'Cancelada',  'default' => false],
];

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimientos - TI / Sistemas</title>
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
        /* ---------- Filtros y leyenda ---------- */
        .filtro-estado .form-check-label { font-size: 0.82rem; }
        .leyenda { display: flex; flex-wrap: wrap; gap: 0.9rem; font-size: 0.78rem; color: #495057; }
        .leyenda span { display: inline-flex; align-items: center; gap: 0.35rem; }
        .leyenda .cuadro { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }

        /* ---------- Calendario ---------- */
        #calendario { min-height: 600px; }
        .fc .fc-toolbar-title { font-size: 1.15rem; font-weight: 600; text-transform: capitalize; }
        .fc .fc-button { font-size: 0.8rem; text-transform: capitalize; }
        .fc .fc-daygrid-day.fc-day-today { background: #fff8e1; }

        .fc-event.mant-evento { cursor: pointer; font-size: 0.75rem; }
        .mant-contenido {
            display: flex; align-items: center; gap: 0.25rem;
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
            padding: 0 2px;
        }
        .mant-contenido .mant-texto { overflow: hidden; text-overflow: ellipsis; }

        .fc-event.mant-estado-finalizada,
        .fc-event.mant-estado-cerrada  { opacity: 0.6; }
        .fc-event.mant-estado-cancelada { opacity: 0.45; }
        .fc-event.mant-estado-cancelada .mant-texto { text-decoration: line-through; }
        .fc-event.mant-vencido {
            border-left: 4px solid #dc3545 !important;
            box-shadow: 0 0 0 1px #dc3545;
        }
        /* Vista lista: el punto toma el color del tipo */
        .fc-list-event.mant-vencido td { background: #fff5f5; }
        .fc-list-event .mant-contenido { color: #212529; }

        /* ---------- Modal detalle ---------- */
        .det-label {
            font-size: 0.72rem; color: #6c757d; margin-bottom: 0.1rem;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .det-valor { font-weight: 500; color: #212529; }
        #detDescripcion { white-space: pre-wrap; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php include __DIR__ . '/../../../../includes/sidebar/sidebar_ti.php'; ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Calendario de Mantenimientos</h4>
                        <small class="text-muted">Mantenimientos programados por fecha</small>
                    </div>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/programacion_mantenimientos.php"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar-plus me-1"></i>Programar
                </a>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <!-- Filtros -->
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Departamento</label>
                            <select id="filtroDepto" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?php echo intval($d['id']); ?>">
                                        <?php echo htmlspecialchars($d['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small mb-1 d-block">Estados</label>
                            <div class="d-flex flex-wrap gap-3 filtro-estado">
                                <?php foreach ($estados_filtro as $clave => $info): ?>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input js-estado" type="checkbox"
                                               id="est_<?php echo $clave; ?>" value="<?php echo $clave; ?>"
                                               <?php echo $info['default'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="est_<?php echo $clave; ?>">
                                            <?php echo $info['nombre']; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch m-0 filtro-estado">
                                <input class="form-check-input" type="checkbox" id="filtroHistoricos" checked>
                                <label class="form-check-label" for="filtroHistoricos">Incluir históricos</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="leyenda">
                        <span><i class="cuadro" style="background:#0d6efd"></i>Físico</span>
                        <span><i class="cuadro" style="background:#198754"></i>Lógico</span>
                        <span><i class="cuadro" style="background:#fff;border:2px solid #dc3545"></i>Pendiente vencido</span>
                        <span><i class="bi bi-clock"></i>Pendiente</span>
                        <span><i class="bi bi-gear"></i>En proceso</span>
                        <span><i class="bi bi-pen"></i>Finalizada (esperando firma)</span>
                        <span><i class="bi bi-check-circle"></i>Cerrada</span>
                        <span><i class="bi bi-x-circle"></i>Cancelada</span>
                    </div>
                </div>
            </div>

            <!-- Calendario -->
            <div class="card">
                <div class="card-body p-2 position-relative">
                    <div id="calCargando" class="position-absolute top-0 end-0 m-3 small text-muted d-none">
                        <span class="spinner-border spinner-border-sm me-1"></span>Cargando...
                    </div>
                    <div id="calendario"></div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Modal detalle del mantenimiento -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white" id="detHeader">
                <h5 class="modal-title">
                    <i class="bi bi-tools me-2"></i><span id="detFolio"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detVencido" class="alert alert-danger py-2 small d-none">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    Mantenimiento vencido: la fecha programada ya pasó y sigue pendiente.
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span id="detTipo" class="badge"></span>
                    <span id="detEstado" class="badge"></span>
                    <span id="detPrioridad" class="badge"></span>
                    <span id="detHistorico" class="badge bg-info d-none"><i class="bi bi-clock-history"></i> Histórico</span>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="det-label">Programado para</div>
                        <div class="det-valor" id="detFecha"></div>
                    </div>
                    <div class="col-6">
                        <div class="det-label">Equipo</div>
                        <div class="det-valor" id="detEquipo"></div>
                        <small class="text-muted" id="detEquipoInfo"></small>
                    </div>
                    <div class="col-6">
                        <div class="det-label">Usuario</div>
                        <div class="det-valor" id="detUsuario"></div>
                    </div>
                    <div class="col-6">
                        <div class="det-label">Departamento</div>
                        <div class="det-valor" id="detDepto"></div>
                    </div>
                    <div class="col-12">
                        <div class="det-label">Descripción</div>
                        <div class="det-valor" id="detDescripcion"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-primary" id="detVerLink">
                    <i class="bi bi-eye me-1"></i>Ver mantenimiento
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const URL_API = '<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/api_calendario_mantenimientos.php';
    const FECHA_INICIAL = '<?php echo $fecha_inicial; ?>';

    const TIPOS = {
        fisico: { nombre: 'Físico', icono: 'bi-tools', badge: 'bg-primary', color: '#0d6efd' },
        logico: { nombre: 'Lógico', icono: 'bi-cpu',   badge: 'bg-success', color: '#198754' },
    };
    const ESTADOS = {
        pendiente:  { nombre: 'Pendiente',  icono: 'bi-clock',        badge: 'bg-warning text-dark' },
        en_proceso: { nombre: 'En Proceso', icono: 'bi-gear',         badge: 'bg-primary' },
        finalizada: { nombre: 'Finalizada', icono: 'bi-pen',          badge: 'bg-success' },
        cerrada:    { nombre: 'Cerrada',    icono: 'bi-check-circle', badge: 'bg-secondary' },
        cancelada:  { nombre: 'Cancelada',  icono: 'bi-x-circle',     badge: 'bg-danger' },
    };
    const PRIORIDADES = {
        alta:  { nombre: 'Prioridad Alta',  badge: 'bg-danger' },
        media: { nombre: 'Prioridad Media', badge: 'bg-warning text-dark' },
        baja:  { nombre: 'Prioridad Baja',  badge: 'bg-success' },
    };
    const TIPOS_EQUIPO = { all_in_one: 'All in One', pc: 'PC', laptop: 'Laptop', computadora: 'Computadora' };

    const selDepto      = document.getElementById('filtroDepto');
    const chkHistoricos = document.getElementById('filtroHistoricos');
    const chkEstados    = document.querySelectorAll('.js-estado');
    const indCargando   = document.getElementById('calCargando');
    const modalDetalle  = new bootstrap.Modal(document.getElementById('modalDetalle'));

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, m => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[m]));
    }

    function formatoFecha(iso) {
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    }

    // =================================================================
    // CALENDARIO
    // =================================================================
    const calendar = new FullCalendar.Calendar(document.getElementById('calendario'), {
        initialView: 'dayGridMonth',
        initialDate: FECHA_INICIAL,
        locale: 'es',
        firstDay: 1,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
        allDayText: 'Día',
        noEventsText: 'No hay mantenimientos en este periodo',
        height: 'auto',
        dayMaxEvents: 4,
        moreLinkText: n => `+${n} más`,
        eventDisplay: 'block',
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

        events: function (info, success, failure) {
            const estados = Array.from(chkEstados).filter(c => c.checked).map(c => c.value);
            const params = new URLSearchParams({
                inicio:     info.startStr.substring(0, 10),
                fin:        info.endStr.substring(0, 10),
                estados:    estados.join(','),
                departamento_id: selDepto.value,
                historicos: chkHistoricos.checked ? '1' : '0',
            });

            indCargando.classList.remove('d-none');
            fetch(URL_API + '?' + params.toString())
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Error al cargar mantenimientos');
                    success(j);
                })
                .catch(err => {
                    console.error(err);
                    alert(err.message || 'Error al cargar los mantenimientos.');
                    failure(err);
                })
                .finally(() => indCargando.classList.add('d-none'));
        },

        // Contenido del evento: icono de estado + equipo · usuario
        eventContent: function (arg) {
            const p = arg.event.extendedProps;
            const icono = p.vencido ? 'bi-exclamation-circle' : (ESTADOS[p.estado]?.icono || 'bi-circle');
            const hora  = arg.timeText ? `<strong>${escapeHtml(arg.timeText)}</strong> ` : '';
            const div = document.createElement('div');
            div.className = 'mant-contenido';
            div.innerHTML = `<i class="bi ${icono}"></i><span class="mant-texto">${hora}${escapeHtml(arg.event.title)}</span>`;
            return { domNodes: [div] };
        },

        eventDidMount: function (info) {
            const p = info.event.extendedProps;
            info.el.title = `${p.folio} · ${TIPOS[p.tipo_mant]?.nombre || ''} · ${ESTADOS[p.estado]?.nombre || ''}`
                          + (p.vencido ? ' (VENCIDO)' : '');
        },

        eventClick: function (info) {
            info.jsEvent.preventDefault();
            mostrarDetalle(info.event);
        }
    });
    calendar.render();

    // Filtros -> recargar eventos
    selDepto.addEventListener('change', () => calendar.refetchEvents());
    chkHistoricos.addEventListener('change', () => calendar.refetchEvents());
    chkEstados.forEach(c => c.addEventListener('change', () => calendar.refetchEvents()));

    // =================================================================
    // MODAL DETALLE
    // =================================================================
    function mostrarDetalle(evento) {
        const p    = evento.extendedProps;
        const tipo = TIPOS[p.tipo_mant]   || { nombre: '—', icono: 'bi-question', badge: 'bg-secondary', color: '#6c757d' };
        const est  = ESTADOS[p.estado]    || { nombre: p.estado, badge: 'bg-secondary' };
        const pri  = PRIORIDADES[p.prioridad] || { nombre: p.prioridad, badge: 'bg-secondary' };

        document.getElementById('detHeader').style.backgroundColor = tipo.color;
        document.getElementById('detFolio').textContent = p.folio;

        const bTipo = document.getElementById('detTipo');
        bTipo.className = 'badge ' + tipo.badge;
        bTipo.innerHTML = `<i class="bi ${tipo.icono}"></i> ${tipo.nombre}`;

        const bEst = document.getElementById('detEstado');
        bEst.className = 'badge ' + est.badge;
        bEst.textContent = est.nombre;

        const bPri = document.getElementById('detPrioridad');
        bPri.className = 'badge ' + pri.badge;
        bPri.textContent = pri.nombre;

        document.getElementById('detHistorico').classList.toggle('d-none', !p.es_historico);
        document.getElementById('detVencido').classList.toggle('d-none', !p.vencido);

        document.getElementById('detFecha').textContent =
            formatoFecha(p.fecha) + (p.hora ? ' · ' + p.hora : '');

        document.getElementById('detEquipo').textContent =
            p.equipo + (p.hostname ? ` (${p.hostname})` : '');
        document.getElementById('detEquipoInfo').textContent =
            [TIPOS_EQUIPO[p.tipo_equipo] || p.tipo_equipo, p.marca_modelo].filter(Boolean).join(' · ');

        document.getElementById('detUsuario').textContent     = p.usuario;
        document.getElementById('detDepto').textContent       = p.departamento;
        document.getElementById('detDescripcion').textContent = p.descripcion || '—';
        document.getElementById('detVerLink').href            = p.url_detalle;

        modalDetalle.show();
    }
});
</script>

</body>
</html>