<?php
/**
 * Dashboard para departamentos con acceso a Inventario EPP
 * Ubicación: dashboard/inventario_epp/dashboard_epp.php
 * 
 * Estructura idéntica a dashboard/colaborativo/colaborativo.php
 * EXCLUSIVO para: Almacén de Refacciones, Seguridad, Contabilidad
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';

// ⭐ LLAMAR a verificar_sesion()
verificar_sesion();

// ⭐ Verificar expiración por inactividad
if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado por inactividad. Por favor inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

// ⭐ Renovar tiempo de actividad
actualizar_sesion();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

// Verificar permisos EPP
$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    establecer_alerta('error', 'No tienes acceso al módulo de Inventario de EPP.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];
$usuario_id = $_SESSION['usuario_id'];

// ====================================
// ESTADÍSTICAS DE SOLICITUDES (igual que colaborativo.php)
// ====================================
$stats = [
    'pendientes' => 0,
    'en_proceso' => 0,
    'finalizadas' => 0,
    'total' => 0
];

try {
    $pdo = conectarDB();
    
    // Contar solicitudes por estado
    $stmt = $pdo->prepare("
        SELECT estado, COUNT(*) as total 
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        GROUP BY estado
    ");
    $stmt->execute([$usuario_id]);
    
    while ($row = $stmt->fetch()) {
        if ($row['estado'] == 'pendiente') {
            $stats['pendientes'] = $row['total'];
        } elseif ($row['estado'] == 'en_proceso') {
            $stats['en_proceso'] = $row['total'];
        } elseif ($row['estado'] == 'finalizada') {
            $stats['finalizadas'] = $row['total'];
        }
    }
    
    $stats['total'] = $stats['pendientes'] + $stats['en_proceso'] + $stats['finalizadas'];
    
    // Obtener solicitudes recientes
    $stmt = $pdo->prepare("
        SELECT folio, descripcion, fecha_creacion, estado, prioridad
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $solicitudes_recientes = $stmt->fetchAll();
    
    // ====================================
    // ESTADÍSTICAS DE INVENTARIO EPP
    // ====================================
    $stats_epp = obtener_estadisticas_epp();
    
} catch (Exception $e) {
    $solicitudes_recientes = [];
    $stats_epp = [
        'total_articulos' => 0,
        'total_stock' => 0,
        'sin_stock' => 0,
        'movimientos_mes' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($departamento); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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

    <!-- ==========================================================
         ESTILOS SCOPED DEL DASHBOARD EPP (no afectan otras páginas)
         Todo el markup nuevo vive bajo .epp-dash con prefijo .epp-*
         Modo claro/oscuro vía variables propias + data-theme
    =========================================================== -->
    <style>
        .epp-dash {
            /* ----- Modo claro (por defecto) ----- */
            --epp-card-bg: #ffffff;
            --epp-panel-bg: #ffffff;
            --epp-text: #0f172a;
            --epp-text-muted: #64748b;
            --epp-border: rgba(15, 23, 42, 0.07);
            --epp-shadow: 0 10px 30px -14px rgba(2, 6, 23, 0.18);
            --epp-shadow-hover: 0 20px 42px -16px rgba(2, 6, 23, 0.28);
            --epp-track: #eef2f7;
            --epp-hero-grad: linear-gradient(135deg, #047857 0%, #10b981 48%, #34d399 100%);
            --epp-row-hover: #f8fafc;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--epp-text);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        body[data-theme="dark"] .epp-dash {
            /* ----- Modo oscuro ----- */
            --epp-card-bg: #1e293b;
            --epp-panel-bg: #1e293b;
            --epp-text: #e2e8f0;
            --epp-text-muted: #94a3b8;
            --epp-border: rgba(255, 255, 255, 0.08);
            --epp-shadow: 0 10px 30px -14px rgba(0, 0, 0, 0.55);
            --epp-shadow-hover: 0 20px 42px -16px rgba(0, 0, 0, 0.65);
            --epp-track: rgba(255, 255, 255, 0.06);
            --epp-hero-grad: linear-gradient(135deg, #064e3b 0%, #059669 48%, #10b981 100%);
            --epp-row-hover: rgba(255, 255, 255, 0.04);
        }

        /* ---------- Barra superior ---------- */
        .epp-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .epp-topbar__date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.92rem;
            font-weight: 500;
            color: var(--epp-text-muted);
        }
        .epp-topbar__date i { color: #10b981; }
        .epp-topbar__right {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        .epp-dept-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 999px;
            font-size: 0.86rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 6px 18px -8px rgba(16, 185, 129, 0.65);
            white-space: nowrap;
        }
        .epp-bell {
            position: relative;
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 14px;
            background: var(--epp-card-bg);
            color: var(--epp-text);
            box-shadow: var(--epp-shadow);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .epp-bell:hover { transform: translateY(-2px); box-shadow: var(--epp-shadow-hover); }
        .epp-bell .badge-count {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            line-height: 18px;
        }

        /* ---------- Hero de bienvenida (descartable) ---------- */
        .epp-hero {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 1.75rem 2rem;
            background: var(--epp-hero-grad);
            color: #fff;
            box-shadow: 0 18px 40px -18px rgba(4, 120, 87, 0.6);
        }
        .epp-hero::after {
            content: "";
            position: absolute;
            top: -60px;
            right: -40px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, transparent 70%);
            pointer-events: none;
        }
        .epp-hero__inner {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            z-index: 1;
        }
        .epp-hero__icon {
            flex-shrink: 0;
            width: 66px;
            height: 66px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .epp-hero__title {
            margin: 0;
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1.15;
        }
        .epp-hero__subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.95rem;
            font-weight: 400;
            opacity: 0.92;
        }

        /* ---------- Grid de tarjetas de estadísticas ---------- */
        .epp-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.1rem;
        }
        .epp-stat {
            --accent: #6366f1;
            --accent-soft: #e0e7ff;
            position: relative;
            overflow: hidden;
            background: var(--epp-card-bg);
            border: 1px solid var(--epp-border);
            border-radius: 18px;
            padding: 1.35rem;
            box-shadow: var(--epp-shadow);
            transition: transform .22s ease, box-shadow .22s ease;
        }
        .epp-stat::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--accent);
        }
        .epp-stat:hover { transform: translateY(-4px); box-shadow: var(--epp-shadow-hover); }
        .epp-stat__icon {
            width: 54px;
            height: 54px;
            border-radius: 15px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.9rem;
        }
        .epp-stat__num {
            margin: 0;
            font-size: 2.1rem;
            font-weight: 700;
            line-height: 1;
            color: var(--epp-text);
        }
        .epp-stat__label {
            margin: 0.4rem 0 0;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--epp-text-muted);
        }
        .epp-stat--amber  { --accent:#f59e0b; --accent-soft:#fef3c7; }
        .epp-stat--blue   { --accent:#3b82f6; --accent-soft:#dbeafe; }
        .epp-stat--green  { --accent:#10b981; --accent-soft:#d1fae5; }
        .epp-stat--indigo { --accent:#6366f1; --accent-soft:#e0e7ff; }
        body[data-theme="dark"] .epp-stat--amber  { --accent-soft: rgba(245,158,11,0.18); }
        body[data-theme="dark"] .epp-stat--blue   { --accent-soft: rgba(59,130,246,0.18); }
        body[data-theme="dark"] .epp-stat--green  { --accent-soft: rgba(16,185,129,0.18); }
        body[data-theme="dark"] .epp-stat--indigo { --accent-soft: rgba(99,102,241,0.18); }

        /* ---------- Panel genérico ---------- */
        .epp-panel {
            background: var(--epp-panel-bg);
            border: 1px solid var(--epp-border);
            border-radius: 20px;
            box-shadow: var(--epp-shadow);
            overflow: hidden;
        }
        .epp-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid var(--epp-border);
        }
        .epp-panel__title {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
            font-size: 1.02rem;
            font-weight: 600;
            color: var(--epp-text);
        }
        .epp-panel__title i { color: #10b981; }
        .epp-panel__body { padding: 1.4rem; }
        .epp-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            color: #059669;
            background: rgba(16, 185, 129, 0.12);
            transition: background .2s ease;
            white-space: nowrap;
        }
        .epp-link-btn:hover { background: rgba(16, 185, 129, 0.22); color: #047857; }

        /* ---------- Métricas EPP (panel destacado) ---------- */
        .epp-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .epp-metric {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1rem;
            border-radius: 15px;
            background: var(--epp-track);
        }
        .epp-metric__icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
            flex-shrink: 0;
        }
        .epp-metric__num { margin: 0; font-size: 1.55rem; font-weight: 700; line-height: 1; }
        .epp-metric__label { margin: 0.2rem 0 0; font-size: 0.8rem; color: var(--epp-text-muted); }

        /* ---------- Layout inferior (2 columnas) ---------- */
        .epp-columns {
            display: grid;
            grid-template-columns: 1.9fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        /* ---------- Acciones rápidas ---------- */
        .epp-actions { display: flex; flex-direction: column; gap: 0.75rem; }
        .epp-action {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.9rem 1rem;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            color: #fff;
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }
        .epp-action i { font-size: 1.15rem; }
        .epp-action:hover { transform: translateY(-2px); filter: brightness(1.05); color: #fff; }
        .epp-action--primary { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); box-shadow: 0 10px 24px -12px rgba(99,102,241,0.8); }
        .epp-action--green   { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 10px 24px -12px rgba(16,185,129,0.8); }
        .epp-action--teal    { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); box-shadow: 0 10px 24px -12px rgba(14,165,233,0.8); }

        /* ---------- Tabla de solicitudes recientes ---------- */
        .epp-table-wrap { overflow-x: auto; }
        .epp-table { width: 100%; border-collapse: collapse; }
        .epp-table thead th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            font-size: 0.74rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--epp-text-muted);
            border-bottom: 1px solid var(--epp-border);
            white-space: nowrap;
        }
        .epp-table tbody td {
            padding: 0.85rem 0.8rem;
            font-size: 0.9rem;
            color: var(--epp-text);
            border-bottom: 1px solid var(--epp-border);
            vertical-align: middle;
        }
        .epp-table tbody tr:last-child td { border-bottom: none; }
        .epp-table tbody tr { transition: background .15s ease; }
        .epp-table tbody tr:hover { background: var(--epp-row-hover); }
        .epp-folio { font-weight: 700; color: #059669; }
        .epp-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.7rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .epp-chip--pendiente  { background: rgba(245,158,11,0.15); color: #b45309; }
        .epp-chip--en_proceso { background: rgba(59,130,246,0.15); color: #1d4ed8; }
        .epp-chip--finalizada { background: rgba(16,185,129,0.15); color: #047857; }
        .epp-chip--critica { background: rgba(239,68,68,0.15);  color: #b91c1c; }
        .epp-chip--alta    { background: rgba(245,158,11,0.15); color: #b45309; }
        .epp-chip--media   { background: rgba(59,130,246,0.15); color: #1d4ed8; }
        .epp-chip--baja    { background: rgba(100,116,139,0.15); color: #475569; }
        body[data-theme="dark"] .epp-chip--pendiente  { color: #fcd34d; }
        body[data-theme="dark"] .epp-chip--en_proceso { color: #93c5fd; }
        body[data-theme="dark"] .epp-chip--finalizada { color: #6ee7b7; }
        body[data-theme="dark"] .epp-chip--critica { color: #fca5a5; }
        body[data-theme="dark"] .epp-chip--alta    { color: #fcd34d; }
        body[data-theme="dark"] .epp-chip--media   { color: #93c5fd; }
        body[data-theme="dark"] .epp-chip--baja    { color: #cbd5e1; }

        .epp-empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--epp-text-muted);
        }
        .epp-empty i { font-size: 2.2rem; display: block; margin-bottom: 0.6rem; opacity: 0.5; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) {
            .epp-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .epp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .epp-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .epp-grid { grid-template-columns: 1fr; }
            .epp-metrics { grid-template-columns: 1fr; }
            .epp-hero { padding: 1.4rem 1.25rem; }
            .epp-hero__title { font-size: 1.4rem; }
            .epp-hero__icon { width: 56px; height: 56px; font-size: 1.7rem; }
            .epp-topbar { justify-content: flex-start; }
        }
    </style>

    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_inventario.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <div class="epp-dash">

                    <!-- Barra superior: fecha + anuncios + departamento -->
                    <div class="epp-topbar">
                        <span class="epp-topbar__date">
                            <i class="bi bi-calendar3"></i>
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </span>
                        <div class="epp-topbar__right">
                            <button class="epp-bell" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
                                <i class="bi bi-megaphone-fill"></i>
                                <span class="badge-count" id="anunciosBadge"></span>
                            </button>
                            <span class="epp-dept-badge">
                                <i class="bi bi-shield-check"></i>
                                <?php echo htmlspecialchars($departamento); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Hero de bienvenida (fijo, siempre visible) -->
                    <div class="epp-hero">
                        <div class="epp-hero__inner">
                            <div class="epp-hero__icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <h1 class="epp-hero__title">&iexcl;Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h1>
                                <p class="epp-hero__subtitle">
                                    Panel de control de <?php echo htmlspecialchars($departamento); ?> &middot; Inventario EPP
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php mostrar_alerta(); // Se consume el flash de bienvenida del login SIN renderizarlo (elimina el recuadro negro) ?>

                    <!-- Tarjetas de estadísticas de solicitudes -->
                    <div class="epp-grid">
                        <div class="epp-stat epp-stat--amber">
                            <div class="epp-stat__icon"><i class="bi bi-clock-history"></i></div>
                            <h2 class="epp-stat__num"><?php echo $stats['pendientes']; ?></h2>
                            <p class="epp-stat__label">Pendientes</p>
                        </div>
                        <div class="epp-stat epp-stat--blue">
                            <div class="epp-stat__icon"><i class="bi bi-gear"></i></div>
                            <h2 class="epp-stat__num"><?php echo $stats['en_proceso']; ?></h2>
                            <p class="epp-stat__label">En Proceso</p>
                        </div>
                        <div class="epp-stat epp-stat--green">
                            <div class="epp-stat__icon"><i class="bi bi-check-circle"></i></div>
                            <h2 class="epp-stat__num"><?php echo $stats['finalizadas']; ?></h2>
                            <p class="epp-stat__label">Finalizadas</p>
                        </div>
                        <div class="epp-stat epp-stat--indigo">
                            <div class="epp-stat__icon"><i class="bi bi-file-earmark-text"></i></div>
                            <h2 class="epp-stat__num"><?php echo $stats['total']; ?></h2>
                            <p class="epp-stat__label">Total</p>
                        </div>
                    </div>

                    <!-- Resumen destacado de Inventario EPP -->
                    <div class="epp-panel">
                        <div class="epp-panel__head">
                            <h3 class="epp-panel__title"><i class="bi bi-shield-check"></i> Resumen Inventario EPP</h3>
                            <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="epp-link-btn">
                                Ver completo <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="epp-panel__body">
                            <div class="epp-metrics">
                                <div class="epp-metric">
                                    <div class="epp-metric__icon" style="background: linear-gradient(135deg,#10b981,#059669);">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div>
                                        <p class="epp-metric__num"><?php echo $stats_epp['total_articulos']; ?></p>
                                        <p class="epp-metric__label">Art&iacute;culos</p>
                                    </div>
                                </div>
                                <div class="epp-metric">
                                    <div class="epp-metric__icon" style="background: linear-gradient(135deg,#3b82f6,#2563eb);">
                                        <i class="bi bi-stack"></i>
                                    </div>
                                    <div>
                                        <p class="epp-metric__num"><?php echo $stats_epp['total_stock']; ?></p>
                                        <p class="epp-metric__label">Stock Total</p>
                                    </div>
                                </div>
                                <div class="epp-metric">
                                    <div class="epp-metric__icon" style="background: linear-gradient(135deg,<?php echo $stats_epp['sin_stock'] > 0 ? '#ef4444,#dc2626' : '#94a3b8,#64748b'; ?>);">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <p class="epp-metric__num" style="color: <?php echo $stats_epp['sin_stock'] > 0 ? '#dc2626' : 'inherit'; ?>;"><?php echo $stats_epp['sin_stock']; ?></p>
                                        <p class="epp-metric__label">Sin Stock</p>
                                    </div>
                                </div>
                                <div class="epp-metric">
                                    <div class="epp-metric__icon" style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </div>
                                    <div>
                                        <p class="epp-metric__num"><?php echo $stats_epp['movimientos_mes']; ?></p>
                                        <p class="epp-metric__label">Movimientos (Mes)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columnas: Solicitudes recientes + Acciones rápidas -->
                    <div class="epp-columns">

                        <!-- Solicitudes recientes -->
                        <div class="epp-panel">
                            <div class="epp-panel__head">
                                <h3 class="epp-panel__title"><i class="bi bi-clock-history"></i> Solicitudes Recientes</h3>
                            </div>
                            <div class="epp-panel__body">
                                <?php if (empty($solicitudes_recientes)): ?>
                                <div class="epp-empty">
                                    <i class="bi bi-inbox"></i>
                                    No tienes solicitudes a&uacute;n
                                </div>
                                <?php else: ?>
                                <div class="epp-table-wrap">
                                    <table class="epp-table">
                                        <thead>
                                            <tr>
                                                <th>Folio</th>
                                                <th>Descripci&oacute;n</th>
                                                <th>Fecha</th>
                                                <th>Estado</th>
                                                <th>Prioridad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($solicitudes_recientes as $sol): ?>
                                            <tr>
                                                <td><span class="epp-folio"><?php echo htmlspecialchars($sol['folio']); ?></span></td>
                                                <td><?php echo htmlspecialchars(mb_substr($sol['descripcion'], 0, 50)); ?>...</td>
                                                <td><?php echo date('d/m/Y', strtotime($sol['fecha_creacion'])); ?></td>
                                                <td>
                                                    <span class="epp-chip epp-chip--<?php echo $sol['estado']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $sol['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="epp-chip epp-chip--<?php echo htmlspecialchars($sol['prioridad']); ?>">
                                                        <?php echo ucfirst($sol['prioridad']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Acciones rápidas -->
                        <div class="epp-panel">
                            <div class="epp-panel__head">
                                <h3 class="epp-panel__title"><i class="bi bi-lightning-charge"></i> Acciones R&aacute;pidas</h3>
                            </div>
                            <div class="epp-panel__body">
                                <div class="epp-actions">
                                    <a href="#" class="epp-action epp-action--primary" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                        <i class="bi bi-plus-circle"></i> Nueva Solicitud de Atenci&oacute;n
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="epp-action epp-action--green">
                                        <i class="bi bi-box-seam"></i> Inventario de EPP
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="epp-action epp-action--teal">
                                        <i class="bi bi-clipboard-check"></i> &Oacute;rdenes de Mantenimiento
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>

                </div><!-- /.epp-dash -->

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // ---------- Cambio de tema (claro/oscuro) ----------
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

    <!-- Modal de Anuncios -->
    <?php include __DIR__ . '/../../includes/anuncios/modal_anuncios.php'; ?>

</body>
</html>