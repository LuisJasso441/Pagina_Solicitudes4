<?php
/**
 * Ver Solicitud de Vacaciones - Vista de detalle
 * dashboard/vacaciones/ver_solicitud_vacaciones.php
 * 
 * Muestra: datos completos, firmas, timeline de aprobaciones
 * Admin de Área: puede aprobar/rechazar con firma si estado = pendiente_admin
 * GTH: puede aprobar/rechazar con firma si estado = pendiente_gth
 * Empleado: puede cancelar si estado = pendiente_admin
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$pdo = conectarDB();
$solicitud_id = intval($_GET['id'] ?? 0);

if ($solicitud_id <= 0) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'ID de solicitud inválido.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

$sol = obtener_solicitud_vacaciones($pdo, $solicitud_id);

if (!$sol) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solicitud no encontrada.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

// Verificar permisos
$es_dueno = ((int)$sol['usuario_id'] === (int)$_SESSION['usuario_id']);
$es_admin_area = (!empty($_SESSION['es_admin_area']) && (int)$_SESSION['departamento_id'] === (int)$sol['departamento_id']);
$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_gth = in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad']);
$es_sistemas = in_array($depto_codigo, ['sistemas', 'ti']);

if (!$es_dueno && !$es_admin_area && !$es_gth && !$es_sistemas) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permiso para ver esta solicitud.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

// ¿Quién puede actuar?
$puede_aprobar_admin = ($es_admin_area && $sol['estado'] === 'pendiente_admin');
$puede_aprobar_gth = ($es_gth && $sol['estado'] === 'pendiente_gth');
$necesita_jsignature = ($puede_aprobar_admin || $puede_aprobar_gth);

// Calcular antigüedad del solicitante
$antiguedad_texto = texto_antiguedad($sol['fecha_ingreso']);
$periodo = obtener_periodo_actual($sol['fecha_ingreso']);
$anio_periodo = $periodo ? $periodo['anio_periodo'] : 0;
$dias_lft = dias_vacaciones_lft($anio_periodo);

// Resumen de días del solicitante (para contexto del aprobador)
$resumen_solicitante = obtener_resumen_vacaciones($pdo, $sol['usuario_id']);

// Alertas
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

// Sidebar
$es_ti = ($depto_codigo === 'sistemas' || $depto_codigo === 'ti');
$es_mantenimiento = ($depto_codigo === 'mantenimiento');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud <?php echo htmlspecialchars($sol['folio']); ?> - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .detail-label {
            font-size: 0.78rem; font-weight: 600; text-transform: uppercase;
            color: #6c757d; letter-spacing: 0.3px; margin-bottom: 2px;
        }
        .detail-value { font-size: 0.95rem; color: #212529; }
        
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before {
            content: ''; position: absolute; left: 10px; top: 5px; bottom: 5px;
            width: 2px; background: #dee2e6;
        }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-dot {
            position: absolute; left: -25px; top: 3px; width: 14px; height: 14px;
            border-radius: 50%; border: 2px solid #dee2e6; background: #fff;
        }
        .timeline-dot.completado { background: #198754; border-color: #198754; }
        .timeline-dot.activo { background: #ffc107; border-color: #ffc107; animation: pulse 2s infinite; }
        .timeline-dot.rechazado { background: #dc3545; border-color: #dc3545; }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            50% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); }
        }
        .timeline-title { font-weight: 600; font-size: 0.9rem; }
        .timeline-sub { font-size: 0.8rem; color: #6c757d; }
        
        .firma-preview {
            border: 1px solid #dee2e6; border-radius: 8px; padding: 8px;
            background: #fff; text-align: center;
        }
        .firma-preview img { max-height: 100px; max-width: 100%; }
        .firma-placeholder { color: #adb5bd; font-size: 0.85rem; padding: 20px; }
        
        .folio-header {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: #fff; border-radius: 12px 12px 0 0; padding: 20px;
        }
        .folio-header .folio-text { font-size: 1.3rem; font-weight: 700; letter-spacing: 1px; }
        
        /* Action cards */
        .admin-action-card { border: 2px solid #ffc107; border-radius: 12px; background: #fffdf5; }
        .admin-action-card .card-header { background: #ffc107; color: #212529; font-weight: 600; border-radius: 10px 10px 0 0; }
        
        .gth-action-card { border: 2px solid #0d6efd; border-radius: 12px; background: #f0f4ff; }
        .gth-action-card .card-header { background: #0d6efd; color: #fff; font-weight: 600; border-radius: 10px 10px 0 0; }
        
        .firma-action-container {
            border: 2px dashed #ced4da; border-radius: 8px; background: #fff; min-height: 150px;
        }
        .firma-action-container.firmado { border-color: #198754; border-style: solid; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php
        if ($es_mantenimiento) {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_gth.php';
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>
        
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-file-earmark-text"></i> Solicitud de Vacaciones</h1>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                Detalle de la solicitud <?php echo htmlspecialchars($sol['folio']); ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($es_dueno && $sol['estado'] === 'pendiente_admin'): ?>
                            <button type="button" class="btn btn-outline-danger" id="btnCancelarEmpleado">
                                <i class="bi bi-x-circle me-1"></i> Cancelar Solicitud
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($es_gth): ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_gth.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Panel GTH
                            </a>
                            <?php elseif ($es_admin_area): ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Panel Admin
                            </a>
                            <?php else: ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Regresar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Alertas -->
                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $alerta['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row g-4">
                    <!-- Columna principal -->
                    <div class="col-lg-8">
                        
                        <!-- Card folio y estado -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="folio-header">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="folio-text"><?php echo htmlspecialchars($sol['folio']); ?></div>
                                        <small class="opacity-75">Creada el <?php echo fecha_larga_es($sol['fecha_creacion']); ?></small>
                                    </div>
                                    <div><?php echo badge_estado_vacaciones($sol['estado']); ?></div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                
                                <!-- Datos del empleado -->
                                <h6 class="mb-3"><i class="bi bi-person me-2 text-primary"></i>Datos del Empleado</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="detail-label">Nombre completo</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($sol['nombre_completo'] ?? $sol['nombre_manual'] ?? ''); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-label">No. Nómina</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($sol['no_nomina'] ?? $sol['no_nomina_manual'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-label">Departamento</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($sol['departamento_nombre']); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Puesto</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($sol['puesto'] ?? $sol['puesto_manual'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Fecha de ingreso</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($sol['fecha_ingreso'] ?? $sol['fecha_ingreso_manual'] ?? ''); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Antigüedad</div>
                                        <div class="detail-value"><?php echo $antiguedad_texto; ?> (<?php echo $dias_lft; ?> días LFT)</div>
                                    </div>
                                </div>
                                
                                <?php if (($puede_aprobar_admin || $puede_aprobar_gth) && $resumen_solicitante['tiene_fecha_ingreso']): ?>
                                <div class="alert alert-light border mb-4" style="font-size: 0.85rem;">
                                    <i class="bi bi-bar-chart me-1 text-primary"></i>
                                    <strong>Disponibilidad del empleado:</strong>
                                    <?php echo $resumen_solicitante['dias_correspondientes']; ?> días por periodo |
                                    <?php echo $resumen_solicitante['dias_tomados']; ?> tomados/solicitados |
                                    <strong class="text-success"><?php echo $resumen_solicitante['dias_disponibles']; ?> disponibles</strong>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <!-- Periodo solicitado -->
                                <h6 class="mb-3"><i class="bi bi-calendar-range me-2 text-success"></i>Periodo Solicitado</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <div class="detail-label">Fecha inicio</div>
                                        <div class="detail-value"><?php echo fecha_corta_es($sol['fecha_inicio']); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-label">Fecha fin</div>
                                        <div class="detail-value"><?php echo fecha_corta_es($sol['fecha_fin']); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-label">Días hábiles (L-S)</div>
                                        <div class="detail-value">
                                            <span class="badge bg-primary fs-6"><?php echo $sol['dias_solicitados']; ?> días</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-label">Estado actual</div>
                                        <div class="detail-value"><?php echo texto_estado_vacaciones($sol['estado']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($sol['motivo'])): ?>
                                <div class="mb-4">
                                    <div class="detail-label">Motivo</div>
                                    <div class="detail-value bg-light rounded p-3" style="font-size: 0.9rem;">
                                        <?php echo nl2br(htmlspecialchars($sol['motivo'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <!-- Firmas -->
                                <h6 class="mb-3"><i class="bi bi-pen me-2 text-info"></i>Firmas</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="detail-label">Firma del Empleado</div>
                                        <div class="firma-preview">
                                            <?php if (!empty($sol['firma_empleado'])): ?>
                                                <img src="<?php echo htmlspecialchars($sol['firma_empleado']); ?>" alt="Firma empleado">
                                                <div class="mt-1"><small class="text-muted"><?php echo fecha_corta_es($sol['fecha_firma_empleado']); ?></small></div>
                                            <?php else: ?>
                                                <div class="firma-placeholder">Sin firma</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Firma Admin. de Área</div>
                                        <div class="firma-preview">
                                            <?php if (!empty($sol['firma_admin'])): ?>
                                                <img src="<?php echo htmlspecialchars($sol['firma_admin']); ?>" alt="Firma admin">
                                                <div class="mt-1"><small class="text-muted"><?php echo htmlspecialchars($sol['admin_nombre']); ?><br><?php echo fecha_corta_es($sol['fecha_aprobacion_admin']); ?></small></div>
                                            <?php else: ?>
                                                <div class="firma-placeholder"><i class="bi bi-hourglass-split"></i> Pendiente</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Firma GTH</div>
                                        <div class="firma-preview">
                                            <?php if (!empty($sol['firma_gth'])): ?>
                                                <img src="<?php echo htmlspecialchars($sol['firma_gth']); ?>" alt="Firma GTH">
                                                <div class="mt-1"><small class="text-muted"><?php echo htmlspecialchars($sol['gth_nombre']); ?><br><?php echo fecha_corta_es($sol['fecha_aprobacion_gth']); ?></small></div>
                                            <?php else: ?>
                                                <div class="firma-placeholder"><i class="bi bi-hourglass-split"></i> Pendiente</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Comentarios de rechazo -->
                                <?php if (!empty($sol['comentarios_admin']) && $sol['estado'] === 'rechazada_admin'): ?>
                                <div class="mt-4">
                                    <div class="alert alert-danger">
                                        <div class="detail-label text-danger">Comentarios del Admin. de Área</div>
                                        <div class="mt-1"><?php echo nl2br(htmlspecialchars($sol['comentarios_admin'])); ?></div>
                                        <?php if ($sol['admin_nombre']): ?>
                                        <small class="text-muted mt-1 d-block">— <?php echo htmlspecialchars($sol['admin_nombre']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($sol['comentarios_gth']) && $sol['estado'] === 'rechazada_gth'): ?>
                                <div class="mt-4">
                                    <div class="alert alert-danger">
                                        <div class="detail-label text-danger">Comentarios de GTH</div>
                                        <div class="mt-1"><?php echo nl2br(htmlspecialchars($sol['comentarios_gth'])); ?></div>
                                        <?php if ($sol['gth_nombre']): ?>
                                        <small class="text-muted mt-1 d-block">— <?php echo htmlspecialchars($sol['gth_nombre']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Comentarios admin (cuando ya fue aprobada, visible para GTH) -->
                                <?php if (!empty($sol['comentarios_admin']) && !in_array($sol['estado'], ['rechazada_admin', 'pendiente_admin'])): ?>
                                <div class="mt-4">
                                    <div class="alert alert-light border">
                                        <div class="detail-label">Comentarios del Admin. de Área</div>
                                        <div class="mt-1"><?php echo nl2br(htmlspecialchars($sol['comentarios_admin'])); ?></div>
                                        <?php if ($sol['admin_nombre']): ?>
                                        <small class="text-muted mt-1 d-block">— <?php echo htmlspecialchars($sol['admin_nombre']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                        
                        <!-- ============================================ -->
                        <!-- SECCIÓN DE APROBACIÓN - ADMIN DE ÁREA -->
                        <!-- ============================================ -->
                        <?php if ($puede_aprobar_admin): ?>
                        <div class="card admin-action-card mb-4">
                            <div class="card-header">
                                <i class="bi bi-shield-check me-2"></i>Acción del Admin de Área
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3" style="font-size: 0.88rem;">
                                    Revise la solicitud y firme para <strong>aprobar</strong> o indique un motivo para <strong>rechazar</strong>.
                                </p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Comentarios (obligatorio al rechazar)</label>
                                    <textarea id="comentariosAdmin" class="form-control" rows="3" maxlength="500"
                                              placeholder="Ej: Aprobado sin observaciones / No es posible en esas fechas..."></textarea>
                                    <small class="text-muted"><span id="contadorComentariosAdmin">0</span>/500</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Firma del Admin de Área (obligatoria para aprobar)</label>
                                    <div class="firma-action-container mb-2" id="firmaAdminContainer">
                                        <div id="firmaAdmin" style="width: 100%; height: 150px;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted" id="firmaAdminEstado">
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente de firma
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirmaAdmin">
                                            <i class="bi bi-eraser me-1"></i> Limpiar firma
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-danger" id="btnRechazarAdmin">
                                        <i class="bi bi-x-circle me-1"></i> Rechazar
                                    </button>
                                    <button type="button" class="btn btn-success btn-lg" id="btnAprobarAdmin" disabled>
                                        <i class="bi bi-check-circle me-1"></i> Aprobar y Firmar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <form id="formAprobarAdmin" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" style="display:none;">
                            <input type="hidden" name="accion" value="aprobar_admin">
                            <input type="hidden" name="solicitud_id" value="<?php echo $sol['id']; ?>">
                            <input type="hidden" name="comentarios_admin" id="hiddenComentariosAprobarAdmin" value="">
                            <input type="hidden" name="firma_admin" id="hiddenFirmaAprobarAdmin" value="">
                        </form>
                        <form id="formRechazarAdmin" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" style="display:none;">
                            <input type="hidden" name="accion" value="rechazar_admin">
                            <input type="hidden" name="solicitud_id" value="<?php echo $sol['id']; ?>">
                            <input type="hidden" name="comentarios_admin" id="hiddenComentariosRechazarAdmin" value="">
                        </form>
                        <?php endif; ?>
                        
                        <!-- ============================================ -->
                        <!-- SECCIÓN DE APROBACIÓN - GTH -->
                        <!-- ============================================ -->
                        <?php if ($puede_aprobar_gth): ?>
                        <div class="card gth-action-card mb-4">
                            <div class="card-header">
                                <i class="bi bi-building-check me-2"></i>Acción de Gestión de Talento Humano
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3" style="font-size: 0.88rem;">
                                    Esta solicitud ya fue aprobada por el Admin de Área. Revise y firme para <strong>aprobar finalmente</strong> o <strong>rechazar</strong>.
                                </p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Comentarios (obligatorio al rechazar)</label>
                                    <textarea id="comentariosGth" class="form-control" rows="3" maxlength="500"
                                              placeholder="Ej: Aprobado / No procede por..."></textarea>
                                    <small class="text-muted"><span id="contadorComentariosGth">0</span>/500</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Firma de GTH (obligatoria para aprobar)</label>
                                    <div class="firma-action-container mb-2" id="firmaGthContainer">
                                        <div id="firmaGth" style="width: 100%; height: 150px;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted" id="firmaGthEstado">
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente de firma
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirmaGth">
                                            <i class="bi bi-eraser me-1"></i> Limpiar firma
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-danger" id="btnRechazarGth">
                                        <i class="bi bi-x-circle me-1"></i> Rechazar
                                    </button>
                                    <button type="button" class="btn btn-primary btn-lg" id="btnAprobarGth" disabled>
                                        <i class="bi bi-check-circle me-1"></i> Aprobar y Firmar (Final)
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <form id="formAprobarGth" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" style="display:none;">
                            <input type="hidden" name="accion" value="aprobar_gth">
                            <input type="hidden" name="solicitud_id" value="<?php echo $sol['id']; ?>">
                            <input type="hidden" name="comentarios_gth" id="hiddenComentariosAprobarGth" value="">
                            <input type="hidden" name="firma_gth" id="hiddenFirmaAprobarGth" value="">
                        </form>
                        <form id="formRechazarGth" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" style="display:none;">
                            <input type="hidden" name="accion" value="rechazar_gth">
                            <input type="hidden" name="solicitud_id" value="<?php echo $sol['id']; ?>">
                            <input type="hidden" name="comentarios_gth" id="hiddenComentariosRechazarGth" value="">
                        </form>
                        <?php endif; ?>
                        
                    </div>
                    
                    <!-- Columna lateral: Timeline -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>Flujo de Aprobación</h6>
                                <?php
                                $estado = $sol['estado'];
                                $paso1_class = 'completado';
                                $paso2_class = '';
                                if (in_array($estado, ['aprobada_admin', 'pendiente_gth', 'aprobada_gth', 'completada'])) $paso2_class = 'completado';
                                elseif ($estado === 'pendiente_admin') $paso2_class = 'activo';
                                elseif ($estado === 'rechazada_admin') $paso2_class = 'rechazado';
                                
                                $paso3_class = '';
                                if (in_array($estado, ['aprobada_gth', 'completada'])) $paso3_class = 'completado';
                                elseif ($estado === 'pendiente_gth') $paso3_class = 'activo';
                                elseif ($estado === 'rechazada_gth') $paso3_class = 'rechazado';
                                
                                $paso4_class = ($estado === 'completada') ? 'completado' : '';
                                ?>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-dot <?php echo $paso1_class; ?>"></div>
                                        <div class="timeline-title">Empleado firma y envía</div>
                                        <div class="timeline-sub"><?php echo fecha_corta_es($sol['fecha_firma_empleado']); ?></div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot <?php echo $paso2_class; ?>"></div>
                                        <div class="timeline-title">Admin. de Área</div>
                                        <div class="timeline-sub">
                                            <?php 
                                            if ($paso2_class === 'completado') echo htmlspecialchars($sol['admin_nombre'] ?? '') . ' — ' . fecha_corta_es($sol['fecha_aprobacion_admin']);
                                            elseif ($paso2_class === 'activo') echo '<span class="text-warning">En espera de aprobación</span>';
                                            elseif ($paso2_class === 'rechazado') { echo '<span class="text-danger">Rechazada</span>'; if ($sol['admin_nombre']) echo ' por ' . htmlspecialchars($sol['admin_nombre']); }
                                            else echo 'Pendiente';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot <?php echo $paso3_class; ?>"></div>
                                        <div class="timeline-title">Gestión de Talento Humano</div>
                                        <div class="timeline-sub">
                                            <?php 
                                            if ($paso3_class === 'completado') echo htmlspecialchars($sol['gth_nombre'] ?? '') . ' — ' . fecha_corta_es($sol['fecha_aprobacion_gth']);
                                            elseif ($paso3_class === 'activo') echo '<span class="text-warning">En espera de aprobación</span>';
                                            elseif ($paso3_class === 'rechazado') { echo '<span class="text-danger">Rechazada</span>'; if ($sol['gth_nombre']) echo ' por ' . htmlspecialchars($sol['gth_nombre']); }
                                            else echo 'Pendiente';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot <?php echo $paso4_class; ?>"></div>
                                        <div class="timeline-title">Solicitud completada</div>
                                        <div class="timeline-sub"><?php echo $paso4_class === 'completado' ? 'Aprobada y finalizada' : 'Pendiente'; ?></div>
                                    </div>
                                </div>
                                <?php if ($estado === 'cancelada'): ?>
                                <div class="alert alert-dark mt-3 py-2 mb-0" style="font-size: 0.82rem;">
                                    <i class="bi bi-slash-circle me-1"></i> Solicitud cancelada por el empleado.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="bi bi-clock-history me-2"></i>Información adicional</h6>
                                <div style="font-size: 0.82rem;">
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">Fecha de creación</span>
                                        <span><?php echo fecha_corta_es($sol['fecha_creacion']); ?></span>
                                    </div>
                                    <?php if ($sol['fecha_actualizacion']): ?>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">Última actualización</span>
                                        <span><?php echo fecha_corta_es($sol['fecha_actualizacion']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">Periodo vacacional</span>
                                        <span>Año <?php echo $anio_periodo; ?>°</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">Días LFT del periodo</span>
                                        <span><?php echo $dias_lft; ?> días</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <!-- Modal cancelar (empleado) -->
    <?php if ($es_dueno && $sol['estado'] === 'pendiente_admin'): ?>
    <div class="modal fade" id="modalCancelarEmpleado" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Cancelar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas cancelar la solicitud <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>?</p>
                    <p class="text-muted mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener</button>
                    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" class="d-inline">
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="solicitud_id" value="<?php echo $sol['id']; ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i> Sí, cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal rechazar Admin -->
    <?php if ($puede_aprobar_admin): ?>
    <div class="modal fade" id="modalRechazarAdmin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Rechazar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Confirma que desea <strong class="text-danger">rechazar</strong> la solicitud <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>?</p>
                    <p class="text-muted mb-0">El empleado será notificado del rechazo.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarRechazoAdmin">
                        <i class="bi bi-x-circle me-1"></i> Confirmar Rechazo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal rechazar GTH -->
    <?php if ($puede_aprobar_gth): ?>
    <div class="modal fade" id="modalRechazarGth" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Rechazar Solicitud (GTH)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Confirma que desea <strong class="text-danger">rechazar</strong> la solicitud <strong><?php echo htmlspecialchars($sol['folio']); ?></strong> desde GTH?</p>
                    <p class="text-muted mb-0">El empleado y el Admin de Área serán notificados.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarRechazoGth">
                        <i class="bi bi-x-circle me-1"></i> Confirmar Rechazo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($necesita_jsignature): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <?php endif; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Sidebar hamburger
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
                this.setAttribute('aria-expanded', sidebar.classList.contains('active'));
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                this.classList.remove('active');
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
            });
        }
        
        // Cancelar (empleado)
        const btnCancelarEmpleado = document.getElementById('btnCancelarEmpleado');
        if (btnCancelarEmpleado) {
            btnCancelarEmpleado.addEventListener('click', function() {
                new bootstrap.Modal(document.getElementById('modalCancelarEmpleado')).show();
            });
        }
        
        <?php if ($puede_aprobar_admin): ?>
        // ============================================
        // ADMIN: jSignature + Aprobar/Rechazar
        // ============================================
        let firmaAdminOk = false;
        const $firmaAdmin = $("#firmaAdmin");
        $firmaAdmin.jSignature({ width: '100%', height: 150, color: '#000', 'background-color': '#fff', 'decor-color': 'transparent', lineWidth: 2 });
        
        $firmaAdmin.bind('change', function() {
            const data = $firmaAdmin.jSignature('getData', 'base30');
            if (data && data.length > 1 && data[1].length > 0) {
                firmaAdminOk = true;
                document.getElementById('firmaAdminContainer').classList.add('firmado');
                document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firma registrada';
                document.getElementById('btnAprobarAdmin').disabled = false;
            }
        });
        
        document.getElementById('btnLimpiarFirmaAdmin').addEventListener('click', function() {
            $firmaAdmin.jSignature('reset');
            firmaAdminOk = false;
            document.getElementById('firmaAdminContainer').classList.remove('firmado');
            document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente de firma';
            document.getElementById('btnAprobarAdmin').disabled = true;
        });
        
        document.getElementById('comentariosAdmin').addEventListener('input', function() {
            document.getElementById('contadorComentariosAdmin').textContent = this.value.length;
        });
        
        document.getElementById('btnAprobarAdmin').addEventListener('click', function() {
            if (!firmaAdminOk) { alert('Debe firmar antes de aprobar.'); return; }
            const firmaData = $firmaAdmin.jSignature('getData', 'image');
            if (!firmaData || firmaData.length < 2) { alert('Error al capturar la firma.'); return; }
            document.getElementById('hiddenFirmaAprobarAdmin').value = 'data:' + firmaData[0] + ',' + firmaData[1];
            document.getElementById('hiddenComentariosAprobarAdmin').value = document.getElementById('comentariosAdmin').value;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Aprobando...';
            document.getElementById('formAprobarAdmin').submit();
        });
        
        document.getElementById('btnRechazarAdmin').addEventListener('click', function() {
            if (!document.getElementById('comentariosAdmin').value.trim()) {
                alert('Debe escribir un comentario indicando el motivo del rechazo.');
                document.getElementById('comentariosAdmin').focus();
                return;
            }
            new bootstrap.Modal(document.getElementById('modalRechazarAdmin')).show();
        });
        
        document.getElementById('btnConfirmarRechazoAdmin').addEventListener('click', function() {
            document.getElementById('hiddenComentariosRechazarAdmin').value = document.getElementById('comentariosAdmin').value;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Rechazando...';
            document.getElementById('formRechazarAdmin').submit();
        });
        <?php endif; ?>
        
        <?php if ($puede_aprobar_gth): ?>
        // ============================================
        // GTH: jSignature + Aprobar/Rechazar
        // ============================================
        let firmaGthOk = false;
        const $firmaGth = $("#firmaGth");
        $firmaGth.jSignature({ width: '100%', height: 150, color: '#000', 'background-color': '#fff', 'decor-color': 'transparent', lineWidth: 2 });
        
        $firmaGth.bind('change', function() {
            const data = $firmaGth.jSignature('getData', 'base30');
            if (data && data.length > 1 && data[1].length > 0) {
                firmaGthOk = true;
                document.getElementById('firmaGthContainer').classList.add('firmado');
                document.getElementById('firmaGthEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firma registrada';
                document.getElementById('btnAprobarGth').disabled = false;
            }
        });
        
        document.getElementById('btnLimpiarFirmaGth').addEventListener('click', function() {
            $firmaGth.jSignature('reset');
            firmaGthOk = false;
            document.getElementById('firmaGthContainer').classList.remove('firmado');
            document.getElementById('firmaGthEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente de firma';
            document.getElementById('btnAprobarGth').disabled = true;
        });
        
        document.getElementById('comentariosGth').addEventListener('input', function() {
            document.getElementById('contadorComentariosGth').textContent = this.value.length;
        });
        
        document.getElementById('btnAprobarGth').addEventListener('click', function() {
            if (!firmaGthOk) { alert('Debe firmar antes de aprobar.'); return; }
            const firmaData = $firmaGth.jSignature('getData', 'image');
            if (!firmaData || firmaData.length < 2) { alert('Error al capturar la firma.'); return; }
            document.getElementById('hiddenFirmaAprobarGth').value = 'data:' + firmaData[0] + ',' + firmaData[1];
            document.getElementById('hiddenComentariosAprobarGth').value = document.getElementById('comentariosGth').value;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Aprobando...';
            document.getElementById('formAprobarGth').submit();
        });
        
        document.getElementById('btnRechazarGth').addEventListener('click', function() {
            if (!document.getElementById('comentariosGth').value.trim()) {
                alert('Debe escribir un comentario indicando el motivo del rechazo.');
                document.getElementById('comentariosGth').focus();
                return;
            }
            new bootstrap.Modal(document.getElementById('modalRechazarGth')).show();
        });
        
        document.getElementById('btnConfirmarRechazoGth').addEventListener('click', function() {
            document.getElementById('hiddenComentariosRechazarGth').value = document.getElementById('comentariosGth').value;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Rechazando...';
            document.getElementById('formRechazarGth').submit();
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>