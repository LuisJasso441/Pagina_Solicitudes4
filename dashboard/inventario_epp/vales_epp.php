<?php
/**
 * Listado de Vales de Entrega de EPP
 * Ubicación: dashboard/inventario_epp/vales_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
$permisos_vale = verificar_permisos_vales();

if (!$permisos['tiene_acceso'] && !$permisos_vale['puede_ver']) {
    establecer_alerta('error', 'No tienes acceso a esta seccion.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
];

$vales = obtener_vales_epp($filtros);
$stats = obtener_estadisticas_vales();
$page_title = "Vales de Entrega de EPP";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/dashboard.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/base/variables.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css" rel="stylesheet">
    <style>
        .tabla-vales-wrapper { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tabla-vales { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; }
        .tabla-vales thead th { background: #2c3e50; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #34495e; white-space: nowrap; }
        .tabla-vales tbody td { padding: 8px 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .tabla-vales tbody tr:hover { background: #f0f7ff; cursor: pointer; }
        .tabla-vales tbody tr:nth-child(even) { background: #f8fafc; }
        .tabla-vales tbody tr:nth-child(even):hover { background: #f0f7ff; }
        .badge-pendiente { background: #fff3cd; color: #856404; font-weight: 600; }
        .badge-entregado { background: #d4edda; color: #155724; font-weight: 600; }
        .badge-cancelado { background: #f8d7da; color: #721c24; font-weight: 600; }
        .stat-card-mini { padding: 0.75rem 1rem; border-radius: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); text-align: center; }
        .stat-card-mini .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .stat-card-mini .stat-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; letter-spacing: 0.3px; }
        .filtros-bar { background: #fff; padding: 0.75rem 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1rem; }
        .filtros-bar .form-control, .filtros-bar .form-select { font-size: 0.8rem; padding: 0.3rem 0.5rem; }
        .filtros-bar .form-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c757d; margin-bottom: 0.15rem; }
        .btn-accion { padding: 0.15rem 0.4rem; font-size: 0.75rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
        $depto_sb = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
        if ($depto_sb === 'almacen_residuos') {
            $sidebar_file = __DIR__ . "/../../includes/sidebar/sidebar_sec.php";
            if (file_exists($sidebar_file)) include $sidebar_file;
        } else {
            include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php";
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.4rem;">
                            <i class="bi bi-file-earmark-text text-danger"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Gestión de vales de entrega de equipo de protección personal</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Inventario
                        </a>
                        <?php if ($permisos_vale['puede_crear']): ?>
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/crear_vale_epp.php" class="btn btn-danger btn-sm">
                            <i class="bi bi-plus-circle"></i> Crear Vale
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php echo mostrar_alerta(); ?>
                
                <!-- Estadísticas -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3"><div class="stat-card-mini"><div class="stat-value text-primary"><?php echo $stats['total']; ?></div><div class="stat-label">Total Vales</div></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card-mini"><div class="stat-value text-warning"><?php echo $stats['pendientes']; ?></div><div class="stat-label">Pendientes</div></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card-mini"><div class="stat-value text-success"><?php echo $stats['entregados']; ?></div><div class="stat-label">Entregados</div></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card-mini"><div class="stat-value text-info"><?php echo $stats['entregados_mes']; ?></div><div class="stat-label">Entregados (Mes)</div></div></div>
                </div>
                
                <!-- Filtros -->
                <form method="GET" class="filtros-bar">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos</option>
                                <option value="Pendiente" <?php echo $filtros['estado'] === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Entregado" <?php echo $filtros['estado'] === 'Entregado' ? 'selected' : ''; ?>>Entregado</option>
                                <option value="Cancelado" <?php echo $filtros['estado'] === 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="busqueda" class="form-control" placeholder="Folio, empleado, área..." value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                        </div>
                        <div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="fecha_desde" class="form-control" value="<?php echo $filtros['fecha_desde']; ?>"></div>
                        <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="fecha_hasta" class="form-control" value="<?php echo $filtros['fecha_hasta']; ?>"></div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                                <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Tabla -->
                <div class="tabla-vales-wrapper">
                    <table class="tabla-vales">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Folio</th>
                                <th>Empleado</th>
                                <th>Área</th>
                                <th style="width: 90px;">Artículos</th>
                                <th style="width: 90px;">Piezas</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Creado por</th>
                                <th style="width: 60px;">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vales)): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron vales.</td></tr>
                            <?php else: ?>
                            <?php foreach ($vales as $i => $v): ?>
                            <tr onclick="window.location='<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_vale_epp.php?id=<?php echo $v['id']; ?>'" style="cursor:pointer;">
                                <td class="text-center text-muted"><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($v['folio']); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['nombre_empleado']); ?></td>
                                <td><?php echo htmlspecialchars($v['area']); ?></td>
                                <td class="text-center"><?php echo $v['total_lineas']; ?></td>
                                <td class="text-center fw-bold"><?php echo $v['total_piezas']; ?></td>
                                <td>
                                    <?php
                                    $badge_class = match($v['estado']) {
                                        'Pendiente' => 'badge-pendiente',
                                        'Entregado' => 'badge-entregado',
                                        'Cancelado' => 'badge-cancelado',
                                        default => 'bg-secondary'
                                    };
                                    $badge_icon = match($v['estado']) {
                                        'Pendiente' => 'bi-clock',
                                        'Entregado' => 'bi-check-circle',
                                        'Cancelado' => 'bi-x-circle',
                                        default => 'bi-question-circle'
                                    };
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><i class="bi <?php echo $badge_icon; ?>"></i> <?php echo $v['estado']; ?></span>
                                    <?php if ($v['estado'] === 'Pendiente'):
                                        $exp = verificar_expiracion_vale($v['fecha_creacion']);
                                    ?>
                                    <br><small class="<?php echo $exp['expirado'] ? 'text-danger fw-bold' : 'text-muted'; ?>" style="font-size:0.7rem;">
                                        <i class="bi bi-clock"></i> <?php echo $exp['texto']; ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($v['fecha_creacion'])); ?></td>
                                <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($v['creado_por_nombre']); ?></td>
                                <td class="text-center">
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_vale_epp.php?id=<?php echo $v['id']; ?>" 
                                       class="btn btn-outline-info btn-accion" onclick="event.stopPropagation();" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle"></i> <?php echo count($vales); ?> vale(s)
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>