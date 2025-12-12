<?php
/**
 * Dashboard de Mantenimiento - Órdenes de Servicio Finalizadas
 * Vista global de todas las órdenes completadas
 * Diseño compacto y optimizado
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

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

// Filtros
$filtros = [
    'departamento' => $_GET['departamento'] ?? '',
    'empleado' => $_GET['empleado'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

// Construir consulta SQL
$sql = "SELECT 
            id, folio, usuario_nombre, departamento, empresa,
            fecha_creacion, fecha_completado, apartado1_data
        FROM ordenes_servicio_mantenimiento 
        WHERE estado = 'completado'";

$params = [];

if (!empty($filtros['departamento'])) {
    $sql .= " AND departamento = :departamento";
    $params[':departamento'] = $filtros['departamento'];
}

if (!empty($filtros['empleado'])) {
    $sql .= " AND usuario_nombre = :empleado";
    $params[':empleado'] = $filtros['empleado'];
}

if (!empty($filtros['fecha_desde'])) {
    $sql .= " AND DATE(fecha_completado) >= :fecha_desde";
    $params[':fecha_desde'] = $filtros['fecha_desde'];
}

if (!empty($filtros['fecha_hasta'])) {
    $sql .= " AND DATE(fecha_completado) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtros['fecha_hasta'];
}

if (!empty($filtros['busqueda'])) {
    $sql .= " AND (folio LIKE :busqueda OR usuario_nombre LIKE :busqueda OR departamento LIKE :busqueda)";
    $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
}

$sql .= " ORDER BY fecha_completado DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de departamentos
$stmt_deptos = $pdo->query("SELECT DISTINCT departamento FROM ordenes_servicio_mantenimiento WHERE estado = 'completado' ORDER BY departamento");
$departamentos = $stmt_deptos->fetchAll(PDO::FETCH_COLUMN);

// Obtener empleados si hay departamento seleccionado
$empleados = [];
if (!empty($filtros['departamento'])) {
    $stmt_emp = $pdo->prepare("SELECT DISTINCT usuario_nombre FROM ordenes_servicio_mantenimiento WHERE departamento = :departamento AND estado = 'completado' ORDER BY usuario_nombre");
    $stmt_emp->execute([':departamento' => $filtros['departamento']]);
    $empleados = $stmt_emp->fetchAll(PDO::FETCH_COLUMN);
}

// Estadísticas
$total = count($ordenes);

// Estadísticas adicionales
$stmt_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_global,
        COUNT(DISTINCT departamento) as departamentos_totales,
        COUNT(DISTINCT DATE(fecha_completado)) as dias_con_finalizaciones
    FROM ordenes_servicio_mantenimiento
    WHERE estado = 'completado'
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes Finalizadas | <?php echo NOMBRE_SISTEMA; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        /* Diseño compacto general */
        body { 
            font-size: 0.85rem; 
            background: #f5f6fa;
        }
        
        /* Cards compactas */
        .card { 
            box-shadow: 0 1px 2px rgba(0,0,0,0.08); 
            border: 1px solid #e3e6f0;
            margin-bottom: 0.75rem;
        }
        .card-header { 
            padding: 0.4rem 0.75rem; 
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
        }
        .card-body { 
            padding: 0.75rem; 
        }
        
        /* Estadísticas compactas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            border-left: 3px solid #2ecc71;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
            color: #2ecc71;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.3;
            color: #2ecc71;
        }
        
        /* Tabla compacta */
        .table { 
            font-size: 0.8rem; 
            margin-bottom: 0;
        }
        .table th { 
            padding: 0.4rem 0.5rem; 
            background: #f8f9fc;
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
            color: #5a5c69;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td { 
            padding: 0.5rem; 
            vertical-align: middle;
            border-bottom: 1px solid #f0f1f5;
        }
        .table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s;
        }
        .table tbody tr:hover { 
            background-color: #f8f9fc;
        }
        
        /* Badges compactos */
        .badge { 
            font-size: 0.7rem; 
            padding: 0.25em 0.6em;
            font-weight: 600;
        }
        
        /* Botones compactos */
        .btn-sm { 
            padding: 0.25rem 0.6rem; 
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        /* Formularios compactos */
        .form-label { 
            margin-bottom: 0.2rem; 
            font-size: 0.75rem; 
            font-weight: 600;
            color: #5a5c69;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .form-control, .form-select { 
            padding: 0.35rem 0.5rem; 
            font-size: 0.8rem;
            border: 1px solid #d1d3e2;
            border-radius: 4px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2ecc71;
            box-shadow: 0 0 0 0.15rem rgba(46, 204, 113, 0.15);
        }
        
        /* Encabezado compacto */
        .page-header {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .page-header h4 {
            font-size: 1.25rem;
            margin: 0;
            color: #2d3748;
        }
        .page-header small {
            font-size: 0.75rem;
            color: #718096;
        }
        
        /* Badge de completado */
        .badge-completado {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        
        /* Icono de check */
        .success-icon {
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php 
                // Cargar sidebar según departamento
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
            </div>
            
            <!-- Contenido principal -->
            <div class="col-md-10 p-2">
                
                <!-- Encabezado -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><i class="bi bi-check-circle success-icon"></i> Órdenes Finalizadas</h4>
                            <small>Historial completo de órdenes completadas (todos los departamentos)</small>
                        </div>
                        <div class="text-end">
                            <div class="badge badge-completado"><?php echo $total; ?> registros</div>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $stats['total_global']; ?></div>
                                <div class="stat-label">Total Completadas</div>
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
                                <div class="stat-number"><?php echo $total; ?></div>
                                <div class="stat-label">Filtrados</div>
                            </div>
                            <i class="bi bi-funnel stat-icon"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros de Búsqueda
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Departamento</label>
                                <select name="departamento" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">Todos los departamentos</option>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?php echo htmlspecialchars($depto); ?>" 
                                                <?php echo $filtros['departamento'] == $depto ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($depto); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Empleado</label>
                                <select name="empleado" class="form-select form-select-sm" 
                                        <?php echo empty($empleados) ? 'disabled' : ''; ?>>
                                    <option value="">Todos los empleados</option>
                                    <?php foreach ($empleados as $emp): ?>
                                        <option value="<?php echo htmlspecialchars($emp); ?>" 
                                                <?php echo $filtros['empleado'] == $emp ? 'selected' : ''; ?>>
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
                            
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button type="submit" class="btn btn-success btn-sm flex-grow-1">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                                <a href="?" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabla -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-table"></i> Listado de Órdenes Completadas
                        <span class="badge bg-light text-dark ms-2"><?php echo count($ordenes); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($ordenes)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-2 text-muted"></i>
                                <p class="text-muted mt-2 mb-0">No hay órdenes finalizadas con estos filtros</p>
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
                                            <th style="width: 90px;">Creada</th>
                                            <th style="width: 90px;">Completada</th>
                                            <th style="width: 70px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ordenes as $orden): 
                                            $apartado1 = json_decode($orden['apartado1_data'], true);
                                            
                                            // Calcular días de duración
                                            $fecha_inicio = new DateTime($orden['fecha_creacion']);
                                            $fecha_fin = new DateTime($orden['fecha_completado']);
                                            $dias_duracion = $fecha_inicio->diff($fecha_fin)->days;
                                        ?>
                                            <tr onclick="window.location='ver_orden_servicio.php?id=<?php echo $orden['id']; ?>'">
                                                <td>
                                                    <strong class="text-success"><?php echo htmlspecialchars($orden['folio']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($orden['usuario_nombre']); ?></td>
                                                <td><small><?php echo htmlspecialchars($orden['departamento']); ?></small></td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($orden['empresa']); ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($apartado1['unidad_equipo'] ?? '-'); ?></small></td>
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
                                                    <br>
                                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">
                                                        <?php echo $dias_duracion; ?> día(s)
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="ver_orden_servicio.php?id=<?php echo $orden['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" 
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
</body>
</html>