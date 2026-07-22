<?php
/**
 * Ver todas las solicitudes (solo TI)
 * Panel completo de gestión con filtros y acciones
 * 
 * ACTUALIZADO:
 * - Muestra nombre_solicitante de la tabla (editable por usuario)
 * - Paginación de 15 registros por página (server-side)
 * - Filtro de búsqueda de texto libre
 * - Rediseño con temática tecnológica (estilo dashboard TI/Sistemas)
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que sea usuario de TI
if (!es_usuario_ti()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

// Parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? limpiar_dato($_GET['estado']) : '';
$filtro_prioridad = isset($_GET['prioridad']) ? limpiar_dato($_GET['prioridad']) : '';
$filtro_departamento = isset($_GET['departamento']) ? limpiar_dato($_GET['departamento']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? limpiar_dato($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? limpiar_dato($_GET['fecha_hasta']) : '';
$filtro_busqueda = isset($_GET['buscar']) ? limpiar_dato($_GET['buscar']) : '';

// ====================================
// PAGINACIÓN
// ====================================
$por_pagina = 15;
$pagina_actual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($pagina_actual < 1) {
    $pagina_actual = 1;
}

// Obtener solicitudes
try {
    $pdo = conectarDB();

    // ------------------------------------
    // Construir el WHERE una sola vez para
    // reutilizarlo en el COUNT y en el SELECT
    // ------------------------------------
    $where = " WHERE 1=1";
    $params = [];

    if (!empty($filtro_estado)) {
        $where .= " AND s.estado = ?";
        $params[] = $filtro_estado;
    }

    if (!empty($filtro_prioridad)) {
        $where .= " AND s.prioridad = ?";
        $params[] = $filtro_prioridad;
    }

    if (!empty($filtro_departamento)) {
        $where .= " AND u.departamento = ?";
        $params[] = $filtro_departamento;
    }

    if (!empty($filtro_fecha_desde)) {
        $where .= " AND DATE(s.fecha_creacion) >= ?";
        $params[] = $filtro_fecha_desde;
    }

    if (!empty($filtro_fecha_hasta)) {
        $where .= " AND DATE(s.fecha_creacion) <= ?";
        $params[] = $filtro_fecha_hasta;
    }

    // Búsqueda de texto libre: folio, solicitante, descripción, departamento y tipo
    if ($filtro_busqueda !== '') {
        $where .= " AND (
                        s.folio LIKE ?
                        OR s.descripcion LIKE ?
                        OR COALESCE(s.nombre_solicitante, u.nombre_completo) LIKE ?
                        OR u.departamento LIKE ?
                        OR s.tipo_soporte LIKE ?
                    )";
        $like = '%' . $filtro_busqueda . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // ------------------------------------
    // 1) Contar el total de resultados (con los mismos filtros)
    // ------------------------------------
    $sql_count = "SELECT COUNT(*) as total
                  FROM solicitudes_atencion s
                  INNER JOIN usuarios u ON s.usuario_id = u.id"
                  . $where;

    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params);
    $total_resultados = (int) $stmt->fetch()['total'];

    // Calcular total de páginas y ajustar la página actual si se pasó del rango
    $total_paginas = $total_resultados > 0 ? (int) ceil($total_resultados / $por_pagina) : 1;
    if ($pagina_actual > $total_paginas) {
        $pagina_actual = $total_paginas;
    }
    $offset = ($pagina_actual - 1) * $por_pagina;

    // ------------------------------------
    // 2) Obtener solo los registros de la página actual
    // ------------------------------------
    // ACTUALIZADO: Usa COALESCE para mostrar nombre_solicitante o nombre de usuario
    $sql = "SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre, 
                   u.departamento
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id"
            . $where;

    $sql .= " ORDER BY 
              CASE s.estado 
                  WHEN 'pendiente' THEN 1 
                  WHEN 'en_proceso' THEN 2 
                  ELSE 3 
              END,
              CASE s.prioridad
                  WHEN 'critica' THEN 1
                  WHEN 'alta' THEN 2
                  WHEN 'media' THEN 3
                  WHEN 'baja' THEN 4
              END,
              s.fecha_creacion DESC";

    // LIMIT/OFFSET se interpolan como enteros ya validados (no admiten placeholders en algunos drivers)
    $sql .= " LIMIT " . (int) $por_pagina . " OFFSET " . (int) $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();

    // Contadores globales (no dependen de los filtros)
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total
        FROM solicitudes_atencion
    ");
    $contadores = $stmt->fetch();

    // Obtener lista de departamentos para el filtro
    $stmt_deptos = $pdo->query("SELECT codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre ASC");
    $departamentos = $stmt_deptos->fetchAll();

} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar solicitudes: ' . $e->getMessage());
    $solicitudes = [];
    $contadores = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0];
    $departamentos = [];
    $total_resultados = 0;
    $total_paginas = 1;
    $pagina_actual = 1;
    $offset = 0;
}

/**
 * Construye la URL de paginación conservando los filtros activos
 */
