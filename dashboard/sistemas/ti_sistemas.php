<?php
/**
 * Dashboard para TI/Sistemas
 * Panel de control para gestionar todas las solicitudes
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

// Verificar que sea usuario de TI
if (!es_usuario_ti()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$usuario_id = $_SESSION['usuario_id'];

// Obtener estadísticas globales
try {
    $pdo = conectarDB();
    
    // Estadísticas generales
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total,
            SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas
        FROM solicitudes_atencion
    ");
    $stats = $stmt->fetch();
    
    // Solicitudes pendientes recientes (últimas 10)
    $stmt = $pdo->query("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.estado = 'pendiente'
        ORDER BY 
            CASE s.prioridad
                WHEN 'critica' THEN 1
                WHEN 'alta' THEN 2
                WHEN 'media' THEN 3
                WHEN 'baja' THEN 4
            END,
            s.fecha_creacion ASC
        LIMIT 10
    ");
    $pendientes = $stmt->fetchAll();
    
    // Solicitudes en proceso asignadas a este técnico
    $stmt = $pdo->prepare("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.estado = 'en_proceso' AND s.atendido_por = ?
        ORDER BY s.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $mis_asignadas = $stmt->fetchAll();
    
    // Solicitudes finalizadas hoy
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM solicitudes_atencion
        WHERE estado = 'finalizada' 
        AND DATE(fecha_actualizacion) = CURDATE()
    ");
    $finalizadas_hoy = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar estadísticas: ' . $e->getMessage());
    $stats = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0, 'criticas' => 0];
    $pendientes = [];
    $mis_asignadas = [];
    $finalizadas_hoy = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard TI/Sistemas</title>
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
         ESTILOS SCOPED DEL DASHBOARD SISTEMAS/TI (no afectan otras páginas)
         Temática: tecnología moderna (índigo/cian, rejilla, monoespaciado)
         Todo el markup nuevo vive bajo .sis-dash con prefijo .sis-*
         Modo claro/oscuro vía variables propias + data-theme
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
            --sis-shadow-hover: 0 20px 44px -16px rgba(37, 99, 235, 0.28);
            --sis-track: #f1f5f9;
            --sis-row-hover: #f5f8ff;
            --sis-grid-line: rgba(79, 70, 229, 0.07);
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
            --sis-shadow-hover: 0 20px 44px -16px rgba(8, 145, 178, 0.45);
            --sis-track: rgba(255, 255, 255, 0.04);
            --sis-row-hover: rgba(56, 189, 248, 0.08);
            --sis-grid-line: rgba(34, 211, 238, 0.07);
            --sis-hero-grad: linear-gradient(120deg, #1e1b4b 0%, #3730a3 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #3730a3 0%, #1d4ed8 55%, #0e7490 100%);
        }

        /* ---------- Hero / barra de mando tecnológica ---------- */
        .sis-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 1.6rem 1.9rem;
            background: var(--sis-hero-grad);
            color: #fff;
            box-shadow: 0 18px 42px -18px rgba(37, 99, 235, 0.6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        /* Rejilla tipo panel de control */
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
            top: -70px;
            right: -30px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.35) 0%, transparent 70%);
            pointer-events: none;
        }
        .sis-hero__left {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1.1rem;
        }
        .sis-hero__icon {
            flex-shrink: 0;
            width: 62px;
            height: 62px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.9rem;
        }
        .sis-hero__title {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .sis-hero__subtitle {
            margin: 0.3rem 0 0;
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.92;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .sis-hero__right {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sis-bell {
            position: relative;
            width: 44px;
            height: 44px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 13px;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: background .2s ease, transform .2s ease;
        }
        .sis-bell:hover { background: rgba(255, 255, 255, 0.26); transform: translateY(-2px); }
        .sis-bell .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
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
        .sis-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.32);
            white-space: nowrap;
        }
        .sis-role-badge i { color: #a5f3fc; }

        /* ---------- Grid de estadísticas ---------- */
        .sis-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.1rem;
        }
        .sis-stat {
            --accent: #6366f1;
            --accent-soft: rgba(99, 102, 241, 0.12);
            position: relative;
            overflow: hidden;
            background: var(--sis-card-bg);
            border: 1px solid var(--sis-border);
            border-radius: 16px;
            padding: 1.3rem;
            box-shadow: var(--sis-shadow);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        /* Línea de glow superior */
        .sis-stat::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0.9;
        }
        /* Textura de rejilla tenue */
        .sis-stat::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, var(--sis-grid-line) 1px, transparent 1px),
                linear-gradient(to bottom, var(--sis-grid-line) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
        .sis-stat:hover {
            transform: translateY(-4px);
            box-shadow: var(--sis-shadow-hover);
            border-color: var(--accent);
        }
        .sis-stat__top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.9rem;
        }
        .sis-stat__icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--accent-soft);
            color: var(--accent);
            border: 1px solid var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .sis-stat__dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }
        .sis-stat__num {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 2.1rem;
            font-weight: 700;
            line-height: 1;
            color: var(--sis-text);
        }
        .sis-stat__label {
            position: relative;
            z-index: 1;
            margin: 0.4rem 0 0;
            font-size: 0.82rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            color: var(--sis-text-muted);
        }
        .sis-stat--amber  { --accent:#f59e0b; --accent-soft:rgba(245,158,11,0.12); }
        .sis-stat--cyan   { --accent:#06b6d4; --accent-soft:rgba(6,182,212,0.12); }
        .sis-stat--green  { --accent:#10b981; --accent-soft:rgba(16,185,129,0.12); }
        .sis-stat--red    { --accent:#ef4444; --accent-soft:rgba(239,68,68,0.12); }

        /* ---------- Acciones rápidas ---------- */
        .sis-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .sis-action {
            --accent: #6366f1;
            --accent-soft: rgba(99, 102, 241, 0.12);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.95rem 1rem;
            border-radius: 14px;
            text-decoration: none;
            background: var(--sis-card-bg);
            border: 1px solid var(--sis-border);
            box-shadow: var(--sis-shadow);
            color: var(--sis-text);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .sis-action:hover {
            transform: translateY(-3px);
            box-shadow: var(--sis-shadow-hover);
            border-color: var(--accent);
            color: var(--sis-text);
        }
        .sis-action__icon {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--accent);
            background: var(--accent-soft);
            border: 1px solid var(--accent);
        }
        .sis-action__label {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.2;
        }
        .sis-action--indigo { --accent:#6366f1; --accent-soft:rgba(99,102,241,0.12); }
        .sis-action--cyan   { --accent:#06b6d4; --accent-soft:rgba(6,182,212,0.12); }
        .sis-action--blue   { --accent:#3b82f6; --accent-soft:rgba(59,130,246,0.12); }
        .sis-action--violet { --accent:#8b5cf6; --accent-soft:rgba(139,92,246,0.12); }

        /* ---------- Paneles ---------- */
        .sis-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        .sis-panel {
            background: var(--sis-panel-bg);
            border: 1px solid var(--sis-border);
            border-radius: 18px;
            box-shadow: var(--sis-shadow);
            overflow: hidden;
        }
        .sis-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.95rem 1.3rem;
            background: var(--sis-panel-head);
            color: #fff;
        }
        .sis-panel__title {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .sis-panel__body { padding: 0.8rem; }
        .sis-head-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: background .2s ease;
            white-space: nowrap;
        }
        .sis-head-link:hover { background: rgba(255, 255, 255, 0.34); color: #fff; }
        .sis-head-count {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* ---------- Lista de solicitudes ---------- */
        .sis-list { display: flex; flex-direction: column; gap: 0.35rem; }
        .sis-item {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 0.95rem 0.85rem 1.1rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--sis-text);
            transition: background .16s ease, transform .16s ease;
        }
        .sis-item::before {
            content: "";
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            border-radius: 3px;
            background: #06b6d4;
            transition: height .18s ease;
        }
        .sis-item:hover {
            background: var(--sis-row-hover);
            transform: translateX(2px);
            color: var(--sis-text);
        }
        .sis-item:hover::before { height: 62%; }
        .sis-item__folio {
            font-family: var(--sis-mono);
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--sis-text);
            letter-spacing: -0.01em;
        }
        .sis-item__meta {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            color: var(--sis-text-muted);
        }
        .sis-item__right {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            flex-shrink: 0;
        }
        .sis-item__chev {
            color: var(--sis-text-muted);
            font-size: 0.85rem;
            opacity: 0;
            transform: translateX(-4px);
            transition: opacity .16s ease, transform .16s ease;
        }
        .sis-item:hover .sis-item__chev { opacity: 1; transform: translateX(0); }

        .sis-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.26rem 0.68rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .sis-chip--critica { background: rgba(239,68,68,0.15);  color: #b91c1c; }
        .sis-chip--alta    { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--media   { background: rgba(6,182,212,0.15);  color: #0e7490; }
        .sis-chip--baja    { background: rgba(100,116,139,0.15);color: #475569; }
        .sis-chip--proceso { background: rgba(59,130,246,0.15); color: #1d4ed8; }
        body[data-theme="dark"] .sis-chip--critica { color: #fca5a5; }
        body[data-theme="dark"] .sis-chip--alta    { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--media   { color: #67e8f9; }
        body[data-theme="dark"] .sis-chip--baja    { color: #cbd5e1; }
        body[data-theme="dark"] .sis-chip--proceso { color: #93c5fd; }

        .sis-empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--sis-text-muted);
        }
        .sis-empty i { font-size: 2.1rem; display: block; margin-bottom: 0.55rem; opacity: 0.5; }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) {
            .sis-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 992px) {
            .sis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sis-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        /* En móvil se mantienen 2 columnas y las tarjetas se compactan */
        @media (max-width: 560px) {
            .sis-grid { gap: 0.75rem; }
            .sis-stat { padding: 1rem 0.9rem; border-radius: 14px; }
            .sis-stat__top { margin-bottom: 0.6rem; }
            .sis-stat__icon { width: 40px; height: 40px; font-size: 1.15rem; border-radius: 10px; }
            .sis-stat__num { font-size: 1.7rem; }
            .sis-stat__label { font-size: 0.76rem; }
            .sis-stat::after { background-size: 18px 18px; }

            .sis-hero { padding: 1.3rem 1.25rem; }
            .sis-hero__title { font-size: 1.35rem; }
            .sis-hero__icon { width: 54px; height: 54px; font-size: 1.6rem; }
            .sis-hero__right { width: 100%; justify-content: flex-start; }
        }
        /* Pantallas muy angostas: seguimos con 2 columnas, aún más compactas */
        @media (max-width: 380px) {
            .sis-grid { gap: 0.6rem; }
            .sis-stat { padding: 0.85rem 0.75rem; }
            .sis-stat__num { font-size: 1.5rem; }
            .sis-stat__icon { width: 36px; height: 36px; font-size: 1.05rem; }
        }
    </style>
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <div class="sis-dash">

                    <!-- Hero / barra de mando tecnológica -->
                    <div class="sis-hero">
                        <div class="sis-hero__left">
                            <div class="sis-hero__icon">
                                <i class="bi bi-cpu"></i>
                            </div>
                            <div>
                                <h1 class="sis-hero__title">&iexcl;Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h1>
                                <p class="sis-hero__subtitle">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo obtener_fecha_actual_espanol(); ?>
                                </p>
                            </div>
                        </div>
                        <div class="sis-hero__right">
                            <button class="sis-bell" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
                                <i class="bi bi-megaphone-fill"></i>
                                <span class="badge-count" id="anunciosBadge"></span>
                            </button>
                            <span class="sis-role-badge">
                                <i class="bi bi-shield-check"></i>
                                TI / Sistemas
                            </span>
                        </div>
                    </div>

                    <?php echo mostrar_alerta(); ?>

                    <!-- Tarjetas de estadísticas -->
                    <div class="sis-grid">
                        <div class="sis-stat sis-stat--amber">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-clock-history"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $stats['pendientes']; ?></h2>
                            <p class="sis-stat__label">Pendientes</p>
                        </div>

                        <div class="sis-stat sis-stat--cyan">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-gear"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $stats['en_proceso']; ?></h2>
                            <p class="sis-stat__label">En Proceso</p>
                        </div>

                        <div class="sis-stat sis-stat--green">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-check-circle"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $stats['finalizadas']; ?></h2>
                            <p class="sis-stat__label">Finalizadas</p>
                        </div>

                        <div class="sis-stat sis-stat--red">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-exclamation-triangle"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $stats['criticas']; ?></h2>
                            <p class="sis-stat__label">Cr&iacute;ticas</p>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="sis-panel">
                        <div class="sis-panel__head">
                            <h3 class="sis-panel__title"><i class="bi bi-lightning-charge"></i> Acciones R&aacute;pidas</h3>
                        </div>
                        <div class="sis-panel__body">
                            <div class="sis-actions">
                                <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="sis-action sis-action--indigo">
                                    <span class="sis-action__icon"><i class="bi bi-folder2-open"></i></span>
                                    <span class="sis-action__label">Todas las solicitudes</span>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="sis-action sis-action--cyan">
                                    <span class="sis-action__icon"><i class="bi bi-pc-display"></i></span>
                                    <span class="sis-action__label">Inventario de Equipos</span>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/acceso_equipos.php" class="sis-action sis-action--blue">
                                    <span class="sis-action__icon"><i class="bi bi-box-arrow-in-right"></i></span>
                                    <span class="sis-action__label">Acceso Equipos</span>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" class="sis-action sis-action--violet">
                                    <span class="sis-action__icon"><i class="bi bi-tools"></i></span>
                                    <span class="sis-action__label">Mantenimientos</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Paneles principales -->
                    <div class="sis-columns">

                        <!-- Solicitudes Pendientes -->
                        <div class="sis-panel">
                            <div class="sis-panel__head">
                                <h3 class="sis-panel__title"><i class="bi bi-clock-history"></i> Solicitudes Pendientes</h3>
                                <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php?estado=pendiente" class="sis-head-link">
                                    Ver todas <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="sis-panel__body">
                                <?php if (empty($pendientes)): ?>
                                <div class="sis-empty">
                                    <i class="bi bi-inboxes"></i>
                                    No hay solicitudes pendientes
                                </div>
                                <?php else: ?>
                                <div class="sis-list">
                                    <?php foreach ($pendientes as $sol): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" class="sis-item">
                                        <div class="flex-grow-1">
                                            <span class="sis-item__folio"><?php echo htmlspecialchars($sol['folio']); ?></span>
                                            <p class="sis-item__meta">
                                                <?php echo htmlspecialchars($sol['solicitante_nombre']); ?> &middot;
                                                <?php echo htmlspecialchars($sol['departamento']); ?>
                                            </p>
                                        </div>
                                        <div class="sis-item__right">
                                            <span class="sis-chip sis-chip--<?php echo htmlspecialchars($sol['prioridad']); ?>">
                                                <?php echo ucfirst($sol['prioridad']); ?>
                                            </span>
                                            <i class="bi bi-chevron-right sis-item__chev"></i>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mis Asignadas -->
                        <div class="sis-panel">
                            <div class="sis-panel__head">
                                <h3 class="sis-panel__title"><i class="bi bi-person-check"></i> Mis Asignadas</h3>
                                <span class="sis-head-count">
                                    <i class="bi bi-gear-wide-connected"></i>
                                    <?php echo count($mis_asignadas); ?> en proceso
                                </span>
                            </div>
                            <div class="sis-panel__body">
                                <?php if (empty($mis_asignadas)): ?>
                                <div class="sis-empty">
                                    <i class="bi bi-clipboard-check"></i>
                                    No tienes solicitudes asignadas
                                </div>
                                <?php else: ?>
                                <div class="sis-list">
                                    <?php foreach ($mis_asignadas as $sol): ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" class="sis-item">
                                        <div class="flex-grow-1">
                                            <span class="sis-item__folio"><?php echo htmlspecialchars($sol['folio']); ?></span>
                                            <p class="sis-item__meta">
                                                <?php echo htmlspecialchars($sol['solicitante_nombre']); ?>
                                            </p>
                                        </div>
                                        <div class="sis-item__right">
                                            <span class="sis-chip sis-chip--proceso">En proceso</span>
                                            <i class="bi bi-chevron-right sis-item__chev"></i>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div><!-- /.sis-dash -->

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

    <!-- Modal de Anuncios -->
    <?php include __DIR__ . '/../../includes/anuncios/modal_anuncios.php'; ?>

</body>
</html>