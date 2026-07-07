<?php
/**
 * Dashboard Principal - Inventario de EPP
 * VERSIÓN 2.1 - Tallas como dropdown dinámico (1 fila por artículo)
 * Ubicación: dashboard/inventario_epp/inventario_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$puede_eliminar = $permisos['puede_editar'] && $depto_codigo !== 'almacen_refacciones';
if (!$permisos['tiene_acceso']) {
    establecer_alerta('error', 'No tienes acceso al módulo de Inventario de EPP.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$vista = $_GET['vista'] ?? 'inventario';

$filtros = [
    'categoria'       => $_GET['categoria'] ?? '',
    'busqueda'        => $_GET['busqueda'] ?? '',
    'fecha_desde'     => $_GET['fecha_desde'] ?? '',
    'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
    'tipo_movimiento' => $_GET['tipo_movimiento'] ?? ''
];

if ($vista === 'movimientos') {
    $datos_tabla = obtener_movimientos_epp($filtros);
    $page_title = "Inventario de EPP - Movimientos";
} else {
    $datos_tabla = obtener_inventario_epp_compacto($filtros);
    $page_title = "Inventario de EPP";
}

$stats = obtener_estadisticas_epp();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
        .tabla-inventario-wrapper { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tabla-inventario { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; }
        .tabla-inventario thead th { background: #2c3e50; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #34495e; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .tabla-inventario tbody td { padding: 6px 10px; border: 1px solid #e2e8f0; vertical-align: middle; transition: background 0.15s; }
        .tabla-inventario tbody tr:hover { background: #f0f7ff; }
        .tabla-inventario tbody tr:nth-child(even) { background: #f8fafc; }
        .tabla-inventario tbody tr:nth-child(even):hover { background: #f0f7ff; }
        .celda-editable { cursor: text; position: relative; min-width: 80px; }
        .celda-editable:hover { background: #fffde7 !important; outline: 2px solid #ffc107; outline-offset: -2px; }
        .celda-editable:focus-within { background: #fff9c4 !important; outline: 2px solid #ff9800; outline-offset: -2px; }
        .celda-editable input, .celda-editable select { border: none; background: transparent; width: 100%; padding: 0; font-size: 0.85rem; outline: none; }
        .stock-badge { font-weight: 700; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; display: inline-block; min-width: 40px; text-align: center; }
        .stock-ok { background: #d4edda; color: #155724; }
        .stock-bajo { background: #fff3cd; color: #856404; }
        .stock-cero { background: #f8d7da; color: #721c24; }
        .badge-entrada { background: #28a745; }
        .badge-salida { background: #dc3545; }
        .badge-talla { background: #e8f4fd; color: #1565c0; font-weight: 600; padding: 3px 10px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #b3d9ff; }
        .badge-talla-unica { background: #f1f3f5; color: #6c757d; border-color: #dee2e6; }
        .vista-tabs .nav-link { color: #6c757d; font-weight: 600; border-bottom: 3px solid transparent; border-radius: 0; padding: 0.5rem 1rem; }
        .vista-tabs .nav-link.active { color: #2c3e50; border-bottom-color: #2c3e50; background: transparent; }
        .vista-tabs .nav-link:hover:not(.active) { color: #495057; border-bottom-color: #dee2e6; }
        .stat-card-mini { padding: 0.75rem 1rem; border-radius: 8px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); text-align: center; }
        .stat-card-mini .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .stat-card-mini .stat-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; letter-spacing: 0.3px; }
        .filtros-bar { background: #fff; padding: 0.75rem 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1rem; }
        .filtros-bar .form-control, .filtros-bar .form-select { font-size: 0.8rem; padding: 0.3rem 0.5rem; }
        .filtros-bar .form-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c757d; margin-bottom: 0.15rem; }
        .btn-accion { padding: 0.15rem 0.4rem; font-size: 0.75rem; border-radius: 4px; }
        .save-indicator { position: fixed; top: 20px; right: 20px; z-index: 9999; display: none; }
        /* Select de talla compacto */
        .select-talla { font-size: 0.8rem; padding: 0.2rem 0.4rem; border: 1px solid #b3d9ff; border-radius: 4px; background: #e8f4fd; color: #1565c0; font-weight: 600; cursor: pointer; max-width: 100px; }
        .select-talla:focus { outline: 2px solid #1565c0; }
        /* Stock dinámico con transición */
        .stock-display { transition: all 0.2s ease; }
        .stock-display.updating { transform: scale(1.15); }
    </style>
</head>
<body>
    <div class="save-indicator" id="saveIndicator">
        <div class="alert alert-success alert-dismissible py-2 px-3 shadow" style="font-size: 0.85rem;">
            <i class="bi bi-check-circle"></i> <span id="saveMessage">Guardado</span>
        </div>
    </div>

    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.4rem;">
                            <i class="bi bi-shield-check text-success"></i> Inventario de EPP
                        </h2>
                        <small class="text-muted">Equipo de Protección Personal</small>
                    </div>
                    <?php if ($permisos['puede_crear']): ?>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/agregar_epp.php" class="btn btn-success btn-sm">
                            <i class="bi bi-plus-circle"></i> Agregar EPP
                        </a>
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/registrar_movimiento.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-left-right"></i> Registrar Movimiento
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php echo mostrar_alerta(); ?>
                
                <!-- Estadísticas -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-primary"><?php echo $stats['total_articulos']; ?></div><div class="stat-label">Artículos</div></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-success"><?php echo number_format($stats['total_stock']); ?></div><div class="stat-label">Stock Total</div></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-danger"><?php echo $stats['sin_stock']; ?></div><div class="stat-label">Sin Stock</div></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-info"><?php echo $stats['movimientos_mes']; ?></div><div class="stat-label">Movimientos (Mes)</div></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-success"><?php echo $stats['entradas_mes']; ?></div><div class="stat-label">Entradas (Mes)</div></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card-mini"><div class="stat-value text-danger"><?php echo $stats['salidas_mes']; ?></div><div class="stat-label">Salidas (Mes)</div></div></div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav vista-tabs mb-3">
                    <li class="nav-item"><a class="nav-link <?php echo $vista === 'inventario' ? 'active' : ''; ?>" href="?vista=inventario"><i class="bi bi-box-seam"></i> Inventario</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $vista === 'movimientos' ? 'active' : ''; ?>" href="?vista=movimientos"><i class="bi bi-arrow-left-right"></i> Movimientos</a></li>
                </ul>
                
                <!-- Filtros -->
                <form method="GET" class="filtros-bar">
                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($vista); ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Categoría de EPP</label>
                            <select name="categoria" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach (EPP_CATEGORIAS as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo $filtros['categoria'] === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><label class="form-label">Fecha Desde</label><input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>"></div>
                        <div class="col-md-2"><label class="form-label">Fecha Hasta</label><input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>"></div>
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
                
                <!-- ============================================ -->
                <!-- VISTA: INVENTARIO -->
                <!-- ============================================ -->
                <?php if ($vista === 'inventario'): ?>
                <div class="tabla-inventario-wrapper">
                    <table class="tabla-inventario" id="tablaInventario">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Artículo</th>
                                <th style="width: 120px;">Talla</th>
                                <th>Unidad</th>
                                <th style="width: 100px;">Stock</th>
                                <?php if ($permisos['puede_editar']): ?>
                                <th style="width: 100px;">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($datos_tabla)): ?>
                            <tr>
                                <td colspan="<?php echo $permisos['puede_editar'] ? 6 : 5; ?>" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i> No se encontraron artículos.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($datos_tabla as $index => $item): 
                                $tallas = $item['tallas_data'] ?? [];
                                $tiene_multiples_tallas = (count($tallas) > 1) || (count($tallas) === 1 && $tallas[0]['talla'] !== 'Única');
                                $primera_talla = $tallas[0] ?? null;
                                $stock_inicial = $primera_talla ? (int)$primera_talla['stock'] : 0;
                                $sc = $stock_inicial == 0 ? 'stock-cero' : ($stock_inicial <= 5 ? 'stock-bajo' : 'stock-ok');
                            ?>
                            <tr data-id="<?php echo $item['id']; ?>">
                                <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                
                                <td>
                                    <?php if ($permisos['puede_editar']): ?>
                                    <div class="celda-editable">
                                        <input type="text" value="<?php echo htmlspecialchars($item['articulo']); ?>" 
                                               data-original="<?php echo htmlspecialchars($item['articulo']); ?>"
                                               onchange="guardarCampo(<?php echo $item['id']; ?>, 'articulo', this.value, this)">
                                    </div>
                                    <?php else: ?>
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($item['articulo']); ?>
                                    </a>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Talla: dropdown si tiene múltiples, badge si Única -->
                                <td class="text-center">
                                    <?php if ($tiene_multiples_tallas): ?>
                                    <select class="select-talla" onchange="cambiarTalla(this, <?php echo $item['id']; ?>)">
                                        <?php foreach ($tallas as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" data-stock="<?php echo $t['stock']; ?>">
                                            <?php echo htmlspecialchars($t['talla']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <span class="badge-talla badge-talla-unica">Única</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($permisos['puede_editar']): ?>
                                    <div class="celda-editable">
                                        <select onchange="guardarCampo(<?php echo $item['id']; ?>, 'unidad', this.value, this)" data-original="<?php echo $item['unidad']; ?>">
                                            <option value="Pieza(s)" <?php echo $item['unidad'] === 'Pieza(s)' ? 'selected' : ''; ?>>Pieza(s)</option>
                                            <option value="Lote" <?php echo $item['unidad'] === 'Lote' ? 'selected' : ''; ?>>Lote</option>
                                            <option value="PAR" <?php echo $item['unidad'] === 'PAR' ? 'selected' : ''; ?>>PAR</option>
                                        </select>
                                    </div>
                                    <?php else: echo htmlspecialchars($item['unidad']); endif; ?>
                                </td>
                                
                                <!-- Stock: editable, se actualiza al cambiar talla -->
                                <td class="<?php echo $permisos['puede_editar'] ? 'celda-editable' : ''; ?> text-center">
                                    <?php if ($permisos['puede_editar']): ?>
                                    <input type="number" min="0" 
                                           value="<?php echo $stock_inicial; ?>" 
                                           style="text-align:center;width:70px;"
                                           data-original="<?php echo $stock_inicial; ?>"
                                           data-talla-id="<?php echo $primera_talla ? $primera_talla['id'] : 0; ?>"
                                           id="stock-<?php echo $item['id']; ?>"
                                           onchange="guardarStockTalla(this, <?php echo $item['id']; ?>)">
                                    <?php else: ?>
                                    <span class="stock-badge stock-display <?php echo $sc; ?>" id="stock-<?php echo $item['id']; ?>">
                                        <?php echo $stock_inicial; ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if ($permisos['puede_editar']): ?>
                                <td class="text-center">
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $item['id']; ?>" 
                                       class="btn btn-outline-info btn-accion" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                    <?php if ($puede_eliminar): ?>
                                    <button type="button" class="btn btn-outline-danger btn-accion" title="Eliminar" 
                                            onclick="eliminarEPP(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['articulo'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle"></i> <?php echo count($datos_tabla); ?> artículo(s) &mdash; Selecciona una talla para ver su stock.
                </div>
                <?php endif; ?>
                
                <!-- ============================================ -->
                <!-- VISTA: MOVIMIENTOS -->
                <!-- ============================================ -->
                <?php if ($vista === 'movimientos'): ?>
                <div class="tabla-inventario-wrapper">
                    <table class="tabla-inventario" id="tablaMovimientos">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Categoría</th>
                                <th>Artículo</th>
                                <th>Talla</th>
                                <th style="width: 90px;">Cantidad</th>
                                <th style="width: 80px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($datos_tabla)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron movimientos.</td></tr>
                            <?php else: ?>
                            <?php foreach ($datos_tabla as $i => $mov): ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $i + 1; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                <td><span class="badge <?php echo $mov['tipo_movimiento'] === 'Entrada' ? 'badge-entrada' : 'badge-salida'; ?>"><i class="bi <?php echo $mov['tipo_movimiento'] === 'Entrada' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle'; ?>"></i> <?php echo $mov['tipo_movimiento']; ?></span></td>
                                <td><?php echo htmlspecialchars($mov['categoria']); ?></td>
                                <td><?php echo htmlspecialchars($mov['articulo']); ?></td>
                                <td><?php if ($mov['talla']): ?><span class="badge-talla <?php echo $mov['talla'] === 'Única' ? 'badge-talla-unica' : ''; ?>"><?php echo htmlspecialchars($mov['talla']); ?></span><?php else: ?>-<?php endif; ?></td>
                                <td class="text-center fw-bold"><?php echo $mov['cantidad']; ?></td>
                                <td class="text-center"><a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_movimiento.php?id=<?php echo $mov['id']; ?>" class="btn btn-outline-info btn-accion" title="Ver"><i class="bi bi-eye"></i></a></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> <?php echo count($datos_tabla); ?> movimiento(s)</div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
    function cambiarTalla(selectEl, articuloId) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const stock = parseInt(opt.dataset.stock) || 0;
        const tallaId = opt.value;
        const stockEl = document.getElementById('stock-' + articuloId);
        if (!stockEl) return;
        
        // Si es input (editor), actualizar value y talla_id
        if (stockEl.tagName === 'INPUT') {
            stockEl.value = stock;
            stockEl.dataset.original = stock;
            stockEl.dataset.tallaId = tallaId;
        } else {
            // Si es span (lector)
            stockEl.textContent = stock;
            stockEl.className = 'stock-badge stock-display ' + 
                (stock === 0 ? 'stock-cero' : (stock <= 5 ? 'stock-bajo' : 'stock-ok'));
        }
    }
    
    function guardarStockTalla(inputEl, articuloId) {
        const nuevoStock = inputEl.value;
        const tallaId = inputEl.dataset.tallaId;
        if (nuevoStock === inputEl.dataset.original || !tallaId || tallaId === '0') return;
        
        fetch('<?php echo URL_BASE; ?>dashboard/inventario_epp/api_inventario_epp.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({accion: 'actualizar_stock_talla', talla_id: parseInt(tallaId), stock: parseInt(nuevoStock)})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                inputEl.dataset.original = nuevoStock;
                // Actualizar también el data-stock en el option del select de talla
                const row = inputEl.closest('tr');
                const selectTalla = row ? row.querySelector('.select-talla') : null;
                if (selectTalla) {
                    const opt = selectTalla.options[selectTalla.selectedIndex];
                    if (opt) opt.dataset.stock = nuevoStock;
                }
                mostrarIndicador('Stock actualizado', 'success');
            } else {
                inputEl.value = inputEl.dataset.original;
                mostrarIndicador(data.message || 'Error al guardar', 'danger');
            }
        }).catch(() => { inputEl.value = inputEl.dataset.original; mostrarIndicador('Error de conexión', 'danger'); });
    }
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
            else { el.value = el.dataset.original; mostrarIndicador(data.message || 'Error', 'danger'); }
        }).catch(() => { el.value = el.dataset.original; mostrarIndicador('Error de conexión', 'danger'); });
    }
    function eliminarEPP(id, nombre) {
        if (!confirm('¿Eliminar "' + nombre + '" y todas sus tallas?')) return;
        fetch('<?php echo URL_BASE; ?>dashboard/inventario_epp/api_inventario_epp.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({accion: 'eliminar', id: id})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const f = document.querySelector('tr[data-id="'+id+'"]');
                if (f) { f.style.transition='opacity 0.3s'; f.style.opacity='0'; setTimeout(()=>f.remove(),300); }
                mostrarIndicador('Eliminado', 'success');
            } else mostrarIndicador(data.message || 'Error', 'danger');
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