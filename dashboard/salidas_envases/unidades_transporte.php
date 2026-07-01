<?php
/**
 * Gestión de Unidades de Transporte
 * Módulo SEC — Salidas de Envases para Clientes
 *
 * Ubicación: dashboard/salidas_envases/unidades_transporte.php
 *
 * Exclusivo para Logística.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/unidades_transporte_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado por inactividad. Por favor inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

// Acceso: solo Logística
if (!es_logistica()) {
    establecer_alerta('error', 'Sólo el departamento de Logística puede gestionar Unidades de Transporte.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id     = $_SESSION['usuario_id'];

// Filtro: mostrar inactivas
$mostrar_inactivas = isset($_GET['inactivas']) && $_GET['inactivas'] == '1';
$unidades = obtener_unidades_transporte(!$mostrar_inactivas);

// Conteo de usos por unidad (para mostrar advertencia al desactivar)
$usos_por_unidad = [];
foreach ($unidades as $u) {
    $usos_por_unidad[$u['id']] = contar_usos_unidad_transporte($u['id']);
}

// Mensajes flash
$mensajes = [
    'creada'         => ['success', 'Unidad de transporte creada correctamente.'],
    'actualizada'    => ['success', 'Unidad de transporte actualizada correctamente.'],
    'desactivada'    => ['warning', 'Unidad de transporte desactivada.'],
    'reactivada'     => ['success', 'Unidad de transporte reactivada.'],
    'error'          => ['danger',  'Ocurrió un error al procesar la solicitud.'],
    'error_validacion' => ['danger', 'Hay errores de validación. Revisa los campos.'],
];
$msg_flash = $_GET['msg'] ?? null;
$errores_flash = $_SESSION['unidad_errores'] ?? [];
unset($_SESSION['unidad_errores']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades de Transporte | <?php echo NOMBRE_SISTEMA; ?></title>
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
        .capacity-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
        }
        .capacity-cell {
            text-align: center;
            padding: 0.4rem 0.25rem;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .capacity-cell .label {
            font-size: 0.65rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .capacity-cell .value {
            font-weight: 600;
            color: #14b8a6;
            font-size: 1rem;
        }
        .badge-inactiva {
            background-color: #6c757d;
        }
        .placa-cell {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            letter-spacing: 1px;
        }
        @media (max-width: 768px) {
            .capacity-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        include __DIR__ . '/../../includes/sidebar/sidebar_sec.php';
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <!-- Encabezado -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-truck"></i> Unidades de Transporte</h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Gestión de unidades disponibles para Salidas de Envases (SEC)
                            </p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUnidad" onclick="abrirModalNueva()">
                                <i class="bi bi-plus-circle me-1"></i> Nueva Unidad
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mensajes flash -->
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

                <!-- Filtros -->
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="text-muted me-2">
                                    <i class="bi bi-list-ul"></i> Total: <strong><?php echo count($unidades); ?></strong> unidades
                                </span>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggleInactivas"
                                       <?php echo $mostrar_inactivas ? 'checked' : ''; ?>
                                       onchange="window.location='?inactivas=<?php echo $mostrar_inactivas ? '0' : '1'; ?>'">
                                <label class="form-check-label" for="toggleInactivas">
                                    Mostrar inactivas
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Nombre</th>
                                        <th>Placas</th>
                                        <th style="min-width: 280px;">Capacidad por tipo de envase</th>
                                        <th class="text-center" style="width: 110px;">Estado</th>
                                        <th class="text-center" style="width: 140px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($unidades)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox" style="font-size: 2rem;"></i><br>
                                                No hay unidades registradas. Crea la primera con el botón <strong>Nueva Unidad</strong>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($unidades as $u): ?>
                                            <tr <?php echo $u['activa'] == 0 ? 'class="table-secondary"' : ''; ?>>
                                                <td><?php echo (int)$u['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($u['nombre']); ?></strong>
                                                </td>
                                                <td class="placa-cell"><?php echo htmlspecialchars($u['placas']); ?></td>
                                                <td>
                                                    <div class="capacity-grid">
                                                        <div class="capacity-cell">
                                                            <span class="label">TMB</span>
                                                            <span class="value"><?php echo (int)$u['capacidad_tmb']; ?></span>
                                                        </div>
                                                        <div class="capacity-cell">
                                                            <span class="label">TOTE</span>
                                                            <span class="value"><?php echo (int)$u['capacidad_tote']; ?></span>
                                                        </div>
                                                        <div class="capacity-cell">
                                                            <span class="label">GFA</span>
                                                            <span class="value"><?php echo (int)$u['capacidad_gfa']; ?></span>
                                                        </div>
                                                        <div class="capacity-cell">
                                                            <span class="label">JAULA</span>
                                                            <span class="value"><?php echo (int)$u['capacidad_jaula']; ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($u['activa'] == 1): ?>
                                                        <span class="badge bg-success">Activa</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-inactiva">Inactiva</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary"
                                                                title="Editar"
                                                                data-bs-toggle="modal" data-bs-target="#modalUnidad"
                                                                onclick='abrirModalEditar(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($u['activa'] == 1): ?>
                                                            <button type="button" class="btn btn-outline-warning"
                                                                    title="Desactivar"
                                                                    onclick="confirmarDesactivar(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>', <?php echo (int)$usos_por_unidad[$u['id']]; ?>)">
                                                                <i class="bi bi-slash-circle"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-success"
                                                                    title="Reactivar"
                                                                    onclick="confirmarReactivar(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['nombre'], ENT_QUOTES); ?>')">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: Crear / Editar Unidad                                           -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalUnidad" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/guardar_unidad_transporte.php" method="POST" id="formUnidad" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalUnidadTitulo">
                            <i class="bi bi-truck"></i> Nueva Unidad de Transporte
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="id" value="">

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Nombre de la unidad <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required>
                                <small class="text-muted">Ejemplo: Camión 1, Pickup Norte, Furgón Ruta A.</small>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Placas <span class="text-danger">*</span></label>
                                <input type="text" name="placas" id="placas" class="form-control text-uppercase placa-cell" maxlength="20" required
                                       style="letter-spacing: 1px;">
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3"><i class="bi bi-box-seam me-1"></i> Capacidad permitida por tipo de envase</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label">TMB <small class="text-muted">(Tambo)</small></label>
                                <input type="number" name="capacidad_tmb" id="capacidad_tmb" class="form-control" min="0" step="1" value="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">TOTE</label>
                                <input type="number" name="capacidad_tote" id="capacidad_tote" class="form-control" min="0" step="1" value="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">GFA <small class="text-muted">(Garrafa)</small></label>
                                <input type="number" name="capacidad_gfa" id="capacidad_gfa" class="form-control" min="0" step="1" value="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">JAULA</label>
                                <input type="number" name="capacidad_jaula" id="capacidad_jaula" class="form-control" min="0" step="1" value="0" required>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Si la unidad no permite cierto tipo de envase, deja el valor en 0.
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> <span id="btnGuardarTxt">Crear unidad</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- FORMS OCULTOS: desactivar / reactivar                                  -->
    <!-- ===================================================================== -->
    <form id="formDesactivar" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/eliminar_unidad_transporte.php" method="POST" style="display:none;">
        <input type="hidden" name="accion" value="desactivar">
        <input type="hidden" name="id" id="desactivarId" value="">
    </form>
    <form id="formReactivar" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/eliminar_unidad_transporte.php" method="POST" style="display:none;">
        <input type="hidden" name="accion" value="reactivar">
        <input type="hidden" name="id" id="reactivarId" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
        function abrirModalNueva() {
            document.getElementById('formUnidad').reset();
            document.getElementById('accion').value = 'crear';
            document.getElementById('id').value = '';
            document.getElementById('modalUnidadTitulo').innerHTML = '<i class="bi bi-truck"></i> Nueva Unidad de Transporte';
            document.getElementById('btnGuardarTxt').textContent = 'Crear unidad';
        }

        function abrirModalEditar(u) {
            document.getElementById('formUnidad').reset();
            document.getElementById('accion').value = 'editar';
            document.getElementById('id').value = u.id;
            document.getElementById('nombre').value = u.nombre;
            document.getElementById('placas').value = u.placas;
            document.getElementById('capacidad_tmb').value   = u.capacidad_tmb;
            document.getElementById('capacidad_tote').value  = u.capacidad_tote;
            document.getElementById('capacidad_gfa').value   = u.capacidad_gfa;
            document.getElementById('capacidad_jaula').value = u.capacidad_jaula;
            document.getElementById('modalUnidadTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Unidad: ' + u.nombre;
            document.getElementById('btnGuardarTxt').textContent = 'Guardar cambios';
        }

        function confirmarDesactivar(id, nombre, usos) {
            let msg = '¿Desactivar la unidad "' + nombre + '"?\n\n';
            if (usos > 0) {
                msg += '⚠️ Esta unidad está asignada en ' + usos + ' línea(s) de SEC históricas. ';
                msg += 'Esas referencias se mantendrán intactas, pero la unidad ya no aparecerá disponible para nuevas SEC.';
            } else {
                msg += 'Podrás reactivarla después si lo necesitas.';
            }
            if (confirm(msg)) {
                document.getElementById('desactivarId').value = id;
                document.getElementById('formDesactivar').submit();
            }
        }

        function confirmarReactivar(id, nombre) {
            if (confirm('¿Reactivar la unidad "' + nombre + '"?')) {
                document.getElementById('reactivarId').value = id;
                document.getElementById('formReactivar').submit();
            }
        }
    </script>
</body>
</html>