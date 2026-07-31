<?php
/**
 * Registrar Movimiento de EPP
 * VERSIÓN 2.0 - Selección obligatoria de talla
 * Ubicación: dashboard/inventario_epp/registrar_movimiento.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['puede_crear']) {
    establecer_alerta('error', 'No tienes permiso para registrar movimientos.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

$page_title = "Registrar Movimiento";
$errores = [];
$datos = [];

// Obtener artículos con tallas para el dropdown
$articulos_disponibles = obtener_articulos_dropdown_epp();

// Agrupar por artículo para el JS
$articulos_agrupados = [];
foreach ($articulos_disponibles as $art) {
    $key = $art['id'];
    if (!isset($articulos_agrupados[$key])) {
        $articulos_agrupados[$key] = [
            'id' => $art['id'],
            'categoria' => $art['categoria'],
            'articulo' => $art['articulo'],
            'unidad' => $art['unidad'],
            'stock_total' => $art['stock_total'],
            'tallas' => []
        ];
    }
    $articulos_agrupados[$key]['tallas'][] = [
        'talla_id' => $art['talla_id'],
        'talla' => $art['talla'],
        'stock' => $art['talla_stock']
    ];
}

$preselect_id = $_GET['epp_id'] ?? '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $datos['inventario_epp_id'] = (int) ($_POST['inventario_epp_id'] ?? 0);
    $datos['talla_id']          = (int) ($_POST['talla_id'] ?? 0);
    $datos['tipo_movimiento']   = trim($_POST['tipo_movimiento'] ?? '');
    $datos['fecha_movimiento']  = $_POST['fecha_movimiento'] ?? date('Y-m-d H:i:s');
    $datos['cantidad']          = (int) ($_POST['cantidad'] ?? 0);
    $datos['nombre_trabajador'] = trim($_POST['nombre_trabajador'] ?? '');
    $datos['observaciones']     = trim($_POST['observaciones'] ?? '');
    
    if (!$datos['inventario_epp_id']) $errores[] = "Debe seleccionar un artículo.";
    if (!$datos['talla_id']) $errores[] = "Debe seleccionar una talla.";
    if (empty($datos['tipo_movimiento']) || !in_array($datos['tipo_movimiento'], ['Entrada', 'Salida'])) $errores[] = "Tipo de movimiento no válido.";
    if ($datos['cantidad'] < 1) $errores[] = "La cantidad debe ser al menos 1.";
    
    if (empty($errores)) {
        $datos['usuario_id'] = $_SESSION['usuario_id'];
        $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
        $datos['departamento'] = $_SESSION['departamento'];
        
        $resultado = registrar_movimiento_epp($datos);
        
        if ($resultado['success']) {
            establecer_alerta('success', $resultado['message']);
            header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php?vista=movimientos');
            exit;
        } else {
            $errores[] = $resultado['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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
    <style>
        .form-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1.5rem; max-width: 700px; }
        .form-card .form-label { font-size: 0.8rem; font-weight: 600; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; }
        .stock-info { background: #f0f7ff; border: 1px solid #b3d9ff; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.85rem; display: none; }
        .stock-info.visible { display: block; }
        .campo-trabajador { display: none; }
        .talla-select-wrapper { display: none; }
        .talla-select-wrapper.visible { display: block; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;"><i class="bi bi-arrow-left-right text-primary"></i> <?php echo $page_title; ?></h2>
                        <small class="text-muted">Registrar entrada de EPP al inventario</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=movimientos" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver a Movimientos
                    </a>
                </div>
                
                <?php echo mostrar_alerta(); ?>
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;">
                        <i class="bi bi-exclamation-circle"></i>
                        <?php foreach ($errores as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-card">
                    <form method="POST" id="formMovimiento">
                        
                        <div class="mb-3">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_movimiento" class="form-control" required
                                   value="<?php echo $datos['fecha_movimiento'] ?? date('Y-m-d\TH:i'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento</label>
                            <input type="hidden" name="tipo_movimiento" value="Entrada">
                            <div class="form-control bg-light" style="pointer-events:none;">
                                <i class="bi bi-box-arrow-in-down text-success"></i> <strong>Entrada</strong>
                            </div>
                        </div>
                        
                        <!-- Artículo (agrupado, sin tallas en el dropdown) -->
                        <div class="mb-3">
                            <label class="form-label">Artículo del Inventario <span class="text-danger">*</span></label>
                            <select name="inventario_epp_id" id="selectArticulo" class="form-select" required>
                                <option value="">Seleccione un artículo</option>
                                <?php foreach ($articulos_agrupados as $art): ?>
                                <option value="<?php echo $art['id']; ?>"
                                        <?php echo ($preselect_id == $art['id'] || ($datos['inventario_epp_id'] ?? '') == $art['id']) ? 'selected' : ''; ?>>
                                    [<?php echo $art['categoria']; ?>] <?php echo $art['articulo']; ?> — Stock total: <?php echo $art['stock_total']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Selector de Talla (aparece al elegir artículo) -->
                        <div class="mb-3 talla-select-wrapper" id="tallaWrapper">
                            <label class="form-label">Talla <span class="text-danger">*</span></label>
                            <select name="talla_id" id="selectTalla" class="form-select" required>
                                <option value="">Seleccione una talla</option>
                            </select>
                        </div>
                        
                        <!-- Info del stock -->
                        <div class="stock-info mb-3" id="stockInfo">
                            <i class="bi bi-box-seam"></i> 
                            <strong>Stock de esta talla:</strong> <span id="stockTallaDisplay">-</span> |
                            <strong>Artículo:</strong> <span id="articuloInfo">-</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" name="cantidad" class="form-control" min="1" required placeholder="0"
                                   value="<?php echo $datos['cantidad'] ?? ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"
                                      placeholder="Observaciones del movimiento..."><?php echo htmlspecialchars($datos['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Enviar</button>
                        </div>
                        
                    </form>
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <script>
    const articulosData = <?php echo json_encode(array_values($articulos_agrupados), JSON_UNESCAPED_UNICODE); ?>;
    
    const selectArticulo = document.getElementById('selectArticulo');
    const selectTalla = document.getElementById('selectTalla');
    const tallaWrapper = document.getElementById('tallaWrapper');
    const stockInfo = document.getElementById('stockInfo');
    
    selectArticulo.addEventListener('change', function() {
        selectTalla.innerHTML = '<option value="">Seleccione una talla</option>';
        stockInfo.classList.remove('visible');
        
        if (!this.value) {
            tallaWrapper.classList.remove('visible');
            return;
        }
        
        const art = articulosData.find(a => a.id == this.value);
        if (!art) return;
        
        art.tallas.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.talla_id;
            opt.textContent = t.talla + ' — Stock: ' + t.stock;
            opt.dataset.stock = t.stock;
            opt.dataset.talla = t.talla;
            selectTalla.appendChild(opt);
        });
        
        tallaWrapper.classList.add('visible');
        
        if (art.tallas.length === 1) {
            selectTalla.selectedIndex = 1;
            selectTalla.dispatchEvent(new Event('change'));
        }
    });
    
    selectTalla.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (this.value && opt.dataset.stock !== undefined) {
            document.getElementById('stockTallaDisplay').textContent = opt.dataset.stock + ' unidades';
            const art = articulosData.find(a => a.id == selectArticulo.value);
            document.getElementById('articuloInfo').textContent = art ? art.articulo + ' — Talla: ' + opt.dataset.talla : '-';
            stockInfo.classList.add('visible');
        } else {
            stockInfo.classList.remove('visible');
        }
    });
    
    if (selectArticulo.value) selectArticulo.dispatchEvent(new Event('change'));
    </script>
</body>
</html>