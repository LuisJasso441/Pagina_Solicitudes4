<?php
/**
 * Nueva Cotización de Químicos y/o Residuos
 * Ubicación: dashboard/cotizaciones_qr/nueva_cotizacion_qr.php
 * Solo accesible para usuarios de Ventas con permiso de creador
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cotizaciones_qr/cotizaciones_qr_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Verificar permisos de creación
$permisos = verificar_permisos_cqr($_SESSION['usuario_id']);
if (!$permisos['puede_crear']) {
    $_SESSION['error'] = "No tienes permiso para crear cotizaciones.";
    header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/cotizaciones_qr.php');
    exit;
}

$page_title = "Nueva Cotización de Químicos y/o Residuos";
$errores = [];
$datos = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obligatorios
    $datos['folio'] = trim($_POST['folio'] ?? '');
    $datos['fecha_solicitud'] = $_POST['fecha_solicitud'] ?? '';
    $datos['nombre_amigable'] = trim($_POST['nombre_amigable'] ?? '');
    $datos['nombre_tecnico'] = trim($_POST['nombre_tecnico'] ?? '');
    $datos['categoria'] = $_POST['categoria'] ?? '';
    $datos['comentarios_ventas'] = trim($_POST['comentarios_ventas'] ?? '');
    
    // Validaciones
    if (empty($datos['folio'])) {
        $errores[] = "El folio es obligatorio";
    } else {
        // Verificar que el folio no exista
        $existe = obtener_cotizacion_qr_por_folio($datos['folio']);
        if ($existe) {
            $errores[] = "El folio '{$datos['folio']}' ya existe en el sistema";
        }
    }
    
    if (empty($datos['fecha_solicitud'])) {
        $errores[] = "La fecha es obligatoria";
    }
    
    if (empty($datos['nombre_amigable'])) {
        $errores[] = "El nombre amigable es obligatorio";
    }
    
    // Procesar archivos si no hay errores
    if (empty($errores)) {
        // Ficha técnica
        if (!empty($_FILES['ficha_tecnica']['name'])) {
            $resultado_archivo = procesar_archivo_cqr($_FILES['ficha_tecnica'], 'ficha');
            if (is_array($resultado_archivo) && isset($resultado_archivo['error'])) {
                $errores[] = "Ficha técnica: " . $resultado_archivo['error'];
            } else {
                $datos['ficha_tecnica'] = $resultado_archivo;
            }
        }
        
        // Formato descripción
        if (!empty($_FILES['formato_descripcion']['name'])) {
            $resultado_archivo = procesar_archivo_cqr($_FILES['formato_descripcion'], 'formato');
            if (is_array($resultado_archivo) && isset($resultado_archivo['error'])) {
                $errores[] = "Formato descripción: " . $resultado_archivo['error'];
            } else {
                $datos['formato_descripcion'] = $resultado_archivo;
            }
        }
    }
    
    // Crear cotización si no hay errores
    if (empty($errores)) {
        $datos['usuario_creador_id'] = $_SESSION['usuario_id'];
        $datos['departamento_creador'] = $_SESSION['departamento'];
        $datos['departamento_id'] = $_SESSION['departamento_id'] ?? null;
        $datos['usuario_nombre'] = $_SESSION['nombre_completo'];
        
        $resultado = crear_cotizacion_qr($datos);
        
        if ($resultado['success']) {
            // Enviar notificaciones a Normatividad
            $cotizacion = obtener_cotizacion_qr_por_id($resultado['id']);
            $usuarios_normatividad = obtener_usuarios_normatividad();
            
            foreach ($usuarios_normatividad as $usuario) {
                enviar_notificacion_cqr('nueva_cotizacion', $cotizacion, $usuario['id']);
            }
            
            $_SESSION['success'] = "Cotización creada exitosamente. Se ha notificado a Normatividad.";
            header('Location: ' . URL_BASE . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $resultado['id']);
            exit;
        } else {
            $errores[] = $resultado['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - GrupoVerden</title>
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
        /* Estilos específicos del formulario */
        .form-card {
            background: var(--bg-card);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-md);
            padding: 30px;
        }
        
        .section-title {
            border-bottom: 2px solid var(--color-brand-dark);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            text-align: center;
            transition: all var(--transition-normal);
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--color-brand-dark);
            background-color: #f8fff8;
        }
        
        .file-upload-area.has-file {
            border-color: var(--color-success);
            background-color: #f8fff8;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
            
            <!-- Navegación -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php">
                            Cotizaciones QR
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Nueva Cotización</li>
                </ol>
            </nav>
            
            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-plus-circle text-primary"></i>
                        <?php echo $page_title; ?>
                    </h2>
                    <p class="text-muted mb-0">Complete el formulario para crear una nueva solicitud</p>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
            
            <!-- Errores -->
            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Errores encontrados:</h6>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data" id="formCotizacion">
                    
                    <!-- Sección: Información General -->
                    <h5 class="section-title">
                        <i class="bi bi-info-circle text-primary"></i>
                        Información General
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Folio <span class="text-danger">*</span></label>
                            <input type="text" name="folio" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['folio'] ?? ''); ?>"
                                   placeholder="Ingrese el folio" required>
                            <small class="text-muted">Ingrese el folio manualmente</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_solicitud" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['fecha_solicitud'] ?? date('Y-m-d')); ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Categoría</label>
                            <select name="categoria" class="form-select">
                                <option value="">Seleccione una categoría</option>
                                <option value="en_espera_1" <?php echo ($datos['categoria'] ?? '') === 'en_espera_1' ? 'selected' : ''; ?>>En espera</option>
                                <option value="en_espera_2" <?php echo ($datos['categoria'] ?? '') === 'en_espera_2' ? 'selected' : ''; ?>>En espera</option>
                                <option value="en_espera_3" <?php echo ($datos['categoria'] ?? '') === 'en_espera_3' ? 'selected' : ''; ?>>En espera</option>
                                <option value="en_espera_4" <?php echo ($datos['categoria'] ?? '') === 'en_espera_4' ? 'selected' : ''; ?>>En espera</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Sección: Datos del Producto -->
                    <h5 class="section-title">
                        <i class="bi bi-box-seam text-primary"></i>
                        Datos del Producto
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_amigable" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['nombre_amigable'] ?? ''); ?>"
                                   placeholder="Nombre comercial del producto" required>
                            <small class="text-muted">Nombre comercial o común del producto</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre Tecnico</label>
                            <input type="text" name="nombre_tecnico" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['nombre_tecnico'] ?? ''); ?>"
                                   placeholder="Nombre técnico/químico del producto">
                            <small class="text-muted">Nombre técnico o químico del producto</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Comentarios</label>
                            <textarea name="comentarios_ventas" class="form-control" rows="4" 
                                      placeholder="Agregue comentarios o información adicional..."><?php echo htmlspecialchars($datos['comentarios_ventas'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Sección: Archivos Adjuntos -->
                    <h5 class="section-title">
                        <i class="bi bi-paperclip text-primary"></i>
                        Archivos Adjuntos <small class="text-muted fw-normal">(Opcionales)</small>
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ficha Técnica</label>
                            <div class="file-upload-area" id="area-ficha" onclick="document.getElementById('ficha_tecnica').click()">
                                <i class="bi bi-file-earmark-arrow-up fs-2 text-muted"></i>
                                <p class="mb-0 mt-2" id="label-ficha">Click para seleccionar archivo</p>
                                <small class="text-muted">PDF, DOC, DOCX, XLS, XLSX, imágenes (máx. 10MB)</small>
                            </div>
                            <input type="file" name="ficha_tecnica" id="ficha_tecnica" class="d-none" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.jpg,.jpeg,.png,.gif">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Formato Descripción</label>
                            <div class="file-upload-area" id="area-formato" onclick="document.getElementById('formato_descripcion').click()">
                                <i class="bi bi-file-earmark-arrow-up fs-2 text-muted"></i>
                                <p class="mb-0 mt-2" id="label-formato">Click para seleccionar archivo</p>
                                <small class="text-muted">PDF, DOC, DOCX, XLS, XLSX, imágenes (máx. 10MB)</small>
                            </div>
                            <input type="file" name="formato_descripcion" id="formato_descripcion" class="d-none" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.jpg,.jpeg,.png,.gif">
                        </div>
                    </div>
                    
                    <!-- Botones de Acción -->
                    <hr class="my-4">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo URL_BASE; ?>dashboard/cotizaciones_qr/cotizaciones_qr.php" 
                           class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Enviar a Normatividad
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Manejar selección de archivos
        document.getElementById('ficha_tecnica').addEventListener('change', function() {
            const area = document.getElementById('area-ficha');
            const label = document.getElementById('label-ficha');
            if (this.files.length > 0) {
                area.classList.add('has-file');
                label.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + this.files[0].name;
            } else {
                area.classList.remove('has-file');
                label.textContent = 'Click para seleccionar archivo';
            }
        });
        
        document.getElementById('formato_descripcion').addEventListener('change', function() {
            const area = document.getElementById('area-formato');
            const label = document.getElementById('label-formato');
            if (this.files.length > 0) {
                area.classList.add('has-file');
                label.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + this.files[0].name;
            } else {
                area.classList.remove('has-file');
                label.textContent = 'Click para seleccionar archivo';
            }
        });
        
        // Confirmar antes de enviar
        document.getElementById('formCotizacion').addEventListener('submit', function(e) {
            if (!confirm('¿Está seguro de enviar esta cotización a Normatividad?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>