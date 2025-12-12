<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';

if (!es_usuario_colaborativo()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
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
    require_once __DIR__ . '/../../config/database.php';
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
    <title>Dashboard Colaborativo - <?php echo htmlspecialchars($departamento); ?></title>
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
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </p>
                    </div>
                    <div class="user-info">
                        <span class="user-badge" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                            <i class="bi bi-people-fill"></i>
                            <?php echo htmlspecialchars($departamento); ?>
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
                                    <a href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php" class="btn btn-success">
                                        <i class="bi bi-file-earmark-text"></i> Documentos SSC
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="btn btn-info">
                                        <i class="bi bi-clipboard-check"></i> Órdenes de Mantenimiento
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solicitudes recientes -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Solicitudes Recientes
                            </div>
                            <div class="card-body">
                                <?php if (empty($solicitudes_recientes)): ?>
                                <p class="text-muted text-center mb-0">No tienes solicitudes aún</p>
                                <?php else: ?>
                                <div class="table-responsive-custom">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Folio</th>
                                                <th>Descripción</th>
                                                <th>Fecha</th>
                                                <th>Estado</th>
                                                <th>Prioridad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($solicitudes_recientes as $sol): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sol['folio']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(mb_substr($sol['descripcion'], 0, 50)); ?>...</td>
                                                <td><?php echo date('d/m/Y', strtotime($sol['fecha_creacion'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $sol['estado']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $sol['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $sol['prioridad'] == 'critica' ? 'danger' : 
                                                            ($sol['prioridad'] == 'alta' ? 'warning' : 
                                                            ($sol['prioridad'] == 'media' ? 'info' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($sol['prioridad']); ?>
                                                    </span>
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