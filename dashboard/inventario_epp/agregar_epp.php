<?php
/**
 * Agregar EPP al Inventario
 * VERSIÓN 2.3 - Toggle EPP Existente / EPP Nuevo + Agregar nueva talla
 * Ubicación: dashboard/inventario_epp/agregar_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['puede_crear']) {
    establecer_alerta('error', 'No tienes permiso para agregar artículos al inventario.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

$page_title = "Agregar EPP";
$errores = [];
$datos = [];

// Obtener artículos existentes para el dropdown
$articulos_existentes = obtener_inventario_epp_compacto([]);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $modo = $_POST['modo_agregar'] ?? 'nuevo';
    $datos['fecha_movimiento'] = $_POST['fecha_movimiento'] ?? date('Y-m-d H:i:s');
    $datos['observaciones'] = trim($_POST['observaciones'] ?? '');
    
    // ============================================
    // MODO: EPP EXISTENTE
    // ============================================
    if ($modo === 'existente') {
        $datos['inventario_epp_id'] = (int) ($_POST['inventario_epp_id'] ?? 0);
        $datos['cantidad'] = (int) ($_POST['cantidad_existente'] ?? 0);
        $es_nueva_talla = ($_POST['es_nueva_talla'] ?? '0') === '1';
        $datos['nueva_talla_nombre'] = trim($_POST['nueva_talla_nombre'] ?? '');
        
        if ($es_nueva_talla) {
            $datos['talla_id'] = 0;
            if (empty($datos['nueva_talla_nombre'])) $errores[] = "Ingrese el nombre de la nueva talla.";
        } else {
            $datos['talla_id'] = (int) ($_POST['talla_id'] ?? 0);
            if (!$datos['talla_id']) $errores[] = "Debe seleccionar una talla.";
        }
        
        if (!$datos['inventario_epp_id']) $errores[] = "Debe seleccionar un artículo.";
        if ($datos['cantidad'] < 1) $errores[] = "La cantidad debe ser al menos 1.";
        
        if (empty($errores)) {
            $datos['usuario_id'] = $_SESSION['usuario_id'];
            $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
            $datos['departamento'] = $_SESSION['departamento'];
            
            $resultado = agregar_stock_existente($datos);
            
            if ($resultado['success']) {
                establecer_alerta('success', $resultado['message']);
                if (isset($_POST['agregar_mas'])) {
                    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/agregar_epp.php');
                } else {
                    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
                }
                exit;
            } else {
                $errores[] = $resultado['message'];
            }
        }
    }
    // ============================================
    // MODO: EPP NUEVO
    // ============================================
    else {
        $datos['categoria']        = trim($_POST['categoria'] ?? '');
        $datos['articulo']         = trim($_POST['articulo'] ?? '');
        $datos['unidad']           = trim($_POST['unidad'] ?? '');
        $datos['lote_identificador'] = trim($_POST['lote_identificador'] ?? '');
        $datos['precio']           = trim($_POST['precio'] ?? '');
        $datos['nombre_proveedor'] = trim($_POST['nombre_proveedor'] ?? '');
        
        $tallas_raw = $_POST['tallas'] ?? [];
        $cantidades_raw = $_POST['cantidades'] ?? [];
        $datos['tallas'] = [];
        $stock_total = 0;
        
        $usa_tallas = isset($_POST['usa_tallas']) && $_POST['usa_tallas'] === '1';
        
        if ($usa_tallas && !empty($tallas_raw)) {
            foreach ($tallas_raw as $i => $talla) {
                $talla = trim($talla);
                $cantidad = (int) ($cantidades_raw[$i] ?? 0);
                if (!empty($talla)) {
                    $datos['tallas'][] = ['talla' => $talla, 'cantidad' => $cantidad];
                    $stock_total += $cantidad;
                }
            }
        } else {
            $cantidad_unica = (int) ($_POST['stock'] ?? 0);
            $datos['tallas'] = [['talla' => 'Única', 'cantidad' => $cantidad_unica]];
            $stock_total = $cantidad_unica;
        }
        
        $datos['stock'] = $stock_total;
        
        if (empty($datos['categoria'])) $errores[] = "La categoría es obligatoria.";
        if (empty($datos['articulo'])) $errores[] = "El nombre del artículo es obligatorio.";
        if (empty($datos['unidad'])) $errores[] = "Debe seleccionar Pieza(s), Lote o PAR.";
        if ($stock_total < 1) $errores[] = "La cantidad total debe ser al menos 1.";
        
        if ($usa_tallas) {
            $tallas_nombres = array_column($datos['tallas'], 'talla');
            if (count($tallas_nombres) !== count(array_unique($tallas_nombres))) {
                $errores[] = "Hay tallas duplicadas.";
            }
        }
        
        if (empty($errores)) {
            $datos['usuario_id'] = $_SESSION['usuario_id'];
            $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
            $datos['departamento'] = $_SESSION['departamento'];
            
            $resultado = agregar_epp($datos);
            
            if ($resultado['success']) {
                establecer_alerta('success', $resultado['message']);
                if (isset($_POST['agregar_mas'])) {
                    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/agregar_epp.php');
                } else {
                    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
                }
                exit;
            } else {
                $errores[] = $resultado['message'];
            }
        }
    }
}

// Preparar datos de artículos con tallas para JS
$articulos_js = [];
foreach ($articulos_existentes as $art) {
    $articulos_js[] = [
        'id' => $art['id'],
        'categoria' => $art['categoria'],
        'articulo' => $art['articulo'],
        'unidad' => $art['unidad'],
        'tallas' => $art['tallas_data']
    ];
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
        .form-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1.5rem; max-width: 750px; }
        .form-card .form-label { font-size: 0.8rem; font-weight: 600; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; }
        .campo-condicional { display: none; }
        .tallas-container { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .talla-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
        .talla-row input { font-size: 0.85rem; }
        .btn-remove-talla { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        .toggle-tallas { background: #e8f4fd; border: 1px solid #b3d9ff; border-radius: 6px; padding: 0.75rem; }
        .stock-total-badge { font-size: 1.1rem; font-weight: 700; }
        .modo-toggle { display: flex; gap: 0; margin-bottom: 1.25rem; border-radius: 8px; overflow: hidden; border: 2px solid #2c3e50; }
        .modo-toggle .modo-btn { flex: 1; padding: 0.6rem 1rem; text-align: center; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; background: #fff; color: #2c3e50; border: none; }
        .modo-toggle .modo-btn.active { background: #2c3e50; color: #fff; }
        .modo-toggle .modo-btn:hover:not(.active) { background: #f0f7ff; }
        .modo-toggle .modo-btn i { margin-right: 0.4rem; }
        .stock-info-existente { background: #f0f7ff; border: 1px solid #b3d9ff; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.85rem; display: none; }
        .stock-info-existente.visible { display: block; }
        .seccion-modo { display: none; }
        .seccion-modo.visible { display: block; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;">
                            <i class="bi bi-plus-circle text-success"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Agregar artículo nuevo o stock a uno existente</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver al Inventario
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
                    
                    <!-- Toggle: EPP Existente / EPP Nuevo -->
                    <div class="modo-toggle">
                        <button type="button" class="modo-btn active" data-modo="existente" onclick="cambiarModo('existente')">
                            <i class="bi bi-box-seam"></i> EPP Existente
                        </button>
                        <button type="button" class="modo-btn" data-modo="nuevo" onclick="cambiarModo('nuevo')">
                            <i class="bi bi-plus-circle"></i> EPP Nuevo
                        </button>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- MODO: EPP EXISTENTE -->
                    <!-- ============================================ -->
                    <div class="seccion-modo visible" id="seccionExistente">
                        <form method="POST" id="formExistente">
                            <input type="hidden" name="modo_agregar" value="existente">
                            
                            <div class="mb-3">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_movimiento" class="form-control" required
                                       value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Artículo del inventario <span class="text-danger">*</span></label>
                                <select name="inventario_epp_id" id="selectArticuloExistente" class="form-select" required>
                                    <option value="">Seleccione un artículo existente</option>
                                    <?php foreach ($articulos_existentes as $art): ?>
                                    <option value="<?php echo $art['id']; ?>">
                                        [<?php echo $art['categoria']; ?>] <?php echo $art['articulo']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="tallaExistenteWrapper" style="display: none;">
                                <label class="form-label">Talla <span class="text-danger">*</span></label>
                                <select name="talla_id" id="selectTallaExistente" class="form-select" required>
                                    <option value="">Seleccione una talla</option>
                                </select>
                            </div>
                            
                            <!-- Botón para agregar nueva talla -->
                            <div class="mb-2" id="nuevaTallaToggle" style="display: none;">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="toggleNuevaTalla()">
                                    <i class="bi bi-plus-circle"></i> Agregar nueva talla a este artículo
                                </button>
                            </div>
                            
                            <!-- Campos de nueva talla -->
                            <div class="mb-3 campo-condicional" id="nuevaTallaFields">
                                <div class="tallas-container">
                                    <label class="form-label mb-1" style="font-size:0.75rem;">NUEVA TALLA</label>
                                    <div class="talla-row">
                                        <input type="text" name="nueva_talla_nombre" id="inputNuevaTalla" class="form-control" 
                                               placeholder="Talla (ej: 28, XL, CH)" style="flex: 2;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleNuevaTalla()">
                                            <i class="bi bi-x-lg"></i> Cancelar
                                        </button>
                                    </div>
                                    <small class="text-muted">Se creará esta talla con el stock que indiques abajo.</small>
                                    <input type="hidden" name="es_nueva_talla" id="esNuevaTalla" value="0">
                                </div>
                            </div>
                            
                            <div class="stock-info-existente mb-3" id="stockInfoExistente">
                                <i class="bi bi-box-seam"></i> 
                                <strong>Stock actual de esta talla:</strong> <span id="stockActualDisplay">-</span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cantidad a agregar <span class="text-danger">*</span></label>
                                <input type="number" name="cantidad_existente" class="form-control" min="1" required placeholder="0">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="2" placeholder="Observaciones del ingreso..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" name="agregar_mas" value="1" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Agregar más
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Enviar
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- MODO: EPP NUEVO -->
                    <!-- ============================================ -->
                    <div class="seccion-modo" id="seccionNuevo">
                        <form method="POST" id="formNuevo">
                            <input type="hidden" name="modo_agregar" value="nuevo">
                            
                            <div class="mb-3">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_movimiento" class="form-control" required
                                       value="<?php echo $datos['fecha_movimiento'] ?? date('Y-m-d\TH:i'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Categoría de EPP <span class="text-danger">*</span></label>
                                <select name="categoria" id="selectCategoria" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach (EPP_CATEGORIAS as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($datos['categoria'] ?? '') === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Artículo (nombre específico) <span class="text-danger">*</span></label>
                                <input type="text" name="articulo" class="form-control" required
                                       placeholder="Ej: Bota industrial punta de acero modelo X"
                                       value="<?php echo htmlspecialchars($datos['articulo'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Pieza o Lote <span class="text-danger">*</span></label>
                                <select name="unidad" id="selectUnidad" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <option value="Pieza(s)" <?php echo ($datos['unidad'] ?? '') === 'Pieza(s)' ? 'selected' : ''; ?>>Pieza(s)</option>
                                    <option value="Lote" <?php echo ($datos['unidad'] ?? '') === 'Lote' ? 'selected' : ''; ?>>Lote</option>
                                    <option value="PAR" <?php echo ($datos['unidad'] ?? '') === 'PAR' ? 'selected' : ''; ?>>PAR</option>
                                </select>
                            </div>
                            
                            <div class="mb-3 campo-condicional" id="campoLote">
                                <label class="form-label">Lote(s)</label>
                                <input type="text" name="lote_identificador" class="form-control" placeholder="Identificador del lote"
                                       value="<?php echo htmlspecialchars($datos['lote_identificador'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <div class="toggle-tallas">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="switchTallas" name="usa_tallas" value="1"
                                               <?php echo (isset($_POST['usa_tallas']) && $_POST['usa_tallas'] === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="switchTallas">
                                            <i class="bi bi-rulers"></i> Este artículo tiene tallas / números
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Activa esto para botas, overoles, guantes u otros artículos con tallas.</small>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="campoCantidadUnica">
                                <label class="form-label">Cantidad de piezas <span class="text-danger">*</span></label>
                                <input type="number" name="stock" class="form-control" min="1" placeholder="0"
                                       value="<?php echo $datos['stock'] ?? ''; ?>">
                            </div>
                            
                            <div class="mb-3 campo-condicional" id="campoTallas">
                                <label class="form-label">Tallas y cantidades <span class="text-danger">*</span></label>
                                <div class="tallas-container">
                                    <div id="tallasLista"></div>
                                    <button type="button" class="btn btn-outline-success btn-sm mt-2" id="btnAgregarTalla">
                                        <i class="bi bi-plus-circle"></i> Agregar Talla
                                    </button>
                                    <div class="mt-2 text-end">
                                        <span class="text-muted" style="font-size: 0.8rem;">Stock total: </span>
                                        <span class="stock-total-badge text-primary" id="stockTotalDisplay">0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Precio</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" name="precio" class="form-control" placeholder="0.00"
                                           value="<?php echo htmlspecialchars($datos['precio'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre del proveedor</label>
                                <input type="text" name="nombre_proveedor" class="form-control" placeholder="Nombre del proveedor"
                                       value="<?php echo htmlspecialchars($datos['nombre_proveedor'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="3"
                                          placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($datos['observaciones'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" name="agregar_mas" value="1" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Agregar más EPP
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Enviar
                                </button>
                            </div>
                        </form>
                    </div>
                    
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <script>
    // ============================================
    // Datos de artículos con tallas (desde PHP)
    // ============================================
    const articulosData = <?php echo json_encode($articulos_js, JSON_UNESCAPED_UNICODE); ?>;
    
    // ============================================
    // Toggle modo: Existente / Nuevo
    // ============================================
    function cambiarModo(modo) {
        document.querySelectorAll('.modo-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.modo === modo);
        });
        document.getElementById('seccionExistente').classList.toggle('visible', modo === 'existente');
        document.getElementById('seccionNuevo').classList.toggle('visible', modo === 'nuevo');
        
        const formExistente = document.getElementById('formExistente');
        const formNuevo = document.getElementById('formNuevo');
        formExistente.querySelectorAll('[required]').forEach(el => el.disabled = (modo !== 'existente'));
        formNuevo.querySelectorAll('[required]').forEach(el => el.disabled = (modo !== 'nuevo'));
        
        if (modo === 'existente') {
            formExistente.querySelectorAll('[required]').forEach(el => el.disabled = false);
        } else {
            formNuevo.querySelectorAll('[required]').forEach(el => el.disabled = false);
        }
    }
    
    // ============================================
    // EPP Existente: dropdown artículo → tallas
    // ============================================
    const selectArticuloEx = document.getElementById('selectArticuloExistente');
    const selectTallaEx = document.getElementById('selectTallaExistente');
    const tallaExWrapper = document.getElementById('tallaExistenteWrapper');
    const stockInfoEx = document.getElementById('stockInfoExistente');
    
    selectArticuloEx.addEventListener('change', function() {
        selectTallaEx.innerHTML = '<option value="">Seleccione una talla</option>';
        stockInfoEx.classList.remove('visible');
        document.getElementById('nuevaTallaToggle').style.display = 'none';
        document.getElementById('nuevaTallaFields').style.display = 'none';
        document.getElementById('esNuevaTalla').value = '0';
        selectTallaEx.disabled = false;
        selectTallaEx.required = true;
        
        if (!this.value) { tallaExWrapper.style.display = 'none'; return; }
        
        const art = articulosData.find(a => a.id == this.value);
        if (!art) return;
        
        art.tallas.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.talla + ' — Stock actual: ' + t.stock;
            opt.dataset.stock = t.stock;
            selectTallaEx.appendChild(opt);
        });
        
        tallaExWrapper.style.display = 'block';
        document.getElementById('nuevaTallaToggle').style.display = 'block';
        
        // Auto-seleccionar si solo hay 1 talla
        if (art.tallas.length === 1) {
            selectTallaEx.selectedIndex = 1;
            selectTallaEx.dispatchEvent(new Event('change'));
        }
    });
    
    selectTallaEx.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (this.value && opt.dataset.stock !== undefined) {
            document.getElementById('stockActualDisplay').textContent = opt.dataset.stock + ' unidades';
            stockInfoEx.classList.add('visible');
        } else {
            stockInfoEx.classList.remove('visible');
        }
    });
    
    // ============================================
    // Nueva talla para artículo existente
    // ============================================
    function toggleNuevaTalla() {
        const fields = document.getElementById('nuevaTallaFields');
        const esNueva = document.getElementById('esNuevaTalla');
        const selectTalla = document.getElementById('selectTallaExistente');
        const inputNueva = document.getElementById('inputNuevaTalla');
        
        if (fields.style.display === 'block') {
            // Cerrar
            fields.style.display = 'none';
            esNueva.value = '0';
            inputNueva.value = '';
            selectTalla.disabled = false;
            selectTalla.required = true;
            stockInfoEx.classList.remove('visible');
        } else {
            // Abrir
            fields.style.display = 'block';
            esNueva.value = '1';
            selectTalla.disabled = true;
            selectTalla.required = false;
            stockInfoEx.classList.remove('visible');
            inputNueva.focus();
        }
    }
    
    // ============================================
    // EPP Nuevo: toggle tallas
    // ============================================
    const switchTallas = document.getElementById('switchTallas');
    const campoTallas = document.getElementById('campoTallas');
    const campoCantidadUnica = document.getElementById('campoCantidadUnica');
    const tallasLista = document.getElementById('tallasLista');
    const btnAgregarTalla = document.getElementById('btnAgregarTalla');
    const stockTotalDisplay = document.getElementById('stockTotalDisplay');
    
    function toggleTallasView() {
        if (switchTallas.checked) {
            campoTallas.style.display = 'block';
            campoCantidadUnica.style.display = 'none';
            campoCantidadUnica.querySelector('input').removeAttribute('required');
            if (tallasLista.children.length === 0) agregarFilaTalla();
        } else {
            campoTallas.style.display = 'none';
            campoCantidadUnica.style.display = 'block';
            campoCantidadUnica.querySelector('input').setAttribute('required', 'required');
        }
    }
    
    switchTallas.addEventListener('change', toggleTallasView);
    
    function agregarFilaTalla(talla = '', cantidad = '') {
        const row = document.createElement('div');
        row.className = 'talla-row';
        row.innerHTML = `
            <input type="text" name="tallas[]" class="form-control" placeholder="Talla (ej: 25, M, XL)" 
                   value="${talla}" style="flex: 2;">
            <input type="number" name="cantidades[]" class="form-control input-cantidad" min="0" placeholder="Cantidad" 
                   value="${cantidad}" style="flex: 1;">
            <button type="button" class="btn btn-outline-danger btn-remove-talla" onclick="eliminarFilaTalla(this)">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        tallasLista.appendChild(row);
        recalcularTotal();
        row.querySelector('.input-cantidad').addEventListener('input', recalcularTotal);
    }
    
    function eliminarFilaTalla(btn) { btn.closest('.talla-row').remove(); recalcularTotal(); }
    
    function recalcularTotal() {
        let total = 0;
        document.querySelectorAll('#seccionNuevo .input-cantidad').forEach(input => { total += parseInt(input.value) || 0; });
        stockTotalDisplay.textContent = total;
    }
    
    btnAgregarTalla.addEventListener('click', () => agregarFilaTalla());
    
    // Lote condicional
    const selectUnidad = document.getElementById('selectUnidad');
    const campoLote = document.getElementById('campoLote');
    selectUnidad.addEventListener('change', () => {
        campoLote.style.display = selectUnidad.value === 'Lote' ? 'block' : 'none';
    });
    
    // Inicializar
    toggleTallasView();
    cambiarModo('existente');
    if (selectUnidad.value === 'Lote') campoLote.style.display = 'block';
    
    <?php
    if (!empty($datos['tallas']) && isset($_POST['usa_tallas']) && $_POST['usa_tallas'] === '1'):
        echo "cambiarModo('nuevo');\n";
        foreach ($datos['tallas'] as $t):
    ?>
    agregarFilaTalla('<?php echo addslashes($t['talla']); ?>', '<?php echo (int) $t['cantidad']; ?>');
    <?php endforeach; endif; ?>
    
    <?php if (isset($_POST['modo_agregar']) && $_POST['modo_agregar'] === 'existente'): ?>
    cambiarModo('existente');
    <?php endif; ?>
    </script>
</body>
</html>