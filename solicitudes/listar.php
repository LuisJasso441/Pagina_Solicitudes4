<?php
/**
 * Listar solicitudes del usuario
 * Muestra solo las solicitudes del usuario logueado
 * 
 * ⭐ CORREGIDO: Sidebar ahora incluye verificación de GTH
 * 
 * ACTUALIZADO:
 * - Rediseño con temática tecnológica (estilo dashboard TI/Sistemas)
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];

// Parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['buscar']) ? limpiar_dato($_GET['buscar']) : '';

// Construir consulta
try {
    $pdo = conectarDB();
    
    $sql = "SELECT * FROM solicitudes_atencion WHERE usuario_id = ?";
    $params = [$usuario_id];
    
    // Aplicar filtro de estado
    if (!empty($filtro_estado)) {
        $sql .= " AND estado = ?";
        $params[] = $filtro_estado;
    }
    
    // Aplicar búsqueda por folio o descripción
    if (!empty($busqueda)) {
        $sql .= " AND (folio LIKE ? OR descripcion LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $sql .= " ORDER BY fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    // Contar por estado
    $stmt_count = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total
        FROM solicitudes_atencion 
        WHERE usuario_id = ?
    ");
    $stmt_count->execute([$usuario_id]);
    $contadores = $stmt_count->fetch();
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar solicitudes: ' . $e->getMessage());
    $solicitudes = [];
    $contadores = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0];
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
 * Obtener texto del estado
 */
