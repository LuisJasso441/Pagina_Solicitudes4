<?php
/**
 * Dashboard de Inicio del módulo SEC — v3 (temática de operaciones)
 * dashboard/salidas_envases/inicio_sec.php
 *
 * Panel para los 3 roles: Logística, Almacén y Ventas.
 * Estética: operativa/industrial cálida (no tech).
 *   - Hero con cinta diagonal tipo conveyor.
 *   - Stats como "etiquetas de embarque" (banda superior color).
 *   - Acciones tipo "carpeta operativa" (borde izquierdo, feel expediente).
 *   - Timeline tipo "slip de despacho" con folio.
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/inventario_funciones.php';

// ---- Autenticación ----
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ---- Autorización ----
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$es_almacen   = ($dept === 'almacen_residuos');
$es_logistica = ($dept === 'logistica');
$es_ventas    = ($dept === 'ventas');

$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$dept_label = match ($dept) {
    'almacen_residuos' => 'Almacén de Residuos',
    'logistica'        => 'Logística',
    'ventas'           => 'Ventas',
    default            => ucfirst($dept),
};
$dept_icon = match ($dept) {
    'almacen_residuos' => 'bi-recycle',
    'logistica'        => 'bi-truck',
    'ventas'           => 'bi-briefcase',
    default            => 'bi-person-badge',
};

// ---- Umbral global de stock bajo ----
$UMBRAL_STOCK_BAJO = 10;

// ---- Datos ----
$metricas        = obtener_metricas_inventario($UMBRAL_STOCK_BAJO);
$por_tipo        = obtener_stock_por_tipo();
$top_specs       = obtener_top_especificaciones(10);
$stock_bajo      = obtener_stock_bajo($UMBRAL_STOCK_BAJO);
$movs_recientes  = obtener_movimientos([], 8, 0);

$chart_labels = [];
$chart_data   = [];
foreach ($por_tipo as $t) {
    if ((int) $t['total_especificaciones'] === 0) continue;
    $chart_labels[] = $t['tipo_nombre'];
    $chart_data[]   = (int) $t['total_envases'];
}

$tipos_mov_labels = [
    'entrada'    => ['label' => 'Entrada',    'clase' => 'verde',   'icono' => 'arrow-down-circle', 'signo' => '+'],
    'salida'     => ['label' => 'Salida',     'clase' => 'rojo',    'icono' => 'arrow-up-circle',   'signo' => '−'],
    'ajuste'     => ['label' => 'Ajuste',     'clase' => 'ambar',   'icono' => 'sliders',           'signo' => '±'],
    'devolucion' => ['label' => 'Devolución', 'clase' => 'azul',    'icono' => 'arrow-return-left', 'signo' => '+'],
    'inicial'    => ['label' => 'Inicial',    'clase' => 'tierra',  'icono' => 'flag',              'signo' => '+'],
];

// Fecha en español (fallback si la función global no existe)
if (!function_exists('obtener_fecha_actual_espanol')) {
    function obtener_fecha_actual_espanol() {
        $dias   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $meses  = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dia_semana = $dias[(int) date('w')];
        $dia        = (int) date('j');
        $mes        = $meses[(int) date('n') - 1];
        $anio       = date('Y');
        return ucfirst($dia_semana) . ", $dia de $mes de $anio";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel SEC | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
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
        /* ==========================================================
           Panel SEC — estética operativa (almacén / logística)
           Prefijo: .sec-*
           Paleta cálida: verde bosque + ámbar industrial + tierras
        =========================================================== */
        .sec-dash {
            --sec-verde:        #2f7d5b;
            --sec-verde-osc:    #1e5a3f;
            --sec-verde-soft:   rgba(47, 125, 91, 0.10);
            --sec-ambar:        #d97706;
            --sec-ambar-soft:   rgba(217, 119, 6, 0.12);
            --sec-azul:         #1d4ed8;
            --sec-azul-soft:    rgba(29, 78, 216, 0.12);
            --sec-rojo:         #b91c1c;
            --sec-rojo-soft:    rgba(185, 28, 28, 0.12);
            --sec-tierra:       #92603a;
            --sec-tierra-soft:  rgba(146, 96, 58, 0.12);

            --sec-bg-base:      #f9f6ee;
            --sec-card:         #ffffff;
            --sec-panel:        #ffffff;
            --sec-text:         #1c2b21;
            --sec-text-muted:   #6b7268;
            --sec-border:       #e3ddce;
            --sec-border-soft:  #efe9dc;
            --sec-shadow-sm:    0 2px 6px rgba(30, 90, 63, 0.05);
            --sec-shadow-md:    0 8px 20px -8px rgba(30, 90, 63, 0.14);
            --sec-shadow-lg:    0 18px 32px -14px rgba(30, 90, 63, 0.22);
            --sec-hover-bg:     #f9f6ee;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sec-text);
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        body[data-theme="dark"] .sec-dash {
            --sec-verde-soft:   rgba(74, 173, 130, 0.14);
            --sec-ambar-soft:   rgba(251, 146, 60, 0.14);
            --sec-azul-soft:    rgba(96, 165, 250, 0.14);
            --sec-rojo-soft:    rgba(248, 113, 113, 0.14);
            --sec-tierra-soft:  rgba(180, 130, 90, 0.14);
            --sec-bg-base:      #0d1a13;
            --sec-card:         #14251a;
            --sec-panel:        #14251a;
            --sec-text:         #d8e4dc;
            --sec-text-muted:   #8ca093;
            --sec-border:       #223028;
            --sec-border-soft:  #1a2820;
            --sec-shadow-sm:    0 2px 6px rgba(0, 0, 0, 0.35);
            --sec-shadow-md:    0 8px 20px -8px rgba(0, 0, 0, 0.5);
            --sec-shadow-lg:    0 18px 32px -14px rgba(0, 0, 0, 0.6);
            --sec-hover-bg:     #1a2921;
        }

        /* Hero: cinta transportadora */
        .sec-hero {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            padding: 1.6rem 1.9rem;
            background: linear-gradient(115deg, var(--sec-verde-osc) 0%, var(--sec-verde) 100%);
            color: #fff;
            box-shadow: var(--sec-shadow-lg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .sec-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(
                -45deg,
                rgba(255, 255, 255, 0.055) 0px,
                rgba(255, 255, 255, 0.055) 1px,
                transparent 1px,
                transparent 22px
            );
            pointer-events: none;
        }
        .sec-hero::after {
            content: "";
            position: absolute;
            top: -40px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.28) 0%, transparent 65%);
            pointer-events: none;
        }
        .sec-hero__left {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1.15rem;
        }
        .sec-hero__stamp {
            flex-shrink: 0;
            width: 66px;
            height: 66px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 2px dashed rgba(255, 255, 255, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            transform: rotate(-3deg);
        }
        .sec-hero__title { margin: 0; font-size: 1.6rem; font-weight: 700; line-height: 1.15; }
        .sec-hero__subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.88rem;
            opacity: 0.92;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .sec-hero__subtitle span { display: inline-flex; align-items: center; gap: 0.35rem; }
        .sec-hero__right {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            flex-wrap: wrap;
        }
        .sec-bell {
            position: relative;
            width: 42px;
            height: 42px;
            border: 1.5px solid rgba(255, 255, 255, 0.32);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.10);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: background .2s;
        }
        .sec-bell:hover { background: rgba(255, 255, 255, 0.22); }
        .sec-bell .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--sec-ambar);
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            line-height: 18px;
        }
        .sec-role-plate {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.95rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #fff;
            background: rgba(0, 0, 0, 0.2);
            border-left: 3px solid var(--sec-ambar);
            white-space: nowrap;
            letter-spacing: 0.02em;
        }
        .sec-role-plate i { color: #fbbf24; font-size: 0.95rem; }

        /* Stats: etiquetas de embarque */
        .sec-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .sec-stat {
            --accent: var(--sec-verde);
            --accent-soft: var(--sec-verde-soft);
            position: relative;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            padding: 1.1rem 1.15rem 1rem;
            box-shadow: var(--sec-shadow-sm);
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .sec-stat::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--accent);
            border-radius: 10px 10px 0 0;
        }
        .sec-stat:hover {
            transform: translateY(-2px);
            box-shadow: var(--sec-shadow-md);
            border-color: var(--accent);
        }
        .sec-stat__row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.4rem;
        }
        .sec-stat__icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .sec-stat__label {
            margin: 0;
            font-size: 0.74rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--sec-text-muted);
            text-align: right;
        }
        .sec-stat__num {
            margin: 0.5rem 0 0;
            font-size: 2.05rem;
            font-weight: 700;
            line-height: 1;
            color: var(--sec-text);
            font-variant-numeric: tabular-nums;
        }
        .sec-stat--verde  { --accent: var(--sec-verde); --accent-soft: var(--sec-verde-soft); }
        .sec-stat--azul   { --accent: var(--sec-azul);  --accent-soft: var(--sec-azul-soft); }
        .sec-stat--ambar  { --accent: var(--sec-ambar); --accent-soft: var(--sec-ambar-soft); }
        .sec-stat--tierra { --accent: var(--sec-tierra); --accent-soft: var(--sec-tierra-soft); }
        .sec-stat--rojo   { --accent: var(--sec-rojo);  --accent-soft: var(--sec-rojo-soft); }

        /* Acciones: carpetas operativas */
        .sec-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .sec-action {
            --accent: var(--sec-verde);
            --accent-soft: var(--sec-verde-soft);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-left: 4px solid var(--accent);
            box-shadow: var(--sec-shadow-sm);
            color: var(--sec-text);
            transition: transform .18s, box-shadow .18s, background .18s;
        }
        .sec-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--sec-shadow-md);
            background: var(--sec-hover-bg);
            color: var(--sec-text);
        }
        .sec-action__icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--accent);
            background: var(--accent-soft);
        }
        .sec-action__label { font-size: 0.9rem; font-weight: 600; line-height: 1.15; }
        .sec-action--verde  { --accent: var(--sec-verde);  --accent-soft: var(--sec-verde-soft); }
        .sec-action--azul   { --accent: var(--sec-azul);   --accent-soft: var(--sec-azul-soft); }
        .sec-action--ambar  { --accent: var(--sec-ambar);  --accent-soft: var(--sec-ambar-soft); }
        .sec-action--tierra { --accent: var(--sec-tierra); --accent-soft: var(--sec-tierra-soft); }

        /* Paneles */
        .sec-columns {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 1rem;
            align-items: start;
        }
        .sec-panel {
            background: var(--sec-panel);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            box-shadow: var(--sec-shadow-sm);
            overflow: hidden;
        }
        .sec-panel__head {
            background: var(--sec-card);
            color: var(--sec-text);
            padding: 0.85rem 1.2rem;
            border-bottom: 1px solid var(--sec-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .sec-panel__title {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            color: var(--sec-text);
        }
        .sec-panel__title i { color: var(--sec-verde); }
        .sec-head-link {
            color: var(--sec-verde);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .sec-head-link:hover { color: var(--sec-verde-osc); text-decoration: underline; }
        .sec-head-count {
            font-size: 0.75rem;
            padding: 0.22rem 0.6rem;
            border-radius: 4px;
            background: var(--sec-ambar-soft);
            color: var(--sec-ambar);
            font-weight: 700;
        }
        .sec-panel__body { padding: 1rem 1.2rem; }
        .sec-panel__body--compact { padding: 0; }
        .sec-chart-wrap { position: relative; height: 320px; }

        /* Tabla Top 10 / Stock bajo */
        .sec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .sec-table th, .sec-table td {
            padding: 0.65rem 1.2rem;
            border-bottom: 1px solid var(--sec-border-soft);
            text-align: left;
        }
        .sec-table thead th {
            background: var(--sec-bg-base);
            color: var(--sec-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.06em;
        }
        .sec-table tbody tr:hover td { background: var(--sec-hover-bg); }
        .sec-table tbody tr:last-child td { border-bottom: none; }
        .sec-rank__num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px; height: 26px;
            border-radius: 4px;
            background: var(--sec-border-soft);
            color: var(--sec-text-muted);
            font-weight: 700;
            font-size: 0.78rem;
            font-variant-numeric: tabular-nums;
        }
        .sec-rank__num--gold   { background: #fbbf24; color: #78350f; }
        .sec-rank__num--silver { background: #cbd5e1; color: #334155; }
        .sec-rank__num--bronze { background: #b45309; color: #fff; }
        .sec-rank__spec { color: var(--sec-text); font-weight: 500; }
        .sec-rank__tipo { display: block; font-size: 0.72rem; color: var(--sec-text-muted); margin-top: 2px; }
        .sec-rank__stock {
            text-align: right;
            font-weight: 700;
            color: var(--sec-verde);
            font-variant-numeric: tabular-nums;
        }

        /* Timeline: slip de despacho */
        .sec-slips { display: flex; flex-direction: column; gap: 0.55rem; }
        .sec-slip {
            --accent: var(--sec-verde);
            --accent-soft: var(--sec-verde-soft);
            display: grid;
            grid-template-columns: 78px auto 1fr auto;
            align-items: center;
            gap: 0.85rem;
            padding: 0.7rem 0.95rem;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-left: 3px solid var(--accent);
            border-radius: 6px;
        }
        .sec-slip--verde  { --accent: var(--sec-verde);  --accent-soft: var(--sec-verde-soft); }
        .sec-slip--rojo   { --accent: var(--sec-rojo);   --accent-soft: var(--sec-rojo-soft); }
        .sec-slip--ambar  { --accent: var(--sec-ambar);  --accent-soft: var(--sec-ambar-soft); }
        .sec-slip--azul   { --accent: var(--sec-azul);   --accent-soft: var(--sec-azul-soft); }
        .sec-slip--tierra { --accent: var(--sec-tierra); --accent-soft: var(--sec-tierra-soft); }
        .sec-slip__folio {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--sec-text-muted);
            letter-spacing: 0.03em;
            text-align: center;
            border-right: 1px dashed var(--sec-border);
            padding-right: 0.85rem;
            line-height: 1.3;
        }
        .sec-slip__folio strong {
            display: block;
            color: var(--accent);
            font-size: 0.9rem;
            font-variant-numeric: tabular-nums;
        }
        .sec-slip__icon {
            width: 38px;
            height: 38px;
            border-radius: 6px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .sec-slip__body { min-width: 0; }
        .sec-slip__title { font-size: 0.9rem; color: var(--sec-text); margin: 0; }
        .sec-slip__title strong { font-weight: 600; }
        .sec-slip__meta {
            font-size: 0.75rem;
            color: var(--sec-text-muted);
            margin-top: 2px;
        }
        .sec-slip__meta .qty {
            font-weight: 700;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }
        .sec-slip__stamp {
            font-size: 0.72rem;
            color: var(--sec-text-muted);
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* Empty state */
        .sec-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--sec-text-muted);
            font-size: 0.9rem;
        }
        .sec-empty i {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.45;
        }

        /* Alerta stock bajo */
        .sec-alert-panel {
            border: 1px solid var(--sec-ambar);
            border-left-width: 4px;
        }
        .sec-alert-panel .sec-panel__head {
            background: var(--sec-ambar-soft);
        }
        .sec-alert-panel .sec-panel__title i { color: var(--sec-ambar); }

        /* Toggle tema */
        .sec-theme-toggle {
            position: fixed;
            bottom: 22px;
            right: 22px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 12px 32px -8px rgba(102, 126, 234, 0.55);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 900;
            transition: transform .3s ease;
        }
        .sec-theme-toggle:hover { transform: translateY(-2px) scale(1.03); }
        .sec-theme-toggle.rotating { transform: rotate(360deg); }
        .sec-theme-toggle .icon-sun,
        .sec-theme-toggle .icon-moon { display: none; }
        body[data-theme="light"] .sec-theme-toggle .icon-moon { display: inline; }
        body[data-theme="dark"]  .sec-theme-toggle .icon-sun  { display: inline; }

        /* Responsive */
        @media (max-width: 992px) {
            .sec-columns { grid-template-columns: 1fr; }
            .sec-grid    { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sec-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .sec-hero { padding: 1.3rem 1.2rem; border-radius: 10px; }
            .sec-hero__title { font-size: 1.3rem; }
            .sec-hero__stamp { width: 56px; height: 56px; font-size: 1.65rem; }
            .sec-hero__right { width: 100%; }

            .sec-grid { gap: 0.65rem; }
            .sec-stat { padding: 0.9rem 0.85rem; }
            .sec-stat__num { font-size: 1.7rem; }
            .sec-stat__icon { width: 38px; height: 38px; font-size: 1.15rem; }
            .sec-stat__label { font-size: 0.68rem; }

            .sec-actions { gap: 0.6rem; }
            .sec-action { padding: 0.75rem 0.8rem; }
            .sec-action__label { font-size: 0.82rem; }

            .sec-panel__head { padding: 0.75rem 1rem; }
            .sec-panel__body { padding: 0.85rem 1rem; }
            .sec-table th, .sec-table td { padding: 0.55rem 0.85rem; }
            .sec-chart-wrap { height: 260px; }

            .sec-slip {
                grid-template-columns: 60px auto 1fr;
                gap: 0.6rem;
                padding: 0.6rem 0.75rem;
            }
            .sec-slip__stamp { grid-column: 1 / -1; text-align: left; margin-top: 4px; }
        }
        @media (max-width: 380px) {
            .sec-hero__stamp { display: none; }
            .sec-grid { gap: 0.5rem; }
            .sec-stat { padding: 0.75rem 0.7rem; }
            .sec-stat__num { font-size: 1.55rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php include __DIR__ . '/../../includes/sidebar/sidebar_sec.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="sec-dash">

                    <!-- Hero -->
                    <div class="sec-hero">
                        <div class="sec-hero__left">
                            <div class="sec-hero__stamp">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <div>
                                <h1 class="sec-hero__title">¡Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h1>
                                <div class="sec-hero__subtitle">
                                    <span><i class="bi bi-calendar3"></i> <?php echo obtener_fecha_actual_espanol(); ?></span>
                                    <?php if ($metricas['ultimo_movimiento']): ?>
                                        <span><i class="bi bi-clock-history"></i> Último movimiento: <strong><?php echo date('d/m/Y H:i', strtotime($metricas['ultimo_movimiento'])); ?></strong></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="sec-hero__right">
                            <button class="sec-bell" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
                                <i class="bi bi-megaphone-fill"></i>
                                <span class="badge-count" id="anunciosBadge"></span>
                            </button>
                            <span class="sec-role-plate">
                                <i class="bi <?php echo $dept_icon; ?>"></i>
                                <?php echo htmlspecialchars($dept_label); ?>
                            </span>
                        </div>
                    </div>

                    <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

                    <!-- Stats -->
                    <div class="sec-grid">
                        <div class="sec-stat sec-stat--verde">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-boxes"></i></div>
                                <p class="sec-stat__label">Total<br>envases</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo number_format((int) $metricas['total_envases']); ?></h2>
                        </div>

                        <div class="sec-stat sec-stat--azul">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-tags"></i></div>
                                <p class="sec-stat__label">Especifica-<br>ciones</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo (int) $metricas['total_especificaciones']; ?></h2>
                        </div>

                        <div class="sec-stat sec-stat--tierra">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-check-circle"></i></div>
                                <p class="sec-stat__label">Con<br>stock</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo (int) $metricas['con_stock']; ?></h2>
                        </div>

                        <div class="sec-stat sec-stat--<?php echo (int) $metricas['stock_bajo'] > 0 ? 'rojo' : 'ambar'; ?>">
                            <div class="sec-stat__row">
                                <div class="sec-stat__icon"><i class="bi bi-exclamation-triangle"></i></div>
                                <p class="sec-stat__label">Stock bajo<br>(&lt; <?php echo $UMBRAL_STOCK_BAJO; ?>)</p>
                            </div>
                            <h2 class="sec-stat__num"><?php echo (int) $metricas['stock_bajo']; ?></h2>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-lightning-charge-fill"></i> Acciones rápidas</h3>
                        </div>
                        <div class="sec-panel__body">
                            <div class="sec-actions">
                                <?php if ($es_almacen): ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php" class="sec-action sec-action--verde">
                                        <span class="sec-action__icon"><i class="bi bi-plus-circle"></i></span>
                                        <span class="sec-action__label">Registrar movimiento</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php" class="sec-action sec-action--azul">
                                        <span class="sec-action__icon"><i class="bi bi-boxes"></i></span>
                                        <span class="sec-action__label">Ver inventario</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php" class="sec-action sec-action--ambar">
                                        <span class="sec-action__icon"><i class="bi bi-clock-history"></i></span>
                                        <span class="sec-action__label">Movimientos</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/tipos_envase.php" class="sec-action sec-action--tierra">
                                        <span class="sec-action__icon"><i class="bi bi-box2"></i></span>
                                        <span class="sec-action__label">Tipos de envase</span>
                                    </a>
                                <?php elseif ($es_logistica): ?>
                                    <?php if (puede_crear_sec()): ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/nueva_sec.php" class="sec-action sec-action--verde">
                                        <span class="sec-action__icon"><i class="bi bi-plus-circle"></i></span>
                                        <span class="sec-action__label">Nueva SEC</span>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/salidas_envases.php" class="sec-action sec-action--azul">
                                        <span class="sec-action__icon"><i class="bi bi-box-arrow-right"></i></span>
                                        <span class="sec-action__label">Salidas de envases</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/vueltas/vueltas.php" class="sec-action sec-action--ambar">
                                        <span class="sec-action__icon"><i class="bi bi-arrow-repeat"></i></span>
                                        <span class="sec-action__label">Vueltas</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades/unidades_transporte.php" class="sec-action sec-action--tierra">
                                        <span class="sec-action__icon"><i class="bi bi-truck"></i></span>
                                        <span class="sec-action__label">Unidades</span>
                                    </a>
                                <?php else: ?>
                                    <?php if (puede_crear_sec()): ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/nueva_sec.php" class="sec-action sec-action--verde">
                                        <span class="sec-action__icon"><i class="bi bi-plus-circle"></i></span>
                                        <span class="sec-action__label">Nueva SEC</span>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/sec/salidas_envases.php" class="sec-action sec-action--azul">
                                        <span class="sec-action__icon"><i class="bi bi-box-arrow-right"></i></span>
                                        <span class="sec-action__label">Salidas de envases</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/vueltas/vueltas.php" class="sec-action sec-action--ambar">
                                        <span class="sec-action__icon"><i class="bi bi-arrow-repeat"></i></span>
                                        <span class="sec-action__label">Vueltas</span>
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php" class="sec-action sec-action--tierra">
                                        <span class="sec-action__icon"><i class="bi bi-boxes"></i></span>
                                        <span class="sec-action__label">Ver inventario</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfica + Top 10 -->
                    <div class="sec-columns">
                        <div class="sec-panel">
                            <div class="sec-panel__head">
                                <h3 class="sec-panel__title"><i class="bi bi-bar-chart"></i> Envases por tipo</h3>
                            </div>
                            <div class="sec-panel__body">
                                <?php if (empty($chart_labels) || array_sum($chart_data) === 0): ?>
                                    <div class="sec-empty">
                                        <i class="bi bi-bar-chart"></i>
                                        Aún no hay envases en inventario.
                                    </div>
                                <?php else: ?>
                                    <div class="sec-chart-wrap">
                                        <canvas id="chartTipos"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sec-panel">
                            <div class="sec-panel__head">
                                <h3 class="sec-panel__title"><i class="bi bi-trophy"></i> Top 10 con más stock</h3>
                            </div>
                            <div class="sec-panel__body sec-panel__body--compact">
                                <?php if (empty($top_specs)): ?>
                                    <div class="sec-empty">
                                        <i class="bi bi-trophy"></i>
                                        Sin especificaciones con stock.
                                    </div>
                                <?php else: ?>
                                    <table class="sec-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 55px;">#</th>
                                                <th>Especificación</th>
                                                <th style="text-align: right;">Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_specs as $i => $s):
                                                $pos = $i + 1;
                                                $rk = $pos === 1 ? 'gold' : ($pos === 2 ? 'silver' : ($pos === 3 ? 'bronze' : ''));
                                            ?>
                                            <tr>
                                                <td><span class="sec-rank__num <?php echo $rk ? 'sec-rank__num--' . $rk : ''; ?>"><?php echo $pos; ?></span></td>
                                                <td>
                                                    <span class="sec-rank__spec"><?php echo htmlspecialchars($s['especificacion_nombre']); ?></span>
                                                    <span class="sec-rank__tipo"><?php echo htmlspecialchars($s['tipo_nombre']); ?></span>
                                                </td>
                                                <td class="sec-rank__stock"><?php echo number_format((int) $s['cantidad_actual']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Alerta stock bajo -->
                    <?php if (!empty($stock_bajo)): ?>
                    <div class="sec-panel sec-alert-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                Alerta de stock bajo
                                <span class="sec-head-count"><?php echo count($stock_bajo); ?></span>
                            </h3>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem;">
                                Stock inferior a <?php echo $UMBRAL_STOCK_BAJO; ?> unidades
                            </small>
                        </div>
                        <div class="sec-panel__body sec-panel__body--compact">
                            <table class="sec-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Especificación</th>
                                        <th style="text-align: right; width: 110px;">Stock</th>
                                        <th style="width: 160px;">Actualización</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_bajo as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['tipo_nombre']); ?></td>
                                        <td class="sec-rank__spec"><?php echo htmlspecialchars($s['especificacion_nombre']); ?></td>
                                        <td style="text-align: right; color: var(--sec-ambar); font-weight: 700; font-variant-numeric: tabular-nums;">
                                            <?php echo number_format((int) $s['cantidad_actual']); ?>
                                        </td>
                                        <td style="color: var(--sec-text-muted); font-size: 0.78rem;">
                                            <?php echo $s['actualizado_en'] ? date('d/m/Y H:i', strtotime($s['actualizado_en'])) : '—'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Movimientos recientes -->
                    <div class="sec-panel">
                        <div class="sec-panel__head">
                            <h3 class="sec-panel__title"><i class="bi bi-clock-history"></i> Movimientos recientes</h3>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php" class="sec-head-link">
                                Ver historial <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="sec-panel__body">
                            <?php if (empty($movs_recientes)): ?>
                                <div class="sec-empty">
                                    <i class="bi bi-clock-history"></i>
                                    Sin movimientos registrados aún.
                                </div>
                            <?php else: ?>
                                <div class="sec-slips">
                                    <?php foreach ($movs_recientes as $m):
                                        $mLabel = $tipos_mov_labels[$m['tipo_movimiento']] ?? [
                                            'label' => ucfirst($m['tipo_movimiento']),
                                            'clase' => 'verde',
                                            'icono' => 'question-circle',
                                            'signo' => '',
                                        ];
                                        if ($m['tipo_movimiento'] === 'ajuste') {
                                            $delta = (int) $m['cantidad_resultante'] - (int) $m['cantidad_anterior'];
                                            $signo = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
                                            $cant_display = $signo . number_format(abs($delta));
                                        } else {
                                            $cant_display = $mLabel['signo'] . number_format((int) $m['cantidad_movimiento']);
                                        }
                                    ?>
                                    <div class="sec-slip sec-slip--<?php echo $mLabel['clase']; ?>">
                                        <div class="sec-slip__folio">
                                            <?php echo strtoupper($mLabel['label']); ?>
                                            <strong>#<?php echo str_pad((int)$m['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        </div>
                                        <div class="sec-slip__icon">
                                            <i class="bi bi-<?php echo $mLabel['icono']; ?>"></i>
                                        </div>
                                        <div class="sec-slip__body">
                                            <p class="sec-slip__title">
                                                <strong><?php echo htmlspecialchars($m['tipo_nombre']); ?></strong>
                                                · <?php echo htmlspecialchars($m['especificacion_nombre']); ?>
                                            </p>
                                            <div class="sec-slip__meta">
                                                <span class="qty"><?php echo $cant_display; ?></span>
                                                (de <?php echo number_format((int) $m['cantidad_anterior']); ?>
                                                a <?php echo number_format((int) $m['cantidad_resultante']); ?>)
                                                <?php if (!empty($m['motivo'])): ?>
                                                    · <?php echo htmlspecialchars($m['motivo']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="sec-slip__stamp">
                                            <?php echo date('d/m/Y H:i', strtotime($m['creado_en'])); ?><br>
                                            <?php echo htmlspecialchars($m['usuario_nombre'] ?? '—'); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /.sec-dash -->
            </div>
        </main>
    </div>

    <button class="sec-theme-toggle" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        bodyElement.setAttribute('data-theme', currentTheme);

        themeToggle.addEventListener('click', () => {
            const t = bodyElement.getAttribute('data-theme');
            const newTheme = t === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => themeToggle.classList.remove('rotating'), 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            if (window.chartTiposInstance) {
                window.chartTiposInstance.destroy();
                setTimeout(renderChartTipos, 100);
            }
        });
    </script>

    <?php if (!empty($chart_labels) && array_sum($chart_data) > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    function renderChartTipos() {
        const isDark = document.body.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#d8e4dc' : '#1c2b21';
        const gridColor = isDark ? '#223028' : '#e3ddce';

        // Paleta cálida operativa
        const palette = ['#2f7d5b', '#92603a', '#d97706', '#1d4ed8', '#65a30d', '#0891b2', '#b45309', '#4d7c0f'];

        const labels = <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>;
        const data   = <?php echo json_encode($chart_data); ?>;
        const colors = labels.map((_, i) => palette[i % palette.length]);

        const ctx = document.getElementById('chartTipos').getContext('2d');
        window.chartTiposInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Envases',
                    data: data,
                    backgroundColor: colors,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: (c) => ' ' + c.parsed.x.toLocaleString() + ' envases' }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { color: textColor, precision: 0 },
                        grid:  { color: gridColor }
                    },
                    y: {
                        ticks: { color: textColor },
                        grid:  { display: false }
                    }
                }
            }
        });
    }
    renderChartTipos();
    </script>
    <?php endif; ?>

    <?php
    $modal_anuncios = __DIR__ . '/../../includes/anuncios/modal_anuncios.php';
    if (file_exists($modal_anuncios)) include $modal_anuncios;
    ?>

</body>
</html>