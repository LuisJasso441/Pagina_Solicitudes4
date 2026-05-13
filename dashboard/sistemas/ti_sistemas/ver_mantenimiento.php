<?php
/**
 * Ver Detalles de Solicitud de Mantenimiento (Nuevo Flujo + Firma)
 * dashboard/sistemas/ti_sistemas/ver_mantenimiento.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$solicitud_id = intval($_GET['id'] ?? 0);
if (!$solicitud_id) {
    establecer_alerta('error', 'ID de solicitud no válido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

$pdo = conectarDB();

$departamento     = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti            = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

// ---------- Obtener datos de la solicitud ----------
$sql = "SELECT sm.*, 
               u.nombre_completo  AS solicitante_nombre,
               u.usuario          AS solicitante_usuario,
               d.nombre           AS departamento_nombre,
               ua.nombre_completo AS atendido_por_nombre,
               ie.codigo_interno  AS equipo_codigo,
               ie.hostname        AS equipo_hostname,
               ie.marca           AS equipo_marca,
               ie.modelo          AS equipo_modelo,
               ie.numero_serie    AS equipo_serie,
               ie.ubicacion       AS equipo_ubicacion
        FROM solicitudes_mantenimiento_ti sm
        LEFT JOIN usuarios u       ON sm.usuario_id = u.id
        LEFT JOIN departamentos d  ON sm.departamento_id = d.id
        LEFT JOIN usuarios ua      ON sm.atendido_por = ua.id
        LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
        WHERE sm.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$solicitud_id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    establecer_alerta('error', 'Solicitud no encontrada.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

if (!$es_ti && $solicitud['usuario_id'] != $_SESSION['usuario_id']) {
    establecer_alerta('error', 'No tiene permisos para ver esta solicitud.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos.php');
    exit;
}

// ---------- Historial ----------
$sql_historial = "SELECT h.*, u.nombre_completo AS usuario_nombre
                  FROM historial_mantenimientos_ti h
                  LEFT JOIN usuarios u ON h.usuario_id = u.id
                  WHERE h.solicitud_id = ?
                  ORDER BY h.fecha_cambio DESC";
$stmt = $pdo->prepare($sql_historial);
$stmt->execute([$solicitud_id]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Evidencias ----------
$sql_evid = "SELECT e.*, u.nombre_completo AS subido_por_nombre
             FROM evidencias_mantenimiento_ti e
             LEFT JOIN usuarios u ON e.subido_por = u.id
             WHERE e.solicitud_id = ?
             ORDER BY e.fecha_subida DESC";
$stmt = $pdo->prepare($sql_evid);
$stmt->execute([$solicitud_id]);
$evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====================================================================
// CONFIGURACIONES DE VISUALIZACION
// ====================================================================
$iconos_tipo = [
    'all_in_one'   => 'bi-display text-primary',
    'pc'           => 'bi-pc-display text-primary',
    'laptop'       => 'bi-laptop text-primary',
    'computadora'  => 'bi-pc-display text-primary',
    'monitor'      => 'bi-display text-info',
    'mouse'        => 'bi-mouse text-info',
    'teclado'      => 'bi-keyboard text-info',
    'impresora'    => 'bi-printer text-secondary',
    'access_point' => 'bi-wifi text-warning',
    'switch'       => 'bi-diagram-3 text-warning',
    'camara'       => 'bi-camera-video text-success',
    'celular'      => 'bi-phone text-success',
    'telefono'     => 'bi-telephone text-success',
    'pantalla_tv'  => 'bi-tv text-secondary',
    'nobreak'      => 'bi-battery-charging text-secondary',
];
$tipos_nombres = [
    'all_in_one' => 'All in One', 'pc' => 'PC', 'laptop' => 'Laptop',
    'computadora' => 'Computadora', 'monitor' => 'Monitor', 'mouse' => 'Mouse',
    'teclado' => 'Teclado', 'impresora' => 'Impresora', 'access_point' => 'Access Point',
    'switch' => 'Switch', 'camara' => 'Cámara', 'celular' => 'Celular',
    'telefono' => 'Teléfono', 'pantalla_tv' => 'Pantalla TV', 'nobreak' => 'Nobreak',
];
$grupos_nombres = [
    'computo' => 'Cómputo', 'perifericos' => 'Periféricos',
    'impresoras' => 'Impresoras', 'red' => 'Red', 'otros' => 'Otros',
];
$badges_estado = [
    'pendiente'  => 'bg-warning text-dark', 'en_proceso' => 'bg-primary',
    'finalizada' => 'bg-success', 'cerrada' => 'bg-secondary', 'cancelada' => 'bg-danger',
];
$nombres_estado = [
    'pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso',
    'finalizada' => 'Finalizada', 'cerrada' => 'Cerrada', 'cancelada' => 'Cancelada',
];
$badges_prioridad = ['alta' => 'bg-danger', 'media' => 'bg-warning text-dark', 'baja' => 'bg-success'];
$nombres_prioridad = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];

$tipo_equipo_actual  = $solicitud['tipo_equipo'] ?? '';
$estado_actual       = $solicitud['estado'] ?? 'pendiente';
$grupo_actual        = $solicitud['grupo_equipo'] ?? '';
$prioridad_actual    = $solicitud['prioridad'] ?? 'media';
$tipo_mant_actual    = $solicitud['tipo_mantenimiento'] ?? '';

$badge_estado_class  = $badges_estado[$estado_actual]   ?? 'bg-secondary';
$nombre_estado       = $nombres_estado[$estado_actual]  ?? ucfirst($estado_actual);
$icono_tipo          = $iconos_tipo[$tipo_equipo_actual] ?? 'bi-device-hdd text-secondary';
$tipo_nombre         = $tipos_nombres[$tipo_equipo_actual] ?? ($tipo_equipo_actual ?: 'Sin especificar');
$grupo_nombre        = $grupos_nombres[$grupo_actual]   ?? ucfirst($grupo_actual);

$puede_editar = $es_ti && in_array($estado_actual, ['pendiente', 'en_proceso', 'finalizada']);
$puede_subir_evidencias = $puede_editar;

// Capacidad de firma del usuario destinatario
$es_destinatario = ($solicitud['usuario_id'] == $_SESSION['usuario_id']);
$puede_firmar = $es_destinatario
                && $estado_actual === 'finalizada'
                && empty($solicitud['firma_usuario']);

// Si ya firmaron, mostrar la firma
$ya_firmada = !empty($solicitud['firma_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($solicitud['folio']); ?> - Mantenimiento TI</title>
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
        .detail-card { border-left: 4px solid #6c757d; }
        .detail-card.pendiente  { border-left-color: #ffc107; }
        .detail-card.en_proceso { border-left-color: #0d6efd; }
        .detail-card.finalizada { border-left-color: #198754; }
        .detail-card.cerrada    { border-left-color: #6c757d; }
        .detail-card.cancelada  { border-left-color: #dc3545; }

        .detail-label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 0.15rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .detail-value { font-weight: 500; color: #212529; }

        .timeline { position: relative; padding-left: 1.25rem; }
        .timeline::before {
            content: ''; position: absolute; left: 6px; top: 0; bottom: 0;
            width: 2px; background: #dee2e6;
        }
        .timeline-item { position: relative; padding-bottom: 1rem; }
        .timeline-item::before {
            content: ''; position: absolute; left: -1.21rem; top: 4px;
            width: 14px; height: 14px; border-radius: 50%;
            background: #fff; border: 2px solid #0d6efd;
        }
        .timeline-item.last::before { border-color: #198754; }
        .timeline-item.cancel::before { border-color: #dc3545; }

        .evidencia-card {
            border: 1px solid #dee2e6; border-radius: 6px;
            padding: 0.5rem; background: #fff;
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.5rem;
        }
        .evidencia-thumb {
            width: 60px; height: 60px;
            border-radius: 4px; background: #f8f9fa;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
        }
        .evidencia-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .evidencia-thumb i { font-size: 1.5rem; color: #6c757d; }
        .evidencia-info { flex: 1; min-width: 0; }
        .evidencia-name {
            font-size: 0.85rem; font-weight: 500; color: #212529;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ====== Firma ====== */
        .firma-banner {
            background: linear-gradient(90deg, #fff3cd, #ffeaa7);
            border-left: 4px solid #f59e0b;
            padding: 0.75rem 1rem;
            border-radius: 6px;
        }
        .firma-card {
            border: 2px solid #198754;
            border-radius: 8px;
        }
        .firma-card .card-header {
            background: linear-gradient(90deg, #d1e7dd, #f0fdf4);
            border-bottom: 1px solid #198754;
        }
        .firma-pad-wrapper {
            border: 2px dashed #6c757d;
            border-radius: 6px;
            background: #fff;
            padding: 4px;
            margin: 0.5rem 0;
            text-align: center;
        }
        #firmaPad {
            background: #fff;
            cursor: crosshair;
            width: 600px;
            max-width: 100%;
            min-height: 200px;
            margin: 0 auto;
        }
        .firma-imagen {
            max-width: 100%;
            max-height: 180px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
            padding: 0.25rem;
        }
        .firma-estado-pill {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.25rem 0.6rem;
            border-radius: 50rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .firma-estado-pill.pendiente { background: #fff3cd; color: #92400e; }
        .firma-estado-pill.firmada   { background: #d1e7dd; color: #166534; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php
    if ($es_mantenimiento) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
    } elseif ($es_ti) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
    } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
    } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
        include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
    } else {
        include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
    }
    ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h4 class="mb-0">
                        <i class="bi bi-tools me-2"></i>
                        <?php echo htmlspecialchars($solicitud['folio']); ?>
                    </h4>
                    <span class="badge <?php echo $badge_estado_class; ?> fs-6">
                        <?php echo $nombre_estado; ?>
                    </span>
                </div>

                <?php if ($puede_editar): ?>
                    <div class="d-flex gap-2">
                        <?php if ($estado_actual === 'pendiente'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalEstado('en_proceso')">
                                <i class="bi bi-play-circle me-1"></i>Iniciar Mantenimiento
                            </button>
                        <?php elseif ($estado_actual === 'en_proceso'): ?>
                            <button type="button" class="btn btn-success btn-sm" onclick="abrirModalEstado('finalizada')">
                                <i class="bi bi-check-circle me-1"></i>Finalizar
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="abrirModalEstado('cancelada')">
                            <i class="bi bi-x-circle me-1"></i>Cancelar Solicitud
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <!-- Banner para usuario receptor cuando esta finalizada y aun no ha firmado -->
            <?php if ($puede_firmar): ?>
                <div class="firma-banner mb-3">
                    <i class="bi bi-pen me-2"></i>
                    <strong>Falta tu firma:</strong> Sistemas finalizó el mantenimiento. Por favor revisa los detalles abajo
                    y firma tu conformidad para cerrar la solicitud.
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Columna principal -->
                <div class="col-lg-8">

                    <!-- Datos de la Solicitud -->
                    <div class="card detail-card <?php echo $estado_actual; ?> mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Información de la Solicitud</h6>
                            <span class="badge <?php echo $badges_prioridad[$prioridad_actual] ?? 'bg-secondary'; ?>">
                                <i class="bi bi-flag me-1"></i>Prioridad <?php echo $nombres_prioridad[$prioridad_actual] ?? ucfirst($prioridad_actual); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="detail-label">Tipo de Equipo (Grupo)</div>
                                    <div class="detail-value">
                                        <i class="bi <?php echo $icono_tipo; ?> me-1"></i>
                                        <?php echo htmlspecialchars($grupo_nombre); ?> · <?php echo htmlspecialchars($tipo_nombre); ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="detail-label">Tipo de Mantenimiento</div>
                                    <div class="detail-value">
                                        <?php if ($tipo_mant_actual === 'logico'): ?>
                                            <span class="badge bg-info"><i class="bi bi-cpu me-1"></i>Lógico</span>
                                        <?php elseif ($tipo_mant_actual === 'fisico'): ?>
                                            <span class="badge bg-secondary"><i class="bi bi-tools me-1"></i>Físico</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($solicitud['equipo_codigo'] || $solicitud['equipo_hostname']): ?>
                                <div class="col-md-6">
                                    <div class="detail-label">Equipo del Inventario</div>
                                    <div class="detail-value">
                                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $solicitud['equipo_id']; ?>">
                                            <?php echo htmlspecialchars($solicitud['equipo_hostname'] ?: $solicitud['equipo_codigo']); ?>
                                        </a>
                                        <?php if ($solicitud['equipo_marca'] || $solicitud['equipo_modelo']): ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars(trim($solicitud['equipo_marca'] . ' ' . $solicitud['equipo_modelo'])); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($solicitud['equipo_ubicacion']): ?>
                                            <br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($solicitud['equipo_ubicacion']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="col-md-6">
                                    <div class="detail-label">Programado para</div>
                                    <div class="detail-value">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?php
                                        echo $solicitud['fecha_deseada']
                                            ? date('d/m/Y', strtotime($solicitud['fecha_deseada']))
                                            : '—';
                                        ?>
                                        <?php if ($solicitud['hora_deseada']): ?>
                                            <i class="bi bi-clock ms-2 me-1"></i>
                                            <?php echo date('H:i', strtotime($solicitud['hora_deseada'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="detail-label">Descripción del problema</div>
                                    <div class="detail-value" style="white-space: pre-wrap;">
                                        <?php echo htmlspecialchars($solicitud['descripcion_problema']); ?>
                                    </div>
                                </div>

                                <?php if ($solicitud['observaciones_sistemas']): ?>
                                <div class="col-12">
                                    <div class="detail-label">Observaciones de Sistemas</div>
                                    <div class="detail-value" style="white-space: pre-wrap;">
                                        <?php echo htmlspecialchars($solicitud['observaciones_sistemas']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($solicitud['descripcion_solucion']): ?>
                                <div class="col-12">
                                    <div class="detail-label">Solución / Trabajo realizado</div>
                                    <div class="detail-value" style="white-space: pre-wrap; background:#e7f5e8; padding:.5rem; border-radius:4px;">
                                        <?php echo htmlspecialchars($solicitud['descripcion_solucion']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         FIRMA DEL USUARIO (Segundo Cierre)
                         ========================================================= -->
                    <?php if ($puede_firmar): ?>
                        <!-- Formulario de firma para usuario destinatario -->
                        <div class="card firma-card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-pen me-2 text-success"></i>Firma de Conformidad
                                    <span class="firma-estado-pill pendiente ms-2">
                                        <i class="bi bi-exclamation-circle"></i> Pendiente de firma
                                    </span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    Al firmar, confirmas que el mantenimiento fue realizado correctamente y aceptas el cierre
                                    de la solicitud. Esta acción es irreversible.
                                </p>

                                <form method="POST"
                                      action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_firmar_mantenimiento.php"
                                      id="formFirmar" novalidate>
                                    <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_id; ?>">
                                    <input type="hidden" name="firma_usuario" id="firma_usuario_input">

                                    <div class="mb-3">
                                        <label class="form-label small mb-1 fw-semibold">Nombre completo *</label>
                                        <input type="text" name="nombre_firma" id="nombre_firma" class="form-control"
                                               required minlength="3" maxlength="150"
                                               value="<?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small mb-1 fw-semibold">Firma *</label>
                                        <div class="firma-pad-wrapper">
                                            <div id="firmaPad"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Dibuje su firma en el recuadro de arriba.</small>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    id="btnLimpiarFirma">
                                                <i class="bi bi-eraser me-1"></i>Limpiar firma
                                            </button>
                                        </div>
                                        <div id="firmaEstado" class="mt-2 small text-muted">
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>Firma pendiente
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small mb-1">Comentario (opcional)</label>
                                        <textarea name="comentario_firma" class="form-control" rows="2" maxlength="1000"
                                                  placeholder="Algún comentario sobre el mantenimiento realizado..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-success" id="btnConfirmarFirma" disabled>
                                        <i class="bi bi-check-circle me-1"></i>Confirmar y Cerrar Solicitud
                                    </button>
                                    <small class="text-muted ms-2">Una vez confirmada, no podrá modificarse.</small>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($ya_firmada): ?>
                        <!-- Mostrar firma guardada -->
                        <div class="card firma-card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-pen me-2 text-success"></i>Firma de Conformidad
                                    <span class="firma-estado-pill firmada ms-2">
                                        <i class="bi bi-check-circle-fill"></i> Firmada
                                    </span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Firmada por</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($solicitud['nombre_firma_usuario']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Fecha y hora</div>
                                        <div class="detail-value">
                                            <?php echo $solicitud['fecha_firma_usuario']
                                                ? date('d/m/Y H:i', strtotime($solicitud['fecha_firma_usuario']))
                                                : '—'; ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label mb-1">Firma</div>
                                        <img src="<?php echo htmlspecialchars($solicitud['firma_usuario']); ?>"
                                             alt="Firma del usuario" class="firma-imagen">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Evidencias -->
                    <div class="card mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-paperclip me-2"></i>Evidencias
                                <span class="badge bg-secondary ms-1"><?php echo count($evidencias); ?>/5</span>
                            </h6>
                            <?php if ($puede_subir_evidencias && count($evidencias) < 5): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#formSubirEvidencia">
                                    <i class="bi bi-cloud-upload me-1"></i>Subir archivo
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">

                            <?php if ($puede_subir_evidencias): ?>
                                <div class="collapse mb-3" id="formSubirEvidencia">
                                    <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_evidencia_mantenimiento.php"
                                          method="POST" enctype="multipart/form-data" class="border rounded p-3 bg-light">
                                        <input type="hidden" name="accion" value="subir_evidencia">
                                        <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_id; ?>">
                                        <label class="form-label mb-2">Selecciona archivos (JPG, PNG, PDF · máx 10 MB c/u)</label>
                                        <input type="file" name="evidencias[]" class="form-control mb-2"
                                               multiple required accept=".jpg,.jpeg,.png,.pdf">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-upload me-1"></i>Subir
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    data-bs-toggle="collapse" data-bs-target="#formSubirEvidencia">
                                                Cancelar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($evidencias)): ?>
                                <p class="text-muted small mb-0 text-center py-3">
                                    <i class="bi bi-inbox me-1"></i>
                                    Sin evidencias adjuntas.
                                    <?php if ($puede_subir_evidencias): ?>
                                        Puedes subir archivos durante la edición del mantenimiento.
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach ($evidencias as $ev):
                                        $ext = strtolower(pathinfo($ev['nombre_original'], PATHINFO_EXTENSION));
                                        $es_imagen = in_array($ext, ['jpg', 'jpeg', 'png']);
                                        $url_archivo = URL_BASE . $ev['ruta_archivo'];
                                    ?>
                                        <div class="col-md-6">
                                            <div class="evidencia-card">
                                                <div class="evidencia-thumb">
                                                    <?php if ($es_imagen): ?>
                                                        <a href="<?php echo htmlspecialchars($url_archivo); ?>" target="_blank">
                                                            <img src="<?php echo htmlspecialchars($url_archivo); ?>" alt="evidencia">
                                                        </a>
                                                    <?php else: ?>
                                                        <i class="bi bi-file-earmark-pdf"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="evidencia-info">
                                                    <div class="evidencia-name" title="<?php echo htmlspecialchars($ev['nombre_original']); ?>">
                                                        <?php echo htmlspecialchars($ev['nombre_original']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo number_format($ev['tamanio'] / 1024, 0); ?> KB ·
                                                        <?php echo date('d/m/Y H:i', strtotime($ev['fecha_subida'])); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="<?php echo htmlspecialchars($url_archivo); ?>" target="_blank"
                                                       class="btn btn-sm btn-outline-primary" title="Ver">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($puede_subir_evidencias): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="confirmarEliminarEvidencia(<?php echo $ev['id']; ?>, '<?php echo htmlspecialchars(addslashes($ev['nombre_original'])); ?>')"
                                                                title="Eliminar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Historial -->
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                                <p class="text-muted small mb-0">Sin movimientos registrados.</p>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($historial as $h):
                                        $clase_extra = '';
                                        if ($h['estado_nuevo'] === 'finalizada' || $h['estado_nuevo'] === 'cerrada') $clase_extra = 'last';
                                        if ($h['estado_nuevo'] === 'cancelada') $clase_extra = 'cancel';
                                    ?>
                                        <div class="timeline-item <?php echo $clase_extra; ?>">
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($h['fecha_cambio'])); ?>
                                                · <?php echo htmlspecialchars($h['usuario_nombre'] ?? 'Sistema'); ?>
                                            </small>
                                            <div>
                                                <?php if ($h['estado_anterior'] && $h['estado_anterior'] !== $h['estado_nuevo']): ?>
                                                    <span class="badge <?php echo $badges_estado[$h['estado_anterior']] ?? 'bg-secondary'; ?>"><?php echo $nombres_estado[$h['estado_anterior']] ?? $h['estado_anterior']; ?></span>
                                                    <i class="bi bi-arrow-right mx-1"></i>
                                                <?php endif; ?>
                                                <span class="badge <?php echo $badges_estado[$h['estado_nuevo']] ?? 'bg-secondary'; ?>"><?php echo $nombres_estado[$h['estado_nuevo']] ?? $h['estado_nuevo']; ?></span>
                                            </div>
                                            <?php if ($h['comentario']): ?>
                                                <div class="small text-muted mt-1" style="white-space: pre-wrap;">
                                                    <?php echo htmlspecialchars($h['comentario']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Columna lateral -->
                <div class="col-lg-4">

                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-person me-2"></i>Solicitud para</h6>
                        </div>
                        <div class="card-body small">
                            <div class="detail-label">Usuario</div>
                            <div class="detail-value mb-2"><?php echo htmlspecialchars($solicitud['solicitante_nombre'] ?? '—'); ?></div>

                            <div class="detail-label">Departamento</div>
                            <div class="detail-value"><?php echo htmlspecialchars($solicitud['departamento_nombre'] ?? '—'); ?></div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-headset me-2"></i>Sistemas</h6>
                        </div>
                        <div class="card-body small">
                            <div class="detail-label">Atendido por</div>
                            <div class="detail-value mb-2"><?php echo htmlspecialchars($solicitud['atendido_por_nombre'] ?? '—'); ?></div>

                            <div class="detail-label">Creada</div>
                            <div class="detail-value mb-2"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></div>

                            <?php if ($solicitud['fecha_atencion']): ?>
                                <div class="detail-label">Iniciada (En Proceso)</div>
                                <div class="detail-value mb-2"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_atencion'])); ?></div>
                            <?php endif; ?>

                            <?php if ($solicitud['fecha_finalizacion']): ?>
                                <div class="detail-label">Finalizada / Cancelada</div>
                                <div class="detail-value mb-2"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_finalizacion'])); ?></div>
                            <?php endif; ?>

                            <?php if ($solicitud['fecha_firma_usuario']): ?>
                                <div class="detail-label">Firmada por usuario</div>
                                <div class="detail-value">
                                    <i class="bi bi-pen text-success me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_firma_usuario'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($es_ti && $solicitud['fecha_atencion']): ?>
                    <div class="card mb-3 border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-stopwatch me-2"></i>Tiempo de atención</h6>
                        </div>
                        <div class="card-body small">
                            <?php
                            $inicio = new DateTime($solicitud['fecha_atencion']);
                            $fin = $solicitud['fecha_finalizacion']
                                ? new DateTime($solicitud['fecha_finalizacion'])
                                : new DateTime();
                            $diff = $inicio->diff($fin);
                            $partes = [];
                            if ($diff->days > 0) $partes[] = $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
                            if ($diff->h    > 0) $partes[] = $diff->h    . ' h';
                            if ($diff->i    > 0) $partes[] = $diff->i    . ' min';
                            if (empty($partes))  $partes[] = '< 1 min';
                            ?>
                            <h5 class="mb-1"><?php echo implode(' ', $partes); ?></h5>
                            <small class="text-muted">
                                <?php echo $solicitud['fecha_finalizacion'] ? 'Total empleado' : 'En curso desde el inicio'; ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </main>
</div>

<!-- Modal cambiar estado (solo Sistemas) -->
<?php if ($puede_editar): ?>
<div class="modal fade" id="modalEstado" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i><span id="modalEstadoTitulo">Actualizar Estado</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_mantenimiento.php">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_id; ?>">
                    <input type="hidden" name="nuevo_estado" id="inputNuevoEstado">

                    <p class="mb-3">Solicitud: <strong><?php echo htmlspecialchars($solicitud['folio']); ?></strong></p>
                    <div id="modalEstadoMensaje" class="alert alert-info py-2 small mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label" id="labelDescripcion">Descripción / Observaciones</label>
                        <textarea name="descripcion" class="form-control" rows="4"
                                  placeholder="Describa el motivo o el trabajo realizado..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal eliminar evidencia -->
<?php if ($puede_subir_evidencias): ?>
<div class="modal fade" id="modalEliminarEvidencia" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar evidencia</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p class="mb-0">¿Eliminar el archivo <strong id="evidNombre"></strong>?</p>
                <p class="text-muted small mb-0 mt-2">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_evidencia_mantenimiento.php">
                    <input type="hidden" name="accion" value="eliminar_evidencia">
                    <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_id; ?>">
                    <input type="hidden" name="evidencia_id" id="evidIdEliminar">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- jQuery (necesario para jSignature) - cargar SOLO si se va a firmar -->
<?php if ($puede_firmar): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

<?php if ($puede_editar): ?>
<script>
function abrirModalEstado(estadoNuevo) {
    document.getElementById('inputNuevoEstado').value = estadoNuevo;
    const titulo  = document.getElementById('modalEstadoTitulo');
    const mensaje = document.getElementById('modalEstadoMensaje');
    const labelD  = document.getElementById('labelDescripcion');

    if (estadoNuevo === 'en_proceso') {
        titulo.textContent  = 'Iniciar Mantenimiento';
        mensaje.textContent = 'La solicitud cambiará a "En Proceso" y el cronómetro de tiempo de atención comenzará.';
        labelD.textContent  = 'Observaciones (opcional pero recomendado)';
    } else if (estadoNuevo === 'finalizada') {
        titulo.textContent  = 'Finalizar Mantenimiento';
        mensaje.textContent = 'Primer cierre. La solicitud quedará pendiente de la firma del usuario.';
        labelD.textContent  = 'Solución / Trabajo realizado';
    } else if (estadoNuevo === 'cancelada') {
        titulo.textContent  = 'Cancelar Solicitud';
        mensaje.textContent = 'La solicitud quedará cancelada y no podrá modificarse.';
        labelD.textContent  = 'Motivo de la cancelación';
    }

    new bootstrap.Modal(document.getElementById('modalEstado')).show();
}
</script>
<?php endif; ?>

<?php if ($puede_subir_evidencias): ?>
<script>
function confirmarEliminarEvidencia(id, nombre) {
    document.getElementById('evidIdEliminar').value = id;
    document.getElementById('evidNombre').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminarEvidencia')).show();
}
</script>
<?php endif; ?>

<?php if ($puede_firmar): ?>
<script>
// =====================================================================
// FIRMA DEL USUARIO (jSignature)
// =====================================================================
$(document).ready(function() {
    let firmaOk = false;

    const $firmaPad = $('#firmaPad').jSignature({
        color: '#000000',
        lineWidth: 2,
        background: '#ffffff',
        'decor-color': 'transparent',
        width: 600,
        height: 200
    });

    const btnConfirmar = document.getElementById('btnConfirmarFirma');
    const firmaEstado = document.getElementById('firmaEstado');

    $firmaPad.bind('change', function() {
        const data = $firmaPad.jSignature('getData', 'base30');
        if (data && data.length > 1 && data[1].length > 0) {
            firmaOk = true;
            firmaEstado.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Firma capturada';
            btnConfirmar.disabled = false;
        }
    });

    document.getElementById('btnLimpiarFirma').addEventListener('click', function() {
        $firmaPad.jSignature('reset');
        firmaOk = false;
        firmaEstado.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Firma pendiente';
        btnConfirmar.disabled = true;
        document.getElementById('firma_usuario_input').value = '';
    });

    document.getElementById('formFirmar').addEventListener('submit', function(e) {
        if (!firmaOk) {
            e.preventDefault();
            alert('Por favor, dibuje su firma antes de confirmar.');
            return false;
        }

        const nombre = document.getElementById('nombre_firma').value.trim();
        if (nombre.length < 3) {
            e.preventDefault();
            alert('Por favor, escriba su nombre completo.');
            return false;
        }

        // Capturar firma en formato image (data:image/png;base64,...)
        const fd = $firmaPad.jSignature('getData', 'image');
        if (!fd || fd.length < 2) {
            e.preventDefault();
            alert('Error al capturar la firma. Intente nuevamente.');
            return false;
        }
        document.getElementById('firma_usuario_input').value = 'data:' + fd[0] + ',' + fd[1];

        // Confirmacion final
        if (!confirm('¿Confirmar firma y cerrar la solicitud?\n\nUna vez confirmada, no podrá modificarse.')) {
            e.preventDefault();
            // Limpiar el input para que no quede firma sin confirmar
            document.getElementById('firma_usuario_input').value = '';
            return false;
        }

        // Deshabilitar boton para evitar doble envio
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    });
});
</script>
<?php endif; ?>

</body>
</html>