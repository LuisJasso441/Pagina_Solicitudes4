<?php
/**
 * Inventario de Equipos Electrónicos
 * Solo accesible para usuarios del departamento de Sistemas
 * dashboard/sistemas/ti_sistemas/inventario.php
 * 
 * ⚠️ CORREGIDO para coincidir con estructura real de tabla inventario_equipos
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta sección.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_departamento = $_GET['departamento'] ?? '';
$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$registros_por_pagina = intval($_GET['mostrar'] ?? 10);
$pagina_actual = max(1, intval($_GET['pagina'] ?? 1));

// Construir consulta
$where = ["1=1"];
$params = [];

if ($filtro_tipo && in_array($filtro_tipo, ['computadora', 'impresora', 'camara', 'telefono'])) {
    $where[] = "ie.tipo_equipo = ?";
    $params[] = $filtro_tipo;
}

// Estados según el enum de la BD
if ($filtro_estado && in_array($filtro_estado, ['activo', 'inactivo', 'en_reparacion', 'dado_de_baja'])) {
    $where[] = "ie.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_departamento) {
    $where[] = "ie.departamento_id = ?";
    $params[] = intval($filtro_departamento);
}

if ($filtro_busqueda) {
    $where[] = "(ie.codigo_interno LIKE ? OR ie.marca LIKE ? OR ie.modelo LIKE ? OR ie.ubicacion LIKE ? OR ie.numero_serie LIKE ?)";
    $busqueda_param = "%{$filtro_busqueda}%";
    $params = array_merge($params, [$busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param]);
}

$where_clause = implode(" AND ", $where);

// Contar total de registros
$sql_count = "SELECT COUNT(*) FROM inventario_equipos ie WHERE {$where_clause}";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener equipos
$sql = "SELECT ie.*, d.nombre as departamento_nombre,
               u.nombre_completo as registrado_por_nombre,
               ua.nombre_completo as usuario_asignado_nombre
        FROM inventario_equipos ie
        LEFT JOIN departamentos d ON ie.departamento_id = d.id
        LEFT JOIN usuarios u ON ie.registrado_por = u.id
        LEFT JOIN usuarios ua ON ie.usuario_asignado_id = ua.id
        WHERE {$where_clause}
        ORDER BY ie.fecha_registro DESC
        LIMIT {$registros_por_pagina} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener departamentos para el filtro
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener contadores por tipo
$sql_contadores = "SELECT tipo_equipo, COUNT(*) as total FROM inventario_equipos GROUP BY tipo_equipo";
$contadores = $pdo->query($sql_contadores)->fetchAll(PDO::FETCH_KEY_PAIR);

// Obtener contadores por estado
$sql_estados = "SELECT estado, COUNT(*) as total FROM inventario_equipos GROUP BY estado";
$contadores_estado = $pdo->query($sql_estados)->fetchAll(PDO::FETCH_KEY_PAIR);
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
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        .stat-card {
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card.active {
            border-color: #0d6efd;
        }
        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stat-card .count {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .stat-card.computadora { background-color: #e7f1ff; }
        .stat-card.computadora .icon { color: #0d6efd; }
        .stat-card.impresora { background-color: #f3e8ff; }
        .stat-card.impresora .icon { color: #6f42c1; }
        .stat-card.camara { background-color: #d1f7e0; }
        .stat-card.camara .icon { color: #198754; }
        .stat-card.telefono { background-color: #fff3e0; }
        .stat-card.telefono .icon { color: #fd7e14; }
        
        .badge-estado {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .btn-accion {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .stat-card .icon { font-size: 1.5rem; }
            .stat-card .count { font-size: 1.2rem; }
            .stat-card .label { font-size: 0.7rem; }
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
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-pc-display-horizontal me-2"></i>Inventario de Equipos</h4>
                        <small class="text-muted">Gestión de equipos electrónicos</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/crear_equipo.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Agregar Equipo
                    </a>
                </div>

                <!-- Cards por tipo de equipo -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card computadora <?php echo $filtro_tipo === 'computadora' ? 'active' : ''; ?>" 
                             onclick="filtrarPorTipo('computadora')">
                            <div class="icon"><i class="bi bi-pc-display"></i></div>
                            <div class="count"><?php echo $contadores['computadora'] ?? 0; ?></div>
                            <div class="label">Computadoras</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card impresora <?php echo $filtro_tipo === 'impresora' ? 'active' : ''; ?>"
                             onclick="filtrarPorTipo('impresora')">
                            <div class="icon"><i class="bi bi-printer"></i></div>
                            <div class="count"><?php echo $contadores['impresora'] ?? 0; ?></div>
                            <div class="label">Impresoras</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card camara <?php echo $filtro_tipo === 'camara' ? 'active' : ''; ?>"
                             onclick="filtrarPorTipo('camara')">
                            <div class="icon"><i class="bi bi-camera-video"></i></div>
                            <div class="count"><?php echo $contadores['camara'] ?? 0; ?></div>
                            <div class="label">Cámaras</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card telefono <?php echo $filtro_tipo === 'telefono' ? 'active' : ''; ?>"
                             onclick="filtrarPorTipo('telefono')">
                            <div class="icon"><i class="bi bi-phone"></i></div>
                            <div class="count"><?php echo $contadores['telefono'] ?? 0; ?></div>
                            <div class="label">Teléfonos</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body py-2">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="computadora" <?php echo $filtro_tipo === 'computadora' ? 'selected' : ''; ?>>Computadora</option>
                                    <option value="impresora" <?php echo $filtro_tipo === 'impresora' ? 'selected' : ''; ?>>Impresora</option>
                                    <option value="camara" <?php echo $filtro_tipo === 'camara' ? 'selected' : ''; ?>>Cámara</option>
                                    <option value="telefono" <?php echo $filtro_tipo === 'telefono' ? 'selected' : ''; ?>>Teléfono</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="en_reparacion" <?php echo $filtro_estado === 'en_reparacion' ? 'selected' : ''; ?>>En Reparación</option>
                                    <option value="dado_de_baja" <?php echo $filtro_estado === 'dado_de_baja' ? 'selected' : ''; ?>>Dado de Baja</option>
                                </select>
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
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Buscar</label>
                                <input type="text" name="busqueda" class="form-control form-select-sm" 
                                       placeholder="Código, marca, modelo..." 
                                       value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small mb-1">Mostrar</label>
                                <select name="mostrar" class="form-select form-select-sm">
                                    <option value="10" <?php echo $registros_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $registros_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $registros_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Equipos -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                        <span class="fw-medium">
                            <i class="bi bi-list-ul me-1"></i>
                            Listado de Equipos
                            <span class="badge bg-secondary ms-1"><?php echo $total_registros; ?></span>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo</th>
                                    <th>Código</th>
                                    <th>Marca / Modelo</th>
                                    <th>Ubicación</th>
                                    <th>Departamento</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($equipos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                            No se encontraron equipos con los filtros seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $iconos = [
                                                    'computadora' => 'bi-pc-display text-primary',
                                                    'impresora' => 'bi-printer text-purple',
                                                    'camara' => 'bi-camera-video text-success',
                                                    'telefono' => 'bi-phone text-orange'
                                                ];
                                                $icono = $iconos[$equipo['tipo_equipo']] ?? 'bi-device';
                                                ?>
                                                <i class="bi <?php echo $icono; ?> fs-5"></i>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($equipo['codigo_interno']); ?></strong>
                                                <?php if ($equipo['numero_serie']): ?>
                                                    <br><small class="text-muted">S/N: <?php echo htmlspecialchars($equipo['numero_serie']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $marca_modelo = trim(($equipo['marca'] ?? '') . ' ' . ($equipo['modelo'] ?? ''));
                                                echo $marca_modelo ? htmlspecialchars($marca_modelo) : '<span class="text-muted">-</span>';
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($equipo['ubicacion']); ?></td>
                                            <td>
                                                <?php echo $equipo['departamento_nombre'] ? htmlspecialchars($equipo['departamento_nombre']) : '<span class="text-muted">Sin asignar</span>'; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $badges_estado = [
                                                    'activo' => 'bg-success',
                                                    'inactivo' => 'bg-secondary',
                                                    'en_reparacion' => 'bg-warning text-dark',
                                                    'dado_de_baja' => 'bg-danger'
                                                ];
                                                $nombres_estado = [
                                                    'activo' => 'Activo',
                                                    'inactivo' => 'Inactivo',
                                                    'en_reparacion' => 'En Reparación',
                                                    'dado_de_baja' => 'Dado de Baja'
                                                ];
                                                $badge_class = $badges_estado[$equipo['estado']] ?? 'bg-secondary';
                                                $estado_nombre = $nombres_estado[$equipo['estado']] ?? $equipo['estado'];
                                                ?>
                                                <span class="badge badge-estado <?php echo $badge_class; ?>">
                                                    <?php echo $estado_nombre; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/ver_equipo.php?id=<?php echo $equipo['id']; ?>" 
                                                       class="btn btn-outline-info btn-accion" title="Ver detalles">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/editar_equipo.php?id=<?php echo $equipo['id']; ?>" 
                                                       class="btn btn-outline-warning btn-accion" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger btn-accion" 
                                                            onclick="confirmarEliminar(<?php echo $equipo['id']; ?>, '<?php echo htmlspecialchars($equipo['codigo_interno'], ENT_QUOTES); ?>')"
                                                            title="Eliminar">
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
                    
                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <small class="text-muted">
                                    Mostrando <?php echo ($offset + 1); ?> - <?php echo min($offset + $registros_por_pagina, $total_registros); ?> 
                                    de <?php echo $total_registros; ?> equipos
                                </small>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php 
                                        $params_url = $_GET;
                                        unset($params_url['pagina']);
                                        $base_url = '?' . http_build_query($params_url) . '&pagina=';
                                        ?>
                                        
                                        <li class="page-item <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo $base_url . ($pagina_actual - 1); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo $base_url . $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $pagina_actual >= $total_paginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo $base_url . ($pagina_actual + 1); ?>">
                                                <i class="bi bi-chevron-right"></i>
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
    
    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el equipo <strong id="codigoEquipoEliminar"></strong>?</p>
                    <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="formEliminar" method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_equipo.php" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="equipo_id" id="equipoIdEliminar">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Filtrar por tipo al hacer clic en las tarjetas
        function filtrarPorTipo(tipo) {
            const url = new URL(window.location.href);
            const tipoActual = url.searchParams.get('tipo');
            
            if (tipoActual === tipo) {
                url.searchParams.delete('tipo');
            } else {
                url.searchParams.set('tipo', tipo);
            }
            url.searchParams.delete('pagina');
            
            window.location.href = url.toString();
        }
        
        // Confirmar eliminación
        function confirmarEliminar(id, codigo) {
            document.getElementById('equipoIdEliminar').value = id;
            document.getElementById('codigoEquipoEliminar').textContent = codigo;
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>