<?php
/**
 * Vista única de una SEC
 *
 * Ubicación: dashboard/salidas_envases/ver_sec.php
 *
 * Permite:
 * - Ver detalle completo (líneas, firmas, condiciones, evidencias)
 * - Almacén firma Entrega (si estado=enviada)
 * - Otro Almacén firma Recibe + marca condiciones (si estado=entregada)
 * - Almacén sube/elimina evidencias en cualquier estado activo
 * - Botones Editar / Cancelar / Cerrar (handlers en Bloque 4c)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_comentarios_funciones.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_historial_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al módulo de Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

$id_sec = (int)($_GET['id'] ?? 0);
if ($id_sec <= 0) {
    establecer_alerta('error', 'SEC no especificada.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$sec = obtener_sec_por_id($id_sec);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/salidas_envases.php');
}

$evidencias  = obtener_evidencias_sec($id_sec);
$comentarios = obtener_comentarios_sec($id_sec);
$historial   = obtener_historial_sec($id_sec);

$usuario_id     = (int)$_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$dept           = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

// Cálculos de permisos para esta vista
$es_almacen = ($dept === 'almacen_residuos');
$es_log_ven = in_array($dept, ['logistica', 'ventas'], true);

$puede_firmar_entrega = $es_almacen && $sec['estado'] === 'enviada' && empty($sec['entrega_firma']);
$puede_firmar_recibe  = $es_almacen
                        && $sec['estado'] === 'entregada'
                        && empty($sec['recibe_firma'])
                        && (int)$sec['entrega_usuario_id'] !== $usuario_id;

// Evidencias: sube cualquier Almacén; elimina quien las pueda ver (cualquier Almacén)
$puede_subir_evidencias    = $es_almacen && !in_array($sec['estado'], ['cerrada', 'cancelada'], true);
$puede_eliminar_evidencias = $es_almacen && !in_array($sec['estado'], ['cerrada', 'cancelada'], true);

// Acciones de Logística/Ventas (handlers vienen en Bloque 4c)
$puede_editar   = $es_log_ven && puede_crear_sec() && sec_es_editable($sec);
$puede_cancelar = es_logistica() && sec_es_cancelable($sec);
$puede_cerrar   = es_logistica() && $sec['estado'] === 'recibida';

$info_estado = info_estado_sec($sec['estado']);

// Mensajes flash
$mensajes = [
    'entrega_firmada'      => ['success', 'Firma de Entrega registrada correctamente.'],
    'recibe_firmada'       => ['success', 'Firma de Recibe y condiciones registradas correctamente.'],
    'evidencia_subida'     => ['success', 'Evidencia subida correctamente.'],
    'evidencia_eliminada'  => ['warning', 'Evidencia eliminada.'],
    'actualizada'          => ['success', 'SEC actualizada correctamente.'],
    'cancelada'            => ['warning', 'SEC cancelada. Los slots fueron liberados.'],
    'cerrada'              => ['success', 'SEC cerrada exitosamente.'],
    'comentario_agregado'  => ['success', 'Comentario agregado.'],
    'comentario_editado'   => ['success', 'Comentario actualizado.'],
    'comentario_eliminado' => ['warning', 'Comentario eliminado.'],
    'error'                => ['danger', 'Ocurrió un error.'],
    'error_validacion'     => ['danger', 'Hay errores de validación.'],
];
$msg_flash = $_GET['msg'] ?? null;
$errores_flash = $_SESSION['sec_errores'] ?? [];
unset($_SESSION['sec_errores']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sec['folio']); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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
        .seccion-card { margin-bottom: 1.5rem; }
        .firma-box {
            background: #fff; border: 1px solid #dee2e6; border-radius: 8px;
            padding: 1rem; min-height: 200px; display: flex; align-items: center; justify-content: center;
            flex-direction: column;
        }
        .firma-box img { max-width: 100%; max-height: 150px; }
        .firma-pendiente {
            background: #f8f9fa; color: #6c757d; font-style: italic;
        }
        .firma-canvas-wrapper {
            border: 2px dashed #ced4da; border-radius: 8px;
            background: #fff; position: relative; overflow: hidden;
        }
        .firma-canvas-wrapper canvas { display: block; width: 100%; height: 180px; }
        .firma-actions { position: absolute; top: 8px; right: 8px; }
        .placa-cell { font-family: 'Courier New', monospace; font-weight: 600; }
        .tabla-lineas { font-size: 0.85rem; }
        .tabla-lineas th { background: #f1f3f5; font-size: 0.78rem; text-align: center; vertical-align: middle; padding: 8px 4px; }
        .tabla-lineas .col-tipo, .tabla-lineas .col-cond { text-align: center; width: 50px; }
        .tabla-lineas .marca { color: #14b8a6; font-weight: 700; font-size: 1rem; }
        .estado-badge { font-size: 0.85rem; padding: 6px 12px; }
        .condicion-pill {
            display: inline-block; padding: 6px 14px; margin: 3px; border-radius: 16px;
            background: #f8f9fa; color: #6c757d; font-size: 0.85rem; font-weight: 600;
        }
        .condicion-pill.activa { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .evidencia-thumb {
            position: relative; width: 100%; padding-top: 100%;
            background: #f8f9fa; border-radius: 8px; overflow: hidden; cursor: pointer;
        }
        .evidencia-thumb img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .evidencia-thumb .btn-borrar {
            position: absolute; top: 5px; right: 5px;
            background: rgba(220,53,69,0.85); color: white; border: none; border-radius: 50%;
            width: 30px; height: 30px;
        }
        .evidencia-info {
            font-size: 0.7rem; color: #6c757d; margin-top: 4px;
        }
        .modal-imagen-grande img { width: 100%; height: auto; }
        .info-meta {
            font-size: 0.8rem; color: #6c757d;
        }
        .info-meta strong { color: #495057; }

        .info-meta {
            font-size: 0.8rem; color: #6c757d;
        }
        .info-meta strong { color: #495057; }

        /* ===== COMENTARIOS ===== */
        .comentario-item {
            background: #f8f9fa; border-radius: 8px; padding: 0.75rem 1rem;
            margin-bottom: 0.75rem; border-left: 3px solid #14b8a6;
        }
        .comentario-item.propio { border-left-color: #0d6efd; background: #f0f7ff; }
        .comentario-header {
            display: flex; justify-content: space-between; align-items: start;
            margin-bottom: 0.4rem; font-size: 0.82rem;
        }
        .comentario-autor { font-weight: 600; color: #212529; }
        .comentario-dept { color: #6c757d; font-size: 0.75rem; }
        .comentario-fecha { color: #6c757d; font-size: 0.72rem; white-space: nowrap; }
        .comentario-texto { color: #212529; white-space: pre-wrap; word-wrap: break-word; font-size: 0.9rem; }
        .comentario-editado { color: #6c757d; font-style: italic; font-size: 0.7rem; }
        .comentario-acciones { display: flex; gap: 4px; margin-top: 0.4rem; }
        .comentario-acciones .btn { padding: 2px 8px; font-size: 0.72rem; }
        .comentarios-lista { max-height: 500px; overflow-y: auto; padding-right: 4px; }
        .comentarios-vacio {
            text-align: center; color: #6c757d; font-style: italic; padding: 2rem 0;
        }

        /* ===== TIMELINE HISTORIAL ===== */
        .timeline {
            position: relative; padding-left: 42px; max-height: 600px; overflow-y: auto;
            padding-right: 4px;
        }
        .timeline::before {
            content: ''; position: absolute; left: 15px; top: 0; bottom: 0;
            width: 2px; background: #e9ecef;
        }
        .timeline-item { position: relative; padding-bottom: 1.25rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-icon {
            position: absolute; left: -42px; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            background: white; border: 2px solid; z-index: 1; font-size: 0.9rem;
        }
        .timeline-icon.text-primary   { border-color: #0d6efd; color: #0d6efd; }
        .timeline-icon.text-info      { border-color: #0dcaf0; color: #0dcaf0; }
        .timeline-icon.text-success   { border-color: #198754; color: #198754; }
        .timeline-icon.text-warning   { border-color: #ffc107; color: #ffc107; }
        .timeline-icon.text-danger    { border-color: #dc3545; color: #dc3545; }
        .timeline-icon.text-secondary { border-color: #6c757d; color: #6c757d; }
        .timeline-content {
            background: #fff; border: 1px solid #e9ecef; border-radius: 8px;
            padding: 0.6rem 0.9rem;
        }
        .timeline-title {
            font-weight: 600; font-size: 0.85rem; color: #212529; margin-bottom: 2px;
        }
        .timeline-meta {
            font-size: 0.72rem; color: #6c757d;
        }
        .timeline-detalles {
            margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #e9ecef;
            font-size: 0.78rem;
        }
        .diff-agrego { color: #0f5132; background: #d1e7dd; padding: 2px 8px; border-radius: 4px; display: inline-block; margin: 2px 0; font-family: monospace; font-size: 0.72rem; }
        .diff-quito  { color: #842029; background: #f8d7da; padding: 2px 8px; border-radius: 4px; display: inline-block; margin: 2px 0; font-family: monospace; font-size: 0.72rem; }
        .diff-lista { display: flex; flex-direction: column; gap: 3px; }
        .condicion-mini {
            display: inline-block; padding: 2px 6px; border-radius: 8px;
            background: #d1fae5; color: #065f46; font-size: 0.7rem; margin: 2px 2px 0 0;
        }
        .timeline-vacio {
            text-align: center; color: #6c757d; font-style: italic; padding: 2rem 0;
        }
    </style>
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        if (in_array($dept, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../includes/sidebar/sidebar_sec.php';
        } elseif ($dept === 'ventas') {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h1>
                                <i class="bi bi-file-earmark-text"></i>
                                <?php echo htmlspecialchars($sec['folio']); ?>
                                <span class="badge estado-badge ms-2 <?php echo $info_estado[0]; ?>"><?php echo $info_estado[1]; ?></span>
                            </h1>
                            <p class="info-meta mb-0">
                                Fecha del documento: <strong><?php echo sec_fecha_larga_es($sec['fecha_documento']); ?></strong> ·
                                Creada por: <strong><?php echo htmlspecialchars($sec['creador_nombre'] ?? '—'); ?></strong>
                                (<?php echo htmlspecialchars(ucfirst($sec['departamento_creador'])); ?>)
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/salidas_envases.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                            <?php if ($puede_editar): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/editar_sec.php?id=<?php echo $id_sec; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            <?php endif; ?>
                            <?php if ($puede_cerrar): ?>
                                <button type="button" class="btn btn-success" onclick="confirmarCerrar()">
                                    <i class="bi bi-check2-circle"></i> Cerrar SEC
                                </button>
                            <?php endif; ?>
                            <?php if ($puede_cancelar): ?>
                                <button type="button" class="btn btn-outline-danger" onclick="confirmarCancelar()">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($msg_flash && isset($mensajes[$msg_flash])): ?>
                    <div class="alert alert-<?php echo $mensajes[$msg_flash][0]; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($mensajes[$msg_flash][1]); ?>
                        <?php if (!empty($errores_flash)): ?>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errores_flash as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ============================================== -->
                <!-- LÍNEAS DE LA SEC                                -->
                <!-- ============================================== -->
                <div class="card seccion-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Líneas de envases</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($sec['empresa_destino']) || !empty($sec['condiciones_envase'])): ?>
                            <div class="p-3 border-bottom" style="background:#f8f9fa;">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="info-meta mb-1"><i class="bi bi-building"></i> <strong>Empresa destino</strong></div>
                                        <div><?php echo htmlspecialchars($sec['empresa_destino'] ?: '—'); ?></div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="info-meta mb-1"><i class="bi bi-clipboard-check"></i> <strong>Condiciones del envase</strong></div>
                                        <div style="white-space:pre-wrap;"><?php echo htmlspecialchars($sec['condiciones_envase'] ?: '—'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover tabla-lineas mb-0">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="width: 50px;">#</th>
                                        <th rowspan="2" style="width: 80px;">Cant.</th>
                                        <th colspan="4">Tipo de envase</th>
                                        <th rowspan="2">Unidad</th>
                                        <th rowspan="2">Horario</th>
                                        <?php if (!empty($sec['recibe_firma'])): ?>
                                            <th colspan="4">Condiciones</th>
                                        <?php endif; ?>
                                    </tr>
                                    <tr>
                                        <th class="col-tipo">TMB</th>
                                        <th class="col-tipo">TOTE</th>
                                        <th class="col-tipo">GFA</th>
                                        <th class="col-tipo">JAULA</th>
                                        <?php if (!empty($sec['recibe_firma'])): ?>
                                            <th class="col-cond" title="B1 Buenas">B1</th>
                                            <th class="col-cond" title="R2 Regulares">R2</th>
                                            <th class="col-cond" title="A3 Abierto">A3</th>
                                            <th class="col-cond" title="C4 Cerrado">C4</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sec['lineas'] as $idx => $linea): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $idx + 1; ?></td>
                                            <td class="text-center"><strong><?php echo (int)$linea['cantidad']; ?></strong></td>
                                            <td class="col-tipo"><?php echo $linea['tipo_envase']==='TMB'   ? '<span class="marca">✕</span>' : ''; ?></td>
                                            <td class="col-tipo"><?php echo $linea['tipo_envase']==='TOTE'  ? '<span class="marca">✕</span>' : ''; ?></td>
                                            <td class="col-tipo"><?php echo $linea['tipo_envase']==='GFA'   ? '<span class="marca">✕</span>' : ''; ?></td>
                                            <td class="col-tipo"><?php echo $linea['tipo_envase']==='JAULA' ? '<span class="marca">✕</span>' : ''; ?></td>
                                            <td class="text-center">
                                                <?php if ($linea['unidad_nombre']): ?>
                                                    <strong><?php echo htmlspecialchars($linea['unidad_nombre']); ?></strong>
                                                    <small class="placa-cell text-muted d-block"><?php echo htmlspecialchars($linea['unidad_placas']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($linea['slot_hora_inicio']): ?>
                                                    <span class="badge bg-light text-dark" style="font-family: monospace;">
                                                        <?php echo substr($linea['slot_hora_inicio'],0,5); ?> - <?php echo substr($linea['slot_hora_fin'],0,5); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!empty($sec['recibe_firma'])): ?>
                                                <td class="col-cond"><?php echo $sec['condicion_b1'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                <td class="col-cond"><?php echo $sec['condicion_r2'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                <td class="col-cond"><?php echo $sec['condicion_a3'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                <td class="col-cond"><?php echo $sec['condicion_c4'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($sec['recibe_firma'])): ?>
                            <div class="p-2 border-top text-center" style="background:#f8f9fa; font-size: 0.75rem;">
                                <span class="fw-bold text-muted me-2">Condiciones:</span>
                                <span class="me-3"><strong>B1</strong> = Buenas</span>
                                <span class="me-3"><strong>R2</strong> = Regulares</span>
                                <span class="me-3"><strong>A3</strong> = Abierto</span>
                                <span><strong>C4</strong> = Cerrado</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- FIRMAS                                          -->
                <!-- ============================================== -->
                <div class="row">
                    <!-- SOLICITA -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pencil-square"></i> Solicita</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($sec['solicita_firma'])): ?>
                                    <div class="firma-box">
                                        <img src="<?php echo htmlspecialchars($sec['solicita_firma']); ?>" alt="Firma Solicita">
                                    </div>
                                    <p class="mt-2 mb-0 text-center">
                                        <strong><?php echo htmlspecialchars($sec['solicita_nombre']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($sec['solicita_fecha'])); ?>
                                        </small>
                                    </p>
                                <?php else: ?>
                                    <div class="firma-box firma-pendiente">Sin firma</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ENTREGA -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-box-arrow-right"></i> Entrega</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($sec['entrega_firma'])): ?>
                                    <div class="firma-box">
                                        <img src="<?php echo htmlspecialchars($sec['entrega_firma']); ?>" alt="Firma Entrega">
                                    </div>
                                    <p class="mt-2 mb-0 text-center">
                                        <strong><?php echo htmlspecialchars($sec['entrega_nombre']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($sec['entrega_fecha'])); ?>
                                        </small>
                                    </p>
                                <?php elseif ($puede_firmar_entrega): ?>
                                    <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/firmar_entrega.php" method="POST" id="formEntrega">
                                        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                                        <input type="hidden" name="entrega_firma" id="entregaFirmaInput">
                                        <div class="mb-2">
                                            <label class="form-label small">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" name="entrega_nombre" class="form-control form-control-sm" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                                        </div>
                                        <div class="firma-canvas-wrapper mb-2">
                                            <div id="canvasEntrega"></div>
                                            <div class="firma-actions">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#canvasEntrega').jSignature('reset')">
                                                    <i class="bi bi-eraser"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary w-100" id="btnFirmarEntrega">
                                            <i class="bi bi-pen"></i> Firmar Entrega
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="firma-box firma-pendiente">Pendiente</div>
                                    <?php if (!$es_almacen && $sec['estado'] === 'enviada'): ?>
                                        <small class="d-block text-muted text-center mt-2">
                                            Almacén de Residuos debe firmar.
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RECIBE -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-box-arrow-in-down"></i> Recibe</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($sec['recibe_firma'])): ?>
                                    <div class="firma-box">
                                        <img src="<?php echo htmlspecialchars($sec['recibe_firma']); ?>" alt="Firma Recibe">
                                    </div>
                                    <p class="mt-2 mb-0 text-center">
                                        <strong><?php echo htmlspecialchars($sec['recibe_nombre']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($sec['recibe_fecha'])); ?>
                                        </small>
                                    </p>
                                <?php elseif ($es_almacen && $sec['estado'] === 'entregada'): ?>
                                    <!-- VISTA NORMAL (default) -->
                                    <div id="recibeVistaNormal">
                                        <?php if ($puede_firmar_recibe): ?>
                                            <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/firmar_recibe.php" method="POST" id="formRecibe">
                                                <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                                                <input type="hidden" name="recibe_firma" id="recibeFirmaInput">
                                                <div class="mb-2">
                                                    <label class="form-label small">Nombre <span class="text-danger">*</span></label>
                                                    <input type="text" name="recibe_nombre" class="form-control form-control-sm" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                                                </div>
                                                <div class="firma-canvas-wrapper mb-2">
                                                    <div id="canvasRecibe"></div>
                                                    <div class="firma-actions">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#canvasRecibe').jSignature('reset')">
                                                            <i class="bi bi-eraser"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small d-block">Condiciones <span class="text-danger">*</span></label>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <label class="form-check"><input class="form-check-input" type="checkbox" name="cond_b1" value="1"><span class="form-check-label small">B1 Buenas</span></label>
                                                        <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_r2" value="1"><span class="form-check-label small">R2 Regulares</span></label>
                                                        <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_a3" value="1"><span class="form-check-label small">A3 Abierto</span></label>
                                                        <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_c4" value="1"><span class="form-check-label small">C4 Cerrado</span></label>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-primary w-100" id="btnFirmarRecibe">
                                                    <i class="bi bi-pen"></i> Firmar Recibe
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="firma-box firma-pendiente">Pendiente</div>
                                            <small class="d-block text-warning text-center mt-2">
                                                <i class="bi bi-info-circle"></i> Tú firmaste Entrega.<br>Otro usuario de Almacén debe firmar Recibe.
                                            </small>
                                        <?php endif; ?>

                                        <!-- Botón firma externa -->
                                        <div class="border-top pt-2 mt-3">
                                            <button type="button" class="btn btn-sm btn-outline-warning w-100" onclick="mostrarFirmaExterna()">
                                                <i class="bi bi-hand-index-thumb"></i> Pasar dispositivo para firma externa
                                            </button>
                                            <small class="d-block text-muted text-center mt-1" style="font-size:0.7rem;">
                                                Si otra persona va a firmar aquí físicamente
                                            </small>
                                        </div>
                                    </div>

                                    <!-- VISTA EXTERNA (oculta por default) -->
                                    <div id="recibeVistaExterna" style="display:none;">
                                        <div class="alert alert-warning py-2 mb-2" style="font-size: 0.78rem;">
                                            <i class="bi bi-hand-index-thumb"></i> <strong>Modo firma externa.</strong>
                                            La persona debe escribir su nombre y firmar directamente.
                                        </div>
                                        <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/firmar_recibe_externo.php" method="POST" id="formRecibeExterno">
                                            <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                                            <input type="hidden" name="recibe_firma" id="recibeFirmaExtInput">
                                            <div class="mb-2">
                                                <label class="form-label small">Nombre de quien recibe <span class="text-danger">*</span></label>
                                                <input type="text" name="recibe_nombre" class="form-control form-control-sm" placeholder="Escribe tu nombre" required>
                                            </div>
                                            <div class="firma-canvas-wrapper mb-2">
                                                <div id="canvasRecibeExt"></div>
                                                <div class="firma-actions">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#canvasRecibeExt').jSignature('reset')">
                                                        <i class="bi bi-eraser"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small d-block">Condiciones <span class="text-danger">*</span></label>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <label class="form-check"><input class="form-check-input" type="checkbox" name="cond_b1" value="1"><span class="form-check-label small">B1 Buenas</span></label>
                                                    <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_r2" value="1"><span class="form-check-label small">R2 Regulares</span></label>
                                                    <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_a3" value="1"><span class="form-check-label small">A3 Abierto</span></label>
                                                    <label class="form-check ms-2"><input class="form-check-input" type="checkbox" name="cond_c4" value="1"><span class="form-check-label small">C4 Cerrado</span></label>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelarFirmaExterna()">
                                                    <i class="bi bi-x"></i> Cancelar
                                                </button>
                                                <button type="submit" class="btn btn-sm btn-primary flex-grow-1" id="btnFirmarRecibeExt">
                                                    <i class="bi bi-pen"></i> Confirmar firma
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="firma-box firma-pendiente">Pendiente</div>
                                    <?php if (!$es_almacen && $sec['estado'] === 'entregada'): ?>
                                        <small class="d-block text-muted text-center mt-2">
                                            Almacén debe firmar.
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- EVIDENCIAS                                      -->
                <!-- ============================================== -->
                <div class="card seccion-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-images"></i> Evidencias (<?php echo count($evidencias); ?>)</h6>
                        <?php if ($puede_subir_evidencias): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubirEvidencia">
                                <i class="bi bi-cloud-arrow-up"></i> Subir evidencia
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($evidencias)): ?>
                            <p class="text-center text-muted mb-0">Sin evidencias todavía.</p>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($evidencias as $ev): ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <div class="evidencia-thumb" onclick="verEvidencia('<?php echo htmlspecialchars(URL_BASE . $ev['ruta']); ?>', '<?php echo htmlspecialchars($ev['nombre_archivo'], ENT_QUOTES); ?>')">
                                            <img src="<?php echo URL_BASE . htmlspecialchars($ev['ruta']); ?>" alt="<?php echo htmlspecialchars($ev['nombre_archivo']); ?>">
                                            <?php if ($puede_eliminar_evidencias): ?>
                                                <button type="button" class="btn-borrar" onclick="event.stopPropagation(); confirmarEliminarEvidencia(<?php echo (int)$ev['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="evidencia-info">
                                            <?php echo htmlspecialchars($ev['subida_por_nombre']); ?><br>
                                            <?php echo date('d/m/Y H:i', strtotime($ev['fecha_subida'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </div>

                <!-- ============================================== -->
                <!-- COMENTARIOS + HISTORIAL (dos columnas)           -->
                <!-- ============================================== -->
                <div class="row" id="comentarios">
                    <!-- COMENTARIOS -->
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-chat-dots"></i> Comentarios (<?php echo count($comentarios); ?>)</h6>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <?php if (empty($comentarios)): ?>
                                    <div class="comentarios-vacio mb-3">
                                        <i class="bi bi-chat-square-text" style="font-size: 1.5rem;"></i>
                                        <p class="mb-0">Aún no hay comentarios.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="comentarios-lista mb-3">
                                        <?php foreach ($comentarios as $c): ?>
                                            <?php $es_propio = ((int)$c['usuario_id'] === $usuario_id); ?>
                                            <div class="comentario-item <?php echo $es_propio ? 'propio' : ''; ?>">
                                                <div class="comentario-header">
                                                    <div>
                                                        <div class="comentario-autor"><?php echo htmlspecialchars($c['autor_nombre'] ?? '—'); ?></div>
                                                        <div class="comentario-dept"><?php echo htmlspecialchars($c['autor_departamento'] ?? ''); ?></div>
                                                    </div>
                                                    <div class="comentario-fecha">
                                                        <?php echo date('d/m/Y H:i', strtotime($c['fecha_creacion'])); ?>
                                                        <?php if (!empty($c['fecha_edicion'])): ?>
                                                            <br><span class="comentario-editado"><i class="bi bi-pencil"></i> editado</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="comentario-texto"><?php echo htmlspecialchars($c['comentario']); ?></div>
                                                <?php if ($es_propio): ?>
                                                    <div class="comentario-acciones">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                                onclick="abrirEditarComentario(<?php echo (int)$c['id']; ?>, this)">
                                                            <i class="bi bi-pencil"></i> Editar
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="confirmarEliminarComentario(<?php echo (int)$c['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Eliminar
                                                        </button>
                                                        <textarea class="d-none" data-texto-original><?php echo htmlspecialchars($c['comentario']); ?></textarea>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Formulario abajo (patrón chat) -->
                                <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/agregar_comentario_sec.php" method="POST" class="mt-auto">
                                    <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                                    <div class="mb-2">
                                        <textarea name="comentario" class="form-control" rows="3"
                                                  placeholder="Escribe un comentario..." maxlength="5000" required></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-send"></i> Enviar comentario
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- HISTORIAL -->
                    <div class="col-lg-6 mb-3" id="historial">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Historial (<?php echo count($historial); ?>)</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($historial)): ?>
                                    <div class="timeline-vacio">
                                        <i class="bi bi-clock" style="font-size: 1.5rem;"></i>
                                        <p class="mb-0">Sin eventos registrados aún.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($historial as $evt): ?>
                                            <?php
                                                $ic = historial_icono_evento($evt['tipo_evento']);
                                                $datos = !empty($evt['datos_json']) ? json_decode($evt['datos_json'], true) : null;
                                            ?>
                                            <div class="timeline-item">
                                                <div class="timeline-icon text-<?php echo $ic['color']; ?>">
                                                    <i class="bi <?php echo $ic['icono']; ?>"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title"><?php echo htmlspecialchars($evt['descripcion']); ?></div>
                                                    <div class="timeline-meta">
                                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($evt['autor_nombre'] ?? 'Sistema'); ?>
                                                        <?php if (!empty($evt['autor_departamento'])): ?>
                                                            (<?php echo htmlspecialchars($evt['autor_departamento']); ?>)
                                                        <?php endif; ?>
                                                        · <?php echo date('d/m/Y H:i', strtotime($evt['fecha_creacion'])); ?>
                                                    </div>

                                                    <?php // Detalles por tipo de evento ?>
                                                    <?php if ($evt['tipo_evento'] === 'lineas_editadas' && $datos && isset($datos['antes']) && isset($datos['despues'])): ?>
                                                        <?php $cambios = comparar_lineas_sec($datos['antes'], $datos['despues']); ?>
                                                        <div class="timeline-detalles">
                                                            <div class="diff-lista">
                                                                <?php foreach ($cambios as $ch): ?>
                                                                    <?php $es_agrego = strpos($ch, 'Agrego') === 0 || strpos($ch, 'Agregó') === 0; ?>
                                                                    <?php $es_quito = strpos($ch, 'Quito') === 0 || strpos($ch, 'Quitó') === 0; ?>
                                                                    <?php if ($es_agrego): ?>
                                                                        <span class="diff-agrego"><i class="bi bi-plus-circle"></i> <?php echo htmlspecialchars($ch); ?></span>
                                                                    <?php elseif ($es_quito): ?>
                                                                        <span class="diff-quito"><i class="bi bi-dash-circle"></i> <?php echo htmlspecialchars($ch); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted"><?php echo htmlspecialchars($ch); ?></span>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php elseif ($evt['tipo_evento'] === 'sec_cancelada' && $datos && !empty($datos['motivo'])): ?>
                                                        <div class="timeline-detalles">
                                                            <strong>Motivo:</strong> <?php echo htmlspecialchars($datos['motivo']); ?>
                                                        </div>
                                                    <?php elseif ($evt['tipo_evento'] === 'recibe_firmada' && $datos && !empty($datos['condiciones'])): ?>
                                                        <div class="timeline-detalles">
                                                            <strong>Condiciones:</strong>
                                                            <?php foreach ($datos['condiciones'] as $cond): ?>
                                                                <span class="condicion-mini"><?php echo htmlspecialchars($cond); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php elseif (in_array($evt['tipo_evento'], ['evidencia_subida', 'evidencia_eliminada']) && $datos && !empty($datos['nombre_archivo'])): ?>
                                                        <div class="timeline-detalles">
                                                            <i class="bi bi-paperclip"></i> <?php echo htmlspecialchars($datos['nombre_archivo']); ?>
                                                        </div>
                                                    <?php elseif ($evt['tipo_evento'] === 'comentario_eliminado' && $datos && !empty($datos['texto_original'])): ?>
                                                        <div class="timeline-detalles text-muted">
                                                            <i class="bi bi-quote"></i> <em><?php echo htmlspecialchars(mb_substr($datos['texto_original'], 0, 120)) . (mb_strlen($datos['texto_original']) > 120 ? '...' : ''); ?></em>
                                                        </div>
                                                    <?php elseif ($evt['tipo_evento'] === 'comentario_editado' && $datos && !empty($datos['texto_original'])): ?>
                                                        <div class="timeline-detalles">
                                                            <div class="text-muted mb-1"><small><strong>Antes:</strong> <em><?php echo htmlspecialchars(mb_substr($datos['texto_original'], 0, 80)) . (mb_strlen($datos['texto_original']) > 80 ? '...' : ''); ?></em></small></div>
                                                            <div class="text-muted"><small><strong>Después:</strong> <em><?php echo htmlspecialchars(mb_substr($datos['texto_nuevo'] ?? '', 0, 80)) . (mb_strlen($datos['texto_nuevo'] ?? '') > 80 ? '...' : ''); ?></em></small></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: Subir Evidencia                                                 -->
    <!-- ===================================================================== -->
    <?php if ($puede_subir_evidencias): ?>
    <div class="modal fade" id="modalSubirEvidencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/subir_evidencia.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-cloud-arrow-up"></i> Subir evidencia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                        <label class="form-label">Imagen <span class="text-danger">*</span></label>
                        <input type="file" name="evidencia" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Formatos: JPG, PNG, WebP. Tamaño máximo: 5 MB.
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Subir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL: Ver Imagen Grande                                               -->
    <!-- ===================================================================== -->
    <div class="modal fade modal-imagen-grande" id="modalVerImagen" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloImagen"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imagenGrande" src="" alt="">
                </div>
            </div>
        </div>
    </div>

    <!-- Forms ocultos para acciones del Bloque 4c -->
    <?php if ($puede_cancelar): ?>
    <form id="formCancelar" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/cancelar_sec.php" method="POST" style="display:none;">
        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
        <input type="hidden" name="motivo" id="motivoCancelar">
    </form>
    <?php endif; ?>
    <?php if ($puede_cerrar): ?>
    <form id="formCerrar" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/cerrar_sec.php" method="POST" style="display:none;">
        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
    </form>
    <?php endif; ?>
    <?php if ($puede_eliminar_evidencias): ?>
    <form id="formEliminarEvidencia" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/eliminar_evidencia.php" method="POST" style="display:none;">
        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
        <input type="hidden" name="evidencia_id" id="evidenciaIdEliminar">
    </form>
    <?php endif; ?>

    <!-- Form oculto para eliminar comentario propio -->
    <form id="formEliminarComentario" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/eliminar_comentario_sec.php" method="POST" style="display:none;">
        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
        <input type="hidden" name="comentario_id" id="comentarioIdEliminar">
    </form>

    <!-- Modal editar comentario -->
    <div class="modal fade" id="modalEditarComentario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo URL_BASE; ?>dashboard/salidas_envases/editar_comentario_sec.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar comentario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="sec_id" value="<?php echo $id_sec; ?>">
                        <input type="hidden" name="comentario_id" id="modalComentarioId">
                        <textarea name="comentario" id="modalComentarioTexto"
                                  class="form-control" rows="5" maxlength="5000" required></textarea>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Solo puedes editar tus propios comentarios.
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
    <?php if ($puede_firmar_entrega): ?>
    $('#canvasEntrega').jSignature({ width: '100%', height: 180, lineWidth: 2 });
    document.getElementById('formEntrega').addEventListener('submit', function(e) {
        const data = $('#canvasEntrega').jSignature('getData', 'image');
        if (!data || data[1].length < 100) { e.preventDefault(); alert('Debes firmar antes de enviar.'); return; }
        document.getElementById('entregaFirmaInput').value = 'data:' + data[0] + ',' + data[1];
        document.getElementById('btnFirmarEntrega').disabled = true;
    });
    <?php endif; ?>

    <?php if ($puede_firmar_recibe): ?>
    $('#canvasRecibe').jSignature({ width: '100%', height: 180, lineWidth: 2 });
    document.getElementById('formRecibe').addEventListener('submit', function(e) {
        const form = this;
        const data = $('#canvasRecibe').jSignature('getData', 'image');
        if (!data || data[1].length < 100) { e.preventDefault(); alert('Debes firmar antes de enviar.'); return; }
        const hayCond = ['cond_b1','cond_r2','cond_a3','cond_c4'].some(c => form.querySelector(`[name="${c}"]`)?.checked);
        if (!hayCond) { e.preventDefault(); alert('Debes marcar al menos una condición.'); return; }
        document.getElementById('recibeFirmaInput').value = 'data:' + data[0] + ',' + data[1];
        document.getElementById('btnFirmarRecibe').disabled = true;
    });
    <?php endif; ?>

    <?php if ($es_almacen && $sec['estado'] === 'entregada' && empty($sec['recibe_firma'])): ?>
    // ==== Firma externa ====
    let canvasRecibeExtInicializado = false;

    function mostrarFirmaExterna() {
        if (!confirm('¿Vas a pasar el dispositivo a otra persona para que firme?')) return;
        document.getElementById('recibeVistaNormal').style.display  = 'none';
        document.getElementById('recibeVistaExterna').style.display = 'block';
        if (!canvasRecibeExtInicializado) {
            $('#canvasRecibeExt').jSignature({ width: '100%', height: 180, lineWidth: 2 });
            canvasRecibeExtInicializado = true;
        }
    }

    function cancelarFirmaExterna() {
        document.getElementById('recibeVistaExterna').style.display = 'none';
        document.getElementById('recibeVistaNormal').style.display  = 'block';
    }

    document.getElementById('formRecibeExterno').addEventListener('submit', function(e) {
        const form = this;
        const nombre = form.querySelector('[name="recibe_nombre"]').value.trim();
        if (!nombre) { e.preventDefault(); alert('La persona debe escribir su nombre.'); return; }
        const data = $('#canvasRecibeExt').jSignature('getData', 'image');
        if (!data || data[1].length < 100) { e.preventDefault(); alert('La persona debe firmar antes de enviar.'); return; }
        const hayCond = ['cond_b1','cond_r2','cond_a3','cond_c4'].some(c => form.querySelector(`[name="${c}"]`)?.checked);
        if (!hayCond) { e.preventDefault(); alert('Debes marcar al menos una condición.'); return; }
        document.getElementById('recibeFirmaExtInput').value = 'data:' + data[0] + ',' + data[1];
        document.getElementById('btnFirmarRecibeExt').disabled = true;
    });
    <?php endif; ?>

    function verEvidencia(url, titulo) {
        document.getElementById('imagenGrande').src = url;
        document.getElementById('tituloImagen').textContent = titulo;
        new bootstrap.Modal(document.getElementById('modalVerImagen')).show();
    }

    function confirmarEliminarEvidencia(id) {
        if (confirm('¿Eliminar esta evidencia? Esta acción no se puede deshacer.')) {
            document.getElementById('evidenciaIdEliminar').value = id;
            document.getElementById('formEliminarEvidencia').submit();
        }
    }

    <?php if ($puede_cancelar): ?>
    function confirmarCancelar() {
        const motivo = prompt('Motivo de cancelación (opcional):');
        if (motivo === null) return;
        if (!confirm('¿Confirmar cancelación de la SEC? Los slots ocupados serán liberados.')) return;
        document.getElementById('motivoCancelar').value = motivo || '';
        document.getElementById('formCancelar').submit();
    }
    <?php endif; ?>

    <?php if ($puede_cerrar): ?>
    function confirmarCerrar() {
        if (confirm('¿Cerrar la SEC exitosamente? Esto la marca como completada.')) {
            document.getElementById('formCerrar').submit();
        }
    }
    <?php endif; ?>

    // ===== Comentarios =====
    function abrirEditarComentario(comentarioId, btn) {
        const textoOriginal = btn.parentElement.querySelector('[data-texto-original]').value;
        document.getElementById('modalComentarioId').value = comentarioId;
        document.getElementById('modalComentarioTexto').value = textoOriginal;
        new bootstrap.Modal(document.getElementById('modalEditarComentario')).show();
    }

    function confirmarEliminarComentario(comentarioId) {
        if (confirm('¿Eliminar este comentario? Esta acción no se puede deshacer.')) {
            document.getElementById('comentarioIdEliminar').value = comentarioId;
            document.getElementById('formEliminarComentario').submit();
        }
    }

    // Al cargar, si el URL tiene el hash #comentarios, hacer scroll
    if (window.location.hash === '#comentarios') {
        document.getElementById('comentarios')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
    </script>
</body>
</html>