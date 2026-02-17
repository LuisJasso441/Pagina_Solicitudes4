<?php
/**
 * Agregar nuevo EPP al Inventario
 * Ubicación: dashboard/inventario_epp/agregar_epp.php
 * 
 * Basado en formulario JotForm: Añadir Nuevo EPP
 * Solo accesible por Almacén de Refacciones (creador)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

// Verificar permisos de creación
$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['puede_crear']) {
    establecer_alerta('error', 'No tienes permiso para agregar artículos al inventario.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

$page_title = "Agregar Nuevo EPP";
$errores = [];
$datos = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger datos
    $datos['fecha_movimiento'] = $_POST['fecha_movimiento'] ?? date('Y-m-d H:i:s');
    $datos['categoria']        = trim($_POST['categoria'] ?? '');
    $datos['articulo']         = trim($_POST['articulo'] ?? '');
    $datos['unidad']           = trim($_POST['unidad'] ?? '');
    $datos['lote_identificador'] = trim($_POST['lote_identificador'] ?? '');
    $datos['stock']            = (int) ($_POST['stock'] ?? 0);
    $datos['talla']            = trim($_POST['talla'] ?? '');
    $datos['precio']           = trim($_POST['precio'] ?? '');
    $datos['nombre_proveedor'] = trim($_POST['nombre_proveedor'] ?? '');
    $datos['observaciones']    = trim($_POST['observaciones'] ?? '');
    
    // Validaciones
    if (empty($datos['categoria'])) {
        $errores[] = "La categoría de EPP es obligatoria.";
    }
    if (empty($datos['articulo'])) {
        $errores[] = "El nombre del artículo es obligatorio.";
    }
    if (empty($datos['unidad'])) {
        $errores[] = "Debe seleccionar Pieza(s) o Lote.";
    }
    if ($datos['stock'] < 1) {
        $errores[] = "La cantidad de piezas debe ser al menos 1.";
    }
    
    // Si no hay errores, agregar
    if (empty($errores)) {
        $datos['usuario_id'] = $_SESSION['usuario_id'];
        $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
        $datos['departamento'] = $_SESSION['departamento'];
        
        $resultado = agregar_epp($datos);
        
        if ($resultado['success']) {
            // ¿Agregar más o volver al dashboard?
            if (isset($_POST['agregar_mas'])) {
                establecer_alerta('success', $resultado['message']);
                header('Location: ' . URL_BASE . 'dashboard/inventario_epp/agregar_epp.php');
            } else {
                establecer_alerta('success', $resultado['message']);
                header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
            }
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
        .campo-condicional { display: none; }
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
                            <i class="bi bi-plus-circle text-success"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Agregar artículo al inventario de EPP</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver al Inventario
                    </a>
                </div>
                
                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>
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
                    <form method="POST" id="formAgregarEPP">
                        
                        <!-- Fecha -->
                        <div class="mb-3">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_movimiento" class="form-control" required
                                   value="<?php echo $datos['fecha_movimiento'] ?? date('Y-m-d\TH:i'); ?>">
                        </div>
                        
                        <!-- Categoría -->
                        <div class="mb-3">
                            <label class="form-label">Categoría de EPP <span class="text-danger">*</span></label>
                            <select name="categoria" id="selectCategoria" class="form-select" required>
                                <option value="">Seleccione</option>
                                <?php foreach (EPP_CATEGORIAS as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo ($datos['categoria'] ?? '') === $cat ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Artículo (nombre específico) -->
                        <div class="mb-3">
                            <label class="form-label">Artículo (nombre específico) <span class="text-danger">*</span></label>
                            <input type="text" name="articulo" class="form-control" required
                                   placeholder="Ej: Bota industrial punta de acero modelo X"
                                   value="<?php echo htmlspecialchars($datos['articulo'] ?? ''); ?>">
                        </div>
                        
                        <!-- Pieza o Lote -->
                        <div class="mb-3">
                            <label class="form-label">Pieza o Lote <span class="text-danger">*</span></label>
                            <select name="unidad" id="selectUnidad" class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Pieza(s)" <?php echo ($datos['unidad'] ?? '') === 'Pieza(s)' ? 'selected' : ''; ?>>Pieza(s)</option>
                                <option value="Lote" <?php echo ($datos['unidad'] ?? '') === 'Lote' ? 'selected' : ''; ?>>Lote</option>
                                <option value="PAR" <?php echo ($datos['unidad'] ?? '') === 'PAR' ? 'selected' : ''; ?>>PAR</option>
                            </select>
                        </div>
                        
                        <!-- Lote(s) - solo visible cuando unidad = Lote -->
                        <div class="mb-3 campo-condicional" id="campoLote">
                            <label class="form-label">Lote(s)</label>
                            <input type="text" name="lote_identificador" class="form-control"
                                   placeholder="Identificador del lote"
                                   value="<?php echo htmlspecialchars($datos['lote_identificador'] ?? ''); ?>">
                        </div>
                        
                        <!-- Cantidad de piezas -->
                        <div class="mb-3">
                            <label class="form-label">Cantidad de piezas <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" min="1" required
                                   placeholder="0"
                                   value="<?php echo $datos['stock'] ?? ''; ?>">
                        </div>
                        
                        <!-- Talla - solo visible cuando categoría = Overol o Botas -->
                        <div class="mb-3 campo-condicional" id="campoTalla">
                            <label class="form-label">Talla / Número de calzado</label>
                            <input type="text" name="talla" class="form-control"
                                   placeholder="Ej: M, L, XL, 26, 27, 28..."
                                   value="<?php echo htmlspecialchars($datos['talla'] ?? ''); ?>">
                        </div>
                        
                        <!-- Precio -->
                        <div class="mb-3">
                            <label class="form-label">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" name="precio" class="form-control"
                                       placeholder="0.00"
                                       value="<?php echo htmlspecialchars($datos['precio'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Proveedor -->
                        <div class="mb-3">
                            <label class="form-label">Nombre del proveedor</label>
                            <input type="text" name="nombre_proveedor" class="form-control"
                                   placeholder="Nombre del proveedor"
                                   value="<?php echo htmlspecialchars($datos['nombre_proveedor'] ?? ''); ?>">
                        </div>
                        
                        <!-- Observaciones -->
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"
                                      placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($datos['observaciones'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Botones -->
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
    // Campos condicionales
    const selectUnidad = document.getElementById('selectUnidad');
    const selectCategoria = document.getElementById('selectCategoria');
    const campoLote = document.getElementById('campoLote');
    const campoTalla = document.getElementById('campoTalla');
    
    function actualizarCamposCondicionales() {
        // Lote visible cuando unidad = Lote
        campoLote.style.display = selectUnidad.value === 'Lote' ? 'block' : 'none';
        
        // Talla visible cuando categoría = Overol de Seguridad o Botas
        const categoriasConTalla = ['Overol de Seguridad', 'Botas'];
        campoTalla.style.display = categoriasConTalla.includes(selectCategoria.value) ? 'block' : 'none';
    }
    
    selectUnidad.addEventListener('change', actualizarCamposCondicionales);
    selectCategoria.addEventListener('change', actualizarCamposCondicionales);
    
    // Ejecutar al cargar
    actualizarCamposCondicionales();
    </script>
</body>
</html>