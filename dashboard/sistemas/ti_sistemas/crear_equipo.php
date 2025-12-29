<?php
/**
 * Crear/Agregar Nuevo Equipo al Inventario
 * Solo accesible para usuarios del departamento de Sistemas
 * dashboard/sistemas/ti_sistemas/crear_equipo.php
 * 
 * ⚠️ CORREGIDO para coincidir con estructura real de tabla inventario_equipos
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta sección.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener lista de departamentos para el select
$sql_deptos = "SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos = $pdo->query($sql_deptos)->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de usuarios para asignar
$sql_usuarios = "SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo";
$usuarios = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_ASSOC);

// Generar siguiente código interno sugerido por tipo
function generarCodigoSugerido($pdo, $tipo) {
    $prefijos = [
        'computadora' => 'PC',
        'impresora' => 'IMP',
        'camara' => 'CAM',
        'telefono' => 'TEL'
    ];
    
    $prefijo = $prefijos[$tipo] ?? 'EQ';
    
    $sql = "SELECT codigo_interno FROM inventario_equipos 
            WHERE codigo_interno LIKE :prefijo 
            ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['prefijo' => $prefijo . '-%']);
    $ultimo = $stmt->fetchColumn();
    
    if ($ultimo) {
        $numero = intval(substr($ultimo, strlen($prefijo) + 1)) + 1;
    } else {
        $numero = 1;
    }
    
    return $prefijo . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
}

// Recuperar datos del formulario si hay errores
$form_data = $_SESSION['form_data'] ?? [];
$errores = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Equipo - Inventario TI</title>
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
    
    <style>
        .form-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .form-section-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .tipo-equipo-card {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #dee2e6;
        }
        .tipo-equipo-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .tipo-equipo-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .tipo-equipo-card .icon-container {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .tipo-equipo-card.computadora .icon-container { color: #0d6efd; }
        .tipo-equipo-card.impresora .icon-container { color: #6f42c1; }
        .tipo-equipo-card.camara .icon-container { color: #198754; }
        .tipo-equipo-card.telefono .icon-container { color: #fd7e14; }
        
        /* Responsive para cards de tipo */
        @media (max-width: 576px) {
            .tipo-equipo-card {
                padding: 0.75rem !important;
            }
            .tipo-equipo-card .icon-container {
                font-size: 1.5rem;
            }
            .tipo-equipo-card span {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="row mb-3">
                    <div class="col">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i>
                            </a>
                            <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Agregar Nuevo Equipo</h4>
                        </div>
                    </div>
                </div>

                <!-- Mostrar errores -->
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Por favor corrija los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_equipo.php" method="POST" id="formCrearEquipo">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            
                            <!-- Tipo de Equipo -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-grid me-2"></i>Tipo de Equipo
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card computadora card text-center p-3 h-100 <?php echo (isset($form_data['tipo_equipo']) && $form_data['tipo_equipo'] === 'computadora') ? 'selected' : ''; ?>" 
                                             data-tipo="computadora" onclick="seleccionarTipo('computadora')">
                                            <div class="icon-container">
                                                <i class="bi bi-pc-display"></i>
                                            </div>
                                            <span class="fw-medium">Computadora</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card impresora card text-center p-3 h-100 <?php echo (isset($form_data['tipo_equipo']) && $form_data['tipo_equipo'] === 'impresora') ? 'selected' : ''; ?>"
                                             data-tipo="impresora" onclick="seleccionarTipo('impresora')">
                                            <div class="icon-container">
                                                <i class="bi bi-printer"></i>
                                            </div>
                                            <span class="fw-medium">Impresora</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card camara card text-center p-3 h-100 <?php echo (isset($form_data['tipo_equipo']) && $form_data['tipo_equipo'] === 'camara') ? 'selected' : ''; ?>"
                                             data-tipo="camara" onclick="seleccionarTipo('camara')">
                                            <div class="icon-container">
                                                <i class="bi bi-camera-video"></i>
                                            </div>
                                            <span class="fw-medium">Cámara</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card telefono card text-center p-3 h-100 <?php echo (isset($form_data['tipo_equipo']) && $form_data['tipo_equipo'] === 'telefono') ? 'selected' : ''; ?>"
                                             data-tipo="telefono" onclick="seleccionarTipo('telefono')">
                                            <div class="icon-container">
                                                <i class="bi bi-phone"></i>
                                            </div>
                                            <span class="fw-medium">Teléfono</span>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="tipo_equipo" id="inputTipoEquipo" 
                                       value="<?php echo htmlspecialchars($form_data['tipo_equipo'] ?? ''); ?>" required>
                                <div class="invalid-feedback" id="tipoEquipoError" style="display: none;">
                                    Debe seleccionar un tipo de equipo.
                                </div>
                            </div>
                            
                            <!-- Identificación del Equipo -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-upc-scan me-2"></i>Identificación del Equipo
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Código Interno</label>
                                        <input type="text" name="codigo_interno" class="form-control" 
                                               id="inputCodigoInterno"
                                               value="<?php echo htmlspecialchars($form_data['codigo_interno'] ?? ''); ?>"
                                               required maxlength="50" placeholder="Ej: PC-001"
                                               pattern="[A-Z0-9\-]+" title="Solo letras, números y guiones">
                                        <small class="text-muted">Se sugiere automáticamente al seleccionar tipo.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Número de Serie</label>
                                        <input type="text" name="numero_serie" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['numero_serie'] ?? ''); ?>"
                                               maxlength="100" placeholder="Número de serie del fabricante">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Marca</label>
                                        <input type="text" name="marca" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['marca'] ?? ''); ?>"
                                               maxlength="100" placeholder="Ej: HP, Dell, Canon">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Modelo</label>
                                        <input type="text" name="modelo" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['modelo'] ?? ''); ?>"
                                               maxlength="100" placeholder="Ej: EliteDesk 800">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Asignación -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-person-badge me-2"></i>Asignación
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Ubicación Física</label>
                                        <input type="text" name="ubicacion" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['ubicacion'] ?? ''); ?>"
                                               required maxlength="200" placeholder="Ej: Oficina 201, Recepción, Almacén">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Departamento</label>
                                        <select name="departamento_id" class="form-select" id="selectDepartamento">
                                            <option value="">Sin asignar</option>
                                            <?php foreach ($departamentos as $depto): ?>
                                                <option value="<?php echo $depto['id']; ?>" 
                                                        <?php echo (isset($form_data['departamento_id']) && $form_data['departamento_id'] == $depto['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($depto['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Usuario Responsable</label>
                                        <select name="usuario_asignado_id" class="form-select">
                                            <option value="">Sin asignar</option>
                                            <?php foreach ($usuarios as $usr): ?>
                                                <option value="<?php echo $usr['id']; ?>"
                                                        <?php echo (isset($form_data['usuario_asignado_id']) && $form_data['usuario_asignado_id'] == $usr['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($usr['nombre_completo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Correo Electrónico Asignado</label>
                                        <input type="email" name="correo_asignado" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['correo_asignado'] ?? ''); ?>"
                                               maxlength="150" placeholder="Ej: usuario@empresa.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Fecha de Adquisición</label>
                                        <input type="date" name="fecha_adquisicion" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['fecha_adquisicion'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Estado y Notas -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-toggles me-2"></i>Estado y Notas
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Estado del Equipo</label>
                                        <select name="estado" class="form-select" required>
                                            <option value="activo" <?php echo (!isset($form_data['estado']) || $form_data['estado'] === 'activo') ? 'selected' : ''; ?>>
                                                Activo
                                            </option>
                                            <option value="inactivo" <?php echo (isset($form_data['estado']) && $form_data['estado'] === 'inactivo') ? 'selected' : ''; ?>>
                                                Inactivo
                                            </option>
                                            <option value="en_reparacion" <?php echo (isset($form_data['estado']) && $form_data['estado'] === 'en_reparacion') ? 'selected' : ''; ?>>
                                                En Reparación
                                            </option>
                                            <option value="dado_de_baja" <?php echo (isset($form_data['estado']) && $form_data['estado'] === 'dado_de_baja') ? 'selected' : ''; ?>>
                                                Dado de Baja
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notas</label>
                                        <textarea name="notas" class="form-control" rows="3" 
                                                  maxlength="1000" placeholder="Notas adicionales sobre el equipo..."><?php echo htmlspecialchars($form_data['notas'] ?? ''); ?></textarea>
                                        <small class="text-muted">Máximo 1000 caracteres.</small>
                                    </div>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Panel lateral de ayuda -->
                        <div class="col-lg-4">
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white py-2">
                                    <i class="bi bi-question-circle me-2"></i>Ayuda
                                </div>
                                <div class="card-body small">
                                    <h6>Código Interno</h6>
                                    <p class="text-muted">Identificador único del equipo en el sistema. Se sugiere automáticamente según el tipo seleccionado.</p>
                                    
                                    <h6>Número de Serie</h6>
                                    <p class="text-muted">Número de serie del fabricante. Útil para garantías y seguimiento.</p>
                                    
                                    <h6>Estados</h6>
                                    <ul class="text-muted ps-3 mb-0">
                                        <li><span class="badge bg-success">Activo</span> En uso normal.</li>
                                        <li><span class="badge bg-secondary">Inactivo</span> Sin usar temporalmente.</li>
                                        <li><span class="badge bg-warning text-dark">En Reparación</span> En mantenimiento.</li>
                                        <li><span class="badge bg-danger">Dado de Baja</span> Fuera de servicio.</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Resumen del equipo -->
                            <div class="card border-secondary" id="cardResumen" style="display: none;">
                                <div class="card-header bg-secondary text-white py-2">
                                    <i class="bi bi-clipboard-check me-2"></i>Resumen
                                </div>
                                <div class="card-body small">
                                    <div id="resumenContenido">
                                        <!-- Se llena dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Guardar Equipo
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="guardarYNuevo()">
                                    <i class="bi bi-plus-lg me-1"></i>Guardar y Agregar Otro
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i>Cancelar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Códigos sugeridos por tipo (se obtienen via AJAX o se definen aquí)
        const codigosSugeridos = {
            computadora: '<?php echo generarCodigoSugerido($pdo, "computadora"); ?>',
            impresora: '<?php echo generarCodigoSugerido($pdo, "impresora"); ?>',
            camara: '<?php echo generarCodigoSugerido($pdo, "camara"); ?>',
            telefono: '<?php echo generarCodigoSugerido($pdo, "telefono"); ?>'
        };
        
        // Seleccionar tipo de equipo
        function seleccionarTipo(tipo) {
            // Quitar selección anterior
            document.querySelectorAll('.tipo-equipo-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Agregar selección actual
            document.querySelector(`.tipo-equipo-card.${tipo}`).classList.add('selected');
            
            // Actualizar input hidden
            document.getElementById('inputTipoEquipo').value = tipo;
            
            // Sugerir código interno
            const inputCodigo = document.getElementById('inputCodigoInterno');
            if (!inputCodigo.value || inputCodigo.value.match(/^(PC|IMP|CAM|TEL|EQ)-\d{3}$/)) {
                inputCodigo.value = codigosSugeridos[tipo];
            }
            
            // Mostrar card de resumen
            document.getElementById('cardResumen').style.display = 'block';
            actualizarResumen();
        }
        
        // Actualizar resumen en tiempo real
        function actualizarResumen() {
            const tipo = document.getElementById('inputTipoEquipo').value;
            const codigo = document.getElementById('inputCodigoInterno').value;
            const ubicacion = document.querySelector('input[name="ubicacion"]').value;
            const marca = document.querySelector('input[name="marca"]').value;
            const modelo = document.querySelector('input[name="modelo"]').value;
            
            const tiposNombres = {
                computadora: 'Computadora',
                impresora: 'Impresora',
                camara: 'Cámara',
                telefono: 'Teléfono'
            };
            
            let html = '<ul class="list-unstyled mb-0">';
            if (tipo) html += `<li><strong>Tipo:</strong> ${tiposNombres[tipo] || tipo}</li>`;
            if (codigo) html += `<li><strong>Código:</strong> ${codigo}</li>`;
            if (marca || modelo) html += `<li><strong>Equipo:</strong> ${marca} ${modelo}</li>`;
            if (ubicacion) html += `<li><strong>Ubicación:</strong> ${ubicacion}</li>`;
            html += '</ul>';
            
            document.getElementById('resumenContenido').innerHTML = html;
        }
        
        // Validar formulario antes de enviar
        document.getElementById('formCrearEquipo').addEventListener('submit', function(e) {
            const tipoEquipo = document.getElementById('inputTipoEquipo').value;
            
            if (!tipoEquipo) {
                e.preventDefault();
                document.getElementById('tipoEquipoError').style.display = 'block';
                document.querySelector('.tipo-equipo-card').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
        });
        
        // Guardar y agregar otro
        function guardarYNuevo() {
            const form = document.getElementById('formCrearEquipo');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'agregar_otro';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
        
        // Actualizar resumen cuando cambien los campos
        document.querySelectorAll('#formCrearEquipo input, #formCrearEquipo select, #formCrearEquipo textarea').forEach(el => {
            el.addEventListener('input', actualizarResumen);
            el.addEventListener('change', actualizarResumen);
        });
        
        // Convertir código interno a mayúsculas
        document.getElementById('inputCodigoInterno').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
        });
        
        // Si ya hay un tipo seleccionado (por errores previos), mostrar resumen
        <?php if (!empty($form_data['tipo_equipo'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('cardResumen').style.display = 'block';
            actualizarResumen();
        });
        <?php endif; ?>
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>