<?php
/**
 * Nueva Cotización de Químicos y/o Residuos - v3.0
 * Ubicación: dashboard/cotizaciones_qr/nueva_cotizacion_qr.php
 * 
 * ACTUALIZACIÓN 23/01/2026:
 * - Folio consecutivo automático: CQR-DDMMYYYY-000x
 * - Campos renombrados: nombre_real_semarnat, nombre_ante_semarnat
 * - Nuevos campos obligatorios: tipo_cliente, nombre_cliente
 * - Eliminado: campo categoría
 * - Validación archivos: ficha=PDF, formato=PDF/XLSX
 * - Todos los campos obligatorios excepto Comentarios
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

// Generar vista previa del folio
$folio_preview = generar_folio_cqr();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger datos del formulario
    $datos['fecha_solicitud'] = $_POST['fecha_solicitud'] ?? '';
    $datos['nombre_real_semarnat'] = trim($_POST['nombre_real_semarnat'] ?? '');
    $datos['nombre_ante_semarnat'] = trim($_POST['nombre_ante_semarnat'] ?? '');
    $datos['tipo_cliente'] = $_POST['tipo_cliente'] ?? '';
    $datos['nombre_cliente'] = trim($_POST['nombre_cliente'] ?? '');
    $datos['comentarios_ventas'] = trim($_POST['comentarios_ventas'] ?? '');
    
    // =====================================================
    // VALIDACIONES - Todos obligatorios excepto Comentarios
    // =====================================================
    
    if (empty($datos['fecha_solicitud'])) {
        $errores[] = "La fecha de solicitud es obligatoria";
    }
    
    if (empty($datos['nombre_real_semarnat'])) {
        $errores[] = "El campo 'Nombre real (SEMARNAT)' es obligatorio";
    }
    
    if (empty($datos['nombre_ante_semarnat'])) {
        $errores[] = "El campo 'Nombre ante SEMARNAT' es obligatorio";
    }
    
    if (empty($datos['tipo_cliente'])) {
        $errores[] = "Debe seleccionar el tipo de cliente";
    } elseif (!in_array($datos['tipo_cliente'], ['nuevo', 'frecuente'])) {
        $errores[] = "Tipo de cliente no válido";
    }
    
    if (empty($datos['nombre_cliente'])) {
        $errores[] = "El nombre del cliente es obligatorio";
    }
    
    // =====================================================
    // VALIDACIÓN DE ARCHIVOS
    // =====================================================
    
    // Ficha técnica - OBLIGATORIA, SOLO PDF
    if (empty($_FILES['ficha_tecnica']['name'])) {
        $errores[] = "La Ficha Técnica es obligatoria";
    } else {
        $ext_ficha = strtolower(pathinfo($_FILES['ficha_tecnica']['name'], PATHINFO_EXTENSION));
        if ($ext_ficha !== 'pdf') {
            $errores[] = "La Ficha Técnica debe ser un archivo PDF";
        }
    }
    
    // Formato descripción - OBLIGATORIO, SOLO PDF o XLSX
    if (empty($_FILES['formato_descripcion']['name'])) {
        $errores[] = "El Formato Descripción es obligatorio";
    } else {
        $ext_formato = strtolower(pathinfo($_FILES['formato_descripcion']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext_formato, ['pdf', 'xlsx'])) {
            $errores[] = "El Formato Descripción debe ser un archivo PDF o Excel (.xlsx)";
        }
    }
    
    // Procesar archivos si no hay errores de validación
    if (empty($errores)) {
        // Ficha técnica
        $resultado_ficha = procesar_archivo_cqr($_FILES['ficha_tecnica'], 'ficha');
        if (is_array($resultado_ficha) && isset($resultado_ficha['error'])) {
            $errores[] = "Ficha técnica: " . $resultado_ficha['error'];
        } else {
            $datos['ficha_tecnica'] = $resultado_ficha;
        }
        
        // Formato descripción
        $resultado_formato = procesar_archivo_cqr($_FILES['formato_descripcion'], 'formato');
        if (is_array($resultado_formato) && isset($resultado_formato['error'])) {
            $errores[] = "Formato descripción: " . $resultado_formato['error'];
        } else {
            $datos['formato_descripcion'] = $resultado_formato;
        }
        
        // Imágenes del residuo (opcional, múltiples)
        if (!empty($_FILES['imagenes_residuo']['name'][0])) {
            $resultado_imagenes = procesar_imagenes_residuo_cqr($_FILES['imagenes_residuo']);
            if (is_array($resultado_imagenes) && isset($resultado_imagenes['errores'])) {
                foreach ($resultado_imagenes['errores'] as $err) {
                    $errores[] = "Imagen: " . $err;
                }
                // Si hay algunas imágenes válidas, guardarlas
                if (!empty($resultado_imagenes['imagenes'])) {
                    $datos['imagenes_residuo'] = json_encode($resultado_imagenes['imagenes']);
                }
            } elseif ($resultado_imagenes) {
                $datos['imagenes_residuo'] = $resultado_imagenes;
            }
        }
    }
    
    // Crear cotización si no hay errores
    if (empty($errores)) {
        $datos['usuario_creador_id'] = $_SESSION['usuario_id'];
        $datos['departamento_creador'] = $_SESSION['departamento'];
        $datos['departamento_id'] = $_SESSION['departamento_id'] ?? null;
        $datos['creador_nombre'] = $_SESSION['nombre_completo'];
        
        $resultado = crear_cotizacion_qr($datos);
        
        if ($resultado['success']) {
            $_SESSION['success'] = "Cotización {$resultado['folio']} creada exitosamente. Se ha notificado a Normatividad.";
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
        
        .file-upload-area.error {
            border-color: var(--color-danger);
            background-color: #fff8f8;
        }
        
        .folio-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius-lg);
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .preview-imagen {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #dee2e6;
        }
        
        .preview-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .preview-imagen .btn-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 22px;
            height: 22px;
            padding: 0;
            border-radius: 50%;
            font-size: 12px;
            line-height: 1;
        }
        
        .tipo-cliente-option {
            border: 2px solid #dee2e6;
            border-radius: var(--border-radius-lg);
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .tipo-cliente-option:hover {
            border-color: var(--color-brand-dark);
        }
        
        .tipo-cliente-option.selected {
            border-color: var(--color-brand-dark);
            background-color: #f8fff8;
        }
        
        .tipo-cliente-option input[type="radio"] {
            display: none;
        }
        
        .required-field::after {
            content: " *";
            color: var(--color-danger);
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
                    <p class="text-muted mb-0">Complete todos los campos obligatorios (*)</p>
                </div>
            </div>
            
            <!-- Errores -->
            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-exclamation-triangle"></i> Por favor corrija los siguientes errores:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errores as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data" id="formCotizacion" novalidate>
                    
                    <!-- Sección: Información General -->
                    <h5 class="section-title">
                        <i class="bi bi-info-circle text-primary"></i>
                        Información General
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Folio (Generado Automáticamente)</label>
                            <div class="folio-preview">
                                <i class="bi bi-upc-scan me-2"></i>
                                <?php echo htmlspecialchars($folio_preview['folio']); ?>
                            </div>
                            <small class="text-muted">El folio se genera automáticamente al enviar</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Fecha de Solicitud</label>
                            <input type="date" name="fecha_solicitud" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['fecha_solicitud'] ?? date('Y-m-d')); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <!-- Sección: Información del Cliente -->
                    <h5 class="section-title">
                        <i class="bi bi-person-badge text-primary"></i>
                        Información del Cliente
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Tipo de Cliente</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="tipo-cliente-option d-block <?php echo ($datos['tipo_cliente'] ?? '') === 'nuevo' ? 'selected' : ''; ?>">
                                        <input type="radio" name="tipo_cliente" value="nuevo" 
                                               <?php echo ($datos['tipo_cliente'] ?? '') === 'nuevo' ? 'checked' : ''; ?> required>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-plus fs-4 text-info me-2"></i>
                                            <div>
                                                <strong>Cliente Nuevo</strong>
                                                <small class="d-block text-muted">Primera vez</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="tipo-cliente-option d-block <?php echo ($datos['tipo_cliente'] ?? '') === 'frecuente' ? 'selected' : ''; ?>">
                                        <input type="radio" name="tipo_cliente" value="frecuente"
                                               <?php echo ($datos['tipo_cliente'] ?? '') === 'frecuente' ? 'checked' : ''; ?>>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-check fs-4 text-success me-2"></i>
                                            <div>
                                                <strong>Cliente Frecuente</strong>
                                                <small class="d-block text-muted">Cliente recurrente</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Nombre del Cliente</label>
                            <input type="text" name="nombre_cliente" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['nombre_cliente'] ?? ''); ?>"
                                   placeholder="Ingrese el nombre del cliente" required>
                        </div>
                    </div>
                    
                    <!-- Sección: Datos del Producto -->
                    <h5 class="section-title">
                        <i class="bi bi-box-seam text-primary"></i>
                        Datos del Producto
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Nombre real (SEMARNAT)</label>
                            <input type="text" name="nombre_real_semarnat" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['nombre_real_semarnat'] ?? ''); ?>"
                                   placeholder="Nombre real según SEMARNAT" required>
                            <small class="text-muted">Nombre oficial registrado en SEMARNAT</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Nombre ante SEMARNAT</label>
                            <input type="text" name="nombre_ante_semarnat" class="form-control" 
                                   value="<?php echo htmlspecialchars($datos['nombre_ante_semarnat'] ?? ''); ?>"
                                   placeholder="Nombre registrado ante SEMARNAT" required>
                            <small class="text-muted">Denominación ante la Secretaría</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Comentarios <span class="text-muted fw-normal">(Opcional)</span></label>
                            <textarea name="comentarios_ventas" class="form-control" rows="3" 
                                      placeholder="Agregue comentarios o información adicional..."><?php echo htmlspecialchars($datos['comentarios_ventas'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Sección: Archivos Adjuntos -->
                    <h5 class="section-title">
                        <i class="bi bi-paperclip text-primary"></i>
                        Archivos Adjuntos <span class="text-danger">*</span>
                    </h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Ficha Técnica</label>
                            <div class="file-upload-area" id="area-ficha" onclick="document.getElementById('ficha_tecnica').click()">
                                <i class="bi bi-file-earmark-pdf fs-2 text-danger"></i>
                                <p class="mb-0 mt-2" id="label-ficha">Click para seleccionar archivo</p>
                                <small class="text-muted"><strong>Solo archivos PDF</strong> (máx. 10MB)</small>
                            </div>
                            <input type="file" name="ficha_tecnica" id="ficha_tecnica" class="d-none" 
                                   accept=".pdf" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold required-field">Formato Descripción</label>
                            <div class="file-upload-area" id="area-formato" onclick="document.getElementById('formato_descripcion').click()">
                                <i class="bi bi-file-earmark-spreadsheet fs-2 text-success"></i>
                                <p class="mb-0 mt-2" id="label-formato">Click para seleccionar archivo</p>
                                <small class="text-muted"><strong>Solo PDF o Excel (.xlsx)</strong> (máx. 10MB)</small>
                            </div>
                            <input type="file" name="formato_descripcion" id="formato_descripcion" class="d-none" 
                                   accept=".pdf,.xlsx" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Imagen del Residuo <span class="text-muted fw-normal">(Opcional - Múltiples imágenes)</span></label>
                            <div class="file-upload-area" id="area-imagenes" onclick="document.getElementById('imagenes_residuo').click()">
                                <i class="bi bi-images fs-2 text-info"></i>
                                <p class="mb-0 mt-2" id="label-imagenes">Click para seleccionar imágenes</p>
                                <small class="text-muted"><strong>Formatos de imagen</strong> (JPG, PNG, GIF, etc. - máx. 10MB c/u)</small>
                            </div>
                            <input type="file" name="imagenes_residuo[]" id="imagenes_residuo" class="d-none" 
                                   accept="image/*" multiple>
                            <div id="preview-imagenes" class="mt-3 d-flex flex-wrap gap-2"></div>
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
        // Manejar selección de tipo de cliente
        document.querySelectorAll('.tipo-cliente-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.tipo-cliente-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // Manejar selección de archivos - Ficha Técnica (solo PDF)
        document.getElementById('ficha_tecnica').addEventListener('change', function() {
            const area = document.getElementById('area-ficha');
            const label = document.getElementById('label-ficha');
            
            if (this.files.length > 0) {
                const file = this.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                
                if (ext !== 'pdf') {
                    area.classList.remove('has-file');
                    area.classList.add('error');
                    label.innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i> Solo se permiten archivos PDF';
                    this.value = '';
                    return;
                }
                
                area.classList.remove('error');
                area.classList.add('has-file');
                label.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + file.name;
            } else {
                area.classList.remove('has-file', 'error');
                label.textContent = 'Click para seleccionar archivo';
            }
        });
        
        // Manejar selección de archivos - Formato Descripción (PDF o XLSX)
        document.getElementById('formato_descripcion').addEventListener('change', function() {
            const area = document.getElementById('area-formato');
            const label = document.getElementById('label-formato');
            
            if (this.files.length > 0) {
                const file = this.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                
                if (!['pdf', 'xlsx'].includes(ext)) {
                    area.classList.remove('has-file');
                    area.classList.add('error');
                    label.innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i> Solo PDF o Excel (.xlsx)';
                    this.value = '';
                    return;
                }
                
                area.classList.remove('error');
                area.classList.add('has-file');
                label.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + file.name;
            } else {
                area.classList.remove('has-file', 'error');
                label.textContent = 'Click para seleccionar archivo';
            }
        });
        
        // Manejar selección de múltiples imágenes
        document.getElementById('imagenes_residuo').addEventListener('change', function() {
            const area = document.getElementById('area-imagenes');
            const label = document.getElementById('label-imagenes');
            const preview = document.getElementById('preview-imagenes');
            
            preview.innerHTML = '';
            
            if (this.files.length > 0) {
                area.classList.add('has-file');
                label.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + this.files.length + ' imagen(es) seleccionada(s)';
                
                // Mostrar preview de cada imagen
                Array.from(this.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'preview-imagen';
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview">
                                <span class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white text-center small py-1" style="font-size: 10px;">${file.name.substring(0, 12)}${file.name.length > 12 ? '...' : ''}</span>
                            `;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                area.classList.remove('has-file');
                label.textContent = 'Click para seleccionar imágenes';
            }
        });
        
        // Validación antes de enviar
        document.getElementById('formCotizacion').addEventListener('submit', function(e) {
            const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked');
            const nombreCliente = document.querySelector('input[name="nombre_cliente"]').value.trim();
            const nombreSemarnat = document.querySelector('input[name="nombre_real_semarnat"]').value.trim();
            const nombreAnteSemarnat = document.querySelector('input[name="nombre_ante_semarnat"]').value.trim();
            const fichaTecnica = document.getElementById('ficha_tecnica').files.length;
            const formatoDescripcion = document.getElementById('formato_descripcion').files.length;
            
            let errores = [];
            
            if (!tipoCliente) errores.push('Seleccione el tipo de cliente');
            if (!nombreCliente) errores.push('Ingrese el nombre del cliente');
            if (!nombreSemarnat) errores.push('Ingrese el Nombre real (SEMARNAT)');
            if (!nombreAnteSemarnat) errores.push('Ingrese el Nombre ante SEMARNAT');
            if (!fichaTecnica) errores.push('Adjunte la Ficha Técnica (PDF)');
            if (!formatoDescripcion) errores.push('Adjunte el Formato Descripción (PDF o XLSX)');
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Por favor corrija los siguientes errores:\n\n• ' + errores.join('\n• '));
                return;
            }
            
            if (!confirm('¿Está seguro de enviar esta cotización a Normatividad?\n\nEsta acción generará una notificación al departamento.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>