function obtener_texto_estado_custom($estado) {
    $textos = [
        'pendiente' => 'Pendiente',
        'en_proceso' => 'En Proceso',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada'
    ];
    return $textos[$estado] ?? $estado;
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
 * Obtener texto de prioridad
 */
function obtener_texto_prioridad($prioridad) {
    $textos = [
        'critica' => 'Crítica',
        'alta' => 'Alta',
        'media' => 'Media',
        'baja' => 'Baja'
    ];
    return $textos[$prioridad] ?? ucfirst($prioridad);
}

// ¿Hay filtros activos?
$hay_filtros = !empty($filtro_estado) || $busqueda !== '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOLICITUDES PARA SISTEMAS - <?php echo htmlspecialchars($departamento); ?></title>
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
            --sis-shadow-hover: 0 20px 44px -16px rgba(37, 99, 235, 0.28);
            --sis-track: #f1f5f9;
            --sis-row-hover: #f5f8ff;
            --sis-grid-line: rgba(79, 70, 229, 0.07);
            --sis-hero-grad: linear-gradient(120deg, #312e81 0%, #4338ca 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            --sis-input-bg: #ffffff;
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
            --sis-input-bg: #0f1626;
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
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
        }
        .sis-hero::before {
            content: ""; position: absolute; inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 26px 26px;
            mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            -webkit-mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            pointer-events: none;
        }
        .sis-hero::after {
            content: ""; position: absolute; top: -70px; right: -30px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.35) 0%, transparent 70%);
            pointer-events: none;
        }
        .sis-hero__left { position: relative; z-index: 1; display: flex; align-items: center; gap: 1.1rem; }
        .sis-hero__icon {
            flex-shrink: 0; width: 58px; height: 58px; border-radius: 15px;
            background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.28);
            display: flex; align-items: center; justify-content: center; font-size: 1.7rem;
        }
        .sis-hero__title { margin: 0; font-size: 1.45rem; font-weight: 700; line-height: 1.1; }
        .sis-hero__subtitle { margin: 0.3rem 0 0; font-size: 0.88rem; opacity: 0.92; display: inline-flex; align-items: center; gap: 0.45rem; }
        .sis-hero__right { position: relative; z-index: 1; }
        .sis-hero-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.1rem; border-radius: 11px;
            font-size: 0.86rem; font-weight: 600; text-decoration: none;
            color: #1e1b4b; background: rgba(255, 255, 255, 0.92); border: 1px solid transparent;
            transition: background .2s ease, transform .2s ease; white-space: nowrap;
        }
        .sis-hero-btn:hover { background: #fff; color: #1e1b4b; transform: translateY(-2px); }

        /* ---------- Grid de estadísticas ---------- */
        .sis-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.1rem; }
        .sis-stat {
            --accent: #6366f1; --accent-soft: rgba(99, 102, 241, 0.12);
            position: relative; overflow: hidden;
            background: var(--sis-card-bg); border: 1px solid var(--sis-border);
            border-radius: 16px; padding: 1.3rem; box-shadow: var(--sis-shadow);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .sis-stat::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }
        .sis-stat::after {
            content: ""; position: absolute; inset: 0;
            background-image:
                linear-gradient(to right, var(--sis-grid-line) 1px, transparent 1px),
                linear-gradient(to bottom, var(--sis-grid-line) 1px, transparent 1px);
            background-size: 22px 22px; pointer-events: none;
        }
        .sis-stat:hover { transform: translateY(-4px); box-shadow: var(--sis-shadow-hover); border-color: var(--accent); }
        .sis-stat__top { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.9rem; }
        .sis-stat__icon {
            width: 48px; height: 48px; border-radius: 12px;
            background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent);
            display: flex; align-items: center; justify-content: center; font-size: 1.35rem;
        }
        .sis-stat__dot { width: 9px; height: 9px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
        .sis-stat__num { position: relative; z-index: 1; margin: 0; font-size: 2.1rem; font-weight: 700; line-height: 1; color: var(--sis-text); }
        .sis-stat__label { position: relative; z-index: 1; margin: 0.4rem 0 0; font-size: 0.82rem; font-weight: 500; color: var(--sis-text-muted); }
        .sis-stat--amber  { --accent:#f59e0b; --accent-soft:rgba(245,158,11,0.12); }
        .sis-stat--cyan   { --accent:#06b6d4; --accent-soft:rgba(6,182,212,0.12); }
        .sis-stat--green  { --accent:#10b981; --accent-soft:rgba(16,185,129,0.12); }
        .sis-stat--indigo { --accent:#6366f1; --accent-soft:rgba(99,102,241,0.12); }

        /* ---------- Panel ---------- */
        .sis-panel { background: var(--sis-panel-bg); border: 1px solid var(--sis-border); border-radius: 18px; box-shadow: var(--sis-shadow); overflow: hidden; }
        .sis-panel__head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
            padding: 0.95rem 1.3rem; background: var(--sis-panel-head); color: #fff; flex-wrap: wrap;
        }
        .sis-panel__title { display: inline-flex; align-items: center; gap: 0.6rem; margin: 0; font-size: 1rem; font-weight: 600; }
        .sis-panel__body { padding: 1.2rem; }
        .sis-panel__body--flush { padding: 0; }
        .sis-head-count {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.76rem; font-weight: 600;
            background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* ---------- Filtros ---------- */
        .sis-filtros-label {
            display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.03em;
            text-transform: uppercase; color: var(--sis-text-muted); margin-bottom: 0.35rem;
        }
        .sis-dash .form-select, .sis-dash .form-control {
            background-color: var(--sis-input-bg); border: 1px solid var(--sis-border);
            color: var(--sis-text); border-radius: 10px; font-size: 0.9rem; padding: 0.55rem 0.8rem;
        }
        .sis-dash .form-select:focus, .sis-dash .form-control:focus {
            background-color: var(--sis-input-bg); color: var(--sis-text);
            border-color: #06b6d4; box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.18);
        }
        body[data-theme="dark"] .sis-dash .form-control::placeholder { color: #64748b; }
        .sis-btn-search {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; width: 100%;
            padding: 0.55rem 0.9rem; border-radius: 10px; font-size: 0.88rem; font-weight: 600;
            color: #fff; border: 1px solid transparent; cursor: pointer;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            transition: filter .2s ease, transform .2s ease;
        }
        .sis-btn-search:hover { filter: brightness(1.06); transform: translateY(-1px); }

        /* ---------- Tabla ---------- */
        .sis-table-wrap { overflow-x: auto; }
        .sis-table { width: 100%; border-collapse: collapse; margin: 0; }
        .sis-table thead th {
            text-align: left; padding: 0.75rem 1rem; font-size: 0.72rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em; color: var(--sis-text-muted);
            background: var(--sis-track); border-bottom: 1px solid var(--sis-border); white-space: nowrap;
        }
        .sis-table tbody td { padding: 0.85rem 1rem; font-size: 0.88rem; color: var(--sis-text); border-bottom: 1px solid var(--sis-border); vertical-align: middle; }
        .sis-table tbody tr:last-child td { border-bottom: none; }
        .sis-table tbody tr { transition: background .15s ease; }
        .sis-table tbody tr:hover { background: var(--sis-row-hover); }
        .sis-table__folio { font-family: var(--sis-mono); font-weight: 600; font-size: 0.86rem; color: #2563eb; text-decoration: none; }
        body[data-theme="dark"] .sis-table__folio { color: #67e8f9; }
        .sis-table__folio:hover { text-decoration: underline; }
        .sis-muted { color: var(--sis-text-muted); font-size: 0.82rem; }

        .sis-chip { display: inline-flex; align-items: center; padding: 0.26rem 0.68rem; border-radius: 999px; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
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

        .sis-btn-icon {
            width: 34px; height: 34px; border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.9rem; text-decoration: none; border: 1px solid;
            color: #2563eb; border-color: rgba(37, 99, 235, 0.4); background: rgba(37, 99, 235, 0.1);
            transition: transform .18s ease, filter .18s ease;
        }
        .sis-btn-icon:hover { transform: translateY(-2px); filter: brightness(1.08); }
        body[data-theme="dark"] .sis-btn-icon { color: #93c5fd; }

        /* ---------- Estado vacío ---------- */
        .sis-empty { text-align: center; padding: 3rem 1rem; color: var(--sis-text-muted); }
        .sis-empty i { font-size: 2.4rem; display: block; margin-bottom: 0.7rem; opacity: 0.5; }
        .sis-empty__btn {
            display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem;
            padding: 0.6rem 1.2rem; border-radius: 11px; font-size: 0.9rem; font-weight: 600;
            color: #fff; text-decoration: none; border: none; cursor: pointer;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            transition: filter .2s ease, transform .2s ease;
        }
        .sis-empty__btn:hover { color: #fff; filter: brightness(1.06); transform: translateY(-2px); }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) { .sis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) {
            .sis-grid { gap: 0.75rem; }
            .sis-stat { padding: 1rem 0.9rem; border-radius: 14px; }
            .sis-stat__top { margin-bottom: 0.6rem; }
            .sis-stat__icon { width: 40px; height: 40px; font-size: 1.15rem; border-radius: 10px; }
            .sis-stat__num { font-size: 1.7rem; }
            .sis-stat__label { font-size: 0.76rem; }
            .sis-hero { padding: 1.3rem 1.25rem; }
            .sis-hero__title { font-size: 1.25rem; }
            .sis-hero__icon { width: 50px; height: 50px; font-size: 1.5rem; }
            .sis-hero__right { width: 100%; }
            .sis-hero-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 380px) {
            .sis-grid { gap: 0.6rem; }
            .sis-stat { padding: 0.85rem 0.75rem; }
            .sis-stat__num { font-size: 1.5rem; }
            .sis-stat__icon { width: 36px; height: 36px; font-size: 1.05rem; }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR ⭐ CORREGIDO: Ahora incluye verificación de GTH -->
        <?php 
        if (es_usuario_ti()) {
            include __DIR__ . '/../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../includes/sidebar/sidebar_gth.php';
        } elseif (es_usuario_epp()) {
            include __DIR__ . '/../includes/sidebar/sidebar_inventario.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../includes/sidebar/sidebar_colaborativo.php';
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
                                <i class="bi bi-folder2-open"></i>
                            </div>
                            <div>
                                <h1 class="sis-hero__title">Mis Solicitudes</h1>
                                <p class="sis-hero__subtitle">
                                    <i class="bi bi-hdd-network"></i>
                                    Solicitudes de atenci&oacute;n a TI/Sistemas
                                </p>
                            </div>
                        </div>
                        <div class="sis-hero__right">
                            <a href="#" class="sis-hero-btn" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                <i class="bi bi-plus-circle"></i> Nueva Solicitud
                            </a>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php echo mostrar_alerta(); ?>

                    <!-- Estadísticas -->
                    <div class="sis-grid">
                        <div class="sis-stat sis-stat--amber">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-clock-history"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $contadores['pendientes']; ?></h2>
                            <p class="sis-stat__label">Pendientes</p>
                        </div>
                        <div class="sis-stat sis-stat--cyan">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-gear"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $contadores['en_proceso']; ?></h2>
                            <p class="sis-stat__label">En Proceso</p>
                        </div>
                        <div class="sis-stat sis-stat--green">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-check-circle"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $contadores['finalizadas']; ?></h2>
                            <p class="sis-stat__label">Finalizadas</p>
                        </div>
                        <div class="sis-stat sis-stat--indigo">
                            <div class="sis-stat__top">
                                <div class="sis-stat__icon"><i class="bi bi-file-earmark-text"></i></div>
                                <span class="sis-stat__dot"></span>
                            </div>
                            <h2 class="sis-stat__num"><?php echo $contadores['total']; ?></h2>
                            <p class="sis-stat__label">Total</p>
                        </div>
                    </div>

                    <!-- Filtros y Búsqueda -->
                    <div class="sis-panel">
                        <div class="sis-panel__head">
                            <h3 class="sis-panel__title"><i class="bi bi-funnel"></i> Filtros y B&uacute;squeda</h3>
                            <?php if ($hay_filtros): ?>
                            <span class="sis-head-count"><i class="bi bi-check2-circle"></i> Filtros activos</span>
                            <?php endif; ?>
                        </div>
                        <div class="sis-panel__body">
                            <form method="GET" action="" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="sis-filtros-label">Filtrar por estado</label>
                                    <select name="estado" class="form-select" onchange="this.form.submit()">
                                        <option value="">Todos los estados</option>
                                        <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="en_proceso" <?php echo $filtro_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                        <option value="finalizada" <?php echo $filtro_estado == 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                        <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="sis-filtros-label">Buscar</label>
                                    <input type="text" name="buscar" class="form-control" 
                                           placeholder="Folio o descripción..." 
                                           value="<?php echo htmlspecialchars($busqueda); ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="sis-btn-search">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de Solicitudes -->
                    <div class="sis-panel">
                        <div class="sis-panel__head">
                            <h3 class="sis-panel__title"><i class="bi bi-list-ul"></i> Listado de Solicitudes</h3>
                            <span class="sis-head-count"><i class="bi bi-hash"></i> <?php echo count($solicitudes); ?> resultado(s)</span>
                        </div>
                        <div class="sis-panel__body sis-panel__body--flush">
                            <?php if (empty($solicitudes)): ?>
                            <div class="sis-empty">
                                <i class="bi bi-inbox"></i>
                                No hay solicitudes para mostrar
                                <div>
                                    <a href="#" class="sis-empty__btn" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                        <i class="bi bi-plus-circle"></i> Crear primera solicitud
                                    </a>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="sis-table-wrap">
                                <table class="sis-table">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Tipo</th>
                                            <th>Descripci&oacute;n</th>
                                            <th>Prioridad</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitudes as $sol): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" class="sis-table__folio">
                                                    <?php echo htmlspecialchars($sol['folio']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="sis-chip <?php echo $sol['tipo_soporte'] == 'Apoyo' ? 'sis-chip--apoyo' : 'sis-chip--incidencia'; ?>">
                                                    <?php echo htmlspecialchars($sol['tipo_soporte']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $desc = htmlspecialchars($sol['descripcion']);
                                                echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                                ?>
                                            </td>
                                            <td>
                                                <span class="sis-chip sis-chip--<?php echo htmlspecialchars($sol['prioridad']); ?>">
                                                    <?php echo obtener_texto_prioridad($sol['prioridad']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sis-chip sis-chip--<?php echo htmlspecialchars($sol['estado']); ?>">
                                                    <?php echo obtener_texto_estado_custom($sol['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sis-muted"><?php echo date('d/m/Y H:i', strtotime($sol['fecha_creacion'])); ?></span>
                                            </td>
                                            <td>
                                                <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                                   class="sis-btn-icon" title="Ver detalle">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
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
    <?php include __DIR__ . '/modal_crear.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.setAttribute('data-theme', 'dark');
        }
        
        themeToggle?.addEventListener('click', () => {
            const isDark = document.body.getAttribute('data-theme') === 'dark';
            document.body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        });
    </script>
</body>
</html>