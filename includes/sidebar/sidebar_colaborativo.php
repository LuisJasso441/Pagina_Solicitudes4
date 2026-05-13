<?php
/**
 * Sidebar para usuarios colaborativos
 * includes/sidebar/sidebar_colaborativo.php
 */
$current_page = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/../../config/database.php';
?>

<!-- Boton Hamburguesa para Sidebar Responsive -->
<button class="hamburger-btn" 
        type="button" 
        aria-label="Abrir menu de navegacion"
        aria-expanded="false"
        aria-controls="sidebar">
    <span class="hamburger-icon">
        <span></span>
        <span></span>
        <span></span>
    </span>
</button>

<!-- Overlay para cerrar sidebar en movil -->
<div class="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-people-fill text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></h4>
        <small><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
        <span class="badge bg-info mt-2">Colaborativo</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'colaborativo.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/colaborativo/colaborativo.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SOLICITUDES PARA SISTEMAS</small>
            
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                    <i class="bi bi-plus-circle"></i> Nueva Solicitud
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'listar.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>solicitudes/listar.php">
                    <i class="bi bi-list-ul"></i> Mis Solicitudes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'listar_mantenimientos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php">
                    <i class="bi bi-wrench"></i> Mis Mantenimientos
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SOLICITUDES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'documentos_colaborativos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php">
                    <i class="bi bi-file-earmark-text"></i> Solicitudes de Servicio a Clientes
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">COTIZACIONES QUIMICOS</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'cotizaciones_qr.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php">
                    <i class="bi bi-file-earmark-medical"></i> Cotizaciones QR
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ORDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> Ordenes de Mantenimiento
                </a>
            </li>

            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SALA DE JUNTAS</small>

            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reservaciones_sala.php' ? 'active' : ''; ?>" 
                href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php">
                    <i class="bi bi-calendar-event"></i> Reservar Sala de Juntas
                </a>
            </li>

            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">VACACIONES</small>

            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'mis_vacaciones.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php">
                    <i class="bi bi-calendar-check"></i> Mis Vacaciones
                </a>
            </li>
            <?php if (function_exists('es_admin_area') && es_admin_area()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'vacaciones_admin.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php">
                    <i class="bi bi-person-check"></i> Panel Admin Area
                </a>
            </li>
            <?php endif; ?>
            
            <hr class="text-white-50 my-3">
            <li class="nav-item">
                <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesion
                </a>
            </li>
        </ul>
    </nav>
</aside>