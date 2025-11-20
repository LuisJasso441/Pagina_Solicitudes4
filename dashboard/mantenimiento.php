<?php
/**
 * Dashboard específico para departamento de MANTENIMIENTO
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';

// Verificar que sea departamento de Mantenimiento
if (strtolower($_SESSION['departamento']) !== 'mantenimiento') {
    header('Location: ' . URL_BASE . 'dashboard/index.php');
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];
$usuario_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mantenimiento | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/sidebar_mantenimiento.php'; ?>

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
                        <span class="user-badge" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="bi bi-tools"></i>
                            MANTENIMIENTO
                        </span>
                    </div>
                </div>

                <?php echo mostrar_alerta(); ?>

                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong>GESTIÓN DE ÓRDENES DE SERVICIO</strong><br>
                        Como parte de Mantenimiento, tienes acceso a la gestión completa de órdenes de servicio y solicitudes de mantenimiento.
                    </div>
                </div>

                <!-- ACCIONES RÁPIDAS -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-lightning-charge"></i> Acciones Rápidas
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio_mantenimiento.php" class="btn btn-gradient">
                                        <i class="bi bi-tools"></i> Órdenes de Servicio
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="btn btn-outline-info">
                                        <i class="bi bi-list-ul"></i> Ver Solicitudes
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/buscar.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-search"></i> Buscar
                                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
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