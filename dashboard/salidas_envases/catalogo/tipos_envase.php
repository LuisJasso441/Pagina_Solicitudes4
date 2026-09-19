<?php
/**
 * Catálogo de Tipos de Envase + Especificaciones
 * dashboard/salidas_envases/catalogo/tipos_envase.php
 *
 * Permisos:
 *   - Logística: CRUD completo (crear/editar/eliminar tipos y especificaciones).
 *   - Almacén de Residuos y Ventas: solo lectura.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';

// ---- Autenticación ----
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ---- Autorización por departamento ----
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$puede_editar = puede_administrar_tipos_envase();

// ---- Datos ----
$tipos = obtener_tipos_envase(true); // incluye inactivos para admin
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Envase - Verden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <style>
        .tipo-card { transition: box-shadow 0.15s; }
        .tipo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .espec-item { border-bottom: 1px solid #f0f0f0; }
        .espec-item:last-child { border-bottom: none; }
        .espec-inactiva { opacity: 0.55; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Encabezado -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-box2"></i> Tipos de Envase</h1>
                    <small class="text-muted">
                        <?php echo $puede_editar
                            ? 'Gestiona el catálogo de envases y sus especificaciones.'
                            : 'Consulta el catálogo de envases (solo lectura).'; ?>
                    </small>
                </div>
                <?php if ($puede_editar): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" disabled
                            title="Función próximamente disponible">
                        <i class="bi bi-file-earmark-arrow-up"></i> Importar
                    </button>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalNuevoTipo">
                        <i class="bi bi-plus-circle"></i> Agregar
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Listado -->
            <?php if (empty($tipos)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Aún no hay tipos de envase registrados.
                    <?php if ($puede_editar): ?>
                        Presiona <strong>Agregar</strong> para crear el primero.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($tipos as $tipo):
                        $especs = obtener_especificaciones($tipo['id'], true);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card tipo-card h-100 <?php echo $tipo['activo'] ? '' : 'border-secondary opacity-75'; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-box text-success"></i>
                                    <span><?php echo htmlspecialchars($tipo['nombre']); ?></span>
                                    <?php if (!$tipo['activo']): ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </h5>
                                <?php if ($puede_editar): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-dark p-0" data-bs-toggle="dropdown"
                                            aria-label="Acciones">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#"
                                               onclick="editarTipo(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>, <?php echo (int) $tipo['activo']; ?>); return false;">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#"
                                               onclick="eliminarTipo(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>); return false;">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <small class="text-muted d-block mb-2">
                                    <?php
                                    $n = count($especs);
                                    echo $n . ' especificaci' . ($n === 1 ? 'ón' : 'ones');
                                    ?>
                                </small>

                                <?php if (empty($especs)): ?>
                                    <p class="text-muted small mb-3">Sin especificaciones.</p>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-3">
                                        <?php foreach ($especs as $espec): ?>
                                        <li class="espec-item d-flex justify-content-between align-items-center py-2 <?php echo $espec['activo'] ? '' : 'espec-inactiva'; ?>">
                                            <span>
                                                <i class="bi bi-dot"></i>
                                                <?php echo htmlspecialchars($espec['nombre']); ?>
                                                <?php if (!$espec['activo']): ?>
                                                    <span class="badge bg-secondary ms-1">Inactiva</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($puede_editar): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-link text-dark p-1"
                                                        title="Editar"
                                                        onclick="editarEspec(<?php echo (int) $espec['id']; ?>, <?php echo htmlspecialchars(json_encode($espec['nombre']), ENT_QUOTES); ?>, <?php echo (int) $espec['activo']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-link text-danger p-1"
                                                        title="Eliminar"
                                                        onclick="eliminarEspec(<?php echo (int) $espec['id']; ?>, <?php echo htmlspecialchars(json_encode($espec['nombre']), ENT_QUOTES); ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($puede_editar): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100"
                                        onclick="agregarEspecRapido(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>)">
                                    <i class="bi bi-plus"></i> Agregar especificación
                                </button>
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
    <!-- ================== MODAL: Nuevo Tipo / Especificación ================== -->
    <div class="modal fade" id="modalNuevoTipo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/guardar_tipo_envase.php" id="formNuevoTipo">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-box2"></i> Nuevo Tipo de Envase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Selector de modo -->
                        <div class="mb-4">
                            <div class="btn-group w-100" role="group" aria-label="Modo">
                                <input type="radio" class="btn-check" name="modo" id="modoNuevo" value="nuevo" checked>
                                <label class="btn btn-outline-primary" for="modoNuevo">
                                    <i class="bi bi-plus-circle"></i> Nuevo Tipo de Envase
                                </label>
                                <input type="radio" class="btn-check" name="modo" id="modoExistente" value="existente">
                                <label class="btn btn-outline-primary" for="modoExistente">
                                    <i class="bi bi-plus-square"></i> Tipo de Envase Existente
                                </label>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <strong>Nuevo:</strong> crea un tipo desde cero con su primera especificación.<br>
                                <strong>Existente:</strong> agrega una especificación nueva a un tipo ya registrado.
                            </small>
                        </div>

                        <div class="row g-3">
                            <!-- Columna Tipo (nuevo o existente según modo) -->
                            <div class="col-md-6" id="colTipoNuevo">
                                <label class="form-label fw-bold">
                                    Tipo de Envase: <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="tipo_nuevo" id="inputTipoNuevo"
                                       maxlength="100" placeholder="Ej. Tambo, Tote, Garrafa" required>
                            </div>

                            <div class="col-md-6" id="colTipoExistente" style="display:none;">
                                <label class="form-label fw-bold">
                                    Tipo de Envase: <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="tipo_id" id="selectTipoExistente">
                                    <option value="">Seleccione un tipo…</option>
                                    <?php foreach ($tipos as $t): if (!$t['activo']) continue; ?>
                                    <option value="<?php echo (int) $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                $tipos_activos = array_filter($tipos, static fn($t) => (int) $t['activo'] === 1);
                                if (empty($tipos_activos)):
                                ?>
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Aún no hay tipos activos. Cambia a modo "Nuevo Tipo".
                                </small>
                                <?php endif; ?>
                            </div>

                            <!-- Columna Especificación (siempre presente) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    Especificación: <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="especificacion" id="inputEspec"
                                       maxlength="150" placeholder="Ej. Tambo abierto, Tote 1000L" required>
                            </div>
                        </div>
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

    <!-- ================== MODAL: Editar Tipo ================== -->
    <div class="modal fade" id="modalEditarTipo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/actualizar_tipo_envase.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Tipo de Envase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editTipoId">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Nombre: <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="nombre" id="editTipoNombre"
                                   maxlength="100" required>
                        </div>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="activo" id="editTipoActivo" value="1">
                            <label class="form-check-label" for="editTipoActivo">Activo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================== MODAL: Editar Especificación ================== -->
    <div class="modal fade" id="modalEditarEspec" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/actualizar_especificacion.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Especificación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editEspecId">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Nombre: <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="nombre" id="editEspecNombre"
                                   maxlength="150" required>
                        </div>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="activo" id="editEspecActivo" value="1">
                            <label class="form-check-label" for="editEspecActivo">Activa</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================== MODAL: Agregar Especificación rápido ================== -->
    <div class="modal fade" id="modalAgregarEspec" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/guardar_tipo_envase.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar Especificación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="modo" value="existente">
                        <input type="hidden" name="tipo_id" id="rapidoTipoId">
                        <div class="mb-3">
                            <strong>Tipo:</strong> <span id="rapidoTipoNombre" class="text-primary"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Especificación: <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="especificacion" id="rapidoEspec"
                                   maxlength="150" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formularios silenciosos para eliminación -->
    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/eliminar_tipo_envase.php"
          id="formEliminarTipo" style="display:none;">
        <input type="hidden" name="id" id="eliminarTipoId">
    </form>
    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/eliminar_especificacion.php"
          id="formEliminarEspec" style="display:none;">
        <input type="hidden" name="id" id="eliminarEspecId">
    </form>
    <?php endif; // fin bloque solo Logística ?>

    <!-- Toast de mensajes flash -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
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
    (function () {
        // Alternar modo del modal principal
        const radioNuevo = document.getElementById('modoNuevo');
        const radioExist = document.getElementById('modoExistente');
        const colNuevo   = document.getElementById('colTipoNuevo');
        const colExist   = document.getElementById('colTipoExistente');
        const inpNuevo   = document.getElementById('inputTipoNuevo');
        const selExist   = document.getElementById('selectTipoExistente');

        function aplicarModo() {
            if (radioNuevo.checked) {
                colNuevo.style.display = '';
                colExist.style.display = 'none';
                inpNuevo.required = true;
                selExist.required = false;
                selExist.value = '';
            } else {
                colNuevo.style.display = 'none';
                colExist.style.display = '';
                inpNuevo.required = false;
                selExist.required = true;
                inpNuevo.value = '';
            }
        }
        radioNuevo.addEventListener('change', aplicarModo);
        radioExist.addEventListener('change', aplicarModo);

        // Reset al cerrar modal
        document.getElementById('modalNuevoTipo').addEventListener('hidden.bs.modal', function () {
            radioNuevo.checked = true;
            aplicarModo();
            document.getElementById('formNuevoTipo').reset();
            radioNuevo.checked = true;
            aplicarModo();
        });
    })();

    function editarTipo(id, nombre, activo) {
        document.getElementById('editTipoId').value = id;
        document.getElementById('editTipoNombre').value = nombre;
        document.getElementById('editTipoActivo').checked = (activo === 1 || activo === '1');
        new bootstrap.Modal(document.getElementById('modalEditarTipo')).show();
    }

    function editarEspec(id, nombre, activo) {
        document.getElementById('editEspecId').value = id;
        document.getElementById('editEspecNombre').value = nombre;
        document.getElementById('editEspecActivo').checked = (activo === 1 || activo === '1');
        new bootstrap.Modal(document.getElementById('modalEditarEspec')).show();
    }

    function agregarEspecRapido(tipoId, tipoNombre) {
        document.getElementById('rapidoTipoId').value = tipoId;
        document.getElementById('rapidoTipoNombre').textContent = tipoNombre;
        document.getElementById('rapidoEspec').value = '';
        new bootstrap.Modal(document.getElementById('modalAgregarEspec')).show();
    }

    function eliminarTipo(id, nombre) {
        if (confirm('¿Eliminar el tipo "' + nombre + '"?\n\nEsto también eliminará todas sus especificaciones.')) {
            document.getElementById('eliminarTipoId').value = id;
            document.getElementById('formEliminarTipo').submit();
        }
    }

    function eliminarEspec(id, nombre) {
        if (confirm('¿Eliminar la especificación "' + nombre + '"?')) {
            document.getElementById('eliminarEspecId').value = id;
            document.getElementById('formEliminarEspec').submit();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>