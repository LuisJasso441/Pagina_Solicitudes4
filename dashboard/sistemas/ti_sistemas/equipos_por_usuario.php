<?php
/**
 * Equipos por Usuario — Vista agrupada
 * dashboard/sistemas/ti_sistemas/equipos_por_usuario.php
 * 
 * Muestra todos los equipos del inventario agrupados por usuario asignado.
 * Permite ver de un vistazo qué tiene cada persona.
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

// Filtro de búsqueda
$busqueda = trim($_GET['busqueda'] ?? '');

// Obtener equipos agrupados por usuario_asignado_id
$where_busqueda = '';
$params = [];
if ($busqueda) {
    $where_busqueda = "AND (ua.nombre_completo LIKE ? OR ie.hostname LIKE ? OR ie.marca LIKE ? OR ie.modelo LIKE ?)";
    $b = "%{$busqueda}%";
    $params = [$b, $b, $b, $b];
}

// Usuarios con equipos asignados
$sql_usuarios = "
    SELECT ua.id AS usuario_id, ua.nombre_completo, ua.departamento,
           d.nombre AS departamento_nombre,
           COUNT(ie.id) AS total_equipos
    FROM inventario_equipos ie
    INNER JOIN usuarios ua ON ie.usuario_asignado_id = ua.id
    LEFT JOIN departamentos d ON ua.departamento_id = d.id
    WHERE ie.usuario_asignado_id IS NOT NULL {$where_busqueda}
    GROUP BY ua.id, ua.nombre_completo, ua.departamento, d.nombre
    ORDER BY ua.nombre_completo ASC
";
$stmt = $pdo->prepare($sql_usuarios);
$stmt->execute($params);
$usuarios_con_equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener TODOS los equipos asignados (para el detalle)
$sql_equipos = "
    SELECT ie.id, ie.hostname, ie.tipo_equipo, ie.marca, ie.modelo, ie.numero_serie,
           ie.ubicacion, ie.estado, ie.personal_asignado, ie.usuario_asignado_id,
           d.nombre AS departamento_nombre
    FROM inventario_equipos ie
    LEFT JOIN departamentos d ON ie.departamento_id = d.id
    WHERE ie.usuario_asignado_id IS NOT NULL
    ORDER BY ie.usuario_asignado_id, ie.tipo_equipo
";
$equipos_todos = $pdo->query($sql_equipos)->fetchAll(PDO::FETCH_ASSOC);

// Agrupar equipos por usuario
$equipos_por_usuario = [];
foreach ($equipos_todos as $eq) {
    $uid = $eq['usuario_asignado_id'];
    if (!isset($equipos_por_usuario[$uid])) $equipos_por_usuario[$uid] = [];
    $equipos_por_usuario[$uid][] = $eq;
}

// Equipos sin asignar
$stmt_sin = $pdo->query("SELECT COUNT(*) FROM inventario_equipos WHERE usuario_asignado_id IS NULL");
$equipos_sin_asignar = $stmt_sin->fetchColumn();

// Stats
$total_usuarios_con_equipos = count($usuarios_con_equipos);
$total_equipos_asignados = array_sum(array_column($usuarios_con_equipos, 'total_equipos'));

// Tipos config para iconos
$tipos_config = [
    'all_in_one'   => ['nombre' => 'All In One',    'icono' => 'bi-display',       'color' => '#2563eb'],
    'pc'           => ['nombre' => 'PC',             'icono' => 'bi-pc-display',    'color' => '#2563eb'],
    'laptop'       => ['nombre' => 'Laptop',         'icono' => 'bi-laptop',        'color' => '#0891b2'],
    'monitor'      => ['nombre' => 'Monitor',        'icono' => 'bi-display',       'color' => '#0891b2'],
    'mouse'        => ['nombre' => 'Mouse',          'icono' => 'bi-mouse',         'color' => '#64748b'],
    'teclado'      => ['nombre' => 'Teclado',        'icono' => 'bi-keyboard',      'color' => '#64748b'],
    'impresora'    => ['nombre' => 'Impresora',      'icono' => 'bi-printer',       'color' => '#7c3aed'],
    'access_point' => ['nombre' => 'Access Point',   'icono' => 'bi-wifi',          'color' => '#d97706'],
    'switch'       => ['nombre' => 'Switch',         'icono' => 'bi-diagram-3',     'color' => '#d97706'],
    'camara'       => ['nombre' => 'C&aacute;mara',  'icono' => 'bi-camera-video',  'color' => '#059669'],
    'celular'      => ['nombre' => 'Celular',        'icono' => 'bi-phone',         'color' => '#ea580c'],
    'computadora'  => ['nombre' => 'Computadora',    'icono' => 'bi-pc-display',    'color' => '#2563eb'],
    'telefono'     => ['nombre' => 'Tel&eacute;fono','icono' => 'bi-telephone',     'color' => '#ea580c'],
    'pantalla_tv'  => ['nombre' => 'Pantalla TV',    'icono' => 'bi-tv',              'color' => '#0891b2'],
    'nobreak'      => ['nombre' => 'Nobreak',        'icono' => 'bi-battery-charging', 'color' => '#059669'],
];

$current_page = basename(__FILE__);
$page_title = "Equipos por Usuario";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/dashboard.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/base/variables.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css" rel="stylesheet">
    <style>
        .usuario-card {
            border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
            margin-bottom: 12px; overflow: hidden; transition: box-shadow 0.2s;
        }
        .usuario-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .usuario-header {
            padding: 14px 20px; cursor: pointer; display: flex; 
            justify-content: space-between; align-items: center;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            transition: background 0.15s;
        }
        .usuario-header:hover { background: #f0f7ff; }
        .usuario-header.active { background: #eff6ff; border-bottom-color: #bfdbfe; }
        .usuario-nombre { font-weight: 700; font-size: 1rem; color: #1e293b; }
        .usuario-depto { font-size: 0.78rem; color: #64748b; }
        .usuario-badge { 
            background: #2563eb; color: #fff; padding: 4px 12px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600;
        }
        .equipo-lista { display: none; padding: 0; }
        .equipo-lista.show { display: block; }
        .equipo-item {
            display: flex; align-items: center; gap: 14px;
            padding: 10px 20px; border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }
        .equipo-item:last-child { border-bottom: none; }
        .equipo-item:hover { background: #f8fafc; }
        .equipo-icono {
            width: 40px; height: 40px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; font-size: 1.1rem;
            flex-shrink: 0;
        }
        .equipo-info { flex: 1; min-width: 0; }
        .equipo-hostname { font-weight: 700; font-size: 0.88rem; color: #1e293b; font-family: monospace; }
        .equipo-hostname a { color: #2563eb; text-decoration: none; }
        .equipo-hostname a:hover { text-decoration: underline; }
        .equipo-meta { font-size: 0.78rem; color: #64748b; }
        .equipo-estado { font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
        .estado-activo { background: #d1fae5; color: #065f46; }
        .estado-inactivo { background: #fee2e2; color: #991b1b; }
        .estado-en_reparacion { background: #fef3c7; color: #92400e; }
        .estado-dado_de_baja { background: #f1f5f9; color: #475569; }
        
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        
        .chevron-icon { transition: transform 0.2s; }
        .usuario-header.active .chevron-icon { transform: rotate(90deg); }
        
        /* Dark mode */
        body[data-theme="dark"] .usuario-card { background: #1e293b; border-color: #334155; }
        body[data-theme="dark"] .usuario-header { background: #0f172a; border-color: #334155; }
        body[data-theme="dark"] .usuario-header:hover { background: #1e3a5f; }
        body[data-theme="dark"] .usuario-nombre { color: #e2e8f0; }
        body[data-theme="dark"] .equipo-item { border-color: #1e293b; }
        body[data-theme="dark"] .equipo-item:hover { background: #0f172a; }
        body[data-theme="dark"] .equipo-hostname { color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-people me-2"></i><?php echo $page_title; ?></h4>
                        <small class="text-muted">Vista agrupada de equipos asignados por persona</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al Inventario
                    </a>
                </div>
                
                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-people-fill"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $total_usuarios_con_equipos; ?></div><small class="text-muted">Usuarios con equipos</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: #d1fae5; color: #059669;"><i class="bi bi-laptop"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $total_equipos_asignados; ?></div><small class="text-muted">Equipos asignados</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: <?php echo $equipos_sin_asignar > 0 ? '#fef3c7' : '#f1f5f9'; ?>; color: <?php echo $equipos_sin_asignar > 0 ? '#d97706' : '#64748b'; ?>;"><i class="bi bi-question-circle"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $equipos_sin_asignar; ?></div><small class="text-muted">Sin asignar</small></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Buscador + Expandir/Colapsar -->
                <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
                    <form method="GET" class="d-flex gap-2 align-items-end">
                        <div>
                            <label class="form-label">Buscar</label>
                            <input type="text" name="busqueda" class="form-control form-control-sm" style="min-width: 280px;"
                                   placeholder="Nombre de usuario, hostname, marca, modelo..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-search"></i></button>
                        <?php if ($busqueda): ?>
                        <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="expandirTodos()"><i class="bi bi-arrows-expand me-1"></i>Expandir todos</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="colapsarTodos()"><i class="bi bi-arrows-collapse me-1"></i>Colapsar</button>
                    </div>
                </div>
                
                <!-- Lista de usuarios con equipos -->
                <?php if (empty($usuarios_con_equipos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                    <p>No hay equipos asignados a usuarios<?php echo $busqueda ? ' con ese criterio de b&uacute;squeda' : ''; ?>.</p>
                </div>
                <?php else: ?>
                
                <?php foreach ($usuarios_con_equipos as $usuario): 
                    $uid = $usuario['usuario_id'];
                    $equipos_usuario = $equipos_por_usuario[$uid] ?? [];
                ?>
                <div class="usuario-card">
                    <div class="usuario-header" onclick="toggleUsuario(<?php echo $uid; ?>)" id="header-<?php echo $uid; ?>">
                        <div>
                            <div class="usuario-nombre">
                                <i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                            </div>
                            <div class="usuario-depto">
                                <?php echo htmlspecialchars($usuario['departamento_nombre'] ?? $usuario['departamento'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="usuario-badge"><?php echo $usuario['total_equipos']; ?> equipo(s)</span>
                            <i class="bi bi-chevron-right chevron-icon" id="chevron-<?php echo $uid; ?>"></i>
                        </div>
                    </div>
                    <div class="equipo-lista" id="equipos-<?php echo $uid; ?>">
                        <?php foreach ($equipos_usuario as $eq): 
                            $tipo = $tipos_config[$eq['tipo_equipo']] ?? ['nombre' => $eq['tipo_equipo'], 'icono' => 'bi-device-hdd', 'color' => '#64748b'];
                            $estado_class = 'estado-' . str_replace(' ', '_', $eq['estado']);
                        ?>
                        <div class="equipo-item">
                            <div class="equipo-icono" style="background: <?php echo $tipo['color']; ?>15; color: <?php echo $tipo['color']; ?>;">
                                <i class="bi <?php echo $tipo['icono']; ?>"></i>
                            </div>
                            <div class="equipo-info">
                                <div class="equipo-hostname">
                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $eq['id']; ?>">
                                        <?php echo htmlspecialchars($eq['hostname']); ?>
                                    </a>
                                    <span style="font-weight: 400; font-family: sans-serif; color: #64748b; font-size: 0.8rem;"> — <?php echo $tipo['nombre']; ?></span>
                                </div>
                                <div class="equipo-meta">
                                    <?php if ($eq['marca'] || $eq['modelo']): ?>
                                    <?php echo htmlspecialchars(trim($eq['marca'] . ' ' . $eq['modelo'])); ?>
                                    <?php endif; ?>
                                    <?php if ($eq['numero_serie']): ?> &bull; S/N: <?php echo htmlspecialchars($eq['numero_serie']); ?><?php endif; ?>
                                    <?php if ($eq['ubicacion']): ?> &bull; <?php echo htmlspecialchars($eq['ubicacion']); ?><?php endif; ?>
                                    <?php if ($eq['personal_asignado']): ?> &bull; <span style="color: #2563eb;">Padre: <?php echo htmlspecialchars($eq['personal_asignado']); ?></span><?php endif; ?>
                                </div>
                            </div>
                            <span class="equipo-estado <?php echo $estado_class; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $eq['estado'])); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
                
                <div class="text-muted mt-3" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle"></i> Solo se muestran equipos con <strong>Usuario Asignado</strong> definido en el inventario. 
                    Para asignar, edite el equipo desde el <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php">Inventario de Equipos</a>.
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
    function toggleUsuario(uid) {
        const lista = document.getElementById('equipos-' + uid);
        const header = document.getElementById('header-' + uid);
        const chevron = document.getElementById('chevron-' + uid);
        
        if (lista.classList.contains('show')) {
            lista.classList.remove('show');
            header.classList.remove('active');
        } else {
            lista.classList.add('show');
            header.classList.add('active');
        }
    }
    
    function expandirTodos() {
        document.querySelectorAll('.equipo-lista').forEach(el => el.classList.add('show'));
        document.querySelectorAll('.usuario-header').forEach(el => el.classList.add('active'));
    }
    
    function colapsarTodos() {
        document.querySelectorAll('.equipo-lista').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.usuario-header').forEach(el => el.classList.remove('active'));
    }
    </script>
    
    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>