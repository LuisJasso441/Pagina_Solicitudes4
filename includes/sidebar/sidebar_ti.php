<?php
/**
 * Sidebar para usuarios de TI/Sistemas
 * includes/sidebar/sidebar_ti.php
 * 
 * v2.0 - Agregado: Acceso Equipos, Importar Excel, contador acceso
 */

// Obtener p&aacute;gina actual para marcar como activa
$current_page = basename($_SERVER['PHP_SELF']);

// Obtener filtro de estado actual (para marcar links activos)
$filtro_estado_actual = $_GET['estado'] ?? '';

// Determinar si est&aacute; en secci&oacute;n de &oacute;rdenes de servicio
$en_mis_ordenes = in_array($current_page, [
    'mis_ordenes_servicio.php',
    'mis_ordenes_servicio_finalizadas.php',
    'ver_orden_servicio.php'
]);

// Determinar si est&aacute; en secci&oacute;n de gesti&oacute;n de usuarios
$en_gestion_usuarios = in_array($current_page, [
    'dashboard_usuarios.php',
    'crear_usuario.php',
    'editar_usuario.php'
]);

// Obtener estad&iacute;sticas para badges
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../../config/database.php';
        $pdo = conectarDB();
    }
    
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM solicitudes_atencion
    ");
    $stats_sidebar = $stmt->fetch();
    
    // Obtener &oacute;rdenes pendientes de validaci&oacute;n
    $stmt_ordenes = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM ordenes_servicio_mantenimiento 
        WHERE usuario_id = :usuario_id 
        AND estado = 'pendiente_usuario'
    ");
    $stmt_ordenes->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $ordenes_pendientes_validar = $stmt_ordenes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar usuarios activos e inactivos para badge
    $stmt_usuarios = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_usuarios = $stmt_usuarios->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar equipos en inventario
    $stmt_equipos = $pdo->query("SELECT COUNT(*) as total FROM inventario_equipos WHERE estado != 'dado_de_baja'");
    $total_equipos = $stmt_equipos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar mantenimientos pendientes
    $stmt_mant = $pdo->query("SELECT COUNT(*) as total FROM solicitudes_mantenimiento_ti WHERE estado = 'pendiente'");
    $mant_pendientes = $stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar registros de acceso activos
    $stmt_acceso = $pdo->query("SELECT COUNT(*) as total FROM acceso_equipos WHERE estado = 'activo'");
    $total_acceso = $stmt_acceso->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (Exception $e) {
    $stats_sidebar = ['pendientes' => 0, 'en_proceso' => 0];
    $ordenes_pendientes_validar = 0;
    $total_usuarios = 0;
    $total_equipos = 0;
    $mant_pendientes = 0;
    $total_acceso = 0;
}
?>

<!-- Bot&oacute;n Hamburguesa para m&oacute;vil -->
<button class="hamburger-btn" id="hamburgerBtn" aria-label="Abrir men&uacute;">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- Overlay para cerrar sidebar en m&oacute;vil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-laptop text-white fs-1 mb-2"></i>
        <h4>TI / Sistemas</h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ''); ?></small>
        <span class="badge bg-danger mt-2">Administrador</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ti_sistemas.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">GESTI&Oacute;N DE SOLICITUDES</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'gestion_solicitudes.php' && empty($filtro_estado_actual)) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php">
                    <i class="bi bi-folder"></i> Todas las Solicitudes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'gestion_solicitudes.php' && $filtro_estado_actual == 'pendiente') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php?estado=pendiente">
                    <i class="bi bi-clock-history"></i> Pendientes
                    <?php if ($stats_sidebar['pendientes'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $stats_sidebar['pendientes']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'gestion_solicitudes.php' && $filtro_estado_actual == 'en_proceso') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php?estado=en_proceso">
                    <i class="bi bi-gear"></i> En Proceso
                    <?php if ($stats_sidebar['en_proceso'] > 0): ?>
                    <span class="badge bg-info text-dark ms-2"><?php echo $stats_sidebar['en_proceso']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'gestion_solicitudes.php' && $filtro_estado_actual == 'finalizada') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php?estado=finalizada">
                    <i class="bi bi-check-circle"></i> Finalizadas
                </a>
            </li>

            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ADMINISTRACI&Oacute;N DE USUARIOS</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard_usuarios.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php">
                    <i class="bi bi-people"></i> Gesti&oacute;n de Usuarios
                    <?php if ($total_usuarios > 0): ?>
                    <span class="badge bg-secondary ms-2"><?php echo $total_usuarios; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'crear_usuario.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/crear_usuario.php">
                    <i class="bi bi-person-plus"></i> Nuevo Usuario
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">INVENTARIO TI</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php">
                    <i class="bi bi-pc-display"></i> Equipos
                    <?php if ($total_equipos > 0): ?>
                    <span class="badge bg-secondary ms-2"><?php echo $total_equipos; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'crear_equipo.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/crear_equipo.php">
                    <i class="bi bi-plus-circle"></i> Nuevo Equipo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'acceso_equipos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/acceso_equipos.php">
                    <i class="bi bi-key"></i> Acceso Equipos
                    <?php if ($total_acceso > 0): ?>
                    <span class="badge bg-secondary ms-2"><?php echo $total_acceso; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'importar_inventario.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/importar_inventario.php">
                    <i class="bi bi-upload"></i> Importar Excel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['mantenimientos.php', 'ver_mantenimiento.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php">
                    <i class="bi bi-tools"></i> Mantenimientos
                    <?php if ($mant_pendientes > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $mant_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">&Oacute;RDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> &Oacute;rdenes de Mantenimiento
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">HERRAMIENTAS</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'buscar.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                    <i class="bi bi-search"></i> Buscar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/reportes.php">
                    <i class="bi bi-graph-up"></i> Reportes
                </a>
            </li>
            
            <hr class="text-white-50 my-3">
            <li class="nav-item">
                <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesi&oacute;n
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Modal para crear nueva orden de servicio -->
<?php include __DIR__ . '/../ordenes_servicio/modal_crear_orden_servicio.php'; ?>