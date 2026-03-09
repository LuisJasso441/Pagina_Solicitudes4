<?php
/**
 * Vacaciones - Panel de Admin de Área
 * dashboard/vacaciones/vacaciones_admin.php
 * 
 * Muestra solicitudes de vacaciones del departamento del Admin.
 * Permite filtrar por estado. Acceso solo para usuarios con es_admin_area = 1
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

// Verificar que sea Admin de Área
if (empty($_SESSION['es_admin_area'])) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'No tienes permisos de Admin de Área.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php');
    exit;
}

$pdo = conectarDB();

// Filtro de estado
$filtro_estado = $_GET['estado'] ?? 'pendiente_admin';
$estados_validos = ['pendiente_admin', 'aprobada_admin', 'pendiente_gth', 'aprobada_gth', 'rechazada_admin', 'completada', 'cancelada', 'todos'];

if (!in_array($filtro_estado, $estados_validos)) {
    $filtro_estado = 'pendiente_admin';
}

// Obtener departamento_id del admin
$departamento_id = $_SESSION['departamento_id'] ?? null;

if (empty($departamento_id)) {
    $stmt = $pdo->prepare("SELECT departamento_id FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $departamento_id = $stmt->fetchColumn();
}

// Obtener solicitudes del departamento
$sql = "
    SELECT sv.*, 
           u.nombre_completo, u.no_nomina, u.puesto, u.fecha_ingreso
    FROM solicitudes_vacaciones sv
    LEFT JOIN usuarios u ON sv.usuario_id = u.id
    WHERE sv.departamento_id = ?
";
$params = [$departamento_id];

if ($filtro_estado !== 'todos') {
    $sql .= " AND sv.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY sv.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores por estado
$stmt_contadores = $pdo->prepare("
    SELECT estado, COUNT(*) as total
    FROM solicitudes_vacaciones
    WHERE departamento_id = ?
    GROUP BY estado
");
$stmt_contadores->execute([$departamento_id]);
$contadores_raw = $stmt_contadores->fetchAll(PDO::FETCH_KEY_PAIR);

$pendientes_admin = $contadores_raw['pendiente_admin'] ?? 0;
$aprobadas_admin = $contadores_raw['aprobada_admin'] ?? 0;
$rechazadas_admin = $contadores_raw['rechazada_admin'] ?? 0;
$total_todas = array_sum($contadores_raw);

// Alertas
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

// Sidebar
$departamento_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_ti = ($departamento_codigo === 'sistemas' || $departamento_codigo === 'ti');
$es_mantenimiento = ($departamento_codigo === 'mantenimiento');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacaciones - Admin de Área - <?php echo NOMBRE_SISTEMA; ?></title>
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
        .filter-tabs .nav-link:hover {
            background-color: #f8f9fa;
        }
        .filter-tabs .nav-link.active {
            background-color: #198754;
            color: #fff;
            border-color: #198754;
        }
        .filter-tabs .badge {
            font-size: 0.7rem;
        }
        
        .table-admin th {
            background-color: #f8f9fa;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .table-admin td {
            vertical-align: middle;
            font-size: 0.88rem;
        }
        .table-admin tr:hover {
            background-color: #f1f8f4;
        }
        
        .stat-mini {
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-mini .stat-num {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        .stat-mini .stat-txt {
            font-size: 0.78rem;
            color: #6c757d;
            margin-top: 4px;
        }
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
                            <h1><i class="bi bi-person-check"></i> Vacaciones - Admin de Área</h1>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                Solicitudes del departamento: <strong><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?></strong>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/nueva_solicitud_manual.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil-square me-1"></i> Solicitud Manual
                            </a>
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
                    <div class="col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-warning"><?php echo $pendientes_admin; ?></div>
                            <div class="stat-txt">Pendientes</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-success"><?php echo $aprobadas_admin; ?></div>
                            <div class="stat-txt">Aprobadas</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-danger"><?php echo $rechazadas_admin; ?></div>
                            <div class="stat-txt">Rechazadas</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-mini">
                            <div class="stat-num text-primary"><?php echo $total_todas; ?></div>
                            <div class="stat-txt">Total</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <ul class="nav filter-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filtro_estado === 'pendiente_admin' ? 'active' : ''; ?>" 
                           href="?estado=pendiente_admin">
                            Pendientes
                            <?php if ($pendientes_admin > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?php echo $pendientes_admin; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filtro_estado === 'aprobada_admin' ? 'active' : ''; ?>" 
                           href="?estado=aprobada_admin">Aprobadas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filtro_estado === 'rechazada_admin' ? 'active' : ''; ?>" 
                           href="?estado=rechazada_admin">Rechazadas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filtro_estado === 'todos' ? 'active' : ''; ?>" 
                           href="?estado=todos">Todas</a>
                    </li>
                </ul>
                
                <!-- Tabla -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-0">No hay solicitudes <?php echo $filtro_estado !== 'todos' ? 'con este estado' : ''; ?> en tu departamento.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-admin mb-0">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Empleado</th>
                                        <th>Puesto</th>
                                        <th>Periodo</th>
                                        <th class="text-center">Días</th>
                                        <th>Estado</th>
                                        <th>Fecha Solicitud</th>
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
                                            <div><?php echo htmlspecialchars($sol['nombre_completo']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($sol['no_nomina'] ?: ''); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($sol['puesto'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo fecha_corta_es($sol['fecha_inicio']); ?>
                                                <br>
                                                <?php echo fecha_corta_es($sol['fecha_fin']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo $sol['dias_solicitados']; ?></span>
                                        </td>
                                        <td><?php echo badge_estado_vacaciones($sol['estado']); ?></td>
                                        <td>
                                            <small class="text-muted"><?php echo fecha_corta_es($sol['fecha_creacion']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/ver_solicitud_vacaciones.php?id=<?php echo $sol['id']; ?>" 
                                               class="btn btn-sm <?php echo $sol['estado'] === 'pendiente_admin' ? 'btn-warning' : 'btn-outline-primary'; ?>"
                                               title="<?php echo $sol['estado'] === 'pendiente_admin' ? 'Revisar y aprobar' : 'Ver detalle'; ?>">
                                                <?php if ($sol['estado'] === 'pendiente_admin'): ?>
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