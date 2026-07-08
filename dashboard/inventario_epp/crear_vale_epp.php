<?php
/**
 * Crear Vale de Entrega de EPP
 * Solo accesible por Seguridad
 * Ubicación: dashboard/inventario_epp/crear_vale_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

$permisos_vale = verificar_permisos_vales();
if (!$permisos_vale['puede_crear']) {
    establecer_alerta('error', 'Solo Seguridad puede crear vales de entrega.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
    exit;
}

$page_title = "Crear Vale de Entrega de EPP";
$errores = [];

// Obtener artículos del inventario para selección
$articulos_inventario = obtener_inventario_epp_compacto([]);
$articulos_js = [];
foreach ($articulos_inventario as $art) {
    $articulos_js[] = [
        'id' => $art['id'],
        'categoria' => $art['categoria'],
        'articulo' => $art['articulo'],
        'unidad' => $art['unidad'],
        'tallas' => $art['tallas_data']
    ];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [];
    $datos['nombre_empleado'] = trim($_POST['nombre_empleado'] ?? '');
    $datos['area'] = trim($_POST['area'] ?? '');
    $datos['observaciones'] = trim($_POST['observaciones'] ?? '');
    $datos['usuario_id'] = $_SESSION['usuario_id'];
    $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
    
    if (empty($datos['nombre_empleado'])) $errores[] = "El nombre del empleado es obligatorio.";
    if (empty($datos['area'])) $errores[] = "El área es obligatoria.";
    
    // Procesar líneas
    $descripciones = $_POST['descripcion'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $motivos = $_POST['motivo'] ?? [];
    $motivos_otro = $_POST['motivo_otro'] ?? [];
    $epp_ids = $_POST['inventario_epp_id'] ?? [];
    $talla_ids = $_POST['talla_id'] ?? [];
    $talla_nombres = $_POST['talla_nombre'] ?? [];
    
    $datos['lineas'] = [];
    
    for ($i = 0; $i < count($descripciones); $i++) {
        $desc = trim($descripciones[$i] ?? '');
        $cant = (int) ($cantidades[$i] ?? 0);
        $motivo = $motivos[$i] ?? 'Nuevo';
        
        if (empty($desc) || $cant < 1) continue;
        
        $datos['lineas'][] = [
            'descripcion' => $desc,
            'cantidad' => $cant,
            'motivo' => $motivo,
            'motivo_otro' => ($motivo === 'Otro') ? trim($motivos_otro[$i] ?? '') : null,
            'inventario_epp_id' => (int) ($epp_ids[$i] ?? 0) ?: null,
            'talla_id' => (int) ($talla_ids[$i] ?? 0) ?: null,
            'talla' => $talla_nombres[$i] ?? null
        ];
    }
    
    if (empty($datos['lineas'])) $errores[] = "Debe agregar al menos un artículo al vale.";
    
    if (empty($errores)) {
        $resultado = crear_vale_epp($datos);
        if ($resultado['success']) {
            establecer_alerta('success', $resultado['message']);
            header('Location: ' . URL_BASE . 'dashboard/inventario_epp/ver_vale_epp.php?id=' . $resultado['id']);
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
        .form-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1.5rem; max-width: 900px; }
        .form-card .form-label { font-size: 0.8rem; font-weight: 600; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; }
        .lineas-container { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .linea-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; margin-bottom: 0.5rem; }
        .linea-item:hover { border-color: #b3d9ff; }
        .linea-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .linea-num { font-weight: 700; color: #2c3e50; font-size: 0.8rem; }
        .vale-header-card { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: #fff; border-radius: 10px; padding: 1rem 1.5rem; margin-bottom: 1rem; }
        .vale-header-card h4 { margin: 0; font-size: 1.1rem; }
        .vale-header-card small { opacity: 0.85; }
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
                            <i class="bi bi-file-earmark-text text-danger"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Vale de solicitud de entrega de equipo de protección personal</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver a Vales
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
                    <form method="POST" id="formVale">
                        
                        <!-- Datos del empleado -->
                        <div class="vale-header-card">
                            <h4><i class="bi bi-person-badge"></i> Datos del Trabajador</h4>
                            <small>Empleado que recibirá el equipo</small>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del empleado <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_empleado" class="form-control" required
                                       placeholder="Nombre completo del trabajador"
                                       value="<?php echo htmlspecialchars($_POST['nombre_empleado'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Área / Departamento <span class="text-danger">*</span></label>
                                <input type="text" name="area" class="form-control" required
                                       placeholder="Área donde labora"
                                       value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Líneas del vale -->
                        <div class="vale-header-card" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
                            <h4><i class="bi bi-list-check"></i> Artículos a Entregar</h4>
                            <small>Selecciona los EPP del inventario</small>
                        </div>
                        
                        <div class="lineas-container mb-3">
                            <div id="lineasLista"></div>
                            <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="agregarLinea()">
                                <i class="bi bi-plus-circle"></i> Agregar Artículo
                            </button>
                        </div>
                        
                        <!-- Observaciones -->
                        <div class="mb-3">
                            <label class="form-label">Observaciones generales</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Observaciones opcionales..."><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-file-earmark-check"></i> Crear Vale
                            </button>
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
    const articulosData = <?php echo json_encode($articulos_js, JSON_UNESCAPED_UNICODE); ?>;
    let lineaCount = 0;
    
    function agregarLinea() {
        lineaCount++;
        const n = lineaCount;
        
        // Build article options
        let optionsHtml = '<option value="">-- Seleccionar del inventario --</option>';
        articulosData.forEach(a => {
            optionsHtml += `<option value="${a.id}" data-articulo="${a.articulo}">[${a.categoria}] ${a.articulo}</option>`;
        });
        
        const div = document.createElement('div');
        div.className = 'linea-item';
        div.id = 'linea-' + n;
        div.innerHTML = `
            <div class="linea-header">
                <span class="linea-num"><i class="bi bi-box-seam"></i> Artículo #${n}</span>
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="eliminarLinea(${n})" style="font-size:0.75rem;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-5">
                    <select class="form-select form-select-sm" onchange="seleccionarArticulo(this, ${n})">
                        ${optionsHtml}
                    </select>
                    <input type="hidden" name="inventario_epp_id[]" id="epp_id_${n}" value="0">
                    <input type="hidden" name="talla_id[]" id="talla_id_${n}" value="0">
                    <input type="hidden" name="talla_nombre[]" id="talla_nombre_${n}" value="">
                </div>
                <div class="col-md-7">
                    <input type="text" name="descripcion[]" class="form-control form-control-sm" 
                           id="desc_${n}" placeholder="Descripción del artículo" required>
                </div>
                <div class="col-md-3" id="tallaWrapper_${n}" style="display:none;">
                    <label style="font-size:0.7rem;color:#6c757d;">Talla</label>
                    <select class="form-select form-select-sm" id="selectTalla_${n}" onchange="seleccionarTalla(this, ${n})">
                        <option value="">Seleccione</option>
                    </select>
                </div>
                <div class="col-md-3" id="stockInfo_${n}" style="display:none;">
                    <label style="font-size:0.7rem;color:#6c757d;">Stock disponible</label>
                    <div class="fw-bold text-primary" id="stockDisp_${n}" style="font-size:0.9rem;">-</div>
                </div>
                <div class="col-md-2">
                    <label style="font-size:0.7rem;color:#6c757d;">Cantidad</label>
                    <input type="number" name="cantidad[]" class="form-control form-control-sm" min="1" value="1" required>
                </div>
                <div class="col-md-2">
                    <label style="font-size:0.7rem;color:#6c757d;">Motivo</label>
                    <select name="motivo[]" class="form-select form-select-sm" onchange="toggleMotivo(this, ${n})">
                        <option value="Nuevo">Nuevo</option>
                        <option value="Cambio">Cambio</option>
                        <option value="Reemplazo">Reemplazo</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="col-md-2" id="motivoOtro_${n}" style="display:none;">
                    <label style="font-size:0.7rem;color:#6c757d;">¿Cuál?</label>
                    <input type="text" name="motivo_otro[]" class="form-control form-control-sm" placeholder="Especifique">
                </div>
            </div>
        `;
        
        document.getElementById('lineasLista').appendChild(div);
    }
    
    function eliminarLinea(n) {
        const el = document.getElementById('linea-' + n);
        if (el) el.remove();
    }
    
    function seleccionarArticulo(selectEl, n) {
        const eppId = selectEl.value;
        const descInput = document.getElementById('desc_' + n);
        const eppIdInput = document.getElementById('epp_id_' + n);
        const tallaWrapper = document.getElementById('tallaWrapper_' + n);
        const selectTalla = document.getElementById('selectTalla_' + n);
        const stockInfo = document.getElementById('stockInfo_' + n);
        
        eppIdInput.value = eppId || 0;
        document.getElementById('talla_id_' + n).value = 0;
        document.getElementById('talla_nombre_' + n).value = '';
        stockInfo.style.display = 'none';
        
        if (!eppId) {
            tallaWrapper.style.display = 'none';
            return;
        }
        
        const art = articulosData.find(a => a.id == eppId);
        if (!art) return;
        
        // Auto-fill description
        descInput.value = art.articulo;
        
        // Populate tallas
        selectTalla.innerHTML = '<option value="">Seleccione talla</option>';
        art.tallas.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.talla + ' (stock: ' + t.stock + ')';
            opt.dataset.stock = t.stock;
            opt.dataset.talla = t.talla;
            selectTalla.appendChild(opt);
        });
        
        tallaWrapper.style.display = 'block';
        
        // Auto-select if single talla
        if (art.tallas.length === 1) {
            selectTalla.selectedIndex = 1;
            seleccionarTalla(selectTalla, n);
        }
    }
    
    function seleccionarTalla(selectEl, n) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const stockInfo = document.getElementById('stockInfo_' + n);
        
        if (selectEl.value && opt.dataset.stock !== undefined) {
            document.getElementById('talla_id_' + n).value = selectEl.value;
            document.getElementById('talla_nombre_' + n).value = opt.dataset.talla;
            document.getElementById('stockDisp_' + n).textContent = opt.dataset.stock + ' unidades';
            stockInfo.style.display = 'block';
            
            const stock = parseInt(opt.dataset.stock);
            document.getElementById('stockDisp_' + n).className = 'fw-bold ' + 
                (stock === 0 ? 'text-danger' : (stock <= 5 ? 'text-warning' : 'text-success'));
        } else {
            document.getElementById('talla_id_' + n).value = 0;
            document.getElementById('talla_nombre_' + n).value = '';
            stockInfo.style.display = 'none';
        }
    }
    
    function toggleMotivo(selectEl, n) {
        document.getElementById('motivoOtro_' + n).style.display = 
            selectEl.value === 'Otro' ? 'block' : 'none';
    }
    
    // Agregar primera línea al cargar
    agregarLinea();
    </script>
</body>
</html>