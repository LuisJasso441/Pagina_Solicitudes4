<?php
/**
 * Dashboard Principal - Cotizaciones de Químicos y/o Residuos
 * Ubicación: dashboard/cotizaciones_qr/cotizaciones_qr.php
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

// Incluir funciones CQR (verificar que existe primero)
$funciones_cqr = __DIR__ . '/../../includes/cotizaciones_qr/cotizaciones_qr_funciones.php';
if (file_exists($funciones_cqr)) {
    require_once $funciones_cqr;
} else {
    // Si no existe el archivo de funciones, redirigir con error
    $_SESSION['error'] = "El módulo de Cotizaciones no está instalado correctamente.";
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    exit;
}

// Verificar permisos CQR
$permisos = verificar_permisos_cqr($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    $_SESSION['error'] = "No tienes acceso a este módulo.";
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/colaborativo.php');
    exit;
}

// Determinar vista (local = en proceso, global = completados)
$vista = $_GET['vista'] ?? 'local';
$ubicacion = ($vista === 'global') ? 'global' : 'local';

// Filtros
$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
];

// Obtener cotizaciones
$cotizaciones = obtener_cotizaciones_qr($ubicacion, $_SESSION['usuario_id'], $_SESSION['departamento'], $filtros);

// Estadísticas
$stats = obtener_estadisticas_cqr();

// Determinar rol del usuario
$es_ventas = strpos(strtolower($_SESSION['departamento'] ?? ''), 'ventas') !== false;
$es_normatividad = strpos(strtolower($_SESSION['departamento'] ?? ''), 'normatividad') !== false;

$page_title = "Cotizaciones de Químicos y/o Residuos";
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
        /* Estilos específicos del módulo CQR */
        .stats-card {
            border-radius: var(--border-radius-lg);
            border: none;
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-normal);
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .vista-tabs .nav-link {
            border-radius: var(--border-radius-pill);
            margin-right: 10px;
            padding: 8px 20px;
            background: #e9ecef;
            color: var(--text-secondary);
        }
        
        .vista-tabs .nav-link.active {
            background-color: var(--color-brand-dark);
            color: var(--text-white);
        }
        
        .filtros-card {
            background: var(--bg-card);
            border-radius: var(--border-radius-lg);
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
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
                    <h2 class="mb-1">
                        <i class="bi bi-flask text-primary"></i>
                        <?php echo $page_title; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($es_ventas): ?>
                            Gestiona tus solicitudes de cotización
                        <?php elseif ($es_normatividad): ?>
                            Revisa y responde las cotizaciones de Ventas
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if ($permisos['puede_crear']): ?>
                <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/nueva_cotizacion_qr.php" 
                   class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nueva Cotización
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Total</h6>
                                    <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="bi bi-folder fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Enviadas</h6>
                                    <h3 class="mb-0"><?php echo $stats['enviadas']; ?></h3>
                                </div>
                                <i class="bi bi-send fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">En Revisión</h6>
                                    <h3 class="mb-0"><?php echo $stats['en_revision']; ?></h3>
                                </div>
                                <i class="bi bi-hourglass-split fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0">Finalizadas</h6>
                                    <h3 class="mb-0"><?php echo $stats['finalizadas']; ?></h3>
                                </div>
                                <i class="bi bi-check-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs de Vista -->
            <ul class="nav vista-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo $vista === 'local' ? 'active' : ''; ?>" 
                       href="?vista=local">
                        <i class="bi bi-inbox"></i> Base Local (En Proceso)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $vista === 'global' ? 'active' : ''; ?>" 
                       href="?vista=global">
                        <i class="bi bi-archive"></i> Base Global (Completados)
                    </a>
                </li>
            </ul>
            
            <!-- Filtros -->
            <div class="filtros-card">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="busqueda" class="form-control" 
                               placeholder="Folio, nombre..." 
                               value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="enviada" <?php echo $filtros['estado'] === 'enviada' ? 'selected' : ''; ?>>Enviada</option>
                            <option value="en_revision" <?php echo $filtros['estado'] === 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                            <option value="finalizada" <?php echo $filtros['estado'] === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control" 
                               value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control" 
                               value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="?vista=<?php echo $vista; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Tabla de Cotizaciones -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($cotizaciones)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No hay cotizaciones <?php echo $vista === 'global' ? 'completadas' : 'en proceso'; ?></p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Nombre Amigable</th>
                                    <th>Nombre Técnico</th>
                                    <th>Categoría</th>
                                    <th>Creador</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cotizaciones as $cot): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo htmlspecialchars($cot['folio']); ?></strong>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($cot['fecha_solicitud'])); ?></td>
                                    <td><?php echo htmlspecialchars($cot['nombre_amigable']); ?></td>
                                    <td><?php echo htmlspecialchars($cot['nombre_tecnico'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo obtener_nombre_categoria_cqr($cot['categoria']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo htmlspecialchars($cot['creador_nombre']); ?>
                                            <br>
                                            <span class="text-muted"><?php echo ucfirst($cot['departamento_creador']); ?></span>
                                        </small>
                                    </td>
                                    <td><?php echo obtener_badge_estado_cqr($cot['estado']); ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=<?php echo $cot['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                            <i class="bi bi-eye"></i>
                                        </a>
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
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div class="toast show align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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
    </script>
</body>
</html>