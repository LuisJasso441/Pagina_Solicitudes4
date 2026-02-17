<?php
/**
 * Registrar Movimiento de EPP
 * Ubicación: dashboard/inventario_epp/registrar_movimiento.php
 * 
 * Basado en formulario JotForm: Registrar Movimiento
 * Campos: Fecha, Tipo (Entrada/Salida), Categoría, Artículo, Talla, Cantidad,
 *         Nombre del trabajador (solo Salida), Observaciones
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

// Verificar permisos de creación/edición
$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['puede_crear']) {
    establecer_alerta('error', 'No tienes permiso para registrar movimientos.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

$page_title = "Registrar Movimiento";
$errores = [];
$datos = [];

// Obtener artículos para el dropdown
$articulos_disponibles = obtener_articulos_dropdown_epp();

// Pre-seleccionar si viene de un artículo específico
$preselect_id = $_GET['epp_id'] ?? '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $datos['inventario_epp_id'] = (int) ($_POST['inventario_epp_id'] ?? 0);
    $datos['tipo_movimiento']   = trim($_POST['tipo_movimiento'] ?? '');
    $datos['fecha_movimiento']  = $_POST['fecha_movimiento'] ?? date('Y-m-d H:i:s');
    $datos['categoria']         = trim($_POST['categoria'] ?? '');
    $datos['articulo']          = trim($_POST['articulo'] ?? '');
    $datos['talla']             = trim($_POST['talla'] ?? '');
    $datos['cantidad']          = (int) ($_POST['cantidad'] ?? 0);
    $datos['nombre_trabajador'] = trim($_POST['nombre_trabajador'] ?? '');
    $datos['observaciones']     = trim($_POST['observaciones'] ?? '');
    
    // Validaciones
    if (!$datos['inventario_epp_id']) {
        $errores[] = "Debe seleccionar un artículo del inventario.";
    }
    if (empty($datos['tipo_movimiento']) || !in_array($datos['tipo_movimiento'], ['Entrada', 'Salida'])) {
        $errores[] = "Debe seleccionar un tipo de movimiento válido.";
    }
    if ($datos['cantidad'] < 1) {
        $errores[] = "La cantidad debe ser al menos 1.";
    }
    
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

// Sidebar
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
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .form-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 1.5rem;
            max-width: 700px;
        }
        .form-card .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stock-info {
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            display: none;
        }
        .stock-info.visible { display: block; }
        .campo-trabajador { display: none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;">
                            <i class="bi bi-arrow-left-right text-primary"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Registrar entrada o salida de EPP</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=movimientos" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver a Movimientos
                    </a>
                </div>
                
                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>
                
                <!-- Errores de validación -->
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;">
                        <i class="bi bi-exclamation-circle"></i>
                        <?php foreach ($errores as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario -->
                <div class="form-card">
                    <form method="POST" id="formMovimiento">
                        
                        <!-- Fecha -->
                        <div class="mb-3">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_movimiento" class="form-control" required
                                   value="<?php echo $datos['fecha_movimiento'] ?? date('Y-m-d\TH:i'); ?>">
                        </div>
                        
                        <!-- Tipo de Movimiento -->
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento <span class="text-danger">*</span></label>
                            <select name="tipo_movimiento" id="selectTipo" class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Entrada" <?php echo ($datos['tipo_movimiento'] ?? '') === 'Entrada' ? 'selected' : ''; ?>>Entrada</option>
                                <option value="Salida" <?php echo ($datos['tipo_movimiento'] ?? '') === 'Salida' ? 'selected' : ''; ?>>Salida</option>
                            </select>
                        </div>
                        
                        <!-- Artículo (dropdown con datos del inventario) -->
                        <div class="mb-3">
                            <label class="form-label">Artículo del Inventario <span class="text-danger">*</span></label>
                            <select name="inventario_epp_id" id="selectArticulo" class="form-select" required>
                                <option value="">Seleccione un artículo</option>
                                <?php foreach ($articulos_disponibles as $art): ?>
                                <option value="<?php echo $art['id']; ?>"
                                        data-categoria="<?php echo htmlspecialchars($art['categoria']); ?>"
                                        data-articulo="<?php echo htmlspecialchars($art['articulo']); ?>"
                                        data-talla="<?php echo htmlspecialchars($art['talla'] ?? ''); ?>"
                                        data-stock="<?php echo $art['stock']; ?>"
                                        data-unidad="<?php echo $art['unidad']; ?>"
                                        <?php echo ($preselect_id == $art['id'] || ($datos['inventario_epp_id'] ?? '') == $art['id']) ? 'selected' : ''; ?>>
                                    [<?php echo $art['categoria']; ?>] <?php echo $art['articulo']; ?> — Stock: <?php echo $art['stock']; ?> <?php echo $art['talla'] ? '(Talla: ' . $art['talla'] . ')' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Info del stock actual -->
                        <div class="stock-info mb-3" id="stockInfo">
                            <i class="bi bi-box-seam"></i> 
                            <strong>Stock actual:</strong> <span id="stockActual">-</span> | 
                            <strong>Categoría:</strong> <span id="categoriaInfo">-</span> | 
                            <strong>Talla:</strong> <span id="tallaInfo">-</span>
                        </div>
                        
                        <!-- Campos ocultos para categoría, artículo, talla -->
                        <input type="hidden" name="categoria" id="hiddenCategoria">
                        <input type="hidden" name="articulo" id="hiddenArticulo">
                        <input type="hidden" name="talla" id="hiddenTalla">
                        
                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" name="cantidad" class="form-control" min="1" required
                                   placeholder="0"
                                   value="<?php echo $datos['cantidad'] ?? ''; ?>">
                        </div>
                        
                        <!-- Nombre del trabajador (solo Salida) -->
                        <div class="mb-3 campo-trabajador" id="campoTrabajador">
                            <label class="form-label">Nombre del trabajador</label>
                            <input type="text" name="nombre_trabajador" class="form-control"
                                   placeholder="Nombre del trabajador que recibe el EPP"
                                   value="<?php echo htmlspecialchars($datos['nombre_trabajador'] ?? ''); ?>">
                        </div>
                        
                        <!-- Observaciones -->
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"
                                      placeholder="Observaciones del movimiento..."><?php echo htmlspecialchars($datos['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Botón -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Enviar
                            </button>
                        </div>
                        
                    </form>
                </div>
                
            </div>
        </main>
    </div>

    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        bodyElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
        themeToggle.addEventListener('click', () => {
            const newTheme = bodyElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => themeToggle.classList.remove('rotating'), 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
    <script>
    const selectArticulo = document.getElementById('selectArticulo');
    const selectTipo = document.getElementById('selectTipo');
    const stockInfo = document.getElementById('stockInfo');
    const campoTrabajador = document.getElementById('campoTrabajador');
    
    // Mostrar/ocultar campo trabajador según tipo
    selectTipo.addEventListener('change', function() {
        campoTrabajador.style.display = this.value === 'Salida' ? 'block' : 'none';
    });
    
    // Actualizar info al seleccionar artículo
    selectArticulo.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('stockActual').textContent = option.dataset.stock + ' (' + option.dataset.unidad + ')';
            document.getElementById('categoriaInfo').textContent = option.dataset.categoria;
            document.getElementById('tallaInfo').textContent = option.dataset.talla || 'N/A';
            document.getElementById('hiddenCategoria').value = option.dataset.categoria;
            document.getElementById('hiddenArticulo').value = option.dataset.articulo;
            document.getElementById('hiddenTalla').value = option.dataset.talla;
            stockInfo.classList.add('visible');
        } else {
            stockInfo.classList.remove('visible');
        }
    });
    
    // Trigger al cargar si hay preselección
    if (selectArticulo.value) selectArticulo.dispatchEvent(new Event('change'));
    if (selectTipo.value) selectTipo.dispatchEvent(new Event('change'));
    </script>
</body>
</html>