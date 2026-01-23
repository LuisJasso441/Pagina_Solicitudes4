<?php
/**
 * Ver Detalle de Cotización - Químicos y/o Residuos v3.0
 * Ubicación: dashboard/cotizaciones_qr/ver_cotizacion_qr.php
 * 
 * ACTUALIZACIÓN 23/01/2026:
 * - Campos renombrados: nombre_real_semarnat, nombre_ante_semarnat
 * - Nuevos campos: tipo_cliente, nombre_cliente
 * - Estados: enviada, en_revision, aceptada, rechazada
 * - Acceso para Laboratorio y Dirección (lectores con comentarios)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Incluir funciones CQR
$funciones_cqr = __DIR__ . '/../../includes/cotizaciones_qr/cotizaciones_qr_funciones.php';
if (file_exists($funciones_cqr)) {
    require_once $funciones_cqr;
} else {
    $_SESSION['error'] = "El módulo de Cotizaciones no está instalado correctamente.";
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    exit;
}

// Verificar ID
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($cotizacion_id <= 0) {
    $_SESSION['error'] = "Cotización no especificada.";
    header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/cotizaciones_qr.php');
    exit;
}

// Verificar permisos
$permisos = verificar_permisos_cqr($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    $_SESSION['error'] = "No tienes acceso a este módulo.";
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    exit;
}

// Obtener cotización
$cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
if (!$cotizacion) {
    $_SESSION['error'] = "Cotización no encontrada.";
    header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/cotizaciones_qr.php');
    exit;
}

// Determinar rol del usuario actual
$rol_usuario = obtener_rol_cqr($_SESSION['departamento'] ?? '');
$es_normatividad = ($rol_usuario === 'normatividad');
$es_ventas = ($rol_usuario === 'ventas');
$es_laboratorio = ($rol_usuario === 'laboratorio');
$es_direccion = ($rol_usuario === 'direccion');
$normatividad_respondio = normatividad_respondio_cqr($cotizacion);

// Si es Normatividad y aún no ha respondido, marcar como "en revisión"
if ($es_normatividad && $cotizacion['estado'] === 'enviada') {
    marcar_en_revision_cqr($cotizacion_id, $_SESSION['usuario_id']);
    $cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
}

// Procesar formulario de respuesta de Normatividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // Agregar comentario general (disponible para todos los departamentos con acceso)
    if ($_POST['accion'] === 'agregar_comentario' && $permisos['puede_comentar']) {
        $comentario = trim($_POST['comentario'] ?? '');
        if (!empty($comentario)) {
            agregar_comentario_cqr(
                $cotizacion_id, 
                $_SESSION['usuario_id'], 
                $_SESSION['departamento'] ?? 'Desconocido',
                $comentario
            );
            $_SESSION['success'] = "Comentario agregado correctamente.";
        }
        header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id);
        exit;
    }
    
    // Respuesta de Normatividad (solo si tiene permiso de editor y no ha respondido)
    if ($_POST['accion'] === 'responder_normatividad' && $permisos['puede_editar'] && $es_normatividad && !$normatividad_respondio) {
        
        $datos_respuesta = [
            'resultados' => trim($_POST['resultados'] ?? ''),
            'decision' => $_POST['decision'] ?? ''
        ];
        
        // Validar decisión
        if (!in_array($datos_respuesta['decision'], ['aceptada', 'rechazada'])) {
            $_SESSION['error'] = "Debes seleccionar un estado válido: Aceptada o Rechazada.";
            header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id);
            exit;
        }
        
        $resultado = responder_cotizacion_qr($cotizacion_id, $datos_respuesta, $_SESSION['usuario_id']);
        
        if ($resultado['success']) {
            $_SESSION['success'] = "Respuesta enviada correctamente.";
        } else {
            $_SESSION['error'] = "Error al enviar respuesta: " . ($resultado['error'] ?? 'Error desconocido');
        }
        
        header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id);
        exit;
    }
}

// Obtener comentarios
$comentarios = obtener_comentarios_cqr($cotizacion_id);

// Obtener historial
$historial = obtener_historial_cqr($cotizacion_id);

$page_title = "Cotización " . htmlspecialchars($cotizacion['folio']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - GrupoVerden</title>
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
        .detail-card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .detail-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        }
        
        .info-row {
            display: flex;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            width: 180px;
            flex-shrink: 0;
        }
        
        .info-value {
            flex-grow: 1;
        }
        
        .decision-option {
            border: 2px solid #dee2e6;
            border-radius: var(--border-radius-lg);
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        
        .decision-option:hover {
            border-color: #adb5bd;
        }
        
        .decision-option.aceptada:hover,
        .decision-option.aceptada.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }
        
        .decision-option.rechazada:hover,
        .decision-option.rechazada.selected {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .decision-option input[type="radio"] {
            display: none;
        }
        
        .comentarios-lista {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .comentario-item {
            background: #f8f9fa;
            border-radius: var(--border-radius-lg);
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #6c757d;
        }
        
        .comentario-item.ventas { border-left-color: #0d6efd; }
        .comentario-item.normatividad { border-left-color: #198754; }
        .comentario-item.laboratorio { border-left-color: #6f42c1; }
        .comentario-item.direccion { border-left-color: #dc3545; }
        
        .comentario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .comentario-autor {
            font-weight: 600;
        }
        
        .comentario-departamento {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
            color: white;
        }
        
        .comentario-fecha {
            font-size: 0.8rem;
            color: #6c757d;
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
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }
        
        .archivo-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: var(--border-radius-md);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .archivo-link:hover {
            background: #e9ecef;
            color: var(--color-brand-dark);
        }
        
        .imagen-residuo-thumb {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #dee2e6;
            display: block;
            transition: all 0.2s ease;
        }
        
        .imagen-residuo-thumb:hover {
            border-color: var(--color-brand-dark);
            transform: scale(1.05);
        }
        
        .imagen-residuo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .imagen-residuo-thumb .imagen-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .imagen-residuo-thumb:hover .imagen-overlay {
            opacity: 1;
        }
        
        .imagen-residuo-thumb .imagen-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        
        .cliente-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .cliente-badge.nuevo {
            background: #cff4fc;
            color: #055160;
        }
        
        .cliente-badge.frecuente {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        @media (max-width: 991px) {
            .info-row {
                flex-direction: column;
            }
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Navegación -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php">
                            Cotizaciones QR
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($cotizacion['folio']); ?></li>
                </ol>
            </nav>
            
            <!-- Alertas -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-flask text-primary"></i>
                        <?php echo htmlspecialchars($cotizacion['folio']); ?>
                    </h2>
                    <div class="d-flex gap-2 align-items-center">
                        <?php echo obtener_badge_estado_cqr($cotizacion['estado']); ?>
                        <?php echo obtener_badge_tipo_cliente($cotizacion['tipo_cliente']); ?>
                        <span class="badge bg-<?php echo $cotizacion['ubicacion'] === 'global' ? 'success' : 'secondary'; ?>">
                            <?php echo $cotizacion['ubicacion'] === 'global' ? 'Completado' : 'En Proceso'; ?>
                        </span>
                    </div>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    
                    <!-- Card: Información de la Solicitud (Ventas) -->
                    <div class="card detail-card">
                        <div class="card-header">
                            <i class="bi bi-file-text me-2"></i>
                            Solicitud de Ventas
                        </div>
                        <div class="card-body">
                            <!-- Información del Cliente -->
                            <h6 class="text-muted mb-3"><i class="bi bi-person-badge"></i> Información del Cliente</h6>
                            
                            <div class="info-row">
                                <div class="info-label">Tipo de Cliente</div>
                                <div class="info-value">
                                    <?php if ($cotizacion['tipo_cliente'] === 'nuevo'): ?>
                                    <span class="cliente-badge nuevo">
                                        <i class="bi bi-person-plus"></i> Cliente Nuevo
                                    </span>
                                    <?php else: ?>
                                    <span class="cliente-badge frecuente">
                                        <i class="bi bi-person-check"></i> Cliente Frecuente
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Nombre del Cliente</div>
                                <div class="info-value"><strong><?php echo htmlspecialchars($cotizacion['nombre_cliente']); ?></strong></div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <!-- Información del Producto -->
                            <h6 class="text-muted mb-3"><i class="bi bi-box-seam"></i> Datos del Producto</h6>
                            
                            <div class="info-row">
                                <div class="info-label">Nombre real (SEMARNAT)</div>
                                <div class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_real_semarnat']); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Nombre ante SEMARNAT</div>
                                <div class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_ante_semarnat'] ?? '-'); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Fecha de Solicitud</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($cotizacion['fecha_solicitud'])); ?></div>
                            </div>
                            
                            <?php if (!empty($cotizacion['comentarios_ventas'])): ?>
                            <div class="info-row">
                                <div class="info-label">Comentarios</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($cotizacion['comentarios_ventas'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <hr class="my-3">
                            
                            <!-- Archivos -->
                            <h6 class="text-muted mb-3"><i class="bi bi-paperclip"></i> Archivos Adjuntos</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Ficha Técnica:</strong></p>
                                    <?php if (!empty($cotizacion['ficha_tecnica'])): ?>
                                        <?php $ficha = json_decode($cotizacion['ficha_tecnica'], true); ?>
                                        <a href="<?php echo URL_BASE . $ficha['ruta']; ?>" target="_blank" class="archivo-link">
                                            <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                                            <span><?php echo htmlspecialchars($ficha['nombre_original']); ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No adjuntado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Formato Descripción:</strong></p>
                                    <?php if (!empty($cotizacion['formato_descripcion'])): ?>
                                        <?php $formato = json_decode($cotizacion['formato_descripcion'], true); ?>
                                        <?php 
                                        $icono_formato = 'bi-file-earmark';
                                        $color_icono = 'text-secondary';
                                        if ($formato['extension'] === 'pdf') {
                                            $icono_formato = 'bi-file-earmark-pdf';
                                            $color_icono = 'text-danger';
                                        } elseif ($formato['extension'] === 'xlsx') {
                                            $icono_formato = 'bi-file-earmark-excel';
                                            $color_icono = 'text-success';
                                        }
                                        ?>
                                        <a href="<?php echo URL_BASE . $formato['ruta']; ?>" target="_blank" class="archivo-link">
                                            <i class="bi <?php echo $icono_formato . ' ' . $color_icono; ?> fs-5"></i>
                                            <span><?php echo htmlspecialchars($formato['nombre_original']); ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No adjuntado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($cotizacion['imagenes_residuo'])): ?>
                            <?php $imagenes = json_decode($cotizacion['imagenes_residuo'], true); ?>
                            <?php if (!empty($imagenes)): ?>
                            <div class="mt-4">
                                <p class="mb-2"><strong><i class="bi bi-images text-info"></i> Imagen del Residuo:</strong></p>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($imagenes as $img): ?>
                                    <a href="<?php echo URL_BASE . $img['ruta']; ?>" target="_blank" class="imagen-residuo-thumb" title="<?php echo htmlspecialchars($img['nombre_original']); ?>">
                                        <img src="<?php echo URL_BASE . $img['ruta']; ?>" alt="<?php echo htmlspecialchars($img['nombre_original']); ?>">
                                        <div class="imagen-overlay">
                                            <i class="bi bi-zoom-in"></i>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <hr class="my-3">
                            
                            <!-- Creador -->
                            <div class="info-row">
                                <div class="info-label">Creado por</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($cotizacion['creador_nombre']); ?>
                                    <span class="badge bg-primary ms-2"><?php echo ucfirst($cotizacion['departamento_creador']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card: Respuesta de Normatividad -->
                    <div class="card detail-card">
                        <div class="card-header bg-<?php echo $normatividad_respondio ? 'success' : 'warning'; ?>">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Respuesta de Normatividad
                        </div>
                        <div class="card-body">
                            <?php if ($normatividad_respondio): ?>
                                <!-- Mostrar respuesta -->
                                <div class="info-row">
                                    <div class="info-label">Estado</div>
                                    <div class="info-value">
                                        <?php echo obtener_badge_estado_cqr($cotizacion['estado']); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($cotizacion['resultados'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Resultados</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($cotizacion['resultados'])); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-row">
                                    <div class="info-label">Respondido por</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($cotizacion['normatividad_nombre'] ?? 'No especificado'); ?>
                                        <span class="badge bg-success ms-2">Normatividad</span>
                                    </div>
                                </div>
                                
                            <?php elseif ($es_normatividad && $permisos['puede_editar']): ?>
                                <!-- Formulario de respuesta para Normatividad -->
                                <form method="POST" id="formRespuesta">
                                    <input type="hidden" name="accion" value="responder_normatividad">
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Resultados</label>
                                        <textarea name="resultados" class="form-control" rows="4" 
                                                  placeholder="Ingrese los resultados de la revisión..."></textarea>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Estado de la Cotización <span class="text-danger">*</span></label>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="decision-option aceptada d-block">
                                                    <input type="radio" name="decision" value="aceptada" required>
                                                    <i class="bi bi-check-circle-fill text-success fs-2"></i>
                                                    <div class="mt-2"><strong>Aceptada</strong></div>
                                                    <small class="text-muted">Aprobar la cotización</small>
                                                </label>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="decision-option rechazada d-block">
                                                    <input type="radio" name="decision" value="rechazada">
                                                    <i class="bi bi-x-circle-fill text-danger fs-2"></i>
                                                    <div class="mt-2"><strong>Rechazada</strong></div>
                                                    <small class="text-muted">Rechazar la cotización</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-send me-1"></i> Enviar Respuesta
                                        </button>
                                    </div>
                                </form>
                                
                            <?php else: ?>
                                <!-- Mensaje de espera -->
                                <div class="text-center py-4">
                                    <i class="bi bi-hourglass-split text-warning fs-1"></i>
                                    <h5 class="mt-3">En espera de respuesta</h5>
                                    <p class="text-muted">Normatividad aún no ha respondido a esta cotización.</p>
                                    <p class="mb-0">
                                        Estado actual: <?php echo obtener_badge_estado_cqr($cotizacion['estado']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Card: Comentarios -->
                    <div class="card detail-card">
                        <div class="card-header">
                            <i class="bi bi-chat-dots me-2"></i>
                            Comentarios (<?php echo count($comentarios); ?>)
                            <small class="ms-2 opacity-75">- Ventas, Normatividad, Laboratorio, Dirección</small>
                        </div>
                        <div class="card-body">
                            <!-- Formulario para agregar comentario -->
                            <?php if ($permisos['puede_comentar']): ?>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="accion" value="agregar_comentario">
                                <div class="mb-2">
                                    <textarea name="comentario" class="form-control" rows="2" 
                                              placeholder="Escribe un comentario..." required></textarea>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-send me-1"></i> Enviar Comentario
                                    </button>
                                </div>
                            </form>
                            <hr>
                            <?php endif; ?>
                            
                            <!-- Lista de comentarios -->
                            <?php if (empty($comentarios)): ?>
                            <p class="text-muted text-center mb-0">
                                <i class="bi bi-chat-square-text"></i> No hay comentarios aún
                            </p>
                            <?php else: ?>
                            <div class="comentarios-lista">
                                <?php foreach ($comentarios as $com): ?>
                                <?php $rol_comentario = obtener_rol_cqr($com['departamento']); ?>
                                <div class="comentario-item <?php echo $rol_comentario; ?>">
                                    <div class="comentario-header">
                                        <div>
                                            <span class="comentario-autor"><?php echo htmlspecialchars($com['usuario_nombre']); ?></span>
                                            <span class="comentario-departamento ms-2" style="background: <?php echo obtener_color_departamento_cqr($com['departamento']); ?>">
                                                <?php echo ucfirst($com['departamento']); ?>
                                            </span>
                                        </div>
                                        <span class="comentario-fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($com['fecha_creacion'])); ?>
                                        </span>
                                    </div>
                                    <div class="comentario-texto">
                                        <?php echo nl2br(htmlspecialchars($com['comentario'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
                <div class="col-lg-4">
                    <!-- Timeline / Historial -->
                    <div class="card detail-card">
                        <div class="card-header">
                            <i class="bi bi-clock-history me-2"></i>
                            Historial
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                            <p class="text-muted text-center mb-0">Sin historial</p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($historial as $evento): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="timeline-icon me-3">
                                            <?php
                                            $icono = 'bi-circle';
                                            $color = 'text-secondary';
                                            switch ($evento['accion']) {
                                                case 'creada':
                                                    $icono = 'bi-plus-circle-fill';
                                                    $color = 'text-primary';
                                                    break;
                                                case 'en_revision':
                                                    $icono = 'bi-eye-fill';
                                                    $color = 'text-warning';
                                                    break;
                                                case 'aceptada':
                                                    $icono = 'bi-check-circle-fill';
                                                    $color = 'text-success';
                                                    break;
                                                case 'rechazada':
                                                    $icono = 'bi-x-circle-fill';
                                                    $color = 'text-danger';
                                                    break;
                                                case 'comentario':
                                                    $icono = 'bi-chat-fill';
                                                    $color = 'text-info';
                                                    break;
                                            }
                                            ?>
                                            <i class="bi <?php echo $icono; ?> <?php echo $color; ?> fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?php echo ucfirst(str_replace('_', ' ', $evento['accion'])); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($evento['usuario_nombre']); ?> 
                                                (<?php echo ucfirst($evento['departamento']); ?>)
                                            </small>
                                            <?php if (!empty($evento['detalles'])): ?>
                                            <div class="small text-muted mt-1">
                                                <?php echo htmlspecialchars(substr($evento['detalles'], 0, 100)); ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($evento['fecha_hora'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Confirmar antes de enviar respuesta
        document.getElementById('formRespuesta')?.addEventListener('submit', function(e) {
            const decision = document.querySelector('input[name="decision"]:checked');
            if (!decision) {
                e.preventDefault();
                alert('Debes seleccionar un estado: Aceptada o Rechazada');
                return;
            }
            
            const textoDecision = decision.value === 'aceptada' ? 'ACEPTAR' : 'RECHAZAR';
            
            if (!confirm(`¿Estás seguro de ${textoDecision} esta cotización?\n\nEsta acción notificará a Ventas.`)) {
                e.preventDefault();
            }
        });
        
        // Resaltar opción seleccionada
        document.querySelectorAll('.decision-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.decision-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.decision-option').classList.add('selected');
                }
            });
        });
    </script>
</body>
</html>