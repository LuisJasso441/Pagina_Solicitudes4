<?php
/**
 * Dashboard específico para departamento de MANTENIMIENTO
 * ⭐ REDISEÑO: Enfocado en Órdenes de Servicio de Mantenimiento (OSM)
 *    con temática industrial / taller mecánico
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';

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

// Verificar que sea departamento de Mantenimiento
if (strtolower($_SESSION['departamento']) !== 'mantenimiento') {
    header('Location: ' . URL_BASE . 'dashboard/index.php');
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento   = $_SESSION['departamento_nombre'];
$usuario_id     = $_SESSION['usuario_id'];

// ========================================
// ESTADÍSTICAS OSM (globales, ya que Mantenimiento las ve todas)
// ========================================
$stats_osm = [
    'por_atender'     => 0,
    'en_proceso'      => 0,
    'por_validar'     => 0,
    'devueltas'       => 0,
    'completadas_mes' => 0,
];

$osm_activas          = [];
$solicitudes_recientes = [];

try {
    $pdo = conectarDB();

    // Conteos por estado
    $sql_stats = "
        SELECT 
            SUM(CASE WHEN estado = 'pendiente_mantenimiento' THEN 1 ELSE 0 END) AS por_atender,
            SUM(CASE WHEN estado = 'en_proceso'              THEN 1 ELSE 0 END) AS en_proceso,
            SUM(CASE WHEN estado = 'pendiente_usuario'       THEN 1 ELSE 0 END) AS por_validar,
            SUM(CASE WHEN estado = 'devuelto'                THEN 1 ELSE 0 END) AS devueltas,
            SUM(CASE WHEN estado = 'completado'
                     AND MONTH(fecha_completado) = MONTH(CURRENT_DATE())
                     AND YEAR(fecha_completado)  = YEAR(CURRENT_DATE())
                THEN 1 ELSE 0 END) AS completadas_mes
        FROM ordenes_servicio_mantenimiento
    ";
    $row = $pdo->query($sql_stats)->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats_osm['por_atender']     = (int)$row['por_atender'];
        $stats_osm['en_proceso']      = (int)$row['en_proceso'];
        $stats_osm['por_validar']     = (int)$row['por_validar'];
        $stats_osm['devueltas']       = (int)$row['devueltas'];
        $stats_osm['completadas_mes'] = (int)$row['completadas_mes'];
    }

    // ========================================
    // Órdenes activas (para la tabla)
    // Prioridad de orden: Devueltas → Pendientes → En Proceso
    // ========================================
    $sql_activas = "
        SELECT 
            id, folio, estado, usuario_nombre, departamento, empresa,
            fecha_creacion, apartado1_data,
            DATEDIFF(CURRENT_DATE, DATE(fecha_creacion)) AS dias_antiguedad
        FROM ordenes_servicio_mantenimiento 
        WHERE estado IN ('pendiente_mantenimiento', 'en_proceso', 'devuelto')
        ORDER BY 
            CASE estado
                WHEN 'devuelto'                THEN 1
                WHEN 'pendiente_mantenimiento' THEN 2
                WHEN 'en_proceso'              THEN 3
                ELSE 4
            END,
            fecha_creacion DESC
        LIMIT 10
    ";
    $osm_activas = $pdo->query($sql_activas)->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // Solicitudes de atención recientes del usuario (top 3)
    // ========================================
    $stmt = $pdo->prepare("
        SELECT folio, descripcion, fecha_creacion, estado, prioridad
        FROM solicitudes_atencion 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 3
    ");
    $stmt->execute([$usuario_id]);
    $solicitudes_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error dashboard mantenimiento: " . $e->getMessage());
}

// ========================================
// Diccionarios de display
// ========================================
$estado_display = [
    'pendiente_mantenimiento' => ['label' => 'Por Atender',  'class' => 'mant-badge--pendiente'],
    'en_proceso'              => ['label' => 'En Proceso',   'class' => 'mant-badge--proceso'],
    'pendiente_usuario'       => ['label' => 'Por Validar',  'class' => 'mant-badge--validar'],
    'devuelto'                => ['label' => 'Devuelto',     'class' => 'mant-badge--devuelto'],
    'completado'              => ['label' => 'Completado',   'class' => 'mant-badge--completado'],
];

$prioridad_class = [
    'critica' => 'mant-prio--critica',
    'alta'    => 'mant-prio--alta',
    'media'   => 'mant-prio--media',
    'baja'    => 'mant-prio--baja',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mantenimiento | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS para formularios y modales (incluye estilos de modo oscuro) -->
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
           TEMA "TALLER DE MANTENIMIENTO"
           Todos los estilos con prefijo .mant- para no colisionar.
           ============================================================ */

        :root {
            --mant-primary:      #f59e0b;   /* Amber industrial */
            --mant-primary-dark: #d97706;
            --mant-secondary:    #3b82f6;   /* Azul acero */
            --mant-success:      #10b981;   /* Verde industrial */
            --mant-danger:       #dc2626;   /* Rojo alerta */
            --mant-accent:       #8b5cf6;   /* Morado validación */
            --mant-steel:        #64748b;   /* Gris acero */
            --mant-card-bg:      #ffffff;
            --mant-card-border:  #e5e7eb;
            --mant-text:         #1f2937;
            --mant-text-muted:   #6b7280;
            --mant-surface-soft: #fafaf9;
            --mant-gradient:      linear-gradient(135deg, #f59e0b 0%, #d97706 55%, #92400e 100%);
            --mant-gradient-cool: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
        }

        body[data-theme="dark"] {
            --mant-card-bg:      #232a36;
            --mant-card-border:  #374151;
            --mant-text:         #e5e7eb;
            --mant-text-muted:   #9ca3af;
            --mant-surface-soft: #1a2029;
        }

        /* ---------- Banner temático de taller ---------- */
        .mant-banner {
            background: var(--mant-gradient);
            background-size: 200% 200%;
            animation: mant-flow 20s ease infinite;
            color: #fff;
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(217, 119, 6, 0.28);
            margin-bottom: 1.25rem;
        }
        /* Patrón de engranajes y tuercas en el fondo */
        .mant-banner::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1.4' opacity='0.35'%3E%3C!-- Engranaje grande --%3E%3Cg transform='translate(30 30)'%3E%3Ccircle r='14'/%3E%3Ccircle r='6'/%3E%3Cpath d='M0 -18 L0 -14 M0 14 L0 18 M-18 0 L-14 0 M14 0 L18 0 M-13 -13 L-10 -10 M10 10 L13 13 M-13 13 L-10 10 M10 -10 L13 -13'/%3E%3C/g%3E%3C!-- Engranaje mediano --%3E%3Cg transform='translate(90 90)'%3E%3Ccircle r='10'/%3E%3Ccircle r='4'/%3E%3Cpath d='M0 -13 L0 -10 M0 10 L0 13 M-13 0 L-10 0 M10 0 L13 0'/%3E%3C/g%3E%3C!-- Hexágono (tuerca) --%3E%3Cpath d='M85 25 L92 21 L99 25 L99 33 L92 37 L85 33 Z'/%3E%3Cpath d='M20 85 L27 81 L34 85 L34 93 L27 97 L20 93 Z'/%3E%3C!-- Punto brillante --%3E%3Ccircle cx='30' cy='30' r='2' fill='%23ffffff'/%3E%3Ccircle cx='90' cy='90' r='1.5' fill='%23ffffff'/%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.65;
            pointer-events: none;
        }
        /* Engranaje decorativo giratorio en la esquina */
        .mant-banner__gear {
            position: absolute;
            right: -30px;
            top: -30px;
            width: 180px;
            height: 180px;
            opacity: 0.18;
            animation: mant-spin 30s linear infinite;
            pointer-events: none;
        }
        .mant-banner__gear--slow {
            right: auto;
            left: -50px;
            bottom: -50px;
            top: auto;
            width: 130px;
            height: 130px;
            animation: mant-spin-reverse 45s linear infinite;
        }
        .mant-banner__content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .mant-banner__title {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .mant-banner__subtitle {
            font-size: 0.85rem;
            opacity: 0.95;
            margin: 0.3rem 0 0 0;
        }
        .mant-banner__cta {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #fff;
            padding: 0.55rem 1.1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.2s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            backdrop-filter: blur(4px);
        }
        .mant-banner__cta:hover {
            background: rgba(255, 255, 255, 0.30);
            color: #fff;
            transform: translateY(-2px);
        }

        @keyframes mant-flow {
            0%, 100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
        }
        @keyframes mant-spin         { to { transform: rotate(360deg); } }
        @keyframes mant-spin-reverse { to { transform: rotate(-360deg); } }

        /* ---------- Cards OSM ---------- */
        .mant-card {
            background: var(--mant-card-bg);
            border: 1px solid var(--mant-card-border);
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
        }
        .mant-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
        }
        body[data-theme="dark"] .mant-card:hover {
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.4);
        }
        /* Barra lateral de color */
        .mant-card::after {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--mant-card-accent, var(--mant-primary));
        }
        /* Círculo decorativo suave */
        .mant-card::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            opacity: 0.10;
            background: var(--mant-card-accent, var(--mant-primary));
        }
        .mant-card__icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            background: var(--mant-card-accent-soft, rgba(245, 158, 11, 0.14));
            color: var(--mant-card-accent, var(--mant-primary));
            margin-bottom: 0.65rem;
        }
        .mant-card__number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--mant-text);
            line-height: 1;
            margin: 0;
        }
        .mant-card__label {
            font-size: 0.7rem;
            color: var(--mant-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
            margin: 0.5rem 0 0 0;
        }
        .mant-card__sub {
            font-size: 0.72rem;
            color: var(--mant-text-muted);
            margin-top: 0.25rem;
        }
        .mant-card--por-atender  { --mant-card-accent: #f59e0b; --mant-card-accent-soft: rgba(245, 158, 11, 0.14); }
        .mant-card--proceso      { --mant-card-accent: #3b82f6; --mant-card-accent-soft: rgba(59, 130, 246, 0.14); }
        .mant-card--por-validar  { --mant-card-accent: #8b5cf6; --mant-card-accent-soft: rgba(139, 92, 246, 0.14); }
        .mant-card--completadas  { --mant-card-accent: #10b981; --mant-card-accent-soft: rgba(16, 185, 129, 0.14); }

        /* Aviso extra de devueltas dentro de la card "Por Atender" */
        .mant-card__alert {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: var(--mant-danger);
            color: #fff;
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
            z-index: 2;
        }

        /* ---------- Sección con header industrial ---------- */
        .mant-section {
            background: var(--mant-card-bg);
            border: 1px solid var(--mant-card-border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 1.25rem;
        }
        .mant-section__header {
            padding: 0.85rem 1.15rem;
            background: var(--mant-gradient-cool);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        /* Textura de rayas industriales */
        .mant-section__header::before {
            content: '';
            position: absolute; inset: 0;
            background-image: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.05) 10px,
                rgba(255,255,255,0.05) 20px
            );
            pointer-events: none;
        }
        .mant-section__title {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 1;
        }
        .mant-section__count {
            background: rgba(255, 255, 255, 0.22);
            padding: 0.2rem 0.65rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .mant-section__link {
            color: #fff;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        .mant-section__link:hover { color: #fff; opacity: 1; text-decoration: underline; }

        /* ---------- Tabla OSM ---------- */
        .mant-table-wrap { overflow-x: auto; }
        .mant-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .mant-table thead th {
            background: var(--mant-surface-soft);
            color: var(--mant-text-muted);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--mant-card-border);
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        .mant-table tbody td {
            padding: 0.7rem 1rem;
            font-size: 0.82rem;
            color: var(--mant-text);
            border-bottom: 1px solid var(--mant-card-border);
            vertical-align: middle;
        }
        .mant-table tbody tr:last-child td { border-bottom: none; }
        .mant-table tbody tr {
            transition: background 0.15s;
            cursor: pointer;
            position: relative;
        }
        .mant-table tbody tr:hover td {
            background: rgba(245, 158, 11, 0.06);
        }
        body[data-theme="dark"] .mant-table tbody tr:hover td {
            background: rgba(245, 158, 11, 0.10);
        }

        /* Marcador de urgencia en filas devueltas */
        .mant-table tbody tr.mant-row--devuelto td:first-child {
            box-shadow: inset 3px 0 0 var(--mant-danger);
        }
        .mant-table tbody tr.mant-row--pendiente td:first-child {
            box-shadow: inset 3px 0 0 var(--mant-primary);
        }
        .mant-table tbody tr.mant-row--proceso td:first-child {
            box-shadow: inset 3px 0 0 var(--mant-secondary);
        }

        .mant-folio {
            font-weight: 700;
            color: var(--mant-primary-dark);
            font-size: 0.8rem;
            white-space: nowrap;
        }
        body[data-theme="dark"] .mant-folio { color: var(--mant-primary); }

        .mant-empresa {
            font-weight: 600;
            color: var(--mant-text);
        }
        .mant-solicitante {
            font-size: 0.72rem;
            color: var(--mant-text-muted);
            display: block;
            margin-top: 2px;
        }
        .mant-depto {
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--mant-text-muted);
            font-size: 0.7rem;
            font-weight: 600;
        }
        .mant-equipo {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--mant-text);
            font-size: 0.78rem;
        }
        .mant-equipo i { color: var(--mant-steel); }

        .mant-dias {
            font-size: 0.72rem;
            padding: 0.2rem 0.55rem;
            border-radius: 12px;
            font-weight: 600;
            background: rgba(107,114,128,0.14);
            color: var(--mant-steel);
        }
        .mant-dias--old {
            background: rgba(220, 38, 38, 0.15);
            color: var(--mant-danger);
        }
        .mant-dias--warn {
            background: rgba(245, 158, 11, 0.15);
            color: var(--mant-primary-dark);
        }
        body[data-theme="dark"] .mant-dias--warn { color: #fbbf24; }

        /* ---------- Badges estado ---------- */
        .mant-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .mant-badge::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
        }
        .mant-badge--pendiente  { background: rgba(245,158,11,0.15); color: #d97706; }
        .mant-badge--proceso    { background: rgba(59,130,246,0.15); color: #2563eb; }
        .mant-badge--validar    { background: rgba(139,92,246,0.15); color: #7c3aed; }
        .mant-badge--devuelto   { background: rgba(220,38,38,0.15); color: #dc2626; }
        .mant-badge--completado { background: rgba(16,185,129,0.15); color: #059669; }
        body[data-theme="dark"] .mant-badge--pendiente  { color: #fbbf24; }
        body[data-theme="dark"] .mant-badge--proceso    { color: #60a5fa; }
        body[data-theme="dark"] .mant-badge--validar    { color: #a78bfa; }
        body[data-theme="dark"] .mant-badge--devuelto   { color: #f87171; }
        body[data-theme="dark"] .mant-badge--completado { color: #34d399; }

        /* Prioridad */
        .mant-prio {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .mant-prio--critica { background: rgba(220, 38, 38, 0.15);  color: #dc2626; }
        .mant-prio--alta    { background: rgba(245, 158, 11, 0.18); color: #d97706; }
        .mant-prio--media   { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .mant-prio--baja    { background: rgba(107, 114, 128, 0.14); color: #6b7280; }
        body[data-theme="dark"] .mant-prio--critica { color: #f87171; }
        body[data-theme="dark"] .mant-prio--alta    { color: #fbbf24; }
        body[data-theme="dark"] .mant-prio--media   { color: #60a5fa; }
        body[data-theme="dark"] .mant-prio--baja    { color: #9ca3af; }

        /* ---------- Empty state ---------- */
        .mant-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--mant-text-muted);
        }
        .mant-empty__icon {
            font-size: 2.6rem;
            color: var(--mant-primary);
            opacity: 0.5;
            margin-bottom: 0.5rem;
            display: block;
        }
        .mant-empty__text {
            font-size: 0.88rem;
            margin: 0;
        }

        /* ---------- Panel acciones rápidas ---------- */
        .mant-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            padding: 1rem;
        }
        .mant-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
            border: none;
            cursor: pointer;
        }
        .mant-action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        .mant-action-btn--primary {
            background: var(--mant-gradient-cool);
            color: #fff;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.28);
        }
        .mant-action-btn--primary:hover { color: #fff; box-shadow: 0 6px 16px rgba(217, 119, 6, 0.4); }
        .mant-action-btn--secondary {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
        }
        .mant-action-btn--secondary:hover { color: #2563eb; background: rgba(59, 130, 246, 0.22); }
        .mant-action-btn--outline {
            background: transparent;
            color: var(--mant-text-muted);
            border: 1px solid var(--mant-card-border);
        }
        .mant-action-btn--outline:hover { color: var(--mant-text); border-color: var(--mant-steel); background: var(--mant-surface-soft); }
        body[data-theme="dark"] .mant-action-btn--secondary { color: #60a5fa; }

        /* ---------- Mini list de solicitudes de atención ---------- */
        .mant-mini-list { padding: 0.5rem; }
        .mant-mini-item {
            display: block;
            padding: 0.7rem 0.85rem;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s;
            border: 1px solid transparent;
        }
        .mant-mini-item + .mant-mini-item { margin-top: 0.35rem; }
        .mant-mini-item:hover {
            background: rgba(245, 158, 11, 0.06);
            border-color: rgba(245, 158, 11, 0.20);
            color: inherit;
        }
        body[data-theme="dark"] .mant-mini-item:hover {
            background: rgba(245, 158, 11, 0.10);
        }
        .mant-mini-item__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
        .mant-mini-item__folio {
            font-family: 'Poppins', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--mant-primary-dark);
        }
        body[data-theme="dark"] .mant-mini-item__folio { color: var(--mant-primary); }
        .mant-mini-item__date {
            font-size: 0.7rem;
            color: var(--mant-text-muted);
        }
        .mant-mini-item__desc {
            font-size: 0.8rem;
            color: var(--mant-text);
            margin: 0.25rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .mant-mini-item__meta {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        /* Badges compactos de solicitudes de atención */
        .mant-mini-badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .mant-mini-badge--pendiente  { background: rgba(245,158,11,0.18); color: #d97706; }
        .mant-mini-badge--en_proceso { background: rgba(59,130,246,0.18); color: #2563eb; }
        .mant-mini-badge--finalizada { background: rgba(16,185,129,0.18); color: #059669; }
        .mant-mini-badge--cancelada  { background: rgba(107,114,128,0.18); color: #6b7280; }
        body[data-theme="dark"] .mant-mini-badge--pendiente  { color: #fbbf24; }
        body[data-theme="dark"] .mant-mini-badge--en_proceso { color: #60a5fa; }
        body[data-theme="dark"] .mant-mini-badge--finalizada { color: #34d399; }

        /* ---------- Ajustes overrides sobre estilos base ---------- */
        body[data-theme="dark"] .top-navbar {
            background: transparent;
            box-shadow: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mant-banner { padding: 1.15rem 1rem; }
            .mant-banner__title { font-size: 1.15rem; }
            .mant-banner__gear { display: none; }
            .mant-card__number  { font-size: 1.6rem; }
            .mant-action-btn { flex: 1 1 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Top navbar (bienvenida + badge) -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </p>
                    </div>
                    <div class="user-info">
                        <button class="btn-anuncios-trigger" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
                            <i class="bi bi-megaphone-fill"></i>
                            <span class="badge-count" id="anunciosBadge"></span>
                        </button>
                        <span class="user-badge" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="bi bi-tools"></i>
                            MANTENIMIENTO
                        </span>
                    </div>
                </div>

                <?php echo mostrar_alerta(); ?>

                <!-- ============================================ -->
                <!-- BANNER TEMÁTICO TALLER                       -->
                <!-- ============================================ -->
                <div class="mant-banner">
                    <!-- Engranajes decorativos giratorios -->
                    <svg class="mant-banner__gear" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <g fill="#ffffff">
                            <path d="M50,10 L55,10 L58,25 A25,25 0 0,1 68,29 L79,20 L83,24 L74,35 A25,25 0 0,1 78,45 L93,48 L93,53 L78,56 A25,25 0 0,1 74,66 L83,77 L79,81 L68,72 A25,25 0 0,1 58,76 L55,91 L50,91 L45,91 L42,76 A25,25 0 0,1 32,72 L21,81 L17,77 L26,66 A25,25 0 0,1 22,56 L7,53 L7,48 L22,45 A25,25 0 0,1 26,35 L17,24 L21,20 L32,29 A25,25 0 0,1 42,25 Z"/>
                            <circle cx="50" cy="50" r="13" fill="#f59e0b"/>
                        </g>
                    </svg>
                    <svg class="mant-banner__gear mant-banner__gear--slow" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <g fill="#ffffff">
                            <path d="M50,10 L55,10 L58,25 A25,25 0 0,1 68,29 L79,20 L83,24 L74,35 A25,25 0 0,1 78,45 L93,48 L93,53 L78,56 A25,25 0 0,1 74,66 L83,77 L79,81 L68,72 A25,25 0 0,1 58,76 L55,91 L50,91 L45,91 L42,76 A25,25 0 0,1 32,72 L21,81 L17,77 L26,66 A25,25 0 0,1 22,56 L7,53 L7,48 L22,45 A25,25 0 0,1 26,35 L17,24 L21,20 L32,29 A25,25 0 0,1 42,25 Z"/>
                            <circle cx="50" cy="50" r="13" fill="#d97706"/>
                        </g>
                    </svg>

                    <div class="mant-banner__content">
                        <div>
                            <h1 class="mant-banner__title">
                                <i class="bi bi-wrench-adjustable-circle-fill"></i>
                                Taller de Mantenimiento
                            </h1>
                            <p class="mant-banner__subtitle">
                                Centro de operaciones para atender las Órdenes de Servicio de todos los departamentos.
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="mant-banner__cta">
                            <i class="bi bi-clipboard2-check-fill"></i>
                            Gestionar todas las OSM
                        </a>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- 4 CARDS DE MÉTRICAS OSM                      -->
                <!-- ============================================ -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-lg-3">
                        <div class="mant-card mant-card--por-atender">
                            <?php if ($stats_osm['devueltas'] > 0): ?>
                            <span class="mant-card__alert" title="<?php echo $stats_osm['devueltas']; ?> órdenes devueltas por el usuario">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?php echo $stats_osm['devueltas']; ?> devuelta<?php echo $stats_osm['devueltas'] > 1 ? 's' : ''; ?>
                            </span>
                            <?php endif; ?>
                            <div class="mant-card__icon"><i class="bi bi-cone-striped"></i></div>
                            <h2 class="mant-card__number"><?php echo $stats_osm['por_atender']; ?></h2>
                            <p class="mant-card__label">Por Atender</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="mant-card mant-card--proceso">
                            <div class="mant-card__icon"><i class="bi bi-gear-fill"></i></div>
                            <h2 class="mant-card__number"><?php echo $stats_osm['en_proceso']; ?></h2>
                            <p class="mant-card__label">En Proceso</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="mant-card mant-card--por-validar">
                            <div class="mant-card__icon"><i class="bi bi-hourglass-split"></i></div>
                            <h2 class="mant-card__number"><?php echo $stats_osm['por_validar']; ?></h2>
                            <p class="mant-card__label">Por Validar</p>
                            <p class="mant-card__sub">Esperando visto bueno del usuario</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="mant-card mant-card--completadas">
                            <div class="mant-card__icon"><i class="bi bi-check2-square"></i></div>
                            <h2 class="mant-card__number"><?php echo $stats_osm['completadas_mes']; ?></h2>
                            <p class="mant-card__label">Completadas</p>
                            <p class="mant-card__sub">En <?php 
                                $meses_es = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                                echo $meses_es[(int)date('n') - 1];
                            ?></p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABLA OSM ACTIVAS (protagónica)              -->
                <!-- ============================================ -->
                <div class="mant-section">
                    <div class="mant-section__header">
                        <h3 class="mant-section__title">
                            <i class="bi bi-clipboard2-pulse-fill"></i>
                            Órdenes activas en el taller
                            <span class="mant-section__count"><?php echo count($osm_activas); ?></span>
                        </h3>
                        <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php?base=local" class="mant-section__link">
                            Ver todas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (empty($osm_activas)): ?>
                        <div class="mant-empty">
                            <i class="bi bi-check-circle mant-empty__icon"></i>
                            <p class="mant-empty__text">¡El taller está al día! No hay órdenes activas por atender.</p>
                        </div>
                    <?php else: ?>
                        <div class="mant-table-wrap">
                            <table class="mant-table">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Empresa / Solicitante</th>
                                        <th>Depto.</th>
                                        <th>Unidad / Equipo</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Antigüedad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($osm_activas as $orden):
                                        // Decodificar apartado1 para obtener unidad/equipo y prioridad
                                        $ap1 = [];
                                        if (!empty($orden['apartado1_data'])) {
                                            $decoded = json_decode($orden['apartado1_data'], true);
                                            if (is_array($decoded)) $ap1 = $decoded;
                                        }
                                        $unidad    = $ap1['unidad_equipo'] ?? '—';
                                        $prio_key  = strtolower(trim($ap1['prioridad'] ?? 'baja'));
                                        $prio_cls  = $prioridad_class[$prio_key] ?? 'mant-prio--baja';
                                        $prio_lbl  = ucfirst($prio_key);

                                        $est_info  = $estado_display[$orden['estado']] ?? ['label' => $orden['estado'], 'class' => ''];

                                        // Clase de fila según urgencia
                                        $row_class = '';
                                        if     ($orden['estado'] === 'devuelto')                $row_class = 'mant-row--devuelto';
                                        elseif ($orden['estado'] === 'pendiente_mantenimiento') $row_class = 'mant-row--pendiente';
                                        elseif ($orden['estado'] === 'en_proceso')              $row_class = 'mant-row--proceso';

                                        // Clase de días según antigüedad
                                        $dias = (int)($orden['dias_antiguedad'] ?? 0);
                                        $dias_cls = '';
                                        if     ($dias > 15) $dias_cls = 'mant-dias--old';
                                        elseif ($dias > 7)  $dias_cls = 'mant-dias--warn';

                                        $url_ver = URL_BASE . 'dashboard/ordenes_servicio/ver_orden_servicio.php?id=' . intval($orden['id']);
                                    ?>
                                    <tr class="<?php echo $row_class; ?>" onclick="window.location='<?php echo $url_ver; ?>'">
                                        <td><span class="mant-folio"><?php echo htmlspecialchars($orden['folio']); ?></span></td>
                                        <td>
                                            <span class="mant-empresa"><?php echo htmlspecialchars($orden['empresa'] ?? '—'); ?></span>
                                            <span class="mant-solicitante"><?php echo htmlspecialchars($orden['usuario_nombre'] ?? ''); ?></span>
                                        </td>
                                        <td><span class="mant-depto"><?php echo htmlspecialchars($orden['departamento'] ?? '—'); ?></span></td>
                                        <td>
                                            <span class="mant-equipo">
                                                <i class="bi bi-gear-wide-connected"></i>
                                                <?php echo htmlspecialchars(mb_substr($unidad, 0, 32)); ?><?php echo mb_strlen($unidad) > 32 ? '…' : ''; ?>
                                            </span>
                                        </td>
                                        <td><span class="mant-prio <?php echo $prio_cls; ?>"><?php echo $prio_lbl; ?></span></td>
                                        <td><span class="mant-badge <?php echo $est_info['class']; ?>"><?php echo $est_info['label']; ?></span></td>
                                        <td><span class="mant-dias <?php echo $dias_cls; ?>"><?php echo $dias; ?> día<?php echo $dias == 1 ? '' : 's'; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================ -->
                <!-- FILA INFERIOR: Acciones + Solicitudes recientes -->
                <!-- ============================================ -->
                <div class="row g-3">
                    <!-- Acciones rápidas -->
                    <div class="col-lg-8 align-self-start">
                        <div class="mant-section">
                            <div class="mant-section__header">
                                <h3 class="mant-section__title">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Herramientas del Taller
                                </h3>
                            </div>
                            <div class="mant-actions">
                                <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="mant-action-btn mant-action-btn--primary">
                                    <i class="bi bi-clipboard2-check-fill"></i>
                                    Órdenes de Servicio
                                </a>
                                <a href="#" class="mant-action-btn mant-action-btn--secondary" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                    <i class="bi bi-plus-circle-fill"></i>
                                    Nueva Solicitud TI
                                </a>
                                <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="mant-action-btn mant-action-btn--outline">
                                    <i class="bi bi-list-ul"></i>
                                    Mis Solicitudes
                                </a>
                                <a href="<?php echo URL_BASE; ?>solicitudes/buscar.php" class="mant-action-btn mant-action-btn--outline">
                                    <i class="bi bi-search"></i>
                                    Buscar
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Solicitudes de atención recientes (compacto) -->
                    <div class="col-lg-4">
                        <div class="mant-section h-100">
                            <div class="mant-section__header">
                                <h3 class="mant-section__title">
                                    <i class="bi bi-clock-history"></i>
                                    Mis Solicitudes Recientes
                                </h3>
                                <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="mant-section__link">
                                    Ver todas
                                </a>
                            </div>

                            <?php if (empty($solicitudes_recientes)): ?>
                                <div class="mant-empty" style="padding: 1.5rem 1rem;">
                                    <i class="bi bi-inbox mant-empty__icon" style="font-size: 2rem;"></i>
                                    <p class="mant-empty__text" style="font-size: 0.8rem;">Sin solicitudes recientes</p>
                                </div>
                            <?php else: ?>
                                <div class="mant-mini-list">
                                    <?php foreach ($solicitudes_recientes as $sol):
                                        $estado_key   = $sol['estado'];
                                        $estado_class = 'mant-mini-badge--' . preg_replace('/[^a-z_]/', '', $estado_key);
                                    ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" class="mant-mini-item">
                                        <div class="mant-mini-item__row">
                                            <span class="mant-mini-item__folio"><?php echo htmlspecialchars($sol['folio']); ?></span>
                                            <span class="mant-mini-item__date"><?php echo date('d/m/Y', strtotime($sol['fecha_creacion'])); ?></span>
                                        </div>
                                        <p class="mant-mini-item__desc"><?php echo htmlspecialchars(mb_substr($sol['descripcion'], 0, 90)); ?><?php echo mb_strlen($sol['descripcion']) > 90 ? '…' : ''; ?></p>
                                        <div class="mant-mini-item__meta">
                                            <span class="mant-mini-badge <?php echo $estado_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $estado_key)); ?>
                                            </span>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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