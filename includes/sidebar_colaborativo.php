<?php
/**
 * Sidebar para usuarios colaborativos
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-people-fill text-white fs-1 mb-2"></i>
        <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></h4>
        <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
        <span class="badge bg-info mt-2">Colaborativo</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'colaborativo.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/colaborativo.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">MIS SOLICITUDES</small>
            
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                    <i class="bi bi-plus-circle"></i> Nueva Solicitud
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'crear_mantenimiento.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>solicitudes/crear_mantenimiento.php">
                    <i class="bi bi-tools"></i> Solicitar Mantenimiento
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
                   href="<?php echo URL_BASE; ?>solicitudes/listar_mantenimientos.php">
                    <i class="bi bi-wrench"></i> Mis Mantenimientos
                </a>
            </li>
            
            <hr class="text-white-50 my-2">
            <small class="text-white-50 px-3 fw-bold">ÁREA COLABORATIVA</small>
            
            <!-- NUEVO: Documentos Colaborativos SSC -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'documentos_colaborativos.php' ? 'active' : ''; ?>" 
                   href="<?php echo URL_BASE; ?>dashboard/documentos_colaborativos.php">
                    <i class="bi bi-file-earmark-text"></i> Documentos SSC
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