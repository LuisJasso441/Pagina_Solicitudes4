<?php
/**
 * Nueva SEC — Crear Salida de Envases para Clientes
 *
 * Ubicación: dashboard/salidas_envases/nueva_sec.php
 *
 * Sólo Logística y Ventas (con permiso creador=1).
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/unidades_transporte_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_crear_sec()) {
    establecer_alerta('error', 'No tienes permisos para crear Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id     = $_SESSION['usuario_id'];
$dept           = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

// Datos para selectores
$unidades = obtener_unidades_transporte(true);

// Errores de validación si vienen de un intento previo
$errores_flash = $_SESSION['sec_errores'] ?? [];
$datos_previos = $_SESSION['sec_datos_previos'] ?? [];
unset($_SESSION['sec_errores'], $_SESSION['sec_datos_previos']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva SEC | <?php echo NOMBRE_SISTEMA; ?></title>
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
        .linea-card {
            background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;
            padding: 1rem; margin-bottom: 0.75rem;
        }
        .linea-card .linea-numero {
            background: #14b8a6; color: white; width: 30px; height: 30px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 600;
        }
        .unidad-selector {
            background: white; border: 1px solid #ced4da; border-radius: 6px;
            padding: 0.5rem 0.75rem; cursor: pointer; min-height: 50px;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.2s;
        }
        .unidad-selector:hover { border-color: #14b8a6; }
        .unidad-selector.seleccionada { border-color: #14b8a6; background: #f0fdfa; }
        .unidad-selector .placeholder { color: #6c757d; font-style: italic; }
        .firma-canvas-wrapper {
            border: 2px dashed #ced4da; border-radius: 8px;
            background: #fff; position: relative; overflow: hidden;
        }
        .firma-canvas-wrapper canvas { display: block; width: 100%; height: 180px; }
        .firma-actions { position: absolute; top: 8px; right: 8px; }
        .unidad-item {
            border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 8px;
            cursor: pointer; transition: all 0.2s;
        }
        .unidad-item:hover { border-color: #14b8a6; background: #f0fdfa; }
        .unidad-item .nombre { font-weight: 600; }
        .capacidad-pill {
            display: inline-block; background: #e0f2fe; color: #075985;
            padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin: 2px;
            font-family: 'Courier New', monospace;
        }
        .slot-libre-pill {
            display: inline-block; background: #d1fae5; color: #065f46;
            border: 1px solid #10b981; padding: 4px 10px; border-radius: 10px;
            margin: 3px; cursor: pointer; font-size: 0.78rem; font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        .slot-libre-pill:hover { background: #a7f3d0; }
        .placa-cell { font-family: 'Courier New', monospace; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        if ($dept === 'logistica') {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-plus-square"></i> Nueva Salida de Envases</h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Creando como <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> ·
                                Departamento: <strong><?php echo htmlspecialchars(ucfirst($dept)); ?></strong>
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/salidas_envases.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </div>

                <?php if (!empty($errores_flash)): ?>
                    <div class="alert alert-danger">
                        <strong>No se pudo crear la SEC:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores_flash as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="formNuevaSec" method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/guardar_sec.php" novalidate>

                    <!-- Fecha del documento -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Fecha del documento <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_documento" id="fechaDocumento" class="form-control"
                                           value="<?php echo htmlspecialchars($datos_previos['fecha_documento'] ?? date('Y-m-d')); ?>" required>
                                    <small class="text-muted">Esto determina los slots de unidades disponibles.</small>
                                </div>
                                <div class="col-md-8 text-end">
                                    <div class="alert alert-info mb-0 py-2" style="font-size: 0.85rem;">
                                        <i class="bi bi-info-circle"></i>
                                        Al cambiar la fecha, se reiniciarán las unidades y horarios seleccionados.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Líneas -->
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Líneas de envases</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="agregarLinea()">
                                <i class="bi bi-plus-circle"></i> Agregar línea
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="lineasContainer"></div>
                            <div id="lineasVacio" class="text-center text-muted py-3" style="display:none;">
                                <i class="bi bi-inbox" style="font-size: 1.5rem;"></i>
                                <p class="mb-0">Agrega al menos una línea para continuar.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Solicita -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Solicita</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                    <input type="text" name="solicita_nombre" id="solicitaNombre" class="form-control"
                                           value="<?php echo htmlspecialchars($datos_previos['solicita_nombre'] ?? $nombre_usuario); ?>"
                                           maxlength="255" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Firma <span class="text-danger">*</span></label>
                                    <div class="firma-canvas-wrapper">
                                        <div id="firmaCanvas"></div>
                                        <div class="firma-actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFirma()" title="Limpiar firma">
                                                <i class="bi bi-eraser"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="solicita_firma" id="solicitaFirma">
                                    <small class="text-muted">Dibuja tu firma en el recuadro.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/salidas_envases.php" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="btnGuardarSec">
                            <i class="bi bi-check-circle"></i> Crear y enviar SEC
                        </button>
                    </div>

                </form>

            </div>
        </main>

    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: Selector de Unidad + Slot                                       -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalUnidadSlot" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Seleccionar Unidad y Horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalLineaIndex" value="">
                    <p class="text-muted small">Selecciona una unidad y luego un horario libre.</p>
                    <div id="listadoUnidades">
                        <!-- Cargado por JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery → jSignature → Bootstrap → sidebar (orden estándar del proyecto) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
    const URL_BASE = <?php echo json_encode(URL_BASE); ?>;
    const UNIDADES = <?php echo json_encode($unidades, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let lineaCounter = 0;
    let lineas = []; // [{cantidad, tipo_envase, slot_id, unidad_id, unidad_label, slot_label}]
    let firma = null;

    document.addEventListener('DOMContentLoaded', function() {
        // jSignature
        firma = $('#firmaCanvas').jSignature({ width: '100%', height: 180, lineWidth: 2 });

        // Cambio de fecha: limpiar selecciones
        document.getElementById('fechaDocumento').addEventListener('change', function() {
            lineas.forEach(l => { l.slot_id = null; l.unidad_id = null; l.unidad_label = null; l.slot_label = null; });
            renderLineas();
        });

        agregarLinea();

        // Submit
        document.getElementById('formNuevaSec').addEventListener('submit', function(e) {
            e.preventDefault();
            enviarFormulario();
        });
    });

    function agregarLinea() {
        lineas.push({
            id: ++lineaCounter,
            cantidad: 1,
            tipo_envase: '',
            slot_id: null,
            unidad_id: null,
            unidad_label: null,
            slot_label: null
        });
        renderLineas();
    }

    function eliminarLinea(idx) {
        lineas.splice(idx, 1);
        renderLineas();
    }

    function renderLineas() {
        const cont = document.getElementById('lineasContainer');
        const vacio = document.getElementById('lineasVacio');
        cont.innerHTML = '';

        if (lineas.length === 0) {
            vacio.style.display = 'block';
            return;
        }
        vacio.style.display = 'none';

        lineas.forEach((l, idx) => {
            const card = document.createElement('div');
            card.className = 'linea-card';
            card.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="linea-numero">${idx + 1}</div>
                    <strong>Línea ${idx + 1}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="eliminarLinea(${idx})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" class="form-control" value="${l.cantidad}"
                               onchange="lineas[${idx}].cantidad = parseInt(this.value)||0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tipo envase <span class="text-danger">*</span></label>
                        <select class="form-select" onchange="lineas[${idx}].tipo_envase = this.value">
                            <option value="">— Seleccionar —</option>
                            <option value="TMB"   ${l.tipo_envase==='TMB'?'selected':''}>TMB (Tambo)</option>
                            <option value="TOTE"  ${l.tipo_envase==='TOTE'?'selected':''}>TOTE</option>
                            <option value="GFA"   ${l.tipo_envase==='GFA'?'selected':''}>GFA (Garrafa)</option>
                            <option value="JAULA" ${l.tipo_envase==='JAULA'?'selected':''}>JAULA</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Unidad de Transporte + Horario <span class="text-danger">*</span></label>
                        <div class="unidad-selector ${l.slot_id ? 'seleccionada' : ''}" onclick="abrirModalUnidad(${idx})">
                            <div>
                                ${l.slot_id
                                    ? `<strong>${escapar(l.unidad_label)}</strong><br><small class="text-muted">${escapar(l.slot_label)}</small>`
                                    : '<span class="placeholder">— Seleccionar unidad y horario —</span>'
                                }
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </div>
                </div>
            `;
            cont.appendChild(card);
        });
    }

    function abrirModalUnidad(lineaIdx) {
        const fecha = document.getElementById('fechaDocumento').value;
        if (!fecha) {
            alert('Primero indica la fecha del documento.');
            return;
        }
        document.getElementById('modalLineaIndex').value = lineaIdx;
        const cont = document.getElementById('listadoUnidades');
        cont.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';

        // IDs de slots ya seleccionados en OTRAS líneas (para excluirlos)
        const slotsExcluidos = lineas
            .filter((l, i) => i !== lineaIdx && l.slot_id)
            .map(l => l.slot_id);

        // Cargar unidades activas
        let html = '';
        if (UNIDADES.length === 0) {
            html = '<div class="alert alert-warning">No hay unidades de transporte activas.</div>';
            cont.innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalUnidadSlot')).show();
            return;
        }

        // Para cada unidad, hacer fetch de sus slots libres
        Promise.all(UNIDADES.map(u =>
            fetch(URL_BASE + 'dashboard/salidas_envases/api/slots_unidad.php?unidad_id=' + u.id + '&fecha=' + fecha)
                .then(r => r.json())
                .then(slots => ({ unidad: u, slots: slots.filter(s => !slotsExcluidos.includes(parseInt(s.id))) }))
        )).then(resultados => {
            html = '';
            resultados.forEach(r => {
                const u = r.unidad;
                const slotsLibres = r.slots;
                html += `
                    <div class="unidad-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="nombre">${escapar(u.nombre)}</span>
                                <small class="placa-cell text-muted ms-2">${escapar(u.placas)}</small>
                            </div>
                            <div>
                                <span class="capacidad-pill" title="TMB">TMB: ${u.capacidad_tmb}</span>
                                <span class="capacidad-pill" title="TOTE">TOTE: ${u.capacidad_tote}</span>
                                <span class="capacidad-pill" title="GFA">GFA: ${u.capacidad_gfa}</span>
                                <span class="capacidad-pill" title="JAULA">JAULA: ${u.capacidad_jaula}</span>
                            </div>
                        </div>
                        <div>
                            ${slotsLibres.length === 0
                                ? '<small class="text-muted"><i class="bi bi-x-circle"></i> Sin horarios disponibles en esta fecha.</small>'
                                : slotsLibres.map(s =>
                                    `<span class="slot-libre-pill" onclick="seleccionarSlot(${lineaIdx}, ${s.id}, '${escapar(u.nombre)}', '${escapar(u.placas)}', '${s.hora_inicio.substring(0,5)} - ${s.hora_fin.substring(0,5)}')">
                                        <i class="bi bi-clock"></i> ${s.hora_inicio.substring(0,5)} - ${s.hora_fin.substring(0,5)}
                                    </span>`
                                ).join('')
                            }
                        </div>
                    </div>
                `;
            });
            cont.innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalUnidadSlot')).show();
        }).catch(err => {
            cont.innerHTML = '<div class="alert alert-danger">Error cargando unidades: ' + err.message + '</div>';
            new bootstrap.Modal(document.getElementById('modalUnidadSlot')).show();
        });
    }

    function seleccionarSlot(lineaIdx, slotId, unidadNombre, unidadPlacas, slotLabel) {
        lineas[lineaIdx].slot_id = slotId;
        lineas[lineaIdx].unidad_label = unidadNombre + ' (' + unidadPlacas + ')';
        lineas[lineaIdx].slot_label = slotLabel;
        bootstrap.Modal.getInstance(document.getElementById('modalUnidadSlot')).hide();
        renderLineas();
    }

    function limpiarFirma() {
        $('#firmaCanvas').jSignature('reset');
    }

    function enviarFormulario() {
        // Validar líneas
        if (lineas.length === 0) {
            alert('Debes agregar al menos una línea.');
            return;
        }
        for (let i = 0; i < lineas.length; i++) {
            const l = lineas[i];
            if (!l.cantidad || l.cantidad <= 0) { alert(`Línea ${i+1}: cantidad inválida.`); return; }
            if (!l.tipo_envase) { alert(`Línea ${i+1}: selecciona el tipo de envase.`); return; }
            if (!l.slot_id)     { alert(`Línea ${i+1}: selecciona unidad y horario.`); return; }
        }
        // Validar firma
        const data = $('#firmaCanvas').jSignature('getData', 'image');
        if (!data || data[1].length < 100) {
            alert('Debes firmar antes de enviar.');
            return;
        }
        const firmaBase64 = 'data:' + data[0] + ',' + data[1];
        document.getElementById('solicitaFirma').value = firmaBase64;

        // Agregar líneas al form como inputs ocultos
        const form = document.getElementById('formNuevaSec');
        // Limpiar inputs previos de líneas
        form.querySelectorAll('input[name^="linea_"]').forEach(el => el.remove());

        lineas.forEach((l, idx) => {
            ['cantidad','tipo_envase','slot_id'].forEach(campo => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `linea_${idx}_${campo}`;
                input.value = l[campo];
                form.appendChild(input);
            });
        });
        const total = document.createElement('input');
        total.type = 'hidden';
        total.name = 'total_lineas';
        total.value = lineas.length;
        form.appendChild(total);

        document.getElementById('btnGuardarSec').disabled = true;
        document.getElementById('btnGuardarSec').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
        form.submit();
    }

    function escapar(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, "\\'");
    }
    </script>
</body>
</html>