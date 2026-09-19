<?php
/**
 * Vueltas — Vista principal con calendario semanal
 * dashboard/salidas_envases/vueltas/vueltas.php
 *
 * Permisos:
 *   - Logística: CRUD completo (agregar/eliminar vueltas por día).
 *   - Almacén de Residuos y Ventas: solo lectura (calendario navegable, modal sin acciones).
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/unidades_transporte_funciones.php';

// ---- Autenticación ----
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ---- Autorización ----
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$puede_editar = es_logistica();
$unidades     = obtener_unidades_transporte(false); // solo activas para el selector
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vueltas - Verden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <style>
        #calendario {
            background: #fff;
            border-radius: 8px;
            padding: 1rem;
        }
        .fc-daygrid-day-frame { min-height: 90px; }
        .fc-event { cursor: pointer; font-size: 0.85rem; padding: 2px 4px; }
        .fc-event:hover { opacity: 0.9; }

        .vuelta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .vuelta-item:last-child { border-bottom: none; }
        .vuelta-numero {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #e3f2fd;
            color: #1976d2;
            font-weight: 700;
            margin-right: 0.75rem;
        }
        .vuelta-info { flex: 1; }
        .vuelta-notas { color: #666; font-size: 0.85rem; }
        .empty-mini { text-align: center; color: #999; padding: 1rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <!-- Encabezado -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-arrow-repeat"></i> Vueltas</h1>
                    <small class="text-muted">
                        <?php echo $puede_editar
                            ? 'Programa vueltas por unidad y día. Click en un día del calendario para gestionar.'
                            : 'Consulta las vueltas programadas por unidad (solo lectura).'; ?>
                    </small>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Filtrar por unidad:</label>
                    <select class="form-select form-select-sm" id="filtroUnidad" style="min-width: 200px;">
                        <option value="">Todas las unidades</option>
                        <?php foreach ($unidades as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($unidades)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No hay unidades de transporte activas. Registra unidades en
                <strong>Unidades de Transporte</strong> antes de programar vueltas.
            </div>
            <?php else: ?>

            <!-- Calendario -->
            <div id="calendario"></div>

            <?php endif; ?>
        </div>
    </main>

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
                        <label class="form-label fw-bold">Unidad:</label>
                        <select class="form-select" id="modalUnidad">
                            <option value="">Seleccione unidad…</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lista de vueltas actuales -->
                    <div class="card mb-3" id="cardListaVueltas" style="display:none;">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <strong>Vueltas programadas</strong>
                            <span class="badge bg-primary" id="badgeTotalVueltas">0</span>
                        </div>
                        <div id="listaVueltas">
                            <div class="empty-mini">Selecciona una unidad…</div>
                        </div>
                    </div>

                    <?php if ($puede_editar): ?>
                    <!-- Agregar vueltas -->
                    <div class="card border-primary" id="cardAgregar" style="display:none;">
                        <div class="card-header bg-primary bg-opacity-10 border-primary">
                            <strong><i class="bi bi-plus-circle"></i> Agregar vueltas</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small">Cantidad:</label>
                                    <input type="number" class="form-control" id="inputCantidad"
                                           min="1" max="20" value="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Notas (opcional):</label>
                                    <input type="text" class="form-control" id="inputNotas"
                                           maxlength="500" placeholder="Opcional">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-primary w-100" id="btnAgregarVueltas">
                                        <i class="bi bi-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Las vueltas se numeran automáticamente comenzando desde el siguiente número disponible.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/es.global.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <?php if (!empty($unidades)): ?>
    <script>
    (function () {
        const URL_BASE      = <?php echo json_encode(URL_BASE); ?>;
        const puedeEditar   = <?php echo $puede_editar ? 'true' : 'false'; ?>;

        let calendario      = null;
        let modalVueltas    = null;
        let fechaModal      = null; // YYYY-MM-DD del modal abierto

        // ---- FullCalendar ----
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
                firstDay: 1, // lunes
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
                    // Abrir modal preseleccionando la unidad del evento
                    const fecha = info.event.extendedProps.fecha || info.event.startStr;
                    const unidadId = info.event.extendedProps.unidad_id;
                    abrirModalDelDia(fecha, unidadId);
                }
            });
            calendario.render();

            // Refrescar al cambiar filtro
            document.getElementById('filtroUnidad').addEventListener('change', () => {
                calendario.refetchEvents();
            });

            modalVueltas = new bootstrap.Modal(document.getElementById('modalVueltas'));
        });

        // ---- Modal ----
        function abrirModalDelDia(fecha, unidadIdPreseleccion = null) {
            fechaModal = fecha;
            // Formatear fecha para el label (usar zona local del navegador)
            const partes = fecha.split('-');
            const d = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
            const opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            document.getElementById('modalFechaLabel').textContent = d.toLocaleDateString('es-MX', opts);

            // Reset UI
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

        // Cambio de unidad en el modal
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
            lista.innerHTML = '<div class="empty-mini"><i class="bi bi-hourglass-split"></i> Cargando…</div>';

            const params = new URLSearchParams({ unidad_id: unidadId, fecha: fechaModal });
            fetch(URL_BASE + 'dashboard/salidas_envases/api/vueltas_por_dia.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.error || 'Error');
                    renderListaVueltas(data.vueltas);
                })
                .catch(err => {
                    console.error(err);
                    lista.innerHTML = '<div class="empty-mini text-danger">Error al cargar las vueltas.</div>';
                });
        }

        function renderListaVueltas(vueltas) {
            const lista = document.getElementById('listaVueltas');
            const badge = document.getElementById('badgeTotalVueltas');
            badge.textContent = vueltas.length;
            if (vueltas.length === 0) {
                lista.innerHTML = '<div class="empty-mini">Sin vueltas programadas para esta unidad en esta fecha.</div>';
                return;
            }
            let html = '';
            vueltas.forEach(v => {
                html += '<div class="vuelta-item">';
                html += '  <div class="d-flex align-items-center">';
                html += '    <span class="vuelta-numero">' + v.numero + '</span>';
                html += '    <div class="vuelta-info">';
                html += '      <strong>Vuelta ' + v.numero + '</strong>';
                if (v.notas) {
                    html += '      <div class="vuelta-notas">' + escapeHtml(v.notas) + '</div>';
                }
                html += '    </div>';
                html += '  </div>';
                if (puedeEditar) {
                    html += '  <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarVuelta(' + v.id + ', ' + v.numero + ')" title="Eliminar">';
                    html += '    <i class="bi bi-trash"></i>';
                    html += '  </button>';
                }
                html += '</div>';
            });
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
                        // Limpiar campos, recargar lista y refrescar calendario
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