function url_pagina($num_pagina) {
    $params = $_GET;
    $params['pagina'] = $num_pagina;
    return '?' . http_build_query($params);
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

// ¿Hay filtros activos? (incluye la búsqueda)
$hay_filtros = !empty($filtro_estado)
            || !empty($filtro_prioridad)
            || !empty($filtro_departamento)
            || !empty($filtro_fecha_desde)
            || !empty($filtro_fecha_hasta)
            || $filtro_busqueda !== '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas las Solicitudes - TI</title>
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
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .sis-hero__title { margin: 0; font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
        .sis-hero__subtitle { margin: 0.3rem 0 0; font-size: 0.9rem; opacity: 0.92; }
        .sis-hero__right { position: relative; z-index: 1; }
        .sis-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.32);
            transition: background .2s ease, transform .2s ease;
            white-space: nowrap;
        }
        .sis-back-btn:hover { background: rgba(255, 255, 255, 0.3); color: #fff; transform: translateY(-2px); }

        /* ---------- Tarjetas de estadísticas ---------- */
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
        .sis-stat::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }
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
            color: var(--sis-text-muted);
        }
        .sis-stat--amber  { --accent:#f59e0b; --accent-soft:rgba(245,158,11,0.12); }
        .sis-stat--cyan   { --accent:#06b6d4; --accent-soft:rgba(6,182,212,0.12); }
        .sis-stat--green  { --accent:#10b981; --accent-soft:rgba(16,185,129,0.12); }
        .sis-stat--indigo { --accent:#6366f1; --accent-soft:rgba(99,102,241,0.12); }

        /* ---------- Panel ---------- */
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
            flex-wrap: wrap;
        }
        .sis-panel__title {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .sis-panel__body { padding: 1.2rem; }
        .sis-panel__body--flush { padding: 0; }
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

        /* ---------- Filtros ---------- */
        .sis-filtros-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--sis-text-muted);
            margin-bottom: 0.35rem;
        }
        .sis-dash .form-select,
        .sis-dash .form-control {
            background-color: var(--sis-input-bg);
            border: 1px solid var(--sis-border);
            color: var(--sis-text);
            border-radius: 10px;
            font-size: 0.88rem;
            padding: 0.5rem 0.75rem;
        }
        .sis-dash .form-select:focus,
        .sis-dash .form-control:focus {
            background-color: var(--sis-input-bg);
            color: var(--sis-text);
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.18);
        }
        body[data-theme="dark"] .sis-dash .form-control::placeholder { color: #64748b; }
        /* El buscador ocupa una fila completa arriba de los demás filtros */
        .sis-search {
            position: relative;
            margin-bottom: 1rem;
        }
        .sis-search .form-control {
            padding-left: 2.5rem;
            padding-top: 0.62rem;
            padding-bottom: 0.62rem;
        }
        .sis-search__icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--sis-text-muted);
            font-size: 0.95rem;
            pointer-events: none;
        }
        .sis-btn-clear,
        .sis-btn-search {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            width: 100%;
            padding: 0.5rem 0.9rem;
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--sis-border);
            cursor: pointer;
            transition: filter .2s ease, transform .2s ease;
        }
        .sis-btn-search {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
        }
        .sis-btn-clear {
            color: var(--sis-text-muted);
            background: var(--sis-track);
        }
        .sis-btn-search:hover, .sis-btn-clear:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .sis-btn-clear:hover { color: var(--sis-text); }

        /* ---------- Tabla ---------- */
        .sis-table-wrap { overflow-x: auto; }
        .sis-table { width: 100%; border-collapse: collapse; margin: 0; }
        .sis-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sis-text-muted);
            background: var(--sis-track);
            border-bottom: 1px solid var(--sis-border);
            white-space: nowrap;
        }
        .sis-table tbody td {
            padding: 0.85rem 1rem;
            font-size: 0.88rem;
            color: var(--sis-text);
            border-bottom: 1px solid var(--sis-border);
            vertical-align: middle;
        }
        .sis-table tbody tr:last-child td { border-bottom: none; }
        .sis-table tbody tr { transition: background .15s ease; }
        .sis-table tbody tr:hover { background: var(--sis-row-hover); }
        .sis-table__folio {
            font-family: var(--sis-mono);
            font-weight: 600;
            font-size: 0.86rem;
            color: #2563eb;
            text-decoration: none;
        }
        body[data-theme="dark"] .sis-table__folio { color: #67e8f9; }
        .sis-table__folio:hover { text-decoration: underline; }
        .sis-muted { color: var(--sis-text-muted); font-size: 0.82rem; }

        .sis-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.26rem 0.68rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
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
        .sis-chip--tipo       { background: var(--sis-track); color: var(--sis-text-muted); }
        body[data-theme="dark"] .sis-chip--critica    { color: #fca5a5; }
        body[data-theme="dark"] .sis-chip--alta       { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--media      { color: #67e8f9; }
        body[data-theme="dark"] .sis-chip--baja       { color: #cbd5e1; }
        body[data-theme="dark"] .sis-chip--pendiente  { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--en_proceso { color: #93c5fd; }
        body[data-theme="dark"] .sis-chip--finalizada { color: #6ee7b7; }
        body[data-theme="dark"] .sis-chip--cancelada  { color: #cbd5e1; }

        /* Botones de acción de la tabla */
        .sis-acciones { display: inline-flex; gap: 0.35rem; }
        .sis-btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            text-decoration: none;
            border: 1px solid;
            transition: transform .18s ease, filter .18s ease;
        }
        .sis-btn-icon:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .sis-btn-icon--ver {
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.4);
            background: rgba(37, 99, 235, 0.1);
        }
        .sis-btn-icon--gestionar {
            color: #059669;
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.1);
        }
        body[data-theme="dark"] .sis-btn-icon--ver { color: #93c5fd; }
        body[data-theme="dark"] .sis-btn-icon--gestionar { color: #6ee7b7; }

        /* ---------- Paginación ---------- */
        .sis-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem 1.3rem;
            border-top: 1px solid var(--sis-border);
        }
        .sis-pagination__info {
            font-size: 0.83rem;
            color: var(--sis-text-muted);
        }
        .sis-pagination__nav {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
        }
        .sis-page {
            min-width: 36px;
            height: 36px;
            padding: 0 0.7rem;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--sis-text);
            background: var(--sis-card-bg);
            border: 1px solid var(--sis-border);
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }
        .sis-page:hover {
            background: var(--sis-row-hover);
            border-color: #06b6d4;
            color: var(--sis-text);
            transform: translateY(-1px);
        }
        .sis-page.is-active {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            box-shadow: 0 6px 16px -8px rgba(37, 99, 235, 0.8);
        }
        .sis-page.is-disabled {
            opacity: 0.45;
            pointer-events: none;
        }
        .sis-page__dots {
            min-width: 24px;
            text-align: center;
            color: var(--sis-text-muted);
            font-weight: 600;
        }

        .sis-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--sis-text-muted);
        }
        .sis-empty i { font-size: 2.4rem; display: block; margin-bottom: 0.7rem; opacity: 0.5; }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) {
            .sis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .sis-grid { gap: 0.75rem; }
            .sis-stat { padding: 1rem 0.9rem; border-radius: 14px; }
            .sis-stat__top { margin-bottom: 0.6rem; }
            .sis-stat__icon { width: 40px; height: 40px; font-size: 1.15rem; border-radius: 10px; }
            .sis-stat__num { font-size: 1.7rem; }
            .sis-stat__label { font-size: 0.76rem; }

            .sis-hero { padding: 1.3rem 1.25rem; }
            .sis-hero__title { font-size: 1.3rem; }
            .sis-hero__icon { width: 52px; height: 52px; font-size: 1.5rem; }
            .sis-hero__right { width: 100%; }
            .sis-back-btn { width: 100%; justify-content: center; }

            .sis-pagination { justify-content: center; }
            .sis-pagination__info { width: 100%; text-align: center; }
            .sis-pagination__nav { width: 100%; justify-content: center; }
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
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <div class="sis-dash">

                    <!-- Hero -->
                    <div class="sis-hero">
                        <div class="sis-hero__left">
                            <div class="sis-hero__icon">
                                <i class="bi bi-kanban"></i>
                            </div>
                            <div>
                                <h1 class="sis-hero__title">Gesti&oacute;n de Solicitudes</h1>
                                <p class="sis-hero__subtitle">Panel completo de administraci&oacute;n</p>
                            </div>
                        </div>
                        <div class="sis-hero__right">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas.php" class="sis-back-btn">
                                <i class="bi bi-arrow-left"></i> Volver al Dashboard
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

                    <!-- Filtros -->
                    <div class="sis-panel">
                        <div class="sis-panel__head">
                            <h3 class="sis-panel__title"><i class="bi bi-funnel"></i> Filtros de B&uacute;squeda</h3>
                            <?php if ($hay_filtros): ?>
                            <span class="sis-head-count">
                                <i class="bi bi-check2-circle"></i> Filtros activos
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="sis-panel__body">
                            <form method="GET" action="" id="formFiltros">
                                <!-- Al cambiar cualquier filtro se regresa a la página 1 -->
                                <input type="hidden" name="pagina" value="1">

                                <!-- Búsqueda de texto libre -->
                                <div class="sis-search">
                                    <i class="bi bi-search sis-search__icon"></i>
                                    <input type="text"
                                           name="buscar"
                                           class="form-control"
                                           placeholder="Buscar por folio, solicitante, descripción, departamento o tipo..."
                                           value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                                </div>

                                <div class="row g-3 align-items-end">
                                    <div class="col-lg col-md-4 col-sm-6">
                                        <label class="sis-filtros-label">Estado</label>
                                        <select name="estado" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todos</option>
                                            <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                            <option value="en_proceso" <?php echo $filtro_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                            <option value="finalizada" <?php echo $filtro_estado == 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                            <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                        </select>
                                    </div>
                                    <div class="col-lg col-md-4 col-sm-6">
                                        <label class="sis-filtros-label">Prioridad</label>
                                        <select name="prioridad" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <option value="critica" <?php echo $filtro_prioridad == 'critica' ? 'selected' : ''; ?>>Crítica</option>
                                            <option value="alta" <?php echo $filtro_prioridad == 'alta' ? 'selected' : ''; ?>>Alta</option>
                                            <option value="media" <?php echo $filtro_prioridad == 'media' ? 'selected' : ''; ?>>Media</option>
                                            <option value="baja" <?php echo $filtro_prioridad == 'baja' ? 'selected' : ''; ?>>Baja</option>
                                        </select>
                                    </div>
                                    <div class="col-lg col-md-4 col-sm-6">
                                        <label class="sis-filtros-label">Departamento</label>
                                        <select name="departamento" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todos</option>
                                            <?php foreach ($departamentos as $depto): ?>
                                            <option value="<?php echo htmlspecialchars($depto['codigo']); ?>" <?php echo $filtro_departamento == $depto['codigo'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($depto['nombre']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-lg col-md-4 col-sm-6">
                                        <label class="sis-filtros-label">Fecha Desde</label>
                                        <input type="date" name="fecha_desde" class="form-control" 
                                               value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>"
                                               onchange="this.form.submit()">
                                    </div>
                                    <div class="col-lg col-md-4 col-sm-6">
                                        <label class="sis-filtros-label">Fecha Hasta</label>
                                        <input type="date" name="fecha_hasta" class="form-control" 
                                               value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>"
                                               onchange="this.form.submit()">
                                    </div>
                                    <div class="col-lg-auto col-md-4 col-sm-12">
                                        <button type="submit" class="sis-btn-search">
                                            <i class="bi bi-search"></i> Buscar
                                        </button>
                                    </div>
                                    <?php if ($hay_filtros): ?>
                                    <div class="col-lg-auto col-md-4 col-sm-12">
                                        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="sis-btn-clear" title="Limpiar filtros">
                                            <i class="bi bi-x-circle"></i> Limpiar
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de Solicitudes -->
                    <div class="sis-panel">
                        <div class="sis-panel__head">
                            <h3 class="sis-panel__title"><i class="bi bi-list-ul"></i> Listado Completo</h3>
                            <span class="sis-head-count">
                                <i class="bi bi-hash"></i>
                                <?php echo $total_resultados; ?> resultado(s)
                            </span>
                        </div>
                        <div class="sis-panel__body sis-panel__body--flush">
                            <?php if (empty($solicitudes)): ?>
                            <div class="sis-empty">
                                <i class="bi bi-inbox"></i>
                                No hay solicitudes para mostrar
                            </div>
                            <?php else: ?>
                            <div class="sis-table-wrap">
                                <table class="sis-table">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Solicitante</th>
                                            <th>Departamento</th>
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
                                            <td><?php echo htmlspecialchars($sol['solicitante_nombre']); ?></td>
                                            <td>
                                                <span class="sis-muted"><?php echo htmlspecialchars($sol['departamento']); ?></span>
                                            </td>
                                            <td>
                                                <span class="sis-chip sis-chip--tipo">
                                                    <?php echo htmlspecialchars($sol['tipo_soporte']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $desc = htmlspecialchars($sol['descripcion']);
                                                echo strlen($desc) > 40 ? substr($desc, 0, 40) . '...' : $desc;
                                                ?>
                                            </td>
                                            <td>
                                                <span class="sis-chip sis-chip--<?php echo htmlspecialchars($sol['prioridad']); ?>">
                                                    <?php echo ucfirst($sol['prioridad']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sis-chip sis-chip--<?php echo htmlspecialchars($sol['estado']); ?>">
                                                    <?php echo obtener_texto_estado($sol['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sis-muted"><?php echo formatear_fecha($sol['fecha_creacion']); ?></span>
                                            </td>
                                            <td>
                                                <div class="sis-acciones">
                                                    <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                                       class="sis-btn-icon sis-btn-icon--ver" title="Ver detalle">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($sol['estado'] != 'finalizada' && $sol['estado'] != 'cancelada'): ?>
                                                    <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                                       class="sis-btn-icon sis-btn-icon--gestionar" title="Gestionar">
                                                        <i class="bi bi-gear"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <?php if ($total_paginas > 1): ?>
                            <div class="sis-pagination">
                                <div class="sis-pagination__info">
                                    <?php
                                    $desde = $offset + 1;
                                    $hasta = min($offset + $por_pagina, $total_resultados);
                                    ?>
                                    Mostrando <strong><?php echo $desde; ?>&ndash;<?php echo $hasta; ?></strong>
                                    de <strong><?php echo $total_resultados; ?></strong> solicitudes
                                    &middot; P&aacute;gina <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                                </div>

                                <div class="sis-pagination__nav">
                                    <!-- Anterior -->
                                    <a href="<?php echo $pagina_actual > 1 ? url_pagina($pagina_actual - 1) : '#'; ?>" 
                                       class="sis-page <?php echo $pagina_actual <= 1 ? 'is-disabled' : ''; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>

                                    <?php
                                    // Mostrar máximo 5 números de página alrededor de la actual
                                    $rango = 2;
                                    $inicio = max(1, $pagina_actual - $rango);
                                    $fin = min($total_paginas, $pagina_actual + $rango);

                                    // Primera página + puntos suspensivos
                                    if ($inicio > 1) {
                                        echo '<a href="' . url_pagina(1) . '" class="sis-page">1</a>';
                                        if ($inicio > 2) {
                                            echo '<span class="sis-page__dots">…</span>';
                                        }
                                    }

                                    // Rango de páginas
                                    for ($i = $inicio; $i <= $fin; $i++) {
                                        $activa = ($i == $pagina_actual) ? ' is-active' : '';
                                        echo '<a href="' . url_pagina($i) . '" class="sis-page' . $activa . '">' . $i . '</a>';
                                    }

                                    // Puntos suspensivos + última página
                                    if ($fin < $total_paginas) {
                                        if ($fin < $total_paginas - 1) {
                                            echo '<span class="sis-page__dots">…</span>';
                                        }
                                        echo '<a href="' . url_pagina($total_paginas) . '" class="sis-page">' . $total_paginas . '</a>';
                                    }
                                    ?>

                                    <!-- Siguiente -->
                                    <a href="<?php echo $pagina_actual < $total_paginas ? url_pagina($pagina_actual + 1) : '#'; ?>" 
                                       class="sis-page <?php echo $pagina_actual >= $total_paginas ? 'is-disabled' : ''; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>

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

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

</body>
</html>