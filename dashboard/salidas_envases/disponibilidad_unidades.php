<?php
/**
 * Calendario de Disponibilidad de Unidades de Transporte
 * Módulo SEC — Salidas de Envases para Clientes
 *
 * Ubicación: dashboard/salidas_envases/disponibilidad_unidades.php
 *
 * CAMBIO: cada slot es ahora un rango (hora_inicio + hora_fin).
 *
 * Logística: gestión completa.
 * Ventas / Almacén de Residuos: sólo lectura.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/unidades_transporte_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/disponibilidad_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$puede_gestionar = ($dept === 'logistica');
$puede_ver = in_array($dept, ['logistica', 'ventas', 'almacen_residuos']);

if (!$puede_ver) {
    establecer_alerta('error', 'No tienes acceso al calendario de Disponibilidad.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id     = $_SESSION['usuario_id'];

$unidades = obtener_unidades_transporte(true);

$mensajes = [
    'creada'          => ['success', 'Disponibilidad registrada correctamente.'],
    'actualizada'     => ['success', 'Disponibilidad actualizada correctamente.'],
    'eliminada'       => ['warning', 'Disponibilidad eliminada.'],
    'error'           => ['danger',  'Ocurrió un error al procesar la solicitud.'],
    'error_validacion'=> ['danger',  'Hay errores de validación. Revisa los campos.'],
];
$msg_flash = $_GET['msg'] ?? null;
$errores_flash = $_SESSION['disponibilidad_errores'] ?? [];
unset($_SESSION['disponibilidad_errores']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disponibilidad de Unidades | <?php echo NOMBRE_SISTEMA; ?></title>
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
        #calendar { max-width: 100%; }
        .fc-event {
            cursor: pointer;
            font-size: 0.78rem;
            padding: 2px 4px;
            border: none;
        }
        .fc-event-title { font-weight: 600; }
        .slot-badge {
            display: inline-block;
            padding: 4px 10px;
            margin: 2px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        .slot-libre   { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .slot-ocupado { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .leyenda-color {
            display: inline-block; width: 14px; height: 14px;
            border-radius: 3px; vertical-align: middle; margin-right: 6px;
        }
        .slot-input-row {
            display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;
            flex-wrap: wrap;
        }
        .slot-input-row .slot-times {
            display: flex; gap: 0.5rem; flex: 1; min-width: 280px;
        }
        .slot-input-row .slot-times > div { flex: 1; }
        .slot-input-row .slot-times label {
            font-size: 0.7rem; color: #6c757d; margin-bottom: 2px; display: block;
        }
        .modo-lectura-banner {
            background: #fff8e1; border-left: 4px solid #f59e0b;
            padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;
            font-size: 0.85rem; color: #856404;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        if (in_array($dept, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../includes/sidebar/sidebar_sec.php';
        } elseif ($dept === 'ventas') {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-calendar-event"></i> Disponibilidad de Unidades</h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                <?php if ($puede_gestionar): ?>
                                    Registra los horarios en que las unidades están disponibles para Salidas de Envases.
                                <?php else: ?>
                                    Consulta el calendario de unidades de transporte disponibles.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($puede_gestionar): ?>
                            <div>
                                <button type="button" class="btn btn-primary"
                                        data-bs-toggle="modal" data-bs-target="#modalDisponibilidad"
                                        onclick="abrirModalNueva()">
                                    <i class="bi bi-plus-circle me-1"></i> Nueva Disponibilidad
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$puede_gestionar): ?>
                    <div class="modo-lectura-banner">
                        <i class="bi bi-eye"></i> <strong>Modo lectura.</strong>
                        Sólo Logística puede registrar o modificar bloques de disponibilidad.
                    </div>
                <?php endif; ?>

                <?php if ($msg_flash && isset($mensajes[$msg_flash])): ?>
                    <div class="alert alert-<?php echo $mensajes[$msg_flash][0]; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensajes[$msg_flash][1]); ?>
                        <?php if (!empty($errores_flash)): ?>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errores_flash as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($unidades)): ?>
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <small class="text-muted me-2">Unidades:</small>
                            <?php foreach ($unidades as $u): ?>
                                <span class="me-3" style="font-size: 0.85rem;">
                                    <span class="leyenda-color" style="background: <?php echo color_para_unidad($u['id']); ?>"></span>
                                    <?php echo htmlspecialchars($u['nombre']); ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($u['placas']); ?>)</small>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: Crear / Editar Disponibilidad (sólo Logística)                  -->
    <!-- ===================================================================== -->
    <?php if ($puede_gestionar): ?>
    <div class="modal fade" id="modalDisponibilidad" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/guardar_disponibilidad.php" method="POST" id="formDisponibilidad" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalDispTitulo">
                            <i class="bi bi-calendar-plus"></i> Nueva Disponibilidad
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="dispAccion" value="crear">
                        <input type="hidden" name="id" id="dispId" value="">

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Unidad de Transporte <span class="text-danger">*</span></label>
                                <select name="unidad_transporte_id" id="dispUnidad" class="form-select" required>
                                    <option value="">— Seleccionar —</option>
                                    <?php foreach ($unidades as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>">
                                            <?php echo htmlspecialchars($u['nombre']); ?>
                                            (<?php echo htmlspecialchars($u['placas']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" name="fecha" id="dispFecha" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hora inicio de ruta <span class="text-danger">*</span></label>
                                <input type="time" name="hora_inicio_ruta" id="dispHoraInicio" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hora término de ruta <span class="text-danger">*</span></label>
                                <input type="time" name="hora_termino_ruta" id="dispHoraTermino" class="form-control" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-2"><i class="bi bi-clock me-1"></i> Espacios disponibles</h6>
                        <p class="text-muted small mb-3">
                            Cada espacio es un <strong>rango horario</strong> dentro de la ruta en que la unidad puede recoger envases.
                        </p>

                        <div id="slotsContainer">
                            <!-- Slots se agregan dinámicamente -->
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="agregarSlot()">
                            <i class="bi bi-plus-circle"></i> Agregar espacio
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> <span id="dispBtnGuardar">Crear disponibilidad</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL: Detalle de Disponibilidad                                       -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle"></i> Detalle del bloque
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleBody"></div>
                <div class="modal-footer">
                    <?php if ($puede_gestionar): ?>
                        <button type="button" class="btn btn-outline-danger me-auto" id="btnEliminarDisp" onclick="eliminarDisponibilidadActual()">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                        <button type="button" class="btn btn-primary" id="btnEditarDisp" onclick="editarDisponibilidadActual()">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($puede_gestionar): ?>
    <form id="formEliminarDisp" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/eliminar_disponibilidad.php" method="POST" style="display:none;">
        <input type="hidden" name="id" id="eliminarDispId" value="">
    </form>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/es.global.min.js'></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        const URL_BASE = <?php echo json_encode(URL_BASE); ?>;
        const PUEDE_GESTIONAR = <?php echo $puede_gestionar ? 'true' : 'false'; ?>;
        let calendar;
        let disponibilidadSeleccionada = null;
        let cacheDisponibilidades = {};

        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'es',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                height: 'auto',
                events: function(info, successCallback, failureCallback) {
                    fetch(URL_BASE + 'dashboard/salidas_envases/api/eventos_disponibilidad.php' +
                          '?desde=' + info.startStr.substring(0, 10) +
                          '&hasta=' + info.endStr.substring(0, 10))
                        .then(r => r.json())
                        .then(data => {
                            cacheDisponibilidades = {};
                            data.forEach(d => { cacheDisponibilidades[d.id] = d; });
                            successCallback(data.map(d => ({
                                id: d.id,
                                title: d.unidad_nombre + ' • ' + d.slots_libres + '/' + d.total_slots + ' libres',
                                start: d.fecha + 'T' + d.hora_inicio_ruta,
                                end:   d.fecha + 'T' + d.hora_termino_ruta,
                                backgroundColor: d.color,
                                borderColor: d.color
                            })));
                        })
                        .catch(failureCallback);
                },
                eventClick: function(info) {
                    abrirDetalle(parseInt(info.event.id));
                }
            });
            calendar.render();
        });

        function abrirDetalle(id) {
            const d = cacheDisponibilidades[id];
            if (!d) return;
            disponibilidadSeleccionada = d;

            let html = '<div class="row mb-3">';
            html += '<div class="col-md-6"><strong>Unidad:</strong> ' + escaparHtml(d.unidad_nombre) + '</div>';
            html += '<div class="col-md-6"><strong>Placas:</strong> <span style="font-family: monospace;">' + escaparHtml(d.unidad_placas) + '</span></div>';
            html += '<div class="col-md-4"><strong>Fecha:</strong> ' + escaparHtml(d.fecha) + '</div>';
            html += '<div class="col-md-4"><strong>Inicio ruta:</strong> ' + escaparHtml(d.hora_inicio_ruta.substring(0,5)) + '</div>';
            html += '<div class="col-md-4"><strong>Término ruta:</strong> ' + escaparHtml(d.hora_termino_ruta.substring(0,5)) + '</div>';
            html += '</div>';

            html += '<hr><h6><i class="bi bi-clock"></i> Espacios disponibles (' + d.slots_libres + ' libres / ' + d.slots_ocupados + ' ocupados):</h6>';
            html += '<div>';
            d.slots.forEach(s => {
                const claseSlot = s.ocupado == 1 ? 'slot-ocupado' : 'slot-libre';
                const titulo = s.ocupado == 1
                    ? 'Ocupado por: ' + (s.sec_folio || 'SEC #' + s.sec_id)
                    : 'Libre';
                html += '<span class="slot-badge ' + claseSlot + '" title="' + escaparHtml(titulo) + '">' +
                        s.hora_inicio.substring(0,5) + ' - ' + s.hora_fin.substring(0,5) +
                        (s.ocupado == 1 ? ' <i class="bi bi-lock-fill"></i>' : '') +
                        '</span>';
            });
            html += '</div>';

            document.getElementById('detalleBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        }

        <?php if ($puede_gestionar): ?>
        function abrirModalNueva() {
            document.getElementById('formDisponibilidad').reset();
            document.getElementById('dispAccion').value = 'crear';
            document.getElementById('dispId').value = '';
            document.getElementById('modalDispTitulo').innerHTML = '<i class="bi bi-calendar-plus"></i> Nueva Disponibilidad';
            document.getElementById('dispBtnGuardar').textContent = 'Crear disponibilidad';
            document.getElementById('slotsContainer').innerHTML = '';
            agregarSlot();
        }

        function editarDisponibilidadActual() {
            const d = disponibilidadSeleccionada;
            if (!d) return;
            bootstrap.Modal.getInstance(document.getElementById('modalDetalle')).hide();

            document.getElementById('formDisponibilidad').reset();
            document.getElementById('dispAccion').value = 'editar';
            document.getElementById('dispId').value = d.id;
            document.getElementById('dispUnidad').value = d.unidad_transporte_id;
            document.getElementById('dispFecha').value = d.fecha;
            document.getElementById('dispHoraInicio').value = d.hora_inicio_ruta.substring(0,5);
            document.getElementById('dispHoraTermino').value = d.hora_termino_ruta.substring(0,5);
            document.getElementById('modalDispTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Disponibilidad';
            document.getElementById('dispBtnGuardar').textContent = 'Guardar cambios';

            document.getElementById('slotsContainer').innerHTML = '';
            d.slots.forEach(s => agregarSlot(
                s.hora_inicio.substring(0,5),
                s.hora_fin.substring(0,5),
                s.ocupado == 1
            ));

            new bootstrap.Modal(document.getElementById('modalDisponibilidad')).show();
        }

        function eliminarDisponibilidadActual() {
            const d = disponibilidadSeleccionada;
            if (!d) return;
            if (d.slots_ocupados > 0) {
                alert('No se puede eliminar: hay ' + d.slots_ocupados + ' slot(s) ocupado(s). Cancela primero esas SEC.');
                return;
            }
            if (confirm('¿Eliminar la disponibilidad de ' + d.unidad_nombre + ' del ' + d.fecha + '?')) {
                document.getElementById('eliminarDispId').value = d.id;
                document.getElementById('formEliminarDisp').submit();
            }
        }

        function agregarSlot(valorInicio = '', valorFin = '', ocupado = false) {
            const cont = document.getElementById('slotsContainer');
            const row = document.createElement('div');
            row.className = 'slot-input-row';
            row.innerHTML = `
                <div class="slot-times">
                    <div>
                        <label>Hora inicio</label>
                        <input type="time" name="slots_inicio[]" class="form-control" value="${valorInicio}" required ${ocupado ? 'readonly' : ''}>
                    </div>
                    <div>
                        <label>Hora final</label>
                        <input type="time" name="slots_fin[]" class="form-control" value="${valorFin}" required ${ocupado ? 'readonly' : ''}>
                    </div>
                </div>
                ${ocupado
                    ? '<span class="badge bg-danger" title="Slot ocupado por SEC"><i class="bi bi-lock-fill"></i> Ocupado</span>'
                    : '<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.slot-input-row\').remove()"><i class="bi bi-x"></i></button>'
                }
            `;
            cont.appendChild(row);
        }
        <?php endif; ?>

        function escaparHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>