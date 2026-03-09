<?php
/**
 * Vacaciones - Panel de GTH
 * dashboard/vacaciones/vacaciones_gth.php
 * 
 * Muestra solicitudes de vacaciones de TODOS los departamentos.
 * Filtrable por estado y departamento.
 * Acceso: usuarios de GTH, Gestión de Talento, Contabilidad
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea GTH/Contabilidad
$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_gth = in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad']);
$es_sistemas = in_array($depto_codigo, ['sistemas', 'ti']);

if (!$es_gth && !$es_sistemas) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos para acceder al panel de GTH.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

$pdo = conectarDB();

// Filtros
$filtro_estado = $_GET['estado'] ?? 'pendiente_gth';
$filtro_depto  = intval($_GET['departamento'] ?? 0);
$estados_validos = ['pendiente_gth', 'pendiente_admin', 'aprobada_admin', 'aprobada_gth', 'completada', 'rechazada_admin', 'rechazada_gth', 'cancelada', 'todos'];

if (!in_array($filtro_estado, $estados_validos)) {
    $filtro_estado = 'pendiente_gth';
}

// Obtener lista de departamentos para filtro
$stmt_deptos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
$departamentos = $stmt_deptos->fetchAll(PDO::FETCH_ASSOC);

// Construir query
$sql = "
    SELECT sv.*, 
           u.nombre_completo, u.no_nomina, u.puesto, u.fecha_ingreso,
           d.nombre AS departamento_nombre,
           ua.nombre_completo AS admin_nombre
    FROM solicitudes_vacaciones sv
    LEFT JOIN usuarios u ON sv.usuario_id = u.id
    LEFT JOIN departamentos d ON sv.departamento_id = d.id
    LEFT JOIN usuarios ua ON sv.admin_id = ua.id
    WHERE 1=1
";
$params = [];

if ($filtro_estado !== 'todos') {
    $sql .= " AND sv.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_depto > 0) {
    $sql .= " AND sv.departamento_id = ?";
    $params[] = $filtro_depto;
}

$sql .= " ORDER BY sv.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores globales
$stmt_contadores = $pdo->prepare("SELECT estado, COUNT(*) as total FROM solicitudes_vacaciones GROUP BY estado");
$stmt_contadores->execute();
$contadores_raw = $stmt_contadores->fetchAll(PDO::FETCH_KEY_PAIR);

$pendientes_gth = $contadores_raw['pendiente_gth'] ?? 0;
$pendientes_admin = $contadores_raw['pendiente_admin'] ?? 0;
$completadas = $contadores_raw['completada'] ?? 0;
$rechazadas = ($contadores_raw['rechazada_admin'] ?? 0) + ($contadores_raw['rechazada_gth'] ?? 0);
$total_todas = array_sum($contadores_raw);

// Alertas
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

// Sidebar
// Variables para seleccion de sidebar
$es_mantenimiento = ($depto_codigo === 'mantenimiento');
$es_ti = in_array($depto_codigo, ['sistemas', 'ti']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacaciones - GTH - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .filter-tabs .nav-link {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 8px 16px;
            margin-right: 4px;
            transition: all 0.2s;
        }
        .filter-tabs .nav-link:hover { background-color: #f8f9fa; }
        .filter-tabs .nav-link.active { background-color: #0d6efd; color: #fff; border-color: #0d6efd; }
        .filter-tabs .badge { font-size: 0.7rem; }
        
        .table-gth th {
            background-color: #f8f9fa;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .table-gth td { vertical-align: middle; font-size: 0.88rem; }
        .table-gth tr:hover { background-color: #f0f4ff; }
        
        .stat-mini {
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-mini .stat-num { font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .stat-mini .stat-txt { font-size: 0.78rem; color: #6c757d; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php
        if ($es_mantenimiento) {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_gth.php';
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>
        
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-clipboard2-check"></i> Vacaciones - Panel GTH</h1>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/descargar_vacaciones_excel.php?anio=<?php echo date('Y'); ?>" 
                           class="btn btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i> Descargar BD
                        </a>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                Gestión de solicitudes de vacaciones de todos los departamentos
                            </p>
                        </div>
                        <div>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i> Mis Vacaciones
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Alertas -->
                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $alerta['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Mini stats -->
                <div class="row g-3 mb-4">
                    <div class="col-lg col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-warning"><?php echo $pendientes_gth; ?></div>
                            <div class="stat-txt">Pendientes GTH</div>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-info"><?php echo $pendientes_admin; ?></div>
                            <div class="stat-txt">Pend. Admin</div>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-success"><?php echo $completadas; ?></div>
                            <div class="stat-txt">Completadas</div>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-danger"><?php echo $rechazadas; ?></div>
                            <div class="stat-txt">Rechazadas</div>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-primary"><?php echo $total_todas; ?></div>
                            <div class="stat-txt">Total</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <ul class="nav filter-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filtro_estado === 'pendiente_gth' ? 'active' : ''; ?>" 
                               href="?estado=pendiente_gth<?php echo $filtro_depto ? '&departamento='.$filtro_depto : ''; ?>">
                                Pendientes GTH
                                <?php if ($pendientes_gth > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $pendientes_gth; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filtro_estado === 'completada' ? 'active' : ''; ?>" 
                               href="?estado=completada<?php echo $filtro_depto ? '&departamento='.$filtro_depto : ''; ?>">Completadas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filtro_estado === 'rechazada_gth' ? 'active' : ''; ?>" 
                               href="?estado=rechazada_gth<?php echo $filtro_depto ? '&departamento='.$filtro_depto : ''; ?>">Rechazadas GTH</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filtro_estado === 'todos' ? 'active' : ''; ?>" 
                               href="?estado=todos<?php echo $filtro_depto ? '&departamento='.$filtro_depto : ''; ?>">Todas</a>
                        </li>
                    </ul>
                    
                    <!-- Filtro por departamento -->
                    <div>
                        <select id="filtroDepto" class="form-select form-select-sm" style="min-width: 200px;">
                            <option value="0">Todos los departamentos</option>
                            <?php foreach ($departamentos as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $filtro_depto == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Tabla -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-0">No hay solicitudes con los filtros seleccionados.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-gth mb-0">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Empleado</th>
                                        <th>Departamento</th>
                                        <th>Periodo</th>
                                        <th class="text-center">Días</th>
                                        <th>Admin Área</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($sol['nombre_completo'] ?? $sol['nombre_manual'] ?? ''); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($sol['no_nomina'] ?: ''); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($sol['departamento_nombre']); ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo fecha_corta_es($sol['fecha_inicio']); ?><br>
                                                <?php echo fecha_corta_es($sol['fecha_fin']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo $sol['dias_solicitados']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($sol['admin_nombre']): ?>
                                                <small>
                                                    <?php echo htmlspecialchars($sol['admin_nombre']); ?><br>
                                                    <span class="text-muted"><?php echo $sol['fecha_aprobacion_admin'] ? fecha_corta_es($sol['fecha_aprobacion_admin']) : ''; ?></span>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo badge_estado_vacaciones($sol['estado']); ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/ver_solicitud_vacaciones.php?id=<?php echo $sol['id']; ?>" 
                                               class="btn btn-sm <?php echo $sol['estado'] === 'pendiente_gth' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                               title="<?php echo $sol['estado'] === 'pendiente_gth' ? 'Revisar y aprobar' : 'Ver detalle'; ?>">
                                                <?php if ($sol['estado'] === 'pendiente_gth'): ?>
                                                    <i class="bi bi-pencil-square me-1"></i> Revisar
                                                <?php else: ?>
                                                    <i class="bi bi-eye"></i>
                                                <?php endif; ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filtro departamento
        document.getElementById('filtroDepto').addEventListener('change', function() {
            const estado = '<?php echo $filtro_estado; ?>';
            const depto = this.value;
            let url = '?estado=' + estado;
            if (depto > 0) url += '&departamento=' + depto;
            window.location.href = url;
        });
        
        // Sidebar hamburger
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
                this.setAttribute('aria-expanded', sidebar.classList.contains('active'));
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                this.classList.remove('active');
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
            });
        }
    });
    </script>
</body>
</html>