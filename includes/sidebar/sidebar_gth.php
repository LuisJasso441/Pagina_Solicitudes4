<?php
/**
 * Sidebar para GTH (Gestion de Talento Humano) y Contabilidad
 * includes/sidebar/sidebar_gth.php
 */

if (!isset($_SESSION['usuario_id'])) {
    if (function_exists('destruir_sesion')) { destruir_sesion(); }
    $url_login = defined('URL_BASE') ? URL_BASE . 'auth/InicioSesion.php' : '/auth/InicioSesion.php';
    header('Location: ' . $url_login);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../../config/database.php';
$pdo = conectarDB();

try {
    $stmt_pendientes = $pdo->prepare("SELECT COUNT(*) as total FROM ordenes_servicio_mantenimiento WHERE usuario_id = :uid AND estado = 'pendiente_usuario'");
    $stmt_pendientes->execute([':uid' => $_SESSION['usuario_id']]);
    $ordenes_pendientes_validar = $stmt_pendientes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $ordenes_pendientes_validar = 0; }

try {
    $stmt_vac = $pdo->query("SELECT COUNT(*) FROM solicitudes_vacaciones WHERE estado = 'pendiente_gth'");
    $vacaciones_pendientes_gth = (int)$stmt_vac->fetchColumn();
} catch (Exception $e) { $vacaciones_pendientes_gth = 0; }

try {
    $stmt_emp = $pdo->query("SELECT COUNT(*) FROM empleados_gth WHERE activo = 1");
    $total_empleados_gth = (int)$stmt_emp->fetchColumn();
} catch (Exception $e) { $total_empleados_gth = 0; }

$en_empleados = in_array($current_page, ['listar_empleados.php', 'crear_empleado.php', 'editar_empleado.php']);
?>

<button class="hamburger-btn" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">
    <span class="hamburger-icon"><span></span><span></span><span></span></span>
</button>
<div class="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-people-fill text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
    </div>
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard_gth.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/gth/dashboard_gth.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SOLICITUDES PARA SISTEMAS</small>
            <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud"><i class="bi bi-plus-circle"></i> Nueva Solicitud</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'listar.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>solicitudes/listar.php"><i class="bi bi-list-ul"></i> Mis Solicitudes</a></li>
            <li class="nav-item"><a class="nav-link <?php echo in_array($current_page, ['mantenimientos.php', 'ver_mantenimiento.php', 'solicitar_mantenimiento.php', 'crear_mantenimiento_historico.php', 'ver_mantenimiento_historico.php', 'editar_mantenimiento_historico.php']) ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php"><i class="bi bi-wrench"></i> Mis Mantenimientos</a></li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'bitacora_aires.php' ? 'active' : ''; ?>"
                   href="<?php echo URL_BASE; ?>dashboard/sistemas/bitacora_aires/bitacora_aires.php">
                    <i class="bi bi-snow"></i> Control Aire Acondicionado
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ORDENES DE SERVICIO</small>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php"><i class="bi bi-clipboard-check"></i> Ordenes de Mantenimiento</a></li>
            <?php if ($ordenes_pendientes_validar > 0): ?>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'mis_ordenes_servicio.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/mis_ordenes_servicio.php"><i class="bi bi-exclamation-circle"></i> Mis Ordenes <span class="badge bg-warning text-dark ms-auto"><?php echo $ordenes_pendientes_validar; ?></span></a></li>
            <?php endif; ?>

            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">SALA DE JUNTAS</small>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'reservaciones_sala.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php"><i class="bi bi-calendar-event"></i> Reservar Sala de Juntas</a></li>

            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">EMPLEADOS</small>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'listar_empleados.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php">
                    <i class="bi bi-person-vcard"></i> Gestion de Empleados
                    <?php if ($total_empleados_gth > 0): ?>
                    <span class="badge bg-secondary ms-auto"><?php echo $total_empleados_gth; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'crear_empleado.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/gth/empleados/crear_empleado.php"><i class="bi bi-person-plus"></i> Nuevo Empleado</a></li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">VACACIONES</small>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'mis_vacaciones.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php"><i class="bi bi-calendar-check"></i> Mis Vacaciones</a></li>
            <?php if (function_exists('es_admin_area') && es_admin_area()): ?>
            <li class="nav-item"><a class="nav-link <?php echo $current_page == 'vacaciones_admin.php' ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php"><i class="bi bi-person-check"></i> Panel Admin Area</a></li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['vacaciones_gth.php', 'ver_solicitud_vacaciones.php']) ? 'active' : ''; ?>" href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_gth.php">
                    <i class="bi bi-clipboard2-check"></i> Panel GTH
                    <?php if ($vacaciones_pendientes_gth > 0): ?>
                    <span class="badge bg-warning text-dark ms-auto"><?php echo $vacaciones_pendientes_gth; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <hr class="text-white-50 my-3">
            <li class="nav-item"><a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesion</a></li>
        </ul>
    </nav>
</aside>