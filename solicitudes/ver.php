<?php
/**
 * Ver detalle de una solicitud
 * Muestra información completa de una solicitud específica
 * 
 * ⭐ CORREGIDO: Sidebar ahora incluye verificación de GTH y Mantenimiento
 * 
 * ACTUALIZADO:
 * - Historial completo de cambios de estado
 * - Botón "Editar Solicitud" para usuarios de Sistemas
 * - Muestra nombre_solicitante editable de la tabla
 * - Muestra archivos adjuntos (evidencia) con vista previa y descarga
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$es_ti = es_usuario_ti();

// Obtener folio de la URL
$folio = isset($_GET['folio']) ? limpiar_dato($_GET['folio']) : '';

if (empty($folio)) {
    establecer_alerta('error', 'Folio no especificado');
    redirigir(URL_BASE . 'solicitudes/listar.php');
}

// Obtener datos de la solicitud
try {
    $pdo = conectarDB();
    
    // Si es usuario normal, solo puede ver sus propias solicitudes
    // ACTUALIZADO: Usa COALESCE para mostrar nombre_solicitante o nombre de usuario
    if (!$es_ti) {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre,
                   u.nombre_completo as usuario_nombre
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.folio = ? AND s.usuario_id = ?
        ");
        $stmt->execute([$folio, $usuario_id]);
    } else {
        // TI puede ver todas las solicitudes
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre,
                   u.nombre_completo as usuario_nombre,
                   u.departamento as solicitante_depto,
                   t.nombre_completo as tecnico_nombre
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN usuarios t ON s.atendido_por = t.id
            WHERE s.folio = ?
        ");
        $stmt->execute([$folio]);
    }
    
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        establecer_alerta('error', 'Solicitud no encontrada o no tienes permiso para verla');
        redirigir(URL_BASE . 'solicitudes/listar.php');
    }
    
    // ====================================
    // OBTENER HISTORIAL COMPLETO DE ESTADOS
    // ====================================
    $stmt_historial = $pdo->prepare("
        SELECT h.*, u.nombre_completo as usuario_nombre
        FROM historial_estados h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.solicitud_id = ?
        ORDER BY h.fecha_cambio ASC
    ");
    $stmt_historial->execute([$solicitud['id']]);
    $historial = $stmt_historial->fetchAll();

    // ====================================
    // OBTENER ARCHIVOS ADJUNTOS
    // ====================================
    $stmt_archivos = $pdo->prepare("
        SELECT nombre_archivo, ruta_archivo, tipo_mime, tamanio, fecha_subida
        FROM archivos_adjuntos
        WHERE solicitud_id = ?
        ORDER BY fecha_subida ASC
    ");
    $stmt_archivos->execute([$solicitud['id']]);
    $archivos_adjuntos = $stmt_archivos->fetchAll();
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar la solicitud: ' . $e->getMessage());
    redirigir(URL_BASE . 'solicitudes/listar.php');
}

/**
 * Obtener clase de badge según estado
 */
function obtener_badge_estado($estado) {
    $badges = [
        'pendiente' => 'bg-warning text-dark',
        'en_proceso' => 'bg-info text-dark',
        'finalizada' => 'bg-success',
        'cancelada' => 'bg-secondary'
    ];
    return $badges[$estado] ?? 'bg-secondary';
}

/**
 * Obtener clase de badge según prioridad
 */
function obtener_badge_prioridad($prioridad) {
    $badges = [
        'critica' => 'bg-danger',
        'alta' => 'bg-warning text-dark',
        'media' => 'bg-info text-dark',
        'baja' => 'bg-secondary'
    ];
    return $badges[$prioridad] ?? 'bg-secondary';
}

/**
 * Obtener icono según tipo de soporte
 */
