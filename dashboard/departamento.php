<?php
/**
 * Dashboard para departamentos normales (NO colaborativos)
 * Para usuarios que NO son TI ni departamentos colaborativos
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';

// Verificar que NO sea TI ni colaborativo
if (es_usuario_ti()) {
    redirigir(URL_BASE . 'dashboard/ti_sistemas.php');
}

if (es_usuario_colaborativo()) {
    redirigir(URL_BASE . 'dashboard/colaborativo.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];
$usuario_id = $_SESSION['usuario_id'];

// Estadísticas del usuario
$stats = [
    'pendientes' => 0,
    'en_proceso' => 0,
    'finalizadas' => 0,
    'total' => 0
];

// Obtener estadísticas reales
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    // Contar solicitudes por estado
    $stmt = $pdo->prepare("
        SELECT estado, COUNT(*) as total 
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        GROUP BY estado
    ");
    $stmt->execute([$usuario_id]);
    
    while ($row = $stmt->fetch()) {
        if ($row['estado'] == 'pendiente') {
            $stats['pendientes'] = $row['total'];
        } elseif ($row['estado'] == 'en_proceso') {
            $stats['en_proceso'] = $row['total'];
        } elseif ($row['estado'] == 'finalizada') {
            $stats['finalizadas'] = $row['total'];
        }
    }
    
    $stats['total'] = $stats['pendientes'] + $stats['en_proceso'] + $stats['finalizadas'];
    
    // Obtener solicitudes recientes
    $stmt = $pdo->prepare("
        SELECT folio, descripcion, fecha_creacion, estado, prioridad
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $solicitudes_recientes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $solicitudes_recientes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($departamento); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-building text-white fs-1 mb-2"></i>
                <h4><?php echo htmlspecialchars($departamento); ?></h4>
                <small class="text-white-50"><?php echo htmlspecialchars($nombre_usuario); ?></small>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo URL_BASE; ?>dashboard/departamento.php">
                            <i class="bi bi-house-door"></i> Inicio
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">MIS SOLICITUDES</small>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/crear_mantenimiento.php">
                            <i class="bi bi-tools"></i> Solicitar Mantenimiento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/listar.php">
                            <i class="bi bi-list-ul"></i> Mis Solicitudes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/listar_mantenimientos.php">
                            <i class="bi bi-wrench"></i> Mis Mantenimientos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                            <i class="bi bi-search"></i> Buscar
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-3">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Navbar superior -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </p>
                    </div>
                    <div class="user-info">
                        <!-- Notificaciones - DESHABILITADO (manteniendo SSE activo) -->
                        <?php // include __DIR__ . '/../includes/notificaciones_ui.php'; ?>
                        
                        <span class="user-badge">
                            <i class="bi bi-building"></i>
                            <?php echo htmlspecialchars($departamento); ?>
                        </span>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Estadísticas -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-warning mx-auto mb-3">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['pendientes']; ?></h2>
                                <p class="stats-label">Pendientes</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-info mx-auto mb-3">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['en_proceso']; ?></h2>
                                <p class="stats-label">En Proceso</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-success mx-auto mb-3">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['finalizadas']; ?></h2>
                                <p class="stats-label">Finalizadas</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box mx-auto mb-3" style="background: #e0e7ff; color: #6366f1;">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['total']; ?></h2>
                                <p class="stats-label">Total</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-lightning-charge"></i> Acciones Rápidas
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="#" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                        <i class="bi bi-plus-circle"></i> Nueva Solicitud de Atención
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/crear_mantenimiento.php" class="btn btn-outline-primary">
                                        <i class="bi bi-tools"></i> Solicitar Mantenimiento
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="btn btn-outline-info">
                                        <i class="bi bi-list-ul"></i> Ver Mis Solicitudes
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/buscar.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-search"></i> Buscar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solicitudes recientes -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-text"></i> Mis Solicitudes Recientes</span>
                                <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="btn btn-sm btn-light">
                                    Ver todas <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($solicitudes_recientes)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="text-muted mt-3 mb-0">No tienes solicitudes</p>
                                    <a href="#" class="btn btn-gradient mt-3" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                        <i class="bi bi-plus-circle"></i> Crear primera solicitud
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($solicitudes_recientes as $solicitud): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($solicitud['folio']); ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <strong class="me-3"><?php echo htmlspecialchars($solicitud['folio']); ?></strong>
                                                    <span class="badge badge-<?php echo $solicitud['estado']; ?>">
                                                        <?php echo obtener_texto_estado($solicitud['estado']); ?>
                                                    </span>
                                                    <span class="badge ms-2 <?php 
                                                        if ($solicitud['prioridad'] == 'critica') echo 'bg-danger';
                                                        elseif ($solicitud['prioridad'] == 'alta') echo 'bg-warning text-dark';
                                                        elseif ($solicitud['prioridad'] == 'media') echo 'bg-info text-dark';
                                                        else echo 'bg-secondary';
                                                    ?>">
                                                        <?php echo ucfirst($solicitud['prioridad']); ?>
                                                    </span>
                                                </div>
                                                <p class="mb-1 text-muted small">
                                                    <?php 
                                                    $desc = htmlspecialchars($solicitud['descripcion']);
                                                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                                    ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> 
                                                    <?php echo formatear_fecha($solicitud['fecha_creacion'], true); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <i class="bi bi-chevron-right"></i>
                                            </div>
                                        </div>
                                    </a>
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

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/../solicitudes/modal_crear.php'; ?>

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