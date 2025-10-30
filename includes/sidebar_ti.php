<?php
/**
 * Sidebar para usuarios de TI/Sistemas
 * Componente reutilizable
 */

// Obtener página actual para marcar como activa
$current_page = basename($_SERVER['PHP_SELF']);

// Obtener estadísticas para badges (opcional)
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
    }
    
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM solicitudes_atencion
    ");
    $stats_sidebar = $stmt->fetch();
} catch (Exception $e) {
    $stats_sidebar = ['pendientes' => 0, 'en_proceso' => 0];
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-laptop text-white fs-1 mb-2"></i>
        <h4>TI / Sistemas</h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
        <span class="badge bg-danger mt-2">Administrador</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ti_sistemas.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ti_sistemas.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">GESTIÓN DE SOLICITUDES</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'todas_solicitudes.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/todas_solicitudes.php">
                    <i class="bi bi-folder"></i> Todas las Solicitudes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'pendientes.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/pendientes.php">
                    <i class="bi bi-clock-history"></i> Pendientes
                    <?php if ($stats_sidebar['pendientes'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $stats_sidebar['pendientes']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'en_proceso.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/en_proceso.php">
                    <i class="bi bi-gear"></i> En Proceso
                    <?php if ($stats_sidebar['en_proceso'] > 0): ?>
                    <span class="badge bg-info text-dark ms-2"><?php echo $stats_sidebar['en_proceso']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'finalizadas.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/finalizadas.php">
                    <i class="bi bi-check-circle"></i> Finalizadas
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">INVENTARIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/inventario.php">
                    <i class="bi bi-pc-display"></i> Equipos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'mantenimientos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>ti_sistemas/mantenimientos.php">
                    <i class="bi bi-tools"></i> Mantenimientos
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
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </nav>
</aside>