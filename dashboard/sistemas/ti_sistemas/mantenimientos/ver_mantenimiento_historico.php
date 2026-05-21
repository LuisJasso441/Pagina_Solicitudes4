<?php
/**
 * Ver Mantenimiento Histórico - Vista detalle simplificada
 * dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento_historico.php
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento     = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti            = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_ti) {
    establecer_alerta('error', 'No tiene permisos para acceder a esta función.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    establecer_alerta('error', 'Registro inválido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$pdo = conectarDB();
$stmt = $pdo->prepare("
    SELECT sm.*,
        u.nombre_completo  AS usuario_nombre,
        d.nombre           AS depto_nombre,
        ie.hostname        AS equipo_hostname,
        ie.codigo_interno  AS equipo_codigo,
        ie.tipo_equipo     AS equipo_tipo,
        ie.ubicacion       AS equipo_ubicacion,
        creador.nombre_completo AS creador_nombre
    FROM solicitudes_mantenimiento_ti sm
    LEFT JOIN usuarios u            ON sm.usuario_id      = u.id
    LEFT JOIN departamentos d       ON sm.departamento_id = d.id
    LEFT JOIN inventario_equipos ie ON sm.equipo_id       = ie.id
    LEFT JOIN usuarios creador      ON sm.creado_por      = creador.id
    WHERE sm.id = ? AND sm.es_historico = 1
");
$stmt->execute([$id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    establecer_alerta('error', 'Registro histórico no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($registro['folio']); ?> - Histórico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">

    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
</head>
<body>

<div class="dashboard-container">

    <?php
    if ($es_mantenimiento) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_mantenimiento.php';
    } elseif ($es_ti) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_ti.php';
    } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_colaborativo.php';
    } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_gth.php';
    } else {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_normal.php';
    }
    ?>

    <main class="main-content">
        <div class="content-wrapper">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" class="text-decoration-none">
                                    Mantenimientos
                                </a>
                            </li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($registro['folio']); ?></li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">
                        <i class="bi bi-clock-history text-info"></i>
                        <?php echo htmlspecialchars($registro['folio']); ?>
                        <span class="badge bg-info ms-2"><i class="bi bi-clock-history"></i> Histórico</span>
                    </h2>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/editar_mantenimiento_historico.php?id=<?php echo $registro['id']; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                    <button type="button" class="btn btn-danger"
                            onclick="confirmarEliminar(<?php echo $registro['id']; ?>, '<?php echo htmlspecialchars($registro['folio'], ENT_QUOTES); ?>')">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
            </div>

            <?php echo mostrar_alerta(); ?>

            <div class="alert alert-info border-0 mb-3">
                <i class="bi bi-info-circle"></i>
                <strong>Registro histórico</strong> — Este mantenimiento se registró directamente sin pasar por el flujo normal.
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="bi bi-pc-display text-primary"></i> Equipo</h6></div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" style="width:40%;">ID Equipo:</td><td class="fw-semibold"><?php echo htmlspecialchars($registro['equipo_hostname'] ?: $registro['equipo_codigo'] ?: '—'); ?></td></tr>
                                <tr><td class="text-muted">Tipo:</td><td><?php echo htmlspecialchars(ucfirst($registro['equipo_tipo'] ?? '—')); ?></td></tr>
                                <tr><td class="text-muted">Ubicación:</td><td><?php echo htmlspecialchars($registro['equipo_ubicacion'] ?: '—'); ?></td></tr>
                                <tr><td class="text-muted">Departamento:</td><td><?php echo htmlspecialchars($registro['depto_nombre'] ?: '—'); ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="bi bi-tools text-info"></i> Datos del mantenimiento</h6></div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" style="width:40%;">Tipo:</td><td>
                                    <?php if ($registro['tipo_mantenimiento'] === 'fisico'): ?>
                                        <span class="badge bg-primary"><i class="bi bi-tools"></i> Físico</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="bi bi-cpu"></i> Lógico</span>
                                    <?php endif; ?>
                                </td></tr>
                                <tr><td class="text-muted">Fecha realizado:</td><td class="fw-semibold"><?php echo date('d/m/Y', strtotime($registro['fecha_finalizacion'])); ?></td></tr>
                                <tr><td class="text-muted">Estado:</td><td><span class="badge bg-secondary">Cerrada</span></td></tr>
                                <tr><td class="text-muted">Usuario asignado:</td><td>
                                    <?php echo $registro['usuario_nombre'] ? htmlspecialchars($registro['usuario_nombre']) : '<span class="text-muted fst-italic">Sin asignar</span>'; ?>
                                </td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light"><h6 class="mb-0"><i class="bi bi-file-text text-secondary"></i> Descripción del trabajo realizado</h6></div>
                        <div class="card-body">
                            <?php if (!empty($registro['descripcion_solucion'])): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($registro['descripcion_solucion'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted fst-italic mb-0">Sin descripción registrada.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body py-2">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                Creado el <strong><?php echo date('d/m/Y H:i', strtotime($registro['fecha_solicitud'])); ?></strong>
                                <?php if ($registro['creador_nombre']): ?>
                                    por <strong><?php echo htmlspecialchars($registro['creador_nombre']); ?></strong>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/procesar_eliminar_historico.php">
                <div class="modal-body">
                    <p>¿Eliminar el registro histórico <strong id="folioEliminar"></strong>?</p>
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                    <input type="hidden" name="id" id="idEliminar">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Eliminar definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script>
function confirmarEliminar(id, folio) {
    document.getElementById('idEliminar').value = id;
    document.getElementById('folioEliminar').textContent = folio;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>

</body>
</html>