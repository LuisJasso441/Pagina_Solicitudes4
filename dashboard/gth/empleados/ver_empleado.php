<?php
/**
 * Ver Detalle de Empleado
 * dashboard/gth/empleados/ver_empleado.php
 * 
 * Muestra: datos completos, resumen de vacaciones, historial de solicitudes
 * Accesible por GTH y Contabilidad
 */
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/vacaciones/vacaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
if (!in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad'])) {
    establecer_alerta('error', 'No tienes permisos.');
    header('Location: ' . URL_BASE . 'index.php'); exit;
}

$pdo = conectarDB();
$empleado_id = intval($_GET['id'] ?? 0);

if ($empleado_id <= 0) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'ID invalido.'];
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}

// Obtener empleado
$stmt = $pdo->prepare("
    SELECT e.*, d.nombre AS departamento_nombre,
           u.id AS uid, u.usuario AS username
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$empleado_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Empleado no encontrado.'];
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}

$tiene_cuenta = !empty($emp['usuario_id']);

// Calcular vacaciones
$resumen_vac = null;
$periodo_anterior = null;
$solicitudes_todas = [];

if ($tiene_cuenta && !empty($emp['fecha_ingreso'])) {
    $resumen_vac = obtener_resumen_vacaciones($pdo, $emp['usuario_id']);
    $periodo_anterior = $resumen_vac['periodo_anterior'] ?? null;
    
    // Todas las solicitudes (no solo del periodo actual)
    $stmt_sol = $pdo->prepare("
        SELECT sv.*, 
               admin_u.nombre_completo AS admin_nombre,
               gth_u.nombre_completo AS gth_nombre
        FROM solicitudes_vacaciones sv
        LEFT JOIN usuarios admin_u ON sv.admin_id = admin_u.id
        LEFT JOIN usuarios gth_u ON sv.gth_id = gth_u.id
        WHERE sv.usuario_id = ?
        ORDER BY sv.fecha_creacion DESC
    ");
    $stmt_sol->execute([$emp['usuario_id']]);
    $solicitudes_todas = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);
} elseif (!$tiene_cuenta && !empty($emp['fecha_ingreso'])) {
    // Sin cuenta: calcular manualmente
    $ant = calcular_antiguedad($emp['fecha_ingreso']);
    $anio_p = max(1, $ant['anios_completos'] > 0 ? $ant['anios_completos'] + 1 : 1);
    $dias_lft = dias_vacaciones_lft($anio_p);
    
    // Buscar solicitudes manuales
    $stmt_sol = $pdo->prepare("
        SELECT sv.*,
               admin_u.nombre_completo AS admin_nombre,
               gth_u.nombre_completo AS gth_nombre
        FROM solicitudes_vacaciones sv
        LEFT JOIN usuarios admin_u ON sv.admin_id = admin_u.id
        LEFT JOIN usuarios gth_u ON sv.gth_id = gth_u.id
        WHERE sv.es_manual = 1 AND sv.nombre_manual = ?
        ORDER BY sv.fecha_creacion DESC
    ");
    $stmt_sol->execute([$emp['nombre_completo']]);
    $solicitudes_todas = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);
    
    $dias_tomados = 0;
    foreach ($solicitudes_todas as $s) {
        if (!in_array($s['estado'], ['cancelada', 'rechazada_admin', 'rechazada_gth'])) {
            $dias_tomados += $s['dias_solicitados'];
        }
    }
    
    $resumen_vac = [
        'tiene_fecha_ingreso' => true,
        'antiguedad_texto' => texto_antiguedad($emp['fecha_ingreso']),
        'antiguedad' => $ant,
        'dias_correspondientes' => $dias_lft,
        'dias_tomados' => $dias_tomados,
        'dias_disponibles' => max(0, $dias_lft - $dias_tomados),
        'periodo' => obtener_periodo_actual($emp['fecha_ingreso']),
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleado: <?php echo htmlspecialchars($emp['nombre_completo']); ?> - GTH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <style>
        .info-card { border-radius:12px; }
        .info-card .label { font-size:.78rem; text-transform:uppercase; color:#6c757d; letter-spacing:.3px; font-weight:600; }
        .info-card .value { font-size:.95rem; font-weight:500; }
        .stat-mini { text-align:center; padding:12px; border-radius:10px; }
        .stat-mini .number { font-size:1.6rem; font-weight:700; line-height:1.2; }
        .stat-mini .text { font-size:.72rem; text-transform:uppercase; letter-spacing:.3px; }
        .table-hist th { font-size:.75rem; text-transform:uppercase; letter-spacing:.3px; }
        .table-hist td { font-size:.84rem; vertical-align:middle; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php'; ?>
        <main class="main-content">
            <div class="content-wrapper">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/gth/dashboard_gth.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php">Empleados</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($emp['nombre_completo']); ?></li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <h3 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($emp['nombre_completo']); ?>
                    </h3>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/editar_empleado.php?id=<?php echo $emp['id']; ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
                        <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Regresar</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Datos del empleado -->
                    <div class="col-lg-5">
                        <div class="card info-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>Datos del Empleado</h6>
                                <div class="row g-3">
                                    <div class="col-6"><div class="label">No. Nomina</div><div class="value"><?php echo htmlspecialchars($emp['no_nomina'] ?? '-'); ?></div></div>
                                    <div class="col-6"><div class="label">Departamento</div><div class="value"><?php echo htmlspecialchars($emp['departamento_nombre'] ?? '-'); ?></div></div>
                                    <div class="col-6"><div class="label">Puesto</div><div class="value"><?php echo htmlspecialchars($emp['puesto'] ?? '-'); ?></div></div>
                                    <div class="col-6"><div class="label">Fecha Ingreso</div><div class="value"><?php echo $emp['fecha_ingreso'] ? fecha_corta_es($emp['fecha_ingreso']) : '-'; ?></div></div>
                                    <div class="col-6"><div class="label">Empresa</div><div class="value"><?php echo $emp['empresa'] ? ucfirst($emp['empresa']) : '-'; ?></div></div>
                                    <div class="col-6"><div class="label">Periodo Pago</div><div class="value"><?php echo $emp['periodo_pago'] ? ucfirst($emp['periodo_pago']) : '-'; ?></div></div>
                                    <div class="col-6"><div class="label">Jornada</div><div class="value"><?php echo $emp['jornada'] === 'lunes_viernes' ? 'Lunes a Viernes' : 'Lunes a S&aacute;bado'; ?></div></div>
                                </div>
                                <?php if (!empty($emp['observaciones'])): ?>
                                <div class="mt-3"><div class="label">Observaciones</div><div class="value text-muted"><?php echo htmlspecialchars($emp['observaciones']); ?></div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen vacaciones -->
                    <div class="col-lg-7">
                        <?php if ($resumen_vac): ?>
                        <div class="card info-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-check me-2"></i>Vacaciones - Periodo Actual</h6>
                                <div class="row g-2 mb-3">
                                    <div class="col-sm">
                                        <div class="stat-mini bg-primary bg-opacity-10">
                                            <div class="number text-primary"><?php echo $resumen_vac['antiguedad']['anios'] ?? 0; ?></div>
                                            <div class="text text-primary">A&ntilde;os</div>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <div class="stat-mini bg-info bg-opacity-10">
                                            <div class="number text-info"><?php echo $resumen_vac['dias_correspondientes']; ?></div>
                                            <div class="text text-info">D&iacute;as LFT</div>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <div class="stat-mini bg-warning bg-opacity-10">
                                            <div class="number text-warning"><?php echo $resumen_vac['dias_tomados']; ?></div>
                                            <div class="text text-warning">Tomados</div>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <div class="stat-mini bg-success bg-opacity-10">
                                            <div class="number text-success"><?php echo $resumen_vac['dias_disponibles']; ?></div>
                                            <div class="text text-success">Disponibles</div>
                                        </div>
                                    </div>
                                    <?php if ($periodo_anterior && $periodo_anterior['dias_pendientes'] > 0): ?>
                                    <div class="col-sm">
                                        <div class="stat-mini bg-danger bg-opacity-10 border border-danger border-opacity-25">
                                            <div class="number text-danger"><?php echo $periodo_anterior['dias_pendientes']; ?></div>
                                            <div class="text text-danger">Pend. Anterior</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($resumen_vac['periodo']): ?>
                                <div class="text-muted small">
                                    <i class="bi bi-calendar3 me-1"></i>Periodo: <?php echo fecha_corta_es($resumen_vac['periodo']['inicio']); ?> &rarr; <?php echo fecha_corta_es($resumen_vac['periodo']['fin']); ?>
                                    (A&ntilde;o <?php echo $resumen_vac['periodo']['anio_periodo']; ?>&deg;)
                                </div>
                                <?php endif; ?>
                                <?php if ($periodo_anterior && $periodo_anterior['dias_pendientes'] > 0 && !empty($periodo_anterior['periodo']['fecha_vencimiento'])): ?>
                                <div class="text-danger small mt-1">
                                    <i class="bi bi-clock me-1"></i>D&iacute;as del periodo anterior vencen: <?php echo fecha_corta_es($periodo_anterior['periodo']['fecha_vencimiento']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card info-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                                <div class="text-center">
                                    <i class="bi bi-calendar-x fs-1"></i>
                                    <p class="mt-2 mb-0">Sin fecha de ingreso registrada</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historial de solicitudes -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Historial de Solicitudes</h6>
                            <span class="badge bg-secondary"><?php echo count($solicitudes_todas); ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes_todas)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-2"></i>
                            <p class="mt-2 mb-0">Sin solicitudes registradas.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-hist mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Del</th>
                                        <th>Al</th>
                                        <th class="text-center">D&iacute;as</th>
                                        <th>Estado</th>
                                        <th>Admin</th>
                                        <th>GTH</th>
                                        <th>Creada</th>
                                        <th class="text-center">Ver</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes_todas as $sol): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($sol['folio']); ?></code></td>
                                        <td><?php echo fecha_corta_es($sol['fecha_inicio']); ?></td>
                                        <td><?php echo fecha_corta_es($sol['fecha_fin']); ?></td>
                                        <td class="text-center fw-bold"><?php echo $sol['dias_solicitados']; ?></td>
                                        <td><?php echo badge_estado_vacaciones($sol['estado']); ?></td>
                                        <td><?php echo htmlspecialchars($sol['admin_nombre'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($sol['gth_nombre'] ?? '-'); ?></td>
                                        <td><?php echo fecha_corta_es($sol['fecha_creacion']); ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/ver_solicitud_vacaciones.php?id=<?php echo $sol['id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
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
    const hb=document.querySelector('.hamburger-btn'),sb=document.querySelector('.sidebar'),ov=document.querySelector('.sidebar-overlay');
    if(hb&&sb){hb.addEventListener('click',function(){sb.classList.toggle('active');if(ov)ov.classList.toggle('active');});}
    if(ov){ov.addEventListener('click',function(){sb.classList.remove('active');this.classList.remove('active');});}
    </script>
</body>
</html>