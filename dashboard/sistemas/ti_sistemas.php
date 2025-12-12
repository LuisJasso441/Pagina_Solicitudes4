<?php
/**
 * Dashboard para TI/Sistemas
 * Panel de control para gestionar todas las solicitudes
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';

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
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_ti.php'; ?>

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
                        <span class="user-badge">
                            <i class="bi bi-shield-check"></i>
                            TI / Sistemas
                        </span>
                    </div>
                </div>

                <?php echo mostrar_alerta(); ?>

                <!-- Tarjetas de estadísticas -->
                <div class="row">
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
                                <div class="icon-box mx-auto mb-3" style="background-color: #f8d7da; color: #dc3545;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['criticas']; ?></h2>
                                <p class="stats-label">Críticas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Principal -->
                <div class="row">
                    <!-- Solicitudes Pendientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-clock-history"></i> Solicitudes Pendientes</span>
                                <a href="<?php echo URL_BASE; ?>ti_sistemas/pendientes.php" class="btn btn-sm btn-light">
                                    Ver todas
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pendientes)): ?>
                                <p class="text-muted text-center mb-0">No hay solicitudes pendientes</p>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($pendientes as $sol): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="flex-grow-1">
                                                <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                                <p class="mb-0 small text-muted">
                                                    <?php echo htmlspecialchars($sol['solicitante_nombre']); ?> - 
                                                    <?php echo htmlspecialchars($sol['departamento']); ?>
                                                </p>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $sol['prioridad'] == 'critica' ? 'danger' : 
                                                    ($sol['prioridad'] == 'alta' ? 'warning' : 
                                                    ($sol['prioridad'] == 'media' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($sol['prioridad']); ?>
                                            </span>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mis Asignadas -->
                    <div class="col-lg-6 mb-4">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-check"></i> Mis Asignadas</span>
                                <span class="badge bg-info"><?php echo count($mis_asignadas); ?> en proceso</span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($mis_asignadas)): ?>
                                <p class="text-muted text-center mb-0">No tienes solicitudes asignadas</p>
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
    <?php include __DIR__ . '/../../solicitudes/modal_crear.php'; ?>

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

</body>
</html>