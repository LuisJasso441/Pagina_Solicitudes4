<?php
/**
 * Listado de TODOS los Empleados
 * dashboard/gth/empleados/listar_empleados.php
 * 
 * Fuente unica: empleados_gth (tabla maestra)
 * LEFT JOIN usuarios para saber si tiene cuenta en plataforma
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
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$filtro_depto = intval($_GET['departamento'] ?? 0);
$busqueda = trim($_GET['q'] ?? '');

// Query unica desde empleados_gth con LEFT JOIN a usuarios
$sql = "
    SELECT e.*, d.nombre AS departamento_nombre,
           u.id AS uid, u.usuario AS username
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.activo = 1
";
$params = [];

if ($filtro_depto > 0) {
    $sql .= " AND e.departamento_id = ?";
    $params[] = $filtro_depto;
}
if ($busqueda !== '') {
    $sql .= " AND (e.nombre_completo LIKE ? OR e.no_nomina LIKE ? OR e.puesto LIKE ?)";
    $params[] = "%{$busqueda}%";
    $params[] = "%{$busqueda}%";
    $params[] = "%{$busqueda}%";
}
$sql .= " ORDER BY e.nombre_completo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular dias de vacaciones
foreach ($empleados as &$emp) {
    $emp['dias_tomados'] = 0;
    $emp['dias_pendientes'] = 0;
    if (!empty($emp['fecha_ingreso'])) {
        if ($emp['usuario_id']) {
            // Vinculado a usuario: usar funcion existente
            $resumen = obtener_resumen_vacaciones($pdo, $emp['usuario_id']);
            $emp['dias_tomados'] = $resumen['dias_tomados'] ?? 0;
            $emp['dias_pendientes'] = $resumen['dias_disponibles'] ?? 0;
        } else {
            // Sin cuenta: calcular desde solicitudes manuales
            $ant = calcular_antiguedad($emp['fecha_ingreso']);
            $anio_p = max(1, $ant['anios_completos'] > 0 ? $ant['anios_completos'] + 1 : 1);
            $dias_lft = dias_vacaciones_lft($anio_p);
            $stmt_sol = $pdo->prepare("SELECT COALESCE(SUM(dias_solicitados), 0) FROM solicitudes_vacaciones WHERE es_manual = 1 AND nombre_manual = ? AND estado NOT IN ('cancelada', 'rechazada_admin', 'rechazada_gth')");
            $stmt_sol->execute([$emp['nombre_completo']]);
            $dt = (int)$stmt_sol->fetchColumn();
            $emp['dias_tomados'] = $dt;
            $emp['dias_pendientes'] = max(0, $dias_lft - $dt);
        }
    }
}
unset($emp);

$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total = count($empleados);
$total_resimex = 0; $total_carganova = 0; $total_con_pendientes = 0;
foreach ($empleados as $e) {
    if (($e['empresa'] ?? '') === 'resimex') $total_resimex++;
    if (($e['empresa'] ?? '') === 'carganova') $total_carganova++;
    if ($e['dias_pendientes'] > 0) $total_con_pendientes++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - GTH</title>
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
        .stat-card { border-radius:12px; padding:15px 20px; text-align:center; }
        .stat-card .stat-number { font-size:1.8rem; font-weight:700; }
        .stat-card .stat-label { font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .table-empleados th { font-size:0.78rem; text-transform:uppercase; letter-spacing:0.3px; }
        .table-empleados td { vertical-align:middle; font-size:0.86rem; }
        .badge-jornada { font-size:0.72rem; }
        .dias-ok { color:#198754; font-weight:700; }
        .dias-cero { color:#dc3545; font-weight:700; }
        .badge-plataforma { font-size:0.65rem; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php'; ?>
        <main class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-person-vcard me-2"></i>Empleados</h1>
                            <p class="text-muted mb-0" style="font-size:0.82rem">Todos los empleados de la empresa</p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/crear_empleado.php" class="btn btn-success">
                            <i class="bi bi-person-plus me-1"></i> Nuevo Empleado
                        </a>
                    </div>
                </div>

                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show"><?php echo $alerta['mensaje']; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                            <div class="stat-number text-primary"><?php echo $total; ?></div>
                            <div class="stat-label text-primary">Total Empleados</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="stat-number text-success"><?php echo $total_resimex; ?></div>
                            <div class="stat-label text-success">Resimex</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info bg-opacity-10 border border-info border-opacity-25">
                            <div class="stat-number text-info"><?php echo $total_carganova; ?></div>
                            <div class="stat-label text-info">Carganova</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning bg-opacity-10 border border-warning border-opacity-25">
                            <div class="stat-number text-warning"><?php echo $total_con_pendientes; ?></div>
                            <div class="stat-label text-warning">Con D&iacute;as Pendientes</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body py-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Buscar</label>
                                <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, nomina, puesto..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Departamento</label>
                                <select name="departamento" class="form-select form-select-sm">
                                    <option value="0">Todos</option>
                                    <?php foreach ($departamentos as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $filtro_depto == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrar</button></div>
                            <div class="col-md-2"><a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a></div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <?php if (empty($empleados)): ?>
                        <div class="text-center py-5 text-muted"><i class="bi bi-person-x fs-1"></i><p class="mt-2">No se encontraron empleados.</p></div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-empleados mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>No. Nomina</th>
                                        <th>Puesto</th>
                                        <th>Departamento</th>
                                        <th>Ingreso</th>
                                        <th>Periodo</th>
                                        <th>Empresa</th>
                                        <th>Jornada</th>
                                        <th class="text-center">D. Tomados</th>
                                        <th class="text-center">D. Pendientes</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $idx => $emp): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['nombre_completo']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['no_nomina'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['puesto'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['departamento_nombre'] ?? '-'); ?></td>
                                        <td><?php echo $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '-'; ?></td>
                                        <td><?php echo $emp['periodo_pago'] ? ucfirst($emp['periodo_pago']) : '-'; ?></td>
                                        <td><?php echo $emp['empresa'] ? ucfirst($emp['empresa']) : '-'; ?></td>
                                        <td>
                                            <?php if ($emp['jornada'] === 'lunes_viernes'): ?>
                                                <span class="badge bg-info badge-jornada">L-V</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-jornada">L-S</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="<?php echo $emp['dias_tomados'] > 0 ? 'dias-ok' : ''; ?>"><?php echo $emp['dias_tomados']; ?></span></td>
                                        <td class="text-center"><span class="<?php echo $emp['dias_pendientes'] > 0 ? 'dias-ok' : 'dias-cero'; ?>"><?php echo $emp['dias_pendientes']; ?></span></td>
                                        <td class="text-center">
                                            <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/editar_empleado.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
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