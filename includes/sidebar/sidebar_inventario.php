<?php
/**
 * Sidebar para módulo de Inventario de EPP
 * Ubicación: includes/sidebar/sidebar_inventario.php
 * 
 * EXCLUSIVO para: Almacén de Refacciones, Seguridad, Contabilidad
 * Estructura consistente con sidebar_colaborativo.php y sidebar_normal.php
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

// Incluir database.php ANTES de usar conectarDB()
require_once __DIR__ . '/../../config/database.php';

// Verificar permisos EPP
require_once __DIR__ . '/../inventario_epp/inventario_epp_funciones.php';
$permisos_epp = verificar_permisos_epp($_SESSION['usuario_id']);

// Estadísticas para badges
try {
    $pdo = conectarDB();
    
    // Artículos sin stock
    $stmt_sin_stock = $pdo->query("SELECT COUNT(*) as total FROM inventario_epp WHERE activo = 1 AND stock = 0");
    $epp_sin_stock = $stmt_sin_stock->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Movimientos del mes
    $stmt_movs = $pdo->query("SELECT COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW())");
    $movs_mes = $stmt_movs->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Mantenimientos TI pendientes del usuario
    $stmt_mant = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM solicitudes_mantenimiento_ti 
        WHERE usuario_id = :usuario_id 
        AND estado IN ('pendiente', 'en_proceso')
    ");
    $stmt_mant->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $mis_mant_pendientes = $stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $epp_sin_stock = 0;
    $movs_mes = 0;
    $mis_mant_pendientes = 0;
}

// Determinar páginas activas
$en_inventario = in_array($current_page, ['inventario_epp.php', 'ver_epp.php']);
$en_movimientos = in_array($current_page, ['ver_movimiento.php']);
$en_agregar = ($current_page === 'agregar_epp.php');
$en_registrar_mov = ($current_page === 'registrar_movimiento.php');
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
        <i class="bi bi-shield-check text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?></h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ''); ?></small>
        <span class="badge bg-success mt-2"><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?></span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            
            <!-- Inicio / Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard_epp.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/dashboard_epp.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">MODULO DE TI</small>
            
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
            <small class="text-white-50 px-3 fw-bold">INVENTARIO EPP</small>
            
            <!-- Ver Inventario -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? 'inventario') === 'inventario') || $en_inventario ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-box-seam"></i> Ver Inventario
                </a>
            </li>
            
            <?php if ($permisos_epp['puede_crear']): ?>
            <!-- Agregar EPP -->
            <li class="nav-item">
                <a class="nav-link <?php echo $en_agregar ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/agregar_epp.php">
                    <i class="bi bi-plus-circle"></i> Agregar EPP
                </a>
            </li>
            
            <!-- Registrar Movimiento -->
            <li class="nav-item">
                <a class="nav-link <?php echo $en_registrar_mov ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/registrar_movimiento.php">
                    <i class="bi bi-pencil-square"></i> Registrar Movimiento
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($epp_sin_stock > 0): ?>
            <li class="nav-item">
                <a class="nav-link text-warning" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-exclamation-triangle"></i> Sin Stock
                    <span class="badge bg-danger ms-2"><?php echo $epp_sin_stock; ?></span>
                </a>
            </li>
            <?php endif; ?>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">&Oacute;RDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> &Oacute;rdenes de Mantenimiento
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