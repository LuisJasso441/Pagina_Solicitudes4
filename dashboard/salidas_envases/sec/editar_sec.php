<?php
/**
 * Editar SEC — solo en estado 'pendiente_firma_entrega'.
 * dashboard/salidas_envases/sec/editar_sec.php
 *
 * Al guardar, se revierte el stock viejo y se descuenta el nuevo.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/unidades_transporte_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_crear_sec()) {
    establecer_alerta('error', 'No tienes permisos para editar Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec_id = (int) ($_GET['id'] ?? 0);
if ($sec_id <= 0) {
    establecer_alerta('error', 'SEC inválida.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec = obtener_sec_por_id($sec_id);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}
if (!sec_es_editable($sec)) {
    establecer_alerta('warning', 'Esta SEC ya no se puede editar (estado: ' . info_estado_sec($sec['estado'])[1] . ').');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/ver_sec.php?id=' . $sec_id);
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id     = (int) $_SESSION['usuario_id'];
$dept           = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

$unidades                 = obtener_unidades_transporte(false);
$especificaciones_activas = obtener_especificaciones(null, false);

$specs_para_js = [];
foreach ($especificaciones_activas as $e) {
    $specs_para_js[] = [
        'id'          => (int) $e['id'],
        'nombre'      => $e['nombre'],
        'tipo_id'     => (int) $e['tipo_envase_id'],
        'tipo_nombre' => $e['tipo_nombre'],
    ];
}

// Errores/datos previos (si vinieron de un intento fallido)
$errores_flash = $_SESSION['sec_errores'] ?? [];
$datos_previos = $_SESSION['sec_datos_previos'] ?? null;
unset($_SESSION['sec_errores'], $_SESSION['sec_datos_previos']);

// Cargar valores actuales o previos-fallidos
$prev_fecha    = $datos_previos['fecha_salida']      ?? $sec['fecha_salida'];
$prev_unidad   = (int) ($datos_previos['unidad_id']  ?? $sec['unidad_id']);
$prev_vuelta   = (int) ($datos_previos['vuelta_id']  ?? $sec['vuelta_id']);
$prev_hora_ini = $datos_previos['hora_inicio_ruta']  ?? $sec['hora_inicio_ruta'] ?? '';
$prev_hora_fin = $datos_previos['hora_termino_ruta'] ?? $sec['hora_termino_ruta'] ?? '';
$prev_chofer   = $datos_previos['chofer_nombre']     ?? $sec['chofer_nombre'] ?? '';
$prev_solicita = $datos_previos['solicita_nombre']   ?? $sec['solicita_nombre'] ?? '';
$prev_notas    = $datos_previos['notas_generales']   ?? $sec['notas_generales'] ?? '';

// Líneas: si hubo error previo usar $datos_previos['lineas']; si no, las líneas actuales de la SEC
if ($datos_previos && !empty($datos_previos['lineas'])) {
    $prev_lineas = $datos_previos['lineas'];
} else {
    $prev_lineas = array_map(static fn($l) => [
        'empresa_nombre'     => $l['empresa_nombre'],
        'especificacion_id'  => (int) $l['especificacion_id'],
        'cantidad'           => (int) $l['cantidad'],
        'condiciones_envase' => $l['condiciones_envase'],
    ], $sec['lineas']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar SEC <?php echo htmlspecialchars($sec['folio']); ?> | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .linea-empresa { border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; background: #fbfbfb; position: relative; }
        [data-theme="dark"] .linea-empresa { background: #2a2f34; border-color: #3a3f44; }
        .linea-numero { position: absolute; top: -10px; left: 12px; background: var(--bs-primary); color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .btn-quitar-linea { position: absolute; top: 8px; right: 8px; }
        .stock-info { font-size: 0.78rem; margin-top: 4px; padding: 4px 8px; border-radius: 4px; display: inline-block; }
        .stock-info.ok { background: #d1e7dd; color: #0f5132; }
        .stock-info.warning { background: #fff3cd; color: #664d03; }
        .stock-info.danger { background: #f8d7da; color: #842029; }
        [data-theme="dark"] .stock-info.ok { background: rgba(25,135,84,0.20); color: #75d5a4; }
        [data-theme="dark"] .stock-info.warning { background: rgba(255,193,7,0.20); color: #ffe083; }
        [data-theme="dark"] .stock-info.danger { background: rgba(220,53,69,0.20); color: #ffabab; }
        .campo-cantidad.stock-excedido { border-color: #dc3545 !important; background: #fff5f6; }
        [data-theme="dark"] .campo-cantidad.stock-excedido { background: rgba(220,53,69,0.10); }
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

                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-pencil-square"></i> Editar SEC <?php echo htmlspecialchars($sec['folio']); ?></h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Al guardar, el stock se ajustará automáticamente (se revierte lo viejo y se descuenta lo nuevo).
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/ver_sec.php?id=<?php echo $sec_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al detalle
                        </a>
                    </div>
                </div>

                <?php if (!empty($errores_flash)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Se encontraron los siguientes errores:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errores_flash as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/guardar_sec.php" id="formSec" novalidate>
                    <input type="hidden" name="modo" value="editar">
                    <input type="hidden" name="id" value="<?php echo $sec_id; ?>">

                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Datos generales</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Fecha de salida <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="fecha_salida" id="fechaSalida"
                                           value="<?php echo htmlspecialchars($prev_fecha); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Unidad de transporte <span class="text-danger">*</span></label>
                                    <select class="form-select" name="unidad_id" id="selectUnidad" required>
                                        <option value="">Seleccione unidad…</option>
                                        <?php foreach ($unidades as $u): ?>
                                        <option value="<?php echo (int) $u['id']; ?>"
                                            <?php echo $prev_unidad === (int) $u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre']); ?>
                                            <?php if (!empty($u['matricula'])): ?>· <?php echo htmlspecialchars($u['matricula']); ?><?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">Vuelta <span class="text-danger">*</span></label>
                                    <select class="form-select" name="vuelta_id" id="selectVuelta"
                                            data-preseleccionar="<?php echo $prev_vuelta; ?>" required>
                                        <option value="">Cargando…</option>
                                    </select>
                                    <div id="vueltaAyuda" class="form-text"></div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small">Hora inicio ruta <span class="text-muted">(opcional)</span></label>
                                    <input type="time" class="form-control form-control-sm" name="hora_inicio_ruta"
                                           value="<?php echo htmlspecialchars($prev_hora_ini); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Hora término ruta <span class="text-muted">(opcional)</span></label>
                                    <input type="time" class="form-control form-control-sm" name="hora_termino_ruta"
                                           value="<?php echo htmlspecialchars($prev_hora_fin); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Chofer <span class="text-muted">(opcional)</span></label>
                                    <input type="text" class="form-control form-control-sm" name="chofer_nombre" maxlength="200"
                                           value="<?php echo htmlspecialchars($prev_chofer); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Solicita</label>
                                    <input type="text" class="form-control form-control-sm" name="solicita_nombre" maxlength="200"
                                           value="<?php echo htmlspecialchars($prev_solicita); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Notas generales</label>
                                    <textarea class="form-control form-control-sm" name="notas_generales" rows="2"
                                              maxlength="1000"><?php echo htmlspecialchars($prev_notas); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-building"></i> Empresas destino</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarLinea">
                                <i class="bi bi-plus-lg"></i> Agregar empresa
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Modifica, agrega o quita líneas. El stock del inventario se recalculará automáticamente al guardar.
                            </p>
                            <div id="contenedorLineas"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/ver_sec.php?id=<?php echo $sec_id; ?>" class="btn btn-secondary">
                            Cancelar cambios
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            <i class="bi bi-check-circle"></i> Guardar cambios
                        </button>
                    </div>
                </form>

            </div>
        </main>
    </div>

    <template id="tplLinea">
        <div class="linea-empresa" data-idx="__i__">
            <span class="linea-numero">Línea __n__</span>
            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-linea" title="Quitar">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Empresa destino <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="lineas[__i__][empresa_nombre]"
                           maxlength="200" placeholder="Nombre de la empresa" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Especificación <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm campo-espec" name="lineas[__i__][especificacion_id]" required>
                        <option value="">Selecciona…</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Cantidad <span class="text-danger">*</span></label>
                    <input type="number" class="form-control form-control-sm campo-cantidad" name="lineas[__i__][cantidad]"
                           min="1" step="1" placeholder="Cantidad" required>
                    <div class="stock-info-container"></div>
                </div>
                <div class="col-md-8">
                    <label class="form-label small">Condiciones del envase <span class="text-muted">(opcional)</span></label>
                    <input type="text" class="form-control form-control-sm" name="lineas[__i__][condiciones_envase]"
                           maxlength="500" placeholder="Ej. En buen estado, con tapa, limpio">
                </div>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
    (function () {
        const URL_BASE = <?php echo json_encode(URL_BASE); ?>;
        const SPECS    = <?php echo json_encode($specs_para_js, JSON_UNESCAPED_UNICODE); ?>;
        const PREV_LINEAS = <?php echo json_encode($prev_lineas, JSON_UNESCAPED_UNICODE); ?>;

        // Sumas viejas por especificación (para validación en edición)
        const SUMAS_VIEJAS = <?php
            $s = [];
            foreach ($sec['lineas'] as $l) {
                $eid = (int) $l['especificacion_id'];
                $s[$eid] = ($s[$eid] ?? 0) + (int) $l['cantidad'];
            }
            echo json_encode((object) $s);
        ?>;

        let stockMap = {};
        let capacidadesMap = {};
        let unidadIdActual = null;
        let lineaIdx = 0;

        function cargarStock() {
            return fetch(URL_BASE + 'dashboard/salidas_envases/api/stock_especificaciones.php')
                .then(r => r.json())
                .then(d => {
                    if (d.ok) {
                        stockMap = d.stock;
                        recalcularTodasLasLineas();
                    }
                });
        }

        function cargarCapacidades() {
            const unidadId = document.getElementById('selectUnidad').value;
            unidadIdActual = unidadId ? parseInt(unidadId, 10) : null;

            if (!unidadIdActual) {
                capacidadesMap = {};
                recalcularTodasLasLineas();
                return Promise.resolve();
            }

            return fetch(URL_BASE + 'dashboard/salidas_envases/api/capacidades_unidad.php?unidad_id=' + unidadIdActual)
                .then(r => r.json())
                .then(d => {
                    capacidadesMap = (d.ok && d.capacidades) ? d.capacidades : {};
                    recalcularTodasLasLineas();
                })
                .catch(err => {
                    console.error('Capacidades:', err);
                    capacidadesMap = {};
                    recalcularTodasLasLineas();
                });
        }

        function poblarSelectEspec(sel) {
            const porTipo = {};
            SPECS.forEach(s => {
                if (!porTipo[s.tipo_nombre]) porTipo[s.tipo_nombre] = [];
                porTipo[s.tipo_nombre].push(s);
            });
            let html = '<option value="">Selecciona…</option>';
            Object.keys(porTipo).sort().forEach(tipoNombre => {
                html += `<optgroup label="${escapeHtml(tipoNombre)}">`;
                porTipo[tipoNombre].forEach(s => {
                    html += `<option value="${s.id}">${escapeHtml(s.nombre)}</option>`;
                });
                html += '</optgroup>';
            });
            sel.innerHTML = html;
        }

        function sumarPorEspec() {
            const sumas = {};
            document.querySelectorAll('.linea-empresa').forEach(div => {
                const sel  = div.querySelector('.campo-espec');
                const inp  = div.querySelector('.campo-cantidad');
                const eid  = parseInt(sel.value, 10);
                const cant = parseInt(inp.value, 10);
                if (!eid || isNaN(cant) || cant <= 0) return;
                sumas[eid] = (sumas[eid] || 0) + cant;
            });
            return sumas;
        }

        // Stock EFECTIVO en modo edición: stock actual + lo que la SEC vieja ya consumía
        function stockEfectivo(eid) {
            const actual = stockMap[eid] ?? 0;
            const viejo  = SUMAS_VIEJAS[eid] || 0;
            return actual + viejo;
        }

        function recalcularLinea(div) {
            const sel  = div.querySelector('.campo-espec');
            const inp  = div.querySelector('.campo-cantidad');
            const cont = div.querySelector('.stock-info-container');
            const eid  = parseInt(sel.value, 10);
            const cant = parseInt(inp.value, 10);

            inp.classList.remove('stock-excedido');
            cont.innerHTML = '';

            if (!eid || isNaN(cant) || cant <= 0) return;

            // Stock efectivo: actual + lo que la SEC vieja consumía
            const stockDisp = stockEfectivo(eid);
            const sumas = sumarPorEspec();
            const totalPedido = sumas[eid] || 0;

            let cls, icono, texto, bloquea = false;

            if (!unidadIdActual) {
                cls = 'warning';
                icono = 'exclamation-circle';
                texto = 'Selecciona una unidad para validar capacidad';
            }
            else if (!(eid in capacidadesMap)) {
                cls = 'danger';
                icono = 'x-octagon';
                texto = 'Esta unidad no está configurada para transportar esta especificación';
                bloquea = true;
            }
            else if (totalPedido > stockDisp) {
                cls = 'danger';
                icono = 'exclamation-triangle';
                texto = `Disponible: ${stockDisp} · Pedido total: ${totalPedido} · Faltan ${totalPedido - stockDisp}`;
                bloquea = true;
            }
            else if (totalPedido > capacidadesMap[eid]) {
                cls = 'danger';
                icono = 'exclamation-triangle';
                const cap = capacidadesMap[eid];
                texto = `Capacidad unidad: ${cap} · Pedido total: ${totalPedido} · Excede en ${totalPedido - cap}`;
                bloquea = true;
            }
            else {
                const cap = capacidadesMap[eid];
                const usoPct = (totalPedido / cap) * 100;
                if (usoPct > 80 || totalPedido > stockDisp * 0.8) {
                    cls = 'warning';
                    icono = 'exclamation-circle';
                } else {
                    cls = 'ok';
                    icono = 'check-circle';
                }
                texto = `Disponible: ${stockDisp} · Cap. unidad: ${cap} · Pedido: ${totalPedido}`;
            }

            if (bloquea) inp.classList.add('stock-excedido');
            cont.innerHTML = `<span class="stock-info ${cls}"><i class="bi bi-${icono}"></i> ${texto}</span>`;
        }

        function recalcularTodasLasLineas() {
            document.querySelectorAll('.linea-empresa').forEach(recalcularLinea);
            actualizarBotonGuardar();
        }

        function actualizarBotonGuardar() {
            const tieneExcesos = document.querySelectorAll('.campo-cantidad.stock-excedido').length > 0;
            const btn = document.getElementById('btnGuardar');
            if (btn) btn.disabled = tieneExcesos;
        }

        function agregarLinea(datos = null) {
            const tpl = document.getElementById('tplLinea');
            const clone = tpl.content.cloneNode(true);
            const div = clone.querySelector('.linea-empresa');
            const idx = lineaIdx++;
            div.setAttribute('data-idx', idx);
            div.querySelectorAll('[name*="__i__"]').forEach(el => {
                el.name = el.name.replace('__i__', idx);
            });
            const sel = div.querySelector('.campo-espec');
            poblarSelectEspec(sel);

            if (datos) {
                div.querySelector('input[name*="[empresa_nombre]"]').value    = datos.empresa_nombre     || '';
                div.querySelector('input[name*="[condiciones_envase]"]').value = datos.condiciones_envase || '';
                if (datos.especificacion_id) sel.value                        = datos.especificacion_id;
                div.querySelector('.campo-cantidad').value                    = datos.cantidad          || '';
            }

            document.getElementById('contenedorLineas').appendChild(clone);
            const nuevo = document.getElementById('contenedorLineas').lastElementChild;
            renumerarLineas();

            nuevo.querySelector('.campo-espec').addEventListener('change', recalcularTodasLasLineas);
            nuevo.querySelector('.campo-cantidad').addEventListener('input', recalcularTodasLasLineas);
            nuevo.querySelector('.btn-quitar-linea').addEventListener('click', () => {
                nuevo.remove();
                renumerarLineas();
                recalcularTodasLasLineas();
            });
            recalcularLinea(nuevo);
        }

        function renumerarLineas() {
            document.querySelectorAll('.linea-empresa .linea-numero').forEach((span, i) => {
                span.textContent = 'Línea ' + (i + 1);
            });
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s || '';
            return div.innerHTML;
        }

        function cargarVueltas() {
            const unidadId = document.getElementById('selectUnidad').value;
            const fecha    = document.getElementById('fechaSalida').value;
            const selVuelta = document.getElementById('selectVuelta');
            const ayuda    = document.getElementById('vueltaAyuda');

            if (!unidadId || !fecha) {
                selVuelta.innerHTML = '<option value="">Selecciona primero unidad y fecha…</option>';
                ayuda.textContent = '';
                return;
            }

            selVuelta.innerHTML = '<option value="">Cargando…</option>';
            ayuda.textContent = '';

            const params = new URLSearchParams({ unidad_id: unidadId, fecha: fecha });
            fetch(URL_BASE + 'dashboard/salidas_envases/api/vueltas_disponibles.php?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.error || 'Error');
                    if (data.vueltas.length === 0) {
                        selVuelta.innerHTML = '<option value="">Sin vueltas programadas</option>';
                        ayuda.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> No hay vueltas programadas. <a href="' + URL_BASE + 'dashboard/salidas_envases/vueltas/vueltas.php">Programar vueltas</a></span>';
                        return;
                    }
                    const preseleccionar = parseInt(selVuelta.dataset.preseleccionar, 10) || 0;
                    let html = '<option value="">Selecciona vuelta…</option>';
                    data.vueltas.forEach(v => {
                        const info = v.total_secs > 0 ? ` (${v.total_secs} SEC${v.total_secs === 1 ? '' : 's'} asignada${v.total_secs === 1 ? '' : 's'})` : '';
                        const sel = v.id === preseleccionar ? ' selected' : '';
                        html += `<option value="${v.id}"${sel}>Vuelta ${v.numero}${info}</option>`;
                    });
                    selVuelta.innerHTML = html;
                    ayuda.textContent = data.vueltas.length + ' vuelta(s) disponibles.';
                })
                .catch(err => {
                    selVuelta.innerHTML = '<option value="">Error al cargar</option>';
                    ayuda.innerHTML = '<span class="text-danger">Error al cargar vueltas.</span>';
                });
        }

        document.getElementById('selectUnidad').addEventListener('change', () => {
            cargarVueltas();
            cargarCapacidades();
        });
        document.getElementById('fechaSalida').addEventListener('change', cargarVueltas);
        document.getElementById('btnAgregarLinea').addEventListener('click', () => agregarLinea());

        Promise.all([cargarStock(), cargarCapacidades()]).then(() => {
            PREV_LINEAS.forEach(l => agregarLinea(l));
            recalcularTodasLasLineas();
        });
        cargarVueltas();

        document.getElementById('formSec').addEventListener('submit', function (e) {
            if (document.querySelectorAll('.linea-empresa').length === 0) {
                e.preventDefault();
                alert('Debe haber al menos una línea de empresa destino.');
                return false;
            }
            if (document.querySelectorAll('.campo-cantidad.stock-excedido').length > 0) {
                e.preventDefault();
                alert('Hay líneas que exceden el stock disponible. Corrígelas antes de guardar.');
                return false;
            }
        });
    })();
    </script>
</body>
</html>