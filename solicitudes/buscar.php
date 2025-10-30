<?php
/**
 * Búsqueda avanzada de solicitudes
 * Permite buscar por múltiples criterios
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$es_ti = es_usuario_ti();

// Parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? limpiar_dato($_GET['buscar']) : '';
$estado = isset($_GET['estado']) ? limpiar_dato($_GET['estado']) : '';
$prioridad = isset($_GET['prioridad']) ? limpiar_dato($_GET['prioridad']) : '';
$tipo_soporte = isset($_GET['tipo_soporte']) ? limpiar_dato($_GET['tipo_soporte']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? limpiar_dato($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? limpiar_dato($_GET['fecha_hasta']) : '';

$solicitudes = [];
$mostrar_resultados = false;

// Realizar búsqueda si hay parámetros
if (!empty($buscar) || !empty($estado) || !empty($prioridad) || !empty($tipo_soporte) || !empty($fecha_desde) || !empty($fecha_hasta)) {
    $mostrar_resultados = true;
    
    try {
        $pdo = conectarDB();
        
        $sql = "SELECT s.*, u.nombre_completo as solicitante_nombre 
                FROM solicitudes_atencion s
                INNER JOIN usuarios u ON s.usuario_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Si no es TI, solo puede ver sus solicitudes
        if (!$es_ti) {
            $sql .= " AND s.usuario_id = ?";
            $params[] = $usuario_id;
        }
        
        // Búsqueda por texto
        if (!empty($buscar)) {
            $sql .= " AND (s.folio LIKE ? OR s.descripcion LIKE ? OR s.tipo_apoyo LIKE ? OR s.tipo_problema LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
        }
        
        // Filtro por estado
        if (!empty($estado)) {
            $sql .= " AND s.estado = ?";
            $params[] = $estado;
        }
        
        // Filtro por prioridad
        if (!empty($prioridad)) {
            $sql .= " AND s.prioridad = ?";
            $params[] = $prioridad;
        }
        
        // Filtro por tipo de soporte
        if (!empty($tipo_soporte)) {
            $sql .= " AND s.tipo_soporte = ?";
            $params[] = $tipo_soporte;
        }
        
        // Filtro por fecha desde
        if (!empty($fecha_desde)) {
            $sql .= " AND DATE(s.fecha_creacion) >= ?";
            $params[] = $fecha_desde;
        }
        
        // Filtro por fecha hasta
        if (!empty($fecha_hasta)) {
            $sql .= " AND DATE(s.fecha_creacion) <= ?";
            $params[] = $fecha_hasta;
        }
        
        $sql .= " ORDER BY s.fecha_creacion DESC LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $solicitudes = $stmt->fetchAll();
        
    } catch (Exception $e) {
        establecer_alerta('error', 'Error en la búsqueda: ' . $e->getMessage());
    }
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Solicitudes</title>
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
                <h4><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></h4>
                <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></small>
                <?php if ($es_ti): ?>
                <span class="badge bg-danger mt-2">TI/Sistemas</span>
                <?php else: ?>
                <span class="badge bg-info mt-2">Colaborativo</span>
                <?php endif; ?>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>dashboard/<?php echo $es_ti ? 'ti_sistemas' : 'colaborativo'; ?>.php">
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
                        <a class="nav-link" href="<?php echo URL_BASE; ?>solicitudes/listar.php">
                            <i class="bi bi-list-ul"></i> Mis Solicitudes
                        </a>
                    </li>
                    
                    <?php if ($es_ti): ?>
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">GESTIÓN TI</small>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_BASE; ?>ti_sistemas/todas_solicitudes.php">
                            <i class="bi bi-folder"></i> Todas las Solicitudes
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <hr class="text-white-50 my-2">
                    <small class="text-white-50 px-3 fw-bold">HERRAMIENTAS</small>
                    
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo URL_BASE; ?>solicitudes/buscar.php">
                            <i class="bi bi-search"></i> Buscar
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

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">
                            <i class="bi bi-search"></i> Búsqueda Avanzada
                        </h2>
                        <p class="text-muted mb-0">
                            Encuentra solicitudes con filtros personalizados
                        </p>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Formulario de Búsqueda -->
                <div class="card card-custom mb-4">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros de Búsqueda
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            
                            <div class="row g-3 mb-3">
                                <!-- Búsqueda por texto -->
                                <div class="col-md-12">
                                    <label class="form-label">Buscar por folio, descripción o tipo</label>
                                    <input type="text" name="buscar" class="form-control" 
                                           placeholder="Ej: SOL-20251008-A1B2C3, imprimir, office..."
                                           value="<?php echo htmlspecialchars($buscar); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Estado -->
                                <div class="col-md-3">
                                    <label class="form-label">Estado</label>
                                    <select name="estado" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="en_proceso" <?php echo $estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                        <option value="finalizada" <?php echo $estado == 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                        <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>

                                <!-- Prioridad -->
                                <div class="col-md-3">
                                    <label class="form-label">Prioridad</label>
                                    <select name="prioridad" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="critica" <?php echo $prioridad == 'critica' ? 'selected' : ''; ?>>Crítica</option>
                                        <option value="alta" <?php echo $prioridad == 'alta' ? 'selected' : ''; ?>>Alta</option>
                                        <option value="media" <?php echo $prioridad == 'media' ? 'selected' : ''; ?>>Media</option>
                                        <option value="baja" <?php echo $prioridad == 'baja' ? 'selected' : ''; ?>>Baja</option>
                                    </select>
                                </div>

                                <!-- Tipo de Soporte -->
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de Soporte</label>
                                    <select name="tipo_soporte" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="Apoyo" <?php echo $tipo_soporte == 'Apoyo' ? 'selected' : ''; ?>>Apoyo</option>
                                        <option value="Problema" <?php echo $tipo_soporte == 'Problema' ? 'selected' : ''; ?>>Problema</option>
                                    </select>
                                </div>

                                <!-- Fecha Desde -->
                                <div class="col-md-1.5">
                                    <label class="form-label">Desde</label>
                                    <input type="date" name="fecha_desde" class="form-control" 
                                           value="<?php echo htmlspecialchars($fecha_desde); ?>">
                                </div>

                                <!-- Fecha Hasta -->
                                <div class="col-md-1.5">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" name="fecha_hasta" class="form-control" 
                                           value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-gradient">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <a href="<?php echo URL_BASE; ?>solicitudes/buscar.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Limpiar
                                </a>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- Resultados -->
                <?php if ($mostrar_resultados): ?>
                <div class="card card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check"></i> Resultados de la Búsqueda</span>
                        <span class="badge bg-primary"><?php echo count($solicitudes); ?> resultado(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-search fs-1 text-muted"></i>
                            <p class="text-muted mt-3">No se encontraron solicitudes con los criterios especificados</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <?php if ($es_ti): ?>
                                        <th>Solicitante</th>
                                        <?php endif; ?>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                        </td>
                                        <?php if ($es_ti): ?>
                                        <td>
                                            <?php echo htmlspecialchars($sol['solicitante_nombre']); ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($sol['tipo_soporte']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $desc = htmlspecialchars($sol['descripcion']);
                                            echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_prioridad($sol['prioridad']); ?>">
                                                <?php echo ucfirst($sol['prioridad']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_estado($sol['estado']); ?>">
                                                <?php echo obtener_texto_estado($sol['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatear_fecha($sol['fecha_creacion']); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
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
                <?php else: ?>
                <div class="card card-custom">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-info-circle fs-1 text-muted"></i>
                        <p class="text-muted mt-3 mb-0">Utiliza los filtros de arriba para buscar solicitudes</p>
                    </div>
                </div>
                <?php endif; ?>

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