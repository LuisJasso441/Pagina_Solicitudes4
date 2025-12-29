<?php
/**
 * Ver Detalles de Equipo
 * Solo accesible para usuarios del departamento de Sistemas
 * dashboard/sistemas/ti_sistemas/ver_equipo.php
 * 
 * ⚠️ CORREGIDO para coincidir con estructura real de tabla inventario_equipos
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta sección.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Obtener ID del equipo
$equipo_id = intval($_GET['id'] ?? 0);

if (!$equipo_id) {
    establecer_alerta('error', 'ID de equipo no válido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener datos del equipo
$sql = "SELECT ie.*, 
               d.nombre as departamento_nombre,
               u1.nombre_completo as registrado_por_nombre,
               ua.nombre_completo as usuario_asignado_nombre
        FROM inventario_equipos ie
        LEFT JOIN departamentos d ON ie.departamento_id = d.id
        LEFT JOIN usuarios u1 ON ie.registrado_por = u1.id
        LEFT JOIN usuarios ua ON ie.usuario_asignado_id = ua.id
        WHERE ie.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    establecer_alerta('error', 'Equipo no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

// Obtener historial de mantenimientos del equipo
$sql_mantenimientos = "SELECT sm.*, u.nombre_completo as solicitante_nombre
                       FROM solicitudes_mantenimiento_ti sm
                       LEFT JOIN usuarios u ON sm.usuario_id = u.id
                       WHERE sm.equipo_id = ?
                       ORDER BY sm.fecha_solicitud DESC
                       LIMIT 10";
$stmt = $pdo->prepare($sql_mantenimientos);
$stmt->execute([$equipo_id]);
$mantenimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nombres y configuraciones
$tipos_nombres = [
    'computadora' => 'Computadora',
    'impresora' => 'Impresora',
    'camara' => 'Cámara',
    'telefono' => 'Teléfono'
];
$iconos_tipo = [
    'computadora' => 'bi-pc-display text-primary',
    'impresora' => 'bi-printer text-purple',
    'camara' => 'bi-camera-video text-success',
    'telefono' => 'bi-phone text-orange'
];
$badges_estado = [
    'activo' => 'bg-success',
    'inactivo' => 'bg-secondary',
    'en_reparacion' => 'bg-warning text-dark',
    'dado_de_baja' => 'bg-danger'
];
$nombres_estado = [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo',
    'en_reparacion' => 'En Reparación',
    'dado_de_baja' => 'Dado de Baja'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipo['codigo_interno']); ?> - Inventario TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        .detail-card {
            border-left: 4px solid #0d6efd;
        }
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        .detail-value {
            font-weight: 500;
        }
        .equipo-icon {
            font-size: 3rem;
        }
        .text-purple { color: #6f42c1; }
        .text-orange { color: #fd7e14; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="row mb-4">
                    <div class="col">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i>
                                </a>
                                <h4 class="mb-0">
                                    <i class="bi <?php echo $iconos_tipo[$equipo['tipo_equipo']] ?? 'bi-device'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($equipo['codigo_interno']); ?>
                                </h4>
                                <span class="badge <?php echo $badges_estado[$equipo['estado']]; ?>">
                                    <?php echo $nombres_estado[$equipo['estado']]; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $equipo['id']; ?>" 
                                   class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" 
                                        onclick="confirmarEliminar(<?php echo $equipo['id']; ?>, '<?php echo htmlspecialchars($equipo['codigo_interno'], ENT_QUOTES); ?>')">
                                    <i class="bi bi-trash me-1"></i>Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Información Principal -->
                    <div class="col-lg-8">
                        <!-- Datos del Equipo -->
                        <div class="card detail-card mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información del Equipo</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="detail-label">Tipo de Equipo</div>
                                        <div class="detail-value">
                                            <?php echo $tipos_nombres[$equipo['tipo_equipo']] ?? $equipo['tipo_equipo']; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Código Interno</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['codigo_interno']); ?></div>
                                    </div>
                                    <?php if ($equipo['numero_serie']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Número de Serie</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['numero_serie']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($equipo['marca']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Marca</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['marca']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($equipo['modelo']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Modelo</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['modelo']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Estado</div>
                                        <div class="detail-value">
                                            <span class="badge <?php echo $badges_estado[$equipo['estado']]; ?>">
                                                <?php echo $nombres_estado[$equipo['estado']]; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ubicación y Asignación -->
                        <div class="card detail-card mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Ubicación y Asignación</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="detail-label">Ubicación Física</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['ubicacion']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Departamento</div>
                                        <div class="detail-value">
                                            <?php echo $equipo['departamento_nombre'] ? htmlspecialchars($equipo['departamento_nombre']) : '<span class="text-muted">Sin asignar</span>'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Usuario Responsable</div>
                                        <div class="detail-value">
                                            <?php echo $equipo['usuario_asignado_nombre'] ? htmlspecialchars($equipo['usuario_asignado_nombre']) : '<span class="text-muted">Sin asignar</span>'; ?>
                                        </div>
                                    </div>
                                    <?php if ($equipo['correo_asignado']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Correo Electrónico Asignado</div>
                                        <div class="detail-value">
                                            <a href="mailto:<?php echo htmlspecialchars($equipo['correo_asignado']); ?>">
                                                <?php echo htmlspecialchars($equipo['correo_asignado']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($equipo['fecha_adquisicion']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Fecha de Adquisición</div>
                                        <div class="detail-value"><?php echo date('d/m/Y', strtotime($equipo['fecha_adquisicion'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($equipo['notas']): ?>
                        <div class="card detail-card mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Notas</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($equipo['notas'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Historial de Mantenimientos -->
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-tools me-2"></i>Historial de Mantenimientos</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($mantenimientos)): ?>
                                    <p class="text-muted text-center mb-0">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        No hay mantenimientos registrados para este equipo.
                                    </p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Folio</th>
                                                    <th>Fecha</th>
                                                    <th>Solicitante</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mantenimientos as $mant): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_mantenimiento.php?id=<?php echo $mant['id']; ?>">
                                                                <?php echo htmlspecialchars($mant['folio']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($mant['fecha_solicitud'])); ?></td>
                                                        <td><?php echo htmlspecialchars($mant['solicitante_nombre']); ?></td>
                                                        <td>
                                                            <?php
                                                            $badges_mant = [
                                                                'pendiente' => 'bg-warning text-dark',
                                                                'en_proceso' => 'bg-primary',
                                                                'finalizado' => 'bg-success',
                                                                'cancelado' => 'bg-danger'
                                                            ];
                                                            ?>
                                                            <span class="badge <?php echo $badges_mant[$mant['estado']] ?? 'bg-secondary'; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $mant['estado'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panel Lateral -->
                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Registro</h6>
                            </div>
                            <div class="card-body small">
                                <div class="mb-3">
                                    <div class="detail-label">Registrado por</div>
                                    <div><?php echo htmlspecialchars($equipo['registrado_por_nombre']); ?></div>
                                    <div class="text-muted"><?php echo date('d/m/Y H:i', strtotime($equipo['fecha_registro'])); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $equipo['id']; ?>" 
                                       class="btn btn-outline-warning btn-sm">
                                        <i class="bi bi-pencil me-1"></i>Editar Equipo
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php?equipo_id=<?php echo $equipo['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-tools me-1"></i>Registrar Mantenimiento
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>

    </div>
    
    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el equipo <strong id="codigoEquipoEliminar"></strong>?</p>
                    <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="formEliminar" method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_equipo.php" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="equipo_id" id="equipoIdEliminar">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        function confirmarEliminar(id, codigo) {
            document.getElementById('equipoIdEliminar').value = id;
            document.getElementById('codigoEquipoEliminar').textContent = codigo;
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>