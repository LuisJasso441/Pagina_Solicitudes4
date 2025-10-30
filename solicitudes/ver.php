<?php
/**
 * Ver detalle de una solicitud
 * Muestra información completa de una solicitud específica
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
    if (!$es_ti) {
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre_completo as solicitante_nombre
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.folio = ? AND s.usuario_id = ?
        ");
        $stmt->execute([$folio, $usuario_id]);
    } else {
        // TI puede ver todas las solicitudes
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre_completo as solicitante_nombre,
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
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php 
        if (es_usuario_ti()) {
            include __DIR__ . '/../includes/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../includes/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../includes/sidebar_normal.php';
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
                    <div>
                        <a href="<?php echo URL_BASE; ?>dashboard/ti_sistemas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver a la lista
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Información Principal -->
                <div class="row">
                    <!-- Columna Izquierda: Detalles -->
                    <div class="col-lg-8 mb-4">
                        
                        <!-- Card Principal -->
                        <div class="card card-custom mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="<?php echo obtener_icono_tipo($solicitud['tipo_soporte']); ?>"></i>
                                    Información de la Solicitud
                                </span>
                                <div>
                                    <span class="badge <?php echo obtener_badge_estado($solicitud['estado']); ?> me-2">
                                        <?php echo obtener_texto_estado($solicitud['estado']); ?>
                                    </span>
                                    <span class="badge <?php echo obtener_badge_prioridad($solicitud['prioridad']); ?>">
                                        Prioridad: <?php echo ucfirst($solicitud['prioridad']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                
                                <!-- Folio -->
                                <div class="info-card">
                                    <div class="info-label">Folio</div>
                                    <div class="info-value">
                                        <strong><?php echo htmlspecialchars($solicitud['folio']); ?></strong>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Tipo de Soporte -->
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="info-label">Tipo de Soporte</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($solicitud['tipo_soporte']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Subtipo -->
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

                        <!-- Timeline -->
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Historial
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    
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

</body>
</html>