function obtener_icono_tipo($tipo) {
    return $tipo == 'Apoyo' ? 'bi-hand-thumbs-up' : 'bi-exclamation-triangle';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud <?php echo htmlspecialchars($solicitud['folio']); ?></title>
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
        .info-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 1rem;
            color: #333;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #667eea;
        }
        /* Colores para diferentes estados en timeline */
        .timeline-item.estado-pendiente::before {
            background: #ffc107;
            box-shadow: 0 0 0 2px #ffc107;
        }
        .timeline-item.estado-en_proceso::before {
            background: #17a2b8;
            box-shadow: 0 0 0 2px #17a2b8;
        }
        .timeline-item.estado-finalizada::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }
        .timeline-item.estado-cancelada::before {
            background: #6c757d;
            box-shadow: 0 0 0 2px #6c757d;
        }

        /* === Estilos para archivos adjuntos === */
        .archivo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        .archivo-item:last-child {
            border-bottom: none;
        }
        .archivo-preview {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .archivo-preview:hover {
            transform: scale(1.1);
        }
        .archivo-icono {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            font-size: 1.25rem;
        }

        /* === Dark mode === */
        body[data-theme="dark"] .info-card {
            background: #2d2d2d;
            border-left-color: #667eea;
        }
        body[data-theme="dark"] .info-label {
            color: #b0b0b0;
        }
        body[data-theme="dark"] .info-value {
            color: #e0e0e0;
        }
        body[data-theme="dark"] .archivo-item {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }
        body[data-theme="dark"] .archivo-preview {
            border-color: rgba(255, 255, 255, 0.15);
        }
        body[data-theme="dark"] .archivo-icono {
            background: rgba(102, 126, 234, 0.2);
            color: #8da2fb;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR ⭐ CORREGIDO: Ahora incluye verificación de GTH y Mantenimiento -->
        <?php 
        if (es_usuario_ti()) {
            include __DIR__ . '/../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../includes/sidebar/sidebar_gth.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../includes/sidebar/sidebar_colaborativo.php';
        } elseif (es_usuario_epp()) {
            include __DIR__ . '/../includes/sidebar/sidebar_inventario.php';
        } elseif (es_usuario_gth()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
        } elseif (es_mantenimiento()) {
            include __DIR__ . '/../includes/sidebar/sidebar_mantenimiento.php';
        } elseif (in_array(strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''), ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../includes/sidebar/sidebar_sec.php';
        } else {
            include __DIR__ . '/../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">Detalle de Solicitud</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-file-earmark-text"></i> 
                            <?php echo htmlspecialchars($solicitud['folio']); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($es_ti && $solicitud['estado'] !== 'finalizada' && $solicitud['estado'] !== 'cancelada'): ?>
                        <!-- BOTÓN EDITAR SOLICITUD (SOLO SISTEMAS) -->
                        <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($folio); ?>" 
                           class="btn btn-primary">
                            <i class="bi bi-pencil-square"></i> Editar Solicitud
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($es_ti): ?>
                        <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver a la lista
                        </a>
                        <?php else: ?>
                        <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver a mis solicitudes
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <div class="row">
                    <!-- Columna Principal: Info de la Solicitud -->
                    <div class="col-lg-8 mb-4">
                        
                        <!-- Card Principal -->
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="<?php echo obtener_icono_tipo($solicitud['tipo_soporte']); ?>"></i>
                                    Información de la Solicitud
                                </span>
                                <span class="badge <?php echo obtener_badge_estado($solicitud['estado']); ?>">
                                    <?php echo obtener_texto_estado($solicitud['estado']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                
                                <!-- Fila de badges -->
                                <div class="mb-4 d-flex gap-2 flex-wrap">
                                    <span class="badge <?php echo $solicitud['tipo_soporte'] == 'Apoyo' ? 'bg-info' : 'bg-warning'; ?> px-3 py-2">
                                        <i class="<?php echo obtener_icono_tipo($solicitud['tipo_soporte']); ?>"></i>
                                        <?php echo htmlspecialchars($solicitud['tipo_soporte']); ?>
                                    </span>
                                    <span class="badge <?php echo obtener_badge_prioridad($solicitud['prioridad']); ?> px-3 py-2">
                                        Prioridad: <?php echo ucfirst($solicitud['prioridad']); ?>
                                    </span>
                                </div>

                                <!-- Info en cards -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="info-label">Tipo de Soporte</div>
                                            <div class="info-value"><?php echo htmlspecialchars($solicitud['tipo_soporte']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="info-label">
                                                <?php echo $solicitud['tipo_soporte'] == 'Apoyo' ? 'Tipo de Apoyo' : 'Tipo de Problema'; ?>
                                            </div>
                                            <div class="info-value">
                                                <?php 
                                                if ($solicitud['tipo_soporte'] == 'Apoyo') {
                                                    echo htmlspecialchars($solicitud['tipo_apoyo'] ?? 'No especificado');
                                                } else {
                                                    echo htmlspecialchars($solicitud['tipo_problema'] ?? 'No especificado');
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Descripción -->
                                <div class="info-card">
                                    <div class="info-label">Descripción</div>
                                    <div class="info-value">
                                        <?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?>
                                    </div>
                                </div>

                                <!-- Comentarios de TI (si existen) -->
                                <?php if (!empty($solicitud['comentarios_ti'])): ?>
                                <div class="alert alert-info">
                                    <strong><i class="bi bi-chat-left-text"></i> Comentarios de TI:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($solicitud['comentarios_ti'])); ?>
                                </div>
                                <?php endif; ?>

                                <!-- ====================================== -->
                                <!-- ARCHIVOS ADJUNTOS (EVIDENCIA)          -->
                                <!-- ====================================== -->
                                <?php if (!empty($archivos_adjuntos)): ?>
                                <div class="info-card">
                                    <div class="info-label">
                                        <i class="bi bi-paperclip"></i> Evidencia adjunta
                                        <span class="badge bg-secondary ms-1"><?php echo count($archivos_adjuntos); ?></span>
                                    </div>
                                    <div class="info-value">
                                        <?php foreach ($archivos_adjuntos as $archivo): ?>
                                        <?php
                                            // Determinar ícono según tipo MIME
                                            $icono = 'bi-file-earmark';
                                            if (str_starts_with($archivo['tipo_mime'], 'image/')) {
                                                $icono = 'bi-file-earmark-image';
                                            } elseif ($archivo['tipo_mime'] === 'application/pdf') {
                                                $icono = 'bi-file-earmark-pdf';
                                            } elseif (str_contains($archivo['tipo_mime'], 'word') || str_contains($archivo['tipo_mime'], 'document')) {
                                                $icono = 'bi-file-earmark-word';
                                            } elseif ($archivo['tipo_mime'] === 'text/plain') {
                                                $icono = 'bi-file-earmark-text';
                                            }
                                            
                                            // Formatear tamaño
                                            $tamanio_kb = round($archivo['tamanio'] / 1024, 1);
                                            $tamanio_texto = $tamanio_kb >= 1024 
                                                ? round($tamanio_kb / 1024, 1) . ' MB' 
                                                : $tamanio_kb . ' KB';
                                            
                                            // Verificar si es imagen para preview
                                            $es_imagen = str_starts_with($archivo['tipo_mime'], 'image/');
                                            $ruta_url = URL_BASE . $archivo['ruta_archivo'];
                                        ?>
                                        <div class="archivo-item">
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($es_imagen): ?>
                                                <img src="<?php echo $ruta_url; ?>" 
                                                     alt="Preview" 
                                                     class="archivo-preview"
                                                     onclick="window.open('<?php echo $ruta_url; ?>', '_blank')">
                                                <?php else: ?>
                                                <div class="archivo-icono">
                                                    <i class="<?php echo $icono; ?>"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-semibold" style="font-size: 0.9rem;">
                                                        <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <?php echo $tamanio_texto; ?> &middot; 
                                                        <?php echo date('d/m/Y H:i', strtotime($archivo['fecha_subida'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <a href="<?php echo $ruta_url; ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Ver archivo">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo $ruta_url; ?>" 
                                                   download="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Descargar">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>

                    </div>

                    <!-- Columna Derecha: Timeline -->
                    <div class="col-lg-4 mb-4">
                        
                        <!-- Info del Solicitante -->
                        <div class="card card-custom mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-circle"></i> Información del Solicitante
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Nombre:</strong><br>
                                    <?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Departamento:</strong><br>
                                    <?php echo htmlspecialchars($solicitud['departamento']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Timeline / Historial -->
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Historial
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    
                                    <?php if (!empty($historial)): ?>
                                        <!-- Historial completo desde la tabla historial_estados -->
                                        <?php foreach ($historial as $evento): ?>
                                        <?php 
                                            $estado_clase = $evento['estado_nuevo'] ?? '';
                                            if (empty($evento['estado_anterior'])) {
                                                $titulo = 'Solicitud creada';
                                            } else {
                                                $titulo = 'Estado: ' . obtener_texto_estado($evento['estado_nuevo']);
                                            }
                                        ?>
                                        <div class="timeline-item estado-<?php echo $estado_clase; ?>">
                                            <strong><?php echo $titulo; ?></strong>
                                            <?php if (!empty($evento['comentario']) && $evento['comentario'] !== 'Solicitud creada'): ?>
                                            <p class="mb-1 small text-muted">
                                                <?php echo nl2br(htmlspecialchars($evento['comentario'])); ?>
                                            </p>
                                            <?php endif; ?>
                                            <p class="text-muted mb-0 small">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($evento['usuario_nombre'] ?? 'Sistema'); ?>
                                                <br>
                                                <?php echo formatear_fecha($evento['fecha_cambio'], true); ?>
                                            </p>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Historial básico si no hay registros en la tabla -->
                                        <!-- Creación -->
                                        <div class="timeline-item">
                                            <strong>Solicitud creada</strong>
                                            <p class="text-muted mb-0 small">
                                                <?php echo formatear_fecha($solicitud['fecha_creacion'], true); ?>
                                            </p>
                                        </div>

                                        <!-- Actualización -->
                                        <?php if ($solicitud['fecha_actualizacion']): ?>
                                        <div class="timeline-item">
                                            <strong>Última actualización</strong>
                                            <p class="text-muted mb-0 small">
                                                <?php echo formatear_fecha($solicitud['fecha_actualizacion'], true); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Atención -->
                                        <?php if ($solicitud['fecha_atencion']): ?>
                                        <div class="timeline-item">
                                            <strong>Atendida por</strong>
                                            <p class="mb-0">
                                                <?php echo htmlspecialchars($solicitud['tecnico_nombre'] ?? 'TI'); ?>
                                            </p>
                                            <p class="text-muted mb-0 small">
                                                <?php echo formatear_fecha($solicitud['fecha_atencion'], true); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/modal_crear.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        bodyElement.setAttribute('data-theme', currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = bodyElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => {
                themeToggle.classList.remove('rotating');
            }, 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

</body>
</html>