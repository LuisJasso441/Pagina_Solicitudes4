<?php
/**
 * Sidebar para módulo de Inventario de EPP
 * Ubicación: includes/sidebar/sidebar_inventario.php
 * 
 * EXCLUSIVO para: Almacén de Refacciones, Seguridad, Contabilidad
 */
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
    
} catch (Exception $e) {
    $epp_sin_stock = 0;
    $movs_mes = 0;
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
        <h4>Inventario EPP</h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
        <span class="badge bg-success mt-2"><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            
            <!-- Inicio / Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($en_inventario && !isset($_GET['vista'])) || ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? 'inventario') === 'inventario') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">INVENTARIO</small>
            
            <!-- Ver Inventario -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? 'inventario') === 'inventario') ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-box-seam"></i> Ver Inventario
                </a>
            </li>
            
            <!-- Ver Movimientos -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'inventario_epp.php' && ($_GET['vista'] ?? '') === 'movimientos') || $en_movimientos ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=movimientos">
                    <i class="bi bi-arrow-left-right"></i> Movimientos
                    <?php if ($movs_mes > 0): ?>
                    <span class="badge bg-info text-dark ms-2"><?php echo $movs_mes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if ($permisos_epp['puede_crear']): ?>
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ACCIONES</small>
            
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
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ALERTAS</small>
            
            <li class="nav-item">
                <a class="nav-link text-warning" 
                   href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=inventario">
                    <i class="bi bi-exclamation-triangle"></i> Sin Stock
                    <span class="badge bg-danger ms-2"><?php echo $epp_sin_stock; ?></span>
                </a>
            </li>
            <?php endif; ?>
            
            <hr class="text-white-50 my-3">
            <li class="nav-item">
                <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesi&oacute;n
                </a>
            </li>
        </ul>
    </nav>
</aside>