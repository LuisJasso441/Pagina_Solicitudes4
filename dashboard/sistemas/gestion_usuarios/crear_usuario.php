<?php
/**
 * Crear Nuevo Usuario
 * Solo accesible para usuarios del departamento de Sistemas
 * 
 * ACTUALIZADO: Sin campos de empleado. Opcion de vincular con empleado existente.
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta seccion.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// Departamentos
$departamentos = $pdo->query("SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Empleados sin cuenta (para vincular)
$empleados_sin_cuenta = $pdo->query("
    SELECT e.id, e.nombre_completo, e.no_nomina, e.puesto, e.departamento_id,
           d.nombre AS departamento_nombre
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    WHERE e.usuario_id IS NULL AND e.activo = 1
    ORDER BY e.nombre_completo
")->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <style>
        .form-section { background-color:#f8f9fa; border:1px solid #dee2e6; border-radius:.375rem; padding:1rem; margin-bottom:1rem; }
        .form-section-title { font-weight:600; font-size:.95rem; margin-bottom:.75rem; color:#495057; border-bottom:1px solid #dee2e6; padding-bottom:.5rem; }
        .permisos-table th,.permisos-table td { padding:.5rem; text-align:center; vertical-align:middle; }
        .permisos-table th:first-child,.permisos-table td:first-child { text-align:left; }
        .form-check-input:checked { background-color:#0d6efd; border-color:#0d6efd; }
        .password-toggle { cursor:pointer; }
        .form-label { font-size:.875rem; font-weight:500; }
        .required::after { content:" *"; color:#dc3545; }
        .emp-preview { background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:12px; display:none; }
        .emp-preview .label { font-size:.72rem; text-transform:uppercase; color:#6c757d; font-weight:600; }
        .emp-preview .value { font-size:.88rem; font-weight:500; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>
        <main class="main-content">
            <div class="container-fluid py-4">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php">Gesti&oacute;n de Usuarios</a></li>
                        <li class="breadcrumb-item active">Crear Usuario</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h3>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                </div>

                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>Error:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/procesar_usuario.php" method="POST">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="empleado_gth_id" id="empleado_gth_id" value="<?php echo htmlspecialchars($form_data['empleado_gth_id'] ?? ''); ?>">
                    
                    <div class="row">
                        <div class="col-lg-8">

                            <!-- Vincular con empleado existente -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-link-45deg me-2"></i>&iquest;El usuario ya existe como empleado?
                                </div>
                                <div class="d-flex gap-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="vincular_empleado" id="vincularNo" value="no" <?php echo (($form_data['vincular_empleado'] ?? 'no') === 'no') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="vincularNo">No, crear desde cero</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="vincular_empleado" id="vincularSi" value="si" <?php echo (($form_data['vincular_empleado'] ?? '') === 'si') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="vincularSi">S&iacute;, vincular con empleado</label>
                                    </div>
                                </div>

                                <!-- Selector de empleado (solo visible si elige Si) -->
                                <div id="selectorEmpleado" style="display:none">
                                    <label class="form-label fw-semibold">Seleccionar empleado</label>
                                    <select id="selectEmpleado" class="form-select mb-3">
                                        <option value="">-- Seleccionar empleado sin cuenta --</option>
                                        <?php foreach ($empleados_sin_cuenta as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($emp['nombre_completo']); ?>"
                                                data-nomina="<?php echo htmlspecialchars($emp['no_nomina'] ?? '-'); ?>"
                                                data-puesto="<?php echo htmlspecialchars($emp['puesto'] ?? '-'); ?>"
                                                data-depto="<?php echo htmlspecialchars($emp['departamento_nombre'] ?? '-'); ?>"
                                                data-depto-id="<?php echo $emp['departamento_id']; ?>"
                                                <?php echo (($form_data['empleado_gth_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['nombre_completo']); ?> - <?php echo htmlspecialchars($emp['no_nomina'] ?? 'Sin nomina'); ?> (<?php echo htmlspecialchars($emp['departamento_nombre'] ?? ''); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="emp-preview" id="empPreview">
                                        <div class="row g-2">
                                            <div class="col-md-4"><div class="label">Nombre</div><div class="value" id="prevNombre"></div></div>
                                            <div class="col-md-4"><div class="label">No. Nomina</div><div class="value" id="prevNomina"></div></div>
                                            <div class="col-md-4"><div class="label">Departamento</div><div class="value" id="prevDepto"></div></div>
                                            <div class="col-md-4"><div class="label">Puesto</div><div class="value" id="prevPuesto"></div></div>
                                        </div>
                                    </div>
                                    <?php if (empty($empleados_sin_cuenta)): ?>
                                    <div class="alert alert-info py-2 mb-0" style="font-size:.85rem">
                                        <i class="bi bi-info-circle me-1"></i>Todos los empleados ya tienen cuenta en la plataforma.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Datos del usuario -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-person me-2"></i>Datos del Usuario
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6" id="campoNombre">
                                        <label class="form-label required">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" id="inputNombre" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>"
                                               required maxlength="150" placeholder="Ej: Juan Perez Garcia">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre de Usuario</label>
                                        <input type="text" name="usuario" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['usuario'] ?? ''); ?>"
                                               required maxlength="50" placeholder="Ej: GVSIS01" 
                                               pattern="[A-Za-z0-9_\-]+" title="Solo letras, numeros, guion y guion bajo">
                                        <small class="text-muted">Sin espacios. Se convertir&aacute; a may&uacute;sculas.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Correo Electr&oacute;nico</label>
                                        <input type="email" name="correo" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['correo'] ?? ''); ?>"
                                               maxlength="150" placeholder="Ej: juan.perez@grupoverden.com">
                                        <small class="text-muted">Opcional. Se usar&aacute; para notificaciones autom&aacute;ticas.</small>
                                    </div>
                                    <div class="col-md-6" id="campoDepartamento">
                                        <label class="form-label required">Departamento</label>
                                        <select name="departamento_id" id="selectDepartamento" class="form-select" required>
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

                            <!-- Contrasena -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-lock me-2"></i>Contrase&ntilde;a
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Contrase&ntilde;a</label>
                                        <div class="input-group">
                                            <input type="password" name="password" class="form-control" 
                                                   id="password" required minlength="8" placeholder="Minimo 8 caracteres">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('password', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Confirmar Contrase&ntilde;a</label>
                                        <div class="input-group">
                                            <input type="password" name="password_confirm" class="form-control" 
                                                   id="password_confirm" required minlength="8" placeholder="Repetir contrasena">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('password_confirm', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permisos -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-key me-2"></i>Permisos por M&oacute;dulo
                                </div>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Todos los usuarios tienen permiso de Lector por defecto.
                                </p>
                                
                                <table class="table table-bordered permisos-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40%;">M&oacute;dulo</th>
                                            <th style="width:20%;">Lector</th>
                                            <th style="width:20%;">Creador</th>
                                            <th style="width:20%;">Editor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Documentos SSC</strong><br><small class="text-muted">Documentos Colaborativos</small></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" class="form-check-input" checked disabled></div><input type="hidden" name="ssc_lector" value="1"></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" name="ssc_creador" value="1" class="form-check-input" <?php echo (!empty($form_data['ssc_creador'])) ? 'checked' : ''; ?>></div></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" name="ssc_editor" value="1" class="form-check-input" <?php echo (!empty($form_data['ssc_editor'])) ? 'checked' : ''; ?>></div></td>
                                        </tr>
                                        <tr>
                                            <td><strong>&Oacute;rdenes OSM</strong><br><small class="text-muted">&Oacute;rdenes de Servicio Mantenimiento</small></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" class="form-check-input" checked disabled></div><input type="hidden" name="osm_lector" value="1"></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" name="osm_creador" value="1" class="form-check-input" <?php echo (!empty($form_data['osm_creador'])) ? 'checked' : ''; ?>></div></td>
                                            <td><div class="form-check d-flex justify-content-center"><input type="checkbox" name="osm_editor" value="1" class="form-check-input" <?php echo (!empty($form_data['osm_editor'])) ? 'checked' : ''; ?>></div></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Cotizaciones CQR</strong><br><small class="text-muted">...</small></td>
                                            <td>...</td>
                                            <td>...<input type="checkbox" name="cqr_creador" ...></td>
                                            <td>...<input type="checkbox" name="cqr_editor" ...></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Salidas SEC</strong><br><small class="text-muted">Salidas de Envases para Clientes</small></td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" class="form-check-input" checked disabled>
                                                </div>
                                                <input type="hidden" name="sec_lector" value="1">
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="sec_creador" value="1" class="form-check-input" <?php echo (!empty($form_data['sec_creador'])) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="sec_editor" value="1" class="form-check-input" <?php echo (!empty($form_data['sec_editor'])) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <strong>CQR:</strong> Creador = Ventas (crea solicitudes), Editor = Normatividad (responde solicitudes)
                                </small>

                                <!-- Vacaciones: Admin de Area -->
                                <div class="mt-3 pt-3 border-top">
                                    <div class="form-section-title">
                                        <i class="bi bi-calendar-check me-2"></i>Vacaciones
                                    </div>
                                    <table class="table table-bordered permisos-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:60%;">Rol</th>
                                                <th style="width:40%;">Asignado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong>Administrativo de &Aacute;rea</strong><br>
                                                    <small class="text-muted">Puede aprobar solicitudes de vacaciones del departamento</small>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="checkbox" name="es_admin_area" value="1" class="form-check-input" 
                                                               <?php echo (!empty($form_data['es_admin_area'])) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Botones -->
                            <div class="d-flex gap-2 mb-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus me-1"></i>Crear Usuario
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i>Cancelar
                                </a>
                            </div>
                        </div>

                        <!-- Panel lateral de ayuda -->
                        <div class="col-lg-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white py-2">
                                    <i class="bi bi-question-circle me-2"></i>Ayuda
                                </div>
                                <div class="card-body small">
                                    <h6>Vincular con Empleado</h6>
                                    <p class="text-muted">Si el empleado ya fue registrado por GTH, seleccionalo para vincular su cuenta. Sus datos (nombre, puesto, nomina, etc.) se mantienen desde GTH.</p>
                                    
                                    <h6>Nombre de Usuario</h6>
                                    <p class="text-muted">Debe ser unico. Se recomienda el formato de codigo (ej: GVSIS01).</p>
                                    
                                    <h6>Permisos</h6>
                                    <ul class="text-muted ps-3">
                                        <li><strong>Lector:</strong> Solo puede ver documentos/ordenes.</li>
                                        <li><strong>Creador:</strong> Puede crear nuevos documentos/ordenes.</li>
                                        <li><strong>Editor:</strong> Puede editar documentos/ordenes existentes.</li>
                                    </ul>
                                    
                                    <h6>Vacaciones</h6>
                                    <p class="text-muted mb-0">
                                        <span class="badge bg-primary">Admin. de Area</span> 
                                        Permite aprobar/rechazar solicitudes de vacaciones del departamento.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('bi-eye', 'bi-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('bi-eye-slash', 'bi-eye'); }
        }

        // Toggle vincular empleado
        const radioNo = document.getElementById('vincularNo');
        const radioSi = document.getElementById('vincularSi');
        const selectorEmpleado = document.getElementById('selectorEmpleado');
        const selectEmpleado = document.getElementById('selectEmpleado');
        const empPreview = document.getElementById('empPreview');
        const inputNombre = document.getElementById('inputNombre');
        const selectDepartamento = document.getElementById('selectDepartamento');
        const hiddenEmpleadoId = document.getElementById('empleado_gth_id');

        function toggleVincular() {
            const esSi = radioSi.checked;
            selectorEmpleado.style.display = esSi ? 'block' : 'none';
            
            if (!esSi) {
                // Limpiar seleccion
                selectEmpleado.value = '';
                hiddenEmpleadoId.value = '';
                empPreview.style.display = 'none';
                inputNombre.value = '';
                inputNombre.readOnly = false;
                selectDepartamento.value = '';
                selectDepartamento.disabled = false;
            }
        }

        radioNo.addEventListener('change', toggleVincular);
        radioSi.addEventListener('change', toggleVincular);

        // Al seleccionar empleado, auto-llenar campos
        selectEmpleado.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!this.value) {
                empPreview.style.display = 'none';
                hiddenEmpleadoId.value = '';
                inputNombre.value = '';
                inputNombre.readOnly = false;
                selectDepartamento.value = '';
                selectDepartamento.disabled = false;
                return;
            }

            hiddenEmpleadoId.value = this.value;

            // Preview
            document.getElementById('prevNombre').textContent = opt.dataset.nombre;
            document.getElementById('prevNomina').textContent = opt.dataset.nomina;
            document.getElementById('prevDepto').textContent = opt.dataset.depto;
            document.getElementById('prevPuesto').textContent = opt.dataset.puesto;
            empPreview.style.display = 'block';

            // Auto-llenar nombre (readonly) y departamento
            inputNombre.value = opt.dataset.nombre;
            inputNombre.readOnly = true;

            const deptoId = opt.dataset.deptoId;
            if (deptoId) {
                selectDepartamento.value = deptoId;
                selectDepartamento.disabled = true;
            }
        });

        // Restaurar estado si hay datos previos
        if (radioSi.checked) { toggleVincular(); }
        if (selectEmpleado.value) { selectEmpleado.dispatchEvent(new Event('change')); }
    </script>
</body>
</html>