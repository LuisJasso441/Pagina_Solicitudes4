<?php
/**
 * Dashboard de Mantenimiento - Órdenes de Servicio
 * Con tabs: Base Local (Activas) / Base Global (Finalizadas)
 * Similar al sistema SSC Colaborativo
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/notificaciones.php';

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
    'empleado' => $_GET['empleado'] ?? '',
    'empresa' => $_GET['empresa'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

// Construir consulta según la base
if ($filtro_base === 'local') {
    $sql = "SELECT 
                id, folio, estado, usuario_nombre, departamento, empresa,
                fecha_creacion, fecha_primera_edicion_mant, apartado1_data,
                DATEDIFF(CURRENT_DATE, DATE(fecha_creacion)) as dias_desde_creacion
            FROM ordenes_servicio_mantenimiento 
            WHERE estado IN ('pendiente_mantenimiento', 'en_proceso', 'pendiente_usuario', 'devuelto')";
} else {
    $sql = "SELECT 
                id, folio, usuario_nombre, departamento, empresa,
                fecha_creacion, fecha_completado, apartado1_data
            FROM ordenes_servicio_mantenimiento 
            WHERE estado = 'completado'";
}

$params = [];

// Filtro automático por departamento si NO es Mantenimiento
if (!$es_mantenimiento) {
    $sql .= " AND departamento = :dept_usuario";
    $params[':dept_usuario'] = $_SESSION['departamento_nombre'];
}

// Aplicar filtros
if (!empty($filtros['estado']) && $filtro_base === 'local') {
    $sql .= " AND estado = :estado";
    $params[':estado'] = $filtros['estado'];
}

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
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt_stats = $pdo->query("
        SELECT COUNT(*) as total
        FROM ordenes_servicio_mantenimiento
        WHERE estado = 'completado'
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    $stats['pendientes'] = 0;
    $stats['en_proceso'] = 0;
    $stats['pendiente_usuario'] = 0;
    $stats['devueltas'] = 0;
}

// Obtener lista de empresas para filtro
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
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
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
        
        /* TABS */
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
        .stat-card.pendientes { border-left-color: #f39c12; }
        .stat-card.proceso { border-left-color: #3498db; }
        .stat-card.validar { border-left-color: #9b59b6; }
        .stat-card.devueltas { border-left-color: #e74c3c; }
        .stat-card.completadas { border-left-color: #27ae60; }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.2;
        }
        
        /* Tabla más compacta */
        table { font-size: 0.75rem; }
        table th { 
            padding: 0.4rem 0.5rem; 
            background: #f8f9fc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        table td { 
            padding: 0.35rem 0.5rem;
            vertical-align: middle;
        }
        
        /* Badges */
        .badge {
            font-size: 0.65rem;
            padding: 0.35em 0.65em;
            font-weight: 600;
        }
        
        /* Botones compactos */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
        }
        
        /* Estado visual */
        .orden-card {
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .orden-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left-color: #667eea;
        }
        .orden-card.pendiente { border-left-color: #f39c12; }
        .orden-card.proceso { border-left-color: #3498db; }
        .orden-card.validar { border-left-color: #9b59b6; }
        .orden-card.devuelta { border-left-color: #e74c3c; }
        .orden-card.completada { border-left-color: #27ae60; }
        
        /* Page header */
        .page-header {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
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
        if ($es_mantenimiento) {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
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
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearOrdenServicio">
                                <i class="bi bi-plus-circle"></i> Nueva Orden de Servicio
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- TABS -->
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
                
                <!-- Estadísticas -->
                <?php if ($filtro_base === 'local'): ?>
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
                            <i class="bi bi-gear stat-icon"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card validar">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $stats['pendiente_usuario']; ?></div>
                                <div class="stat-label">Por Validar</div>
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
                <div class="stats-grid">
                    <div class="stat-card completadas">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Finalizadas</div>
                            </div>
                            <i class="bi bi-check-circle stat-icon"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="base" value="<?php echo $filtro_base; ?>">
                            
                            <?php if ($filtro_base === 'local'): ?>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="pendiente_mantenimiento" <?php echo $filtros['estado'] == 'pendiente_mantenimiento' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="en_proceso" <?php echo $filtros['estado'] == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                    <option value="pendiente_usuario" <?php echo $filtros['estado'] == 'pendiente_usuario' ? 'selected' : ''; ?>>A Validar</option>
                                    <option value="devuelto" <?php echo $filtros['estado'] == 'devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$es_mantenimiento && !empty($empleados)): ?>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Empleado</label>
                                <select name="empleado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($empleados as $empleado): ?>
                                        <option value="<?php echo htmlspecialchars($empleado); ?>" 
                                                <?php echo $filtros['empleado'] == $empleado ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($empleado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Empresa</label>
                                <select name="empresa" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?php echo htmlspecialchars($empresa); ?>" 
                                                <?php echo $filtros['empresa'] == $empresa ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($empresa); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Desde</label>
                                <input type="date" name="fecha_desde" class="form-control form-control-sm" 
                                       value="<?php echo $filtros['fecha_desde']; ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control form-control-sm" 
                                       value="<?php echo $filtros['fecha_hasta']; ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label" style="font-size: 0.7rem;">Búsqueda</label>
                                <input type="text" name="busqueda" class="form-control form-control-sm" 
                                       placeholder="Folio, usuario..." 
                                       value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                            
                            <?php if ($es_mantenimiento): ?>
                            <!-- Botón Descargar Registros (EXCLUSIVO Mantenimiento) -->
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success btn-sm w-100" onclick="descargarExcel()">
                                    <i class="bi bi-file-earmark-excel"></i> Descargar registros
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Tabla de órdenes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-table"></i> 
                            <?php echo $filtro_base === 'local' ? 'Órdenes Activas' : 'Órdenes Finalizadas'; ?>
                        </span>
                        <span class="badge bg-light text-dark">
                            <?php echo count($ordenes); ?> registros
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($ordenes)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                <p class="text-muted mt-3">No hay órdenes que mostrar</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <?php if ($filtro_base === 'local'): ?>
                                                <th>Estado</th>
                                                <th>Folio</th>
                                                <th>Solicitante</th>
                                                <th>Departamento</th>
                                                <th>Empresa</th>
                                                <th>Unidad/Equipo</th>
                                                <th>Fecha Solicitud</th>
                                                <th>Antigüedad</th>
                                                <th>Acciones</th>
                                            <?php else: ?>
                                                <th>Folio</th>
                                                <th>Solicitante</th>
                                                <th>Departamento</th>
                                                <th>Empresa</th>
                                                <th>Unidad/Equipo</th>
                                                <th>Fecha Solicitud</th>
                                                <th>Fecha Completado</th>
                                                <th>Duración</th>
                                                <th>Acciones</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ordenes as $orden): 
                                            $apartado1 = json_decode($orden['apartado1_data'], true);
                                            $unidad_equipo = $apartado1['unidad_equipo'] ?? '-';
                                        ?>
                                            <tr class="orden-card <?php 
                                                if ($filtro_base === 'local') {
                                                    echo $orden['estado'] == 'pendiente_mantenimiento' ? 'pendiente' : 
                                                        ($orden['estado'] == 'en_proceso' ? 'proceso' : 
                                                        ($orden['estado'] == 'pendiente_usuario' ? 'validar' : 
                                                        ($orden['estado'] == 'devuelto' ? 'devuelta' : '')));
                                                } else {
                                                    echo 'completada';
                                                }
                                            ?>" onclick="window.location='ver_orden_servicio.php?id=<?php echo $orden['id']; ?>'">
                                                
                                                <?php if ($filtro_base === 'local'): ?>
                                                <td>
                                                    <?php
                                                    $estado_badges = [
                                                        'pendiente_mantenimiento' => ['bg-warning text-dark', 'Pendiente'],
                                                        'en_proceso' => ['bg-info text-dark', 'En Proceso'],
                                                        'pendiente_usuario' => ['bg-purple text-white', 'Por Validar'],
                                                        'devuelto' => ['bg-danger', 'Devuelto']
                                                    ];
                                                    $badge = $estado_badges[$orden['estado']] ?? ['bg-secondary', 'Desconocido'];
                                                    ?>
                                                    <span class="badge <?php echo $badge[0]; ?>" style="<?php echo $orden['estado'] == 'pendiente_usuario' ? 'background-color: #9b59b6;' : ''; ?>">
                                                        <?php echo $badge[1]; ?>
                                                    </span>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <td><strong><?php echo htmlspecialchars($orden['folio']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($orden['usuario_nombre']); ?></td>
                                                <td><small><?php echo htmlspecialchars($orden['departamento']); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($orden['empresa'] ?? '-'); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($unidad_equipo); ?></small></td>
                                                <td>
                                                    <small><?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?></small>
                                                </td>
                                                
                                                <?php if ($filtro_base === 'local'): ?>
                                                <td>
                                                    <?php 
                                                    $dias = $orden['dias_desde_creacion'];
                                                    $color = $dias > 7 ? 'danger' : ($dias > 3 ? 'warning' : 'success');
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>" style="font-size: 0.65rem;">
                                                        <?php echo $dias; ?> día(s)
                                                    </span>
                                                </td>
                                                <?php else: ?>
                                                <td>
                                                    <small class="text-success">
                                                        <i class="bi bi-check-circle"></i>
                                                        <?php echo date('d/m/Y', strtotime($orden['fecha_completado'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $fecha_inicio = strtotime($orden['fecha_creacion']);
                                                    $fecha_fin = strtotime($orden['fecha_completado']);
                                                    $dias_duracion = ceil(($fecha_fin - $fecha_inicio) / (60 * 60 * 24));
                                                    ?>
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
                
            </div>
        </main>
        
    </div>
    
    <!-- Modal para crear nueva orden (solo para usuarios NO-Mantenimiento) -->
    <?php if (!$es_mantenimiento): ?>
        <?php include __DIR__ . '/../../includes/ordenes_servicio/modal_crear_orden_servicio.php'; ?>
    <?php endif; ?>
    
    <!-- Modal de Procesamiento de Descarga (EXCLUSIVO Mantenimiento) -->
    <?php if ($es_mantenimiento): ?>
    <div class="modal fade" id="modalProcesandoDescarga" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-success mb-4" role="status" style="width: 4rem; height: 4rem;">
                        <span class="visually-hidden">Procesando...</span>
                    </div>
                    <h4 class="mb-3" style="color: #495057;">
                        <i class="bi bi-file-earmark-excel text-success"></i> Generando archivo Excel
                    </h4>
                    <p class="text-muted mb-2">Su archivo se está procesando para descargarse.</p>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">
                        <i class="bi bi-clock-history"></i> Esto puede tardar unos segundos dependiendo de la cantidad de registros...
                    </p>
                    <div class="progress mt-4" style="height: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <!-- Script de notificaciones SSE -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
        // Auto-recargar cada 2 minutos (solo en base local)
        <?php if ($filtro_base === 'local'): ?>
        setTimeout(function() {
            location.reload();
        }, 120000);
        <?php endif; ?>

        <?php if ($es_mantenimiento): ?>
        // Función para descargar Excel con modal de procesamiento
        function descargarExcel() {
            // Mostrar modal de procesamiento
            const modal = new bootstrap.Modal(document.getElementById('modalProcesandoDescarga'));
            modal.show();
            
            // Crear un iframe oculto para la descarga
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = '<?php echo URL_BASE; ?>ordenes_servicio/descargar_ordenes_excel.php';
            document.body.appendChild(iframe);
            
            // Cerrar modal después de un tiempo prudente (la descarga debería haber iniciado)
            setTimeout(function() {
                modal.hide();
                // Limpiar el iframe después de cerrar el modal
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 1000);
            }, 3000); // 3 segundos para dar tiempo a que inicie la descarga
        }
        <?php endif; ?>
    </script>

</body>
</html>