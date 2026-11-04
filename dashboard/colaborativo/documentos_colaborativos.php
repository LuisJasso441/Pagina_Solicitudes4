<?php
/**
 * Página principal de Documentos Colaborativos
 * Vista según el departamento del usuario
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_colaborativos.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_comentarios.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar que el usuario tenga acceso (Normatividad, Ventas, Laboratorio o Dirección)
$departamentos_permitidos = ['normatividad', 'ventas', 'laboratorio', 'direccion', 'dirección', 'ptar'];
if (!in_array($dept_lower, $departamentos_permitidos)) {
    header('Location: ' . URL_BASE . 'dashboard/departamento.php');
    exit;
}

// Determinar vista y permisos
$puede_crear = in_array($dept_lower, ['normatividad', 'ventas', 'direccion', 'dirección', 'ptar']);
$es_laboratorio = $dept_lower == 'laboratorio';
$es_direccion = in_array($dept_lower, ['direccion', 'dirección']);

// Obtener filtros
$filtro_ubicacion = $_GET['ubicacion'] ?? 'local';
$filtro_estado = $_GET['estado'] ?? '';
// Cliente ahora soporta selección múltiple (array)
$filtro_cliente = $_GET['cliente'] ?? [];
if (is_string($filtro_cliente)) {
    $filtro_cliente = !empty($filtro_cliente) ? [$filtro_cliente] : [];
}
$filtro_departamento = $_GET['departamento'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir filtros
$filtros = ['ubicacion' => $filtro_ubicacion];

if (!empty($filtro_estado)) {
    $filtros['estado'] = $filtro_estado;
}

if (!empty($filtro_cliente)) {
    $filtros['cliente'] = $filtro_cliente; // Ahora es un array
}

if (!empty($filtro_departamento) && $filtro_ubicacion == 'global') {
    $filtros['departamento'] = $filtro_departamento;
}

if (!empty($filtro_fecha_desde)) {
    $filtros['fecha_desde'] = $filtro_fecha_desde . ' 00:00:00';
}

if (!empty($filtro_fecha_hasta)) {
    $filtros['fecha_hasta'] = $filtro_fecha_hasta . ' 23:59:59';
}

// Si es base local, mostrar solo del departamento del usuario
// Excepción: Laboratorio y Dirección ven TODAS las solicitudes
if ($filtro_ubicacion == 'local' && !$es_laboratorio && !$es_direccion) {
    $filtros['departamento'] = $departamento;
}

// Obtener documentos
$documentos = listar_documentos($filtros, $usuario_id, $departamento);

// ========================================
// OBTENER CONTEOS PARA STAT-CARDS
// ========================================
try {
    $pdo = conectarDB();
    
    if ($es_laboratorio) {
        // Laboratorio: Solicitudes pendientes (enviadas a laboratorio, no completadas)
        $sql_pendientes = "SELECT COUNT(*) FROM documentos_colaborativos 
                          WHERE estado IN ('enviado', 'en_seguimiento') 
                          AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_pendientes);
        $count_pendientes = $stmt->fetchColumn();
        
        // Laboratorio: Solicitudes finalizadas (todas, de todos los departamentos)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'";
        $stmt = $pdo->query($sql_finalizadas);
        $count_finalizadas_global = $stmt->fetchColumn();
        
        // ⭐ Laboratorio: Solicitudes completadas en el MES ACTUAL
        $sql_completadas_mes = "SELECT COUNT(*) FROM documentos_colaborativos 
                               WHERE estado = 'completado'
                               AND MONTH(fecha_completado) = MONTH(CURRENT_DATE())
                               AND YEAR(fecha_completado) = YEAR(CURRENT_DATE())";
        $stmt = $pdo->query($sql_completadas_mes);
        $count_completadas_mes = $stmt->fetchColumn();
        
    } elseif ($es_direccion) {
        // ⭐ Dirección: Ve TODAS las solicitudes de todos los departamentos
        
        // Solicitudes pendientes (todas, enviadas pero no completadas)
        $sql_pendientes = "SELECT COUNT(*) FROM documentos_colaborativos 
                          WHERE estado IN ('enviado', 'en_seguimiento') 
                          AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_pendientes);
        $count_pendientes_direccion = $stmt->fetchColumn();
        
        // Solicitudes en seguimiento (todas)
        $sql_seguimiento = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'en_seguimiento' 
                           AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_seguimiento);
        $count_seguimiento_direccion = $stmt->fetchColumn();
        
        // Solicitudes finalizadas (todas, de todos los departamentos)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'";
        $stmt = $pdo->query($sql_finalizadas);
        $count_finalizadas_global = $stmt->fetchColumn();
        
    } else {
        // Ventas/Normatividad: Solicitudes enviadas (del departamento del usuario)
        $sql_enviadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                        WHERE estado = 'enviado' 
                        AND ubicacion = 'local'
                        AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_enviadas);
        $stmt->execute([':departamento' => $departamento]);
        $count_enviadas = $stmt->fetchColumn();
        
        // Ventas/Normatividad: Solicitudes en seguimiento (del departamento del usuario)
        $sql_seguimiento = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'en_seguimiento' 
                           AND ubicacion = 'local'
                           AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_seguimiento);
        $stmt->execute([':departamento' => $departamento]);
        $count_seguimiento = $stmt->fetchColumn();
        
        // Ventas/Normatividad: Solicitudes finalizadas (SOLO del departamento del usuario)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'
                           AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_finalizadas);
        $stmt->execute([':departamento' => $departamento]);
        $count_finalizadas = $stmt->fetchColumn();
    }
    
    // ⭐ Prioridad alta abiertas (solo Ventas/Normatividad)
    $count_alta_abiertas = 0;
    if (!$es_laboratorio && !$es_direccion) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos_colaborativos
                               WHERE prioridad = 'alta'
                               AND estado <> 'completado'
                               AND ubicacion = 'local'
                               AND departamento_creador = :departamento");
        $stmt->execute([':departamento' => $departamento]);
        $count_alta_abiertas = (int) $stmt->fetchColumn();
    }

    // ⭐ Conteos para las pestañas Base Local / Base Global
    if ($es_laboratorio || $es_direccion) {
        $count_tab_local = (int) $pdo->query("SELECT COUNT(*) FROM documentos_colaborativos WHERE ubicacion = 'local'")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos_colaborativos
                               WHERE ubicacion = 'local' AND departamento_creador = :departamento");
        $stmt->execute([':departamento' => $departamento]);
        $count_tab_local = (int) $stmt->fetchColumn();
    }
    $count_tab_global = (int) $pdo->query("SELECT COUNT(*) FROM documentos_colaborativos WHERE ubicacion = 'global'")->fetchColumn();

    // Obtener lista de clientes únicos para el filtro
    $sql_clientes = "SELECT DISTINCT nombre_cliente FROM documentos_colaborativos 
                    WHERE nombre_cliente IS NOT NULL AND nombre_cliente != '' 
                    ORDER BY nombre_cliente";
    $stmt = $pdo->query($sql_clientes);
    $clientes_lista = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Error al obtener conteos: " . $e->getMessage());
    $count_enviadas = 0;
    $count_seguimiento = 0;
    $count_finalizadas = 0;
    $count_pendientes = 0;
    $count_finalizadas_global = 0;
    $count_completadas_mes = 0;
    $count_pendientes_direccion = 0;
    $count_seguimiento_direccion = 0;
    $count_alta_abiertas = 0;
    $count_tab_local = 0;
    $count_tab_global = 0;
    $clientes_lista = [];
}
// ========================================
// DATOS DE PRESENTACIÓN (solo interfaz)
// ========================================

// URL con los filtros actuales, cambiando o quitando claves
$doc_url = function (array $cambios = [], array $quitar = []): string {
    $q = $_GET;
    foreach ($quitar as $clave) {
        unset($q[$clave]);
    }
    return '?' . http_build_query(array_merge($q, $cambios));
};

// Indicador que filtra por estado (un segundo clic quita el filtro)
$doc_ind_estado = function (string $estado) use ($filtro_estado, $doc_url): array {
    $activo = ($filtro_estado === $estado);
    return [
        'href'   => $activo ? $doc_url([], ['estado']) : $doc_url(['estado' => $estado]),
        'activo' => $activo,
    ];
};

$estados_ui = [
    'borrador'       => ['texto' => 'Borrador',       'clase' => 'borrador'],
    'enviado'        => ['texto' => 'Enviado',        'clase' => 'enviado'],
    'en_seguimiento' => ['texto' => 'En seguimiento', 'clase' => 'seguimiento'],
    'completado'     => ['texto' => 'Completado',     'clase' => 'completado'],
];
$servicios_ui = [
    'tratamiento_agua'    => ['texto' => 'Tratamiento de agua',                     'icono' => 'bi-droplet-half'],
    'revision_productos'  => ['texto' => 'Productos químicos y/o residuos',         'icono' => 'bi-eyedropper'],
    'calibracion_equipos' => ['texto' => 'Calibración y/o verificación de equipos', 'icono' => 'bi-speedometer2'],
    'otro'                => ['texto' => 'Otro',                                    'icono' => 'bi-three-dots'],
];
$meses_es = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
             7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

// Subtítulo del encabezado según rol
if ($es_laboratorio) {
    $subtitulo = 'Todas las solicitudes enviadas a Laboratorio';
} elseif ($es_direccion) {
    $subtitulo = 'Todas las solicitudes de los departamentos';
} else {
    $subtitulo = 'Solicitudes de ' . ucfirst($departamento);
}

// Indicadores (franja compacta, clicables cuando filtran)
$indicadores = [];
if ($filtro_ubicacion == 'local') {
    if ($es_laboratorio) {
        $indicadores[] = ['num' => $count_pendientes ?? 0, 'label' => 'Solicitudes pendientes', 'icono' => 'bi-hourglass-split', 'tono' => 'rojo',
                          'href' => $doc_url([], ['estado']), 'activo' => $filtro_estado === ''];
    } elseif ($es_direccion) {
        $f = $doc_ind_estado('en_seguimiento');
        $indicadores[] = ['num' => $count_pendientes_direccion ?? 0, 'label' => 'Pendientes (todas)', 'icono' => 'bi-hourglass-split', 'tono' => 'rojo',
                          'href' => $doc_url([], ['estado']), 'activo' => $filtro_estado === ''];
        $indicadores[] = ['num' => $count_seguimiento_direccion ?? 0, 'label' => 'En seguimiento (todas)', 'icono' => 'bi-eye', 'tono' => 'ambar',
                          'href' => $f['href'], 'activo' => $f['activo']];
    } else {
        $f1 = $doc_ind_estado('enviado');
        $f2 = $doc_ind_estado('en_seguimiento');
        $indicadores[] = ['num' => $count_enviadas ?? 0, 'label' => 'Solicitudes enviadas', 'icono' => 'bi-send', 'tono' => 'azul',
                          'href' => $f1['href'], 'activo' => $f1['activo']];
        $indicadores[] = ['num' => $count_seguimiento ?? 0, 'label' => 'En seguimiento', 'icono' => 'bi-eye', 'tono' => 'ambar',
                          'href' => $f2['href'], 'activo' => $f2['activo']];
        $indicadores[] = ['num' => $count_alta_abiertas, 'label' => 'Prioridad alta abiertas', 'icono' => 'bi-flag-fill', 'tono' => 'rojo',
                          'href' => null, 'activo' => false];
    }
} else {
    $indicadores[] = ['num' => ($es_laboratorio || $es_direccion) ? ($count_finalizadas_global ?? 0) : ($count_finalizadas ?? 0),
                      'label' => 'Solicitudes finalizadas', 'icono' => 'bi-check-circle', 'tono' => 'verde', 'href' => null, 'activo' => false];
    if ($es_laboratorio) {
        $indicadores[] = ['num' => $count_completadas_mes ?? 0, 'label' => 'Completadas en ' . $meses_es[(int) date('n')],
                          'icono' => 'bi-calendar-check', 'tono' => 'cian', 'href' => null, 'activo' => false];
    }
}

// Chips de filtros activos
$chips = [];
if ($filtro_ubicacion == 'local' && $filtro_estado !== '') {
    $chips[] = ['texto' => 'Estado: ' . ($estados_ui[$filtro_estado]['texto'] ?? $filtro_estado), 'href' => $doc_url([], ['estado'])];
}
if ($filtro_ubicacion == 'global' && $filtro_departamento !== '') {
    $chips[] = ['texto' => 'Departamento: ' . $filtro_departamento, 'href' => $doc_url([], ['departamento'])];
}
foreach ($filtro_cliente as $cliente_f) {
    $q = $_GET;
    $q['cliente'] = array_values(array_diff((array) ($q['cliente'] ?? []), [$cliente_f]));
    if (empty($q['cliente'])) {
        unset($q['cliente']);
    }
    $chips[] = ['texto' => 'Cliente: ' . $cliente_f, 'href' => '?' . http_build_query($q)];
}
if ($filtro_fecha_desde !== '') {
    $chips[] = ['texto' => 'Desde ' . date('d/m/Y', strtotime($filtro_fecha_desde)), 'href' => $doc_url([], ['fecha_desde'])];
}
if ($filtro_fecha_hasta !== '') {
    $chips[] = ['texto' => 'Hasta ' . date('d/m/Y', strtotime($filtro_fecha_hasta)), 'href' => $doc_url([], ['fecha_hasta'])];
}
$hay_filtros = !empty($chips);

// Documentos con datos de presentación (comentarios se cuentan una sola vez para ambas vistas)
$documentos_ui = [];
foreach ($documentos as $doc) {
    $servicio = $servicios_ui[$doc['servicio_solicitado']] ?? ['texto' => 'N/A', 'icono' => 'bi-gear'];
    if (($doc['servicio_solicitado'] ?? '') === 'otro' && !empty($doc['servicio_otro_especificar'])) {
        $servicio['texto'] = $doc['servicio_otro_especificar'];
    }
    $doc['_estado']      = $estados_ui[$doc['estado']] ?? ['texto' => ucfirst(str_replace('_', ' ', $doc['estado'])), 'clase' => 'borrador'];
    $doc['_servicio']    = $servicio;
    $doc['_prioridad']   = in_array($doc['prioridad'], ['alta', 'media'], true) ? $doc['prioridad'] : 'baja';
    $doc['_no_visto']    = $es_laboratorio && array_key_exists('marcado_visto', $doc)
                           && !$doc['marcado_visto'] && $doc['estado'] !== 'completado';
    $doc['_comentarios'] = contar_comentarios_documento($doc['id']);
    $documentos_ui[] = $doc;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Servicio a Clientes | VerdenCore</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        /* ==========================================================
           LISTADO DE SOLICITUDES DE SERVICIO (prefijo .sol-*)
           Misma identidad visual que ver_documento.php
        =========================================================== */
        .sol-view {
            --sol-1: #667eea;
            --sol-2: #764ba2;
            --sol-soft: rgba(102, 126, 234, 0.10);
            --sol-text: #1e2235;
            --sol-muted: #6b7084;
            --sol-border: #e6e8f0;
            --sol-card: #ffffff;
            --sol-bg-soft: #f6f7fb;
            --sol-shadow: 0 10px 28px -16px rgba(60, 50, 120, 0.35);
            --sol-shadow-sm: 0 4px 14px -10px rgba(60, 50, 120, 0.25);
            --sol-mono: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sol-text);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* ---------- Encabezado ---------- */
        .sol-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1.3rem 1.5rem;
            border-radius: 16px;
            background: linear-gradient(120deg, var(--sol-1) 0%, var(--sol-2) 100%);
            color: #fff;
            box-shadow: var(--sol-shadow);
        }
        .sol-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1px);
            background-size: 16px 16px;
            mask-image: linear-gradient(110deg, #000 0%, transparent 70%);
            -webkit-mask-image: linear-gradient(110deg, #000 0%, transparent 70%);
            pointer-events: none;
        }
        .sol-hero__left { position: relative; z-index: 1; display: flex; align-items: center; gap: 0.95rem; }
        .sol-hero__icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
        }
        .sol-hero__title { margin: 0; font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
        .sol-hero__sub { margin: 0.2rem 0 0; font-size: 0.85rem; opacity: 0.92; }
        .sol-btn-new {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.6rem 1.1rem;
            border: 0;
            border-radius: 11px;
            background: #fff;
            color: var(--sol-2);
            font-size: 0.88rem;
            font-weight: 600;
            box-shadow: 0 8px 18px -10px rgba(0, 0, 0, 0.45);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .sol-btn-new:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -10px rgba(0, 0, 0, 0.5); }

        /* ---------- Pestañas + selector de vista ---------- */
        .sol-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .sol-tabs,
        .sol-vista-toggle {
            display: inline-flex;
            gap: 0.35rem;
            padding: 0.3rem;
            background: var(--sol-card);
            border: 1px solid var(--sol-border);
            border-radius: 12px;
        }
        .sol-tab,
        .sol-vista-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 1rem;
            border: 0;
            border-radius: 9px;
            background: transparent;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--sol-muted);
            text-decoration: none;
            transition: background .2s ease, color .2s ease;
        }
        .sol-vista-btn { padding: 0.45rem 0.8rem; }
        .sol-tab:hover,
        .sol-vista-btn:hover { color: var(--sol-2); background: var(--sol-soft); }
        .sol-tab.active,
        .sol-vista-btn.active {
            color: #fff;
            background: linear-gradient(120deg, var(--sol-1), var(--sol-2));
            box-shadow: 0 6px 14px -8px rgba(118, 75, 162, 0.7);
        }
        .sol-tab__count {
            min-width: 22px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            background: var(--sol-soft);
            color: var(--sol-2);
        }
        .sol-tab.active .sol-tab__count { background: rgba(255, 255, 255, 0.25); color: #fff; }

        /* ---------- Indicadores ---------- */
        .sol-kpis { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .sol-kpi {
            --acc: #667eea;
            --acc-soft: rgba(102, 126, 234, 0.12);
            position: relative;
            flex: 1 1 200px;
            max-width: 320px;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.85rem 1rem;
            background: var(--sol-card);
            border: 1px solid var(--sol-border);
            border-radius: 14px;
            color: var(--sol-text);
            text-decoration: none;
            box-shadow: var(--sol-shadow-sm);
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .sol-kpi.is-link:hover { transform: translateY(-2px); border-color: var(--acc); color: var(--sol-text); box-shadow: var(--sol-shadow); }
        .sol-kpi.is-active { border-color: var(--acc); box-shadow: 0 0 0 3px var(--acc-soft); }
        .sol-kpi__icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: var(--acc-soft);
            color: var(--acc);
        }
        .sol-kpi__body { display: flex; flex-direction: column; min-width: 0; }
        .sol-kpi__num { font-size: 1.5rem; font-weight: 700; line-height: 1; }
        .sol-kpi__label { margin-top: 0.2rem; font-size: 0.76rem; color: var(--sol-muted); }
        .sol-kpi__hint {
            position: absolute;
            top: 0.6rem;
            right: 0.7rem;
            font-size: 0.75rem;
            color: var(--sol-muted);
            opacity: 0;
            transition: opacity .2s ease;
        }
        .sol-kpi.is-link:hover .sol-kpi__hint,
        .sol-kpi.is-active .sol-kpi__hint { opacity: 1; color: var(--acc); }
        .sol-kpi--rojo  { --acc: #dc2626; --acc-soft: rgba(220, 38, 38, 0.12); }
        .sol-kpi--azul  { --acc: #2563eb; --acc-soft: rgba(37, 99, 235, 0.12); }
        .sol-kpi--ambar { --acc: #d97706; --acc-soft: rgba(217, 119, 6, 0.12); }
        .sol-kpi--verde { --acc: #16a34a; --acc-soft: rgba(22, 163, 74, 0.12); }
        .sol-kpi--cian  { --acc: #0891b2; --acc-soft: rgba(8, 145, 178, 0.12); }

        /* ---------- Filtros ---------- */
        @media (min-width: 992px) {
            .sol-filtros {
                background: var(--sol-card);
                border: 1px solid var(--sol-border);
                border-radius: 14px;
                padding: 1rem 1.1rem;
                box-shadow: var(--sol-shadow-sm);
            }
        }
        .sol-filtros .offcanvas-title { font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .sol-filtros .form-label {
            margin-bottom: 0.3rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--sol-muted);
        }
        .sol-filtros .form-select,
        .sol-filtros .form-control { border-radius: 10px; font-size: 0.85rem; border-color: var(--sol-border); }
        .sol-filtros .form-select:focus,
        .sol-filtros .form-control:focus { border-color: var(--sol-1); box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.18); }

        .sol-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform .2s ease, border-color .2s ease, color .2s ease, filter .2s ease;
        }
        .sol-btn--primary { color: #fff; background: linear-gradient(120deg, var(--sol-1), var(--sol-2)); }
        .sol-btn--primary:hover { color: #fff; filter: brightness(1.06); transform: translateY(-1px); }
        .sol-btn--ghost { color: var(--sol-muted); background: var(--sol-card); border-color: var(--sol-border); }
        .sol-btn--ghost:hover { color: var(--sol-2); border-color: var(--sol-1); }

        /* Multi-select de clientes (se conserva el comportamiento) */
        .multi-select-dropdown .dropdown-toggle-multi {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 2rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        .multi-select-dropdown .dropdown-toggle-multi::after { display: none; }
        .multi-select-menu { min-width: 100%; max-width: 350px; border-radius: 12px; border-color: var(--sol-border); box-shadow: var(--sol-shadow); }
        .multi-select-menu .multi-option { cursor: pointer; border-radius: 6px; }
        .multi-select-menu .multi-option:hover { background-color: var(--sol-soft); }
        .multi-select-menu .form-check-input { cursor: pointer; }
        .multi-select-menu .form-check-input:checked { background-color: var(--sol-1); border-color: var(--sol-1); }

        /* ---------- Resultados + chips de filtros activos ---------- */
        .sol-resultados { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .sol-resultados__info { display: flex; align-items: center; flex-wrap: wrap; gap: 0.45rem; }
        .sol-resultados__count { margin-right: 0.25rem; font-size: 0.85rem; color: var(--sol-muted); }
        .sol-resultados__count strong { color: var(--sol-text); }
        .sol-fchip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.45rem 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--sol-2);
            background: var(--sol-soft);
            border: 1px solid rgba(102, 126, 234, 0.25);
            transition: background .2s ease;
        }
        .sol-fchip:hover { background: rgba(102, 126, 234, 0.2); color: var(--sol-2); }
        .sol-fchip-clear { font-size: 0.75rem; font-weight: 600; color: #dc2626; text-decoration: none; }
        .sol-fchip-clear:hover { text-decoration: underline; color: #b91c1c; }
        .sol-btn-filtros__n {
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: #fff;
            background: var(--sol-2);
        }

        /* ---------- Tarjetas de solicitud ---------- */
        .sol-card {
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            padding: 1rem 1rem 0.9rem 1.15rem;
            background: var(--sol-card);
            border: 1px solid var(--sol-border);
            border-left: 4px solid #cbd5e1;
            border-radius: 14px;
            box-shadow: var(--sol-shadow-sm);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .sol-card:hover { transform: translateY(-3px); box-shadow: var(--sol-shadow); }
        .sol-card--alta  { border-left-color: #dc2626; }
        .sol-card--media { border-left-color: #f59e0b; }
        .sol-card--baja  { border-left-color: #16a34a; }
        .sol-card__top { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
        .sol-card__badges { display: inline-flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; }
        .sol-folio { font-family: var(--sol-mono); font-size: 0.85rem; font-weight: 700; color: var(--sol-2); }
        .sol-card__cliente { margin: 0; font-size: 1rem; font-weight: 600; line-height: 1.3; }
        .sol-card__servicio { margin: 0; display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 500; color: var(--sol-2); }
        .sol-card__desc { margin: 0; font-size: 0.8rem; line-height: 1.45; color: var(--sol-muted); }
        .sol-card__foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.9rem;
            margin-top: auto;
            padding-top: 0.6rem;
            border-top: 1px dashed var(--sol-border);
            font-size: 0.75rem;
            color: var(--sol-muted);
        }
        .sol-card__foot span { display: inline-flex; align-items: center; gap: 0.3rem; }
        .sol-card__ver {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-left: auto;
            padding: 0.3rem 0.75rem;
            border-radius: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--sol-2);
            background: var(--sol-soft);
            transition: background .2s ease, color .2s ease;
        }
        .sol-card:hover .sol-card__ver { color: #fff; background: linear-gradient(120deg, var(--sol-1), var(--sol-2)); }

        /* Chips de estado / prioridad / no visto */
        .sol-estado {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .sol-estado::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .sol-estado--borrador    { background: #f1f2f6; color: #6b7084; }
        .sol-estado--enviado     { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }
        .sol-estado--seguimiento { background: rgba(245, 158, 11, 0.15); color: #b45309; }
        .sol-estado--completado  { background: rgba(22, 163, 74, 0.12); color: #15803d; }
        .sol-novisto {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            background: rgba(220, 38, 38, 0.10);
            color: #b91c1c;
            white-space: nowrap;
        }
        .sol-prio { font-weight: 600; }
        .sol-prio--alta  { color: #dc2626; }
        .sol-prio--media { color: #d97706; }
        .sol-prio--baja  { color: #16a34a; }

        /* Tabla de Sistema de Gestión (FR-CC-12) */
        .sol-sg { margin: 0; font-size: 0.72rem; }
        .sol-sg td { padding: 0.25rem 0.5rem; border-color: var(--sol-border); }
        .sol-sg td:first-child { width: 60%; font-weight: 600; color: var(--sol-muted); background: var(--sol-bg-soft); }
        .sol-sg td:last-child { text-align: center; font-family: var(--sol-mono); }

        /* ---------- Vista tabla ---------- */
        .sol-listado .sol-tabla-wrap { display: none; }
        .sol-listado.is-tabla .sol-grid { display: none; }
        .sol-listado.is-tabla .sol-tabla-wrap { display: block; }
        .sol-tabla-wrap {
            overflow: hidden;
            background: var(--sol-card);
            border: 1px solid var(--sol-border);
            border-radius: 14px;
            box-shadow: var(--sol-shadow-sm);
        }
        .sol-tabla-head { display: flex; justify-content: flex-end; padding: 0.85rem 1rem; }
        .sol-tabla-head .sol-sg { max-width: 340px; }
        .sol-tabla { margin: 0; font-size: 0.82rem; }
        .sol-tabla thead th {
            padding: 0.65rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
            color: var(--sol-muted);
            background: var(--sol-bg-soft);
            border-bottom: 1px solid var(--sol-border);
        }
        .sol-tabla td { padding: 0.6rem 0.75rem; vertical-align: middle; border-color: var(--sol-border); }
        .sol-tabla tbody tr:hover td { background: #f8f7ff; }
        .sol-tabla .sol-card__ver { margin-left: 0; }

        /* ---------- Estado vacío ---------- */
        .sol-empty {
            padding: 2.5rem 1rem;
            text-align: center;
            color: var(--sol-muted);
            background: var(--sol-card);
            border: 1px dashed var(--sol-border);
            border-radius: 16px;
        }
        .sol-empty__icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 0.9rem;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: var(--sol-soft);
            color: var(--sol-2);
        }
        .sol-empty h3 { margin: 0 0 0.3rem; font-size: 1.05rem; font-weight: 600; color: var(--sol-text); }
        .sol-empty p { margin: 0 0 1rem; font-size: 0.85rem; }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .sol-hero { padding: 1.1rem; }
            .sol-hero__title { font-size: 1.1rem; }
            .sol-hero__icon { width: 44px; height: 44px; font-size: 1.3rem; }
            .sol-btn-new { width: 100%; justify-content: center; }
            .sol-toolbar { flex-direction: column; align-items: stretch; }
            .sol-tabs { display: flex; }
            .sol-tab { flex: 1; justify-content: center; padding: 0.45rem 0.5rem; }
            .sol-vista-toggle { align-self: flex-end; }
            .sol-vista-btn span { display: none; }
            .sol-kpi { max-width: none; flex-basis: calc(50% - 0.375rem); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                <div class="sol-view">

                    <!-- Encabezado -->
                    <section class="sol-hero">
                        <div class="sol-hero__left">
                            <div class="sol-hero__icon"><i class="bi bi-file-earmark-text"></i></div>
                            <div>
                                <h1 class="sol-hero__title">Solicitudes de Servicio a Clientes</h1>
                                <p class="sol-hero__sub"><?= htmlspecialchars($subtitulo) ?></p>
                            </div>
                        </div>
                        <?php if ($puede_crear): ?>
                        <button type="button" class="sol-btn-new" data-bs-toggle="modal" data-bs-target="#modalNuevoDocumento">
                            <i class="bi bi-plus-lg"></i> Nueva Solicitud de Servicio
                        </button>
                        <?php endif; ?>
                    </section>

                    <!-- Pestañas + selector de vista -->
                    <div class="sol-toolbar">
                        <nav class="sol-tabs" aria-label="Base de solicitudes">
                            <a class="sol-tab <?= $filtro_ubicacion == 'local' ? 'active' : '' ?>" href="?ubicacion=local">
                                <i class="bi bi-folder2-open"></i> Base Local
                                <span class="sol-tab__count"><?= $count_tab_local ?></span>
                            </a>
                            <a class="sol-tab <?= $filtro_ubicacion == 'global' ? 'active' : '' ?>" href="?ubicacion=global" title="Solicitudes completadas">
                                <i class="bi bi-globe2"></i> Base Global
                                <span class="sol-tab__count"><?= $count_tab_global ?></span>
                            </a>
                        </nav>

                        <?php if (!empty($documentos_ui)): ?>
                        <div class="sol-vista-toggle" role="group" aria-label="Tipo de vista">
                            <button type="button" class="sol-vista-btn active" data-sol-vista="tarjetas" title="Vista de tarjetas">
                                <i class="bi bi-grid-3x2-gap"></i><span>Tarjetas</span>
                            </button>
                            <button type="button" class="sol-vista-btn" data-sol-vista="tabla" title="Vista de tabla">
                                <i class="bi bi-list-ul"></i><span>Tabla</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Indicadores -->
                    <div class="sol-kpis">
                        <?php foreach ($indicadores as $ind):
                            $tag = $ind['href'] ? 'a' : 'div'; ?>
                        <<?= $tag ?> class="sol-kpi sol-kpi--<?= $ind['tono'] ?><?= $ind['href'] ? ' is-link' : '' ?><?= $ind['activo'] ? ' is-active' : '' ?>"
                            <?= $ind['href'] ? 'href="' . htmlspecialchars($ind['href']) . '" title="Filtrar por este indicador"' : '' ?>>
                            <span class="sol-kpi__icon"><i class="bi <?= $ind['icono'] ?>"></i></span>
                            <span class="sol-kpi__body">
                                <span class="sol-kpi__num"><?= (int) $ind['num'] ?></span>
                                <span class="sol-kpi__label"><?= htmlspecialchars($ind['label']) ?></span>
                            </span>
                            <?php if ($ind['href']): ?><i class="bi bi-funnel-fill sol-kpi__hint"></i><?php endif; ?>
                        </<?= $tag ?>>
                        <?php endforeach; ?>
                    </div>

                    <!-- Filtros: en línea en escritorio, panel lateral en móvil -->
                    <div class="offcanvas-lg offcanvas-end sol-filtros" tabindex="-1" id="panelFiltros" aria-labelledby="panelFiltrosLabel">
                        <div class="offcanvas-header">
                            <h5 class="offcanvas-title" id="panelFiltrosLabel"><i class="bi bi-funnel"></i> Filtros</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#panelFiltros" aria-label="Cerrar"></button>
                        </div>
                        <div class="offcanvas-body">
                            <form method="GET" class="w-100">
                                <input type="hidden" name="ubicacion" value="<?= htmlspecialchars($filtro_ubicacion) ?>">
                                <div class="row g-3 align-items-end">

                                    <?php if ($filtro_ubicacion == 'local'): ?>
                                    <div class="col-12 col-lg-3">
                                        <label class="form-label">Estado</label>
                                        <select name="estado" class="form-select">
                                            <option value="">Todos</option>
                                            <option value="borrador" <?= $filtro_estado == 'borrador' ? 'selected' : '' ?>>Borrador</option>
                                            <option value="enviado" <?= $filtro_estado == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                            <option value="en_seguimiento" <?= $filtro_estado == 'en_seguimiento' ? 'selected' : '' ?>>En Seguimiento</option>
                                        </select>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-12 col-lg-3">
                                        <label class="form-label">Departamento</label>
                                        <select name="departamento" class="form-select">
                                            <option value="">Todos</option>
                                            <option value="Ventas" <?= $filtro_departamento == 'Ventas' ? 'selected' : '' ?>>Ventas</option>
                                            <option value="Normatividad" <?= $filtro_departamento == 'Normatividad' ? 'selected' : '' ?>>Normatividad</option>
                                            <option value="PTAR" <?= $filtro_departamento == 'PTAR' ? 'selected' : '' ?>>PTAR</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <?php $grupo_cliente = ($filtro_ubicacion == 'local') ? 'local' : 'global'; ?>
                                    <div class="col-12 col-lg-3">
                                        <label class="form-label">Cliente</label>
                                        <div class="dropdown multi-select-dropdown">
                                            <button class="form-select text-start dropdown-toggle-multi" type="button"
                                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                <?php if (empty($filtro_cliente)): ?>
                                                    Todos
                                                <?php elseif (count($filtro_cliente) === 1): ?>
                                                    <?= htmlspecialchars($filtro_cliente[0]) ?>
                                                <?php else: ?>
                                                    <?= count($filtro_cliente) ?> clientes
                                                <?php endif; ?>
                                            </button>
                                            <div class="dropdown-menu multi-select-menu p-2 w-100">
                                                <div class="d-flex justify-content-between mb-2 px-1">
                                                    <a href="#" class="small text-primary multi-select-all" data-target="<?= $grupo_cliente ?>">Todos</a>
                                                    <a href="#" class="small text-danger multi-clear-all" data-target="<?= $grupo_cliente ?>">Limpiar</a>
                                                </div>
                                                <div class="multi-select-search mb-2">
                                                    <input type="text" class="form-control form-control-sm" placeholder="Buscar cliente..." data-target="<?= $grupo_cliente ?>">
                                                </div>
                                                <div class="multi-select-options" style="max-height: 200px; overflow-y: auto;">
                                                    <?php foreach ($clientes_lista as $cliente): ?>
                                                    <label class="dropdown-item d-flex align-items-center py-1 multi-option" data-group="<?= $grupo_cliente ?>">
                                                        <input type="checkbox" name="cliente[]" value="<?= htmlspecialchars($cliente) ?>"
                                                               class="form-check-input me-2 mt-0"
                                                               <?= in_array($cliente, $filtro_cliente) ? 'checked' : '' ?>>
                                                        <span class="small text-truncate"><?= htmlspecialchars($cliente) ?></span>
                                                    </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6 col-lg-2">
                                        <label class="form-label">Fecha desde</label>
                                        <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                                    </div>
                                    <div class="col-6 col-lg-2">
                                        <label class="form-label">Fecha hasta</label>
                                        <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                                    </div>

                                    <div class="col-12 col-lg-2 d-flex gap-2">
                                        <button type="submit" class="sol-btn sol-btn--primary flex-grow-1">
                                            <i class="bi bi-search"></i> Filtrar
                                        </button>
                                        <a href="?ubicacion=<?= urlencode($filtro_ubicacion) ?>" class="sol-btn sol-btn--ghost" title="Limpiar filtros">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados + filtros activos -->
                    <div class="sol-resultados">
                        <div class="sol-resultados__info">
                            <span class="sol-resultados__count">
                                Mostrando <strong><?= count($documentos_ui) ?></strong> solicitud<?= count($documentos_ui) != 1 ? 'es' : '' ?>
                            </span>
                            <?php foreach ($chips as $chip): ?>
                            <a class="sol-fchip" href="<?= htmlspecialchars($chip['href']) ?>" title="Quitar filtro">
                                <?= htmlspecialchars($chip['texto']) ?> <i class="bi bi-x"></i>
                            </a>
                            <?php endforeach; ?>
                            <?php if (count($chips) > 1): ?>
                            <a class="sol-fchip-clear" href="?ubicacion=<?= urlencode($filtro_ubicacion) ?>">Limpiar todo</a>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="sol-btn sol-btn--ghost d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#panelFiltros" aria-controls="panelFiltros">
                            <i class="bi bi-funnel"></i> Filtros
                            <?php if ($hay_filtros): ?><span class="sol-btn-filtros__n"><?= count($chips) ?></span><?php endif; ?>
                        </button>
                    </div>

                    <!-- Listado -->
                    <?php if (empty($documentos_ui)): ?>
                    <div class="sol-empty">
                        <?php if ($hay_filtros): ?>
                            <div class="sol-empty__icon"><i class="bi bi-funnel"></i></div>
                            <h3>Sin resultados con estos filtros</h3>
                            <p>Prueba con otros criterios o quita algunos filtros.</p>
                            <a href="?ubicacion=<?= urlencode($filtro_ubicacion) ?>" class="sol-btn sol-btn--ghost">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        <?php elseif ($filtro_ubicacion == 'global'): ?>
                            <div class="sol-empty__icon"><i class="bi bi-archive"></i></div>
                            <h3>A&uacute;n no hay solicitudes completadas</h3>
                            <p>Cuando Laboratorio complete una solicitud aparecer&aacute; aqu&iacute;.</p>
                        <?php elseif ($puede_crear): ?>
                            <div class="sol-empty__icon"><i class="bi bi-file-earmark-plus"></i></div>
                            <h3>A&uacute;n no hay solicitudes</h3>
                            <p>Crea una solicitud de servicio para enviarla a Laboratorio.</p>
                            <button type="button" class="sol-btn sol-btn--primary" data-bs-toggle="modal" data-bs-target="#modalNuevoDocumento">
                                <i class="bi bi-plus-lg"></i> Crear la primera solicitud
                            </button>
                        <?php else: ?>
                            <div class="sol-empty__icon"><i class="bi bi-inboxes"></i></div>
                            <h3>No hay solicitudes pendientes</h3>
                            <p>Las solicitudes nuevas aparecer&aacute;n aqu&iacute;.</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="sol-listado" id="solListado">

                        <!-- Vista tarjetas -->
                        <div class="row g-3 sol-grid">
                            <?php foreach ($documentos_ui as $doc):
                                $desc = $doc['descripcion_servicio'] ?? ''; ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="sol-card sol-card--<?= $doc['_prioridad'] ?>">
                                    <div class="sol-card__top">
                                        <span class="sol-folio"><?= htmlspecialchars($doc['folio']) ?></span>
                                        <span class="sol-card__badges">
                                            <?php if ($doc['_no_visto']): ?>
                                            <span class="sol-novisto"><i class="bi bi-eye-slash"></i> No visto</span>
                                            <?php endif; ?>
                                            <span class="sol-estado sol-estado--<?= $doc['_estado']['clase'] ?>"><?= htmlspecialchars($doc['_estado']['texto']) ?></span>
                                        </span>
                                    </div>

                                    <h3 class="sol-card__cliente"><?= htmlspecialchars($doc['nombre_cliente'] ?? 'Sin cliente') ?></h3>
                                    <p class="sol-card__servicio">
                                        <i class="bi <?= $doc['_servicio']['icono'] ?>"></i> <?= htmlspecialchars($doc['_servicio']['texto']) ?>
                                    </p>
                                    <p class="sol-card__desc">
                                        <?= htmlspecialchars(mb_substr($desc, 0, 90)) ?><?= mb_strlen($desc) > 90 ? '&hellip;' : '' ?>
                                    </p>

                                    <div class="sol-card__foot">
                                        <span><i class="bi bi-building"></i> <?= htmlspecialchars($doc['departamento_creador']) ?></span>
                                        <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($doc['fecha_solicitud'])) ?></span>
                                        <?php if (!empty($doc['fecha_completado'])): ?>
                                        <span><i class="bi bi-check2-circle"></i> <?= date('d/m/Y', strtotime($doc['fecha_completado'])) ?></span>
                                        <?php endif; ?>
                                        <span title="Comentarios"><i class="bi bi-chat-dots"></i> <?= (int) $doc['_comentarios'] ?></span>
                                        <span class="sol-prio sol-prio--<?= $doc['_prioridad'] ?>"><i class="bi bi-flag-fill"></i> <?= ucfirst($doc['_prioridad']) ?></span>
                                        <a href="ver_documento.php?id=<?= (int) $doc['id'] ?>" class="sol-card__ver stretched-link">
                                            Ver <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>

                                    <!-- Tabla de Sistema de Gestión -->
                                    <table class="table table-bordered table-sm sol-sg">
                                        <tbody>
                                            <tr><td>Código de Sistema De Gestión</td><td>FR-CC-12</td></tr>
                                            <tr><td>No. de Revisión</td><td>02</td></tr>
                                            <tr><td>Fecha de Revisión</td><td>26/01/2026</td></tr>
                                        </tbody>
                                    </table>
                                </article>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Vista tabla -->
                        <div class="sol-tabla-wrap">
                            <div class="sol-tabla-head">
                                <table class="table table-bordered table-sm sol-sg">
                                    <tbody>
                                        <tr><td>Código de Sistema De Gestión</td><td>FR-CC-12</td></tr>
                                        <tr><td>No. de Revisión</td><td>02</td></tr>
                                        <tr><td>Fecha de Revisión</td><td>26/01/2026</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-responsive">
                                <table class="table sol-tabla">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Cliente</th>
                                            <th>Servicio</th>
                                            <th>Departamento</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Prioridad</th>
                                            <th class="text-center"><i class="bi bi-chat-dots" title="Comentarios"></i></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documentos_ui as $doc): ?>
                                        <tr>
                                            <td><span class="sol-folio"><?= htmlspecialchars($doc['folio']) ?></span></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($doc['nombre_cliente'] ?? 'Sin cliente') ?></td>
                                            <td><i class="bi <?= $doc['_servicio']['icono'] ?> me-1"></i><?= htmlspecialchars($doc['_servicio']['texto']) ?></td>
                                            <td><?= htmlspecialchars($doc['departamento_creador']) ?></td>
                                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($doc['fecha_solicitud'])) ?></td>
                                            <td>
                                                <span class="sol-estado sol-estado--<?= $doc['_estado']['clase'] ?>"><?= htmlspecialchars($doc['_estado']['texto']) ?></span>
                                                <?php if ($doc['_no_visto']): ?>
                                                <span class="sol-novisto ms-1"><i class="bi bi-eye-slash"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="sol-prio sol-prio--<?= $doc['_prioridad'] ?>"><i class="bi bi-flag-fill"></i> <?= ucfirst($doc['_prioridad']) ?></span></td>
                                            <td class="text-center"><?= (int) $doc['_comentarios'] ?></td>
                                            <td class="text-end">
                                                <a href="ver_documento.php?id=<?= (int) $doc['id'] ?>" class="sol-card__ver">Ver <i class="bi bi-arrow-right"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /.sol-view -->
            </div>
        </main>
    </div>
    
    <?php if ($puede_crear): ?>
    <!-- Modal Nuevo Documento -->
    <?php include __DIR__ . '/../../includes/colaborativo/modal_nuevo_documento.php'; ?>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

    <!-- ⭐ Multi-select dropdown JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Buscar dentro del dropdown
        document.querySelectorAll('.multi-select-search input').forEach(function(input) {
            input.addEventListener('input', function() {
                const busqueda = this.value.toLowerCase();
                const grupo = this.dataset.target;
                document.querySelectorAll('.multi-option[data-group="' + grupo + '"]').forEach(function(option) {
                    const texto = option.querySelector('span').textContent.toLowerCase();
                    option.style.display = texto.includes(busqueda) ? '' : 'none';
                });
            });
        });

        // Seleccionar todos
        document.querySelectorAll('.multi-select-all').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const grupo = this.dataset.target;
                document.querySelectorAll('.multi-option[data-group="' + grupo + '"] input[type="checkbox"]').forEach(function(cb) {
                    if (cb.closest('.multi-option').style.display !== 'none') {
                        cb.checked = true;
                    }
                });
                actualizarTextoBoton(grupo);
            });
        });

        // Limpiar todos
        document.querySelectorAll('.multi-clear-all').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const grupo = this.dataset.target;
                document.querySelectorAll('.multi-option[data-group="' + grupo + '"] input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = false;
                });
                actualizarTextoBoton(grupo);
            });
        });

        // Actualizar texto del botón al cambiar checkbox
        document.querySelectorAll('.multi-option input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                const grupo = this.closest('.multi-option').dataset.group;
                actualizarTextoBoton(grupo);
            });
        });

        function actualizarTextoBoton(grupo) {
            const checkboxes = document.querySelectorAll('.multi-option[data-group="' + grupo + '"] input[type="checkbox"]:checked');
            const boton = document.querySelector('.multi-option[data-group="' + grupo + '"]')
                          .closest('.multi-select-dropdown').querySelector('.dropdown-toggle-multi');
            
            if (checkboxes.length === 0) {
                boton.textContent = 'Todos';
            } else if (checkboxes.length === 1) {
                boton.textContent = checkboxes[0].closest('.multi-option').querySelector('span').textContent;
            } else {
                boton.textContent = checkboxes.length + ' clientes';
            }
        }
    });
    </script>

    <!-- ⭐ Selector de vista Tarjetas / Tabla -->
    <script>
    (() => {
        const listado = document.getElementById('solListado');
        if (!listado) return;
        const botones = document.querySelectorAll('[data-sol-vista]');

        const aplicar = (vista) => {
            listado.classList.toggle('is-tabla', vista === 'tabla');
            botones.forEach((b) => {
                const activo = b.dataset.solVista === vista;
                b.classList.toggle('active', activo);
                b.setAttribute('aria-pressed', activo ? 'true' : 'false');
            });
        };

        let guardada = 'tarjetas';
        try { guardada = localStorage.getItem('ssc_vista') || 'tarjetas'; } catch (e) {}
        aplicar(guardada);

        botones.forEach((b) => b.addEventListener('click', () => {
            aplicar(b.dataset.solVista);
            try { localStorage.setItem('ssc_vista', b.dataset.solVista); } catch (e) {}
        }));
    })();
    </script>

</body>
</html>