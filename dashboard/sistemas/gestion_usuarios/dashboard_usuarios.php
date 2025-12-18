<?php
/**
 * Dashboard de Gestión de Usuarios
 * Solo accesible para usuarios del departamento de Sistemas
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta sección.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Parámetros de filtrado y paginación
$buscar_usuario = isset($_GET['buscar_usuario']) ? trim($_GET['buscar_usuario']) : '';
$filtro_departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$registros_por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'id';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

// Validar valores
$registros_por_pagina = in_array($registros_por_pagina, [10, 25, 50]) ? $registros_por_pagina : 10;
$pagina_actual = max(1, $pagina_actual);
$orden = in_array($orden, ['id', 'nombre_completo', 'usuario', 'departamento']) ? $orden : 'id';
$direccion = in_array(strtoupper($direccion), ['ASC', 'DESC']) ? strtoupper($direccion) : 'ASC';

// Calcular offset
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Construir consulta base
$where_conditions = [];
$params = [];

if (!empty($buscar_usuario)) {
    $where_conditions[] = "(u.usuario LIKE :buscar1 OR u.nombre_completo LIKE :buscar2)";
    $params[':buscar1'] = "%{$buscar_usuario}%";
    $params[':buscar2'] = "%{$buscar_usuario}%";
}

if (!empty($filtro_departamento)) {
    $where_conditions[] = "u.departamento_id = :depto";
    $params[':depto'] = (int)$filtro_departamento;
}

if ($filtro_estado !== '') {
    $where_conditions[] = "u.activo = :estado";
    $params[':estado'] = (int)$filtro_estado;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Consulta para contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM usuarios u $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta principal
$sql = "SELECT 
            u.id,
            u.nombre_completo,
            u.usuario,
            u.departamento,
            d.nombre AS departamento_nombre,
            u.activo,
            u.fecha_registro,
            u.ultimo_acceso
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        $where_sql
        ORDER BY u.$orden $direccion
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de departamentos para el filtro
$sql_deptos = "SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos = $pdo->query($sql_deptos)->fetchAll(PDO::FETCH_ASSOC);

// Función para construir URL con parámetros
function buildUrl($params_override = []) {
    $params = array_merge($_GET, $params_override);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistemas</title>
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
    
    <style>
        .table th { 
            background-color: #f8f9fa; 
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .table td {
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .badge-activo { background-color: #198754; }
        .badge-inactivo { background-color: #dc3545; }
        .filtros-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .btn-accion {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .pagination .page-link {
            font-size: 0.85rem;
            padding: 0.375rem 0.75rem;
        }
        .sort-icon {
            font-size: 0.7rem;
            margin-left: 3px;
        }
        .th-sortable {
            cursor: pointer;
            user-select: none;
        }
        .th-sortable:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="row mb-3">
                    <div class="col">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/crear_usuario.php" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card filtros-card mb-3">
                    <div class="card-body py-2">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Buscar usuario</label>
                                <input type="text" name="buscar_usuario" class="form-control form-control-sm" 
                                       placeholder="Nombre o usuario..." value="<?php echo htmlspecialchars($buscar_usuario); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Departamento</label>
                                <select name="departamento" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?php echo $depto['id']; ?>" <?php echo $filtro_departamento == $depto['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($depto['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="1" <?php echo $filtro_estado === '1' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo $filtro_estado === '0' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Mostrar</label>
                                <select name="por_pagina" class="form-select form-select-sm">
                                    <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm btn-secondary me-1">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Mensajes de éxito/error -->
                <?php if (isset($_GET['msg'])): ?>
                    <?php
                    $mensajes = [
                        'creado' => ['success', 'Usuario creado correctamente.'],
                        'actualizado' => ['success', 'Usuario actualizado correctamente.'],
                        'activado' => ['success', 'Usuario activado correctamente.'],
                        'desactivado' => ['warning', 'Usuario desactivado correctamente.'],
                        'error' => ['danger', 'Ocurrió un error. Intente nuevamente.'],
                        'error_usuario_existe' => ['danger', 'El nombre de usuario ya existe.'],
                    ];
                    $msg = $_GET['msg'];
                    if (isset($mensajes[$msg])):
                    ?>
                        <div class="alert alert-<?php echo $mensajes[$msg][0]; ?> alert-dismissible fade show py-2" role="alert">
                            <?php echo $mensajes[$msg][1]; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Tabla de usuarios -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <?php
                                        $columnas = [
                                            'id' => 'ID',
                                            'nombre_completo' => 'Nombre Completo',
                                            'usuario' => 'Usuario',
                                            'departamento' => 'Departamento'
                                        ];
                                        foreach ($columnas as $col => $label):
                                            $nueva_dir = ($orden === $col && $direccion === 'ASC') ? 'DESC' : 'ASC';
                                            $icono = '';
                                            if ($orden === $col) {
                                                $icono = $direccion === 'ASC' ? '<i class="bi bi-caret-up-fill sort-icon"></i>' : '<i class="bi bi-caret-down-fill sort-icon"></i>';
                                            }
                                        ?>
                                            <th class="th-sortable" onclick="window.location='<?php echo buildUrl(['orden' => $col, 'dir' => $nueva_dir, 'pagina' => 1]); ?>'">
                                                <?php echo $label . $icono; ?>
                                            </th>
                                        <?php endforeach; ?>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($usuarios) > 0): ?>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <tr>
                                                <td><?php echo $usuario['id']; ?></td>
                                                <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                                <td><code><?php echo htmlspecialchars($usuario['usuario']); ?></code></td>
                                                <td><?php echo htmlspecialchars($usuario['departamento_nombre'] ?? $usuario['departamento']); ?></td>
                                                <td class="text-center">
                                                    <?php if ($usuario['activo']): ?>
                                                        <span class="badge badge-activo">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-inactivo">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/editar_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary btn-accion" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                No se encontraron usuarios con los filtros aplicados.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $registros_por_pagina, $total_registros); ?> 
                                    de <?php echo $total_registros; ?> registros
                                </small>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl(['pagina' => 1]); ?>">
                                                <i class="bi bi-chevron-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl(['pagina' => $pagina_actual - 1]); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $rango = 2;
                                        $inicio = max(1, $pagina_actual - $rango);
                                        $fin = min($total_paginas, $pagina_actual + $rango);
                                        
                                        for ($i = $inicio; $i <= $fin; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo buildUrl(['pagina' => $i]); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl(['pagina' => $pagina_actual + 1]); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo buildUrl(['pagina' => $total_paginas]); ?>">
                                                <i class="bi bi-chevron-double-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
</body>
</html>