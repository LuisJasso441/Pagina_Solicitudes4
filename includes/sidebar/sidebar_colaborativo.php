<?php
/**
 * Sidebar para usuarios colaborativos
 * Actualizado: Enero 2026
 * Incluye módulo de Cotizaciones Químicos/Residuos (solo Normatividad y Ventas)
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

// ⭐ IMPORTANTE: Incluir database.php ANTES de usar conectarDB()
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

// ⭐ Verificar si el usuario tiene acceso al módulo CQR (Cotizaciones Químicos/Residuos)
$tiene_acceso_cqr = false;
$contador_cqr_pendientes = 0;

try {
    // Verificar permisos CQR del usuario
    $stmt_cqr = $pdo->prepare("
        SELECT lector, creador, editor 
        FROM permisos_cqr 
        WHERE user_id = :usuario_id
    ");
    $stmt_cqr->execute([':usuario_id' => $_SESSION['usuario_id']]);
    $permisos_cqr = $stmt_cqr->fetch(PDO::FETCH_ASSOC);
    
    if ($permisos_cqr && ($permisos_cqr['lector'] == 1 || $permisos_cqr['creador'] == 1 || $permisos_cqr['editor'] == 1)) {
        $tiene_acceso_cqr = true;
        
        // Obtener departamento del usuario para determinar qué contador mostrar
        $depto_usuario = strtolower($_SESSION['departamento'] ?? '');
        
        if (strpos($depto_usuario, 'ventas') !== false) {
            // Para Ventas: contar sus cotizaciones pendientes de respuesta
            $stmt_contador = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM cotizaciones_quimicos_residuos 
                WHERE usuario_creador_id = :usuario_id 
                AND estado IN ('enviada', 'en_revision')
                AND ubicacion = 'local'
            ");
            $stmt_contador->execute([':usuario_id' => $_SESSION['usuario_id']]);
        } else if (strpos($depto_usuario, 'normatividad') !== false) {
            // Para Normatividad: contar cotizaciones pendientes de revisar
            $stmt_contador = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM cotizaciones_quimicos_residuos 
                WHERE estado = 'enviada'
                AND ubicacion = 'local'
            ");
            $stmt_contador->execute();
        }
        
        if (isset($stmt_contador)) {
            $contador_cqr_pendientes = $stmt_contador->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    $tiene_acceso_cqr = false;
    $contador_cqr_pendientes = 0;
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
        <i class="bi bi-people-fill text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?></h4>
        <small><?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? ''); ?></small>
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
            <small class="text-white-50 px-3 fw-bold">SOLICITUDES DE SERVICIO</small>
            
            <!-- Documentos Colaborativos SSC -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'documentos_colaborativos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php">
                    <i class="bi bi-file-earmark-text"></i> Solicitudes de Servicio a Clientes
                </a>
            </li>
            
            <?php if ($tiene_acceso_cqr): ?>
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- MÓDULO CQR - Solo visible para Normatividad y Ventas -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">COTIZACIONES QUÍMICOS</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['cotizaciones_qr.php', 'ver_cotizacion_qr.php', 'nueva_cotizacion_qr.php']) ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php">
                    <i class="bi bi-card-checklist"></i> Cotizaciones QR
                    <?php if ($contador_cqr_pendientes > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?php echo $contador_cqr_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <!-- ═══════════════════════════════════════════════════════════════ -->
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ÓRDENES DE SERVICIO</small>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'ordenes_servicio_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php">
                    <i class="bi bi-clipboard-check"></i> Órdenes de Mantenimiento
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