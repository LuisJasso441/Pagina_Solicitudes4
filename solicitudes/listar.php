<?php
/**
 * Listar solicitudes del usuario
 * Muestra solo las solicitudes del usuario logueado
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];

// Parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['buscar']) ? limpiar_dato($_GET['buscar']) : '';

// Construir consulta
try {
    $pdo = conectarDB();
    
    $sql = "SELECT * FROM solicitudes_atencion WHERE usuario_id = ?";
    $params = [$usuario_id];
    
    // Aplicar filtro de estado
    if (!empty($filtro_estado)) {
        $sql .= " AND estado = ?";
        $params[] = $filtro_estado;
    }
    
    // Aplicar búsqueda por folio o descripción
    if (!empty($busqueda)) {
        $sql .= " AND (folio LIKE ? OR descripcion LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    // Contar por estado
    $stmt_count = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total
        FROM solicitudes_atencion 
        WHERE usuario_id = ?
    ");
    $stmt_count->execute([$usuario_id]);
    $contadores = $stmt_count->fetch();
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar solicitudes: ' . $e->getMessage());
    $solicitudes = [];
    $contadores = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0];
}

/**
 * Obtener clase de badge según estado
 */
function obtener_badge_estado($estado) {
    $badges = [
        'pendiente' => 'bg-warning text-dark',
        'en_proceso' => 'bg-info text-dark',
        'finalizada' => 'bg-success',
        'cancelada' => 'bg-secondary'
    ];
    return $badges[$estado] ?? 'bg-secondary';
}

/**
 * Obtener texto del estado
 */
function obtener_texto_estado_custom($estado) {
    $textos = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada'
    ];
    return $textos[$estado] ?? $estado;
}

/**
 * Obtener clase de badge según prioridad
 */
function obtener_badge_prioridad($prioridad) {
    $badges = [
        'critica' => 'bg-danger',
        'alta' => 'bg-warning text-dark',
        'media' => 'bg-info text-dark',
        'baja' => 'bg-secondary'
    ];
    return $badges[$prioridad] ?? 'bg-secondary';
}

/**
 * Obtener texto de prioridad
 */
function obtener_texto_prioridad($prioridad) {
    $textos = [
        'critica' => 'Crítica',
        'alta' => 'Alta',
        'media' => 'Media',
        'baja' => 'Baja'
    ];
    return $textos[$prioridad] ?? ucfirst($prioridad);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes - <?php echo htmlspecialchars($departamento); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-people-fill text-white fs-1 mb-2"></i>
                <h4><?php echo htmlspecialchars($departamento); ?></h4>
                <small class="text-white-50"><?php echo htmlspecialchars($nombre_usuario); ?></small>
                <span class="badge bg-info mt-2">Colaborativo</span>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>dashboard/colaborativo.php">
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
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/crear_mantenimiento.php">
                            <i class="bi bi-tools"></i> Solicitar Mantenimiento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo URL_BASE; ?>solicitudes/listar.php">
                            <i class="bi bi-list-ul"></i> Mis Solicitudes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/listar_mantenimientos.php">
                            <i class="bi bi-wrench"></i> Mis Mantenimientos
                        </a>
                    </li>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">ÁREA COLABORATIVA</small>
                    

                    
                    <hr class="text-white-50 my-3">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-bold" href="<?php echo URL_BASE; ?>auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">Mis Solicitudes de Atención</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($nombre_usuario); ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo URL_BASE; ?>dashboard/colaborativo.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-warning mx-auto mb-2">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['pendientes']; ?></h3>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-info mx-auto mb-2">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['en_proceso']; ?></h3>
                                <small class="text-muted">En Proceso</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-success mx-auto mb-2">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['finalizadas']; ?></h3>
                                <small class="text-muted">Finalizadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box mx-auto mb-2" style="background: #e0e7ff; color: #6366f1;">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['total']; ?></h3>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Búsqueda -->
                <div class="card card-custom mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filtrar por estado</label>
                                <select name="estado" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos los estados</option>
                                    <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="en_proceso" <?php echo $filtro_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                    <option value="finalizada" <?php echo $filtro_estado == 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                    <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="buscar" class="form-control" 
                                       placeholder="Folio o descripción..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Solicitudes -->
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul"></i> Listado de Solicitudes</span>
                        <span class="badge bg-primary"><?php echo count($solicitudes); ?> resultado(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-3">No hay solicitudes para mostrar</p>
                            <a href="#" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                <i class="bi bi-plus-circle"></i> Crear primera solicitud
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $solicitud): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($solicitud['folio']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($solicitud['tipo_soporte']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $desc = htmlspecialchars($solicitud['descripcion']);
                                            echo strlen($desc) > 60 ? substr($desc, 0, 60) . '...' : $desc;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_prioridad($solicitud['prioridad']); ?>">
                                                <?php echo obtener_texto_prioridad($solicitud['prioridad']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_estado($solicitud['estado']); ?>">
                                                <?php echo obtener_texto_estado_custom($solicitud['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatear_fecha($solicitud['fecha_creacion'], true); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($solicitud['folio']); ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/modal_crear.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        bodyElement.setAttribute('data-theme', currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = bodyElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => {
                themeToggle.classList.remove('rotating');
            }, 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>

</body>
</html>