<?php
/**
 * Ver Detalle de Cotización - Químicos y/o Residuos
 * Ubicación: dashboard/cotizaciones_qr/ver_cotizacion_qr.php
 * 
 * Estructura:
 * 1. Formulario de Ventas (siempre visible)
 * 2. Formulario de Normatividad (visible cuando respondió) o mensaje "En revisión"
 * 3. Sección de Comentarios Generales (siempre disponible)
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
$normatividad_respondio = normatividad_respondio_cqr($cotizacion);

// Si es Normatividad y aún no ha respondido, marcar como "en revisión"
if ($es_normatividad && $cotizacion['estado'] === 'enviada') {
    marcar_en_revision_cqr($cotizacion_id, $_SESSION['usuario_id']);
    // Recargar cotización
    $cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
}

// Procesar formulario de respuesta de Normatividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // Agregar comentario general
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
    
    // Respuesta de Normatividad
    if ($_POST['accion'] === 'responder_normatividad' && $permisos['puede_editar'] && $es_normatividad && !$normatividad_respondio) {
        
        $datos_respuesta = [
            'norm_nombre_amigable' => trim($_POST['norm_nombre_amigable'] ?? ''),
            'norm_nombre_tecnico' => trim($_POST['norm_nombre_tecnico'] ?? ''),
            'norm_categoria' => $_POST['norm_categoria'] ?? null,
            'decision' => $_POST['decision'] ?? '',
            'comentarios_normatividad' => trim($_POST['comentarios_normatividad'] ?? '')
        ];
        
        // Validar decisión
        if (!in_array($datos_respuesta['decision'], ['aceptada', 'rechazada'])) {
            $_SESSION['error'] = "Debes seleccionar Aceptada o Rechazada.";
            header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id);
            exit;
        }
        
        // Procesar archivos si se subieron
        if (isset($_FILES['norm_ficha_tecnica']) && $_FILES['norm_ficha_tecnica']['error'] === UPLOAD_ERR_OK) {
            $resultado = procesar_archivo_cqr($_FILES['norm_ficha_tecnica'], 'norm_ficha');
            if (is_string($resultado)) {
                $datos_respuesta['norm_ficha_tecnica'] = $resultado;
            }
        }
        
        if (isset($_FILES['norm_formato_descripcion']) && $_FILES['norm_formato_descripcion']['error'] === UPLOAD_ERR_OK) {
            $resultado = procesar_archivo_cqr($_FILES['norm_formato_descripcion'], 'norm_formato');
            if (is_string($resultado)) {
                $datos_respuesta['norm_formato_descripcion'] = $resultado;
            }
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
        /* Estilos específicos del módulo */
        .detail-card {
            border-radius: var(--border-radius-lg, 12px);
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .detail-card .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .detail-card.ventas-card .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
        }
        
        .detail-card.normatividad-card .card-header {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: white;
        }
        
        .detail-card.comentarios-card .card-header {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
            color: white;
        }
        
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 180px;
        }
        
        .info-value {
            color: #212529;
            flex: 1;
        }
        
        /* Decision Box */
        .decision-box {
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1rem 0;
            background: #f8f9fa;
        }
        
        .decision-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .decision-option:hover {
            border-color: #adb5bd;
        }
        
        .decision-option.aceptada {
            border-color: #198754;
            background: rgba(25, 135, 84, 0.05);
        }
        
        .decision-option.rechazada {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }
        
        .decision-option input[type="radio"] {
            width: 24px;
            height: 24px;
            margin-right: 1rem;
        }
        
        .decision-option input[type="radio"]:checked + .decision-text.aceptada {
            color: #198754;
            font-weight: 600;
        }
        
        .decision-option input[type="radio"]:checked + .decision-text.rechazada {
            color: #dc3545;
            font-weight: 600;
        }
        
        /* Resultado de decisión */
        .decision-result {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .decision-result.aceptada {
            background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
            color: #0f5132;
            border: 2px solid #198754;
        }
        
        .decision-result.rechazada {
            background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
            color: #842029;
            border: 2px solid #dc3545;
        }
        
        /* En revisión */
        .en-revision-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }
        
        .en-revision-box i {
            font-size: 3rem;
            color: #856404;
            margin-bottom: 1rem;
        }
        
        /* Comentarios */
        .comentario-item {
            border-left: 4px solid #6c757d;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .comentario-item.ventas {
            border-left-color: #0d6efd;
        }
        
        .comentario-item.normatividad {
            border-left-color: #198754;
        }
        
        .comentario-item.laboratorio {
            border-left-color: #6f42c1;
        }
        
        .comentario-item.direccion {
            border-left-color: #dc3545;
        }
        
        .comentario-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .comentario-autor {
            font-weight: 600;
        }
        
        .comentario-fecha {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .comentario-departamento {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 4px;
            color: white;
        }
        
        /* Archivo adjunto */
        .archivo-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #e9ecef;
            border-radius: 6px;
            text-decoration: none;
            color: #212529;
            transition: background 0.2s;
        }
        
        .archivo-link:hover {
            background: #dee2e6;
            color: #0d6efd;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
            
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left"></i> Volver al listado
                    </a>
                    <h2 class="mb-1">
                        <i class="bi bi-flask text-primary"></i>
                        <?php echo htmlspecialchars($cotizacion['folio']); ?>
                    </h2>
                    <p class="text-muted mb-0">
                        Creada el <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])); ?>
                    </p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php echo obtener_badge_estado_cqr($cotizacion['estado']); ?>
                    <?php if ($normatividad_respondio): ?>
                        <?php echo obtener_badge_decision_cqr($cotizacion['decision']); ?>
                    <?php else: ?>
                        <?php echo obtener_badge_estado_normatividad($cotizacion['estado_normatividad']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
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
            
            <div class="row">
                <div class="col-lg-8">
                    
                    <!-- ================================================== -->
                    <!-- FORMULARIO DE VENTAS (Siempre visible) -->
                    <!-- ================================================== -->
                    <div class="card detail-card ventas-card">
                        <div class="card-header">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Solicitud de Ventas
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Folio:</span>
                                <span class="info-value"><strong><?php echo htmlspecialchars($cotizacion['folio']); ?></strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Fecha de Solicitud:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($cotizacion['fecha_solicitud'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Solicitante:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotizacion['creador_nombre']); ?> (<?php echo ucfirst($cotizacion['departamento_creador']); ?>)</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Nombre Amigable:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_amigable']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Nombre Técnico:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_tecnico'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Categoría:</span>
                                <span class="info-value"><?php echo obtener_nombre_categoria_cqr($cotizacion['categoria']); ?></span>
                            </div>
                            <?php if (!empty($cotizacion['comentarios_ventas'])): ?>
                            <div class="info-row">
                                <span class="info-label">Comentarios:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($cotizacion['comentarios_ventas'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Archivos adjuntos -->
                            <?php if (!empty($cotizacion['ficha_tecnica'])): ?>
                            <?php $ficha = json_decode($cotizacion['ficha_tecnica'], true); ?>
                            <div class="info-row">
                                <span class="info-label">Ficha Técnica:</span>
                                <span class="info-value">
                                    <a href="<?php echo URL_BASE . ($ficha['ruta'] ?? ''); ?>" target="_blank" class="archivo-link">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <?php echo htmlspecialchars($ficha['nombre_original'] ?? 'Ver archivo'); ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($cotizacion['formato_descripcion'])): ?>
                            <?php $formato = json_decode($cotizacion['formato_descripcion'], true); ?>
                            <div class="info-row">
                                <span class="info-label">Formato Descripción:</span>
                                <span class="info-value">
                                    <a href="<?php echo URL_BASE . ($formato['ruta'] ?? ''); ?>" target="_blank" class="archivo-link">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <?php echo htmlspecialchars($formato['nombre_original'] ?? 'Ver archivo'); ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ================================================== -->
                    <!-- FORMULARIO DE NORMATIVIDAD -->
                    <!-- ================================================== -->
                    <?php if ($normatividad_respondio): ?>
                    <!-- Normatividad ya respondió - Mostrar respuesta -->
                    <div class="card detail-card normatividad-card">
                        <div class="card-header">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Respuesta de Normatividad
                        </div>
                        <div class="card-body">
                            <!-- Decisión -->
                            <div class="mb-4">
                                <div class="decision-result <?php echo $cotizacion['decision']; ?>">
                                    <?php if ($cotizacion['decision'] === 'aceptada'): ?>
                                        <i class="bi bi-check-circle-fill me-2"></i> SOLICITUD ACEPTADA
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill me-2"></i> SOLICITUD RECHAZADA
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($cotizacion['comentarios_normatividad'])): ?>
                            <div class="info-row">
                                <span class="info-label">Comentarios:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($cotizacion['comentarios_normatividad'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="info-row">
                                <span class="info-label">Nombre Amigable:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotizacion['norm_nombre_amigable'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Nombre Técnico:</span>
                                <span class="info-value"><?php echo htmlspecialchars($cotizacion['norm_nombre_tecnico'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Categoría:</span>
                                <span class="info-value"><?php echo obtener_nombre_categoria_cqr($cotizacion['norm_categoria']); ?></span>
                            </div>
                            
                            <!-- Archivos de Normatividad -->
                            <?php if (!empty($cotizacion['norm_ficha_tecnica'])): ?>
                            <?php $norm_ficha = json_decode($cotizacion['norm_ficha_tecnica'], true); ?>
                            <div class="info-row">
                                <span class="info-label">Ficha Técnica:</span>
                                <span class="info-value">
                                    <a href="<?php echo URL_BASE . ($norm_ficha['ruta'] ?? ''); ?>" target="_blank" class="archivo-link">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <?php echo htmlspecialchars($norm_ficha['nombre_original'] ?? 'Ver archivo'); ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($cotizacion['norm_formato_descripcion'])): ?>
                            <?php $norm_formato = json_decode($cotizacion['norm_formato_descripcion'], true); ?>
                            <div class="info-row">
                                <span class="info-label">Formato Descripción:</span>
                                <span class="info-value">
                                    <a href="<?php echo URL_BASE . ($norm_formato['ruta'] ?? ''); ?>" target="_blank" class="archivo-link">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <?php echo htmlspecialchars($norm_formato['nombre_original'] ?? 'Ver archivo'); ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="text-muted small">
                                <i class="bi bi-person me-1"></i> Respondido por: <?php echo htmlspecialchars($cotizacion['normatividad_nombre'] ?? 'N/A'); ?>
                                <br>
                                <i class="bi bi-calendar me-1"></i> Fecha: <?php echo $cotizacion['fecha_respuesta_normatividad'] ? date('d/m/Y H:i', strtotime($cotizacion['fecha_respuesta_normatividad'])) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($es_normatividad && $permisos['puede_editar']): ?>
                    <!-- Normatividad puede llenar el formulario -->
                    <div class="card detail-card normatividad-card">
                        <div class="card-header">
                            <i class="bi bi-pencil-square me-2"></i>
                            Formulario de Respuesta - Normatividad
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="formRespuesta">
                                <input type="hidden" name="accion" value="responder_normatividad">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre Amigable</label>
                                        <input type="text" name="norm_nombre_amigable" class="form-control" 
                                               value="<?php echo htmlspecialchars($cotizacion['nombre_amigable']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre Técnico</label>
                                        <input type="text" name="norm_nombre_tecnico" class="form-control"
                                               value="<?php echo htmlspecialchars($cotizacion['nombre_tecnico'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Categoría</label>
                                        <select name="norm_categoria" class="form-select">
                                            <option value="">Seleccionar...</option>
                                            <option value="en_espera_1" <?php echo ($cotizacion['categoria'] === 'en_espera_1') ? 'selected' : ''; ?>>Categoría 1</option>
                                            <option value="en_espera_2" <?php echo ($cotizacion['categoria'] === 'en_espera_2') ? 'selected' : ''; ?>>Categoría 2</option>
                                            <option value="en_espera_3" <?php echo ($cotizacion['categoria'] === 'en_espera_3') ? 'selected' : ''; ?>>Categoría 3</option>
                                            <option value="en_espera_4" <?php echo ($cotizacion['categoria'] === 'en_espera_4') ? 'selected' : ''; ?>>Categoría 4</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ficha Técnica (opcional)</label>
                                        <input type="file" name="norm_ficha_tecnica" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Formato Descripción (opcional)</label>
                                        <input type="file" name="norm_formato_descripcion" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                                
                                <!-- Caja de Decisión -->
                                <div class="decision-box mt-4">
                                    <h5 class="mb-3"><i class="bi bi-check2-square me-2"></i>Decisión</h5>
                                    
                                    <label class="decision-option aceptada">
                                        <input type="radio" name="decision" value="aceptada" required>
                                        <span class="decision-text aceptada">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <strong>Aceptada</strong> - La solicitud cumple con los requisitos
                                        </span>
                                    </label>
                                    
                                    <label class="decision-option rechazada">
                                        <input type="radio" name="decision" value="rechazada" required>
                                        <span class="decision-text rechazada">
                                            <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                            <strong>Rechazada</strong> - La solicitud no cumple con los requisitos
                                        </span>
                                    </label>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Comentarios</label>
                                        <textarea name="comentarios_normatividad" class="form-control" rows="3" 
                                                  placeholder="Agrega comentarios sobre tu decisión..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-send me-2"></i>Enviar Respuesta
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Otros usuarios ven mensaje de "En revisión" -->
                    <div class="card detail-card">
                        <div class="card-header bg-warning text-dark">
                            <i class="bi bi-hourglass-split me-2"></i>
                            Respuesta de Normatividad
                        </div>
                        <div class="card-body">
                            <div class="en-revision-box">
                                <i class="bi bi-hourglass-split d-block"></i>
                                <h4>En Revisión</h4>
                                <p class="text-muted mb-0">
                                    La solicitud está siendo revisada por Normatividad.<br>
                                    La respuesta aparecerá aquí cuando esté completada.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ================================================== -->
                    <!-- SECCIÓN DE COMENTARIOS GENERALES -->
                    <!-- ================================================== -->
                    <div class="card detail-card comentarios-card">
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
                                            }
                                            ?>
                                            <i class="bi <?php echo $icono; ?> <?php echo $color; ?> fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?php echo ucfirst($evento['accion']); ?></div>
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
                alert('Debes seleccionar Aceptada o Rechazada');
                return;
            }
            
            const textoDecision = decision.value === 'aceptada' ? 'ACEPTAR' : 'RECHAZAR';
            if (!confirm(`¿Estás seguro de ${textoDecision} esta cotización?\n\nEsta acción no se puede deshacer.`)) {
                e.preventDefault();
            }
        });
        
        // Resaltar opción seleccionada
        document.querySelectorAll('.decision-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.decision-option').forEach(opt => {
                    opt.classList.remove('selected');
                    opt.style.borderWidth = '2px';
                });
                if (this.checked) {
                    this.closest('.decision-option').classList.add('selected');
                    this.closest('.decision-option').style.borderWidth = '3px';
                }
            });
        });
    </script>
</body>
</html>