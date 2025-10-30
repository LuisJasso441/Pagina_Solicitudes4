<?php
/**
 * Dashboard para TI/Sistemas
 * Panel de control para gestionar todas las solicitudes
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que sea usuario de TI
if (!es_usuario_ti()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id = $_SESSION['usuario_id'];

// Obtener estadísticas globales
try {
    $pdo = conectarDB();
    
    // Estadísticas generales
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total,
            SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas
        FROM solicitudes_atencion
    ");
    $stats = $stmt->fetch();
    
    // Solicitudes pendientes recientes (últimas 10)
    $stmt = $pdo->query("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.estado = 'pendiente'
        ORDER BY 
            CASE s.prioridad
                WHEN 'critica' THEN 1
                WHEN 'alta' THEN 2
                WHEN 'media' THEN 3
                WHEN 'baja' THEN 4
            END,
            s.fecha_creacion ASC
        LIMIT 10
    ");
    $pendientes = $stmt->fetchAll();
    
    // Solicitudes en proceso asignadas a este técnico
    $stmt = $pdo->prepare("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.estado = 'en_proceso' AND s.atendido_por = ?
        ORDER BY s.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $mis_asignadas = $stmt->fetchAll();
    
    // Solicitudes finalizadas hoy
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM solicitudes_atencion
        WHERE estado = 'finalizada' 
        AND DATE(fecha_actualizacion) = CURDATE()
    ");
    $finalizadas_hoy = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar estadísticas: ' . $e->getMessage());
    $stats = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0, 'criticas' => 0];
    $pendientes = [];
    $mis_asignadas = [];
    $finalizadas_hoy = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard TI/Sistemas</title>
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
                <i class="bi bi-laptop text-white fs-1 mb-2"></i>
                <h4>TI / Sistemas</h4>
                <small class="text-white-50"><?php echo htmlspecialchars($nombre_usuario); ?></small>
                <span class="badge bg-danger mt-2">Administrador</span>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo URL_BASE; ?>dashboard/ti_sistemas.php">
                            <i class="bi bi-house-door"></i> Inicio
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">GESTIÓN DE SOLICITUDES</small>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php">
                            <i class="bi bi-folder"></i> Todas las Solicitudes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/pendientes.php">
                            <i class="bi bi-clock-history"></i> Pendientes
                            <?php if ($stats['pendientes'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $stats['pendientes']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/en_proceso.php">
                            <i class="bi bi-gear"></i> En Proceso
                            <?php if ($stats['en_proceso'] > 0): ?>
                            <span class="badge bg-info text-dark ms-2"><?php echo $stats['en_proceso']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/finalizadas.php">
                            <i class="bi bi-check-circle"></i> Finalizadas
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">INVENTARIO</small>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/inventario.php">
                            <i class="bi bi-pc-display"></i> Equipos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/mantenimientos.php">
                            <i class="bi bi-tools"></i> Mantenimientos
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">HERRAMIENTAS</small>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                            <i class="bi bi-search"></i> Buscar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/reportes.php">
                            <i class="bi bi-graph-up"></i> Reportes
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
                            <i class="bi bi-laptop"></i>
                            TI / Sistemas
                        </span>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Estadísticas Globales -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-2 mb-3">
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

                    <div class="col-md-2 mb-3">
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

                    <div class="col-md-2 mb-3">
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
                                <div class="icon-box mx-auto mb-3" style="background: #fee; color: #dc3545;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['criticas']; ?></h2>
                                <p class="stats-label">Críticas</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box mx-auto mb-3" style="background: #e0f2fe; color: #0ea5e9;">
                                    <i class="bi bi-check2-all"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $finalizadas_hoy; ?></h2>
                                <p class="stats-label">Hoy</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-lightning-charge"></i> Accesos Rápidos
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="btn btn-gradient">
                                        <i class="bi bi-folder"></i> Ver Todas las Solicitudes
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>ti_sistemas/pendientes.php" class="btn btn-outline-warning">
                                        <i class="bi bi-clock-history"></i> Atender Pendientes
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>ti_sistemas/inventario.php" class="btn btn-outline-info">
                                        <i class="bi bi-pc-display"></i> Inventario de Equipos
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/buscar.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-search"></i> Buscar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dos columnas: Pendientes y Mis asignadas -->
                <div class="row">
                    
                    <!-- Solicitudes Pendientes -->
                    <div class="col-lg-7 mb-4">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-exclamation-circle"></i> Solicitudes Pendientes</span>
                                <a href="<?php echo URL_BASE; ?>ti_sistemas/pendientes.php" class="btn btn-sm btn-light">
                                    Ver todas <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($pendientes)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-check-circle fs-1 text-success"></i>
                                    <p class="text-muted mt-3 mb-0">¡No hay solicitudes pendientes!</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($pendientes as $sol): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <strong class="me-2"><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                                    <span class="badge <?php 
                                                        if ($sol['prioridad'] == 'critica') echo 'bg-danger';
                                                        elseif ($sol['prioridad'] == 'alta') echo 'bg-warning text-dark';
                                                        elseif ($sol['prioridad'] == 'media') echo 'bg-info text-dark';
                                                        else echo 'bg-secondary';
                                                    ?>">
                                                        <?php echo ucfirst($sol['prioridad']); ?>
                                                    </span>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="bi bi-person"></i> 
                                                    <?php echo htmlspecialchars($sol['solicitante_nombre']); ?>
                                                    <span class="text-muted">- <?php echo htmlspecialchars($sol['departamento']); ?></span>
                                                </p>
                                                <p class="mb-1 small text-muted">
                                                    <?php 
                                                    $desc = htmlspecialchars($sol['descripcion']);
                                                    echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc;
                                                    ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> 
                                                    <?php echo formatear_fecha($sol['fecha_creacion'], true); ?>
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

                    <!-- Mis solicitudes asignadas -->
                    <div class="col-lg-5 mb-4">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-check"></i> Mis Asignadas</span>
                                <span class="badge bg-info text-dark"><?php echo count($mis_asignadas); ?></span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($mis_asignadas)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="text-muted mt-3 mb-0">No tienes solicitudes asignadas</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($mis_asignadas as $sol): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="flex-grow-1">
                                                <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                                <p class="mb-0 small text-muted">
                                                    <?php echo htmlspecialchars($sol['solicitante_nombre']); ?>
                                                </p>
                                            </div>
                                            <span class="badge bg-info text-dark">En proceso</span>
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