<?php
/**
 * Ver Detalles de Equipo v2.0
 * dashboard/sistemas/ti_sistemas/ver_equipo.php
 * 
 * Muestra info del inventario + datos de acceso si existen + perif&eacute;ricos vinculados
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta secci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$equipo_id = intval($_GET['id'] ?? 0);
if (!$equipo_id) {
    establecer_alerta('error', 'ID de equipo no v&aacute;lido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

$pdo = conectarDB();

$sql = "SELECT ie.*, d.nombre as departamento_nombre,
               u1.nombre_completo as registrado_por_nombre,
               ua.nombre_completo as usuario_asignado_nombre
        FROM inventario_equipos ie
        LEFT JOIN departamentos d ON ie.departamento_id = d.id
        LEFT JOIN usuarios u1 ON ie.registrado_por = u1.id
        LEFT JOIN usuarios ua ON ie.usuario_asignado_id = ua.id
        WHERE ie.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    establecer_alerta('error', 'Equipo no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/inventario.php');
    exit;
}

// Obtener datos de acceso vinculados por hostname
$acceso = null;
if ($equipo['hostname']) {
    $stmt_acc = $pdo->prepare("SELECT ae.*, d.nombre as departamento_nombre FROM acceso_equipos ae LEFT JOIN departamentos d ON ae.departamento_id = d.id WHERE ae.hostname = ?");
    $stmt_acc->execute([$equipo['hostname']]);
    $acceso = $stmt_acc->fetch(PDO::FETCH_ASSOC);
}

// Obtener perif&eacute;ricos vinculados a este equipo (via personal_asignado)
$perifericos = [];
if ($equipo['hostname']) {
    $stmt_per = $pdo->prepare("SELECT id, hostname, tipo_equipo, marca, modelo, estado FROM inventario_equipos WHERE personal_asignado = ? ORDER BY tipo_equipo");
    $stmt_per->execute([$equipo['hostname']]);
    $perifericos = $stmt_per->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener historial de mantenimientos
$stmt_mant = $pdo->prepare("SELECT sm.*, u.nombre_completo as solicitante_nombre FROM solicitudes_mantenimiento_ti sm LEFT JOIN usuarios u ON sm.usuario_id = u.id WHERE sm.equipo_id = ? ORDER BY sm.fecha_solicitud DESC LIMIT 10");
$stmt_mant->execute([$equipo_id]);
$mantenimientos = $stmt_mant->fetchAll(PDO::FETCH_ASSOC);

$tipos_config = [
    'all_in_one'   => ['nombre' => 'All In One',    'icono' => 'bi-display',      'color' => 'primary'],
    'pc'           => ['nombre' => 'PC',             'icono' => 'bi-pc-display',   'color' => 'primary'],
    'laptop'       => ['nombre' => 'Laptop',         'icono' => 'bi-laptop',       'color' => 'info'],
    'monitor'      => ['nombre' => 'Monitor',        'icono' => 'bi-display',      'color' => 'info'],
    'mouse'        => ['nombre' => 'Mouse',          'icono' => 'bi-mouse',        'color' => 'secondary'],
    'teclado'      => ['nombre' => 'Teclado',        'icono' => 'bi-keyboard',     'color' => 'secondary'],
    'impresora'    => ['nombre' => 'Impresora',      'icono' => 'bi-printer',      'color' => 'purple'],
    'access_point' => ['nombre' => 'Access Point',   'icono' => 'bi-wifi',         'color' => 'warning'],
    'switch'       => ['nombre' => 'Switch',         'icono' => 'bi-diagram-3',    'color' => 'warning'],
    'camara'       => ['nombre' => 'C&aacute;mara',  'icono' => 'bi-camera-video', 'color' => 'success'],
    'celular'      => ['nombre' => 'Celular',        'icono' => 'bi-phone',        'color' => 'orange'],
    'computadora'  => ['nombre' => 'Computadora',    'icono' => 'bi-pc-display',   'color' => 'primary'],
    'telefono'     => ['nombre' => 'Tel&eacute;fono', 'icono' => 'bi-telephone',   'color' => 'orange'],
];

$tc = $tipos_config[$equipo['tipo_equipo']] ?? ['nombre' => ucfirst($equipo['tipo_equipo']), 'icono' => 'bi-question-circle', 'color' => 'secondary'];

$badges_estado = ['activo' => 'bg-success', 'inactivo' => 'bg-secondary', 'en_reparacion' => 'bg-warning text-dark', 'dado_de_baja' => 'bg-danger'];
$nombres_estado = ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'en_reparacion' => 'En Reparaci&oacute;n', 'dado_de_baja' => 'Dado de Baja'];

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($equipo['hostname'] ?: $equipo['codigo_interno']); ?> - Inventario TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <style>
        .detail-label { font-size: 0.78rem; color: #6c757d; margin-bottom: 0.1rem; text-transform: uppercase; letter-spacing: 0.3px; }
        .detail-value { font-weight: 500; margin-bottom: 0.8rem; }
        .hostname-display { font-family: 'Courier New', monospace; font-size: 1.5rem; font-weight: 700; }
        .text-purple { color: #6f42c1 !important; }
        .text-orange { color: #fd7e14 !important; }
        .section-title { font-weight: 600; font-size: 0.95rem; padding-bottom: 0.4rem; border-bottom: 2px solid #e9ecef; margin-bottom: 1rem; }
        .periferico-item { display: flex; align-items: center; gap: 0.8rem; padding: 0.6rem; border-radius: 8px; background: #f8f9fa; margin-bottom: 0.5rem; }
        .acceso-field { background: #f8f9fa; padding: 0.5rem 0.8rem; border-radius: 6px; font-family: monospace; font-size: 0.9rem; }
        .password-field { cursor: pointer; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <i class="bi <?php echo $tc['icono']; ?> text-<?php echo $tc['color']; ?>" style="font-size:2.5rem;"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 hostname-display text-<?php echo $tc['color']; ?>">
                                <?php echo htmlspecialchars($equipo['hostname'] ?: $equipo['codigo_interno']); ?>
                            </h4>
                            <span class="badge <?php echo $badges_estado[$equipo['estado']] ?? 'bg-secondary'; ?> me-1">
                                <?php echo $nombres_estado[$equipo['estado']] ?? $equipo['estado']; ?>
                            </span>
                            <small class="text-muted"><?php echo $tc['nombre']; ?></small>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $equipo_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>Editar
                        </a>
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </a>
                    </div>
                </div>

                <?php if (function_exists('mostrar_alerta')) mostrar_alerta(); ?>

                <div class="row g-4">
                    <!-- Columna principal -->
                    <div class="col-lg-8">
                        <!-- Informaci&oacute;n del equipo -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="section-title"><i class="bi bi-info-circle me-2"></i>Informaci&oacute;n del Equipo</div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="detail-label">Marca</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['marca'] ?: '—'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Modelo</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['modelo'] ?: '—'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">N&uacute;mero de Serie</div>
                                        <div class="detail-value"><code><?php echo htmlspecialchars($equipo['numero_serie'] ?: '—'); ?></code></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Departamento</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['departamento_nombre'] ?? '—'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Ubicaci&oacute;n</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($equipo['ubicacion'] ?: '—'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Personal Asignado</div>
                                        <div class="detail-value">
                                            <?php if ($equipo['personal_asignado']): ?>
                                                <code><?php echo htmlspecialchars($equipo['personal_asignado']); ?></code>
                                            <?php elseif ($equipo['usuario_asignado_nombre']): ?>
                                                <?php echo htmlspecialchars($equipo['usuario_asignado_nombre']); ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($equipo['fecha_adquisicion']): ?>
                                    <div class="col-md-4">
                                        <div class="detail-label">Fecha Adquisici&oacute;n</div>
                                        <div class="detail-value"><?php echo date('d/m/Y', strtotime($equipo['fecha_adquisicion'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($equipo['notas']): ?>
                                    <div class="col-12">
                                        <div class="detail-label">Notas</div>
                                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($equipo['notas'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Datos de Acceso (si existen) -->
                        <?php if ($acceso): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="section-title"><i class="bi bi-key me-2 text-warning"></i>Datos de Acceso</div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="detail-label">Direcci&oacute;n IP</div>
                                        <div class="detail-value">
                                            <span class="acceso-field"><?php echo htmlspecialchars($acceso['direccion_ip'] ?: '—'); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">Contrase&ntilde;a</div>
                                        <div class="detail-value">
                                            <span class="acceso-field password-field" onclick="togglePassword(this)" 
                                                  data-pass="<?php echo htmlspecialchars($acceso['contrasena_equipo'] ?? ''); ?>">
                                                &#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;
                                            </span>
                                            <small class="text-muted d-block mt-1">Clic para mostrar/ocultar</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-label">whoami</div>
                                        <div class="detail-value">
                                            <span class="acceso-field"><?php echo htmlspecialchars($acceso['whoami'] ?: '—'); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Usuario Asignado</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($acceso['usuario_asignado_nombre'] ?: '—'); ?></div>
                                    </div>
                                    <?php if ($acceso['comentarios']): ?>
                                    <div class="col-12">
                                        <div class="detail-label">Comentarios</div>
                                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($acceso['comentarios'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Perif&eacute;ricos vinculados -->
                        <?php if (!empty($perifericos)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="section-title"><i class="bi bi-diagram-2 me-2 text-info"></i>Perif&eacute;ricos Vinculados (<?php echo count($perifericos); ?>)</div>
                                <?php foreach ($perifericos as $per): 
                                    $ptc = $tipos_config[$per['tipo_equipo']] ?? ['nombre' => $per['tipo_equipo'], 'icono' => 'bi-device-ssd', 'color' => 'secondary'];
                                ?>
                                    <div class="periferico-item">
                                        <i class="bi <?php echo $ptc['icono']; ?> text-<?php echo $ptc['color']; ?>" style="font-size:1.2rem;"></i>
                                        <div class="flex-grow-1">
                                            <strong style="font-family:monospace;"><?php echo htmlspecialchars($per['hostname']); ?></strong>
                                            <small class="text-muted ms-1"><?php echo $ptc['nombre']; ?></small>
                                            <?php if ($per['marca'] || $per['modelo']): ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars(trim($per['marca'] . ' ' . $per['modelo'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge <?php echo $badges_estado[$per['estado']] ?? 'bg-secondary'; ?>" style="font-size:0.7rem;">
                                            <?php echo $nombres_estado[$per['estado']] ?? $per['estado']; ?>
                                        </span>
                                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $per['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Historial de mantenimientos -->
                        <?php if (!empty($mantenimientos)): ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="section-title"><i class="bi bi-tools me-2"></i>Historial de Mantenimientos</div>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr><th>Fecha</th><th>Solicitante</th><th>Descripci&oacute;n</th><th>Estado</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mantenimientos as $m): ?>
                                            <tr>
                                                <td><small><?php echo date('d/m/Y', strtotime($m['fecha_solicitud'])); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($m['solicitante_nombre'] ?? '—'); ?></small></td>
                                                <td><small><?php echo htmlspecialchars(mb_substr($m['descripcion'] ?? '', 0, 80)); ?></small></td>
                                                <td><span class="badge bg-<?php echo ($m['estado'] === 'completado') ? 'success' : (($m['estado'] === 'pendiente') ? 'warning' : 'info'); ?>" style="font-size:0.7rem;"><?php echo ucfirst($m['estado']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Panel lateral -->
                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-body text-center">
                                <i class="bi <?php echo $tc['icono']; ?> text-<?php echo $tc['color']; ?>" style="font-size:4rem;"></i>
                                <h5 class="mt-2 mb-1" style="font-family:monospace;">
                                    <?php echo htmlspecialchars($equipo['hostname'] ?: $equipo['codigo_interno']); ?>
                                </h5>
                                <p class="text-muted mb-0"><?php echo $tc['nombre']; ?></p>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Acciones R&aacute;pidas</h6>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $equipo_id; ?>" 
                                   class="btn btn-outline-primary w-100 mb-2 btn-sm">
                                    <i class="bi bi-pencil me-1"></i>Editar Equipo
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php?equipo_id=<?php echo $equipo_id; ?>" 
                                   class="btn btn-outline-warning w-100 mb-2 btn-sm">
                                    <i class="bi bi-tools me-1"></i>Solicitar Mantenimiento
                                </a>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body" style="font-size:0.82rem;">
                                <h6 class="fw-bold mb-3">Registro</h6>
                                <div class="detail-label">Registrado por</div>
                                <div class="detail-value"><?php echo htmlspecialchars($equipo['registrado_por_nombre'] ?? '—'); ?></div>
                                <div class="detail-label">Fecha registro</div>
                                <div class="detail-value"><?php echo $equipo['fecha_registro'] ? date('d/m/Y H:i', strtotime($equipo['fecha_registro'])) : '—'; ?></div>
                                <?php if ($equipo['fecha_actualizacion']): ?>
                                <div class="detail-label">&Uacute;ltima actualizaci&oacute;n</div>
                                <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($equipo['fecha_actualizacion'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <script>
    function togglePassword(el) {
        if (el.dataset.showing === 'true') {
            el.textContent = '\u25CF\u25CF\u25CF\u25CF\u25CF\u25CF\u25CF\u25CF';
            el.dataset.showing = 'false';
        } else {
            el.textContent = el.dataset.pass || '—';
            el.dataset.showing = 'true';
        }
    }
    </script>
</body>
</html>