<?php
/**
 * Dashboard Principal - Inventario de EPP
 * Ubicación: dashboard/inventario_epp/inventario_epp.php
 * 
 * Estructura idéntica a dashboard/colaborativo/colaborativo.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

// Verificar permisos EPP
$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    establecer_alerta('error', 'No tienes acceso al módulo de Inventario de EPP.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento_nombre'];
$usuario_id = $_SESSION['usuario_id'];

// Vista activa
$vista = $_GET['vista'] ?? 'inventario';

// Filtros
$filtros = [
    'categoria'       => $_GET['categoria'] ?? '',
    'busqueda'        => $_GET['busqueda'] ?? '',
    'fecha_desde'     => $_GET['fecha_desde'] ?? '',
    'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
    'tipo_movimiento' => $_GET['tipo_movimiento'] ?? ''
];

// Obtener datos según vista
if ($vista === 'movimientos') {
    $datos_tabla = obtener_movimientos_epp($filtros);
} else {
    $datos_tabla = obtener_inventario_epp($filtros);
}

// Estadísticas
$stats = obtener_estadisticas_epp();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario EPP - <?php echo htmlspecialchars($departamento); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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
        /* Tabla editable estilo Excel */
        .tabla-inventario { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; }
        .tabla-inventario thead th { background: #2c3e50; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #34495e; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .tabla-inventario tbody td { padding: 6px 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .tabla-inventario tbody tr:hover { background: #f0f7ff; }
        .tabla-inventario tbody tr:nth-child(even) { background: #f8fafc; }
        .tabla-inventario tbody tr:nth-child(even):hover { background: #f0f7ff; }
        .celda-editable { cursor: text; min-width: 80px; }
        .celda-editable:hover { background: #fffde7 !important; outline: 2px solid #ffc107; outline-offset: -2px; }
        .celda-editable:focus-within { background: #fff9c4 !important; outline: 2px solid #ff9800; outline-offset: -2px; }
        .celda-editable input, .celda-editable select { border: none; background: transparent; width: 100%; padding: 0; font-size: 0.85rem; outline: none; }
        .stock-badge { font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; }
        .stock-ok { background: #d4edda; color: #155724; }
        .stock-bajo { background: #fff3cd; color: #856404; }
        .stock-cero { background: #f8d7da; color: #721c24; }
        .badge-entrada { background: #28a745; }
        .badge-salida { background: #dc3545; }
        .btn-accion { padding: 2px 6px; font-size: 0.75rem; border-radius: 4px; }
        .save-indicator { position: fixed; top: 20px; right: 20px; z-index: 9999; display: none; }
        
        /* Vista tabs */
        .vista-tabs .nav-link { color: #6c757d; font-weight: 600; border-bottom: 3px solid transparent; border-radius: 0; padding: 8px 20px; }
        .vista-tabs .nav-link.active { color: #198754; border-bottom-color: #198754; background: transparent; }
        .vista-tabs .nav-link:hover:not(.active) { color: #333; border-bottom-color: #ddd; }
        
        /* Filtros */
        .filtros-bar .form-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #888; margin-bottom: 3px; }
        .filtros-bar .form-select, .filtros-bar .form-control { font-size: 0.82rem; padding: 5px 10px; }
    </style>
</head>
<body>
    
    <!-- Indicador de guardado -->
    <div class="save-indicator" id="saveIndicator">
        <div class="alert alert-success py-2 px-3 shadow" style="font-size: 0.85rem;">
            <i class="bi bi-check-circle"></i> <span id="saveMessage">Guardado</span>
        </div>
    </div>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_inventario.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Navbar superior -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">&iexcl;Bienvenido, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar3"></i> 
                            <?php echo obtener_fecha_actual_espanol(); ?>
                        </p>
                    </div>
                    <div class="user-info">
                        <span class="user-badge">
                            <i class="bi bi-shield-check"></i>
                            <?php echo htmlspecialchars($departamento); ?>
                        </span>
                    </div>
                </div>

                <?php echo mostrar_alerta(); ?>

                <!-- Tarjetas de estadísticas -->
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-info mx-auto mb-3">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['total_articulos']; ?></h2>
                                <p class="stats-label">Art&iacute;culos</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-success mx-auto mb-3">
                                    <i class="bi bi-check2-all"></i>
                                </div>
                                <h2 class="stats-number"><?php echo number_format($stats['total_stock']); ?></h2>
                                <p class="stats-label">Stock Total</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box icon-box-warning mx-auto mb-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['sin_stock']; ?></h2>
                                <p class="stats-label">Sin Stock</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-custom card-stats">
                            <div class="card-body">
                                <div class="icon-box mx-auto mb-3" style="background-color: #e3e6f0; color: #6c757d;">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <h2 class="stats-number"><?php echo $stats['movimientos_mes']; ?></h2>
                                <p class="stats-label">Movimientos (Mes)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <?php if ($permisos['puede_crear']): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-lightning-charge"></i> Acciones R&aacute;pidas
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/agregar_epp.php" class="btn btn-gradient">
                                        <i class="bi bi-plus-circle"></i> Agregar EPP
                                    </a>
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/registrar_movimiento.php" class="btn btn-success">
                                        <i class="bi bi-arrow-left-right"></i> Registrar Movimiento
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Inventario / Movimientos -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card card-custom">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <!-- Tabs de Vista -->
                                <ul class="nav vista-tabs mb-0">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $vista === 'inventario' ? 'active' : ''; ?>" href="?vista=inventario">
                                            <i class="bi bi-box-seam"></i> Inventario
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $vista === 'movimientos' ? 'active' : ''; ?>" href="?vista=movimientos">
                                            <i class="bi bi-arrow-left-right"></i> Movimientos
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                
                                <!-- Filtros -->
                                <form method="GET" class="filtros-bar mb-3 p-3 bg-light rounded border">
                                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Categor&iacute;a de EPP</label>
                                            <select name="categoria" class="form-select">
                                                <option value="">Todas</option>
                                                <?php foreach (EPP_CATEGORIAS as $cat): ?>
                                                <option value="<?php echo $cat; ?>" <?php echo $filtros['categoria'] === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Fecha Desde</label>
                                            <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Fecha Hasta</label>
                                            <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                        </div>
                                        <?php if ($vista === 'movimientos'): ?>
                                        <div class="col-md-2">
                                            <label class="form-label">Tipo</label>
                                            <select name="tipo_movimiento" class="form-select">
                                                <option value="">Todos</option>
                                                <option value="Entrada" <?php echo $filtros['tipo_movimiento'] === 'Entrada' ? 'selected' : ''; ?>>Entrada</option>
                                                <option value="Salida" <?php echo $filtros['tipo_movimiento'] === 'Salida' ? 'selected' : ''; ?>>Salida</option>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-1">
                                                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                                                <a href="?vista=<?php echo $vista; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                                <!-- VISTA: INVENTARIO -->
                                <?php if ($vista === 'inventario'): ?>
                                <div class="table-responsive-custom">
                                    <table class="tabla-inventario">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;">#</th>
                                                <th>Art&iacute;culo</th>
                                                <th>Unidad</th>
                                                <th style="width: 100px;">Stock</th>
                                                <?php if ($permisos['puede_editar']): ?>
                                                <th style="width: 100px;">Acciones</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($datos_tabla)): ?>
                                            <tr><td colspan="<?php echo $permisos['puede_editar'] ? 5 : 4; ?>" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron art&iacute;culos.
                                            </td></tr>
                                            <?php else: ?>
                                            <?php foreach ($datos_tabla as $index => $item): ?>
                                            <tr data-id="<?php echo $item['id']; ?>">
                                                <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                                <td class="<?php echo $permisos['puede_editar'] ? 'celda-editable' : ''; ?>">
                                                    <?php if ($permisos['puede_editar']): ?>
                                                    <input type="text" value="<?php echo htmlspecialchars($item['articulo']); ?>" data-original="<?php echo htmlspecialchars($item['articulo']); ?>" onchange="guardarCampo(<?php echo $item['id']; ?>, 'articulo', this.value, this)">
                                                    <?php else: ?>
                                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $item['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($item['articulo']); ?></a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="<?php echo $permisos['puede_editar'] ? 'celda-editable' : ''; ?>">
                                                    <?php if ($permisos['puede_editar']): ?>
                                                    <select onchange="guardarCampo(<?php echo $item['id']; ?>, 'unidad', this.value, this)" data-original="<?php echo $item['unidad']; ?>">
                                                        <option value="Pieza(s)" <?php echo $item['unidad'] === 'Pieza(s)' ? 'selected' : ''; ?>>Pieza(s)</option>
                                                        <option value="Lote" <?php echo $item['unidad'] === 'Lote' ? 'selected' : ''; ?>>Lote</option>
                                                        <option value="PAR" <?php echo $item['unidad'] === 'PAR' ? 'selected' : ''; ?>>PAR</option>
                                                    </select>
                                                    <?php else: echo htmlspecialchars($item['unidad']); endif; ?>
                                                </td>
                                                <td class="<?php echo $permisos['puede_editar'] ? 'celda-editable' : ''; ?> text-center">
                                                    <?php if ($permisos['puede_editar']): ?>
                                                    <input type="number" min="0" value="<?php echo $item['stock']; ?>" style="text-align:center;width:70px;" data-original="<?php echo $item['stock']; ?>" onchange="guardarCampo(<?php echo $item['id']; ?>, 'stock', this.value, this)">
                                                    <?php else:
                                                        $sc = $item['stock'] == 0 ? 'stock-cero' : ($item['stock'] <= 5 ? 'stock-bajo' : 'stock-ok');
                                                    ?>
                                                    <span class="stock-badge <?php echo $sc; ?>"><?php echo $item['stock']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($permisos['puede_editar']): ?>
                                                <td class="text-center">
                                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-info btn-accion" title="Ver"><i class="bi bi-eye"></i></a>
                                                    <button type="button" class="btn btn-outline-danger btn-accion" title="Eliminar" onclick="eliminarEPP(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['articulo'], ENT_QUOTES); ?>')"><i class="bi bi-trash"></i></button>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted mt-2 mb-0" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle"></i> <?php echo count($datos_tabla); ?> art&iacute;culo(s) encontrado(s)
                                    <?php if ($permisos['puede_editar']): ?> &mdash; Haz clic en cualquier celda para editarla.<?php endif; ?>
                                </p>
                                <?php endif; ?>
                                
                                <!-- VISTA: MOVIMIENTOS -->
                                <?php if ($vista === 'movimientos'): ?>
                                <div class="table-responsive-custom">
                                    <table class="tabla-inventario">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Categor&iacute;a</th>
                                                <th>Art&iacute;culo</th>
                                                <th>Talla</th>
                                                <th style="width:90px;">Cantidad</th>
                                                <th style="width:80px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($datos_tabla)): ?>
                                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron movimientos.</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($datos_tabla as $index => $mov): ?>
                                            <tr>
                                                <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                                <td><span class="badge <?php echo $mov['tipo_movimiento'] === 'Entrada' ? 'badge-entrada' : 'badge-salida'; ?>"><i class="bi <?php echo $mov['tipo_movimiento'] === 'Entrada' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle'; ?>"></i> <?php echo $mov['tipo_movimiento']; ?></span></td>
                                                <td><?php echo htmlspecialchars($mov['categoria']); ?></td>
                                                <td><?php echo htmlspecialchars($mov['articulo']); ?></td>
                                                <td><?php echo $mov['talla'] ? htmlspecialchars($mov['talla']) : '<span class="text-muted">-</span>'; ?></td>
                                                <td class="text-center fw-bold"><?php echo $mov['cantidad']; ?></td>
                                                <td class="text-center">
                                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_movimiento.php?id=<?php echo $mov['id']; ?>" class="btn btn-outline-info btn-accion" title="Ver"><i class="bi bi-eye"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted mt-2 mb-0" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle"></i> <?php echo count($datos_tabla); ?> movimiento(s) encontrado(s)
                                </p>
                                <?php endif; ?>
                                
                            </div>
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
            setTimeout(() => { themeToggle.classList.remove('rotating'); }, 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
    
    <?php if ($permisos['puede_editar']): ?>
    <script>
    function guardarCampo(id, campo, valor, el) {
        if (valor === el.dataset.original) return;
        fetch('<?php echo URL_BASE; ?>dashboard/inventario_epp/api_inventario_epp.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({accion: 'actualizar_campo', id: id, campo: campo, valor: valor})
        }).then(r => r.json()).then(data => {
            if (data.success) { el.dataset.original = valor; mostrarIndicador('Guardado correctamente', 'success'); }
            else { el.value = el.dataset.original; mostrarIndicador(data.message || 'Error al guardar', 'danger'); }
        }).catch(() => { el.value = el.dataset.original; mostrarIndicador('Error de conexión', 'danger'); });
    }
    function eliminarEPP(id, nombre) {
        if (!confirm('¿Estás seguro de eliminar "' + nombre + '" del inventario?')) return;
        fetch('<?php echo URL_BASE; ?>dashboard/inventario_epp/api_inventario_epp.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({accion: 'eliminar', id: id})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const fila = document.querySelector('tr[data-id="'+id+'"]');
                if (fila) { fila.style.transition='opacity 0.3s'; fila.style.opacity='0'; setTimeout(()=>fila.remove(),300); }
                mostrarIndicador('Artículo eliminado', 'success');
            } else mostrarIndicador(data.message || 'Error al eliminar', 'danger');
        }).catch(() => mostrarIndicador('Error de conexión', 'danger'));
    }
    function mostrarIndicador(msg, tipo) {
        const ind = document.getElementById('saveIndicator'), m = document.getElementById('saveMessage');
        m.textContent = msg; ind.querySelector('.alert').className = 'alert alert-'+tipo+' py-2 px-3 shadow';
        ind.style.display = 'block'; setTimeout(() => ind.style.display = 'none', 2500);
    }
    </script>
    <?php endif; ?>
</body>
</html>