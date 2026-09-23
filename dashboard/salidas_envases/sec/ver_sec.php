<?php
/**
 * Detalle de SEC
 * dashboard/salidas_envases/sec/ver_sec.php
 *
 * Muestra:
 *   - Cabecera (folio, estado, unidad, vuelta, fecha, chofer, solicita, notas)
 *   - Líneas de empresa destino
 *   - Firmas (Entrega y Recibe) con panel para firmar
 *   - Historial completo
 *   - Panel de acciones según estado y rol
 *
 * (Los comentarios y evidencias se agregan en Sub-bloque 6.5)
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_historial_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_comentarios_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_evidencias_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/sec_devoluciones_funciones.php';

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
    redirigir(URL_BASE . 'dashboard/inicio.php');
}

$sec_id = (int) ($_GET['id'] ?? 0);
if ($sec_id <= 0) {
    establecer_alerta('error', 'SEC inválida.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$sec = obtener_sec_por_id($sec_id);
if (!$sec) {
    establecer_alerta('error', 'La SEC no existe.');
    redirigir(URL_BASE . 'dashboard/salidas_envases/sec/salidas_envases.php');
}

$dept        = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$usuario_id  = (int) $_SESSION['usuario_id'];
$nombre_user = $_SESSION['nombre_completo'];

// Permisos por acción
$puede_editar        = puede_crear_sec() && sec_es_editable($sec);
$puede_firmar_entr   = ($dept === 'almacen_residuos') && sec_puede_firmar_entrega($sec);
$puede_firmar_rec    = in_array($dept, ['logistica', 'ventas', 'almacen_residuos'], true) && sec_puede_firmar_recibe($sec);
$puede_cerrar_manual = puede_crear_sec() && sec_puede_cerrarse($sec);
$puede_cancelar      = puede_crear_sec() && sec_es_cancelable($sec);
$puede_devolver      = puede_registrar_devolucion_sec($dept, $sec);

$info_estado = info_estado_sec($sec['estado']);

// Historial
$historial   = obtener_historial_sec($sec_id);
$comentarios = obtener_comentarios_sec($sec_id);
$evidencias  = obtener_evidencias_sec($sec_id);
$devoluciones = obtener_devoluciones_sec($sec_id);

// Saldos por línea (para el modal y para mostrar en la lista)
$mapa_devueltas = cantidades_devueltas_por_linea($sec_id);
$saldos_lineas  = calcular_saldos_lineas($sec['lineas'], $mapa_devueltas);
$hay_saldo      = array_sum($saldos_lineas) > 0;

// Mensajes flash
$msgs_flash = [
    'firma_entrega'      => ['success', 'Firma de entrega registrada.'],
    'firma_recibe'       => ['success', 'Firma de recibe registrada.'],
    'cerrada'            => ['success', 'SEC cerrada correctamente.'],
    'cancelada'          => ['warning', 'SEC cancelada.'],
    'actualizada'        => ['success', 'SEC actualizada.'],
    'evidencia_subida'   => ['success', 'Evidencia(s) subida(s) correctamente.'],
    'devolucion'         => ['success', 'Devolución registrada correctamente.'],
    'error'              => ['danger',  'Ocurrió un error.'],
];
$flash = $_GET['msg'] ?? null;

// Errores post-acción (guardados en sesión)
$errores_flash = $_SESSION['sec_ver_errores'] ?? [];
unset($_SESSION['sec_ver_errores']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sec['folio']); ?> | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .card-detalle { border-radius: 10px; }
        .info-item { margin-bottom: 0.6rem; }
        .info-item .info-label {
            font-size: 0.72rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.4px;
        }
        .info-item .info-value { font-size: 0.95rem; font-weight: 500; }
        [data-theme="dark"] .info-item .info-label { color: #9aa4b2; }
        .estado-badge-lg { font-size: 0.90rem; padding: 6px 14px; }

        .tabla-lineas { font-size: 0.88rem; }
        .tabla-lineas th {
            background: #f1f3f5; font-weight: 600; padding: 8px; font-size: 0.78rem;
        }
        .cantidad-cell {
            display: inline-block; min-width: 42px; padding: 3px 10px;
            background: #e0f2fe; color: #0369a1; border-radius: 4px;
            font-weight: 700; text-align: center;
        }
        [data-theme="dark"] .cantidad-cell { background: rgba(3,105,161,0.20); color: #7dd3fc; }

        .firma-caja {
            border: 1px dashed #adb5bd; border-radius: 8px; padding: 1rem;
            background: #fbfbfb; min-height: 140px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        [data-theme="dark"] .firma-caja { background: #2a2f34; border-color: #495057; }
        .firma-svg-viewer { max-height: 140px; max-width: 100%; }
        .firma-vacia { color: #6c757d; font-style: italic; }
        .firma-meta { font-size: 0.78rem; color: #6c757d; margin-top: 0.5rem; text-align: center; }

        /* Historial timeline */
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before {
            content: ''; position: absolute; left: 8px; top: 0; bottom: 0;
            width: 2px; background: #dee2e6;
        }
        [data-theme="dark"] .timeline::before { background: #3d4349; }
        .timeline-item { position: relative; padding: 0 0 1rem 12px; }
        .timeline-item::before {
            content: ''; position: absolute; left: -22px; top: 3px;
            width: 14px; height: 14px; border-radius: 50%; background: var(--dot-color, #6c757d);
            border: 2px solid #fff;
        }
        [data-theme="dark"] .timeline-item::before { border-color: #262c31; }
        .timeline-item .desc { font-size: 0.88rem; }
        .timeline-item .meta { font-size: 0.72rem; color: #6c757d; }

        /* Sub-cita en el timeline: para eventos de edición/eliminación de comentarios */
        .timeline-quote {
            margin: 6px 0 4px 0;
            padding: 6px 10px;
            border-left: 3px solid #adb5bd;
            background: #f1f3f5;
            border-radius: 3px;
            font-size: 0.80rem;
        }
        .timeline-quote-label {
            display: block;
            font-size: 0.66rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .timeline-quote-text {
            color: #495057;
            white-space: pre-wrap;
            line-height: 1.35;
            font-style: italic;
        }
        [data-theme="dark"] .timeline-quote {
            background: #262c31;
            border-left-color: #495057;
        }
        [data-theme="dark"] .timeline-quote-label { color: #9aa4b2; }
        [data-theme="dark"] .timeline-quote-text  { color: #b8c1cc; }

        /* Canvas firma modal */
        #canvasFirmaEntrega, #canvasFirmaRecibe {
            border: 2px dashed #adb5bd; border-radius: 6px; background: #fff;
            touch-action: none; display: block; width: 100%; height: 200px;
        }

        .cancelada-banner {
            padding: 1rem; border-radius: 8px; background: #f8d7da; color: #842029;
            margin-bottom: 1rem;
        }
        [data-theme="dark"] .cancelada-banner {
            background: rgba(220,53,69,0.20); color: #ffabab;
        }

        /* Evidencias */
        .evidencia-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }
        .evidencia-item {
            position: relative;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .evidencia-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.10);
        }
        [data-theme="dark"] .evidencia-item { background: #2a2f34; border-color: #3a3f44; }
        .evidencia-thumb {
            width: 100%; aspect-ratio: 1;
            object-fit: cover;
            display: block;
            cursor: zoom-in;
            background: #f1f3f5;
        }
        [data-theme="dark"] .evidencia-thumb { background: #262c31; }
        .evidencia-meta {
            padding: 8px 10px;
            font-size: 0.72rem;
            border-top: 1px solid #eee;
        }
        [data-theme="dark"] .evidencia-meta { border-top-color: #3a3f44; }
        .evidencia-meta .autor { font-weight: 600; }
        .evidencia-meta .extra { color: #6c757d; }
        [data-theme="dark"] .evidencia-meta .extra { color: #9aa4b2; }
        .evidencia-item .btn-eliminar-ev {
            position: absolute; top: 6px; right: 6px;
            padding: 2px 6px; font-size: 0.72rem;
            opacity: 0; transition: opacity 0.15s;
        }
        .evidencia-item:hover .btn-eliminar-ev { opacity: 1; }

        /* Comentarios */
        .comentario-item {
            padding: 12px 14px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #fbfbfb;
        }
        [data-theme="dark"] .comentario-item { background: #2a2f34; border-color: #3a3f44; }
        .comentario-item.propio { border-left: 3px solid #0d6efd; }
        .comentario-head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 6px; font-size: 0.82rem;
        }
        .comentario-autor { font-weight: 600; }
        .comentario-depto {
            font-size: 0.68rem; padding: 1px 6px;
            border-radius: 3px;
            background: #e9ecef; color: #495057;
            margin-left: 6px;
        }
        [data-theme="dark"] .comentario-depto { background: #3a3f44; color: #dee2e6; }
        .comentario-fecha { color: #6c757d; font-size: 0.72rem; }
        .comentario-texto { white-space: pre-wrap; font-size: 0.90rem; }
        .comentario-editado {
            font-size: 0.68rem; color: #6c757d; font-style: italic; margin-top: 4px;
        }

        /* Modal lightbox de evidencias */
        #modalEvidenciaViewer .modal-dialog { max-width: 90vw; }
        #modalEvidenciaViewer .modal-body {
            padding: 0; background: #000; text-align: center;
        }
        #modalEvidenciaViewer .modal-body img {
            max-width: 100%; max-height: 85vh; object-fit: contain;
        }

        /* Devoluciones */
        .tabla-devoluciones { font-size: 0.85rem; }
        .tabla-devoluciones th {
            background: #fff3cd; font-weight: 600; padding: 8px; font-size: 0.78rem;
            color: #664d03;
        }
        [data-theme="dark"] .tabla-devoluciones th {
            background: rgba(255,193,7,0.15); color: #ffe083;
        }
        .badge-motivo {
            font-size: 0.72rem; padding: 3px 8px;
            background: #e9ecef; color: #495057; border-radius: 4px;
        }
        [data-theme="dark"] .badge-motivo { background: #3a3f44; color: #dee2e6; }
        .badge-motivo.condiciones { background: #f8d7da; color: #842029; }
        .badge-motivo.cantidad    { background: #cfe2ff; color: #084298; }
        .badge-motivo.otro        { background: #e2e3e5; color: #41464b; }
        [data-theme="dark"] .badge-motivo.condiciones { background: rgba(220,53,69,0.20); color: #ffabab; }
        [data-theme="dark"] .badge-motivo.cantidad    { background: rgba(13,110,253,0.20); color: #9ec5fe; }
        [data-theme="dark"] .badge-motivo.otro        { background: rgba(108,117,125,0.20); color: #adb5bd; }

        /* Modal devolución — estilo del boceto */
        .dev-radio-group { display: flex; flex-direction: column; gap: 8px; }
        .dev-radio-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border: 1px solid #dee2e6;
            border-radius: 8px; cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .dev-radio-item:hover { background: #f8f9fa; border-color: #adb5bd; }
        .dev-radio-item.selected { background: #cfe2ff; border-color: #0d6efd; }
        [data-theme="dark"] .dev-radio-item { background: #2a2f34; border-color: #3a3f44; color: #e0e6ed; }
        [data-theme="dark"] .dev-radio-item:hover { background: #333940; }
        [data-theme="dark"] .dev-radio-item.selected { background: rgba(13,110,253,0.20); border-color: #0d6efd; }
        .dev-radio-item .form-check-input { margin: 0; }
        .dev-radio-item .info { flex: 1; }
        .dev-radio-item .empresa { font-weight: 600; }
        .dev-radio-item .extras {
            font-size: 0.78rem; color: #6c757d; margin-top: 2px;
        }
        [data-theme="dark"] .dev-radio-item .extras { color: #9aa4b2; }
        .dev-radio-item .saldo-tag {
            font-size: 0.72rem; padding: 2px 8px; border-radius: 4px;
            background: #d1e7dd; color: #0f5132; font-weight: 700;
        }
        [data-theme="dark"] .dev-radio-item .saldo-tag {
            background: rgba(25,135,84,0.20); color: #75d5a4;
        }

        .dev-motivo-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        @media (max-width: 640px) {
            .dev-motivo-row { grid-template-columns: 1fr; }
        }
        .dev-motivo-item {
            padding: 12px; border: 1px solid #dee2e6; border-radius: 8px;
            cursor: pointer; text-align: center;
            transition: background 0.15s, border-color 0.15s;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .dev-motivo-item:hover { background: #f8f9fa; border-color: #adb5bd; }
        .dev-motivo-item.selected { background: #cfe2ff; border-color: #0d6efd; }
        [data-theme="dark"] .dev-motivo-item { background: #2a2f34; border-color: #3a3f44; color: #e0e6ed; }
        [data-theme="dark"] .dev-motivo-item:hover { background: #333940; }
        [data-theme="dark"] .dev-motivo-item.selected { background: rgba(13,110,253,0.20); border-color: #0d6efd; }
        .dev-motivo-item .label { font-size: 0.85rem; font-weight: 500; }
        .dev-motivo-item .form-check-input { margin: 0; }
        #devInputOtro { display: none; margin-top: 8px; }
        #devInputOtro.mostrado { display: block; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
        if (in_array($dept, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php';
        } elseif ($dept === 'ventas') {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>
        <main class="main-content">
            <div class="content-wrapper">

                <!-- Header con acciones -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h1 class="mb-0">
                                <i class="bi bi-file-earmark-text"></i>
                                <?php echo htmlspecialchars($sec['folio']); ?>
                                <span class="badge estado-badge-lg ms-2 <?php echo $info_estado[0]; ?>"><?php echo $info_estado[1]; ?></span>
                            </h1>
                            <p class="text-muted mb-0 mt-1" style="font-size: 0.85rem;">
                                <?php echo sec_fecha_larga_es($sec['fecha_salida']); ?>
                                · <?php echo htmlspecialchars($sec['unidad_nombre']); ?>
                                · Vuelta <?php echo (int) $sec['vuelta_numero']; ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/salidas_envases.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver al listado
                            </a>

                            <?php if ($puede_editar): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/editar_sec.php?id=<?php echo $sec_id; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            <?php endif; ?>

                            <?php if ($puede_firmar_entr): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFirmaEntrega">
                                    <i class="bi bi-pen"></i> Firmar entrega
                                </button>
                            <?php endif; ?>

                            <?php if ($puede_firmar_rec): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalFirmaRecibe">
                                    <i class="bi bi-check2-square"></i> Firmar recibe
                                </button>
                            <?php endif; ?>

                            <?php if ($puede_devolver && $hay_saldo): ?>
                                <button type="button" class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalDevolucion">
                                    <i class="bi bi-arrow-return-left"></i> Registrar devolución
                                </button>
                            <?php endif; ?>

                            <?php if ($puede_cerrar_manual): ?>
                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalCerrar">
                                    <i class="bi bi-check-circle"></i> Cerrar SEC
                                </button>
                            <?php endif; ?>

                            <?php if ($puede_cancelar): ?>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($flash && isset($msgs_flash[$flash])): ?>
                    <div class="alert alert-<?php echo $msgs_flash[$flash][0]; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($msgs_flash[$flash][1]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errores_flash)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Errores:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($errores_flash as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($sec['estado'] === 'cancelada'): ?>
                    <div class="cancelada-banner">
                        <i class="bi bi-x-octagon-fill"></i>
                        <strong>SEC cancelada</strong> el <?php echo date('d/m/Y H:i', strtotime($sec['cancelada_en'])); ?>
                        por <?php echo htmlspecialchars($sec['cancelada_por_nombre'] ?? 'usuario eliminado'); ?>.
                        <div class="mt-1"><strong>Motivo:</strong> <?php echo htmlspecialchars($sec['cancelacion_motivo']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <!-- COLUMNA IZQUIERDA: Cabecera + Líneas + Firmas -->
                    <div class="col-lg-8">
                        <!-- Cabecera / Datos generales -->
                        <div class="card card-detalle mb-3">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Datos generales</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Fecha</div>
                                        <div class="info-value"><?php echo date('d/m/Y', strtotime($sec['fecha_salida'])); ?></div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Unidad</div>
                                        <div class="info-value">
                                            <?php echo htmlspecialchars($sec['unidad_nombre']); ?>
                                            <?php if ($sec['unidad_matricula']): ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($sec['unidad_matricula']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Vuelta</div>
                                        <div class="info-value">Vuelta <?php echo (int) $sec['vuelta_numero']; ?></div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Chofer</div>
                                        <div class="info-value"><?php echo $sec['chofer_nombre'] ? htmlspecialchars($sec['chofer_nombre']) : '<span class="text-muted">—</span>'; ?></div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Solicita</div>
                                        <div class="info-value"><?php echo $sec['solicita_nombre'] ? htmlspecialchars($sec['solicita_nombre']) : '<span class="text-muted">—</span>'; ?></div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Hora ruta</div>
                                        <div class="info-value">
                                            <?php
                                            $hi = $sec['hora_inicio_ruta']  ? substr($sec['hora_inicio_ruta'], 0, 5) : '—';
                                            $hf = $sec['hora_termino_ruta'] ? substr($sec['hora_termino_ruta'], 0, 5) : '—';
                                            echo "{$hi} → {$hf}";
                                            ?>
                                        </div>
                                    </div></div>
                                    <div class="col-12"><div class="info-item">
                                        <div class="info-label">Notas generales</div>
                                        <div class="info-value">
                                            <?php echo $sec['notas_generales'] ? nl2br(htmlspecialchars($sec['notas_generales'])) : '<span class="text-muted">—</span>'; ?>
                                        </div>
                                    </div></div>
                                    <div class="col-md-4"><div class="info-item">
                                        <div class="info-label">Creada por</div>
                                        <div class="info-value">
                                            <?php echo $sec['creador_nombre'] ? htmlspecialchars($sec['creador_nombre']) : '<span class="text-muted">—</span>'; ?>
                                            <small class="text-muted d-block"><?php echo date('d/m/Y H:i', strtotime($sec['creado_en'])); ?></small>
                                        </div>
                                    </div></div>
                                </div>
                            </div>
                        </div>

                        <!-- Líneas -->
                        <div class="card card-detalle mb-3">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-building"></i> Empresas destino</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table tabla-lineas mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;">#</th>
                                                <th>Empresa destino</th>
                                                <th>Tipo / Especificación</th>
                                                <th class="text-center" style="width: 100px;">Cantidad</th>
                                                <th>Condiciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sec['lineas'] as $i => $l): ?>
                                                <tr>
                                                    <td><?php echo $i + 1; ?></td>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($l['empresa_nombre']); ?></td>
                                                    <td>
                                                        <div><strong><?php echo htmlspecialchars($l['tipo_nombre']); ?></strong></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($l['especificacion_nombre']); ?></small>
                                                    </td>
                                                    <td class="text-center"><span class="cantidad-cell"><?php echo (int) $l['cantidad']; ?></span></td>
                                                    <td><small><?php echo $l['condiciones_envase'] ? htmlspecialchars($l['condiciones_envase']) : '<span class="text-muted">—</span>'; ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Firmas -->
                        <div class="card card-detalle mb-3">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-pen"></i> Firmas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="info-label mb-2">Entrega (Almacén)</div>
                                        <div class="firma-caja">
                                            <?php if (!empty($sec['entrega_firma_svg'])): ?>
                                                <img class="firma-svg-viewer" src="<?php echo htmlspecialchars($sec['entrega_firma_svg']); ?>" alt="Firma de entrega">
                                                <div class="firma-meta">
                                                    <strong><?php echo htmlspecialchars($sec['entrega_nombre']); ?></strong><br>
                                                    <?php echo date('d/m/Y H:i', strtotime($sec['entrega_firmada_en'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="firma-vacia"><i class="bi bi-hourglass"></i> Pendiente de firma</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label mb-2">Recibe (cliente / opcional)</div>
                                        <div class="firma-caja">
                                            <?php if (!empty($sec['recibe_firma_svg'])): ?>
                                                <img class="firma-svg-viewer" src="<?php echo htmlspecialchars($sec['recibe_firma_svg']); ?>" alt="Firma de recibe">
                                                <div class="firma-meta">
                                                    <strong><?php echo htmlspecialchars($sec['recibe_nombre']); ?></strong><br>
                                                    <?php echo date('d/m/Y H:i', strtotime($sec['recibe_firmada_en'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="firma-vacia"><i class="bi bi-dash-circle"></i> Sin firma</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Evidencias -->
                        <div class="card card-detalle mb-3" id="evidencias">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-image"></i> Evidencias <span class="text-muted small">(<?php echo count($evidencias); ?>)</span></h5>
                                <?php if (es_almacen_residuos()): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalSubirEvidencia">
                                    <i class="bi bi-upload"></i> Subir imágenes
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($evidencias)): ?>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-camera"></i> Sin evidencias adjuntas.
                                        <?php if (es_almacen_residuos()): ?>
                                            Puedes subir fotos de la carga o descarga.
                                        <?php else: ?>
                                            Almacén de Residuos puede subir fotos de la carga o descarga.
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <div class="evidencia-grid">
                                        <?php foreach ($evidencias as $ev):
                                            $url_ev = URL_BASE . htmlspecialchars($ev['ruta_archivo']);
                                            $es_propia = ((int) $ev['subido_por'] === $usuario_id);
                                            ?>
                                                <div class="evidencia-item">
                                                    <img class="evidencia-thumb"
                                                        src="<?php echo $url_ev; ?>"
                                                        alt="<?php echo htmlspecialchars($ev['nombre_original']); ?>"
                                                        data-fullsrc="<?php echo $url_ev; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($ev['nombre_original']); ?>"
                                                        onclick="abrirLightbox(this)">
                                                    <?php if ($es_propia && es_almacen_residuos()): ?>
                                                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-ev"
                                                                data-bs-toggle="modal" data-bs-target="#modalEliminarEvidencia"
                                                            data-ev-id="<?php echo (int) $ev['id']; ?>"
                                                            data-ev-nombre="<?php echo htmlspecialchars($ev['nombre_original']); ?>"
                                                            title="Eliminar evidencia">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <div class="evidencia-meta">
                                                    <div class="autor"><?php echo htmlspecialchars($ev['subido_por_nombre'] ?? '—'); ?></div>
                                                    <div class="extra">
                                                        <?php echo date('d/m/Y H:i', strtotime($ev['fecha_creacion'])); ?>
                                                        · <?php echo evidencias_formatear_bytes((int) $ev['tamanio_bytes']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Devoluciones -->
                        <div class="card card-detalle mb-3" id="devoluciones">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-arrow-return-left"></i> Devoluciones <span class="text-muted small">(<?php echo count($devoluciones); ?>)</span></h5>
                                <?php if ($puede_devolver && $hay_saldo): ?>
                                    <button type="button" class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalDevolucion">
                                        <i class="bi bi-plus-lg"></i> Registrar devolución
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($devoluciones)): ?>
                                    <p class="text-muted small mb-0"><i class="bi bi-info-circle"></i> No hay devoluciones registradas para esta SEC.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table tabla-devoluciones mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 130px;">Fecha</th>
                                                    <th>Empresa</th>
                                                    <th>Tipo / Especificación</th>
                                                    <th class="text-center" style="width: 90px;">Cantidad</th>
                                                    <th>Motivo</th>
                                                    <th>Registrado por</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($devoluciones as $d):
                                                    $clase_motivo = 'otro';
                                                    if ($d['motivo'] === 'condiciones_incorrectas') $clase_motivo = 'condiciones';
                                                    if ($d['motivo'] === 'cantidad_incorrecta')     $clase_motivo = 'cantidad';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <small><?php echo date('d/m/Y H:i', strtotime($d['creado_en'])); ?></small>
                                                        </td>
                                                        <td class="fw-semibold"><?php echo htmlspecialchars($d['empresa_nombre']); ?></td>
                                                        <td>
                                                            <div><strong><?php echo htmlspecialchars($d['tipo_nombre']); ?></strong></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($d['especificacion_nombre']); ?></small>
                                                        </td>
                                                        <td class="text-center"><span class="cantidad-cell"><?php echo (int) $d['cantidad_devuelta']; ?></span></td>
                                                        <td>
                                                            <span class="badge-motivo <?php echo $clase_motivo; ?>"><?php echo motivo_devolucion_label($d['motivo']); ?></span>
                                                            <?php if (!empty($d['motivo_otro'])): ?>
                                                                <div class="mt-1"><small class="text-muted"><?php echo htmlspecialchars($d['motivo_otro']); ?></small></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><small><?php echo htmlspecialchars($d['registrado_por_nombre'] ?? '—'); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Comentarios -->
                        <div class="card card-detalle mb-3" id="comentarios">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Comentarios <span class="text-muted small">(<?php echo count($comentarios); ?>)</span></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($comentarios)): ?>
                                    <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Aún no hay comentarios. Sé el primero en dejar una nota.</p>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <?php foreach ($comentarios as $c):
                                            $es_propio = ((int) $c['usuario_id'] === $usuario_id);
                                        ?>
                                            <div class="comentario-item <?php echo $es_propio ? 'propio' : ''; ?>">
                                                <div class="comentario-head">
                                                    <div>
                                                        <span class="comentario-autor"><?php echo htmlspecialchars($c['autor_nombre'] ?? 'Usuario eliminado'); ?></span>
                                                        <?php if (!empty($c['autor_departamento'])): ?>
                                                            <span class="comentario-depto"><?php echo htmlspecialchars($c['autor_departamento']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="comentario-fecha"><?php echo date('d/m/Y H:i', strtotime($c['fecha_creacion'])); ?></span>
                                                        <?php if ($es_propio): ?>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-link p-0 text-muted" type="button" data-bs-toggle="dropdown">
                                                                    <i class="bi bi-three-dots-vertical"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><button type="button" class="dropdown-item"
                                                                                onclick="editarComentario(<?php echo (int) $c['id']; ?>, this)"
                                                                                data-texto="<?php echo htmlspecialchars($c['comentario']); ?>">
                                                                        <i class="bi bi-pencil"></i> Editar
                                                                    </button></li>
                                                                    <li><form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/eliminar_comentario.php" class="d-inline" onsubmit="return confirm('¿Eliminar este comentario? No se puede deshacer.');">
                                                                        <input type="hidden" name="comentario_id" value="<?php echo (int) $c['id']; ?>">
                                                                        <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                                                    </form></li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="comentario-texto" id="textoComentario<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['comentario']); ?></div>
                                                <?php if (!empty($c['fecha_edicion'])): ?>
                                                    <div class="comentario-editado"><i class="bi bi-pencil"></i> Editado el <?php echo date('d/m/Y H:i', strtotime($c['fecha_edicion'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Formulario nuevo comentario -->
                                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/guardar_comentario.php">
                                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                                    <div class="mb-2">
                                        <textarea class="form-control" name="comentario" rows="2" maxlength="5000" placeholder="Escribe un comentario…" required></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-send"></i> Publicar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: Historial -->
                    <div class="col-lg-4">
                        <div class="card card-detalle sticky-top" style="top: 1rem;">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial</h5>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($historial)): ?>
                                    <p class="text-muted small mb-0">Sin eventos registrados.</p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($historial as $ev):
                                            $info = historial_icono_evento($ev['tipo_evento']);
                                            $color_hex_map = [
                                                'primary'=>'#0d6efd','success'=>'#198754','danger'=>'#dc3545',
                                                'warning'=>'#ffc107','info'=>'#0dcaf0','secondary'=>'#6c757d',
                                            ];
                                            $dot = $color_hex_map[$info['color']] ?? '#6c757d';
                                        ?>
                                            <div class="timeline-item" style="--dot-color: <?php echo $dot; ?>;">
                                                <div class="desc">
                                                    <i class="bi <?php echo $info['icono']; ?> text-<?php echo $info['color']; ?>"></i>
                                                    <?php echo htmlspecialchars($ev['descripcion']); ?>
                                                </div>
                                                <?php
                                                // Sub-cita: para comentario_editado / comentario_eliminado
                                                // el texto anterior se guardó en datos_json.
                                                if (in_array($ev['tipo_evento'], ['comentario_editado', 'comentario_eliminado'], true)
                                                    && !empty($ev['datos_json'])) {
                                                    $__datos = json_decode($ev['datos_json'], true);
                                                    if (is_array($__datos) && !empty($__datos['texto_original'])) {
                                                        $__prev = mb_strlen($__datos['texto_original']) > 300
                                                            ? mb_substr($__datos['texto_original'], 0, 300) . '…'
                                                            : $__datos['texto_original'];
                                                        $__label = $ev['tipo_evento'] === 'comentario_editado' ? 'Antes decía' : 'Decía';
                                                        echo '<div class="timeline-quote">';
                                                        echo '<span class="timeline-quote-label">' . $__label . '</span>';
                                                        echo '<div class="timeline-quote-text">' . htmlspecialchars($__prev) . '</div>';
                                                        echo '</div>';
                                                    }
                                                }
                                                ?>
                                                <div class="meta">
                                                    <?php echo date('d/m/Y H:i', strtotime($ev['fecha_creacion'])); ?>
                                                    <?php if (!empty($ev['autor_nombre'])): ?>
                                                        · <?php echo htmlspecialchars($ev['autor_nombre']); ?>
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

    <?php if ($puede_firmar_entr): ?>
    <!-- MODAL FIRMA ENTREGA -->
    <div class="modal fade" id="modalFirmaEntrega" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/firmar_entrega.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <input type="hidden" name="firma_svg" id="firmaEntregaSvg">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pen"></i> Firma de entrega — Almacén</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de quien firma <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required maxlength="200" value="<?php echo htmlspecialchars($nombre_user); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Firma <span class="text-danger">*</span></label>
                            <div id="canvasFirmaEntrega"></div>
                            <div class="form-text">Firma con el mouse o el dedo. Al enviar, se mueve a estado "En ruta".</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarEntrega">
                            <i class="bi bi-eraser"></i> Limpiar firma
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnConfirmarEntrega">
                            <i class="bi bi-check-circle"></i> Confirmar entrega
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puede_firmar_rec): ?>
    <!-- MODAL FIRMA RECIBE -->
    <div class="modal fade" id="modalFirmaRecibe" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/firmar_recibe.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <input type="hidden" name="firma_svg" id="firmaRecibeSvg">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-check2-square"></i> Firma de recibe</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de quien recibe <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required maxlength="200" placeholder="Ej. Juan Pérez (representante del cliente)">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Firma <span class="text-danger">*</span></label>
                            <div id="canvasFirmaRecibe"></div>
                            <div class="form-text">La firma de recibe es opcional. No cambia el estado de la SEC.</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarRecibe">
                            <i class="bi bi-eraser"></i> Limpiar firma
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="btnConfirmarRecibe">
                            <i class="bi bi-check-circle"></i> Confirmar recibe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puede_cerrar_manual): ?>
    <!-- MODAL CERRAR -->
    <div class="modal fade" id="modalCerrar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/cerrar_sec.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-check-circle"></i> Cerrar SEC</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Cerrar la SEC <strong><?php echo htmlspecialchars($sec['folio']); ?></strong>?</p>
                        <p class="text-muted small mb-0">
                            La SEC pasará a estado <strong>Cerrada</strong>. Si tiene devoluciones registradas, quedará como <strong>Cerrada c/devolución</strong>. La firma de recibe podrá agregarse después si es necesario.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Cerrar SEC</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puede_cancelar): ?>
    <!-- MODAL CANCELAR -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/cancelar_sec.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-x-circle"></i> Cancelar SEC</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Cancelar la SEC <strong>revertirá el stock</strong> descontado y no se podrá deshacer.
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Motivo de cancelación <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="motivo" rows="3" required maxlength="500" placeholder="Explica el motivo de la cancelación"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> Cancelar SEC</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL SUBIR EVIDENCIAS (solo Almacén) -->
    <?php if (es_almacen_residuos()): ?>
    <div class="modal fade" id="modalSubirEvidencia" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/subir_evidencia.php" enctype="multipart/form-data">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-upload"></i> Subir evidencias</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Selecciona hasta 5 imágenes <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="evidencias[]" accept="image/jpeg,image/png,image/webp" multiple required id="inputEvidencias">
                            <div class="form-text">
                                Formatos: JPG, PNG, WebP. Máximo 20 MB por imagen. Puedes seleccionar varias a la vez.
                            </div>
                            <div id="previewEvidencias" class="mt-2 d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSubmitEvidencias">
                            <i class="bi bi-cloud-upload"></i> Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL LIGHTBOX EVIDENCIA -->
    <div class="modal fade" id="modalEvidenciaViewer" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0">
                    <img id="viewerImg" src="" alt="" style="background:#000;">
                </div>
                <div class="text-center text-white mt-2" id="viewerCaption" style="font-size: 0.85rem;"></div>
            </div>
        </div>
    </div>

    <!-- MODAL ELIMINAR EVIDENCIA (solo Almacén) -->
    <?php if (es_almacen_residuos()): ?>
    <div class="modal fade" id="modalEliminarEvidencia" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/eliminar_evidencia.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <input type="hidden" name="evidencia_id" id="delEvId" value="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-trash"></i> Eliminar evidencia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Eliminar la evidencia <strong id="delEvNombre"></strong>?</p>
                        <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puede_devolver && $hay_saldo):
        // Filtrar líneas con saldo > 0 para el modal
        $lineas_devolver = [];
        foreach ($sec['lineas'] as $l) {
            $lid = (int) $l['id'];
            $saldo = $saldos_lineas[$lid] ?? 0;
            if ($saldo > 0) {
                $l['saldo_disponible'] = $saldo;
                $lineas_devolver[] = $l;
            }
        }
    ?>
    <!-- MODAL DEVOLUCIÓN -->
    <div class="modal fade" id="modalDevolucion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/registrar_devolucion.php" id="formDevolucion">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <div class="modal-header">
                        <h4 class="modal-title fw-bold"><i class="bi bi-arrow-return-left"></i> Devolución de Envases</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">

                        <!-- ¿Qué empresa devolvió envases? -->
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">¿Qué empresa devolvió envases? <span class="text-danger">*</span></label>
                            <div class="dev-radio-group">
                                <?php foreach ($lineas_devolver as $l): ?>
                                    <label class="dev-radio-item" data-linea-id="<?php echo (int) $l['id']; ?>" data-saldo="<?php echo (int) $l['saldo_disponible']; ?>">
                                        <input type="radio" class="form-check-input" name="linea_id" value="<?php echo (int) $l['id']; ?>" required>
                                        <div class="info">
                                            <div class="empresa"><?php echo htmlspecialchars($l['empresa_nombre']); ?></div>
                                            <div class="extras">
                                                <?php echo htmlspecialchars($l['tipo_nombre']); ?>
                                                · <?php echo htmlspecialchars($l['especificacion_nombre']); ?>
                                                · Original: <?php echo (int) $l['cantidad']; ?>
                                            </div>
                                        </div>
                                        <span class="saldo-tag">Saldo: <?php echo (int) $l['saldo_disponible']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ¿Por qué fue la devolución? -->
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">¿Por qué fue la devolución? <span class="text-danger">*</span></label>
                            <div class="dev-motivo-row">
                                <label class="dev-motivo-item" data-motivo="condiciones_incorrectas">
                                    <input type="radio" class="form-check-input" name="motivo" value="condiciones_incorrectas" required>
                                    <div class="label">Condiciones incorrectas de envases</div>
                                </label>
                                <label class="dev-motivo-item" data-motivo="cantidad_incorrecta">
                                    <input type="radio" class="form-check-input" name="motivo" value="cantidad_incorrecta">
                                    <div class="label">Cantidad incorrecta de envases</div>
                                </label>
                                <label class="dev-motivo-item" data-motivo="otro">
                                    <input type="radio" class="form-check-input" name="motivo" value="otro">
                                    <div class="label">Otro</div>
                                </label>
                            </div>
                            <div id="devInputOtro" class="mt-3">
                                <label class="form-label small">Especifica el motivo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="motivo_otro" maxlength="500" placeholder="Describe el motivo…">
                            </div>
                        </div>

                        <!-- Cantidad de envases devueltos -->
                        <div class="mb-2">
                            <label class="form-label fw-bold mb-2">Cantidad de envases devueltos <span class="text-danger">*</span></label>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4">
                                    <input type="number" class="form-control" name="cantidad" id="devCantidad"
                                           min="1" step="1" required disabled placeholder="Selecciona empresa">
                                </div>
                                <div class="col-md-8">
                                    <small class="text-muted" id="devSaldoHint">Selecciona una empresa arriba para habilitar este campo.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning text-dark" id="btnGenerarDevolucion">
                            <i class="bi bi-arrow-return-left"></i> Generar devolución
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL EDITAR COMENTARIO -->
    <div class="modal fade" id="modalEditarComentario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/editar_comentario.php">
                    <input type="hidden" name="sec_id" value="<?php echo $sec_id; ?>">
                    <input type="hidden" name="comentario_id" id="editComId" value="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar comentario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea class="form-control" name="comentario" id="editComTexto" rows="4" maxlength="5000" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <?php if ($puede_firmar_entr || $puede_firmar_rec): ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsignature@2.1.2/libs/jSignature.min.js"></script>
    <script>
    (function () {
        let sigEntrega = null, sigRecibe = null;

        <?php if ($puede_firmar_entr): ?>
        // Inicializar canvas entrega al abrir modal (jSignature necesita dimensiones estables)
        const modalEntrega = document.getElementById('modalFirmaEntrega');
        modalEntrega.addEventListener('shown.bs.modal', () => {
            if (!sigEntrega) {
                sigEntrega = $('#canvasFirmaEntrega').jSignature({ height: 200, width: '100%' });
            } else {
                sigEntrega.jSignature('reset');
            }
        });
        document.getElementById('btnLimpiarEntrega').addEventListener('click', () => {
            if (sigEntrega) sigEntrega.jSignature('reset');
        });
        modalEntrega.querySelector('form').addEventListener('submit', function (e) {
            const raw = sigEntrega ? sigEntrega.jSignature('getData', 'native') : null;
            // 'native' devuelve array de trazos: [] si no firmó, [{...}, ...] si sí
            if (!raw || raw.length === 0) {
                e.preventDefault();
                alert('Debes firmar antes de confirmar.');
                return false;
            }
            // getData() sin argumento devuelve el data URL PNG:
            // "data:image/png;base64,iVBORw0KG..."
            // PNG es universalmente compatible y jSignature lo genera bien.
            // La columna se llama _svg pero en LONGTEXT cabe cualquier data URL.
            const dataUrl = sigEntrega.jSignature('getData');
            document.getElementById('firmaEntregaSvg').value = dataUrl;
        });
        <?php endif; ?>

        <?php if ($puede_firmar_rec): ?>
        const modalRecibe = document.getElementById('modalFirmaRecibe');
        modalRecibe.addEventListener('shown.bs.modal', () => {
            if (!sigRecibe) {
                sigRecibe = $('#canvasFirmaRecibe').jSignature({ height: 200, width: '100%' });
            } else {
                sigRecibe.jSignature('reset');
            }
        });
        document.getElementById('btnLimpiarRecibe').addEventListener('click', () => {
            if (sigRecibe) sigRecibe.jSignature('reset');
        });
        modalRecibe.querySelector('form').addEventListener('submit', function (e) {
            const raw = sigRecibe ? sigRecibe.jSignature('getData', 'native') : null;
            if (!raw || raw.length === 0) {
                e.preventDefault();
                alert('Debes firmar antes de confirmar.');
                return false;
            }
            const dataUrl = sigRecibe.jSignature('getData');
            document.getElementById('firmaRecibeSvg').value = dataUrl;
        });
        <?php endif; ?>
    })();
    </script>
    <?php endif; ?>
    <script>
    // Lightbox de evidencias
    function abrirLightbox(imgEl) {
        document.getElementById('viewerImg').src = imgEl.dataset.fullsrc;
        document.getElementById('viewerImg').alt = imgEl.dataset.nombre;
        document.getElementById('viewerCaption').textContent = imgEl.dataset.nombre;
        new bootstrap.Modal(document.getElementById('modalEvidenciaViewer')).show();
    }

    // Editar comentario
    function editarComentario(id, btn) {
        document.getElementById('editComId').value = id;
        document.getElementById('editComTexto').value = btn.dataset.texto;
        new bootstrap.Modal(document.getElementById('modalEditarComentario')).show();
    }

    // Eliminar evidencia: rellenar modal cuando se abre
    const modalEliminarEv = document.getElementById('modalEliminarEvidencia');
    if (modalEliminarEv) {
        modalEliminarEv.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('delEvId').value = btn.getAttribute('data-ev-id');
            document.getElementById('delEvNombre').textContent = btn.getAttribute('data-ev-nombre');
        });
    }

    // Preview de imágenes seleccionadas en modal subir evidencia
    const inputEv = document.getElementById('inputEvidencias');
    if (inputEv) {
        inputEv.addEventListener('change', function () {
            const preview = document.getElementById('previewEvidencias');
            preview.innerHTML = '';
            const files = Array.from(this.files).slice(0, 5);
            if (this.files.length > 5) {
                const w = document.createElement('div');
                w.className = 'text-danger small w-100';
                w.textContent = 'Solo se subirán los primeros 5 archivos.';
                preview.appendChild(w);
            }
            files.forEach(f => {
                if (!f.type.startsWith('image/')) return;
                const url = URL.createObjectURL(f);
                const div = document.createElement('div');
                div.style.cssText = 'width:70px;height:70px;border-radius:6px;overflow:hidden;border:1px solid #dee2e6;';
                div.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;" alt="">`;
                div.title = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
                preview.appendChild(div);
            });
        });
    }

    // Autoscroll al hash si vino en la URL (para volver a comentarios/evidencias)
    if (window.location.hash) {
        setTimeout(() => {
            const el = document.querySelector(window.location.hash);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 200);
    }

    // ============================================================
    // MODAL DEVOLUCIÓN — selección visual + validación en vivo
    // ============================================================
    (function () {
        const modalDev = document.getElementById('modalDevolucion');
        if (!modalDev) return;

        const inputCantidad  = document.getElementById('devCantidad');
        const saldoHint      = document.getElementById('devSaldoHint');
        const inputOtroBox   = document.getElementById('devInputOtro');
        const inputOtro      = inputOtroBox ? inputOtroBox.querySelector('input[name="motivo_otro"]') : null;
        const form           = document.getElementById('formDevolucion');
        const btnGenerar     = document.getElementById('btnGenerarDevolucion');

        // Selección visual de empresa (radios)
        modalDev.querySelectorAll('.dev-radio-item').forEach(item => {
            item.addEventListener('click', () => {
                modalDev.querySelectorAll('.dev-radio-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                const saldo = parseInt(item.dataset.saldo, 10) || 0;
                inputCantidad.disabled = false;
                inputCantidad.max = saldo;
                inputCantidad.placeholder = `Máx ${saldo}`;
                inputCantidad.value = '';
                saldoHint.textContent = `Máximo permitido: ${saldo} envase${saldo === 1 ? '' : 's'}.`;
                saldoHint.classList.remove('text-danger');
            });
        });

        // Validación en vivo de cantidad
        inputCantidad.addEventListener('input', () => {
            const max = parseInt(inputCantidad.max, 10) || 0;
            const val = parseInt(inputCantidad.value, 10);
            if (!isNaN(val) && val > max) {
                saldoHint.textContent = `La cantidad (${val}) excede el saldo (${max}).`;
                saldoHint.classList.add('text-danger');
            } else if (!isNaN(val) && val > 0) {
                saldoHint.textContent = `${val} de ${max} envases.`;
                saldoHint.classList.remove('text-danger');
            } else {
                saldoHint.textContent = `Máximo permitido: ${max}.`;
                saldoHint.classList.remove('text-danger');
            }
        });

        // Selección visual de motivo + mostrar/ocultar input Otro
        modalDev.querySelectorAll('.dev-motivo-item').forEach(item => {
            item.addEventListener('click', () => {
                modalDev.querySelectorAll('.dev-motivo-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                const motivo = item.dataset.motivo;
                if (motivo === 'otro') {
                    inputOtroBox.classList.add('mostrado');
                    inputOtro.setAttribute('required', 'required');
                    setTimeout(() => inputOtro.focus(), 100);
                } else {
                    inputOtroBox.classList.remove('mostrado');
                    inputOtro.removeAttribute('required');
                    inputOtro.value = '';
                }
            });
        });

        // Reset al abrir
        modalDev.addEventListener('show.bs.modal', () => {
            form.reset();
            modalDev.querySelectorAll('.dev-radio-item, .dev-motivo-item').forEach(i => i.classList.remove('selected'));
            inputCantidad.disabled = true;
            inputCantidad.placeholder = 'Selecciona empresa';
            saldoHint.textContent = 'Selecciona una empresa arriba para habilitar este campo.';
            saldoHint.classList.remove('text-danger');
            inputOtroBox.classList.remove('mostrado');
            inputOtro.removeAttribute('required');
        });

        // Validación pre-submit
        form.addEventListener('submit', (e) => {
            const linea = form.querySelector('input[name="linea_id"]:checked');
            const motivo = form.querySelector('input[name="motivo"]:checked');
            const cant = parseInt(inputCantidad.value, 10);
            const max = parseInt(inputCantidad.max, 10) || 0;

            if (!linea) { e.preventDefault(); alert('Selecciona la empresa que devolvió envases.'); return false; }
            if (!motivo) { e.preventDefault(); alert('Selecciona un motivo.'); return false; }
            if (isNaN(cant) || cant <= 0) { e.preventDefault(); alert('Ingresa una cantidad válida.'); return false; }
            if (cant > max) { e.preventDefault(); alert(`La cantidad (${cant}) excede el saldo (${max}).`); return false; }
            if (motivo.value === 'otro' && inputOtro.value.trim() === '') {
                e.preventDefault(); alert('Describe el motivo cuando seleccionas "Otro".'); return false;
            }
        });
    })();
    </script>
</body>
</html>