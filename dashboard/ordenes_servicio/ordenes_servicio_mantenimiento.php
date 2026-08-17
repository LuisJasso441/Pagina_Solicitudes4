<?php
/**
 * Dashboard de Mantenimiento - Órdenes de Servicio
 * Con tabs: Base Local (Activas) / Base Global (Finalizadas)
 * Similar al sistema SSC Colaborativo
 * 
 * ACTUALIZADO: Botones de descarga movidos fuera de filtros
 *              Nuevo botón para descargar por rango de fechas
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

// ========================================
// VERIFICAR PERMISOS OSM - CREADOR
// Para mostrar/ocultar botón "Nueva Orden de Servicio"
// ========================================
$puede_crear_osm = false;
if (!$es_mantenimiento) {
    try {
        $stmt_permisos_osm = $pdo->prepare("SELECT creador FROM permisos_osm WHERE user_id = :user_id");
        $stmt_permisos_osm->execute([':user_id' => $_SESSION['usuario_id']]);
        $permisos_osm = $stmt_permisos_osm->fetch(PDO::FETCH_ASSOC);
        $puede_crear_osm = ($permisos_osm && $permisos_osm['creador'] == 1);
    } catch (Exception $e) {
        error_log("Error verificando permisos OSM: " . $e->getMessage());
        $puede_crear_osm = false;
    }
}

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
    if (!$es_mantenimiento) {
        $stmt_stats = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM ordenes_servicio_mantenimiento
            WHERE estado = 'completado'
            AND departamento = :dept_usuario
        ");
        $stmt_stats->execute([':dept_usuario' => $_SESSION['departamento_nombre']]);
    } else {
        $stmt_stats = $pdo->query("
            SELECT COUNT(*) as total
            FROM ordenes_servicio_mantenimiento
            WHERE estado = 'completado'
        ");
    }
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
    <title>Órdenes de Servicio | VerdenCore</title>
    
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
            padding: 0.5rem 0.75rem; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .card-body { padding: 0.75rem; }
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .stat-card {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .stat-card.pendientes { border-left: 3px solid #f39c12; }
        .stat-card.proceso { border-left: 3px solid #3498db; }
        .stat-card.validar { border-left: 3px solid #9b59b6; }
        .stat-card.devueltas { border-left: 3px solid #e74c3c; }
        .stat-card.completadas { border-left: 3px solid #27ae60; }
        .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #495057;
        }
        .stat-label {
            font-size: 0.65rem;
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
        
        /* Formularios compactos */
        .form-label { 
            margin-bottom: 0.2rem; 
            font-size: 0.7rem; 
            font-weight: 600;
            color: #5a5c69;
        }
        .form-control, .form-select { 
            padding: 0.3rem 0.5rem; 
            font-size: 0.8rem;
        }
        
        /* Tabs */
        .nav-tabs .nav-link {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        /* Botones de descarga */
        .btn-descarga-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .btn-descarga-container .btn {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* ====================================
           MODO OSCURO - Overrides locales
           ==================================== */
        body[data-theme="dark"] {
            background: var(--bg-primary, #1a1a1a);
        }
        body[data-theme="dark"] .page-header {
            background: var(--card-bg, #2d2d2d);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        body[data-theme="dark"] .page-header h1 {
            color: var(--text-primary, #e0e0e0);
        }
        body[data-theme="dark"] .page-header .text-muted {
            color: var(--text-secondary, #b0b0b0) !important;
        }
        body[data-theme="dark"] .stat-card {
            background: var(--card-bg, #2d2d2d);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        body[data-theme="dark"] .stat-number {
            color: var(--text-primary, #e0e0e0);
        }
        body[data-theme="dark"] .stat-label {
            color: var(--text-secondary, #b0b0b0);
        }
        body[data-theme="dark"] .card {
            background: var(--card-bg, #2d2d2d);
            border-color: var(--border-color, #404040);
        }
        body[data-theme="dark"] .card-body {
            background: var(--card-bg, #2d2d2d);
            color: var(--text-primary, #e0e0e0);
        }
        body[data-theme="dark"] table th {
            background: #1f1f1f;
            color: var(--text-primary, #e0e0e0);
        }
        body[data-theme="dark"] .form-label {
            color: var(--text-primary, #e0e0e0);
        }
        body[data-theme="dark"] .nav-tabs {
            border-bottom-color: var(--border-color, #404040);
        }
        body[data-theme="dark"] .nav-tabs .nav-link:not(.active) {
            color: var(--text-secondary, #b0b0b0);
        }
        body[data-theme="dark"] .nav-tabs .nav-link:not(.active):hover {
            color: var(--text-primary, #e0e0e0);
            border-color: transparent;
        }
        body[data-theme="dark"] .form-control::placeholder,
        body[data-theme="dark"] .form-select::placeholder {
            color: #a0a0a0 !important;
            opacity: 1;
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
        } elseif (es_usuario_epp()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_inventario.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } elseif (es_usuario_gth()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_gth.php';
        } elseif (in_array(strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''), ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../includes/sidebar/sidebar_sec.php';
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
                        
                        <?php if ($puede_crear_osm): ?>
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
                    <div class="stat-card pendientes">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $stats['pendientes']; ?></div>
                                <div class="stat-label">Pendientes</div>
                            </div>
                            <i class="bi bi-clock stat-icon"></i>
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
                        </form>
                    </div>
                </div>
                
                <!-- BOTONES DE DESCARGA (EXCLUSIVO Mantenimiento) - FUERA DE FILTROS -->
                <?php if ($es_mantenimiento): ?>
                <div class="btn-descarga-container">
                    <button type="button" class="btn btn-success btn-sm" onclick="descargarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Descargar registros (Mes actual)
                    </button>
                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDescargarRango">
                        <i class="bi bi-calendar-range"></i> Descargar por rango de fechas
                    </button>
                </div>
                <?php endif; ?>
                
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
                                            
                                            // Calcular duración para finalizadas
                                            $dias_duracion = '';
                                            if ($filtro_base === 'global' && !empty($orden['fecha_completado']) && !empty($orden['fecha_creacion'])) {
                                                $fecha_inicio = new DateTime($orden['fecha_creacion']);
                                                $fecha_fin = new DateTime($orden['fecha_completado']);
                                                $diferencia = $fecha_inicio->diff($fecha_fin);
                                                $dias_duracion = $diferencia->days;
                                            }
                                        ?>
                                            <tr class="orden-card <?php 
                                                if ($filtro_base === 'local') {
                                                    switch($orden['estado']) {
                                                        case 'pendiente_mantenimiento': echo 'pendiente'; break;
                                                        case 'en_proceso': echo 'proceso'; break;
                                                        case 'pendiente_usuario': echo 'validar'; break;
                                                        case 'devuelto': echo 'devuelta'; break;
                                                    }
                                                } else {
                                                    echo 'completada';
                                                }
                                            ?>" onclick="window.location='ver_orden_servicio.php?id=<?php echo $orden['id']; ?>'">
                                                
                                                <?php if ($filtro_base === 'local'): ?>
                                                    <td>
                                                        <?php 
                                                        $badge_class = 'bg-secondary';
                                                        $estado_texto = $orden['estado'];
                                                        switch($orden['estado']) {
                                                            case 'pendiente_mantenimiento':
                                                                $badge_class = 'bg-warning text-dark';
                                                                $estado_texto = 'Pendiente';
                                                                break;
                                                            case 'en_proceso':
                                                                $badge_class = 'bg-info';
                                                                $estado_texto = 'En Proceso';
                                                                break;
                                                            case 'pendiente_usuario':
                                                                $badge_class = 'bg-primary';
                                                                $estado_texto = 'A Validar';
                                                                break;
                                                            case 'devuelto':
                                                                $badge_class = 'bg-danger';
                                                                $estado_texto = 'Devuelta';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $estado_texto; ?></span>
                                                    </td>
                                                <?php endif; ?>
                                                
                                                <td><strong><?php echo htmlspecialchars($orden['folio']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($orden['usuario_nombre']); ?></td>
                                                <td><small><?php echo htmlspecialchars($orden['departamento']); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($orden['empresa'] ?? '-'); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($unidad_equipo); ?></small></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($orden['fecha_creacion'])); ?>
                                                    </small>
                                                </td>
                                                
                                                <?php if ($filtro_base === 'local'): ?>
                                                    <td>
                                                        <?php 
                                                        $dias = $orden['dias_desde_creacion'];
                                                        $badge_dias = 'bg-secondary';
                                                        if ($dias > 7) $badge_dias = 'bg-danger';
                                                        elseif ($dias > 3) $badge_dias = 'bg-warning text-dark';
                                                        elseif ($dias > 1) $badge_dias = 'bg-info';
                                                        ?>
                                                        <span class="badge <?php echo $badge_dias; ?>">
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
                                                        <span class="badge bg-secondary">
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
    
    <!-- Modal para crear nueva orden (solo si tiene permisos de creador OSM) -->
    <?php if ($puede_crear_osm): ?>
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
    
    <!-- Modal para Descargar por Rango de Fechas -->
    <div class="modal fade" id="modalDescargarRango" tabindex="-1" aria-labelledby="modalDescargarRangoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDescargarRangoLabel">
                        <i class="bi bi-calendar-range"></i> Descargar por Rango de Fechas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">
                        Seleccione el rango de fechas para descargar las órdenes de servicio. 
                        Se filtrarán por <strong>fecha de entrada</strong>.
                    </p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rango_fecha_desde" class="form-label">
                                <i class="bi bi-calendar-event"></i> Fecha Desde *
                            </label>
                            <input type="date" class="form-control" id="rango_fecha_desde" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rango_fecha_hasta" class="form-label">
                                <i class="bi bi-calendar-event"></i> Fecha Hasta *
                            </label>
                            <input type="date" class="form-control" id="rango_fecha_hasta" required>
                        </div>
                    </div>
                    
                    <div id="errorRangoFechas" class="alert alert-danger mt-3 d-none">
                        <i class="bi bi-exclamation-triangle"></i> <span id="errorRangoTexto"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-info" onclick="descargarExcelRango()">
                        <i class="bi bi-download"></i> Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <!-- Script de notificaciones SSE -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
        // ========================================
        // SISTEMA DE AUTO-RELOAD INTELIGENTE
        // Pausa cuando hay modales abiertos
        // ========================================
        <?php if ($filtro_base === 'local'): ?>
        (function() {
            var AUTO_RELOAD_INTERVAL = 120000; // 2 minutos
            var autoReloadTimer = null;
            var modalAbierto = false;
            
            // Función para iniciar el timer de auto-reload
            function iniciarAutoReload() {
                // Cancelar timer existente si hay uno
                if (autoReloadTimer) {
                    clearTimeout(autoReloadTimer);
                }
                
                // Solo iniciar si no hay modal abierto
                if (!modalAbierto) {
                    autoReloadTimer = setTimeout(function() {
                        // Verificar una vez más antes de recargar
                        if (!modalAbierto) {
                            location.reload();
                        } else {
                            // Si hay modal abierto, reintentar después
                            iniciarAutoReload();
                        }
                    }, AUTO_RELOAD_INTERVAL);
                    console.log('⏱️ Auto-reload programado en 2 minutos');
                }
            }
            
            // Función para pausar el auto-reload
            function pausarAutoReload() {
                if (autoReloadTimer) {
                    clearTimeout(autoReloadTimer);
                    autoReloadTimer = null;
                    console.log('⏸️ Auto-reload pausado (modal abierto)');
                }
                modalAbierto = true;
            }
            
            // Función para reanudar el auto-reload
            function reanudarAutoReload() {
                modalAbierto = false;
                iniciarAutoReload();
                console.log('▶️ Auto-reload reanudado');
            }
            
            // Escuchar eventos de TODOS los modales en la página
            document.addEventListener('shown.bs.modal', function(e) {
                pausarAutoReload();
            });
            
            document.addEventListener('hidden.bs.modal', function(e) {
                // Pequeño delay para verificar si hay otro modal abierto
                setTimeout(function() {
                    var modalesAbiertos = document.querySelectorAll('.modal.show');
                    if (modalesAbiertos.length === 0) {
                        reanudarAutoReload();
                    }
                }, 100);
            });
            
            // Iniciar el auto-reload al cargar la página
            iniciarAutoReload();
        })();
        <?php endif; ?>

        <?php if ($es_mantenimiento): ?>
        // Función para descargar Excel del mes actual
        function descargarExcel() {
            // Mostrar modal de procesamiento
            const modal = new bootstrap.Modal(document.getElementById('modalProcesandoDescarga'));
            modal.show();
            
            // Crear un iframe oculto para la descarga
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = '<?php echo URL_BASE; ?>ordenes_servicio/descargar_ordenes_excel.php';
            document.body.appendChild(iframe);
            
            // Cerrar modal después de un tiempo prudente
            setTimeout(function() {
                modal.hide();
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 1000);
            }, 3000);
        }
        
        // Función para descargar Excel por rango de fechas
        function descargarExcelRango() {
            const fechaDesde = document.getElementById('rango_fecha_desde').value;
            const fechaHasta = document.getElementById('rango_fecha_hasta').value;
            const errorDiv = document.getElementById('errorRangoFechas');
            const errorTexto = document.getElementById('errorRangoTexto');
            
            // Ocultar error previo
            errorDiv.classList.add('d-none');
            
            // Validar campos obligatorios
            if (!fechaDesde || !fechaHasta) {
                errorTexto.textContent = 'Ambas fechas son obligatorias.';
                errorDiv.classList.remove('d-none');
                return;
            }
            
            // Validar que fecha desde no sea mayor que fecha hasta
            if (fechaDesde > fechaHasta) {
                errorTexto.textContent = 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".';
                errorDiv.classList.remove('d-none');
                return;
            }
            
            // Cerrar modal de rango
            const modalRango = bootstrap.Modal.getInstance(document.getElementById('modalDescargarRango'));
            modalRango.hide();
            
            // Mostrar modal de procesamiento
            const modalProcesando = new bootstrap.Modal(document.getElementById('modalProcesandoDescarga'));
            modalProcesando.show();
            
            // Crear un iframe oculto para la descarga con parámetros
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = '<?php echo URL_BASE; ?>ordenes_servicio/descargar_ordenes_excel_rango.php?fecha_desde=' + 
                         encodeURIComponent(fechaDesde) + '&fecha_hasta=' + encodeURIComponent(fechaHasta);
            document.body.appendChild(iframe);
            
            // Cerrar modal después de un tiempo prudente
            setTimeout(function() {
                modalProcesando.hide();
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 1000);
            }, 3000);
        }
        <?php endif; ?>
    </script>

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