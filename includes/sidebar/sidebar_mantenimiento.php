<?php
/**
 * Sidebar para departamento de Mantenimiento
 * CORREGIDO: Incluye modal_crear.php para el botón "Nueva Solicitud"
 */

// ⭐ PROTECCIÓN DE SESIÓN - Redirigir si la sesión expiró
if (!isset($_SESSION['usuario_id'])) {
    if (function_exists('destruir_sesion')) {
        destruir_sesion();
    }
    $url_login = defined('URL_BASE') ? URL_BASE . 'auth/InicioSesion.php' : '/auth/InicioSesion.php';
    header('Location: ' . $url_login);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

// IMPORTANTE: Incluir database.php ANTES de usar conectarDB()
require_once __DIR__ . '/../../config/database.php';

// Obtener contador de mantenimientos pendientes del usuario
try {
    $pdo = conectarDB();
    $stmt_mant = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM solicitudes_mantenimiento_ti 
        WHERE usuario_id = :usuario_id 
        AND estado IN ('pendiente', 'en_proceso')
    ");
    $stmt_mant->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $mis_mant_pendientes = $stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $mis_mant_pendientes = 0;
}
?>

<!-- Botón Hamburguesa para Sidebar Responsive -->
<button class="hamburger-btn" 
        type="button" 
        aria-label="Abrir menú de navegación"
        aria-expanded="false"
        aria-controls="sidebar">
    <span class="hamburger-icon">
        <span></span>
        <span></span>
        <span></span>
    </span>
</button>

<!-- Overlay para cerrar sidebar en móvil -->
<div class="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-tools text-white fs-1 mb-2"></i>
        <h4>Mantenimiento</h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ''); ?></small>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/mantenimiento.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">MÓDULO DE TI</small>
            
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
                <a class="nav-link <?php echo $current_page == 'buscar.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                    <i class="bi bi-search"></i> Buscar
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'solicitar_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php">
                    <i class="bi bi-tools"></i> Solicitar Mantenimiento
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['mantenimientos.php', 'ver_mantenimiento.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php">
                    <i class="bi bi-wrench"></i> Mis Mantenimientos
                    <?php if ($mis_mant_pendientes > 0): ?>
                    <span class="badge bg-info ms-2"><?php echo $mis_mant_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ÓRDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> Órdenes de Servicio para Mantenimiento
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
                <a class="nav-link <?php echo in_array($current_page, ['mis_vacaciones.php', 'nueva_solicitud_vacaciones.php', 'ver_solicitud_vacaciones.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php">
                    <i class="bi bi-calendar-check"></i> Mis Vacaciones
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

<!-- ========================================
     MODAL PARA CREAR NUEVA SOLICITUD DE TI
     Este include es NECESARIO para que funcione
     el botón "Nueva Solicitud" del sidebar
     
     El modal está definido en solicitudes/modal_crear.php
     y tiene el ID #modalNuevaSolicitud
     ======================================== -->
<?php include __DIR__ . '/../../solicitudes/modal_crear.php'; ?>