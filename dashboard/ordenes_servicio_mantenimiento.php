<?php
/**
 * Dashboard de Mantenimiento - Órdenes de Servicio
 * Con tabs: Base Local (Activas) / Base Global (Finalizadas)
 * Similar al sistema SSC Colaborativo
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Determinar el departamento actual
$departamento_codigo = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$es_mantenimiento = ($departamento_codigo === 'mantenimiento');
$es_ti = in_array($departamento_codigo, ['ti', 'sistemas', 'ti_sistemas']);

$pdo = conectarDB();

// Determinar qué base mostrar (local = activas, global = finalizadas)
$filtro_base = $_GET['base'] ?? 'local';

// Filtros
$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'empleado' => $_GET['empleado'] ?? '',  // NUEVO: Filtro por empleado
    'empresa' => $_GET['empresa'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

// Construir consulta según la base
if ($filtro_base === 'local') {
    // Base Local: Órdenes Activas
    $sql = "SELECT 
                id, folio, estado, usuario_nombre, departamento, empresa,
                fecha_creacion, fecha_primera_edicion_mant, apartado1_data,
                DATEDIFF(CURRENT_DATE, DATE(fecha_creacion)) as dias_desde_creacion
            FROM ordenes_servicio_mantenimiento 
            WHERE estado IN ('pendiente_mantenimiento', 'en_proceso', 'pendiente_usuario', 'devuelto')";
} else {
    // Base Global: Órdenes Finalizadas
    $sql = "SELECT 
                id, folio, usuario_nombre, departamento, empresa,
                fecha_creacion, fecha_completado, apartado1_data
            FROM ordenes_servicio_mantenimiento 
            WHERE estado = 'completado'";
}

$params = [];

// ⭐ FILTRO AUTOMÁTICO: Si NO es Mantenimiento, solo mostrar órdenes de su departamento
if (!$es_mantenimiento) {
    $sql .= " AND departamento = :dept_usuario";
    $params[':dept_usuario'] = $_SESSION['departamento_nombre'];
}

// Aplicar filtros
if (!empty($filtros['estado']) && $filtro_base === 'local') {
    $sql .= " AND estado = :estado";
    $params[':estado'] = $filtros['estado'];
}

// NUEVO: Filtro por empleado (solo para usuarios no-Mantenimiento)
if (!empty($filtros['empleado'])) {
    $sql .= " AND usuario_nombre LIKE :empleado";
    $params[':empleado'] = '%' . $filtros['empleado'] . '%';
}

if (!empty($filtros['empresa'])) {
    $sql .= " AND empresa = :empresa";
    $params[':empresa'] = $filtros['empresa'];
}

if (!empty($filtros['fecha_desde'])) {
    $campo_fecha = $filtro_base === 'local' ? 'fecha_creacion' : 'fecha_completado';
    $sql .= " AND DATE($campo_fecha) >= :fecha_desde";
    $params[':fecha_desde'] = $filtros['fecha_desde'];
}

if (!empty($filtros['fecha_hasta'])) {
    $campo_fecha = $filtro_base === 'local' ? 'fecha_creacion' : 'fecha_completado';
    $sql .= " AND DATE($campo_fecha) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtros['fecha_hasta'];
}

if (!empty($filtros['busqueda'])) {
    $sql .= " AND (folio LIKE :busqueda OR usuario_nombre LIKE :busqueda OR departamento LIKE :busqueda)";
    $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
}

// Ordenamiento
if ($filtro_base === 'local') {
    $sql .= " ORDER BY 
        CASE estado 
            WHEN 'devuelto' THEN 1
            WHEN 'pendiente_mantenimiento' THEN 2
            WHEN 'en_proceso' THEN 3
            WHEN 'pendiente_usuario' THEN 4
        END,
        fecha_creacion DESC";
} else {
    $sql .= " ORDER BY fecha_completado DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas según la base
if ($filtro_base === 'local') {
    // ⭐ Si NO es Mantenimiento, filtrar por su departamento
    if (!$es_mantenimiento) {
        $stmt_stats = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente_mantenimiento' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN estado = 'pendiente_usuario' THEN 1 ELSE 0 END) as pendiente_usuario,
                SUM(CASE WHEN estado = 'devuelto' THEN 1 ELSE 0 END) as devueltas
            FROM ordenes_servicio_mantenimiento
            WHERE estado IN ('pendiente_mantenimiento', 'en_proceso', 'pendiente_usuario', 'devuelto')
            AND departamento = :dept_usuario
        ");
        $stmt_stats->execute([':dept_usuario' => $_SESSION['departamento_nombre']]);
    } else {
        $stmt_stats = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente_mantenimiento' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN estado = 'pendiente_usuario' THEN 1 ELSE 0 END) as pendiente_usuario,
                SUM(CASE WHEN estado = 'devuelto' THEN 1 ELSE 0 END) as devueltas
            FROM ordenes_servicio_mantenimiento
            WHERE estado IN ('pendiente_mantenimiento', 'en_proceso', 'pendiente_usuario', 'devuelto')
        ");
    }
} else {
    // ⭐ Si NO es Mantenimiento, filtrar por su departamento
    if (!$es_mantenimiento) {
        $stmt_stats = $pdo->prepare("
            SELECT 
                COUNT(*) as total_global,
                COUNT(DISTINCT DATE(fecha_completado)) as dias_con_finalizaciones
            FROM ordenes_servicio_mantenimiento
            WHERE estado = 'completado'
            AND departamento = :dept_usuario
        ");
        $stmt_stats->execute([':dept_usuario' => $_SESSION['departamento_nombre']]);
    } else {
        $stmt_stats = $pdo->query("
            SELECT 
                COUNT(*) as total_global,
                COUNT(DISTINCT departamento) as departamentos_totales,
                COUNT(DISTINCT DATE(fecha_completado)) as dias_con_finalizaciones
            FROM ordenes_servicio_mantenimiento
            WHERE estado = 'completado'
        ");
    }
}
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener lista de empleados del departamento (para filtro)
if (!$es_mantenimiento) {
    // Solo empleados de su departamento
    $stmt_empleados = $pdo->prepare("
        SELECT DISTINCT usuario_nombre 
        FROM ordenes_servicio_mantenimiento 
        WHERE departamento = :dept_usuario
        ORDER BY usuario_nombre
    ");
    $stmt_empleados->execute([':dept_usuario' => $_SESSION['departamento_nombre']]);
    $empleados = $stmt_empleados->fetchAll(PDO::FETCH_COLUMN);
} else {
    $empleados = [];
}

// Obtener lista de empresas
$stmt_empresas = $pdo->query("SELECT DISTINCT empresa FROM ordenes_servicio_mantenimiento WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa");
$empresas = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes de Servicio | <?php echo NOMBRE_SISTEMA; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <style>
        /* Layout del dashboard */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            background: #f5f6fa;
        }
        .content-wrapper {
            padding: 1.5rem;
        }
        
        /* Diseño compacto */
        body { 
            font-size: 0.85rem; 
            background: #f5f6fa;
            font-family: 'Poppins', sans-serif;
        }
        
        /* Cards compactas */
        .card { 
            box-shadow: 0 1px 2px rgba(0,0,0,0.08); 
            border: 1px solid #e3e6f0;
            margin-bottom: 0.75rem;
        }
        .card-header { 
            padding: 0.4rem 0.75rem; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
        }
        .card-body { 
            padding: 0.75rem; 
        }
        
        /* TABS como SSC */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 1rem;
        }
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s;
        }
        .nav-tabs .nav-link:hover {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            background: transparent;
            border-bottom-color: #667eea;
            font-weight: 600;
        }
        
        /* Estadísticas compactas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            border-left: 3px solid #667eea;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card.pendientes { border-left-color: #ffc107; }
        .stat-card.proceso { border-left-color: #17a2b8; }
        .stat-card.validar { border-left-color: #007bff; }
        .stat-card.devueltas { border-left-color: #dc3545; }
        .stat-card.completadas { border-left-color: #2ecc71; }
        
        .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.15rem;
            color: #667eea;
        }
        .stat-card.pendientes .stat-number { color: #ffc107; }
        .stat-card.proceso .stat-number { color: #17a2b8; }
        .stat-card.validar .stat-number { color: #007bff; }
        .stat-card.devueltas .stat-number { color: #dc3545; }
        .stat-card.completadas .stat-number { color: #2ecc71; }
        
        .stat-label {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.3;
            color: inherit;
        }
        
        /* Formularios compactos */
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #495057;
        }
        .form-select-sm, .form-control-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Tabla compacta */
        .table {
            font-size: 0.8rem;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            padding: 0.5rem 0.75rem;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            padding: 0.5rem 0.75rem;
            vertical-align: middle;
        }
        .table tbody tr {
            cursor: pointer;
            transition: all 0.2s;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }
        
        /* Badges compactos */
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        .badge-estado {
            font-weight: 600;
        }
        .dias-badge {
            font-weight: 700;
        }
        
        /* Botones compactos */
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Estilos de prioridad */
        .prioridad-alta {
            background: rgba(220, 53, 69, 0.05) !important;
        }
        .prioridad-media {
            background: rgba(255, 193, 7, 0.05) !important;
        }
        .prioridad-baja {
            background: rgba(23, 162, 184, 0.05) !important;
        }
        
        /* Header */
        .page-header {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
        // Cargar sidebar según departamento
        if ($es_mantenimiento) {
            include __DIR__ . '/../includes/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../includes/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../includes/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../includes/sidebar_normal.php';
        }
        ?>
        
        <!-- Contenido principal -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado de página -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($es_mantenimiento): ?>
                                <h1><i class="bi bi-tools"></i> Órdenes de Servicio - Mantenimiento</h1>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                    Gestión de órdenes de servicio y mantenimiento de todos los departamentos
                                </p>
                            <?php else: ?>
                                <h1><i class="bi bi-clipboard-check"></i> Mis Órdenes de Servicio</h1>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                    Órdenes de servicio del departamento: <strong><?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$es_mantenimiento): ?>
                        <!-- Botón para crear nueva orden (solo para usuarios NO-Mantenimiento) -->
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearOrdenServicio">
                                <i class="bi bi-plus-circle"></i> Nueva Orden de Servicio
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- TABS: Base Local / Base Global (como SSC) -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $filtro_base == 'local' ? 'active' : ''; ?>" 
                           href="?base=local">
                            <i class="bi bi-folder"></i> Base Local (Activas)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $filtro_base == 'global' ? 'active' : ''; ?>" 
                           href="?base=global">
                            <i class="bi bi-globe"></i> Base Global (Finalizadas)
                        </a>
                    </li>
                </ul>
                
                <!-- Estadísticas según la base -->
                <?php if ($filtro_base === 'local'): ?>
                    <!-- Estadísticas Base Local (Activas) -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                                    <div class="stat-label">Total Activas</div>
                                </div>
                                <i class="bi bi-card-list stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card pendientes">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['pendientes']; ?></div>
                                    <div class="stat-label">Pendientes</div>
                                </div>
                                <i class="bi bi-hourglass-split stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card proceso">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['en_proceso']; ?></div>
                                    <div class="stat-label">En Proceso</div>
                                </div>
                                <i class="bi bi-gear-fill stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card validar">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['pendiente_usuario']; ?></div>
                                    <div class="stat-label">A Validar</div>
                                </div>
                                <i class="bi bi-person-check stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card devueltas">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['devueltas']; ?></div>
                                    <div class="stat-label">Devueltas</div>
                                </div>
                                <i class="bi bi-arrow-return-left stat-icon"></i>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Estadísticas Base Global (Finalizadas) -->
                    <div class="stats-grid">
                        <div class="stat-card completadas">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['total_global']; ?></div>
                                    <div class="stat-label">Completadas</div>
                                </div>
                                <i class="bi bi-check-circle stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo $stats['departamentos_totales']; ?></div>
                                    <div class="stat-label">Departamentos</div>
                                </div>
                                <i class="bi bi-building stat-icon"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-number"><?php echo count($ordenes); ?></div>
                                    <div class="stat-label">Filtradas</div>
                                </div>
                                <i class="bi bi-funnel stat-icon"></i>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros de Búsqueda
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="base" value="<?php echo htmlspecialchars($filtro_base); ?>">
                            
                            <?php if ($es_mantenimiento): ?>
                                <!-- FILTROS PARA MANTENIMIENTO -->
                                <?php if ($filtro_base === 'local'): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Estado</label>
                                    <select name="estado" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        <option value="pendiente_mantenimiento" <?php echo $filtros['estado'] == 'pendiente_mantenimiento' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="en_proceso" <?php echo $filtros['estado'] == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                        <option value="pendiente_usuario" <?php echo $filtros['estado'] == 'pendiente_usuario' ? 'selected' : ''; ?>>Validar</option>
                                        <option value="devuelto" <?php echo $filtros['estado'] == 'devuelto' ? 'selected' : ''; ?>>Devuelta</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-<?php echo $filtro_base === 'local' ? '2' : '3'; ?>">
                                    <label class="form-label">Empresa</label>
                                    <select name="empresa" class="form-select form-select-sm">
                                        <option value="">Todas</option>
                                        <?php foreach ($empresas as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp); ?>" 
                                                    <?php echo $filtros['empresa'] == $emp ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Desde</label>
                                    <input type="date" name="fecha_desde" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                </div>
                                
                            <?php else: ?>
                                <!-- FILTROS PARA OTROS DEPARTAMENTOS -->
                                <div class="col-md-3">
                                    <label class="form-label">Empleado</label>
                                    <input type="text" name="empleado" class="form-control form-control-sm" 
                                           placeholder="Nombre del empleado" 
                                           value="<?php echo htmlspecialchars($filtros['empleado']); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Empresa</label>
                                    <select name="empresa" class="form-select form-select-sm">
                                        <option value="">Todas</option>
                                        <?php foreach ($empresas as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp); ?>" 
                                                    <?php echo $filtros['empresa'] == $emp ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Fecha Desde</label>
                                    <input type="date" name="fecha_desde" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Fecha Hasta</label>
                                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <a href="?base=<?php echo $filtro_base; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabla -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-table"></i> Listado de Órdenes
                        <span class="badge bg-light text-dark ms-2"><?php echo count($ordenes); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($ordenes)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-2 text-muted"></i>
                                <p class="text-muted mt-2 mb-0">No hay órdenes <?php echo $filtro_base === 'local' ? 'activas' : 'finalizadas'; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 90px;">Folio</th>
                                            <th>Solicitante</th>
                                            <th>Departamento</th>
                                            <th style="width: 90px;">Empresa</th>
                                            <th>Unidad/Equipo</th>
                                            <?php if ($filtro_base === 'local'): ?>
                                            <th style="width: 80px;">Prioridad</th>
                                            <th style="width: 110px;">Estado</th>
                                            <th style="width: 60px;">Días</th>
                                            <?php else: ?>
                                            <th style="width: 90px;">Creada</th>
                                            <th style="width: 90px;">Completada</th>
                                            <th style="width: 70px;">Duración</th>
                                            <?php endif; ?>
                                            <th style="width: 70px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ordenes as $orden): 
                                            $apartado1 = json_decode($orden['apartado1_data'], true);
                                            
                                            if ($filtro_base === 'local') {
                                                $prioridad = $apartado1['prioridad'] ?? '-';
                                                
                                                // Clase de prioridad
                                                $prioridad_class = '';
                                                if (stripos($prioridad, 'Alta') !== false) $prioridad_class = 'prioridad-alta';
                                                elseif (stripos($prioridad, 'Media') !== false) $prioridad_class = 'prioridad-media';
                                                elseif (stripos($prioridad, 'Baja') !== false) $prioridad_class = 'prioridad-baja';
                                            } else {
                                                // Calcular duración
                                                $fecha_inicio = new DateTime($orden['fecha_creacion']);
                                                $fecha_fin = new DateTime($orden['fecha_completado']);
                                                $dias_duracion = $fecha_inicio->diff($fecha_fin)->days;
                                            }
                                        ?>
                                            <tr onclick="window.location='ver_orden_servicio.php?id=<?php echo $orden['id']; ?>'" 
                                                class="<?php echo isset($prioridad_class) ? $prioridad_class : ''; ?>">
                                                <td>
                                                    <strong class="<?php echo $filtro_base === 'local' ? 'text-primary' : 'text-success'; ?>">
                                                        <?php echo htmlspecialchars($orden['folio']); ?>
                                                    </strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($orden['usuario_nombre']); ?></td>
                                                <td><small><?php echo htmlspecialchars($orden['departamento']); ?></small></td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($orden['empresa']); ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($apartado1['unidad_equipo'] ?? '-'); ?></small></td>
                                                
                                                <?php if ($filtro_base === 'local'): ?>
                                                <td>
                                                    <?php 
                                                    $badge_class = 'secondary';
                                                    if (stripos($prioridad, 'Alta') !== false) $badge_class = 'danger';
                                                    elseif (stripos($prioridad, 'Media') !== false) $badge_class = 'warning';
                                                    elseif (stripos($prioridad, 'Baja') !== false) $badge_class = 'info';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($prioridad); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $estados = [
                                                        'pendiente_mantenimiento' => ['class' => 'warning', 'icon' => 'hourglass-split', 'text' => 'Pendiente'],
                                                        'en_proceso' => ['class' => 'info', 'icon' => 'gear-fill', 'text' => 'Proceso'],
                                                        'pendiente_usuario' => ['class' => 'primary', 'icon' => 'person-check', 'text' => 'Validar'],
                                                        'devuelto' => ['class' => 'danger', 'icon' => 'arrow-return-left', 'text' => 'Devuelta']
                                                    ];
                                                    $badge = $estados[$orden['estado']];
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge['class']; ?> badge-estado">
                                                        <i class="bi bi-<?php echo $badge['icon']; ?>"></i>
                                                        <?php echo $badge['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary dias-badge">
                                                        <?php echo $orden['dias_desde_creacion']; ?>
                                                    </span>
                                                </td>
                                                <?php else: ?>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-success">
                                                        <i class="bi bi-check-circle"></i>
                                                        <?php echo date('d/m/Y', strtotime($orden['fecha_completado'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">
                                                        <?php echo $dias_duracion; ?> día(s)
                                                    </span>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <td>
                                                    <a href="ver_orden_servicio.php?id=<?php echo $orden['id']; ?>" 
                                                       class="btn btn-sm btn-outline-<?php echo $filtro_base === 'local' ? 'primary' : 'success'; ?>" 
                                                       onclick="event.stopPropagation();">
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
                
            </div><!-- fin content-wrapper -->
        </main><!-- fin main-content -->
        
    </div><!-- fin dashboard-container -->
    
    <!-- Modal para crear nueva orden (solo para usuarios NO-Mantenimiento) -->
    <?php if (!$es_mantenimiento): ?>
        <?php include __DIR__ . '/../includes/modal_crear_orden_servicio.php'; ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-recargar cada 2 minutos (solo en base local)
        <?php if ($filtro_base === 'local'): ?>
        setTimeout(function() {
            location.reload();
        }, 120000);
        <?php endif; ?>
    </script>
</body>
</html>