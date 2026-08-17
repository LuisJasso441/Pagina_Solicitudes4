<?php
/**
 * Dashboard para departamentos normales (NO colaborativos)
 * Para usuarios que NO son TI ni departamentos colaborativos ni EPP
 * 
 * ⭐ REDISEÑO: Interfaz moderna, corporativa y neutra.
 *    Estilo "producto SaaS moderno" con paleta Verden Core.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';

// Verificar sesión
verificar_sesion();

// Verificar expiración por inactividad
if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado por inactividad. Por favor inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}

// Renovar tiempo de actividad
actualizar_sesion();

// Redirecciones por rol especializado
if (es_usuario_ti()) {
    redirigir(URL_BASE . 'dashboard/sistemas/ti_sistemas.php');
}

if (es_usuario_colaborativo()) {
    redirigir(URL_BASE . 'dashboard/colaborativo/colaborativo.php');
}

// Logística y Almacén de Residuos tienen su propio dashboard SEC
$dept_dashboard = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (in_array($dept_dashboard, ['logistica', 'almacen_residuos'])) {
    redirigir(URL_BASE . 'dashboard/salidas_envases/inicio_sec.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento   = $_SESSION['departamento_nombre'];
$usuario_id     = $_SESSION['usuario_id'];
$primer_nombre  = explode(' ', $nombre_usuario)[0];

// ========================================
// Estadísticas del usuario
// ========================================
$stats = [
    'pendientes'  => 0,
    'en_proceso'  => 0,
    'finalizadas' => 0,
    'total'       => 0,
];
$solicitudes_recientes = [];

try {
    $pdo = conectarDB();

    // Conteo por estado
    $stmt = $pdo->prepare("
        SELECT estado, COUNT(*) as total 
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        GROUP BY estado
    ");
    $stmt->execute([$usuario_id]);
    while ($row = $stmt->fetch()) {
        if ($row['estado'] == 'pendiente')     $stats['pendientes']  = (int)$row['total'];
        elseif ($row['estado'] == 'en_proceso') $stats['en_proceso']  = (int)$row['total'];
        elseif ($row['estado'] == 'finalizada') $stats['finalizadas'] = (int)$row['total'];
    }
    $stats['total'] = $stats['pendientes'] + $stats['en_proceso'] + $stats['finalizadas'];

    // Solicitudes recientes
    $stmt = $pdo->prepare("
        SELECT folio, descripcion, fecha_creacion, estado, prioridad
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $solicitudes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error dashboard departamento: " . $e->getMessage());
}

// ========================================
// Saludo dinámico según hora del día
// ========================================
$hora = (int)date('H');
if ($hora >= 5 && $hora < 12) {
    $saludo = 'Buenos días';
    $saludo_icon = 'bi-sunrise-fill';
} elseif ($hora >= 12 && $hora < 19) {
    $saludo = 'Buenas tardes';
    $saludo_icon = 'bi-sun-fill';
} else {
    $saludo = 'Buenas noches';
    $saludo_icon = 'bi-moon-stars-fill';
}

// Mensaje contextual según estado de las solicitudes
if ($stats['total'] === 0) {
    $mensaje_contextual = 'Bienvenido a tu panel de control.';
} elseif ($stats['pendientes'] === 0 && $stats['en_proceso'] === 0) {
    $mensaje_contextual = '¡Todo al día! No tienes solicitudes activas.';
} elseif ($stats['pendientes'] > 0) {
    $n = $stats['pendientes'];
    $sufijo   = ($n === 1) ? ''         : 'es';
    $adjetivo = ($n === 1) ? 'pendiente' : 'pendientes';
    $mensaje_contextual = "Tienes {$n} solicitud{$sufijo} {$adjetivo} de atención.";
} else {
    $n = $stats['en_proceso'];
    $palabra = ($n === 1) ? 'solicitud' : 'solicitudes';
    $mensaje_contextual = "{$n} {$palabra} en curso.";
}

// ========================================
// Cálculo del donut chart (SVG)
// ========================================
$circumference = 251.32; // 2 * PI * 40
$pct_pend = $pct_proc = $pct_fin = 0;
$dash_pend = $dash_proc = $dash_fin = 0;
$off_pend = $off_proc = $off_fin = 0;

if ($stats['total'] > 0) {
    $pct_pend = $stats['pendientes']  / $stats['total'];
    $pct_proc = $stats['en_proceso']  / $stats['total'];
    $pct_fin  = $stats['finalizadas'] / $stats['total'];

    $dash_pend = $pct_pend * $circumference;
    $dash_proc = $pct_proc * $circumference;
    $dash_fin  = $pct_fin  * $circumference;

    $off_pend = 0;
    $off_proc = -$dash_pend;
    $off_fin  = -($dash_pend + $dash_proc);
}

// ========================================
// Diccionarios para mostrar solicitudes
// ========================================
$estado_display = [
    'pendiente'  => ['label' => 'Pendiente',  'class' => 'dpt-badge--pendiente',  'dot' => '#f59e0b'],
    'en_proceso' => ['label' => 'En proceso', 'class' => 'dpt-badge--proceso',    'dot' => '#3b82f6'],
    'finalizada' => ['label' => 'Finalizada', 'class' => 'dpt-badge--finalizada', 'dot' => '#04CF85'],
    'cancelada'  => ['label' => 'Cancelada',  'class' => 'dpt-badge--cancelada',  'dot' => '#6b7280'],
];

$prioridad_class = [
    'critica' => 'dpt-prio--critica',
    'alta'    => 'dpt-prio--alta',
    'media'   => 'dpt-prio--media',
    'baja'    => 'dpt-prio--baja',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($departamento); ?></title>
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
        /* ============================================================
           DASHBOARD DEPARTAMENTO — Diseño moderno neutro
           Prefijo .dpt- para no colisionar con otros módulos.
           ============================================================ */

        :root {
            --dpt-primary:      #04CF85;
            --dpt-primary-dark: #039768;
            --dpt-accent:       #3b82f6;
            --dpt-warning:      #f59e0b;
            --dpt-danger:       #ef4444;
            --dpt-info:         #06b6d4;
            --dpt-slate-50:     #f8fafc;
            --dpt-slate-100:    #f1f5f9;
            --dpt-slate-200:    #e2e8f0;
            --dpt-slate-300:    #cbd5e1;
            --dpt-slate-400:    #94a3b8;
            --dpt-slate-500:    #64748b;
            --dpt-slate-600:    #475569;
            --dpt-slate-700:    #334155;
            --dpt-slate-800:    #1e293b;
            --dpt-slate-900:    #0f172a;

            --dpt-card-bg:      #ffffff;
            --dpt-card-border:  #e2e8f0;
            --dpt-text:         #0f172a;
            --dpt-text-muted:   #64748b;
            --dpt-surface-soft: #f8fafc;
            --dpt-donut-track:  #e2e8f0;
        }

        body[data-theme="dark"] {
            --dpt-card-bg:      #1e293b;
            --dpt-card-border:  #334155;
            --dpt-text:         #f1f5f9;
            --dpt-text-muted:   #94a3b8;
            --dpt-surface-soft: #0f172a;
            --dpt-donut-track:  #334155;
        }

        /* ---------- HERO / Saludo ---------- */
        .dpt-hero {
            background: var(--dpt-card-bg);
            border: 1px solid var(--dpt-card-border);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        /* Acento visual sutil en la esquina */
        .dpt-hero::before {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle at center,
                rgba(4, 207, 133, 0.14) 0%,
                rgba(4, 207, 133, 0) 70%);
            pointer-events: none;
        }
        .dpt-hero__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .dpt-hero__greeting {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--dpt-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
            margin: 0;
        }
        .dpt-hero__greeting i {
            color: var(--dpt-primary);
        }
        .dpt-hero__name {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dpt-text);
            margin: 0.35rem 0 0.5rem 0;
            line-height: 1.1;
        }
        .dpt-hero__message {
            font-size: 0.9rem;
            color: var(--dpt-text-muted);
            margin: 0;
        }
        .dpt-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 1rem;
            background: rgba(4, 207, 133, 0.10);
            color: var(--dpt-primary-dark);
            border: 1px solid rgba(4, 207, 133, 0.25);
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.82rem;
        }
        body[data-theme="dark"] .dpt-hero__badge {
            color: var(--dpt-primary);
            background: rgba(4, 207, 133, 0.15);
        }
        .dpt-hero__actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .dpt-hero__anuncios {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--dpt-card-border);
            background: var(--dpt-surface-soft);
            color: var(--dpt-text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.2s;
        }
        .dpt-hero__anuncios:hover {
            background: rgba(4, 207, 133, 0.10);
            border-color: rgba(4, 207, 133, 0.35);
            color: var(--dpt-primary-dark);
            transform: translateY(-1px);
        }
        body[data-theme="dark"] .dpt-hero__anuncios:hover {
            color: var(--dpt-primary);
        }
        .dpt-hero__anuncios .badge-count {
            position: absolute;
            top: -3px;
            right: -3px;
        }
        .dpt-hero__date {
            font-size: 0.75rem;
            color: var(--dpt-text-muted);
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* ---------- STATS Cards ---------- */
        .dpt-stat {
            background: var(--dpt-card-bg);
            border: 1px solid var(--dpt-card-border);
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            position: relative;
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dpt-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
        }
        body[data-theme="dark"] .dpt-stat:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        .dpt-stat__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .dpt-stat__label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--dpt-text-muted);
            font-weight: 600;
            margin: 0;
        }
        .dpt-stat__icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            background: var(--dpt-stat-accent-soft, rgba(100, 116, 139, 0.10));
            color: var(--dpt-stat-accent, var(--dpt-slate-500));
        }
        .dpt-stat__number {
            font-size: 2.1rem;
            font-weight: 700;
            color: var(--dpt-text);
            line-height: 1;
            margin: 0;
        }
        .dpt-stat__bar {
            margin-top: 0.85rem;
            height: 4px;
            border-radius: 4px;
            background: var(--dpt-surface-soft);
            overflow: hidden;
        }
        .dpt-stat__bar-fill {
            height: 100%;
            border-radius: 4px;
            background: var(--dpt-stat-accent, var(--dpt-slate-500));
            transition: width 0.6s ease;
        }
        .dpt-stat__foot {
            font-size: 0.72rem;
            color: var(--dpt-text-muted);
            margin-top: 0.4rem;
        }
        .dpt-stat--pendientes  { --dpt-stat-accent: #f59e0b; --dpt-stat-accent-soft: rgba(245, 158, 11, 0.12); }
        .dpt-stat--proceso     { --dpt-stat-accent: #3b82f6; --dpt-stat-accent-soft: rgba(59, 130, 246, 0.12); }
        .dpt-stat--finalizadas { --dpt-stat-accent: #04CF85; --dpt-stat-accent-soft: rgba(4, 207, 133, 0.12); }
        .dpt-stat--total       { --dpt-stat-accent: #64748b; --dpt-stat-accent-soft: rgba(100, 116, 139, 0.12); }

        /* ---------- Panel ---------- */
        .dpt-panel {
            background: var(--dpt-card-bg);
            border: 1px solid var(--dpt-card-border);
            border-radius: 14px;
            overflow: hidden;
            height: 100%;
        }
        .dpt-panel__header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--dpt-card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dpt-panel__title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dpt-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dpt-panel__title i { color: var(--dpt-primary); }
        .dpt-panel__link {
            font-size: 0.78rem;
            color: var(--dpt-primary-dark);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .dpt-panel__link:hover { color: var(--dpt-primary); text-decoration: underline; }
        body[data-theme="dark"] .dpt-panel__link { color: var(--dpt-primary); }
        .dpt-panel__body { padding: 1.25rem; }

        /* ---------- DONUT chart ---------- */
        .dpt-donut-wrap {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 0.5rem 0;
        }
        .dpt-donut {
            position: relative;
            width: 160px;
            height: 160px;
            flex-shrink: 0;
        }
        .dpt-donut svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }
        .dpt-donut svg circle {
            transition: stroke-dasharray 0.8s ease, stroke-dashoffset 0.8s ease;
        }
        .dpt-donut__center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .dpt-donut__number {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--dpt-text);
            line-height: 1;
        }
        .dpt-donut__label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--dpt-text-muted);
            font-weight: 600;
            margin-top: 0.2rem;
        }
        .dpt-donut__legend {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        .dpt-donut__legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.82rem;
            color: var(--dpt-text);
        }
        .dpt-donut__legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .dpt-donut__legend-value {
            margin-left: auto;
            font-weight: 600;
            color: var(--dpt-text-muted);
            font-variant-numeric: tabular-nums;
        }

        /* Empty state para el donut */
        .dpt-donut-empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--dpt-text-muted);
        }
        .dpt-donut-empty i {
            font-size: 2.2rem;
            color: var(--dpt-slate-300);
            display: block;
            margin-bottom: 0.5rem;
        }
        body[data-theme="dark"] .dpt-donut-empty i { color: var(--dpt-slate-600); }
        .dpt-donut-empty p {
            font-size: 0.85rem;
            margin: 0;
        }

        /* ---------- TIMELINE de actividad ---------- */
        .dpt-timeline {
            padding: 0.5rem 0;
        }
        .dpt-timeline__item {
            display: block;
            text-decoration: none;
            color: inherit;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            transition: background 0.15s;
            position: relative;
        }
        .dpt-timeline__item + .dpt-timeline__item { margin-top: 0.15rem; }
        .dpt-timeline__item:hover {
            background: var(--dpt-surface-soft);
            color: inherit;
        }
        .dpt-timeline__row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .dpt-timeline__dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px var(--dpt-dot-halo, rgba(100, 116, 139, 0.15));
        }
        .dpt-timeline__content {
            flex: 1;
            min-width: 0;
        }
        .dpt-timeline__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.2rem;
        }
        .dpt-timeline__folio {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--dpt-primary-dark);
            font-family: 'Poppins', sans-serif;
        }
        body[data-theme="dark"] .dpt-timeline__folio { color: var(--dpt-primary); }
        .dpt-timeline__date {
            font-size: 0.72rem;
            color: var(--dpt-text-muted);
            white-space: nowrap;
        }
        .dpt-timeline__desc {
            font-size: 0.82rem;
            color: var(--dpt-text);
            margin: 0 0 0.4rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .dpt-timeline__meta {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        /* Badges */
        .dpt-badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .dpt-badge--pendiente  { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .dpt-badge--proceso    { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .dpt-badge--finalizada { background: rgba(4, 207, 133, 0.15); color: #059669; }
        .dpt-badge--cancelada  { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
        body[data-theme="dark"] .dpt-badge--pendiente  { color: #fbbf24; }
        body[data-theme="dark"] .dpt-badge--proceso    { color: #60a5fa; }
        body[data-theme="dark"] .dpt-badge--finalizada { color: #34d399; }

        .dpt-prio {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .dpt-prio--critica { background: rgba(239, 68, 68, 0.15);  color: #dc2626; }
        .dpt-prio--alta    { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .dpt-prio--media   { background: rgba(6, 182, 212, 0.15);  color: #0891b2; }
        .dpt-prio--baja    { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
        body[data-theme="dark"] .dpt-prio--critica { color: #f87171; }
        body[data-theme="dark"] .dpt-prio--alta    { color: #fbbf24; }
        body[data-theme="dark"] .dpt-prio--media   { color: #22d3ee; }

        /* Empty state timeline */
        .dpt-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--dpt-text-muted);
        }
        .dpt-empty i {
            font-size: 2.4rem;
            color: var(--dpt-slate-300);
            display: block;
            margin-bottom: 0.5rem;
        }
        body[data-theme="dark"] .dpt-empty i { color: var(--dpt-slate-600); }

        /* ---------- Action Cards ---------- */
        .dpt-action {
            display: block;
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--dpt-card-border);
            background: var(--dpt-card-bg);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            height: 100%;
        }
        .dpt-action + .dpt-action { margin-top: 0.65rem; }
        .dpt-action:hover {
            transform: translateY(-2px);
            border-color: var(--dpt-primary);
            box-shadow: 0 6px 18px rgba(4, 207, 133, 0.12);
            color: inherit;
        }
        .dpt-action__row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .dpt-action__icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            background: var(--dpt-action-accent-soft, rgba(4, 207, 133, 0.12));
            color: var(--dpt-action-accent, var(--dpt-primary));
            flex-shrink: 0;
        }
        .dpt-action__body { flex: 1; min-width: 0; }
        .dpt-action__title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dpt-text);
            margin: 0;
            line-height: 1.2;
        }
        .dpt-action__desc {
            font-size: 0.75rem;
            color: var(--dpt-text-muted);
            margin: 0.15rem 0 0 0;
        }
        .dpt-action__arrow {
            color: var(--dpt-text-muted);
            font-size: 1rem;
            transition: transform 0.2s, color 0.2s;
        }
        .dpt-action:hover .dpt-action__arrow {
            transform: translateX(3px);
            color: var(--dpt-primary);
        }
        .dpt-action--primary  { --dpt-action-accent: #04CF85; --dpt-action-accent-soft: rgba(4, 207, 133, 0.12); }
        .dpt-action--info     { --dpt-action-accent: #3b82f6; --dpt-action-accent-soft: rgba(59, 130, 246, 0.12); }

        /* Overrides */
        body[data-theme="dark"] .top-navbar {
            background: transparent;
            box-shadow: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dpt-hero { padding: 1.15rem 1rem; }
            .dpt-hero__name { font-size: 1.4rem; }
            .dpt-hero__badge { align-self: flex-start; }
            .dpt-stat__number { font-size: 1.6rem; }
            .dpt-donut-wrap { flex-direction: column; align-items: stretch; gap: 1rem; }
            .dpt-donut { margin: 0 auto; }
        }

        /* ============================================================
           EFECTOS NEÓN — Nivel Balanceado (Verde Verden)
           Intensidad escalada: sutil en modo claro, intenso en oscuro.
           ============================================================ */

        :root {
            --dpt-neon:            #04CF85;
            --dpt-neon-glow-sm:    0 0 8px  rgba(4, 207, 133, 0.30);
            --dpt-neon-glow-md:    0 0 16px rgba(4, 207, 133, 0.40);
            --dpt-neon-glow-lg:    0 0 24px rgba(4, 207, 133, 0.50);
            --dpt-neon-text-sm:    0 0 8px  rgba(4, 207, 133, 0.35);
            --dpt-neon-text-md:    0 0 14px rgba(4, 207, 133, 0.50);

            --dpt-glow-amber:      0 0 12px rgba(245, 158, 11, 0.45);
            --dpt-glow-blue:       0 0 12px rgba(59, 130, 246, 0.45);
            --dpt-glow-green:      0 0 12px rgba(4, 207, 133, 0.45);
            --dpt-glow-slate:      0 0 12px rgba(100, 116, 139, 0.30);
        }

        body[data-theme="dark"] {
            --dpt-neon-glow-sm:    0 0 12px rgba(4, 207, 133, 0.55);
            --dpt-neon-glow-md:    0 0 22px rgba(4, 207, 133, 0.70);
            --dpt-neon-glow-lg:    0 0 34px rgba(4, 207, 133, 0.85);
            --dpt-neon-text-sm:    0 0 10px rgba(4, 207, 133, 0.60);
            --dpt-neon-text-md:    0 0 18px rgba(4, 207, 133, 0.80);

            --dpt-glow-amber:      0 0 18px rgba(245, 158, 11, 0.65);
            --dpt-glow-blue:       0 0 18px rgba(59, 130, 246, 0.65);
            --dpt-glow-green:      0 0 18px rgba(4, 207, 133, 0.65);
            --dpt-glow-slate:      0 0 16px rgba(148, 163, 184, 0.45);
        }

        /* -- HERO CARD -- */
        .dpt-hero {
            border-color: rgba(4, 207, 133, 0.25);
            box-shadow: var(--dpt-neon-glow-sm);
            transition: box-shadow 0.35s ease, border-color 0.35s ease;
        }
        .dpt-hero:hover {
            box-shadow: var(--dpt-neon-glow-md);
            border-color: rgba(4, 207, 133, 0.45);
        }
        .dpt-hero__greeting i {
            filter: drop-shadow(var(--dpt-neon-glow-sm));
        }
        .dpt-hero__badge {
            box-shadow: var(--dpt-neon-glow-sm);
        }
        body[data-theme="dark"] .dpt-hero__name {
            text-shadow: var(--dpt-neon-text-sm);
        }

        /* Botón de anuncios */
        .dpt-hero__anuncios {
            transition: background 0.2s, color 0.2s, border-color 0.2s,
                        transform 0.2s, box-shadow 0.3s;
        }
        .dpt-hero__anuncios:hover {
            box-shadow: var(--dpt-neon-glow-sm);
        }

        /* -- STATS CARDS: glow con color de cada categoría -- */
        .dpt-stat--pendientes  { --dpt-stat-glow: var(--dpt-glow-amber); }
        .dpt-stat--proceso     { --dpt-stat-glow: var(--dpt-glow-blue);  }
        .dpt-stat--finalizadas { --dpt-stat-glow: var(--dpt-glow-green); }
        .dpt-stat--total       { --dpt-stat-glow: var(--dpt-glow-slate); }

        .dpt-stat {
            transition: transform 0.2s, box-shadow 0.35s, border-color 0.3s;
        }
        .dpt-stat:hover {
            box-shadow: var(--dpt-stat-glow), 0 6px 20px rgba(15, 23, 42, 0.06);
            border-color: var(--dpt-stat-accent);
        }
        body[data-theme="dark"] .dpt-stat:hover {
            box-shadow: var(--dpt-stat-glow), 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        .dpt-stat__icon {
            box-shadow: var(--dpt-stat-glow);
        }
        body[data-theme="dark"] .dpt-stat__number {
            text-shadow: 0 0 12px var(--dpt-stat-accent);
        }
        .dpt-stat__bar-fill {
            box-shadow: 0 0 8px var(--dpt-stat-accent);
        }
        body[data-theme="dark"] .dpt-stat__bar-fill {
            box-shadow: 0 0 12px var(--dpt-stat-accent);
        }

        /* -- PANELES: glow en el icono del título -- */
        .dpt-panel {
            transition: box-shadow 0.35s ease;
        }
        .dpt-panel:hover {
            box-shadow: var(--dpt-neon-glow-sm);
        }
        .dpt-panel__title i {
            filter: drop-shadow(var(--dpt-neon-glow-sm));
        }
        body[data-theme="dark"] .dpt-panel__link {
            text-shadow: 0 0 8px rgba(4, 207, 133, 0.5);
        }

        /* -- DONUT CHART: glow por color de arco -- */
        .dpt-donut svg circle[stroke="#f59e0b"] {
            filter: drop-shadow(0 0 3px #f59e0b);
        }
        .dpt-donut svg circle[stroke="#3b82f6"] {
            filter: drop-shadow(0 0 3px #3b82f6);
        }
        .dpt-donut svg circle[stroke="#04CF85"] {
            filter: drop-shadow(0 0 3px #04CF85);
        }
        body[data-theme="dark"] .dpt-donut svg circle[stroke="#f59e0b"] {
            filter: drop-shadow(0 0 6px #f59e0b);
        }
        body[data-theme="dark"] .dpt-donut svg circle[stroke="#3b82f6"] {
            filter: drop-shadow(0 0 6px #3b82f6);
        }
        body[data-theme="dark"] .dpt-donut svg circle[stroke="#04CF85"] {
            filter: drop-shadow(0 0 6px #04CF85);
        }
        .dpt-donut__number {
            color: var(--dpt-neon);
            text-shadow: var(--dpt-neon-text-md);
        }
        .dpt-donut__legend-dot {
            box-shadow: 0 0 8px currentColor;
        }
        body[data-theme="dark"] .dpt-donut__legend-dot {
            box-shadow: 0 0 10px currentColor;
        }

        /* -- ACTION CARDS: hover con outline neón -- */
        .dpt-action {
            transition: transform 0.2s, box-shadow 0.35s, border-color 0.3s;
        }
        .dpt-action:hover {
            box-shadow: var(--dpt-neon-glow-md), 0 6px 18px rgba(4, 207, 133, 0.15);
        }
        body[data-theme="dark"] .dpt-action:hover {
            box-shadow: var(--dpt-neon-glow-lg), 0 6px 18px rgba(4, 207, 133, 0.25);
        }
        .dpt-action__icon {
            transition: box-shadow 0.3s ease;
        }
        .dpt-action:hover .dpt-action__icon {
            box-shadow: 0 0 14px var(--dpt-action-accent);
        }

        /* -- TIMELINE: dots con halo brillante -- */
        .dpt-timeline__dot {
            box-shadow:
                0 0 0 3px var(--dpt-dot-halo, rgba(100, 116, 139, 0.15)),
                0 0 10px 2px var(--dpt-dot-halo, rgba(100, 116, 139, 0.15));
        }
        body[data-theme="dark"] .dpt-timeline__dot {
            box-shadow:
                0 0 0 3px var(--dpt-dot-halo, rgba(100, 116, 139, 0.15)),
                0 0 14px 3px var(--dpt-dot-halo, rgba(100, 116, 139, 0.15));
        }
        .dpt-timeline__folio {
            text-shadow: 0 0 6px rgba(4, 207, 133, 0.35);
        }
        body[data-theme="dark"] .dpt-timeline__folio {
            text-shadow: 0 0 10px rgba(4, 207, 133, 0.55);
        }
        .dpt-timeline__item:hover {
            box-shadow: inset 0 0 0 1px rgba(4, 207, 133, 0.15);
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php
        $dept_dashboard = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
        if (in_array($dept_dashboard, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../includes/sidebar/sidebar_sec.php';
        } else {
            include __DIR__ . '/../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <?php echo mostrar_alerta(); ?>

                <!-- ============================================ -->
                <!-- HERO / Saludo dinámico                       -->
                <!-- ============================================ -->
                <div class="dpt-hero">
                    <div class="dpt-hero__row">
                        <div>
                            <p class="dpt-hero__greeting">
                                <i class="bi <?php echo $saludo_icon; ?>"></i>
                                <?php echo $saludo; ?>
                            </p>
                            <h1 class="dpt-hero__name"><?php echo htmlspecialchars($primer_nombre); ?></h1>
                            <p class="dpt-hero__message"><?php echo htmlspecialchars($mensaje_contextual); ?></p>
                            <p class="dpt-hero__date">
                                <i class="bi bi-calendar3"></i>
                                <?php echo obtener_fecha_actual_espanol(); ?>
                            </p>
                        </div>
                        <div class="dpt-hero__actions">
                            <button class="dpt-hero__anuncios" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios" aria-label="Anuncios">
                                <i class="bi bi-megaphone-fill"></i>
                                <span class="badge-count" id="anunciosBadge"></span>
                            </button>
                            <span class="dpt-hero__badge">
                                <i class="bi bi-building-fill"></i>
                                <?php echo htmlspecialchars($departamento); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- STATS Row                                    -->
                <!-- ============================================ -->
                <div class="row g-3 mb-3">
                    <?php
                    $pct_p = $stats['total'] > 0 ? round(($stats['pendientes']  / $stats['total']) * 100) : 0;
                    $pct_e = $stats['total'] > 0 ? round(($stats['en_proceso']  / $stats['total']) * 100) : 0;
                    $pct_f = $stats['total'] > 0 ? round(($stats['finalizadas'] / $stats['total']) * 100) : 0;
                    ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="dpt-stat dpt-stat--pendientes">
                            <div class="dpt-stat__head">
                                <p class="dpt-stat__label">Pendientes</p>
                                <span class="dpt-stat__icon"><i class="bi bi-clock-history"></i></span>
                            </div>
                            <h2 class="dpt-stat__number"><?php echo $stats['pendientes']; ?></h2>
                            <div class="dpt-stat__bar">
                                <div class="dpt-stat__bar-fill" style="width: <?php echo $pct_p; ?>%"></div>
                            </div>
                            <p class="dpt-stat__foot"><?php echo $pct_p; ?>% del total</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dpt-stat dpt-stat--proceso">
                            <div class="dpt-stat__head">
                                <p class="dpt-stat__label">En Proceso</p>
                                <span class="dpt-stat__icon"><i class="bi bi-gear-fill"></i></span>
                            </div>
                            <h2 class="dpt-stat__number"><?php echo $stats['en_proceso']; ?></h2>
                            <div class="dpt-stat__bar">
                                <div class="dpt-stat__bar-fill" style="width: <?php echo $pct_e; ?>%"></div>
                            </div>
                            <p class="dpt-stat__foot"><?php echo $pct_e; ?>% del total</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dpt-stat dpt-stat--finalizadas">
                            <div class="dpt-stat__head">
                                <p class="dpt-stat__label">Finalizadas</p>
                                <span class="dpt-stat__icon"><i class="bi bi-check2-circle"></i></span>
                            </div>
                            <h2 class="dpt-stat__number"><?php echo $stats['finalizadas']; ?></h2>
                            <div class="dpt-stat__bar">
                                <div class="dpt-stat__bar-fill" style="width: <?php echo $pct_f; ?>%"></div>
                            </div>
                            <p class="dpt-stat__foot"><?php echo $pct_f; ?>% del total</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dpt-stat dpt-stat--total">
                            <div class="dpt-stat__head">
                                <p class="dpt-stat__label">Total Histórico</p>
                                <span class="dpt-stat__icon"><i class="bi bi-collection-fill"></i></span>
                            </div>
                            <h2 class="dpt-stat__number"><?php echo $stats['total']; ?></h2>
                            <div class="dpt-stat__bar">
                                <div class="dpt-stat__bar-fill" style="width: 100%"></div>
                            </div>
                            <p class="dpt-stat__foot">Todas tus solicitudes</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- Row: Distribución (donut) + Acciones         -->
                <!-- ============================================ -->
                <div class="row g-3 mb-3">
                    <!-- Distribución visual -->
                    <div class="col-lg-7">
                        <div class="dpt-panel">
                            <div class="dpt-panel__header">
                                <h3 class="dpt-panel__title">
                                    <i class="bi bi-pie-chart-fill"></i>
                                    Distribución de tus solicitudes
                                </h3>
                            </div>
                            <div class="dpt-panel__body">
                                <?php if ($stats['total'] === 0): ?>
                                    <div class="dpt-donut-empty">
                                        <i class="bi bi-bar-chart-line"></i>
                                        <p>Aún no tienes solicitudes registradas.<br>
                                        Cuando crees tu primera, verás su distribución aquí.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="dpt-donut-wrap">
                                        <!-- SVG Donut -->
                                        <div class="dpt-donut">
                                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                                <!-- Track -->
                                                <circle cx="50" cy="50" r="40" fill="none"
                                                        stroke="var(--dpt-donut-track)" stroke-width="12"/>
                                                <!-- Pendientes -->
                                                <?php if ($stats['pendientes'] > 0): ?>
                                                <circle cx="50" cy="50" r="40" fill="none"
                                                        stroke="#f59e0b" stroke-width="12"
                                                        stroke-linecap="butt"
                                                        stroke-dasharray="<?php echo $dash_pend; ?> <?php echo $circumference; ?>"
                                                        stroke-dashoffset="<?php echo $off_pend; ?>"/>
                                                <?php endif; ?>
                                                <!-- En proceso -->
                                                <?php if ($stats['en_proceso'] > 0): ?>
                                                <circle cx="50" cy="50" r="40" fill="none"
                                                        stroke="#3b82f6" stroke-width="12"
                                                        stroke-linecap="butt"
                                                        stroke-dasharray="<?php echo $dash_proc; ?> <?php echo $circumference; ?>"
                                                        stroke-dashoffset="<?php echo $off_proc; ?>"/>
                                                <?php endif; ?>
                                                <!-- Finalizadas -->
                                                <?php if ($stats['finalizadas'] > 0): ?>
                                                <circle cx="50" cy="50" r="40" fill="none"
                                                        stroke="#04CF85" stroke-width="12"
                                                        stroke-linecap="butt"
                                                        stroke-dasharray="<?php echo $dash_fin; ?> <?php echo $circumference; ?>"
                                                        stroke-dashoffset="<?php echo $off_fin; ?>"/>
                                                <?php endif; ?>
                                            </svg>
                                            <div class="dpt-donut__center">
                                                <span class="dpt-donut__number"><?php echo $stats['total']; ?></span>
                                                <span class="dpt-donut__label">Total</span>
                                            </div>
                                        </div>

                                        <!-- Leyenda -->
                                        <div class="dpt-donut__legend">
                                            <div class="dpt-donut__legend-item">
                                                <span class="dpt-donut__legend-dot" style="background: #f59e0b;"></span>
                                                Pendientes
                                                <span class="dpt-donut__legend-value"><?php echo $stats['pendientes']; ?> · <?php echo round($pct_pend * 100); ?>%</span>
                                            </div>
                                            <div class="dpt-donut__legend-item">
                                                <span class="dpt-donut__legend-dot" style="background: #3b82f6;"></span>
                                                En proceso
                                                <span class="dpt-donut__legend-value"><?php echo $stats['en_proceso']; ?> · <?php echo round($pct_proc * 100); ?>%</span>
                                            </div>
                                            <div class="dpt-donut__legend-item">
                                                <span class="dpt-donut__legend-dot" style="background: #04CF85;"></span>
                                                Finalizadas
                                                <span class="dpt-donut__legend-value"><?php echo $stats['finalizadas']; ?> · <?php echo round($pct_fin * 100); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="col-lg-5">
                        <div class="dpt-panel">
                            <div class="dpt-panel__header">
                                <h3 class="dpt-panel__title">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Acciones rápidas
                                </h3>
                            </div>
                            <div class="dpt-panel__body">
                                <a href="#" class="dpt-action dpt-action--primary" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                    <div class="dpt-action__row">
                                        <span class="dpt-action__icon"><i class="bi bi-plus-lg"></i></span>
                                        <div class="dpt-action__body">
                                            <p class="dpt-action__title">Nueva solicitud de atención</p>
                                            <p class="dpt-action__desc">Reportar un incidente o requerimiento a Sistemas</p>
                                        </div>
                                        <i class="bi bi-arrow-right dpt-action__arrow"></i>
                                    </div>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="dpt-action dpt-action--info">
                                    <div class="dpt-action__row">
                                        <span class="dpt-action__icon"><i class="bi bi-clipboard2-check"></i></span>
                                        <div class="dpt-action__body">
                                            <p class="dpt-action__title">Órdenes de mantenimiento</p>
                                            <p class="dpt-action__desc">Revisar y crear OSM del departamento</p>
                                        </div>
                                        <i class="bi bi-arrow-right dpt-action__arrow"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- Actividad reciente (timeline)                -->
                <!-- ============================================ -->
                <div class="dpt-panel">
                    <div class="dpt-panel__header">
                        <h3 class="dpt-panel__title">
                            <i class="bi bi-activity"></i>
                            Actividad reciente
                        </h3>
                        <?php if (!empty($solicitudes_recientes)): ?>
                        <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="dpt-panel__link">
                            Ver todas <i class="bi bi-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="dpt-panel__body" style="padding: 0.75rem;">
                        <?php if (empty($solicitudes_recientes)): ?>
                            <div class="dpt-empty">
                                <i class="bi bi-inbox"></i>
                                <p>Aún no tienes solicitudes registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="dpt-timeline">
                                <?php foreach ($solicitudes_recientes as $sol):
                                    $estado_key  = $sol['estado'];
                                    $est_info    = $estado_display[$estado_key] ?? ['label' => ucfirst($estado_key), 'class' => '', 'dot' => '#6b7280'];
                                    $prio_key    = strtolower($sol['prioridad'] ?? 'baja');
                                    $prio_cls    = $prioridad_class[$prio_key] ?? 'dpt-prio--baja';
                                    $dot_halo    = $est_info['dot'] . '30'; // color + opacidad
                                ?>
                                <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                   class="dpt-timeline__item"
                                   style="--dpt-dot-halo: <?php echo $est_info['dot']; ?>30;">
                                    <div class="dpt-timeline__row">
                                        <span class="dpt-timeline__dot" style="background: <?php echo $est_info['dot']; ?>;"></span>
                                        <div class="dpt-timeline__content">
                                            <div class="dpt-timeline__top">
                                                <span class="dpt-timeline__folio"><?php echo htmlspecialchars($sol['folio']); ?></span>
                                                <span class="dpt-timeline__date"><?php echo date('d/m/Y', strtotime($sol['fecha_creacion'])); ?></span>
                                            </div>
                                            <p class="dpt-timeline__desc"><?php echo htmlspecialchars(mb_substr($sol['descripcion'], 0, 110)); ?><?php echo mb_strlen($sol['descripcion']) > 110 ? '…' : ''; ?></p>
                                            <div class="dpt-timeline__meta">
                                                <span class="dpt-badge <?php echo $est_info['class']; ?>"><?php echo $est_info['label']; ?></span>
                                                <span class="dpt-prio <?php echo $prio_cls; ?>"><?php echo ucfirst($prio_key); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/../solicitudes/modal_crear.php'; ?>

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
    <?php include __DIR__ . '/../includes/anuncios/modal_anuncios.php'; ?>

</body>
</html>