<?php
/**
 * Ver detalle de una solicitud
 * Muestra información completa de una solicitud específica
 * 
 * ⭐ CORREGIDO: Sidebar ahora incluye verificación de GTH y Mantenimiento
 * 
 * ACTUALIZADO:
 * - Historial completo de cambios de estado
 * - Botón "Editar Solicitud" para usuarios de Sistemas
 * - Muestra nombre_solicitante editable de la tabla
 * - Muestra archivos adjuntos (evidencia) con vista previa y descarga
 * - Rediseño con temática tecnológica (estilo dashboard TI/Sistemas)
 * - Las imágenes de evidencia se abren en modal (no en pestaña nueva)
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$es_ti = es_usuario_ti();

// Obtener folio de la URL
$folio = isset($_GET['folio']) ? limpiar_dato($_GET['folio']) : '';

if (empty($folio)) {
    establecer_alerta('error', 'Folio no especificado');
    redirigir(URL_BASE . 'solicitudes/listar.php');
}

// Obtener datos de la solicitud
try {
    $pdo = conectarDB();
    
    // Si es usuario normal, solo puede ver sus propias solicitudes
    // ACTUALIZADO: Usa COALESCE para mostrar nombre_solicitante o nombre de usuario
    if (!$es_ti) {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre,
                   u.nombre_completo as usuario_nombre
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.folio = ? AND s.usuario_id = ?
        ");
        $stmt->execute([$folio, $usuario_id]);
    } else {
        // TI puede ver todas las solicitudes
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre,
                   u.nombre_completo as usuario_nombre,
                   u.departamento as solicitante_depto,
                   t.nombre_completo as tecnico_nombre
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN usuarios t ON s.atendido_por = t.id
            WHERE s.folio = ?
        ");
        $stmt->execute([$folio]);
    }
    
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        establecer_alerta('error', 'Solicitud no encontrada o no tienes permiso para verla');
        redirigir(URL_BASE . 'solicitudes/listar.php');
    }
    
    // ====================================
    // OBTENER HISTORIAL COMPLETO DE ESTADOS
    // ====================================
    $stmt_historial = $pdo->prepare("
        SELECT h.*, u.nombre_completo as usuario_nombre
        FROM historial_estados h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.solicitud_id = ?
        ORDER BY h.fecha_cambio ASC
    ");
    $stmt_historial->execute([$solicitud['id']]);
    $historial = $stmt_historial->fetchAll();

    // ====================================
    // OBTENER ARCHIVOS ADJUNTOS
    // ====================================
    $stmt_archivos = $pdo->prepare("
        SELECT nombre_archivo, ruta_archivo, tipo_mime, tamanio, fecha_subida
        FROM archivos_adjuntos
        WHERE solicitud_id = ?
        ORDER BY fecha_subida ASC
    ");
    $stmt_archivos->execute([$solicitud['id']]);
    $archivos_adjuntos = $stmt_archivos->fetchAll();
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar la solicitud: ' . $e->getMessage());
    redirigir(URL_BASE . 'solicitudes/listar.php');
}

/**
 * Obtener clase de badge según estado
 */
function obtener_badge_estado($estado) {
    $badges = [
        'pendiente' => 'bg-warning text-dark',
        'en_proceso' => 'bg-info text-dark',
        'finalizada' => 'bg-success',
        'cancelada' => 'bg-secondary'
    ];
    return $badges[$estado] ?? 'bg-secondary';
}

/**
 * Obtener clase de badge según prioridad
 */
function obtener_badge_prioridad($prioridad) {
    $badges = [
        'critica' => 'bg-danger',
        'alta' => 'bg-warning text-dark',
        'media' => 'bg-info text-dark',
        'baja' => 'bg-secondary'
    ];
    return $badges[$prioridad] ?? 'bg-secondary';
}

/**
 * Obtener icono según tipo de soporte
 */
