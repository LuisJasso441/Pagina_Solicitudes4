<?php
/**
 * Crear Vale de Entrega de EPP
 * VERSION 3.0 - Dropdowns departamento/empleado + Modo Tyvek (Almacen Residuos)
 * Accesible por: Seguridad (completo) y Almacen de Residuos (simplificado)
 * Ubicacion: dashboard/inventario_epp/crear_vale_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

$permisos_vale = verificar_permisos_vales();
if (!$permisos_vale['puede_crear']) {
    establecer_alerta('error', 'No tienes permiso para crear vales de entrega.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
    exit;
}

$es_modo_tyvek = $permisos_vale['es_modo_tyvek'];
$page_title = $es_modo_tyvek ? "Vale Traje Tyvek" : "Crear Vale de Entrega de EPP";
$errores = [];

// Obtener departamentos con empleados para dropdowns
$departamentos_empleados = obtener_empleados_por_departamento();

// Obtener articulos del inventario
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

// Para modo Tyvek: buscar el articulo "Traje Tyvek" en el inventario
$tyvek_epp_id = 0;
$tyvek_talla_id = 0;
if ($es_modo_tyvek) {
    foreach ($articulos_inventario as $art) {
        if (stripos($art['articulo'], 'Tyvek') !== false || stripos($art['articulo'], 'tyvek') !== false) {
            $tyvek_epp_id = $art['id'];
            if (!empty($art['tallas_data'])) {
                $tyvek_talla_id = $art['tallas_data'][0]['id'];
            }
            break;
        }
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [];
    $datos['empleado_id'] = (int) ($_POST['empleado_id'] ?? 0);
    $datos['nombre_empleado'] = trim($_POST['nombre_empleado'] ?? '');
    $datos['area'] = trim($_POST['area'] ?? '');
    $datos['observaciones'] = trim($_POST['observaciones'] ?? '');
    $datos['usuario_id'] = $_SESSION['usuario_id'];
    $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
    
    if (!$datos['empleado_id']) $errores[] = "Debe seleccionar un empleado.";
    if (empty($datos['area'])) $errores[] = "El area es obligatoria.";
    
    // Procesar lineas
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
    
    if (empty($datos['lineas'])) $errores[] = "Debe agregar al menos un articulo al vale.";
    
    if (empty($errores)) {
        $resultado = crear_vale_epp($datos);
        if ($resultado['success']) {
            establecer_alerta('success', $resultado['message']);
            if ($es_modo_tyvek) {
                header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
            } else {
                header('Location: ' . URL_BASE . 'dashboard/inventario_epp/ver_vale_epp.php?id=' . $resultado['id']);
            }
            exit;
        } else {
            $errores[] = $resultado['message'];
        }
    }
}

// Determinar sidebar
$depto_actual = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
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
        .vale-header-card { color: #fff; border-radius: 10px; padding: 1rem 1.5rem; margin-bottom: 1rem; }
        .vale-header-card h4 { margin: 0; font-size: 1.1rem; }
        .vale-header-card small { opacity: 0.85; }
        .vale-header-red { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .vale-header-dark { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); }
        .vale-header-tyvek { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .campo-fijo { background: #e9ecef; pointer-events: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php 
        // Sidebar segun departamento
        if ($depto_actual === 'almacen_residuos') {
            $sidebar_file = __DIR__ . "/../../includes/sidebar/sidebar_sec.php";
            if (file_exists($sidebar_file)) include $sidebar_file;
        } else {
            include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php";
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;">
                            <i class="bi bi-file-earmark-text <?php echo $es_modo_tyvek ? 'text-warning' : 'text-danger'; ?>"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">
                            <?php echo $es_modo_tyvek ? 'Vale de entrega de Traje Tyvek' : 'Vale de solicitud de entrega de equipo de proteccion personal'; ?>
                        </small>
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
                        
                        <!-- ============================================ -->
                        <!-- DATOS DEL TRABAJADOR -->
                        <!-- ============================================ -->
                        <div class="vale-header-card <?php echo $es_modo_tyvek ? 'vale-header-tyvek' : 'vale-header-red'; ?>">
                            <h4><i class="bi bi-person-badge"></i> Datos del Trabajador</h4>
                            <small>Empleado que recibira el equipo</small>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <!-- Departamento -->
                            <div class="col-md-6">
                                <label class="form-label">Area / Departamento <span class="text-danger">*</span></label>
                                <?php if ($es_modo_tyvek): ?>
                                <input type="text" class="form-control campo-fijo" value="Almacen de Residuos" readonly>
                                <input type="hidden" name="area" value="Almacen de Residuos">
                                <?php else: ?>
                                <select name="area" id="selectDepartamento" class="form-select" required>
                                    <option value="">Seleccione un departamento</option>
                                    <?php foreach ($departamentos_empleados as $dep): ?>
                                    <option value="<?php echo htmlspecialchars($dep['nombre']); ?>" data-codigo="<?php echo $dep['codigo']; ?>">
                                        <?php echo htmlspecialchars($dep['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Empleado -->
                            <div class="col-md-6">
                                <label class="form-label">Empleado (ID) <span class="text-danger">*</span></label>
                                <select name="empleado_id" id="selectEmpleado" class="form-select" required>
                                    <option value="">Seleccione primero un departamento</option>
                                    <?php if ($es_modo_tyvek):
                                        // Para modo Tyvek, mostrar solo empleados de Almacen de Residuos
                                        foreach ($departamentos_empleados as $dep):
                                            if ($dep['codigo'] === 'almacen_residuos'):
                                                foreach ($dep['empleados'] as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" data-nombre="<?php echo htmlspecialchars($emp['nombre']); ?>">
                                        <?php echo htmlspecialchars($emp['usuario'] . ' - ' . $emp['nombre']); ?>
                                    </option>
                                    <?php endforeach; endif; endforeach; endif; ?>
                                </select>
                                <input type="hidden" name="nombre_empleado" id="nombreEmpleadoHidden" value="">
                            </div>
                        </div>
                        
                        <!-- ============================================ -->
                        <!-- ARTICULOS -->
                        <!-- ============================================ -->
                        <?php if ($es_modo_tyvek): ?>
                        <!-- Modo Tyvek: articulo fijo -->
                        <div class="vale-header-card vale-header-tyvek">
                            <h4><i class="bi bi-shield-check"></i> Articulo</h4>
                            <small>Traje Tyvek - Motivo: Cambio</small>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Articulo</label>
                                <input type="text" class="form-control campo-fijo" value="Traje Tyvek" readonly>
                                <input type="hidden" name="descripcion[]" value="Traje Tyvek">
                                <input type="hidden" name="inventario_epp_id[]" value="<?php echo $tyvek_epp_id; ?>">
                                <input type="hidden" name="talla_id[]" value="<?php echo $tyvek_talla_id; ?>">
                                <input type="hidden" name="talla_nombre[]" value="<?php echo $tyvek_talla_id ? 'Unica' : ''; ?>">
                                <input type="hidden" name="motivo[]" value="Cambio">
                                <input type="hidden" name="motivo_otro[]" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Motivo</label>
                                <input type="text" class="form-control campo-fijo" value="Cambio" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" name="cantidad[]" class="form-control" min="1" value="1" required>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <!-- Modo completo: lineas dinamicas -->
                        <div class="vale-header-card vale-header-dark">
                            <h4><i class="bi bi-list-check"></i> Articulos a Entregar</h4>
                            <small>Selecciona los EPP del inventario</small>
                        </div>
                        
                        <div class="lineas-container mb-3">
                            <div id="lineasLista"></div>
                            <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="agregarLinea()">
                                <i class="bi bi-plus-circle"></i> Agregar Articulo
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Observaciones -->
                        <div class="mb-3">
                            <label class="form-label">Observaciones generales</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Observaciones opcionales..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> Cancelar
                            </a>
                            <button type="submit" class="btn <?php echo $es_modo_tyvek ? 'btn-warning' : 'btn-danger'; ?>">
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
    // ============================================
    // Datos desde PHP
    // ============================================
    const departamentosData = <?php echo json_encode($departamentos_empleados, JSON_UNESCAPED_UNICODE); ?>;
    const articulosData = <?php echo json_encode($articulos_js, JSON_UNESCAPED_UNICODE); ?>;
    const esModoTyvek = <?php echo $es_modo_tyvek ? 'true' : 'false'; ?>;
    
    const selectDepto = document.getElementById('selectDepartamento');
    const selectEmpleado = document.getElementById('selectEmpleado');
    const nombreHidden = document.getElementById('nombreEmpleadoHidden');
    
    // ============================================
    // Departamento -> cargar empleados
    // ============================================
    if (selectDepto) {
        selectDepto.addEventListener('change', function() {
            selectEmpleado.innerHTML = '<option value="">Seleccione un empleado</option>';
            nombreHidden.value = '';
            
            if (!this.value) return;
            
            const codigoDepto = this.options[this.selectedIndex].dataset.codigo;
            const depto = departamentosData.find(d => d.codigo === codigoDepto);
            if (!depto) return;
            
            depto.empleados.forEach(emp => {
                const opt = document.createElement('option');
                opt.value = emp.id;
                opt.textContent = emp.usuario + ' - ' + emp.nombre;
                opt.dataset.nombre = emp.nombre;
                selectEmpleado.appendChild(opt);
            });
        });
    }
    
    // Al seleccionar empleado -> guardar nombre
    selectEmpleado.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        nombreHidden.value = (opt && opt.dataset.nombre) ? opt.dataset.nombre : '';
    });
    
    <?php if (!$es_modo_tyvek): ?>
    // ============================================
    // Lineas dinamicas (modo completo)
    // ============================================
    let lineaCount = 0;
    
    function agregarLinea() {
        lineaCount++;
        const n = lineaCount;
        
        let optionsHtml = '<option value="">-- Seleccionar del inventario --</option>';
        articulosData.forEach(a => {
            optionsHtml += `<option value="${a.id}" data-articulo="${a.articulo}">[${a.categoria}] ${a.articulo}</option>`;
        });
        
        const div = document.createElement('div');
        div.className = 'linea-item';
        div.id = 'linea-' + n;
        div.innerHTML = `
            <div class="linea-header">
                <span class="linea-num"><i class="bi bi-box-seam"></i> Articulo #${n}</span>
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
                           id="desc_${n}" placeholder="Descripcion del articulo">
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
                    <input type="number" name="cantidad[]" class="form-control form-control-sm" min="1" value="1">
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
                    <label style="font-size:0.7rem;color:#6c757d;">Cual?</label>
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
        
        if (!eppId) { tallaWrapper.style.display = 'none'; return; }
        
        const art = articulosData.find(a => a.id == eppId);
        if (!art) return;
        
        descInput.value = art.articulo;
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
    
    agregarLinea();
    <?php endif; ?>
    </script>
</body>
</html>