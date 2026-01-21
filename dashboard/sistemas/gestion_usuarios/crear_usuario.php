<?php
/**
 * Crear Nuevo Usuario
 * Solo accesible para usuarios del departamento de Sistemas
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea del departamento de Sistemas
$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta sección.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener lista de departamentos
$sql_deptos = "SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos = $pdo->query($sql_deptos)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Crear Usuario - Sistemas</title>
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
        .permisos-table th, .permisos-table td {
            padding: 0.5rem;
            text-align: center;
            vertical-align: middle;
        }
        .permisos-table th:first-child,
        .permisos-table td:first-child {
            text-align: left;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .password-toggle {
            cursor: pointer;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
        }
        .required::after {
            content: " *";
            color: #dc3545;
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
                
                <div class="row mb-3">
                    <div class="col">
                        <div class="d-flex align-items-center">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary btn-sm me-3">
                                <i class="bi bi-arrow-left"></i>
                            </a>
                            <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h4>
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

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/procesar_usuario.php" method="POST" id="formCrearUsuario">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Información básica -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-person me-2"></i>Información del Usuario
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>"
                                               required maxlength="150" placeholder="Ej: Juan Pérez García">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre de Usuario</label>
                                        <input type="text" name="usuario" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['usuario'] ?? ''); ?>"
                                               required maxlength="50" placeholder="Ej: GVSIS01" 
                                               pattern="[A-Za-z0-9_-]+" title="Solo letras, números, guión y guión bajo">
                                        <small class="text-muted">Sin espacios. Se convertirá a mayúsculas.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Departamento</label>
                                        <select name="departamento_id" class="form-select" required>
                                            <option value="">Seleccionar departamento...</option>
                                            <?php foreach ($departamentos as $depto): ?>
                                                <option value="<?php echo $depto['id']; ?>" 
                                                        <?php echo (($form_data['departamento_id'] ?? '') == $depto['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($depto['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Estado Inicial</label>
                                        <select name="activo" class="form-select">
                                            <option value="1" <?php echo (($form_data['activo'] ?? 1) == 1) ? 'selected' : ''; ?>>Activo</option>
                                            <option value="0" <?php echo (($form_data['activo'] ?? 1) == 0) ? 'selected' : ''; ?>>Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Contraseña -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-shield-lock me-2"></i>Contraseña
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" name="password" class="form-control" 
                                                   id="inputPassword" required minlength="8" placeholder="Mínimo 8 caracteres">
                                            <button type="button" class="btn btn-outline-secondary password-toggle" 
                                                    onclick="togglePassword('inputPassword', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Confirmar Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" name="password_confirm" class="form-control" 
                                                   id="inputPasswordConfirm" required minlength="8" placeholder="Repetir contraseña">
                                            <button type="button" class="btn btn-outline-secondary password-toggle" 
                                                    onclick="togglePassword('inputPasswordConfirm', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            La contraseña debe tener al menos 8 caracteres. Se permiten letras, números y signos.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Permisos -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-key me-2"></i>Permisos por Módulo
                                </div>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Todos los usuarios tienen permiso de Lector por defecto. Si marca Editor o Creador, Lector se activará automáticamente.
                                </p>
                                
                                <table class="table table-bordered permisos-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40%;">Módulo</th>
                                            <th style="width: 20%;">Lector</th>
                                            <th style="width: 20%;">Creador</th>
                                            <th style="width: 20%;">Editor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Documentos SSC</strong><br><small class="text-muted">Documentos Colaborativos</small></td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="ssc_lector_visual" value="1" class="form-check-input" 
                                                           id="ssc_lector" checked disabled>
                                                </div>
                                                <input type="hidden" name="ssc_lector" value="1">
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="ssc_creador" value="1" class="form-check-input" 
                                                           id="ssc_creador" <?php echo (isset($form_data['ssc_creador']) && $form_data['ssc_creador']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="ssc_editor" value="1" class="form-check-input" 
                                                           id="ssc_editor" <?php echo (isset($form_data['ssc_editor']) && $form_data['ssc_editor']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Órdenes OSM</strong><br><small class="text-muted">Órdenes de Servicio Mantenimiento</small></td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="osm_lector_visual" value="1" class="form-check-input" 
                                                           id="osm_lector" checked disabled>
                                                </div>
                                                <input type="hidden" name="osm_lector" value="1">
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="osm_creador" value="1" class="form-check-input" 
                                                           id="osm_creador" <?php echo (isset($form_data['osm_creador']) && $form_data['osm_creador']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="osm_editor" value="1" class="form-check-input" 
                                                           id="osm_editor" <?php echo (isset($form_data['osm_editor']) && $form_data['osm_editor']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Cotizaciones CQR</strong><br><small class="text-muted">Cotizaciones de Químicos y/o Residuos</small></td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="cqr_lector_visual" value="1" class="form-check-input" 
                                                           id="cqr_lector" checked disabled>
                                                </div>
                                                <input type="hidden" name="cqr_lector" value="1">
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="cqr_creador" value="1" class="form-check-input" 
                                                           id="cqr_creador" <?php echo (isset($form_data['cqr_creador']) && $form_data['cqr_creador']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="cqr_editor" value="1" class="form-check-input" 
                                                           id="cqr_editor" <?php echo (isset($form_data['cqr_editor']) && $form_data['cqr_editor']) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <strong>CQR:</strong> Creador = Ventas (crea solicitudes), Editor = Normatividad (responde solicitudes)
                                </small>
                            </div>
                        </div>

                        <!-- Panel lateral de ayuda -->
                        <div class="col-lg-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white py-2">
                                    <i class="bi bi-question-circle me-2"></i>Ayuda
                                </div>
                                <div class="card-body small">
                                    <h6>Nombre de Usuario</h6>
                                    <p class="text-muted">Debe ser único en el sistema. Se recomienda usar el formato de código de empleado (ej: GVSIS01).</p>
                                    
                                    <h6>Permisos</h6>
                                    <ul class="text-muted ps-3">
                                        <li><strong>Lector:</strong> Solo puede ver documentos/órdenes.</li>
                                        <li><strong>Creador:</strong> Puede crear nuevos documentos/órdenes.</li>
                                        <li><strong>Editor:</strong> Puede editar documentos/órdenes existentes.</li>
                                    </ul>
                                    
                                    <h6>Estado</h6>
                                    <p class="text-muted mb-0">
                                        <span class="badge bg-success">Activo</span> El usuario puede iniciar sesión.<br>
                                        <span class="badge bg-danger">Inactivo</span> El usuario no puede iniciar sesión.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Crear Usuario
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
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
        // Toggle mostrar/ocultar contraseña
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Validar nombre de usuario sin espacios
        document.querySelector('input[name="usuario"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/\s/g, '').toUpperCase();
        });

        // Validar que las contraseñas coincidan antes de enviar
        document.getElementById('formCrearUsuario').addEventListener('submit', function(e) {
            const password = document.getElementById('inputPassword').value;
            const passwordConfirm = document.getElementById('inputPasswordConfirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                document.getElementById('inputPasswordConfirm').focus();
            }
        });
    </script>
</body>
</html>