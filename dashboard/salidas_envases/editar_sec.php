<?php
/**
 * Editar SEC — modificar líneas (sólo si estado = enviada)
 *
 * Ubicación: dashboard/salidas_envases/editar_sec.php
 *
 * Sólo Logística o Ventas con permiso de creador.
 * Fecha del documento y firma de Solicita quedan congeladas (no editables).
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

$id_sec = (int)($_GET['id'] ?? 0);
if ($id_sec <= 0) {
    establecer_alerta('error', 'SEC no especificada.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec = obtener_sec_por_id($id_sec);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

if (!puede_crear_sec() || !sec_es_editable($sec)) {
    establecer_alerta('error', 'No tienes permisos para editar esta SEC o ya no es editable.');
    redirigir(URL_BASE . "dashboard/salidas_envases/ver_sec.php?id=$id_sec");
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id     = $_SESSION['usuario_id'];
$dept           = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

$unidades = obtener_unidades_transporte(true);

// Preparar líneas existentes para precarga JS
$lineas_actuales = [];
foreach ($sec['lineas'] as $idx => $l) {
    $lineas_actuales[] = [
        'cantidad'     => (int)$l['cantidad'],
        'tipo_envase'  => $l['tipo_envase'],
        'slot_id'      => (int)$l['slot_id'],
        'unidad_id'    => (int)$l['unidad_transporte_id'],
        'unidad_label' => ($l['unidad_nombre'] ?? '') . ' (' . ($l['unidad_placas'] ?? '') . ')',
        'slot_label'   => ($l['slot_hora_inicio'] ? substr($l['slot_hora_inicio'],0,5) . ' - ' . substr($l['slot_hora_fin'],0,5) : '')
    ];
}

$errores_flash = $_SESSION['sec_errores'] ?? [];
unset($_SESSION['sec_errores']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar <?php echo htmlspecialchars($sec['folio']); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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
        }
        .unidad-selector:hover { border-color: #14b8a6; }
        .unidad-selector.seleccionada { border-color: #14b8a6; background: #f0fdfa; }
        .unidad-selector .placeholder { color: #6c757d; font-style: italic; }
        .unidad-item {
            border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 8px;
            cursor: pointer;
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
        .alert-info-edicion {
            background: #fff8e1; border-left: 4px solid #f59e0b; color: #856404;
            padding: 0.75rem 1rem; border-radius: 6px;
            font-size: 0.85rem;
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
                            <h1><i class="bi bi-pencil-square"></i> Editar <?php echo htmlspecialchars($sec['folio']); ?></h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Fecha: <strong><?php echo htmlspecialchars($sec['fecha_documento']); ?></strong> ·
                                Solicita: <strong><?php echo htmlspecialchars($sec['solicita_nombre']); ?></strong>
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/ver_sec.php?id=<?php echo $id_sec; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar edición
                        </a>
                    </div>
                </div>

                <div class="alert-info-edicion mb-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Modo edición.</strong> Sólo puedes modificar las líneas de envases (cantidad, tipo, unidad y horario).
                    La fecha del documento y la firma de "Solicita" quedan congeladas.
                </div>

                <?php if (!empty($errores_flash)): ?>
                    <div class="alert alert-danger">
                        <strong>No se pudo actualizar la SEC:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores_flash as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="formEditarSec" method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/actualizar_sec.php" novalidate>
                    <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">

                    <!-- Destino: empresa + condiciones -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-building"></i> Destino</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Empresa destino <span class="text-danger">*</span></label>
                                    <input type="text" name="empresa_destino" id="empresaDestino" class="form-control"
                                           value="<?php echo htmlspecialchars($sec['empresa_destino'] ?? ''); ?>"
                                           maxlength="200" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Condiciones del envase <span class="text-danger">*</span></label>
                                    <textarea name="condiciones_envase" id="condicionesEnvase" class="form-control"
                                              rows="3" required><?php echo htmlspecialchars($sec['condiciones_envase'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

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

                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/ver_sec.php?id=<?php echo $id_sec; ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="btnGuardar">
                            <i class="bi bi-check-circle"></i> Guardar cambios
                        </button>
                    </div>
                </form>

            </div>
        </main>

    </div>

    <!-- Modal selector de unidad + slot (mismo que nueva_sec) -->
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
                    <div id="listadoUnidades"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
    const URL_BASE = <?php echo json_encode(URL_BASE); ?>;
    const UNIDADES = <?php echo json_encode($unidades, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const FECHA_DOCUMENTO = <?php echo json_encode($sec['fecha_documento']); ?>;
    const SEC_ID = <?php echo (int)$id_sec; ?>;

    // Precargar líneas existentes
    let lineas = <?php echo json_encode($lineas_actuales, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        renderLineas();

        document.getElementById('formEditarSec').addEventListener('submit', function(e) {
            e.preventDefault();
            enviarFormulario();
        });
    });

    function agregarLinea() {
        lineas.push({
            cantidad: 1, tipo_envase: '',
            slot_id: null, unidad_id: null,
            unidad_label: null, slot_label: null
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

        if (lineas.length === 0) { vacio.style.display = 'block'; return; }
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
        document.getElementById('modalLineaIndex').value = lineaIdx;
        const cont = document.getElementById('listadoUnidades');
        cont.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';

        // Excluir slots ya elegidos en OTRAS líneas
        const slotsExcluidos = lineas
            .filter((l, i) => i !== lineaIdx && l.slot_id)
            .map(l => parseInt(l.slot_id));

        // El slot que tenía esta línea originalmente, si lo tenía, debe seguir disponible
        // (el endpoint slots_unidad incluye sólo slots libres, así que necesitamos también incluir
        //  el slot actual de esta línea si está ocupado por nuestra propia SEC)
        const slotActualDeLineaEditada = lineas[lineaIdx].slot_id;

        Promise.all(UNIDADES.map(u =>
            fetch(URL_BASE + 'dashboard/salidas_envases/api/slots_unidad.php?unidad_id=' + u.id + '&fecha=' + FECHA_DOCUMENTO + '&sec_id_excluir=' + SEC_ID)
                .then(r => r.json())
                .then(slots => ({
                    unidad: u,
                    slots: slots.filter(s => !slotsExcluidos.includes(parseInt(s.id)))
                }))
        )).then(resultados => {
            let html = '';
            resultados.forEach(r => {
                const u = r.unidad;
                html += `
                    <div class="unidad-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="nombre">${escapar(u.nombre)}</span>
                                <small class="placa-cell text-muted ms-2">${escapar(u.placas)}</small>
                            </div>
                            <div>
                                <span class="capacidad-pill">TMB: ${u.capacidad_tmb}</span>
                                <span class="capacidad-pill">TOTE: ${u.capacidad_tote}</span>
                                <span class="capacidad-pill">GFA: ${u.capacidad_gfa}</span>
                                <span class="capacidad-pill">JAULA: ${u.capacidad_jaula}</span>
                            </div>
                        </div>
                        <div>
                            ${r.slots.length === 0
                                ? '<small class="text-muted"><i class="bi bi-x-circle"></i> Sin horarios disponibles en esta fecha.</small>'
                                : r.slots.map(s =>
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
        });
    }

    function seleccionarSlot(lineaIdx, slotId, unidadNombre, unidadPlacas, slotLabel) {
        lineas[lineaIdx].slot_id = slotId;
        lineas[lineaIdx].unidad_label = unidadNombre + ' (' + unidadPlacas + ')';
        lineas[lineaIdx].slot_label = slotLabel;
        bootstrap.Modal.getInstance(document.getElementById('modalUnidadSlot')).hide();
        renderLineas();
    }

    function enviarFormulario() {
        // Validar destino
        const empresa = document.getElementById('empresaDestino').value.trim();
        if (!empresa) {
            alert('Debes escribir la empresa destino.');
            document.getElementById('empresaDestino').focus();
            return;
        }
        const condiciones = document.getElementById('condicionesEnvase').value.trim();
        if (!condiciones) {
            alert('Debes escribir las condiciones del envase.');
            document.getElementById('condicionesEnvase').focus();
            return;
        }

        if (lineas.length === 0) { alert('Debes mantener al menos una línea.'); return; }
        for (let i = 0; i < lineas.length; i++) {
            const l = lineas[i];
            if (!l.cantidad || l.cantidad <= 0) { alert(`Línea ${i+1}: cantidad inválida.`); return; }
            if (!l.tipo_envase) { alert(`Línea ${i+1}: selecciona tipo de envase.`); return; }
            if (!l.slot_id)     { alert(`Línea ${i+1}: selecciona unidad y horario.`); return; }
        }

        const form = document.getElementById('formEditarSec');
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
        total.type = 'hidden'; total.name = 'total_lineas'; total.value = lineas.length;
        form.appendChild(total);

        document.getElementById('btnGuardar').disabled = true;
        document.getElementById('btnGuardar').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        form.submit();
    }

    function escapar(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, "\\'");
    }
    </script>
</body>
</html>