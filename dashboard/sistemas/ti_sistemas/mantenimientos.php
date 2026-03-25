<?php
/**
 * Listado de Solicitudes de Mantenimiento
 * - Sistemas ve todas las solicitudes
 * - Otros usuarios ven solo sus propias solicitudes
 * dashboard/sistemas/ti_sistemas/mantenimientos.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// ⭐ DETECCIÓN DE DEPARTAMENTO - Igual que en ordenes_servicio_mantenimiento.php
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti = ($departamento === 'ti' || $departamento === 'sistemas' || $departamento === 'ti_sistemas');

// Filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = trim($_GET['buscar'] ?? '');
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 10;

// Construir consulta
$where = [];
$params = [];

// Si NO es Sistemas, solo mostrar sus propias solicitudes
if (!$es_ti) {
    $where[] = "sm.usuario_id = ?";
    $params[] = $_SESSION['usuario_id'];
}

if ($filtro_tipo && in_array($filtro_tipo, ['computadora', 'impresora', 'camara', 'telefono'])) {
    $where[] = "sm.tipo_equipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_estado && in_array($filtro_estado, ['pendiente', 'en_proceso', 'finalizado', 'cancelado'])) {
    $where[] = "sm.estado = ?";
    $params[] = $filtro_estado;
}

if ($busqueda) {
    $where[] = "(sm.folio LIKE ? OR sm.descripcion_problema LIKE ? OR u.nombre_completo LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Contar total
$sql_count = "SELECT COUNT(*) FROM solicitudes_mantenimiento_ti sm 
              LEFT JOIN usuarios u ON sm.usuario_id = u.id 
              $where_sql";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener registros
$offset = ($pagina - 1) * $por_pagina;
$sql = "SELECT sm.*, 
               u.nombre_completo as solicitante,
               d.nombre as departamento_nombre,
               ua.nombre_completo as atendido_por_nombre
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN usuarios u ON sm.usuario_id = u.id
        LEFT JOIN departamentos d ON sm.departamento_id = d.id
        LEFT JOIN usuarios ua ON sm.atendido_por = ua.id
        $where_sql
        ORDER BY sm.fecha_solicitud DESC
        LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contadores para cards
$contadores_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
FROM solicitudes_mantenimiento_ti sm";

// Si no es TI, solo contar sus propias solicitudes
if (!$es_ti) {
    $contadores_sql .= " WHERE sm.usuario_id = ?";
    $stmt_contadores = $pdo->prepare($contadores_sql);
    $stmt_contadores->execute([$_SESSION['usuario_id']]);
} else {
    $stmt_contadores = $pdo->query($contadores_sql);
}
$contadores = $stmt_contadores->fetch(PDO::FETCH_ASSOC);

// Configuraciones de visualización
$tipos_nombres = [
    'computadora' => 'Computadora',
    'impresora' => 'Impresora',
    'camara' => 'Cámara',
    'telefono' => 'Teléfono'
];
$iconos_tipo = [
    'computadora' => 'bi-pc-display text-primary',
    'impresora' => 'bi-printer text-purple',
    'camara' => 'bi-camera-video text-success',
    'telefono' => 'bi-phone text-orange'
];
$badges_estado = [
    'pendiente' => 'bg-warning text-dark',
    'en_proceso' => 'bg-primary',
    'finalizado' => 'bg-success',
    'cancelado' => 'bg-danger'
];
$nombres_estado = [
    'pendiente' => 'Pendiente',
    'en_proceso' => 'En Proceso',
    'finalizado' => 'Finalizado',
    'cancelado' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $es_ti ? 'Gestión de' : 'Mis'; ?> Mantenimientos - TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        .stat-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card.active {
            border-width: 2px !important;
        }
        .solicitud-card {
            transition: all 0.2s ease;
            border-left: 4px solid;
        }
        .solicitud-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .solicitud-card.pendiente { border-color: #ffc107; }
        .solicitud-card.en_proceso { border-color: #0d6efd; }
        .solicitud-card.finalizado { border-color: #198754; }
        .solicitud-card.cancelado { border-color: #dc3545; }
        
        .text-purple { color: #6f42c1; }
        .text-orange { color: #fd7e14; }
        
        @media (max-width: 576px) {
            .stat-card .display-6 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR - Usando la lógica exacta del proyecto -->
        <?php 
        if ($es_mantenimiento) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } elseif (es_usuario_gth()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0">
                            <i class="bi bi-tools me-2"></i>
                            <?php echo $es_ti ? 'Gestión de Mantenimientos' : 'Mis Solicitudes de Mantenimiento'; ?>
                        </h4>
                        <small class="text-muted">
                            <?php echo $es_ti ? 'Todas las solicitudes del sistema' : 'Historial de sus solicitudes'; ?>
                        </small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php" 
                       class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Nueva Solicitud
                    </a>
                </div>

                <!-- Cards de estadísticas -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['estado' => ''])); ?>" class="text-decoration-none">
                            <div class="card stat-card border-secondary <?php echo empty($filtro_estado) ? 'active' : ''; ?>">
                                <div class="card-body text-center py-3">
                                    <div class="display-6 fw-bold text-secondary"><?php echo $contadores['total']; ?></div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['estado' => 'pendiente'])); ?>" class="text-decoration-none">
                            <div class="card stat-card border-warning <?php echo $filtro_estado === 'pendiente' ? 'active' : ''; ?>">
                                <div class="card-body text-center py-3">
                                    <div class="display-6 fw-bold text-warning"><?php echo $contadores['pendientes']; ?></div>
                                    <small class="text-muted">Pendientes</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['estado' => 'en_proceso'])); ?>" class="text-decoration-none">
                            <div class="card stat-card border-primary <?php echo $filtro_estado === 'en_proceso' ? 'active' : ''; ?>">
                                <div class="card-body text-center py-3">
                                    <div class="display-6 fw-bold text-primary"><?php echo $contadores['en_proceso']; ?></div>
                                    <small class="text-muted">En Proceso</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['estado' => 'finalizado'])); ?>" class="text-decoration-none">
                            <div class="card stat-card border-success <?php echo $filtro_estado === 'finalizado' ? 'active' : ''; ?>">
                                <div class="card-body text-center py-3">
                                    <div class="display-6 fw-bold text-success"><?php echo $contadores['finalizados']; ?></div>
                                    <small class="text-muted">Finalizados</small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body py-2">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Tipo de Equipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos_nombres as $key => $nombre): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtro_tipo === $key ? 'selected' : ''; ?>>
                                            <?php echo $nombre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($nombres_estado as $key => $nombre): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtro_estado === $key ? 'selected' : ''; ?>>
                                            <?php echo $nombre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Buscar</label>
                                <input type="text" name="buscar" class="form-control form-control-sm" 
                                       placeholder="Folio, descripción..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de solicitudes -->
                <?php if (empty($solicitudes)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No se encontraron solicitudes de mantenimiento.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($solicitudes as $sol): ?>
                            <div class="col-12">
                                <div class="card solicitud-card <?php echo $sol['estado']; ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center mb-2 mb-md-0">
                                                <i class="bi <?php echo $iconos_tipo[$sol['tipo_equipo']] ?? 'bi-device'; ?>" 
                                                   style="font-size: 1.5rem;"></i>
                                            </div>
                                            <div class="col-md-3">
                                                <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $tipos_nombres[$sol['tipo_equipo']] ?? $sol['tipo_equipo']; ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted d-block">Solicitante:</small>
                                                <?php echo htmlspecialchars($sol['solicitante']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($sol['departamento_nombre']); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <span class="badge <?php echo $badges_estado[$sol['estado']]; ?>">
                                                    <?php echo $nombres_estado[$sol['estado']]; ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <small class="text-muted d-block">Fecha:</small>
                                                <?php echo date('d/m/Y', strtotime($sol['fecha_solicitud'])); ?>
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_mantenimiento.php?id=<?php echo $sol['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagina < $total_paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>