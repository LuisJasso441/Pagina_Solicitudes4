<?php
/**
 * Promover Empleado a Usuario de Plataforma
 * dashboard/sistemas/gestion_usuarios/promover_empleado.php
 * 
 * Lista empleados sin cuenta (empleados_gth.usuario_id IS NULL)
 * Permite seleccionar uno y crear usuario/password para darle acceso
 */
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../config/database.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$departamento_usuario = strtolower($_SESSION['departamento'] ?? '');
if ($departamento_usuario !== 'sistemas') {
    establecer_alerta('error', 'No tienes permisos.');
    header('Location: ' . URL_BASE . 'index.php'); exit;
}

$pdo = conectarDB();
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

// Empleados sin cuenta
$sql = "
    SELECT e.*, d.nombre AS departamento_nombre
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    WHERE e.usuario_id IS NULL AND e.activo = 1
    ORDER BY e.nombre_completo ASC
";
$empleados_sin_cuenta = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$form_data = $_SESSION['form_data_promover'] ?? [];
$errores = $_SESSION['form_errors_promover'] ?? [];
unset($_SESSION['form_data_promover'], $_SESSION['form_errors_promover']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promover Empleado - Sistemas</title>
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
        .form-section { background:#f8f9fa; border:1px solid #dee2e6; border-radius:.375rem; padding:1rem; margin-bottom:1rem; }
        .form-section-title { font-weight:600; font-size:.95rem; margin-bottom:.75rem; color:#495057; border-bottom:1px solid #dee2e6; padding-bottom:.5rem; }
        .required::after { content:" *"; color:#dc3545; }
        .emp-preview { background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:15px; display:none; }
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
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php">Gestion de Usuarios</a></li>
                        <li class="breadcrumb-item active">Promover Empleado</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="mb-0"><i class="bi bi-person-up me-2"></i>Promover Empleado a Usuario</h3>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Regresar
                    </a>
                </div>
                <p class="text-muted">Selecciona un empleado registrado por GTH y asignale credenciales de acceso a la plataforma.</p>

                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show"><?php echo $alerta['mensaje']; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (empty($empleados_sin_cuenta)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Todos los empleados registrados por GTH ya tienen cuenta en la plataforma.
                </div>
                <?php else: ?>

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/procesar_promover.php" method="POST">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Paso 1: Seleccionar empleado -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="bi bi-1-circle me-2"></i>Seleccionar Empleado</div>
                                <select name="empleado_gth_id" id="selectEmpleado" class="form-select" required>
                                    <option value="">-- Seleccionar empleado sin cuenta --</option>
                                    <?php foreach ($empleados_sin_cuenta as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($emp['nombre_completo']); ?>"
                                            data-nomina="<?php echo htmlspecialchars($emp['no_nomina'] ?? '-'); ?>"
                                            data-puesto="<?php echo htmlspecialchars($emp['puesto'] ?? '-'); ?>"
                                            data-depto="<?php echo htmlspecialchars($emp['departamento_nombre'] ?? '-'); ?>"
                                            data-depto-id="<?php echo $emp['departamento_id']; ?>"
                                            data-ingreso="<?php echo $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '-'; ?>"
                                            <?php echo (($form_data['empleado_gth_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['nombre_completo']); ?> - <?php echo htmlspecialchars($emp['no_nomina'] ?? 'Sin nomina'); ?> (<?php echo htmlspecialchars($emp['departamento_nombre'] ?? ''); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="emp-preview mt-3" id="empPreview">
                                    <div class="row">
                                        <div class="col-md-4"><strong>Nombre:</strong> <span id="prevNombre"></span></div>
                                        <div class="col-md-4"><strong>Nomina:</strong> <span id="prevNomina"></span></div>
                                        <div class="col-md-4"><strong>Depto:</strong> <span id="prevDepto"></span></div>
                                        <div class="col-md-4 mt-2"><strong>Puesto:</strong> <span id="prevPuesto"></span></div>
                                        <div class="col-md-4 mt-2"><strong>Ingreso:</strong> <span id="prevIngreso"></span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paso 2: Credenciales -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="bi bi-2-circle me-2"></i>Credenciales de Acceso</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre de Usuario</label>
                                        <input type="text" name="usuario" class="form-control" required maxlength="50"
                                               value="<?php echo htmlspecialchars($form_data['usuario'] ?? ''); ?>"
                                               pattern="[A-Za-z0-9_\-]+" title="Solo letras, numeros, guion y guion bajo"
                                               placeholder="Ej: GVSIS01">
                                        <small class="text-muted">Se convertira a mayusculas.</small>
                                    </div>
                                    <div class="col-md-6"></div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Contrasena</label>
                                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimo 8 caracteres">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Confirmar Contrasena</label>
                                        <input type="password" name="password_confirm" class="form-control" required minlength="8">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Como funciona</h6>
                                    <ol class="text-muted small ps-3">
                                        <li class="mb-2">Selecciona un empleado registrado por GTH que aun no tenga cuenta.</li>
                                        <li class="mb-2">Asigna un nombre de usuario y contrasena.</li>
                                        <li class="mb-2">Se creara un registro en la tabla de usuarios y se vinculara automaticamente con el empleado.</li>
                                        <li>Los datos del empleado (nombre, puesto, etc.) se mantienen sincronizados.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/gestion_usuarios/dashboard_usuarios.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Regresar</a>
                        <button type="submit" class="btn btn-success"><i class="bi bi-person-up me-1"></i> Promover a Usuario</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
    document.getElementById('selectEmpleado')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const prev = document.getElementById('empPreview');
        if (!this.value) { prev.style.display = 'none'; return; }
        document.getElementById('prevNombre').textContent = opt.dataset.nombre;
        document.getElementById('prevNomina').textContent = opt.dataset.nomina;
        document.getElementById('prevDepto').textContent = opt.dataset.depto;
        document.getElementById('prevPuesto').textContent = opt.dataset.puesto;
        document.getElementById('prevIngreso').textContent = opt.dataset.ingreso;
        prev.style.display = 'block';
    });
    </script>
</body>
</html>