<?php
/**
 * Ver Cotización de Químicos y/o Residuos
 * Ubicación: dashboard/cotizaciones_qr/ver_cotizacion_qr.php
 * Muestra el detalle completo y permite a Normatividad responder
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cotizaciones_qr/cotizaciones_qr_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Verificar permisos CQR
$permisos = verificar_permisos_cqr($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    $_SESSION['error'] = "No tienes acceso a este módulo.";
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    exit;
}

// Obtener ID de la cotización
$cotizacion_id = $_GET['id'] ?? null;
if (!$cotizacion_id) {
    $_SESSION['error'] = "ID de cotización no especificado.";
    header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/cotizaciones_qr.php');
    exit;
}

// Obtener cotización
$cotizacion = obtener_cotizacion_qr_por_id($cotizacion_id);
if (!$cotizacion) {
    $_SESSION['error'] = "Cotización no encontrada.";
    header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/cotizaciones_qr.php');
    exit;
}

// Obtener historial
$historial = obtener_historial_cqr($cotizacion_id);

// Determinar rol del usuario
$es_ventas = strpos(strtolower($_SESSION['departamento'] ?? ''), 'ventas') !== false;
$es_normatividad = strpos(strtolower($_SESSION['departamento'] ?? ''), 'normatividad') !== false;
$puede_editar = $permisos['puede_editar'] && $cotizacion['estado'] !== 'finalizada';

// Procesar respuesta de Normatividad
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_editar) {
    $resultados = trim($_POST['resultados'] ?? '');
    $estado_normatividad = $_POST['estado_normatividad'] ?? '';
    
    if (empty($resultados)) {
        $errores[] = "Los resultados son obligatorios";
    }
    
    if (empty($estado_normatividad)) {
        $errores[] = "Debe seleccionar un estado";
    }
    
    if (empty($errores)) {
        $datos_actualizacion = [
            'resultados' => $resultados,
            'estado_normatividad' => $estado_normatividad,
            'usuario_normatividad_id' => $_SESSION['usuario_id'],
            'usuario_nombre' => $_SESSION['nombre_completo']
        ];
        
        $resultado = actualizar_cotizacion_qr_normatividad($cotizacion_id, $datos_actualizacion);
        
        if ($resultado['success']) {
            // Enviar notificación a Ventas (creador de la cotización)
            $cotizacion_actualizada = obtener_cotizacion_qr_por_id($cotizacion_id);
            $tipo_notif = ($estado_normatividad === 'finalizada') ? 'cotizacion_finalizada' : 'cotizacion_actualizada';
            enviar_notificacion_cqr($tipo_notif, $cotizacion_actualizada, $cotizacion['usuario_creador_id']);
            
            $_SESSION['success'] = "Cotización actualizada correctamente.";
            header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id);
            exit;
        } else {
            $errores[] = $resultado['error'];
        }
    }
}

// Decodificar archivos JSON
$ficha_tecnica = $cotizacion['ficha_tecnica'] ? json_decode($cotizacion['ficha_tecnica'], true) : null;
$formato_descripcion = $cotizacion['formato_descripcion'] ? json_decode($cotizacion['formato_descripcion'], true) : null;

$page_title = "Cotización " . $cotizacion['folio'];
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
        /* Estilos específicos de la vista de detalle */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .detail-card .card-header {
            background: var(--gradient-primary);
            color: var(--text-white);
            border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
            padding: 15px 20px;
        }
        
        .detail-card .card-body {
            padding: 25px;
        }
        
        .info-row {
            display: flex;
            border-bottom: 1px solid var(--border-color-light);
            padding: 12px 0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 200px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .info-value {
            flex: 1;
            color: var(--text-dark);
        }
        
        .section-normatividad {
            background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
        }
        
        .section-historial {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
        }
        
        .archivo-link {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background: var(--bg-body);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-dark);
            transition: all var(--transition-normal);
        }
        
        .archivo-link:hover {
            background: var(--border-color-light);
            color: var(--color-brand-dark);
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
            background: var(--border-color);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--color-brand-dark);
        }
        
        .timeline-item.finalizada::before {
            background: var(--color-success);
        }
        
        @media (max-width: 991.98px) {
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
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
            
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
            
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-flask text-primary"></i>
                        <?php echo htmlspecialchars($cotizacion['folio']); ?>
                    </h2>
                    <div>
                        <?php echo obtener_badge_estado_cqr($cotizacion['estado']); ?>
                        <span class="badge bg-<?php echo $cotizacion['ubicacion'] === 'global' ? 'success' : 'secondary'; ?> ms-2">
                            <?php echo $cotizacion['ubicacion'] === 'global' ? 'Base Global' : 'Base Local'; ?>
                        </span>
                    </div>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
            
            <!-- Errores -->
            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Errores:</h6>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Columna Principal -->
                <div class="col-lg-8">
                    <!-- Apartado 1: Información de Ventas -->
                    <div class="detail-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-1-circle"></i>
                                Información de la Solicitud (Ventas)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <div class="info-label">Folio:</div>
                                <div class="info-value"><strong><?php echo htmlspecialchars($cotizacion['folio']); ?></strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha de Solicitud:</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($cotizacion['fecha_solicitud'])); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Nombre (Amigable):</div>
                                <div class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_amigable']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Nombre Real (Técnico):</div>
                                <div class="info-value"><?php echo htmlspecialchars($cotizacion['nombre_tecnico'] ?? '-'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Categoría:</div>
                                <div class="info-value">
                                    <span class="badge bg-secondary">
                                        <?php echo obtener_nombre_categoria_cqr($cotizacion['categoria']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Solicitante:</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($cotizacion['creador_nombre']); ?>
                                    <small class="text-muted">(<?php echo ucfirst($cotizacion['departamento_creador']); ?>)</small>
                                </div>
                            </div>
                            
                            <?php if (!empty($cotizacion['comentarios_ventas'])): ?>
                            <div class="info-row">
                                <div class="info-label">Comentarios:</div>
                                <div class="info-value">
                                    <div class="bg-light p-3 rounded">
                                        <?php echo nl2br(htmlspecialchars($cotizacion['comentarios_ventas'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Archivos Adjuntos -->
                            <?php if ($ficha_tecnica || $formato_descripcion): ?>
                            <div class="info-row">
                                <div class="info-label">Archivos Adjuntos:</div>
                                <div class="info-value">
                                    <?php if ($ficha_tecnica): ?>
                                    <a href="<?php echo URL_BASE . $ficha_tecnica['ruta']; ?>" 
                                       target="_blank" class="archivo-link me-2 mb-2">
                                        <i class="bi bi-file-earmark me-2"></i>
                                        Ficha Técnica
                                        <small class="text-muted ms-2">(<?php echo $ficha_tecnica['extension']; ?>)</small>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($formato_descripcion): ?>
                                    <a href="<?php echo URL_BASE . $formato_descripcion['ruta']; ?>" 
                                       target="_blank" class="archivo-link mb-2">
                                        <i class="bi bi-file-earmark me-2"></i>
                                        Formato Descripción
                                        <small class="text-muted ms-2">(<?php echo $formato_descripcion['extension']; ?>)</small>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Apartado 2: Respuesta de Normatividad -->
                    <div class="detail-card">
                        <div class="card-header section-normatividad">
                            <h5 class="mb-0">
                                <i class="bi bi-2-circle"></i>
                                Respuesta de Normatividad
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($cotizacion['estado'] === 'enviada' && !$puede_editar): ?>
                            <!-- Vista para Ventas cuando está pendiente -->
                            <div class="text-center py-4">
                                <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                                <p class="text-muted mt-2 mb-0">Pendiente de revisión por Normatividad</p>
                            </div>
                            
                            <?php elseif ($puede_editar): ?>
                            <!-- Formulario para Normatividad -->
                            <form method="POST" id="formRespuesta">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Resultados <span class="text-danger">*</span></label>
                                    <textarea name="resultados" class="form-control" rows="5" 
                                              placeholder="Ingrese los resultados de la revisión..." required><?php echo htmlspecialchars($cotizacion['resultados'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Estado <span class="text-danger">*</span></label>
                                    <select name="estado_normatividad" class="form-select" required>
                                        <option value="">Seleccione un estado</option>
                                        <option value="en_revision" <?php echo ($cotizacion['estado_normatividad'] ?? '') === 'en_revision' ? 'selected' : ''; ?>>
                                            En Revisión
                                        </option>
                                        <option value="finalizada" <?php echo ($cotizacion['estado_normatividad'] ?? '') === 'finalizada' ? 'selected' : ''; ?>>
                                            Finalizada
                                        </option>
                                    </select>
                                    <small class="text-muted">
                                        Al seleccionar "Finalizada", la cotización pasará a la Base Global.
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle"></i> Guardar Respuesta
                                    </button>
                                </div>
                            </form>
                            
                            <?php else: ?>
                            <!-- Vista cuando ya hay respuesta -->
                            <div class="info-row">
                                <div class="info-label">Resultados:</div>
                                <div class="info-value">
                                    <div class="bg-light p-3 rounded">
                                        <?php echo nl2br(htmlspecialchars($cotizacion['resultados'] ?? '-')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Estado Normatividad:</div>
                                <div class="info-value">
                                    <?php echo obtener_badge_estado_cqr($cotizacion['estado_normatividad']); ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Atendido por:</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($cotizacion['normatividad_nombre'] ?? '-'); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Columna Lateral -->
                <div class="col-lg-4">
                    <!-- Información de Fechas -->
                    <div class="detail-card">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-calendar3"></i>
                                Fechas del Proceso
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Fecha de Creación:</small>
                                <div class="fw-bold">
                                    <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($cotizacion['fecha_enviada']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Fecha de Envío:</small>
                                <div class="fw-bold">
                                    <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_enviada'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cotizacion['fecha_en_revision']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Inicio de Revisión:</small>
                                <div class="fw-bold">
                                    <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_en_revision'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cotizacion['fecha_finalizada']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Fecha de Finalización:</small>
                                <div class="fw-bold text-success">
                                    <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_finalizada'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Historial -->
                    <div class="detail-card">
                        <div class="card-header section-historial">
                            <h6 class="mb-0">
                                <i class="bi bi-clock-history"></i>
                                Historial de Cambios
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                            <p class="text-muted text-center mb-0">Sin historial registrado</p>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($historial as $item): ?>
                                <div class="timeline-item <?php echo $item['accion']; ?>">
                                    <div class="fw-bold"><?php echo ucfirst($item['accion']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($item['usuario_nombre']); ?>
                                        (<?php echo ucfirst($item['departamento']); ?>)
                                    </small>
                                    <div class="small text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($item['fecha_hora'])); ?>
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
    
    <!-- Alertas de sesión -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div class="toast show align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Auto-hide toasts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.toast').forEach(function(toast) {
                toast.classList.remove('show');
            });
        }, 5000);
        
        // Confirmar antes de finalizar
        document.getElementById('formRespuesta')?.addEventListener('submit', function(e) {
            const estado = document.querySelector('select[name="estado_normatividad"]').value;
            if (estado === 'finalizada') {
                if (!confirm('¿Está seguro de FINALIZAR esta cotización?\n\nEsta acción moverá la cotización a la Base Global y no podrá ser editada.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>