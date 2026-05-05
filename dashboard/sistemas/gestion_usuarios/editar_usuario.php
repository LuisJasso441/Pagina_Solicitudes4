<?php
/**
 * Editar Usuario
 * Solo accesible para usuarios del departamento de Sistemas
 * 
 * ⭐ ACTUALIZADO: Campos de Vacaciones (no_nomina, puesto, fecha_ingreso, es_admin_area)
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

// Verificar que se reciba un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
    exit;
}

$usuario_id = (int)$_GET['id'];

// Conexión a BD
$pdo = conectarDB();

// Obtener datos del usuario CON permisos
$sql = "SELECT 
            u.*,
            d.nombre AS departamento_nombre,
            uc.nombre_completo AS creado_por_nombre,
            uu.nombre_completo AS actualizado_por_nombre,
            ps.lector AS ssc_lector,
            ps.creador AS ssc_creador,
            ps.editor AS ssc_editor,
            po.lector AS osm_lector,
            po.creador AS osm_creador,
            po.editor AS osm_editor,
            pc.lector AS cqr_lector,
            pc.creador AS cqr_creador,
            pc.editor AS cqr_editor
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN usuarios uc ON u.created_by = uc.id
        LEFT JOIN usuarios uu ON u.updated_by = uu.id
        LEFT JOIN permisos_ssc ps ON u.id = ps.user_id
        LEFT JOIN permisos_osm po ON u.id = po.user_id
        LEFT JOIN permisos_cqr pc ON u.id = pc.user_id
        WHERE u.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php?msg=error');
    exit;
}

// Obtener lista de departamentos
$sql_deptos = "SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos = $pdo->query($sql_deptos)->fetchAll(PDO::FETCH_ASSOC);

// Recuperar datos del formulario si hay errores
$form_data = $_SESSION['form_data'] ?? $usuario;
$errores = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Sistemas</title>
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
        .info-card {
            font-size: 0.85rem;
        }
        .info-card dt {
            font-weight: 500;
            color: #6c757d;
        }
        .info-card dd {
            margin-bottom: 0.5rem;
        }
        .estado-section {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
        }
        .btn-estado {
            min-width: 100px;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>
        
        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="container-fluid py-4">
                
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas.php">
                                <i class="bi bi-house-door"></i> Inicio
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php">Gestión de Usuarios</a>
                        </li>
                        <li class="breadcrumb-item active">Editar Usuario</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">
                        <i class="bi bi-person-gear me-2"></i>Editar Usuario: <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                    </h3>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                </div>

                <!-- Mostrar errores si existen -->
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

                <form id="formEditarUsuario" action="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/procesar_usuario.php" method="POST">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?php echo $usuario_id; ?>">
                    
                    <div class="row">
                        <!-- Formulario principal -->
                        <div class="col-lg-8">
                            
                            <!-- Datos personales -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-person me-2"></i>Datos del Usuario
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>"
                                               required maxlength="150">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre de Usuario</label>
                                        <input type="text" name="usuario" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['usuario'] ?? ''); ?>"
                                               required maxlength="50"
                                               pattern="[A-Za-z0-9_\-]+" title="Solo letras, números, guión y guión bajo">
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
                                    <!-- FIN VACACIONES -->
                                </div>
                            </div>

                            <!-- Contraseña (opcional en edición) -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-lock me-2"></i>Cambiar Contraseña <small class="text-muted fw-normal">(Opcional - dejar vacío para no cambiar)</small>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nueva Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" name="password" class="form-control" 
                                                   id="inputPassword" minlength="8" placeholder="Mínimo 8 caracteres">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('inputPassword', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirmar Nueva Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" name="password_confirm" class="form-control" 
                                                   id="inputPasswordConfirm" minlength="8" placeholder="Repetir contraseña">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('inputPasswordConfirm', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            Se permiten letras, números y signos.
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
                                                           id="ssc_creador" <?php echo ($form_data['ssc_creador'] ?? 0) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="ssc_editor" value="1" class="form-check-input" 
                                                           id="ssc_editor" <?php echo ($form_data['ssc_editor'] ?? 0) ? 'checked' : ''; ?>>
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
                                                           id="osm_creador" <?php echo ($form_data['osm_creador'] ?? 0) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="osm_editor" value="1" class="form-check-input" 
                                                           id="osm_editor" <?php echo ($form_data['osm_editor'] ?? 0) ? 'checked' : ''; ?>>
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
                                                           id="cqr_creador" <?php echo ($form_data['cqr_creador'] ?? 0) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="cqr_editor" value="1" class="form-check-input" 
                                                           id="cqr_editor" <?php echo ($form_data['cqr_editor'] ?? 0) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <strong>CQR:</strong> Creador = Ventas (crea solicitudes), Editor = Normatividad (responde solicitudes)
                                </small>

                                <!-- ⭐ VACACIONES: Rol Administrativo de Área -->
                                <div class="mt-3 pt-3 border-top">
                                    <div class="form-section-title">
                                        <i class="bi bi-calendar-check me-2"></i>Vacaciones
                                    </div>
                                    <table class="table table-bordered permisos-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60%;">Rol</th>
                                                <th style="width: 40%;">Asignado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong>Administrativo de Área</strong><br>
                                                    <small class="text-muted">Jefe/Responsable del departamento — puede aprobar solicitudes de vacaciones</small>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="checkbox" name="es_admin_area" value="1" class="form-check-input" 
                                                               id="es_admin_area" <?php echo ($form_data['es_admin_area'] ?? 0) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Al marcar esta casilla, el usuario podrá aprobar/rechazar solicitudes de vacaciones de su departamento.
                                    </small>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="d-flex gap-2 mb-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                                </button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i>Cancelar
                                </a>
                            </div>
                        </div>

                        <!-- Panel lateral -->
                        <div class="col-lg-4">
                            <!-- Estado del usuario -->
                            <div class="form-section estado-section mb-3">
                                <div class="form-section-title">
                                    <i class="bi bi-toggle-on me-2"></i>Estado del Usuario
                                </div>
                                <div class="text-center mb-3">
                                    <?php if ($usuario['activo']): ?>
                                        <span class="badge bg-success fs-6 px-3 py-2">
                                            <i class="bi bi-check-circle me-1"></i>ACTIVO
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6 px-3 py-2">
                                            <i class="bi bi-x-circle me-1"></i>INACTIVO
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-success btn-estado" 
                                            onclick="cambiarEstado(<?php echo $usuario_id; ?>, 1)"
                                            <?php echo $usuario['activo'] ? 'disabled' : ''; ?>>
                                        <i class="bi bi-check-lg me-1"></i>Activar
                                    </button>
                                    <button type="button" class="btn btn-danger btn-estado" 
                                            onclick="cambiarEstado(<?php echo $usuario_id; ?>, 0)"
                                            <?php echo !$usuario['activo'] ? 'disabled' : ''; ?>>
                                        <i class="bi bi-x-lg me-1"></i>Desactivar
                                    </button>
                                </div>
                            </div>

                            <!-- Información de auditoría -->
                            <div class="card info-card">
                                <div class="card-header bg-secondary text-white py-2">
                                    <i class="bi bi-clock-history me-2"></i>Información de Registro
                                </div>
                                <div class="card-body">
                                    <dl class="mb-0">
                                        <dt>Fecha de Registro</dt>
                                        <dd><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></dd>
                                        
                                        <dt>Creado por</dt>
                                        <dd><?php echo htmlspecialchars($usuario['creado_por_nombre'] ?? 'Sistema'); ?></dd>
                                        
                                        <dt>Último Acceso</dt>
                                        <dd>
                                            <?php 
                                            if ($usuario['ultimo_acceso']) {
                                                echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso']));
                                            } else {
                                                echo '<span class="text-muted">Nunca</span>';
                                            }
                                            ?>
                                        </dd>
                                        
                                        <?php if ($usuario['updated_at']): ?>
                                            <dt>Última Actualización</dt>
                                            <dd><?php echo date('d/m/Y H:i', strtotime($usuario['updated_at'])); ?></dd>
                                            
                                            <dt>Actualizado por</dt>
                                            <dd><?php echo htmlspecialchars($usuario['actualizado_por_nombre'] ?? 'N/A'); ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
            </div>
        </main>

    </div>

    <!-- Form oculto para cambio de estado -->
    <form id="formCambiarEstado" action="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/procesar_usuario.php" method="POST" style="display: none;">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" id="estadoUserId">
        <input type="hidden" name="nuevo_estado" id="nuevoEstado">
    </form>

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
        document.getElementById('formEditarUsuario').addEventListener('submit', function(e) {
            const password = document.getElementById('inputPassword').value;
            const passwordConfirm = document.getElementById('inputPasswordConfirm').value;
            
            // Solo validar si se está cambiando la contraseña
            if (password || passwordConfirm) {
                if (password !== passwordConfirm) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden.');
                    document.getElementById('inputPasswordConfirm').focus();
                    return;
                }
                if (password.length < 8) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 8 caracteres.');
                    document.getElementById('inputPassword').focus();
                    return;
                }
            }
        });

        // Cambiar estado del usuario
        function cambiarEstado(userId, nuevoEstado) {
            const mensaje = nuevoEstado === 1 
                ? '¿Está seguro de ACTIVAR este usuario?' 
                : '¿Está seguro de DESACTIVAR este usuario? No podrá iniciar sesión.';
            
            if (confirm(mensaje)) {
                document.getElementById('estadoUserId').value = userId;
                document.getElementById('nuevoEstado').value = nuevoEstado;
                document.getElementById('formCambiarEstado').submit();
            }
        }
    </script>
</body>
</html>