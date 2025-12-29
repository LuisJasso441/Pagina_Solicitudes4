<?php
/**
 * Ver Detalles de Solicitud de Mantenimiento
 * dashboard/sistemas/ti_sistemas/ver_mantenimiento.php
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

// Obtener ID de la solicitud
$solicitud_id = intval($_GET['id'] ?? 0);

if (!$solicitud_id) {
    establecer_alerta('error', 'ID de solicitud no válido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// ⭐ DETECCIÓN DE DEPARTAMENTO - Igual que en ordenes_servicio_mantenimiento.php
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti = ($departamento === 'ti' || $departamento === 'sistemas' || $departamento === 'ti_sistemas');

// Obtener datos de la solicitud
$sql = "SELECT sm.*, 
               u.nombre_completo as solicitante_nombre,
               d.nombre as departamento_nombre,
               ua.nombre_completo as atendido_por_nombre,
               ie.codigo_interno as equipo_codigo,
               ie.marca as equipo_marca,
               ie.modelo as equipo_modelo
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN usuarios u ON sm.usuario_id = u.id
        LEFT JOIN departamentos d ON sm.departamento_id = d.id
        LEFT JOIN usuarios ua ON sm.atendido_por = ua.id
        LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
        WHERE sm.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$solicitud_id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    establecer_alerta('error', 'Solicitud no encontrada.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

// Si NO es Sistemas, verificar que sea su propia solicitud
if (!$es_ti && $solicitud['usuario_id'] != $_SESSION['usuario_id']) {
    establecer_alerta('error', 'No tiene permisos para ver esta solicitud.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

// Obtener historial de la solicitud
$sql_historial = "SELECT h.*, u.nombre_completo as usuario_nombre
                  FROM historial_mantenimientos_ti h
                  LEFT JOIN usuarios u ON h.usuario_id = u.id
                  WHERE h.solicitud_id = ?
                  ORDER BY h.fecha_cambio DESC";
$stmt = $pdo->prepare($sql_historial);
$stmt->execute([$solicitud_id]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuraciones de visualización
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
    'pendiente' => 'bg-warning text-dark',
    'en_proceso' => 'bg-primary',
    'finalizado' => 'bg-success',
    'cancelado' => 'bg-danger'
];
$nombres_estado = [
    'pendiente' => 'Pendiente',
    'en_proceso' => 'En Proceso',
    'finalizado' => 'Finalizado',
    'cancelado' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($solicitud['folio']); ?> - Mantenimiento TI</title>
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
            border-left: 4px solid;
        }
        .detail-card.pendiente { border-color: #ffc107; }
        .detail-card.en_proceso { border-color: #0d6efd; }
        .detail-card.finalizado { border-color: #198754; }
        .detail-card.cancelado { border-color: #dc3545; }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        .detail-value {
            font-weight: 500;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #0d6efd;
        }
        .timeline-item.first::before {
            background: #198754;
            box-shadow: 0 0 0 2px #198754;
        }
        
        .text-purple { color: #6f42c1; }
        .text-orange { color: #fd7e14; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR - Usando la lógica exacta del proyecto -->
        <?php 
        if ($es_mantenimiento) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="row mb-4">
                    <div class="col">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i>
                                </a>
                                <h4 class="mb-0">
                                    <i class="bi bi-tools me-2"></i>
                                    <?php echo htmlspecialchars($solicitud['folio']); ?>
                                </h4>
                                <span class="badge <?php echo $badges_estado[$solicitud['estado']]; ?>">
                                    <?php echo $nombres_estado[$solicitud['estado']]; ?>
                                </span>
                            </div>
                            <?php if ($es_ti && $solicitud['estado'] !== 'finalizado' && $solicitud['estado'] !== 'cancelado'): ?>
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="abrirModalEstado()">
                                <i class="bi bi-arrow-repeat me-1"></i>Cambiar Estado
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Información Principal -->
                    <div class="col-lg-8">
                        <!-- Datos de la Solicitud -->
                        <div class="card detail-card <?php echo $solicitud['estado']; ?> mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Información de la Solicitud</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="detail-label">Tipo de Equipo</div>
                                        <div class="detail-value">
                                            <i class="bi <?php echo $iconos_tipo[$solicitud['tipo_equipo']] ?? 'bi-device'; ?> me-1"></i>
                                            <?php echo $tipos_nombres[$solicitud['tipo_equipo']] ?? $solicitud['tipo_equipo']; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Tipo de Mantenimiento</div>
                                        <div class="detail-value">
                                            <span class="badge bg-<?php echo $solicitud['tipo_mantenimiento'] === 'preventivo' ? 'info' : 'secondary'; ?>">
                                                <?php echo ucfirst($solicitud['tipo_mantenimiento']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($solicitud['equipo_codigo']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Equipo del Inventario</div>
                                        <div class="detail-value">
                                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $solicitud['equipo_id']; ?>">
                                                <?php echo htmlspecialchars($solicitud['equipo_codigo']); ?>
                                            </a>
                                            <?php if ($solicitud['equipo_marca'] || $solicitud['equipo_modelo']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars(trim($solicitud['equipo_marca'] . ' ' . $solicitud['equipo_modelo'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <div class="detail-label">Fecha de Solicitud</div>
                                        <div class="detail-value">
                                            <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descripción del Problema -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Descripción del Problema</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['descripcion_problema'])); ?></p>
                            </div>
                        </div>
                        
                        <!-- Solución (si existe) -->
                        <?php if ($solicitud['descripcion_solucion']): ?>
                        <div class="card mb-4 border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Solución Aplicada</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['descripcion_solucion'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Observaciones de Sistemas (solo visible para Sistemas) -->
                        <?php if ($es_ti && $solicitud['observaciones_sistemas']): ?>
                        <div class="card mb-4 border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Observaciones Internas</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['observaciones_sistemas'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Historial de Cambios -->
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($historial)): ?>
                                    <p class="text-muted text-center mb-0">No hay historial disponible.</p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($historial as $index => $item): ?>
                                            <div class="timeline-item <?php echo $index === 0 ? 'first' : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong>
                                                            <?php 
                                                            if ($item['estado_anterior']) {
                                                                echo $nombres_estado[$item['estado_anterior']] . ' → ';
                                                            }
                                                            echo $nombres_estado[$item['estado_nuevo']];
                                                            ?>
                                                        </strong>
                                                        <?php if ($item['comentario']): ?>
                                                            <p class="text-muted small mb-0 mt-1">
                                                                <?php echo htmlspecialchars($item['comentario']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($item['fecha_cambio'])); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($item['usuario_nombre']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panel Lateral -->
                    <div class="col-lg-4">
                        <!-- Solicitante -->
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Solicitante</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></strong></p>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($solicitud['departamento_nombre']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Atendido por -->
                        <?php if ($solicitud['atendido_por_nombre']): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-person-gear me-2"></i>Atendido por</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong><?php echo htmlspecialchars($solicitud['atendido_por_nombre']); ?></strong></p>
                                <?php if ($solicitud['fecha_atencion']): ?>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-calendar me-1"></i>
                                    Desde: <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_atencion'])); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Fechas -->
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Fechas</h6>
                            </div>
                            <div class="card-body small">
                                <div class="mb-2">
                                    <div class="detail-label">Solicitud</div>
                                    <div><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></div>
                                </div>
                                <?php if ($solicitud['fecha_atencion']): ?>
                                <div class="mb-2">
                                    <div class="detail-label">Inicio de Atención</div>
                                    <div><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_atencion'])); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($solicitud['fecha_finalizacion']): ?>
                                <div>
                                    <div class="detail-label">Finalización</div>
                                    <div><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_finalizacion'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Tiempo de resolución -->
                        <?php if ($solicitud['fecha_finalizacion']): 
                            $inicio = new DateTime($solicitud['fecha_solicitud']);
                            $fin = new DateTime($solicitud['fecha_finalizacion']);
                            $diff = $inicio->diff($fin);
                        ?>
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-stopwatch me-2"></i>Tiempo de Resolución</h6>
                            </div>
                            <div class="card-body text-center">
                                <h4 class="mb-0">
                                    <?php 
                                    if ($diff->days > 0) {
                                        echo $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
                                    }
                                    if ($diff->h > 0) {
                                        echo ' ' . $diff->h . 'h';
                                    }
                                    if ($diff->i > 0) {
                                        echo ' ' . $diff->i . 'min';
                                    }
                                    if ($diff->days == 0 && $diff->h == 0 && $diff->i == 0) {
                                        echo '< 1 min';
                                    }
                                    ?>
                                </h4>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </main>

    </div>
    
    <?php if ($es_ti && $solicitud['estado'] !== 'finalizado' && $solicitud['estado'] !== 'cancelado'): ?>
    <!-- Modal para cambiar estado -->
    <div class="modal fade" id="modalEstado" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Actualizar Estado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_mantenimiento.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="cambiar_estado">
                        <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_id; ?>">
                        
                        <p class="mb-3">Solicitud: <strong><?php echo htmlspecialchars($solicitud['folio']); ?></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Nuevo Estado</label>
                            <select name="nuevo_estado" class="form-select" required>
                                <?php if ($solicitud['estado'] === 'pendiente'): ?>
                                    <option value="en_proceso" selected>En Proceso</option>
                                <?php endif; ?>
                                <?php if ($solicitud['estado'] !== 'finalizado'): ?>
                                    <option value="finalizado">Finalizado</option>
                                <?php endif; ?>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción / Observaciones</label>
                            <textarea name="descripcion" class="form-control" rows="4" 
                                      placeholder="Describa el trabajo realizado o el motivo del cambio..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Guardar Cambio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <?php if ($es_ti && $solicitud['estado'] !== 'finalizado' && $solicitud['estado'] !== 'cancelado'): ?>
    <script>
        function abrirModalEstado() {
            new bootstrap.Modal(document.getElementById('modalEstado')).show();
        }
    </script>
    <?php endif; ?>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>