function obtener_icono_tipo($tipo) {
    return $tipo == 'Apoyo' ? 'bi-hand-thumbs-up' : 'bi-exclamation-triangle';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud <?php echo htmlspecialchars($solicitud['folio']); ?></title>
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

    <!-- ==========================================================
         ESTILOS SCOPED (no afectan otras páginas)
         Misma temática tecnológica del dashboard TI/Sistemas
         Prefijo .sis-* bajo el contenedor .sis-dash
    =========================================================== -->
    <style>
        .sis-dash {
            /* ----- Modo claro (por defecto) ----- */
            --sis-card-bg: #ffffff;
            --sis-panel-bg: #ffffff;
            --sis-text: #0f172a;
            --sis-text-muted: #64748b;
            --sis-border: rgba(15, 23, 42, 0.08);
            --sis-shadow: 0 10px 30px -14px rgba(2, 6, 23, 0.18);
            --sis-track: #f1f5f9;
            --sis-hero-grad: linear-gradient(120deg, #312e81 0%, #4338ca 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            --sis-mono: ui-monospace, 'SFMono-Regular', 'JetBrains Mono', Menlo, Consolas, monospace;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sis-text);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        body[data-theme="dark"] .sis-dash {
            /* ----- Modo oscuro ----- */
            --sis-card-bg: #131a2c;
            --sis-panel-bg: #131a2c;
            --sis-text: #e2e8f0;
            --sis-text-muted: #94a3b8;
            --sis-border: rgba(148, 163, 184, 0.16);
            --sis-shadow: 0 10px 30px -14px rgba(0, 0, 0, 0.6);
            --sis-track: rgba(255, 255, 255, 0.04);
            --sis-hero-grad: linear-gradient(120deg, #1e1b4b 0%, #3730a3 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #3730a3 0%, #1d4ed8 55%, #0e7490 100%);
        }

        /* ---------- Hero ---------- */
        .sis-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 1.5rem 1.9rem;
            background: var(--sis-hero-grad);
            color: #fff;
            box-shadow: 0 18px 42px -18px rgba(37, 99, 235, 0.6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .sis-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 26px 26px;
            mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            -webkit-mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            pointer-events: none;
        }
        .sis-hero::after {
            content: "";
            position: absolute;
            top: -70px; right: -30px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.35) 0%, transparent 70%);
            pointer-events: none;
        }
        .sis-hero__left { position: relative; z-index: 1; display: flex; align-items: center; gap: 1.1rem; }
        .sis-hero__icon {
            flex-shrink: 0;
            width: 58px; height: 58px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.7rem;
        }
        .sis-hero__title { margin: 0; font-size: 1.45rem; font-weight: 700; line-height: 1.1; }
        .sis-hero__subtitle { margin: 0.3rem 0 0; font-size: 0.88rem; opacity: 0.92; display: inline-flex; align-items: center; gap: 0.45rem; }
        .sis-hero__folio { font-family: var(--sis-mono); font-weight: 600; }
        .sis-hero__right { position: relative; z-index: 1; display: inline-flex; gap: 0.6rem; flex-wrap: wrap; }
        .sis-hero-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 11px;
            font-size: 0.84rem; font-weight: 600;
            text-decoration: none; color: #fff;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.32);
            transition: background .2s ease, transform .2s ease;
            white-space: nowrap;
        }
        .sis-hero-btn:hover { background: rgba(255, 255, 255, 0.3); color: #fff; transform: translateY(-2px); }
        .sis-hero-btn--solid { background: rgba(255,255,255,0.92); color: #1e1b4b; border-color: transparent; }
        .sis-hero-btn--solid:hover { background: #fff; color: #1e1b4b; }

        /* ---------- Layout ---------- */
        .sis-columns { display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; align-items: start; }

        /* ---------- Panel ---------- */
        .sis-panel {
            background: var(--sis-panel-bg);
            border: 1px solid var(--sis-border);
            border-radius: 18px;
            box-shadow: var(--sis-shadow);
            overflow: hidden;
        }
        .sis-panel + .sis-panel { margin-top: 1.25rem; }
        .sis-panel__head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
            padding: 0.95rem 1.3rem;
            background: var(--sis-panel-head);
            color: #fff; font-size: 1rem; font-weight: 600;
        }
        .sis-panel__head-title { display: inline-flex; align-items: center; gap: 0.6rem; }
        .sis-panel__body { padding: 1.4rem; }

        /* ---------- Chips ---------- */
        .sis-chip {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem; font-weight: 600;
            white-space: nowrap;
        }
        .sis-chip--critica    { background: rgba(239,68,68,0.15);  color: #b91c1c; }
        .sis-chip--alta       { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--media      { background: rgba(6,182,212,0.15);  color: #0e7490; }
        .sis-chip--baja       { background: rgba(100,116,139,0.15);color: #475569; }
        .sis-chip--pendiente  { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--en_proceso { background: rgba(59,130,246,0.15); color: #1d4ed8; }
        .sis-chip--finalizada { background: rgba(16,185,129,0.15); color: #047857; }
        .sis-chip--cancelada  { background: rgba(100,116,139,0.15);color: #475569; }
        .sis-chip--apoyo      { background: rgba(6,182,212,0.15);  color: #0e7490; }
        .sis-chip--incidencia { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--white      { background: rgba(255,255,255,0.22); color: #fff; }
        body[data-theme="dark"] .sis-chip--critica    { color: #fca5a5; }
        body[data-theme="dark"] .sis-chip--alta       { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--media      { color: #67e8f9; }
        body[data-theme="dark"] .sis-chip--baja       { color: #cbd5e1; }
        body[data-theme="dark"] .sis-chip--pendiente  { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--en_proceso { color: #93c5fd; }
        body[data-theme="dark"] .sis-chip--finalizada { color: #6ee7b7; }
        body[data-theme="dark"] .sis-chip--cancelada  { color: #cbd5e1; }
        body[data-theme="dark"] .sis-chip--apoyo      { color: #67e8f9; }
        body[data-theme="dark"] .sis-chip--incidencia { color: #fcd34d; }

        /* ---------- Bloques de información ---------- */
        .sis-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .sis-info-block {
            background: var(--sis-track);
            border-left: 3px solid #6366f1;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .sis-info-block:last-child { margin-bottom: 0; }
        .sis-info-block__label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--sis-text-muted); margin-bottom: 0.3rem;
        }
        .sis-info-block__value { font-size: 0.95rem; color: var(--sis-text); word-break: break-word; }

        .sis-alert {
            padding: 0.9rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        .sis-alert--info {
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: var(--sis-text);
        }
        .sis-alert--info i { color: #0891b2; }
        body[data-theme="dark"] .sis-alert--info i { color: #67e8f9; }

        /* ---------- Evidencia adjunta ---------- */
        .sis-archivos-label {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.8rem; font-weight: 600;
            color: var(--sis-text); margin-bottom: 0.6rem;
        }
        .sis-archivos-label i { color: #6366f1; }
        .sis-archivos { display: flex; flex-direction: column; }
        .sis-archivo {
            display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--sis-border);
        }
        .sis-archivo:last-child { border-bottom: none; }
        .sis-archivo__left { display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
        .sis-archivo__thumb {
            width: 46px; height: 46px; object-fit: cover;
            border-radius: 9px; cursor: pointer;
            border: 1px solid var(--sis-border);
            transition: transform 0.2s ease;
        }
        .sis-archivo__thumb:hover { transform: scale(1.08); }
        .sis-archivo__icon {
            width: 46px; height: 46px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 9px;
            background: rgba(99, 102, 241, 0.12);
            color: #6366f1; font-size: 1.3rem;
        }
        .sis-archivo__name { font-weight: 600; font-size: 0.88rem; color: var(--sis-text); word-break: break-word; }
        .sis-archivo__meta { font-size: 0.74rem; color: var(--sis-text-muted); }
        .sis-archivo__actions { display: inline-flex; gap: 0.35rem; flex-shrink: 0; }
        .sis-file-btn {
            width: 34px; height: 34px; border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.9rem; text-decoration: none;
            border: 1px solid; cursor: pointer; background: transparent;
            transition: transform .18s ease, filter .18s ease;
        }
        .sis-file-btn:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .sis-file-btn--ver { color: #2563eb; border-color: rgba(37,99,235,0.4); background: rgba(37,99,235,0.1); }
        .sis-file-btn--descargar { color: #059669; border-color: rgba(16,185,129,0.4); background: rgba(16,185,129,0.1); }
        body[data-theme="dark"] .sis-file-btn--ver { color: #93c5fd; }
        body[data-theme="dark"] .sis-file-btn--descargar { color: #6ee7b7; }

        /* ---------- Info del solicitante ---------- */
        .sis-solicitante-row { padding: 0.6rem 0; border-bottom: 1px solid var(--sis-border); }
        .sis-solicitante-row:first-child { padding-top: 0; }
        .sis-solicitante-row:last-child { border-bottom: none; padding-bottom: 0; }
        .sis-solicitante-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--sis-text-muted); margin-bottom: 0.2rem; }
        .sis-solicitante-value { font-size: 0.94rem; color: var(--sis-text); }

        /* ---------- Timeline ---------- */
        .sis-timeline { position: relative; padding-left: 28px; }
        .sis-timeline::before {
            content: ''; position: absolute; left: 7px; top: 4px; bottom: 4px;
            width: 2px; background: var(--sis-border);
        }
        .sis-tl-item { position: relative; margin-bottom: 1.3rem; }
        .sis-tl-item:last-child { margin-bottom: 0; }
        .sis-tl-item::before {
            content: ''; position: absolute; left: -26px; top: 3px;
            width: 13px; height: 13px; border-radius: 50%;
            background: #6366f1; border: 3px solid var(--sis-panel-bg);
            box-shadow: 0 0 0 2px #6366f1;
        }
        .sis-tl-item.estado-pendiente::before  { background: #f59e0b; box-shadow: 0 0 0 2px #f59e0b; }
        .sis-tl-item.estado-en_proceso::before { background: #3b82f6; box-shadow: 0 0 0 2px #3b82f6; }
        .sis-tl-item.estado-finalizada::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; }
        .sis-tl-item.estado-cancelada::before  { background: #64748b; box-shadow: 0 0 0 2px #64748b; }
        .sis-tl-title { font-weight: 600; font-size: 0.92rem; color: var(--sis-text); }
        .sis-tl-comment { font-size: 0.82rem; color: var(--sis-text-muted); margin: 0.25rem 0; }
        .sis-tl-meta { font-size: 0.78rem; color: var(--sis-text-muted); }
        .sis-tl-meta i { opacity: 0.8; }

        /* ---------- Modal de imagen ---------- */
        .sis-img-modal { border: none; border-radius: 16px; overflow: hidden; }
        .sis-img-modal .modal-header, .sis-img-modal .modal-footer { border-color: rgba(0,0,0,0.08); }
        .sis-img-modal .modal-title { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 1rem; font-weight: 600; }
        .sis-img-modal__img { max-width: 100%; max-height: 72vh; border-radius: 8px; }
        .sis-img-modal__download {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.2rem; border-radius: 11px;
            font-size: 0.9rem; font-weight: 600; text-decoration: none; color: #fff;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
        }
        body[data-theme="dark"] .sis-img-modal { background: #131a2c; color: #e2e8f0; }
        body[data-theme="dark"] .sis-img-modal .modal-header,
        body[data-theme="dark"] .sis-img-modal .modal-footer { border-color: rgba(255,255,255,0.1); }
        body[data-theme="dark"] .sis-img-modal .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) {
            .sis-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .sis-info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .sis-hero { padding: 1.3rem 1.25rem; }
            .sis-hero__title { font-size: 1.25rem; }
            .sis-hero__icon { width: 50px; height: 50px; font-size: 1.5rem; }
            .sis-hero__right { width: 100%; }
            .sis-hero-btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR ⭐ CORREGIDO: Ahora incluye verificación de GTH y Mantenimiento -->
        <?php 
        if (es_usuario_ti()) {
            include __DIR__ . '/../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../includes/sidebar/sidebar_gth.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../includes/sidebar/sidebar_colaborativo.php';
        } elseif (es_usuario_epp()) {
            include __DIR__ . '/../includes/sidebar/sidebar_inventario.php';
        } elseif (es_usuario_gth()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
        } elseif (es_mantenimiento()) {
            include __DIR__ . '/../includes/sidebar/sidebar_mantenimiento.php';
        } elseif (in_array(strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''), ['logistica', 'almacen_residuos'])) {
            // El módulo SEC puede no estar desplegado aún en Producción: respaldo a sidebar_normal.php
            $sidebar_sec = __DIR__ . '/../includes/sidebar/sidebar_sec.php';
            if (file_exists($sidebar_sec)) {
                include $sidebar_sec;
            } else {
                include __DIR__ . '/../includes/sidebar/sidebar_normal.php';
            }
        } else {
            include __DIR__ . '/../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <div class="sis-dash">

                    <!-- Hero -->
                    <div class="sis-hero">
                        <div class="sis-hero__left">
                            <div class="sis-hero__icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div>
                                <h1 class="sis-hero__title">Detalle de Solicitud</h1>
                                <p class="sis-hero__subtitle">
                                    <i class="bi bi-hash"></i>
                                    <span class="sis-hero__folio"><?php echo htmlspecialchars($solicitud['folio']); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="sis-hero__right">
                            <?php if ($es_ti && $solicitud['estado'] !== 'finalizada' && $solicitud['estado'] !== 'cancelada'): ?>
                            <!-- BOTÓN EDITAR SOLICITUD (SOLO SISTEMAS) -->
                            <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($folio); ?>" 
                               class="sis-hero-btn sis-hero-btn--solid">
                                <i class="bi bi-pencil-square"></i> Editar Solicitud
                            </a>
                            <?php endif; ?>

                            <?php if ($es_ti): ?>
                            <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="sis-hero-btn">
                                <i class="bi bi-arrow-left"></i> Volver a la lista
                            </a>
                            <?php else: ?>
                            <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="sis-hero-btn">
                                <i class="bi bi-arrow-left"></i> Volver a mis solicitudes
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php echo mostrar_alerta(); ?>

                    <div class="sis-columns">

                        <!-- Columna Principal: Info de la Solicitud -->
                        <div>
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <span class="sis-panel__head-title">
                                        <i class="<?php echo obtener_icono_tipo($solicitud['tipo_soporte']); ?>"></i>
                                        Informaci&oacute;n de la Solicitud
                                    </span>
                                    <span class="sis-chip sis-chip--<?php echo htmlspecialchars($solicitud['estado']); ?> sis-chip--white">
                                        <?php echo obtener_texto_estado($solicitud['estado']); ?>
                                    </span>
                                </div>
                                <div class="sis-panel__body">

                                    <!-- Fila de chips -->
                                    <div class="mb-4 d-flex gap-2 flex-wrap">
                                        <span class="sis-chip <?php echo $solicitud['tipo_soporte'] == 'Apoyo' ? 'sis-chip--apoyo' : 'sis-chip--incidencia'; ?>">
                                            <i class="<?php echo obtener_icono_tipo($solicitud['tipo_soporte']); ?>"></i>
                                            <?php echo htmlspecialchars($solicitud['tipo_soporte']); ?>
                                        </span>
                                        <span class="sis-chip sis-chip--<?php echo htmlspecialchars($solicitud['prioridad']); ?>">
                                            Prioridad: <?php echo ucfirst($solicitud['prioridad']); ?>
                                        </span>
                                    </div>

                                    <!-- Info en bloques -->
                                    <div class="sis-info-grid mb-3">
                                        <div class="sis-info-block" style="margin-bottom:0;">
                                            <div class="sis-info-block__label">Tipo de Soporte</div>
                                            <div class="sis-info-block__value"><?php echo htmlspecialchars($solicitud['tipo_soporte']); ?></div>
                                        </div>
                                        <div class="sis-info-block" style="margin-bottom:0;">
                                            <div class="sis-info-block__label">
                                                <?php echo $solicitud['tipo_soporte'] == 'Apoyo' ? 'Tipo de Apoyo' : 'Tipo de Problema'; ?>
                                            </div>
                                            <div class="sis-info-block__value">
                                                <?php 
                                                if ($solicitud['tipo_soporte'] == 'Apoyo') {
                                                    echo htmlspecialchars($solicitud['tipo_apoyo'] ?? 'No especificado');
                                                } else {
                                                    echo htmlspecialchars($solicitud['tipo_problema'] ?? 'No especificado');
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Descripción -->
                                    <div class="sis-info-block">
                                        <div class="sis-info-block__label">Descripci&oacute;n</div>
                                        <div class="sis-info-block__value">
                                            <?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?>
                                        </div>
                                    </div>

                                    <!-- Comentarios de TI (si existen) -->
                                    <?php if (!empty($solicitud['comentarios_ti'])): ?>
                                    <div class="sis-alert sis-alert--info">
                                        <strong><i class="bi bi-chat-left-text"></i> Comentarios de TI:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($solicitud['comentarios_ti'])); ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- ====================================== -->
                                    <!-- ARCHIVOS ADJUNTOS (EVIDENCIA)          -->
                                    <!-- ====================================== -->
                                    <?php if (!empty($archivos_adjuntos)): ?>
                                    <div class="sis-info-block mt-3">
                                        <div class="sis-archivos-label">
                                            <i class="bi bi-paperclip"></i> Evidencia adjunta
                                            <span class="sis-chip sis-chip--baja"><?php echo count($archivos_adjuntos); ?></span>
                                        </div>
                                        <div class="sis-archivos">
                                            <?php foreach ($archivos_adjuntos as $archivo): ?>
                                            <?php
                                                // Determinar ícono según tipo MIME
                                                $icono = 'bi-file-earmark';
                                                if (str_starts_with($archivo['tipo_mime'], 'image/')) {
                                                    $icono = 'bi-file-earmark-image';
                                                } elseif ($archivo['tipo_mime'] === 'application/pdf') {
                                                    $icono = 'bi-file-earmark-pdf';
                                                } elseif (str_contains($archivo['tipo_mime'], 'word') || str_contains($archivo['tipo_mime'], 'document')) {
                                                    $icono = 'bi-file-earmark-word';
                                                } elseif ($archivo['tipo_mime'] === 'text/plain') {
                                                    $icono = 'bi-file-earmark-text';
                                                }
                                                
                                                // Formatear tamaño
                                                $tamanio_kb = round($archivo['tamanio'] / 1024, 1);
                                                $tamanio_texto = $tamanio_kb >= 1024 
                                                    ? round($tamanio_kb / 1024, 1) . ' MB' 
                                                    : $tamanio_kb . ' KB';
                                                
                                                // Verificar si es imagen para preview
                                                $es_imagen = str_starts_with($archivo['tipo_mime'], 'image/');
                                                $ruta_url = URL_BASE . $archivo['ruta_archivo'];
                                            ?>
                                            <div class="sis-archivo">
                                                <div class="sis-archivo__left">
                                                    <?php if ($es_imagen): ?>
                                                    <img src="<?php echo $ruta_url; ?>" 
                                                         alt="Preview" 
                                                         class="sis-archivo__thumb js-ver-imagen"
                                                         data-img-url="<?php echo htmlspecialchars($ruta_url); ?>"
                                                         data-img-nombre="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                                    <?php else: ?>
                                                    <div class="sis-archivo__icon">
                                                        <i class="<?php echo $icono; ?>"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="sis-archivo__name">
                                                            <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>
                                                        </div>
                                                        <div class="sis-archivo__meta">
                                                            <?php echo $tamanio_texto; ?> &middot; 
                                                            <?php echo date('d/m/Y H:i', strtotime($archivo['fecha_subida'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="sis-archivo__actions">
                                                    <?php if ($es_imagen): ?>
                                                    <!-- Imagen: abre en modal, no en pestaña nueva -->
                                                    <button type="button"
                                                            class="sis-file-btn sis-file-btn--ver js-ver-imagen"
                                                            title="Ver imagen"
                                                            data-img-url="<?php echo htmlspecialchars($ruta_url); ?>"
                                                            data-img-nombre="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <!-- Otros tipos (PDF, doc, etc.): se abren en pestaña -->
                                                    <a href="<?php echo $ruta_url; ?>" 
                                                       target="_blank" 
                                                       class="sis-file-btn sis-file-btn--ver" 
                                                       title="Ver archivo">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="<?php echo $ruta_url; ?>" 
                                                       download="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>" 
                                                       class="sis-file-btn sis-file-btn--descargar" 
                                                       title="Descargar">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha: Solicitante + Timeline -->
                        <div>

                            <!-- Info del Solicitante -->
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <span class="sis-panel__head-title">
                                        <i class="bi bi-person-circle"></i> Informaci&oacute;n del Solicitante
                                    </span>
                                </div>
                                <div class="sis-panel__body">
                                    <div class="sis-solicitante-row">
                                        <div class="sis-solicitante-label">Nombre</div>
                                        <div class="sis-solicitante-value"><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></div>
                                    </div>
                                    <div class="sis-solicitante-row">
                                        <div class="sis-solicitante-label">Departamento</div>
                                        <div class="sis-solicitante-value"><?php echo htmlspecialchars($solicitud['departamento']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeline / Historial -->
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <span class="sis-panel__head-title">
                                        <i class="bi bi-clock-history"></i> Historial
                                    </span>
                                </div>
                                <div class="sis-panel__body">
                                    <div class="sis-timeline">

                                        <?php if (!empty($historial)): ?>
                                            <!-- Historial completo desde la tabla historial_estados -->
                                            <?php foreach ($historial as $evento): ?>
                                            <?php 
                                                $estado_clase = $evento['estado_nuevo'] ?? '';
                                                if (empty($evento['estado_anterior'])) {
                                                    $titulo = 'Solicitud creada';
                                                } else {
                                                    $titulo = 'Estado: ' . obtener_texto_estado($evento['estado_nuevo']);
                                                }
                                            ?>
                                            <div class="sis-tl-item estado-<?php echo $estado_clase; ?>">
                                                <div class="sis-tl-title"><?php echo $titulo; ?></div>
                                                <?php if (!empty($evento['comentario']) && $evento['comentario'] !== 'Solicitud creada'): ?>
                                                <p class="sis-tl-comment">
                                                    <?php echo nl2br(htmlspecialchars($evento['comentario'])); ?>
                                                </p>
                                                <?php endif; ?>
                                                <p class="sis-tl-meta mb-0">
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($evento['usuario_nombre'] ?? 'Sistema'); ?>
                                                    <br>
                                                    <?php echo formatear_fecha($evento['fecha_cambio'], true); ?>
                                                </p>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <!-- Historial básico si no hay registros en la tabla -->
                                            <!-- Creación -->
                                            <div class="sis-tl-item">
                                                <div class="sis-tl-title">Solicitud creada</div>
                                                <p class="sis-tl-meta mb-0">
                                                    <?php echo formatear_fecha($solicitud['fecha_creacion'], true); ?>
                                                </p>
                                            </div>

                                            <!-- Actualización -->
                                            <?php if ($solicitud['fecha_actualizacion']): ?>
                                            <div class="sis-tl-item">
                                                <div class="sis-tl-title">&Uacute;ltima actualizaci&oacute;n</div>
                                                <p class="sis-tl-meta mb-0">
                                                    <?php echo formatear_fecha($solicitud['fecha_actualizacion'], true); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Atención -->
                                            <?php if ($solicitud['fecha_atencion']): ?>
                                            <div class="sis-tl-item">
                                                <div class="sis-tl-title">Atendida por</div>
                                                <p class="sis-tl-meta mb-0">
                                                    <?php echo htmlspecialchars($solicitud['tecnico_nombre'] ?? 'TI'); ?><br>
                                                    <?php echo formatear_fecha($solicitud['fecha_atencion'], true); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- /.sis-dash -->

            </div>
        </main>

    </div>

    <!-- ====================================== -->
    <!-- MODAL VISOR DE IMAGEN (evidencia)      -->
    <!-- ====================================== -->
    <div class="modal fade" id="modalImagen" tabindex="-1" aria-labelledby="modalImagenLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content sis-img-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImagenLabel">
                        <i class="bi bi-image"></i> <span id="modalImagenNombre">Evidencia</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImagenImg" src="" alt="Evidencia adjunta" class="sis-img-modal__img">
                </div>
                <div class="modal-footer">
                    <a id="modalImagenDescargar" href="#" download class="sis-img-modal__download">
                        <i class="bi bi-download"></i> Descargar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/modal_crear.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
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

        // ====================================
        // VISOR DE IMÁGENES EN MODAL
        // ====================================
        (function () {
            const modalEl = document.getElementById('modalImagen');
            if (!modalEl) return;
            const modal = new bootstrap.Modal(modalEl);
            const img = document.getElementById('modalImagenImg');
            const nombreEl = document.getElementById('modalImagenNombre');
            const descargar = document.getElementById('modalImagenDescargar');

            function abrirImagenModal(url, nombre) {
                img.src = url;
                img.alt = nombre || 'Evidencia adjunta';
                nombreEl.textContent = nombre || 'Evidencia';
                descargar.href = url;
                descargar.setAttribute('download', nombre || '');
                modal.show();
            }

            // Miniaturas y botón "Ver imagen" comparten data-img-url / data-img-nombre
            document.querySelectorAll('.js-ver-imagen').forEach(function (el) {
                el.addEventListener('click', function () {
                    abrirImagenModal(el.dataset.imgUrl, el.dataset.imgNombre);
                });
            });

            // Liberar la imagen al cerrar (evita "parpadeo" de la anterior)
            modalEl.addEventListener('hidden.bs.modal', function () {
                img.src = '';
            });
        })();
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

</body>
</html>