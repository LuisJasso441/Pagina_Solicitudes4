<?php
/**
 * Dashboard - Reservación de Sala de Juntas
 * Ubicación: dashboard/reservaciones/reservaciones_sala.php
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$msg_success = $_SESSION['success'] ?? null;
$msg_error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$filtros = [
    'estado' => $_GET['estado'] ?? 'activa',
    'departamento_id' => $_GET['departamento_id'] ?? '',
    'lugar' => $_GET['lugar'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? '',
    'pagina' => $_GET['pagina'] ?? 1,
    'por_pagina' => 15
];

$resultado = obtener_reservaciones_lista($filtros);
$reservaciones = $resultado['reservaciones'];
$total_paginas = $resultado['total_paginas'];
$pagina_actual = $resultado['pagina'];
$total_registros = $resultado['total'];

$departamentos = obtener_departamentos_activos();
$lugares = obtener_lugares_disponibles();
$usuario_id = $_SESSION['usuario_id'];
$departamento_usuario = $_SESSION['departamento'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Juntas - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/reservaciones.css">
</head>
<body>
    <div class="dashboard-container">
        <?php 
        $depto = strtolower(trim($_SESSION['departamento'] ?? ''));
        if ($depto === 'sistemas') { include __DIR__ . '/../../includes/sidebar/sidebar_ti.php'; }
        elseif ($depto === 'mantenimiento') { include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php'; }
        elseif (function_exists('es_usuario_epp') && es_usuario_epp()) { include __DIR__ . '/../../includes/sidebar/sidebar_inventario.php'; }
        elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) { include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; }
        else { include __DIR__ . '/../../includes/sidebar/sidebar_normal.php'; }
        ?>
        
        <main class="main-content">
            <div class="container-fluid p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Reservar Sala</h4>
                        <small class="text-muted">Reservaciones y calendario</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/nueva_reservacion.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Nueva Reservación
                    </a>
                </div>
                
                <?php if ($msg_success): ?>
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($msg_success); ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($msg_error): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($msg_error); ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- CALENDARIO -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body p-2">
                        <div id="calendario"></div>
                    </div>
                </div>
                
                <!-- TABLA -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0"><i class="bi bi-list-ul me-1"></i> Reservaciones registradas</h6>
                            <span class="badge bg-secondary"><?php echo $total_registros; ?> registros</span>
                        </div>
                        <form method="GET" class="row g-2 mt-2">
                            <div class="col-md-2">
                                <select name="lugar" class="form-select form-select-sm">
                                    <option value="">Todos los lugares</option>
                                    <?php foreach ($lugares as $cod => $nom): ?>
                                    <option value="<?php echo $cod; ?>" <?php echo ($filtros['lugar'] === $cod) ? 'selected' : ''; ?>><?php echo $nom; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="departamento_id" class="form-select form-select-sm">
                                    <option value="">Todos los deptos</option>
                                    <?php foreach ($departamentos as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($filtros['departamento_id'] == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="activa" <?php echo $filtros['estado'] === 'activa' ? 'selected' : ''; ?>>Activas</option>
                                    <option value="cancelada" <?php echo $filtros['estado'] === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                                    <option value="" <?php echo $filtros['estado'] === '' ? 'selected' : ''; ?>>Todas</option>
                                </select>
                            </div>
                            <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo $filtros['fecha_desde']; ?>"></div>
                            <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo $filtros['fecha_hasta']; ?>"></div>
                            <div class="col-md-2"><input type="text" name="busqueda" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtros['busqueda']); ?>" placeholder="Buscar..."></div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                                <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                            </div>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px"></th>
                                        <th>Fecha</th>
                                        <th>Horario</th>
                                        <th>Solicitante</th>
                                        <th>Departamento</th>
                                        <th>Lugar</th>
                                        <th>Motivo</th>
                                        <th>Estado</th>
                                        <th style="width:100px">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reservaciones)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No hay reservaciones</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($reservaciones as $r): ?>
                                    <tr>
                                        <td><span class="d-inline-block rounded-circle" style="width:12px;height:12px;background-color:<?php echo $r['color_hex']; ?>"></span></td>
                                        <td><?php echo date('d/m/Y', strtotime($r['fecha_reservacion'])); ?></td>
                                        <td><?php echo formatear_hora_12h($r['hora_inicio']); ?> - <?php echo formatear_hora_12h($r['hora_fin']); ?></td>
                                        <td><?php echo htmlspecialchars($r['solicitante_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($r['departamento_nombre'] ?? ucfirst($r['departamento'])); ?></td>
                                        <td><small><?php echo obtener_nombre_lugar($r['lugar'] ?? 'sala_juntas'); ?></small></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($r['motivo'] ?? '—'); ?></small></td>
                                        <td>
                                            <?php if ($r['estado'] === 'activa'): ?><span class="badge bg-success">Activa</span>
                                            <?php else: ?><span class="badge bg-danger">Cancelada</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['estado'] === 'activa' && puede_modificar_reservacion($r, $usuario_id, $departamento_usuario)): ?>
                                            <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/editar_reservacion.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-primary btn-sm py-0 px-1" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-outline-danger btn-sm py-0 px-1 btn-cancelar-reservacion" data-id="<?php echo $r['id']; ?>" data-solicitante="<?php echo htmlspecialchars($r['solicitante_nombre']); ?>" title="Cancelar"><i class="bi bi-x-circle"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($total_paginas > 1): ?>
                        <nav class="d-flex justify-content-center py-2">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Cancelar -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-x-circle text-danger me-1"></i> Cancelar Reservación</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" style="font-size:0.85rem">¿Cancelar la reservación de <strong id="cancelar-nombre"></strong>?</p>
                    <textarea id="cancelar-motivo" class="form-control form-control-sm" rows="2" placeholder="Motivo de cancelación (opcional)"></textarea>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger btn-sm" id="btn-confirmar-cancelar">Sí, cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <?php if (file_exists(__DIR__ . '/../../assets/js/sidebar.js')): ?>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar.js"></script>
    <?php endif; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const URL_BASE = '<?php echo URL_BASE; ?>';
        const USUARIO_ID = <?php echo (int)$usuario_id; ?>;
        const DEPTO_USUARIO = '<?php echo addslashes($departamento_usuario); ?>';
        
        // Cerrar popovers
        function cerrarPopovers() {
            document.querySelectorAll('.rsj-popover').forEach(el => el.remove());
        }
        
        const calendar = new FullCalendar.Calendar(document.getElementById('calendario'), {
            initialView: 'timeGridWeek',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridDay,timeGridWeek,dayGridMonth' },
            slotMinTime: '08:00:00', slotMaxTime: '18:00:00', slotDuration: '00:30:00',
            locale: 'es',
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
            allDaySlot: false, nowIndicator: true, height: 'auto', expandRows: true, dayMaxEvents: true,
            slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
            
            events: function(info, successCallback, failureCallback) {
                const inicio = info.startStr.split('T')[0];
                const fin = info.endStr.split('T')[0];
                fetch(`${URL_BASE}api/reservaciones/obtener_eventos.php?inicio=${inicio}&fin=${fin}`)
                    .then(r => r.json()).then(data => successCallback(data)).catch(e => failureCallback(e));
            },
            
            // Contenido dentro del bloque
            eventContent: function(arg) {
                const props = arg.event.extendedProps;
                const html = document.createElement('div');
                html.className = 'rsj-event-content';
                html.innerHTML = `
                    <div class="rsj-event-time">${arg.timeText || ''}</div>
                    <div class="rsj-event-name">${props.solicitante} - ${props.departamento}</div>
                    <div class="rsj-event-lugar">${props.lugar_corto || props.lugar}</div>
                    ${props.motivo ? '<div class="rsj-event-motivo">' + props.motivo + '</div>' : ''}
                `;
                return { domNodes: [html] };
            },
            
            // Click en evento → popover con info completa + botones
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                info.jsEvent.stopPropagation();
                cerrarPopovers();
                
                const props = info.event.extendedProps;
                const eventId = info.event.id;
                const puedeModificar = (parseInt(props.usuario_id) === USUARIO_ID || DEPTO_USUARIO === 'sistemas');
                
                let botonesHTML = '';
                if (puedeModificar) {
                    botonesHTML = `<div class="rsj-popover-actions">
                        <a href="${URL_BASE}dashboard/reservaciones/editar_reservacion.php?id=${eventId}" class="btn btn-outline-primary btn-sm py-0 px-2"><i class="bi bi-pencil"></i> Editar</a>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2 btn-cancelar-popover" data-id="${eventId}" data-solicitante="${props.solicitante}"><i class="bi bi-x-circle"></i> Cancelar</button>
                    </div>`;
                }
                
                const popover = document.createElement('div');
                popover.className = 'rsj-popover shadow';
                popover.innerHTML = `
                    <div class="rsj-popover-header" style="background-color:${info.event.backgroundColor}; color:${info.event.textColor}">
                        <strong>${props.solicitante}</strong>
                        <button class="rsj-popover-close">&times;</button>
                    </div>
                    <div class="rsj-popover-body">
                        <div><i class="bi bi-building me-1"></i> ${props.departamento}</div>
                        <div><i class="bi bi-geo-alt me-1"></i> ${props.lugar}</div>
                        <div><i class="bi bi-clock me-1"></i> ${props.hora_inicio} - ${props.hora_fin}</div>
                        ${props.motivo ? '<div><i class="bi bi-chat-text me-1"></i> ' + props.motivo + '</div>' : ''}
                        ${botonesHTML}
                    </div>`;
                document.body.appendChild(popover);
                
                // Posicionar
                const rect = info.el.getBoundingClientRect();
                popover.style.top = (rect.top + window.scrollY - 10) + 'px';
                popover.style.left = (rect.right + 10) + 'px';
                const popRect = popover.getBoundingClientRect();
                if (popRect.right > window.innerWidth) popover.style.left = (rect.left - popRect.width - 10) + 'px';
                if (popRect.bottom > window.innerHeight) popover.style.top = (rect.bottom + window.scrollY - popRect.height) + 'px';
                
                // Cerrar
                popover.querySelector('.rsj-popover-close').addEventListener('click', () => popover.remove());
                const btnCP = popover.querySelector('.btn-cancelar-popover');
                if (btnCP) btnCP.addEventListener('click', function() { popover.remove(); abrirModalCancelar(this.dataset.id, this.dataset.solicitante); });
                
                setTimeout(() => {
                    document.addEventListener('click', function cerrar(e) {
                        if (!popover.contains(e.target) && !info.el.contains(e.target)) {
                            popover.remove(); document.removeEventListener('click', cerrar);
                        }
                    });
                }, 100);
            },
            
            // Click en espacio vacío → nueva reservación
            dateClick: function(info) {
                const fecha = info.dateStr.split('T')[0];
                const hora = info.dateStr.split('T')[1] || '';
                let url = `${URL_BASE}dashboard/reservaciones/nueva_reservacion.php?fecha=${fecha}`;
                if (hora) url += `&hora=${hora.substring(0,5)}`;
                window.location.href = url;
            }
        });
        calendar.render();
        
        // Cerrar popovers al scroll
        document.addEventListener('scroll', cerrarPopovers, true);
        
        // Modal cancelar
        let cancelarId = null;
        function abrirModalCancelar(id, nombre) {
            cancelarId = id;
            document.getElementById('cancelar-nombre').textContent = nombre;
            document.getElementById('cancelar-motivo').value = '';
            new bootstrap.Modal(document.getElementById('modalCancelar')).show();
        }
        document.querySelectorAll('.btn-cancelar-reservacion').forEach(btn => {
            btn.addEventListener('click', function() { abrirModalCancelar(this.dataset.id, this.dataset.solicitante); });
        });
        document.getElementById('btn-confirmar-cancelar').addEventListener('click', function() {
            if (!cancelarId) return;
            this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            fetch(`${URL_BASE}api/reservaciones/cancelar_reservacion.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cancelarId, motivo: document.getElementById('cancelar-motivo').value })
            }).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else { alert(data.error || 'Error'); this.disabled = false; this.textContent = 'Sí, cancelar'; }
            }).catch(() => { alert('Error de conexión'); this.disabled = false; this.textContent = 'Sí, cancelar'; });
        });
    });
    </script>
</body>
</html>