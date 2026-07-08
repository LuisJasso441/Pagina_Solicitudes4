<?php
/**
 * Sidebar - Inventario de EPP
 * Exclusivo para: Almacén de Refacciones, Seguridad, Contabilidad
 * Ubicación: includes/sidebar/sidebar_inventario.php
 * 
 * VERSIÓN 2.1 - Secciones comunes + Inventario EPP + Vales de Entrega
 */

// Protección de sesión
if (!isset($_SESSION['usuario_id'])) {
    if (function_exists('destruir_sesion')) {
        destruir_sesion();
    }
    $url_login = defined('URL_BASE') ? URL_BASE . 'auth/InicioSesion.php' : '/auth/InicioSesion.php';
    header('Location: ' . $url_login);
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../inventario_epp/inventario_epp_funciones.php';

// =====================================================
// Estadísticas para badges
// =====================================================
try {
    $pdo_sidebar = conectarDB();
    
    // EPP sin stock
    $stmt_sin_stock = $pdo_sidebar->query("SELECT COUNT(*) as total FROM inventario_epp WHERE activo = 1 AND stock = 0");
    $epp_sin_stock = $stmt_sin_stock->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Movimientos del mes
    $stmt_movs = $pdo_sidebar->query("SELECT COUNT(*) as total FROM movimientos_epp WHERE MONTH(fecha_movimiento) = MONTH(NOW()) AND YEAR(fecha_movimiento) = YEAR(NOW())");
    $movs_mes = $stmt_movs->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Vales pendientes
    $stmt_vales = $pdo_sidebar->query("SELECT COUNT(*) as total FROM vales_epp WHERE estado = 'Pendiente'");
    $vales_pendientes = $stmt_vales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Órdenes de servicio pendientes del usuario
    $stmt_ordenes = $pdo_sidebar->prepare("
        SELECT COUNT(*) as total 
        FROM ordenes_servicio_mantenimiento 
        WHERE usuario_id = :usuario_id 
        AND estado = 'pendiente_usuario'
    ");
    $stmt_ordenes->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $ordenes_pendientes_validar = $stmt_ordenes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Mantenimientos pendientes de firma del usuario
    $stmt_mant = $pdo_sidebar->prepare("
        SELECT COUNT(*) as total 
        FROM solicitudes_mantenimiento_ti 
        WHERE usuario_id = :usuario_id 
        AND estado = 'finalizada'
    ");
    $stmt_mant->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $mant_pendientes_firma = $stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $epp_sin_stock = 0;
    $movs_mes = 0;
    $vales_pendientes = 0;
    $ordenes_pendientes_validar = 0;
    $mant_pendientes_firma = 0;
}

// =====================================================
// Determinar páginas activas
// =====================================================
$en_dashboard = ($current_page === 'dashboard_epp.php');
$en_agregar = ($current_page === 'agregar_epp.php');
$en_registrar_mov = ($current_page === 'registrar_movimiento.php');
$en_crear_vale = ($current_page === 'crear_vale_epp.php');

// Permisos EPP
$permisos_sidebar = verificar_permisos_epp($_SESSION['usuario_id']);
$depto_sidebar = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_seguridad = ($depto_sidebar === 'seguridad');
$es_almacen = ($depto_sidebar === 'almacen_refacciones');
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
        <h4>Inventario EPP</h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
        <span class="badge bg-success mt-2"><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ucfirst($_SESSION['departamento'])); ?></span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            
            <!-- Inicio / Dashboard EPP -->
            <li class="nav-item">
                <a class="nav-link <?php echo $en_dashboard ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/dashboard_epp.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <!-- ============================================ -->
            <!-- SOLICITUDES PARA SISTEMAS -->
            <!-- ============================================ -->
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
                <a class="nav-link <?php echo in_array($current_page, ['mantenimientos.php', 'ver_mantenimiento.php', 'solicitar_mantenimiento.php', 'crear_mantenimiento_historico.php', 'ver_mantenimiento_historico.php', 'editar_mantenimiento_historico.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php">
                    <i class="bi bi-wrench"></i> Mis Mantenimientos
                    <?php if ($mant_pendientes_firma > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $mant_pendientes_firma; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'bitacora_aires.php' ? 'active' : ''; ?>"
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/bitacora_aires/bitacora_aires.php">
                    <i class="bi bi-snow"></i> Control Aire Acondicionado
                </a>
            </li>
            
            <!-- ============================================ -->
            <!-- INVENTARIO EPP -->
            <!-- ============================================ -->
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">INVENTARIO EPP</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? 'inventario') === 'inventario') || $current_page === 'ver_epp.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-box-seam"></i> Ver Inventario
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? '') === 'movimientos') || $current_page === 'ver_movimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=movimientos">
                    <i class="bi bi-arrow-left-right"></i> Movimientos
                    <?php if ($movs_mes > 0): ?>
                    <span class="badge bg-info ms-2"><?php echo $movs_mes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if ($permisos_sidebar['puede_crear']): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $en_agregar ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/agregar_epp.php">
                    <i class="bi bi-plus-circle"></i> Agregar EPP
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $en_registrar_mov ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/registrar_movimiento.php">
                    <i class="bi bi-pencil-square"></i> Registrar Movimiento
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link text-warning" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-exclamation-triangle"></i> Sin Stock
                    <span class="badge bg-danger ms-2"><?php echo $epp_sin_stock; ?></span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- ============================================ -->
            <!-- VALES DE ENTREGA -->
            <!-- ============================================ -->
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">VALES DE ENTREGA</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'vales_epp.php' || $current_page === 'ver_vale_epp.php') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php">
                    <i class="bi bi-file-earmark-text"></i> Ver Vales
                    <?php if ($vales_pendientes > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $vales_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if ($es_seguridad): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $en_crear_vale ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/crear_vale_epp.php">
                    <i class="bi bi-file-earmark-plus"></i> Crear Vale
                </a>
            </li>
            <?php endif; ?>
            
            <!-- ============================================ -->
            <!-- ÓRDENES DE SERVICIO -->
            <!-- ============================================ -->
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ÓRDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> Órdenes de Mantenimiento
                    <?php if ($ordenes_pendientes_validar > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $ordenes_pendientes_validar; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- ============================================ -->
            <!-- SALA DE JUNTAS -->
            <!-- ============================================ -->
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SALA DE JUNTAS</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reservaciones_sala.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php">
                    <i class="bi bi-calendar-event"></i> Reservar Sala de Juntas
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

<!-- Modal para crear nueva solicitud de TI -->
<?php include __DIR__ . '/../../solicitudes/modal_crear.php'; ?>
<!-- Modal para crear nueva orden de servicio -->
<?php include __DIR__ . '/../ordenes_servicio/modal_crear_orden_servicio.php'; ?>