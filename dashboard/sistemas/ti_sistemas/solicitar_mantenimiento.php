<?php
/**
 * Solicitar Mantenimiento de Equipo
 * Accesible para todos los usuarios del sistema
 * dashboard/sistemas/ti_sistemas/solicitar_mantenimiento.php
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

// Conexión a BD
$pdo = conectarDB();

// ⭐ DETECCIÓN DE DEPARTAMENTO - Igual que en ordenes_servicio_mantenimiento.php
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti = ($departamento === 'ti' || $departamento === 'sistemas' || $departamento === 'ti_sistemas');

// ⭐ Obtener departamento_id del usuario en sesión
$departamento_id_usuario = null;
$sql_depto_usuario = "SELECT departamento_id FROM usuarios WHERE id = ?";
$stmt_depto = $pdo->prepare($sql_depto_usuario);
$stmt_depto->execute([$_SESSION['usuario_id']]);
$departamento_id_usuario = $stmt_depto->fetchColumn();

// ⭐ Obtener lista de equipos del inventario FILTRADOS por departamento
// Si es usuario de Sistemas/TI, ve todos los equipos activos
// Si es de otro departamento, solo ve los equipos de su departamento
if ($es_ti) {
    $sql_equipos = "SELECT id, tipo_equipo, codigo_interno, marca, modelo, ubicacion, departamento_id 
                    FROM inventario_equipos 
                    WHERE estado = 'activo' 
                    ORDER BY codigo_interno";
    $equipos = $pdo->query($sql_equipos)->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql_equipos = "SELECT id, tipo_equipo, codigo_interno, marca, modelo, ubicacion, departamento_id 
                    FROM inventario_equipos 
                    WHERE estado = 'activo' AND departamento_id = ?
                    ORDER BY codigo_interno";
    $stmt_equipos = $pdo->prepare($sql_equipos);
    $stmt_equipos->execute([$departamento_id_usuario]);
    $equipos = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);
}

// ⭐ Recibir equipo_id de la URL (cuando viene desde ver_equipo.php)
$equipo_id_preseleccionado = intval($_GET['equipo_id'] ?? 0);
$equipo_preseleccionado = null;

if ($equipo_id_preseleccionado > 0) {
    // Buscar el equipo preseleccionado (verificar que sea del mismo departamento o sea usuario TI)
    foreach ($equipos as $eq) {
        if ($eq['id'] == $equipo_id_preseleccionado) {
            $equipo_preseleccionado = $eq;
            break;
        }
    }
    
    // Si el equipo no está en la lista (usuario no tiene acceso), cargar solo ese equipo si es de TI
    if (!$equipo_preseleccionado && $es_ti) {
        $sql_equipo_especifico = "SELECT id, tipo_equipo, codigo_interno, marca, modelo, ubicacion, departamento_id 
                                  FROM inventario_equipos WHERE id = ?";
        $stmt_esp = $pdo->prepare($sql_equipo_especifico);
        $stmt_esp->execute([$equipo_id_preseleccionado]);
        $equipo_preseleccionado = $stmt_esp->fetch(PDO::FETCH_ASSOC);
    }
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
    <title>Solicitar Mantenimiento - TI</title>
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
        
        .char-counter {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .char-counter.warning { color: #ffc107; }
        .char-counter.danger { color: #dc3545; }
        
        /* Estilos para tipo de mantenimiento */
        .tipo-mant-card {
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #dee2e6;
        }
        .tipo-mant-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .tipo-mant-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        
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
        
        <!-- SIDEBAR - Usando la lógica exacta del proyecto -->
        <?php 
        if ($es_mantenimiento) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
        } elseif (es_usuario_colaborativo()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="row mb-3">
                    <div class="col">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i>
                            </a>
                            <h4 class="mb-0"><i class="bi bi-tools me-2"></i>Solicitar Mantenimiento</h4>
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

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_mantenimiento.php" method="POST" id="formSolicitarMantenimiento">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            
                            <!-- ⭐ Seleccionar Equipo del Inventario -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-pc-display me-2"></i>Seleccionar Equipo del Inventario
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Equipo registrado</label>
                                    <select name="equipo_id" id="selectEquipo" class="form-select" onchange="equipoSeleccionado()">
                                        <option value="">-- Seleccione un equipo (opcional) --</option>
                                        <?php foreach ($equipos as $eq): ?>
                                            <?php 
                                            $seleccionado = ($equipo_preseleccionado && $equipo_preseleccionado['id'] == $eq['id']) 
                                                         || (isset($form_data['equipo_id']) && $form_data['equipo_id'] == $eq['id']);
                                            $texto_equipo = $eq['codigo_interno'];
                                            if ($eq['marca'] || $eq['modelo']) {
                                                $texto_equipo .= ' - ' . trim($eq['marca'] . ' ' . $eq['modelo']);
                                            }
                                            $texto_equipo .= ' (' . ucfirst($eq['tipo_equipo']) . ')';
                                            if ($eq['ubicacion']) {
                                                $texto_equipo .= ' - ' . $eq['ubicacion'];
                                            }
                                            ?>
                                            <option value="<?php echo $eq['id']; ?>" 
                                                    data-tipo="<?php echo $eq['tipo_equipo']; ?>"
                                                    <?php echo $seleccionado ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($texto_equipo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($es_ti): ?>
                                        <small class="text-muted">Se muestran todos los equipos activos del inventario.</small>
                                    <?php elseif (empty($equipos)): ?>
                                        <small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No hay equipos registrados en su departamento. Seleccione solo el tipo de equipo abajo.</small>
                                    <?php else: ?>
                                        <small class="text-muted">Se muestran los equipos asignados a su departamento. Si no encuentra el equipo, seleccione solo el tipo abajo.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Tipo de Equipo -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-grid me-2"></i>¿Qué tipo de equipo necesita mantenimiento?
                                </div>
                                
                                <?php 
                                // Determinar tipo preseleccionado
                                $tipo_preseleccionado = $form_data['tipo_equipo'] ?? '';
                                if ($equipo_preseleccionado) {
                                    $tipo_preseleccionado = $equipo_preseleccionado['tipo_equipo'];
                                }
                                ?>
                                
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card computadora card text-center p-3 h-100 <?php echo ($tipo_preseleccionado === 'computadora') ? 'selected' : ''; ?>" 
                                             data-tipo="computadora" onclick="seleccionarTipo('computadora')">
                                            <div class="icon-container">
                                                <i class="bi bi-pc-display"></i>
                                            </div>
                                            <span class="fw-medium">Computadora</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card impresora card text-center p-3 h-100 <?php echo ($tipo_preseleccionado === 'impresora') ? 'selected' : ''; ?>"
                                             data-tipo="impresora" onclick="seleccionarTipo('impresora')">
                                            <div class="icon-container">
                                                <i class="bi bi-printer"></i>
                                            </div>
                                            <span class="fw-medium">Impresora</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card camara card text-center p-3 h-100 <?php echo ($tipo_preseleccionado === 'camara') ? 'selected' : ''; ?>"
                                             data-tipo="camara" onclick="seleccionarTipo('camara')">
                                            <div class="icon-container">
                                                <i class="bi bi-camera-video"></i>
                                            </div>
                                            <span class="fw-medium">Cámara</span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="tipo-equipo-card telefono card text-center p-3 h-100 <?php echo ($tipo_preseleccionado === 'telefono') ? 'selected' : ''; ?>"
                                             data-tipo="telefono" onclick="seleccionarTipo('telefono')">
                                            <div class="icon-container">
                                                <i class="bi bi-phone"></i>
                                            </div>
                                            <span class="fw-medium">Teléfono</span>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="tipo_equipo" id="inputTipoEquipo" 
                                       value="<?php echo htmlspecialchars($tipo_preseleccionado); ?>" required>
                                <div class="invalid-feedback">Debe seleccionar un tipo de equipo.</div>
                            </div>
                            
                            <!-- Tipo de Mantenimiento -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-wrench-adjustable me-2"></i>Tipo de Mantenimiento
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-check-inline w-100">
                                            <div class="card p-3 w-100 tipo-mant-card <?php echo (!isset($form_data['tipo_mantenimiento']) || $form_data['tipo_mantenimiento'] === 'correctivo') ? 'selected' : ''; ?>" 
                                                 onclick="seleccionarTipoMant('correctivo')">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="radio" name="tipo_mantenimiento" 
                                                           id="tipoCorrectivo" value="correctivo"
                                                           <?php echo (!isset($form_data['tipo_mantenimiento']) || $form_data['tipo_mantenimiento'] === 'correctivo') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-medium" for="tipoCorrectivo">
                                                        <i class="bi bi-tools text-danger me-1"></i>Correctivo
                                                    </label>
                                                    <small class="d-block text-muted mt-1">Reparar una falla o problema existente</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-check-inline w-100">
                                            <div class="card p-3 w-100 tipo-mant-card <?php echo (isset($form_data['tipo_mantenimiento']) && $form_data['tipo_mantenimiento'] === 'preventivo') ? 'selected' : ''; ?>"
                                                 onclick="seleccionarTipoMant('preventivo')">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="radio" name="tipo_mantenimiento" 
                                                           id="tipoPreventivo" value="preventivo"
                                                           <?php echo (isset($form_data['tipo_mantenimiento']) && $form_data['tipo_mantenimiento'] === 'preventivo') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-medium" for="tipoPreventivo">
                                                        <i class="bi bi-shield-check text-success me-1"></i>Preventivo
                                                    </label>
                                                    <small class="d-block text-muted mt-1">Mantenimiento programado o revisión</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Descripción del Problema -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-chat-left-text me-2"></i>Descripción del Problema
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Describa el problema o falla del equipo</label>
                                    <textarea name="descripcion_problema" class="form-control" rows="5" 
                                              id="txtDescripcion" required
                                              minlength="20" maxlength="2000"
                                              placeholder="Por favor describa detalladamente el problema que presenta el equipo. Incluya información como: qué estaba haciendo cuando ocurrió, mensajes de error que aparecen, con qué frecuencia sucede, etc."><?php echo htmlspecialchars($form_data['descripcion_problema'] ?? ''); ?></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">Mínimo 20 caracteres.</small>
                                        <small class="char-counter"><span id="charCount">0</span>/2000</small>
                                    </div>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Panel lateral -->
                        <div class="col-lg-4">
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white py-2">
                                    <i class="bi bi-person me-2"></i>Información del Solicitante
                                </div>
                                <div class="card-body small">
                                    <p class="mb-2">
                                        <strong>Nombre:</strong><br>
                                        <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Departamento:</strong><br>
                                        <?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ucfirst($_SESSION['departamento'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card border-warning">
                                <div class="card-header bg-warning py-2">
                                    <i class="bi bi-lightbulb me-2"></i>Consejos
                                </div>
                                <div class="card-body small">
                                    <ul class="mb-0 ps-3">
                                        <li>Sea lo más específico posible</li>
                                        <li>Incluya mensajes de error si los hay</li>
                                        <li>Indique desde cuándo ocurre el problema</li>
                                        <li>Mencione si el problema es intermitente o constante</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary" id="btnEnviar">
                                    <i class="bi bi-send me-1"></i>Enviar Solicitud
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos.php" class="btn btn-outline-secondary">
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
        // ⭐ Cuando se selecciona un equipo del inventario
        function equipoSeleccionado() {
            const select = document.getElementById('selectEquipo');
            const option = select.options[select.selectedIndex];
            
            if (option && option.dataset.tipo) {
                // Auto-seleccionar el tipo de equipo
                seleccionarTipo(option.dataset.tipo);
            }
        }
        
        // Seleccionar tipo de equipo
        function seleccionarTipo(tipo) {
            document.querySelectorAll('.tipo-equipo-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`.tipo-equipo-card.${tipo}`).classList.add('selected');
            document.getElementById('inputTipoEquipo').value = tipo;
        }
        
        // ⭐ Seleccionar tipo de mantenimiento
        function seleccionarTipoMant(tipo) {
            document.querySelectorAll('.tipo-mant-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('tipo' + tipo.charAt(0).toUpperCase() + tipo.slice(1)).checked = true;
        }
        
        // Contador de caracteres
        const txtDescripcion = document.getElementById('txtDescripcion');
        const charCount = document.getElementById('charCount');
        
        txtDescripcion.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            const counter = charCount.parentElement;
            counter.classList.remove('warning', 'danger');
            
            if (count > 1800) {
                counter.classList.add('danger');
            } else if (count > 1500) {
                counter.classList.add('warning');
            }
        });
        
        // Validación antes de enviar
        document.getElementById('formSolicitarMantenimiento').addEventListener('submit', function(e) {
            const tipoEquipo = document.getElementById('inputTipoEquipo').value;
            const descripcion = txtDescripcion.value.trim();
            
            if (!tipoEquipo) {
                e.preventDefault();
                alert('Por favor seleccione un tipo de equipo.');
                return false;
            }
            
            if (descripcion.length < 20) {
                e.preventDefault();
                alert('La descripción debe tener al menos 20 caracteres.');
                return false;
            }
        });
        
        // Inicializar contador
        charCount.textContent = txtDescripcion.value.length;
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>