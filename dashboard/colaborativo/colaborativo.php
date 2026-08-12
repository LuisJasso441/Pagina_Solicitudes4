<?php
/**
 * Dashboard para departamentos colaborativos
 * ⭐ REDISEÑO: Enfocado en documentos SSC (Solicitudes de Servicio a Clientes)
 *    con temática de laboratorio químico
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

if (!es_usuario_colaborativo()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento   = $_SESSION['departamento_nombre'];
$usuario_id     = $_SESSION['usuario_id'];

// ========================================
// DETERMINAR ROL DEL USUARIO
// ========================================
$dept_lower      = strtolower(trim($_SESSION['departamento'] ?? ''));
$es_laboratorio  = ($dept_lower === 'laboratorio');
$es_direccion    = in_array($dept_lower, ['direccion', 'dirección']);
$es_ventas_norm  = in_array($dept_lower, ['normatividad', 'ventas', 'ptar']);

// ========================================
// ESTADÍSTICAS SSC según rol
// ========================================
$stats_ssc = [
    'enviadas'       => 0,
    'en_seguimiento' => 0,
    'completadas'    => 0,
    'total_mes'      => 0,
];

$ssc_enviadas         = [];
$solicitudes_recientes = [];

try {
    $pdo = conectarDB();

    // Filtro por rol: Laboratorio y Dirección ven todo,
    // Ventas/Normatividad/PTAR ven solo lo de su departamento
    if ($es_laboratorio || $es_direccion) {
        $where_extra = '';
        $params_stats = [];
    } else {
        $where_extra  = " AND LOWER(departamento_creador) = :dept";
        $params_stats = [':dept' => $dept_lower];
    }

    // Enviadas (esperando atención de Laboratorio)
    $sql = "SELECT COUNT(*) FROM documentos_colaborativos 
            WHERE estado = 'enviado' AND ubicacion = 'local'" . $where_extra;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_stats);
    $stats_ssc['enviadas'] = (int)$stmt->fetchColumn();

    // En Seguimiento (Lab trabajando en ellas)
    $sql = "SELECT COUNT(*) FROM documentos_colaborativos 
            WHERE estado = 'en_seguimiento' AND ubicacion = 'local'" . $where_extra;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_stats);
    $stats_ssc['en_seguimiento'] = (int)$stmt->fetchColumn();

    // Completadas (histórico total, sin filtro de ubicación)
    $sql = "SELECT COUNT(*) FROM documentos_colaborativos 
            WHERE estado = 'completado'" . $where_extra;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_stats);
    $stats_ssc['completadas'] = (int)$stmt->fetchColumn();

    // Total del mes actual
    $sql = "SELECT COUNT(*) FROM documentos_colaborativos 
            WHERE MONTH(fecha_creacion) = MONTH(CURRENT_DATE()) 
              AND YEAR(fecha_creacion)  = YEAR(CURRENT_DATE())" . $where_extra;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_stats);
    $stats_ssc['total_mes'] = (int)$stmt->fetchColumn();

    // ========================================
    // TABLA de SSC en estado "Enviado"
    // ========================================
    $sql = "SELECT id, folio, nombre_cliente, departamento_creador, 
                   servicio_solicitado, prioridad, fecha_creacion, solicitado_por
            FROM documentos_colaborativos 
            WHERE estado = 'enviado' AND ubicacion = 'local'" . $where_extra . "
            ORDER BY 
                CASE prioridad
                    WHEN 'critica' THEN 1
                    WHEN 'alta'    THEN 2
                    WHEN 'media'   THEN 3
                    WHEN 'baja'    THEN 4
                    ELSE 5
                END,
                fecha_creacion DESC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_stats);
    $ssc_enviadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    error_log("Error dashboard colaborativo: " . $e->getMessage());
}

// ========================================
// Diccionarios de display
// ========================================
$servicios_display = [
    'tratamiento_agua'    => 'Tratamiento de agua',
    'revision_productos'  => 'Prod. químicos/residuos',
    'calibracion_equipos' => 'Calibración de equipos',
    'otro'                => 'Otro',
];

$servicios_icono = [
    'tratamiento_agua'    => 'bi-droplet-fill',
    'revision_productos'  => 'bi-eyedropper',
    'calibracion_equipos' => 'bi-rulers',
    'otro'                => 'bi-three-dots',
];

// Etiqueta de la primera card según rol
$label_enviadas = $es_laboratorio ? 'Por Atender' : 'Enviadas';
$label_tabla    = $es_laboratorio
    ? 'SSC pendientes de atender'
    : ($es_direccion ? 'SSC en trámite (todos los departamentos)' : 'Mis SSC enviadas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Colaborativo - <?php echo htmlspecialchars($departamento); ?></title>
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
           TEMA "LABORATORIO QUÍMICO / SSC"
           Todos los estilos están encapsulados con prefijo .chem-
           para no colisionar con el resto del sistema.
           ============================================================ */

        :root {
            --chem-primary:      #04CF85;
            --chem-secondary:    #06b6d4;
            --chem-accent:       #8b5cf6;
            --chem-warning:      #f59e0b;
            --chem-danger:       #ef4444;
            --chem-card-bg:      #ffffff;
            --chem-card-border:  #e5e7eb;
            --chem-text:         #1f2937;
            --chem-text-muted:   #6b7280;
            --chem-surface-soft: #f9fafb;
            --chem-gradient:      linear-gradient(135deg, #04CF85 0%, #06b6d4 55%, #8b5cf6 100%);
            --chem-gradient-soft: linear-gradient(135deg, rgba(4,207,133,0.10) 0%, rgba(6,182,212,0.10) 55%, rgba(139,92,246,0.10) 100%);
        }

        body[data-theme="dark"] {
            --chem-card-bg:      #232a36;
            --chem-card-border:  #374151;
            --chem-text:         #e5e7eb;
            --chem-text-muted:   #9ca3af;
            --chem-surface-soft: #1a2029;
        }

        /* ---------- Banner temático ---------- */
        .chem-banner {
            background: var(--chem-gradient);
            background-size: 200% 200%;
            animation: chem-flow 18s ease infinite;
            color: #fff;
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(4, 207, 133, 0.18);
            margin-bottom: 1.25rem;
        }
        .chem-banner::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='72' viewBox='0 0 80 72'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1.2' opacity='0.35'%3E%3Cpath d='M20 4L36 14v20L20 44L4 34V14z'/%3E%3Cpath d='M60 4L76 14v20L60 44L44 34V14z'/%3E%3Cpath d='M40 32L56 42v20L40 72L24 62V42z'/%3E%3Ccircle cx='20' cy='24' r='2.2' fill='%23ffffff'/%3E%3Ccircle cx='60' cy='24' r='2.2' fill='%23ffffff'/%3E%3Ccircle cx='40' cy='52' r='2.2' fill='%23ffffff'/%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.55;
            pointer-events: none;
        }
        .chem-banner__content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .chem-banner__title {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .chem-banner__subtitle {
            font-size: 0.85rem;
            opacity: 0.92;
            margin: 0.25rem 0 0 0;
        }
        .chem-banner__cta {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.2s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .chem-banner__cta:hover {
            background: rgba(255, 255, 255, 0.28);
            color: #fff;
            transform: translateY(-1px);
        }

        @keyframes chem-flow {
            0%, 100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
        }

        /* ---------- Cards SSC ---------- */
        .chem-card {
            background: var(--chem-card-bg);
            border: 1px solid var(--chem-card-border);
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
        }
        .chem-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
        }
        body[data-theme="dark"] .chem-card:hover {
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.4);
        }
        .chem-card::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            opacity: 0.10;
            background: var(--chem-card-accent, var(--chem-primary));
        }
        .chem-card__icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            background: var(--chem-card-accent-soft, rgba(4,207,133,0.14));
            color: var(--chem-card-accent, var(--chem-primary));
            margin-bottom: 0.65rem;
        }
        .chem-card__number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--chem-text);
            line-height: 1;
            margin: 0;
        }
        .chem-card__label {
            font-size: 0.7rem;
            color: var(--chem-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
            margin: 0.5rem 0 0 0;
        }
        .chem-card--enviadas      { --chem-card-accent: #04CF85; --chem-card-accent-soft: rgba(4,207,133,0.14); }
        .chem-card--seguimiento   { --chem-card-accent: #06b6d4; --chem-card-accent-soft: rgba(6,182,212,0.14); }
        .chem-card--completadas   { --chem-card-accent: #8b5cf6; --chem-card-accent-soft: rgba(139,92,246,0.14); }
        .chem-card--mes           { --chem-card-accent: #f59e0b; --chem-card-accent-soft: rgba(245,158,11,0.14); }

        /* ---------- Sección con header temático ---------- */
        .chem-section {
            background: var(--chem-card-bg);
            border: 1px solid var(--chem-card-border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 1.25rem;
        }
        .chem-section__header {
            padding: 0.85rem 1.15rem;
            background: var(--chem-gradient);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chem-section__title {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .chem-section__count {
            background: rgba(255, 255, 255, 0.22);
            padding: 0.2rem 0.65rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .chem-section__link {
            color: #fff;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            opacity: 0.95;
        }
        .chem-section__link:hover { color: #fff; opacity: 1; text-decoration: underline; }

        /* ---------- Tabla SSC ---------- */
        .chem-table-wrap { overflow-x: auto; }
        .chem-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .chem-table thead th {
            background: var(--chem-surface-soft);
            color: var(--chem-text-muted);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--chem-card-border);
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        .chem-table tbody td {
            padding: 0.7rem 1rem;
            font-size: 0.82rem;
            color: var(--chem-text);
            border-bottom: 1px solid var(--chem-card-border);
            vertical-align: middle;
        }
        .chem-table tbody tr:last-child td { border-bottom: none; }
        .chem-table tbody tr {
            transition: background 0.15s;
            cursor: pointer;
        }
        .chem-table tbody tr:hover td {
            background: rgba(4, 207, 133, 0.05);
        }
        body[data-theme="dark"] .chem-table tbody tr:hover td {
            background: rgba(4, 207, 133, 0.08);
        }
        .chem-folio {
            font-weight: 600;
            color: var(--chem-primary);
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .chem-cliente {
            font-weight: 600;
            color: var(--chem-text);
        }
        .chem-servicio {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--chem-text-muted);
            font-size: 0.78rem;
        }
        .chem-servicio i { color: var(--chem-secondary); }

        /* ---------- Badges de prioridad ---------- */
        .chem-prio {
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
        .chem-prio::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
        }
        .chem-prio--critica { background: rgba(239, 68, 68, 0.15);  color: #ef4444; }
        .chem-prio--alta    { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .chem-prio--media   { background: rgba(6, 182, 212, 0.15);  color: #0891b2; }
        .chem-prio--baja    { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
        body[data-theme="dark"] .chem-prio--alta  { color: #fbbf24; }
        body[data-theme="dark"] .chem-prio--media { color: #22d3ee; }
        body[data-theme="dark"] .chem-prio--baja  { color: #9ca3af; }

        /* ---------- Empty state ---------- */
        .chem-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--chem-text-muted);
        }
        .chem-empty__icon {
            font-size: 2.6rem;
            color: var(--chem-primary);
            opacity: 0.5;
            margin-bottom: 0.5rem;
            display: block;
        }
        .chem-empty__text {
            font-size: 0.88rem;
            margin: 0;
        }

        /* ---------- Panel acciones rápidas ---------- */
        .chem-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            padding: 1rem;
        }
        .chem-action-btn {
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
        .chem-action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        .chem-action-btn--primary {
            background: var(--chem-gradient);
            color: #fff;
            box-shadow: 0 4px 12px rgba(4, 207, 133, 0.25);
        }
        .chem-action-btn--primary:hover { color: #fff; box-shadow: 0 6px 16px rgba(4, 207, 133, 0.35); }
        .chem-action-btn--secondary {
            background: rgba(6, 182, 212, 0.12);
            color: #06b6d4;
        }
        .chem-action-btn--secondary:hover { color: #06b6d4; background: rgba(6, 182, 212, 0.20); }
        .chem-action-btn--accent {
            background: rgba(139, 92, 246, 0.12);
            color: #8b5cf6;
        }
        .chem-action-btn--accent:hover { color: #8b5cf6; background: rgba(139, 92, 246, 0.20); }
        body[data-theme="dark"] .chem-action-btn--secondary { color: #22d3ee; }
        body[data-theme="dark"] .chem-action-btn--accent    { color: #a78bfa; }

        /* ---------- Mini list de solicitudes de atención ---------- */
        .chem-mini-list { padding: 0.5rem; }
        .chem-mini-item {
            display: block;
            padding: 0.7rem 0.85rem;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s;
            border: 1px solid transparent;
        }
        .chem-mini-item + .chem-mini-item { margin-top: 0.35rem; }
        .chem-mini-item:hover {
            background: rgba(4, 207, 133, 0.06);
            border-color: rgba(4, 207, 133, 0.15);
            color: inherit;
        }
        body[data-theme="dark"] .chem-mini-item:hover {
            background: rgba(4, 207, 133, 0.10);
        }
        .chem-mini-item__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
        .chem-mini-item__folio {
            font-family: 'Poppins', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--chem-primary);
        }
        .chem-mini-item__date {
            font-size: 0.7rem;
            color: var(--chem-text-muted);
        }
        .chem-mini-item__desc {
            font-size: 0.8rem;
            color: var(--chem-text);
            margin: 0.25rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .chem-mini-item__meta {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        /* Badges compactos para estados de solicitudes de atención */
        .chem-mini-badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .chem-mini-badge--pendiente  { background: rgba(245,158,11,0.18); color: #d97706; }
        .chem-mini-badge--en_proceso { background: rgba(6,182,212,0.18); color: #0891b2; }
        .chem-mini-badge--finalizada { background: rgba(4,207,133,0.18); color: #059669; }
        .chem-mini-badge--cancelada  { background: rgba(107,114,128,0.18); color: #6b7280; }
        body[data-theme="dark"] .chem-mini-badge--pendiente  { color: #fbbf24; }
        body[data-theme="dark"] .chem-mini-badge--en_proceso { color: #22d3ee; }
        body[data-theme="dark"] .chem-mini-badge--finalizada { color: #34d399; }

        /* ---------- Ajustes overrides sobre estilos base ---------- */
        body[data-theme="dark"] .top-navbar {
            background: transparent;
            box-shadow: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chem-banner { padding: 1.15rem 1rem; }
            .chem-banner__title { font-size: 1.15rem; }
            .chem-card__number  { font-size: 1.6rem; }
            .chem-action-btn { flex: 1 1 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

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
                        <span class="user-badge" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                            <i class="bi bi-eyedropper"></i>
                            <?php echo htmlspecialchars($departamento); ?>
                        </span>
                    </div>
                </div>

                <?php echo mostrar_alerta(); ?>

                <!-- ============================================ -->
                <!-- BANNER TEMÁTICO SSC                          -->
                <!-- ============================================ -->
                <div class="chem-banner">
                    <div class="chem-banner__content">
                        <div>
                            <h1 class="chem-banner__title">
                                <i class="bi bi-eyedropper"></i>
                                Solicitudes de Servicio a Clientes
                            </h1>
                            <p class="chem-banner__subtitle">
                                <?php if ($es_laboratorio): ?>
                                    Recibes y respondes las solicitudes analíticas de Normatividad, Ventas y Dirección.
                                <?php elseif ($es_direccion): ?>
                                    Vista global de todas las solicitudes de servicio a clientes.
                                <?php else: ?>
                                    Solicitudes de análisis, calibración y tratamiento generadas por <?php echo htmlspecialchars($departamento); ?>.
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php" class="chem-banner__cta">
                            <i class="bi bi-arrow-right-circle-fill"></i>
                            Ver todos las Solicitudes de Servicio a Clientes
                        </a>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- 4 CARDS DE MÉTRICAS SSC                      -->
                <!-- ============================================ -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-lg-3">
                        <div class="chem-card chem-card--enviadas">
                            <div class="chem-card__icon"><i class="bi bi-send-fill"></i></div>
                            <h2 class="chem-card__number"><?php echo $stats_ssc['enviadas']; ?></h2>
                            <p class="chem-card__label"><?php echo htmlspecialchars($label_enviadas); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="chem-card chem-card--seguimiento">
                            <div class="chem-card__icon"><i class="bi bi-arrow-repeat"></i></div>
                            <h2 class="chem-card__number"><?php echo $stats_ssc['en_seguimiento']; ?></h2>
                            <p class="chem-card__label">En Seguimiento</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="chem-card chem-card--completadas">
                            <div class="chem-card__icon"><i class="bi bi-check2-circle"></i></div>
                            <h2 class="chem-card__number"><?php echo $stats_ssc['completadas']; ?></h2>
                            <p class="chem-card__label">Completadas</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="chem-card chem-card--mes">
                            <div class="chem-card__icon"><i class="bi bi-hexagon-fill"></i></div>
                            <h2 class="chem-card__number"><?php echo $stats_ssc['total_mes']; ?></h2>
                            <p class="chem-card__label">Total del mes</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABLA SSC ENVIADAS (protagónica)              -->
                <!-- ============================================ -->
                <div class="chem-section">
                    <div class="chem-section__header">
                        <h3 class="chem-section__title">
                            <i class="bi bi-send-check-fill"></i>
                            <?php echo htmlspecialchars($label_tabla); ?>
                            <span class="chem-section__count"><?php echo count($ssc_enviadas); ?></span>
                        </h3>
                        <a href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php?estado=enviado" class="chem-section__link">
                            Ver todas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (empty($ssc_enviadas)): ?>
                        <div class="chem-empty">
                            <i class="bi bi-inbox chem-empty__icon"></i>
                            <p class="chem-empty__text">No hay solicitudes en estado "Enviado" por el momento.</p>
                        </div>
                    <?php else: ?>
                        <div class="chem-table-wrap">
                            <table class="chem-table">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Cliente</th>
                                        <th>Depto.</th>
                                        <th>Servicio</th>
                                        <th>Prioridad</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ssc_enviadas as $doc): 
                                        $servicio_key  = $doc['servicio_solicitado'] ?? 'otro';
                                        $servicio_txt  = $servicios_display[$servicio_key] ?? 'N/A';
                                        $servicio_icon = $servicios_icono[$servicio_key]   ?? 'bi-three-dots';
                                        $prio_key      = strtolower($doc['prioridad'] ?? 'baja');
                                        $prio_class    = 'chem-prio--' . preg_replace('/[^a-z]/', '', $prio_key);
                                        $url_ver       = URL_BASE . 'dashboard/colaborativo/ver_documento.php?id=' . intval($doc['id']);
                                    ?>
                                    <tr onclick="window.location='<?php echo $url_ver; ?>'">
                                        <td><span class="chem-folio"><?php echo htmlspecialchars($doc['folio']); ?></span></td>
                                        <td><span class="chem-cliente"><?php echo htmlspecialchars($doc['nombre_cliente'] ?? 'Sin cliente'); ?></span></td>
                                        <td><small class="text-uppercase" style="letter-spacing:0.5px; color: var(--chem-text-muted); font-size: 0.7rem; font-weight: 600;"><?php echo htmlspecialchars($doc['departamento_creador']); ?></small></td>
                                        <td>
                                            <span class="chem-servicio">
                                                <i class="bi <?php echo $servicio_icon; ?>"></i>
                                                <?php echo htmlspecialchars($servicio_txt); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="chem-prio <?php echo $prio_class; ?>">
                                                <?php echo ucfirst($prio_key); ?>
                                            </span>
                                        </td>
                                        <td><small style="color: var(--chem-text-muted); font-size: 0.75rem;"><?php echo date('d/m/Y', strtotime($doc['fecha_creacion'])); ?></small></td>
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
                        <div class="chem-section">
                            <div class="chem-section__header">
                                <h3 class="chem-section__title">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Acciones Rápidas
                                </h3>
                            </div>
                            <div class="chem-actions">
                                <a href="#" class="chem-action-btn chem-action-btn--primary" data-bs-toggle="modal" data-bs-target="#modalNuevaSolicitud">
                                    <i class="bi bi-plus-circle-fill"></i>
                                    Nueva Solicitud de Atención
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/colaborativo/documentos_colaborativos.php" class="chem-action-btn chem-action-btn--secondary">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                    Solicitudes de Servicio a Clientes
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="chem-action-btn chem-action-btn--accent">
                                    <i class="bi bi-clipboard-check-fill"></i>
                                    Órdenes de Mantenimiento
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Solicitudes de atención recientes (compacto) -->
                    <div class="col-lg-4">
                        <div class="chem-section h-100">
                            <div class="chem-section__header">
                                <h3 class="chem-section__title">
                                    <i class="bi bi-clock-history"></i>
                                    Mis Solicitudes Recientes
                                </h3>
                                <a href="<?php echo URL_BASE; ?>solicitudes/listar.php" class="chem-section__link">
                                    Ver todas
                                </a>
                            </div>

                            <?php if (empty($solicitudes_recientes)): ?>
                                <div class="chem-empty" style="padding: 1.5rem 1rem;">
                                    <i class="bi bi-inbox chem-empty__icon" style="font-size: 2rem;"></i>
                                    <p class="chem-empty__text" style="font-size: 0.8rem;">Sin solicitudes recientes</p>
                                </div>
                            <?php else: ?>
                                <div class="chem-mini-list">
                                    <?php foreach ($solicitudes_recientes as $sol): 
                                        $estado_key  = $sol['estado'];
                                        $estado_class = 'chem-mini-badge--' . preg_replace('/[^a-z_]/', '', $estado_key);
                                    ?>
                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" class="chem-mini-item">
                                        <div class="chem-mini-item__row">
                                            <span class="chem-mini-item__folio"><?php echo htmlspecialchars($sol['folio']); ?></span>
                                            <span class="chem-mini-item__date"><?php echo date('d/m/Y', strtotime($sol['fecha_creacion'])); ?></span>
                                        </div>
                                        <p class="chem-mini-item__desc"><?php echo htmlspecialchars(mb_substr($sol['descripcion'], 0, 90)); ?><?php echo mb_strlen($sol['descripcion']) > 90 ? '…' : ''; ?></p>
                                        <div class="chem-mini-item__meta">
                                            <span class="chem-mini-badge <?php echo $estado_class; ?>">
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