<?php
/**
 * Dashboard Inicio del módulo SEC
 * Ubicación: dashboard/salidas_envases/inicio_sec.php
 *
 * Para usuarios de Logística y Almacén de Residuos.
 * Contenido condicionado por rol.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$es_logistica_dash = ($dept === 'logistica');
$es_almacen_dash   = ($dept === 'almacen_residuos');

if (!$es_logistica_dash && !$es_almacen_dash) {
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$usuario_id     = (int)$_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento   = $_SESSION['departamento_nombre'];

$pdo = conectarDB();

// ===== ESTADÍSTICAS =====
$stats = [];
if ($es_logistica_dash) {
    $stats['enviadas']       = (int)$pdo->query("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'enviada'")->fetchColumn();
    $stats['en_transito']    = (int)$pdo->query("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'entregada'")->fetchColumn();
    $stats['cerradas_mes']   = (int)$pdo->query("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'cerrada' AND YEAR(cerrada_fecha) = YEAR(CURRENT_DATE) AND MONTH(cerrada_fecha) = MONTH(CURRENT_DATE)")->fetchColumn();
    $stats['canceladas_mes'] = (int)$pdo->query("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'cancelada' AND YEAR(cancelada_fecha) = YEAR(CURRENT_DATE) AND MONTH(cancelada_fecha) = MONTH(CURRENT_DATE)")->fetchColumn();
} else {
    $stats['pend_entrega'] = (int)$pdo->query("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'enviada'")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM salidas_envases_clientes WHERE estado = 'entregada' AND entrega_usuario_id != ?");
    $stmt->execute([$usuario_id]);
    $stats['pend_recibe'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id) FROM salidas_envases_clientes
        WHERE (entrega_usuario_id = ? AND YEAR(entrega_fecha) = YEAR(CURRENT_DATE) AND MONTH(entrega_fecha) = MONTH(CURRENT_DATE))
           OR (recibe_usuario_id  = ? AND YEAR(recibe_fecha)  = YEAR(CURRENT_DATE) AND MONTH(recibe_fecha)  = MONTH(CURRENT_DATE))
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $stats['firmadas_mes'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sec_evidencias WHERE subida_por = ? AND YEAR(fecha_subida) = YEAR(CURRENT_DATE) AND MONTH(fecha_subida) = MONTH(CURRENT_DATE)");
    $stmt->execute([$usuario_id]);
    $stats['evidencias_mes'] = (int)$stmt->fetchColumn();
}

// ===== REQUIEREN ATENCIÓN =====
$requieren_atencion = [];
if ($es_logistica_dash) {
    $stmt = $pdo->query("SELECT id, folio FROM salidas_envases_clientes WHERE estado = 'recibida' ORDER BY fecha_documento DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $requieren_atencion[] = ['id'=>$r['id'],'folio'=>$r['folio'],'texto'=>'Recibida — lista para cerrar','color'=>'success','icono'=>'bi-check2-circle'];
    }
    $stmt = $pdo->query("SELECT id, folio FROM salidas_envases_clientes WHERE estado = 'enviada' ORDER BY fecha_documento DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $requieren_atencion[] = ['id'=>$r['id'],'folio'=>$r['folio'],'texto'=>'Enviada — pendiente entrega','color'=>'primary','icono'=>'bi-hourglass-split'];
    }
} else {
    $stmt = $pdo->query("SELECT id, folio FROM salidas_envases_clientes WHERE estado = 'enviada' ORDER BY fecha_documento DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $requieren_atencion[] = ['id'=>$r['id'],'folio'=>$r['folio'],'texto'=>'Pendiente firmar Entrega','color'=>'warning','icono'=>'bi-pen'];
    }
    $stmt = $pdo->prepare("SELECT id, folio FROM salidas_envases_clientes WHERE estado = 'entregada' AND entrega_usuario_id != ? ORDER BY fecha_documento DESC LIMIT 5");
    $stmt->execute([$usuario_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $requieren_atencion[] = ['id'=>$r['id'],'folio'=>$r['folio'],'texto'=>'Pendiente firmar Recibe','color'=>'info','icono'=>'bi-check2-square'];
    }
}
$requieren_atencion = array_slice($requieren_atencion, 0, 6);

// ===== ACTIVIDAD RECIENTE =====
$stmt = $pdo->query("
    SELECT h.tipo_evento, h.fecha_creacion, s.folio, s.id AS sec_id,
           u.nombre_completo AS autor_nombre
    FROM sec_historial h
    INNER JOIN salidas_envases_clientes s ON s.id = h.sec_id
    LEFT JOIN usuarios u ON u.id = h.usuario_id
    WHERE h.tipo_evento IN ('sec_creada', 'sec_cerrada', 'sec_cancelada')
    ORDER BY h.fecha_creacion DESC
    LIMIT 8
");
$actividad_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== UNIDADES ACTIVAS =====
$unidades_activas = [];
if ($es_logistica_dash) {
    $stmt = $pdo->query("SELECT nombre, placas FROM unidades_transporte WHERE activa = 1 ORDER BY nombre");
    $unidades_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function icono_actividad_dash($tipo) {
    switch ($tipo) {
        case 'sec_creada':    return ['bi-file-earmark-plus', 'text-primary'];
        case 'sec_cerrada':   return ['bi-check-circle-fill', 'text-success'];
        case 'sec_cancelada': return ['bi-x-circle-fill',     'text-danger'];
    }
    return ['bi-circle', 'text-secondary'];
}

function tiempo_relativo_dash($fecha) {
    $ts = strtotime($fecha);
    $diff = time() - $ts;
    if ($diff < 60)     return 'hace un momento';
    if ($diff < 3600)   return 'hace ' . floor($diff/60) . ' min';
    if ($diff < 86400)  return 'hace ' . floor($diff/3600) . ' h';
    if ($diff < 172800) return 'ayer';
    if ($diff < 604800) return 'hace ' . floor($diff/86400) . ' días';
    return date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | <?php echo NOMBRE_SISTEMA; ?></title>
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
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>

    <style>
        .seccion-titulo {
            font-size: 0.85rem; font-weight: 600; color: #6c757d;
            text-transform: uppercase; letter-spacing: 1px;
            margin: 1.5rem 0 0.75rem;
            display: flex; align-items: center; gap: 8px;
        }
        .seccion-titulo::after {
            content: ''; flex: 1; height: 1px; background: #dee2e6;
        }
        .card-stat-sec {
            border: none; border-radius: 12px; background: #fff;
            transition: transform 0.15s; border-left: 4px solid;
        }
        .card-stat-sec:hover { transform: translateY(-2px); }
        .card-stat-sec.st-info      { border-left-color: #0dcaf0; }
        .card-stat-sec.st-warning   { border-left-color: #ffc107; }
        .card-stat-sec.st-success   { border-left-color: #14b8a6; }
        .card-stat-sec.st-danger    { border-left-color: #dc3545; }
        .card-stat-sec.st-secondary { border-left-color: #6c757d; }

        .stat-numero { font-size: 2.5rem; font-weight: 600; line-height: 1; margin: 0.25rem 0; }
        .stat-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        .stat-icono { width: 44px; height: 44px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .btn-accion {
            padding: 0.5rem 0.9rem; font-size: 0.85rem;
            border-radius: 8px; border: 1px solid #dee2e6;
            background: #fff; color: #212529; font-weight: 500;
            transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none;
        }
        .btn-accion:hover {
            background: #f0fdfa; border-color: #14b8a6; color: #0f766e;
            transform: translateY(-1px);
        }
        .btn-accion.btn-primary-accion {
            background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%);
            color: #fff; border-color: transparent;
        }
        .btn-accion.btn-primary-accion:hover { color: #fff; opacity: 0.92; }

        .item-atencion {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 8px;
            margin-bottom: 6px; background: #f8f9fa;
            transition: background 0.15s;
            text-decoration: none; color: inherit;
        }
        .item-atencion:hover { background: #e9ecef; color: inherit; }
        .item-atencion .icono {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .item-atencion .icono.bg-primary { background: #cfe2ff; color: #0d6efd; }
        .item-atencion .icono.bg-success { background: #d1e7dd; color: #14b8a6; }
        .item-atencion .icono.bg-warning { background: #fff3cd; color: #c48a00; }
        .item-atencion .icono.bg-info    { background: #cff4fc; color: #0dcaf0; }
        .item-atencion .folio { font-weight: 600; font-size: 0.88rem; }
        .item-atencion .desc  { font-size: 0.75rem; color: #6c757d; }

        .actividad-item {
            display: flex; gap: 10px; padding: 8px 4px;
            border-bottom: 1px dashed #e9ecef; font-size: 0.85rem;
        }
        .actividad-item:last-child { border-bottom: none; }
        .actividad-item .icono { font-size: 1.05rem; flex-shrink: 0; margin-top: 2px; }
        .actividad-item .cuerpo { flex: 1; }
        .actividad-item .autor { font-weight: 500; color: #212529; }
        .actividad-item .folio-link { font-weight: 600; color: #14b8a6; }
        .actividad-item .cuando { color: #6c757d; font-size: 0.75rem; }

        .unidad-chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: #f0fdfa; border: 1px solid #99f6e4;
            padding: 8px 12px; border-radius: 10px;
            margin: 4px 4px 0 0; font-size: 0.85rem;
        }
        .unidad-chip .nombre { font-weight: 600; color: #0f766e; }
        .unidad-chip .placas { font-family: 'Courier New', monospace; color: #6c757d; font-size: 0.78rem; }

        .empty-state { text-align: center; padding: 2rem 1rem; color: #6c757d; }
        .empty-state i { font-size: 2rem; opacity: 0.5; }

        .bienvenida h2 { font-size: 1.75rem; font-weight: 600; margin: 0; }
        .bienvenida .subtitle { font-size: 0.9rem; color: #6c757d; margin: 4px 0 0; }
        .badge-rol {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px;
            background: #f0fdfa; color: #0f766e;
            font-size: 0.85rem; font-weight: 600;
        }

        .badge-rol {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px;
            background: #f0fdfa; color: #0f766e;
            font-size: 0.85rem; font-weight: 600;
        }

        /* ============ MODO OSCURO ============ */
        [data-theme="dark"] .card {
            background: #2d3339; color: #e0e6ed; border-color: #3d434a;
        }
        [data-theme="dark"] .card-header {
            background: #262c31; color: #e0e6ed; border-color: #3d434a;
        }
        [data-theme="dark"] .card-stat-sec { background: #2d3339; }
        [data-theme="dark"] .stat-numero  { color: #e0e6ed; }
        [data-theme="dark"] .stat-label   { color: #a3adb8; }
        [data-theme="dark"] .btn-accion {
            background: #2d3339; color: #e0e6ed; border-color: #3d434a;
        }
        [data-theme="dark"] .btn-accion:hover {
            background: #0f766e; color: #fff; border-color: #14b8a6;
        }
        [data-theme="dark"] .btn-accion.btn-primary-accion { color: #fff; }
        [data-theme="dark"] .item-atencion { background: #262c31; color: #e0e6ed; }
        [data-theme="dark"] .item-atencion:hover { background: #33393f; color: #e0e6ed; }
        [data-theme="dark"] .item-atencion .folio { color: #e0e6ed; }
        [data-theme="dark"] .item-atencion .desc  { color: #a3adb8; }
        [data-theme="dark"] .actividad-item { border-color: #3d434a; }
        [data-theme="dark"] .actividad-item .autor    { color: #e0e6ed; }
        [data-theme="dark"] .actividad-item .cuando   { color: #a3adb8; }
        [data-theme="dark"] .unidad-chip {
            background: rgba(20, 184, 166, 0.1);
            border-color: #14b8a6;
        }
        [data-theme="dark"] .unidad-chip .nombre { color: #14b8a6; }
        [data-theme="dark"] .unidad-chip .placas { color: #a3adb8; }
        [data-theme="dark"] .seccion-titulo { color: #a3adb8; }
        [data-theme="dark"] .seccion-titulo::after { background: #3d434a; }
        [data-theme="dark"] .empty-state { color: #a3adb8; }
        [data-theme="dark"] .bienvenida h2 { color: #e0e6ed; }
        [data-theme="dark"] .bienvenida .subtitle { color: #a3adb8; }
        [data-theme="dark"] .badge-rol {
            background: rgba(20, 184, 166, 0.15); color: #14b8a6;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php include __DIR__ . '/../../includes/sidebar/sidebar_sec.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="d-flex justify-content-between align-items-center flex-wrap mb-4 bienvenida">
                    <div>
                        <h2>¡Hola, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h2>
                        <p class="subtitle">
                            <i class="bi bi-calendar3"></i>
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn-anuncios-trigger" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
                            <i class="bi bi-megaphone-fill"></i>
                            <span class="badge-count" id="anunciosBadge"></span>
                        </button>
                        <span class="badge-rol">
                            <i class="bi bi-truck"></i>
                            <?php echo htmlspecialchars($departamento); ?>
                        </span>
                    </div>
                </div>

                <?php
                // Ocultamos únicamente el mensaje de bienvenida del login (redundante con el saludo
                // de arriba), pero seguimos mostrando cualquier otra alerta real.
                $alerta_html = mostrar_alerta();
                if (mb_stripos($alerta_html, 'bienvenido') === false) {
                    echo $alerta_html;
                }
                ?>

                <div class="seccion-titulo">
                    <i class="bi bi-graph-up"></i> Estadísticas de Salidas de Envases
                </div>

                <div class="row g-3 mb-2">
                    <?php if ($es_logistica_dash): ?>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-info h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#cff4fc;color:#0dcaf0;"><i class="bi bi-envelope-check"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['enviadas']; ?></div>
                                        <p class="stat-label">Enviadas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-warning h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#fff3cd;color:#c48a00;"><i class="bi bi-truck"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['en_transito']; ?></div>
                                        <p class="stat-label">En tránsito</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-success h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#d1fae5;color:#0f766e;"><i class="bi bi-check-circle"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['cerradas_mes']; ?></div>
                                        <p class="stat-label">Cerradas este mes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-danger h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#f8d7da;color:#dc3545;"><i class="bi bi-x-circle"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['canceladas_mes']; ?></div>
                                        <p class="stat-label">Canceladas este mes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-warning h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#fff3cd;color:#c48a00;"><i class="bi bi-pen"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['pend_entrega']; ?></div>
                                        <p class="stat-label">Pend. Entrega</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-info h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#cff4fc;color:#0dcaf0;"><i class="bi bi-check2-square"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['pend_recibe']; ?></div>
                                        <p class="stat-label">Pend. Recibe</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-success h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#d1fae5;color:#0f766e;"><i class="bi bi-file-earmark-check"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['firmadas_mes']; ?></div>
                                        <p class="stat-label">Firmadas este mes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card card-stat-sec st-secondary h-100">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="stat-icono" style="background:#e9ecef;color:#6c757d;"><i class="bi bi-images"></i></div>
                                    <div>
                                        <div class="stat-numero"><?php echo $stats['evidencias_mes']; ?></div>
                                        <p class="stat-label">Evidencias este mes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="seccion-titulo">
                    <i class="bi bi-lightning-charge"></i> Acciones rápidas
                </div>

                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($es_logistica_dash): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/nueva_sec.php" class="btn-accion btn-primary-accion">
                                    <i class="bi bi-plus-circle"></i> Nueva SEC
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/salidas_envases.php" class="btn-accion">
                                <i class="bi bi-list-ul"></i> Salidas de Envases para Clientes
                            </a>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/disponibilidad_unidades.php" class="btn-accion">
                                <i class="bi bi-calendar-event"></i> Disponibilidad
                            </a>
                            <?php if ($es_logistica_dash): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades_transporte.php" class="btn-accion">
                                    <i class="bi bi-truck-front"></i> Unidades de Transporte
                                </a>
                            <?php endif; ?>
                            <a href="#" class="btn-accion" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                <i class="bi bi-headset"></i> Nueva Solicitud de Atención
                            </a>
                            <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="btn-accion">
                                <i class="bi bi-clipboard-check"></i> Órdenes de Servicio para Mantenimiento
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-exclamation-circle text-warning"></i> Requieren atención</h6>
                                <span class="badge bg-secondary"><?php echo count($requieren_atencion); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($requieren_atencion)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-check-circle"></i>
                                        <p class="mb-0 mt-2">Nada pendiente por ahora.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($requieren_atencion as $r): ?>
                                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/ver_sec.php?id=<?php echo (int)$r['id']; ?>" class="item-atencion">
                                            <div class="icono bg-<?php echo $r['color']; ?>">
                                                <i class="bi <?php echo $r['icono']; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="folio"><?php echo htmlspecialchars($r['folio']); ?></div>
                                                <div class="desc"><?php echo htmlspecialchars($r['texto']); ?></div>
                                            </div>
                                            <i class="bi bi-chevron-right text-muted"></i>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history text-muted"></i> Actividad reciente</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($actividad_reciente)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-clock"></i>
                                        <p class="mb-0 mt-2">Sin actividad todavía.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($actividad_reciente as $act): ?>
                                        <?php $ic = icono_actividad_dash($act['tipo_evento']); ?>
                                        <div class="actividad-item">
                                            <i class="bi <?php echo $ic[0]; ?> <?php echo $ic[1]; ?> icono"></i>
                                            <div class="cuerpo">
                                                <div>
                                                    <span class="autor"><?php echo htmlspecialchars($act['autor_nombre'] ?? 'Sistema'); ?></span>
                                                    <?php
                                                        switch ($act['tipo_evento']) {
                                                            case 'sec_creada':    echo ' creó '; break;
                                                            case 'sec_cerrada':   echo ' cerró '; break;
                                                            case 'sec_cancelada': echo ' canceló '; break;
                                                        }
                                                    ?>
                                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/ver_sec.php?id=<?php echo (int)$act['sec_id']; ?>" class="folio-link text-decoration-none">
                                                        <?php echo htmlspecialchars($act['folio']); ?>
                                                    </a>
                                                </div>
                                                <div class="cuando"><?php echo tiempo_relativo_dash($act['fecha_creacion']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($es_logistica_dash): ?>
                <div class="seccion-titulo">
                    <i class="bi bi-truck"></i> Unidades activas
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (empty($unidades_activas)): ?>
                            <div class="empty-state">
                                <i class="bi bi-truck"></i>
                                <p class="mb-0 mt-2">Sin unidades activas registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($unidades_activas as $u): ?>
                                    <div class="unidad-chip">
                                        <i class="bi bi-truck-front" style="color:#0f766e;"></i>
                                        <div>
                                            <div class="nombre"><?php echo htmlspecialchars($u['nombre']); ?></div>
                                            <div class="placas"><?php echo htmlspecialchars($u['placas']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/../../solicitudes/modal_crear.php'; ?>

    <!-- Modal de Anuncios -->
    <?php include __DIR__ . '/../../includes/anuncios/modal_anuncios.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

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