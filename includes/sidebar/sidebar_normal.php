<?php
/**
 * Sidebar para usuarios normales (no colaborativos, no TI)
 * includes/sidebar/sidebar_normal.php
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Determinar si está en sección de órdenes de servicio
$en_mis_ordenes = in_array($current_page, [
    'mis_ordenes_servicio.php',
    'mis_ordenes_servicio_finalizadas.php',
    'ver_orden_servicio.php'
]);

// ⭐ IMPORTANTE: Incluir database.php ANTES de usar conectarDB()
require_once __DIR__ . '/../../config/database.php';

// Obtener contador de órdenes pendientes de validación (solo del usuario actual)
$pdo = conectarDB();
$stmt_pendientes = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM ordenes_servicio_mantenimiento 
    WHERE usuario_id = :usuario_id 
    AND estado = 'pendiente_usuario'
");
$stmt_pendientes->execute([':usuario_id' => $_SESSION['usuario_id']]);
$ordenes_pendientes_validar = $stmt_pendientes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Contador de mantenimientos pendientes de firma del usuario (nuevo flujo)
try {
    $stmt_mant = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM solicitudes_mantenimiento_ti 
        WHERE usuario_id = :usuario_id 
        AND estado = 'finalizada'
    ");
    $stmt_mant->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $mant_pendientes_firma = $stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $mant_pendientes_firma = 0;
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
        <i class="bi bi-building text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'departamento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/departamento.php">
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
                <a class="nav-link <?php echo in_array($current_page, ['mantenimientos.php', 'ver_mantenimiento.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php">
                    <i class="bi bi-wrench"></i> Mis Mantenimientos
                    <?php if ($mant_pendientes_firma > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $mant_pendientes_firma; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'buscar.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                    <i class="bi bi-search"></i> Buscar
                </a>
            </li>
            
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
                    <i class="bi bi-person-check"></i> Panel Admin Área
                </a>
            </li>
            <?php endif; ?>

            <hr class="text-white-50 my-3">
            <li class="nav-item">
                <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Modal para crear nueva orden de servicio -->
<?php include __DIR__ . '/../ordenes_servicio/modal_crear_orden_servicio.php'; ?>