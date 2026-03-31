<?php
/**
 * Inventario de Equipos Electr&oacute;nicos v2.0
 * dashboard/sistemas/ti_sistemas/inventario.php
 * 
 * Tipos ampliados, hostname como identificador principal, personal_asignado
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta secci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// Filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_departamento = $_GET['departamento'] ?? '';
$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$registros_por_pagina = intval($_GET['mostrar'] ?? 15);
$pagina_actual = max(1, intval($_GET['pagina'] ?? 1));

// Tipos v&aacute;lidos v2.0
$tipos_validos = [
    'all_in_one', 'pc', 'laptop', 'monitor', 'mouse', 'teclado',
    'impresora', 'access_point', 'switch', 'camara', 'celular',
    'computadora', 'telefono'
];

// Mapeo de grupos a tipos
$grupos_tipos = [
    'computo'     => ['all_in_one', 'pc', 'laptop', 'computadora'],
    'perifericos' => ['monitor', 'mouse', 'teclado'],
    'impresoras'  => ['impresora'],
    'red'         => ['access_point', 'switch'],
    'otros'       => ['camara', 'celular', 'telefono'],
];

$filtro_grupo = $_GET['grupo'] ?? '';

$where = ["1=1"];
$params = [];

if ($filtro_grupo && isset($grupos_tipos[$filtro_grupo])) {
    $tipos_grupo = $grupos_tipos[$filtro_grupo];
    $placeholders = implode(',', array_fill(0, count($tipos_grupo), '?'));
    $where[] = "ie.tipo_equipo IN ({$placeholders})";
    $params = array_merge($params, $tipos_grupo);
} elseif ($filtro_tipo && in_array($filtro_tipo, $tipos_validos)) {
    $where[] = "ie.tipo_equipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_estado && in_array($filtro_estado, ['activo', 'inactivo', 'en_reparacion', 'dado_de_baja'])) {
    $where[] = "ie.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_departamento) {
    $where[] = "ie.departamento_id = ?";
    $params[] = intval($filtro_departamento);
}

if ($filtro_busqueda) {
    $where[] = "(ie.hostname LIKE ? OR ie.codigo_interno LIKE ? OR ie.marca LIKE ? OR ie.modelo LIKE ? OR ie.ubicacion LIKE ? OR ie.numero_serie LIKE ? OR ie.personal_asignado LIKE ?)";
    $b = "%{$filtro_busqueda}%";
    $params = array_merge($params, [$b, $b, $b, $b, $b, $b, $b]);
}

$where_clause = implode(" AND ", $where);

// Conteo
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_equipos ie WHERE {$where_clause}");
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener equipos
$sql = "SELECT ie.*, d.nombre as departamento_nombre,
               ua.nombre_completo as usuario_asignado_nombre
        FROM inventario_equipos ie
        LEFT JOIN departamentos d ON ie.departamento_id = d.id
        LEFT JOIN usuarios ua ON ie.usuario_asignado_id = ua.id
        WHERE {$where_clause}
        ORDER BY ie.fecha_registro DESC
        LIMIT {$registros_por_pagina} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Departamentos para filtro
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Contadores por tipo
$contadores = $pdo->query("SELECT tipo_equipo, COUNT(*) as total FROM inventario_equipos GROUP BY tipo_equipo")->fetchAll(PDO::FETCH_KEY_PAIR);

// Contadores por estado
$contadores_estado = $pdo->query("SELECT estado, COUNT(*) as total FROM inventario_equipos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

// Mapeo de tipos
$tipos_config = [
    'all_in_one'   => ['nombre' => 'All In One',    'icono' => 'bi-display',           'color' => 'primary'],
    'pc'           => ['nombre' => 'PC',             'icono' => 'bi-pc-display',        'color' => 'primary'],
    'laptop'       => ['nombre' => 'Laptop',         'icono' => 'bi-laptop',            'color' => 'info'],
    'monitor'      => ['nombre' => 'Monitor',        'icono' => 'bi-display',           'color' => 'info'],
    'mouse'        => ['nombre' => 'Mouse',          'icono' => 'bi-mouse',             'color' => 'secondary'],
    'teclado'      => ['nombre' => 'Teclado',        'icono' => 'bi-keyboard',          'color' => 'secondary'],
    'impresora'    => ['nombre' => 'Impresora',      'icono' => 'bi-printer',           'color' => 'purple'],
    'access_point' => ['nombre' => 'Access Point',   'icono' => 'bi-wifi',              'color' => 'warning'],
    'switch'       => ['nombre' => 'Switch',         'icono' => 'bi-diagram-3',         'color' => 'warning'],
    'camara'       => ['nombre' => 'C&aacute;mara',  'icono' => 'bi-camera-video',      'color' => 'success'],
    'celular'      => ['nombre' => 'Celular',        'icono' => 'bi-phone',             'color' => 'orange'],
    'computadora'  => ['nombre' => 'Computadora',    'icono' => 'bi-pc-display',        'color' => 'primary'],
    'telefono'     => ['nombre' => 'Tel&eacute;fono', 'icono' => 'bi-telephone',        'color' => 'orange'],
];

// Agrupar contadores para cards de resumen
$grupo_computo   = ($contadores['all_in_one'] ?? 0) + ($contadores['pc'] ?? 0) + ($contadores['laptop'] ?? 0) + ($contadores['computadora'] ?? 0);
$grupo_periferic = ($contadores['monitor'] ?? 0) + ($contadores['mouse'] ?? 0) + ($contadores['teclado'] ?? 0);
$grupo_impresora = ($contadores['impresora'] ?? 0);
$grupo_red       = ($contadores['access_point'] ?? 0) + ($contadores['switch'] ?? 0);
$grupo_otros     = ($contadores['camara'] ?? 0) + ($contadores['celular'] ?? 0) + ($contadores['telefono'] ?? 0);

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Equipos - TI/Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            background: var(--card-bg, #fff);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .stat-card.active { border-color: #0d6efd; }
        .stat-card .icon { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .stat-card .count { font-size: 1.4rem; font-weight: 700; }
        .stat-card .label { font-size: 0.75rem; color: #6c757d; }
        .text-purple { color: #6f42c1 !important; }
        .text-orange { color: #fd7e14 !important; }
        .bg-purple  { background-color: #6f42c1 !important; }
        .bg-orange  { background-color: #fd7e14 !important; }
        .hostname-badge {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .table-inventario th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; }
        .table-inventario td { font-size: 0.85rem; vertical-align: middle; }
        .filtro-tipo-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.78rem;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filtro-tipo-btn:hover, .filtro-tipo-btn.active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-pc-display-horizontal me-2"></i>Inventario de Equipos</h4>
                        <small class="text-muted"><?php echo $total_registros; ?> equipos registrados</small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/importar_inventario.php" class="btn btn-outline-success">
                            <i class="bi bi-upload me-1"></i>Importar Excel
                        </a>
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/crear_equipo.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Agregar Equipo
                        </a>
                    </div>
                </div>

                <?php if (function_exists('mostrar_alerta')) mostrar_alerta(); ?>

                <!-- Cards resumen -->
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md">
                        <div class="stat-card <?php echo $filtro_grupo === 'computo' ? 'active' : ''; ?>" 
                             onclick="filtrarPorGrupo('computo')">
                            <div class="icon text-primary"><i class="bi bi-pc-display"></i></div>
                            <div class="count"><?php echo $grupo_computo; ?></div>
                            <div class="label">C&oacute;mputo</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="stat-card <?php echo $filtro_grupo === 'perifericos' ? 'active' : ''; ?>"
                             onclick="filtrarPorGrupo('perifericos')">
                            <div class="icon text-info"><i class="bi bi-mouse"></i></div>
                            <div class="count"><?php echo $grupo_periferic; ?></div>
                            <div class="label">Perif&eacute;ricos</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="stat-card <?php echo $filtro_grupo === 'impresoras' || $filtro_tipo === 'impresora' ? 'active' : ''; ?>"
                             onclick="filtrarPorGrupo('impresoras')">
                            <div class="icon text-purple"><i class="bi bi-printer"></i></div>
                            <div class="count"><?php echo $grupo_impresora; ?></div>
                            <div class="label">Impresoras</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="stat-card <?php echo $filtro_grupo === 'red' ? 'active' : ''; ?>"
                             onclick="filtrarPorGrupo('red')">
                            <div class="icon text-warning"><i class="bi bi-wifi"></i></div>
                            <div class="count"><?php echo $grupo_red; ?></div>
                            <div class="label">Red</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="stat-card <?php echo $filtro_grupo === 'otros' ? 'active' : ''; ?>"
                             onclick="filtrarPorGrupo('otros')">
                            <div class="icon text-success"><i class="bi bi-camera-video"></i></div>
                            <div class="count"><?php echo $grupo_otros; ?></div>
                            <div class="label">Otros</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <form method="GET" class="row g-2 align-items-end" id="formFiltros">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:0.8rem;">Buscar</label>
                                <input type="text" name="busqueda" class="form-control form-control-sm" 
                                       placeholder="Hostname, marca, modelo, serie..."
                                       value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:0.8rem;">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos_config as $key => $cfg): ?>
                                        <?php if (isset($contadores[$key]) && $contadores[$key] > 0): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtro_tipo === $key ? 'selected' : ''; ?>>
                                            <?php echo $cfg['nombre']; ?> (<?php echo $contadores[$key]; ?>)
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:0.8rem;">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="en_reparacion" <?php echo $filtro_estado === 'en_reparacion' ? 'selected' : ''; ?>>En Reparaci&oacute;n</option>
                                    <option value="dado_de_baja" <?php echo $filtro_estado === 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" style="font-size:0.8rem;">Departamento</label>
                                <select name="departamento" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($departamentos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $filtro_departamento == $d['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 table-inventario">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hostname</th>
                                        <th>Tipo</th>
                                        <th>Marca / Modelo</th>
                                        <th>N&uacute;mero de Serie</th>
                                        <th>Departamento</th>
                                        <th>Ubicaci&oacute;n</th>
                                        <th>Asignado a</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($equipos)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                No se encontraron equipos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($equipos as $eq): ?>
                                            <?php 
                                            $tc = $tipos_config[$eq['tipo_equipo']] ?? ['nombre' => ucfirst($eq['tipo_equipo']), 'icono' => 'bi-question-circle', 'color' => 'secondary'];
                                            $badges_estado = [
                                                'activo' => 'bg-success', 'inactivo' => 'bg-secondary',
                                                'en_reparacion' => 'bg-warning text-dark', 'dado_de_baja' => 'bg-danger'
                                            ];
                                            $nombres_estado = [
                                                'activo' => 'Activo', 'inactivo' => 'Inactivo',
                                                'en_reparacion' => 'En Reparaci&oacute;n', 'dado_de_baja' => 'Dado de Baja'
                                            ];
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="hostname-badge text-<?php echo $tc['color']; ?>">
                                                        <?php echo htmlspecialchars($eq['hostname'] ?: $eq['codigo_interno']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="bi <?php echo $tc['icono']; ?> text-<?php echo $tc['color']; ?> me-1"></i>
                                                    <small><?php echo $tc['nombre']; ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($eq['marca'] || $eq['modelo']): ?>
                                                        <div style="font-size:0.82rem;">
                                                            <?php echo htmlspecialchars(trim($eq['marca'] . ' ' . $eq['modelo'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($eq['numero_serie'] ?: '—'); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($eq['departamento_nombre'] ?? '—'); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($eq['ubicacion'] ?? '—'); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($eq['personal_asignado']): ?>
                                                        <span class="badge bg-light text-dark border" style="font-family:monospace;font-size:0.75rem;">
                                                            <?php echo htmlspecialchars($eq['personal_asignado']); ?>
                                                        </span>
                                                    <?php elseif ($eq['usuario_asignado_nombre']): ?>
                                                        <small><?php echo htmlspecialchars($eq['usuario_asignado_nombre']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo $badges_estado[$eq['estado']] ?? 'bg-secondary'; ?>" style="font-size:0.72rem;">
                                                        <?php echo $nombres_estado[$eq['estado']] ?? $eq['estado']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $eq['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" title="Ver">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $eq['id']; ?>" 
                                                           class="btn btn-outline-secondary btn-sm" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" title="Eliminar"
                                                                onclick="confirmarEliminar(<?php echo $eq['id']; ?>, '<?php echo htmlspecialchars($eq['hostname'] ?: $eq['codigo_interno'], ENT_QUOTES); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Paginaci&oacute;n -->
                    <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $registros_por_pagina, $total_registros); ?> 
                            de <?php echo $total_registros; ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $query_base = $_GET;
                                for ($i = 1; $i <= $total_paginas; $i++):
                                    $query_base['pagina'] = $i;
                                ?>
                                    <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query($query_base); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </main>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-1"></i>Confirmar</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>&iquest;Eliminar el equipo <strong id="nombreEquipoEliminar"></strong>?</p>
                    <small class="text-muted">Esta acci&oacute;n no se puede deshacer.</small>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_equipo.php">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="equipo_id" id="inputEliminarId">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
    function filtrarPorTipo(tipo) {
        const url = new URL(window.location.href);
        url.searchParams.delete('grupo');
        if (url.searchParams.get('tipo') === tipo) {
            url.searchParams.delete('tipo');
        } else {
            url.searchParams.set('tipo', tipo);
        }
        url.searchParams.delete('pagina');
        window.location.href = url.toString();
    }
    
    function filtrarPorGrupo(grupo) {
        const url = new URL(window.location.href);
        url.searchParams.delete('tipo');
        if (url.searchParams.get('grupo') === grupo) {
            url.searchParams.delete('grupo');
        } else {
            url.searchParams.set('grupo', grupo);
        }
        url.searchParams.delete('pagina');
        window.location.href = url.toString();
    }
    
    function confirmarEliminar(id, nombre) {
        document.getElementById('inputEliminarId').value = id;
        document.getElementById('nombreEquipoEliminar').textContent = nombre;
        new bootstrap.Modal(document.getElementById('modalEliminar')).show();
    }
    </script>
</body>
</html>