<?php
/**
 * Unidades de Transporte
 * dashboard/salidas_envases/unidades/unidades_transporte.php
 *
 * Permisos:
 *   - Logística: CRUD completo.
 *   - Almacén de Residuos y Ventas: solo lectura.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/unidades_transporte_funciones.php';

// ---- Autenticación ----
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ---- Autorización ----
if (!puede_administrar_tipos_envase()) {
    establecer_alerta('error', 'Solo Almacén de Residuos puede administrar tipos de envase.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/catalogo/tipos_envase.php');
}

$puede_editar = puede_administrar_unidades_transporte();

// ---- Datos ----
$unidades = obtener_unidades_transporte(true); // incluir inactivas para admin

// Especificaciones activas (para el dropdown del modal)
$especificaciones_activas = obtener_especificaciones(null, false);

// Embebido JSON para poblar el modal al editar sin AJAX
$unidades_data = [];
foreach ($unidades as $u) {
    $caps = obtener_capacidades_unidad($u['id']);
    $unidades_data[$u['id']] = [
        'id'         => (int) $u['id'],
        'nombre'     => $u['nombre'],
        'matricula'  => $u['matricula'],
        'notas'      => $u['notas'],
        'activo'     => (int) $u['activo'],
        'capacidades' => array_map(static fn($c) => [
            'especificacion_id' => (int) $c['especificacion_id'],
            'capacidad_maxima'  => (int) $c['capacidad_maxima'],
            'especificacion_nombre' => $c['especificacion_nombre'],
            'tipo_nombre'      => $c['tipo_nombre'],
            'activa'           => (int) $c['especificacion_activa'],
        ], $caps),
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades de Transporte - Verden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <style>
        .unidad-card { transition: box-shadow 0.15s; }
        .unidad-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .cap-item { border-bottom: 1px solid #f0f0f0; padding: 0.5rem 0; }
        .cap-item:last-child { border-bottom: none; }
        .cap-cantidad {
            display: inline-block;
            min-width: 60px;
            padding: 2px 8px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 4px;
            font-weight: 600;
            text-align: center;
        }
        .fila-capacidad {
            display: flex; gap: 0.5rem; align-items: center;
            margin-bottom: 0.5rem;
        }
        .fila-capacidad .form-select { flex: 2; }
        .fila-capacidad .form-control { flex: 1; }
        .empty-state { text-align: center; padding: 2rem 1rem; color: #999; }
        .empty-state .bi { font-size: 2.5rem; opacity: 0.5; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <!-- Encabezado -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-truck-front"></i> Unidades de Transporte</h1>
                    <small class="text-muted">
                        <?php echo $puede_editar
                            ? 'Gestiona las unidades y sus capacidades por especificación.'
                            : 'Consulta las unidades de transporte disponibles (solo lectura).'; ?>
                    </small>
                </div>
                <?php if ($puede_editar): ?>
                <button type="button" class="btn btn-primary" onclick="abrirNuevaUnidad()">
                    <i class="bi bi-plus-circle"></i> Nueva Unidad
                </button>
                <?php endif; ?>
            </div>

            <!-- Listado -->
            <?php if (empty($unidades)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Aún no hay unidades de transporte registradas.
                    <?php if ($puede_editar): ?>
                        Presiona <strong>Nueva Unidad</strong> para crear la primera.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($unidades as $u):
                        $caps = obtener_capacidades_unidad($u['id']);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card unidad-card h-100 <?php echo $u['activo'] ? '' : 'border-secondary opacity-75'; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-truck text-primary"></i>
                                        <span><?php echo htmlspecialchars($u['nombre']); ?></span>
                                        <?php if (!$u['activo']): ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </h5>
                                    <?php if (!empty($u['matricula'])): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-hash"></i> <?php echo htmlspecialchars($u['matricula']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($puede_editar): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-dark p-0" data-bs-toggle="dropdown" aria-label="Acciones">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="abrirEditarUnidad(<?php echo (int) $u['id']; ?>); return false;">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="eliminarUnidad(<?php echo (int) $u['id']; ?>, <?php echo htmlspecialchars(json_encode($u['nombre']), ENT_QUOTES); ?>); return false;">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <small class="text-muted d-block mb-2">
                                    <?php echo count($caps); ?> capacidad<?php echo count($caps) === 1 ? '' : 'es'; ?> configurada<?php echo count($caps) === 1 ? '' : 's'; ?>
                                </small>

                                <?php if (empty($caps)): ?>
                                    <p class="text-muted small mb-2">Sin capacidades configuradas.</p>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-2">
                                        <?php foreach ($caps as $c): ?>
                                        <li class="cap-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($c['tipo_nombre']); ?></strong>
                                                <br>
                                                <small class="<?php echo $c['especificacion_activa'] ? 'text-muted' : 'text-danger'; ?>">
                                                    <?php echo htmlspecialchars($c['especificacion_nombre']); ?>
                                                    <?php if (!$c['especificacion_activa']): ?>
                                                        <i class="bi bi-exclamation-circle" title="Especificación inactiva"></i>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <span class="cap-cantidad"><?php echo number_format((int) $c['capacidad_maxima']); ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if (!empty($u['notas'])): ?>
                                <hr class="my-2">
                                <small class="text-muted d-block">
                                    <i class="bi bi-sticky"></i> <?php echo nl2br(htmlspecialchars($u['notas'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($puede_editar): ?>
    <!-- ================== MODAL: Nueva / Editar unidad ================== -->
    <div class="modal fade" id="modalUnidad" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades/guardar_unidad_transporte.php" id="formUnidad">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-truck-front"></i>
                            <span id="modalUnidadTitulo">Nueva Unidad de Transporte</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="unidadId" value="">

                        <!-- Datos base -->
                        <fieldset class="mb-4">
                            <legend class="h6 text-muted mb-3">Datos de la unidad</legend>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Nombre: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nombre" id="unidadNombre"
                                           maxlength="100" required placeholder="Ej. Camión 01, Tortón Rojo">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Matrícula:</label>
                                    <input type="text" class="form-control" name="matricula" id="unidadMatricula"
                                           maxlength="50" placeholder="Ej. ABC-1234">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Notas:</label>
                                    <textarea class="form-control" name="notas" id="unidadNotas" rows="2"
                                              placeholder="Detalles adicionales, restricciones, etc."></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="activo" id="unidadActivo" value="1" checked>
                                        <label class="form-check-label" for="unidadActivo">Activa</label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Capacidades -->
                        <fieldset>
                            <legend class="h6 text-muted mb-3">
                                Capacidades por especificación
                                <small class="text-muted fw-normal">(opcional — se pueden agregar después)</small>
                            </legend>

                            <?php if (empty($especificaciones_activas)): ?>
                                <div class="alert alert-warning small mb-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    No hay especificaciones activas en el catálogo.
                                    Primero registra tipos y especificaciones en <strong>Tipos de Envase</strong>.
                                </div>
                            <?php else: ?>
                            <div id="contenedorCapacidades"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="agregarCapacidad()">
                                <i class="bi bi-plus"></i> Agregar capacidad
                            </button>
                            <?php endif; ?>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario silencioso para eliminación -->
    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades/eliminar_unidad_transporte.php"
          id="formEliminarUnidad" style="display:none;">
        <input type="hidden" name="id" id="eliminarUnidadId">
    </form>

    <!-- Template de fila de capacidad -->
    <template id="tplCapacidad">
        <div class="fila-capacidad">
            <select class="form-select form-select-sm" name="capacidades[__i__][especificacion_id]" required>
                <option value="">Especificación…</option>
                <?php
                // Agrupar por tipo
                $agrupadas = [];
                foreach ($especificaciones_activas as $e) {
                    $agrupadas[$e['tipo_nombre']][] = $e;
                }
                foreach ($agrupadas as $tipo_nombre => $especs):
                ?>
                <optgroup label="<?php echo htmlspecialchars($tipo_nombre); ?>">
                    <?php foreach ($especs as $e): ?>
                    <option value="<?php echo (int) $e['id']; ?>">
                        <?php echo htmlspecialchars($e['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
            <input type="number" class="form-control form-control-sm" name="capacidades[__i__][capacidad_maxima]"
                   min="1" step="1" placeholder="Cantidad" required>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.fila-capacidad').remove()" title="Quitar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </template>
    <?php endif; // fin bloque solo Logística ?>

    <!-- Toast de mensajes flash -->
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

    <?php if ($puede_editar): ?>
    <script>
    // Datos completos de las unidades para poblar el modal al editar
    const UNIDADES_DATA = <?php echo json_encode($unidades_data, JSON_UNESCAPED_UNICODE); ?>;

    let capIndex = 0;

    function agregarCapacidad(especId = null, cantidad = null) {
        const tpl = document.getElementById('tplCapacidad');
        if (!tpl) return;
        const clone = tpl.content.cloneNode(true);
        // Renombrar los name[] con el índice único
        clone.querySelectorAll('[name*="__i__"]').forEach(el => {
            el.name = el.name.replace('__i__', capIndex);
        });
        // Prepoblar si viene con datos (modo edición)
        if (especId !== null) {
            const sel = clone.querySelector('select');
            const inp = clone.querySelector('input[type="number"]');
            if (sel) sel.value = especId;
            if (inp) inp.value = cantidad;
        }
        document.getElementById('contenedorCapacidades').appendChild(clone);
        capIndex++;
    }

    function limpiarCapacidades() {
        const cont = document.getElementById('contenedorCapacidades');
        if (cont) cont.innerHTML = '';
        capIndex = 0;
    }

    function abrirNuevaUnidad() {
        document.getElementById('formUnidad').reset();
        document.getElementById('unidadId').value = '';
        document.getElementById('unidadActivo').checked = true;
        document.getElementById('modalUnidadTitulo').textContent = 'Nueva Unidad de Transporte';
        limpiarCapacidades();
        new bootstrap.Modal(document.getElementById('modalUnidad')).show();
    }

    function abrirEditarUnidad(id) {
        const u = UNIDADES_DATA[id];
        if (!u) {
            alert('No se pudo cargar la unidad.');
            return;
        }
        document.getElementById('formUnidad').reset();
        document.getElementById('unidadId').value = u.id;
        document.getElementById('unidadNombre').value = u.nombre || '';
        document.getElementById('unidadMatricula').value = u.matricula || '';
        document.getElementById('unidadNotas').value = u.notas || '';
        document.getElementById('unidadActivo').checked = (u.activo === 1 || u.activo === '1');
        document.getElementById('modalUnidadTitulo').textContent = 'Editar: ' + u.nombre;

        limpiarCapacidades();
        (u.capacidades || []).forEach(c => {
            agregarCapacidad(c.especificacion_id, c.capacidad_maxima);
            // Si la especificación está inactiva y ya no aparece en el select,
            // la agregamos manualmente para no perder el dato
            const cont = document.getElementById('contenedorCapacidades');
            const ultimaFila = cont.lastElementChild;
            const sel = ultimaFila.querySelector('select');
            if (sel && sel.value !== String(c.especificacion_id)) {
                const opt = document.createElement('option');
                opt.value = c.especificacion_id;
                opt.textContent = '⚠ ' + (c.tipo_nombre || '') + ' — ' + (c.especificacion_nombre || '') + ' (inactiva)';
                sel.appendChild(opt);
                sel.value = c.especificacion_id;
            }
        });

        new bootstrap.Modal(document.getElementById('modalUnidad')).show();
    }

    function eliminarUnidad(id, nombre) {
        if (confirm('¿Eliminar la unidad "' + nombre + '"?\n\nEsto también eliminará todas sus capacidades configuradas.')) {
            document.getElementById('eliminarUnidadId').value = id;
            document.getElementById('formEliminarUnidad').submit();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>