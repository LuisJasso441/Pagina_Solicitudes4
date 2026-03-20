<?php
/**
 * Crear Empleado (sin cuenta en plataforma)
 * dashboard/gth/empleados/crear_empleado.php
 */
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../config/database.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
if (!in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad'])) {
    establecer_alerta('error', 'No tienes permisos.');
    header('Location: ' . URL_BASE . 'index.php'); exit;
}

$pdo = conectarDB();
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$form_data = $_SESSION['form_data_emp'] ?? [];
$errores = $_SESSION['form_errors_emp'] ?? [];
unset($_SESSION['form_data_emp'], $_SESSION['form_errors_emp']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Empleado - GTH</title>
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
        .form-section { background:#f8f9fa; border:1px solid #dee2e6; border-radius:.375rem; padding:1rem; margin-bottom:1rem; }
        .form-section-title { font-weight:600; font-size:.95rem; margin-bottom:.75rem; color:#495057; border-bottom:1px solid #dee2e6; padding-bottom:.5rem; }
        .required::after { content:" *"; color:#dc3545; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php'; ?>
        <main class="main-content">
            <div class="content-wrapper">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/gth/dashboard_gth.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php">Empleados</a></li>
                        <li class="breadcrumb-item active">Crear Empleado</li>
                    </ol>
                </nav>

                <h3 class="mb-4"><i class="bi bi-person-plus me-2"></i>Nuevo Empleado</h3>

                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>Error:</strong>
                    <ul class="mb-0 mt-2"><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form action="<?php echo URL_BASE; ?>dashboard/gth/empleados/procesar_empleado.php" method="POST">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-section">
                                <div class="form-section-title"><i class="bi bi-person me-2"></i>Datos del Empleado</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>" placeholder="Ej: Juan Carlos Lopez">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">No. Nomina</label>
                                        <input type="text" name="no_nomina" class="form-control" maxlength="20" value="<?php echo htmlspecialchars($form_data['no_nomina'] ?? ''); ?>" placeholder="Ej: 1234">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Departamento</label>
                                        <select name="departamento_id" class="form-select" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo (($form_data['departamento_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Puesto</label>
                                        <input type="text" name="puesto" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($form_data['puesto'] ?? ''); ?>" placeholder="Ej: Operador de Planta">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Fecha de Ingreso</label>
                                        <input type="date" name="fecha_ingreso" class="form-control" value="<?php echo htmlspecialchars($form_data['fecha_ingreso'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Periodo de Pago</label>
                                        <select name="periodo_pago" class="form-select">
                                            <option value="">Seleccionar...</option>
                                            <option value="quincenal" <?php echo (($form_data['periodo_pago'] ?? '') === 'quincenal') ? 'selected' : ''; ?>>Quincenal</option>
                                            <option value="semanal" <?php echo (($form_data['periodo_pago'] ?? '') === 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Empresa</label>
                                        <select name="empresa" class="form-select">
                                            <option value="">Seleccionar...</option>
                                            <option value="resimex" <?php echo (($form_data['empresa'] ?? '') === 'resimex') ? 'selected' : ''; ?>>Resimex</option>
                                            <option value="carganova" <?php echo (($form_data['empresa'] ?? '') === 'carganova') ? 'selected' : ''; ?>>Carganova</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Jornada</label>
                                        <select name="jornada" class="form-select">
                                            <option value="lunes_sabado" <?php echo (($form_data['jornada'] ?? 'lunes_sabado') === 'lunes_sabado') ? 'selected' : ''; ?>>Lunes a Sabado</option>
                                            <option value="lunes_viernes" <?php echo (($form_data['jornada'] ?? '') === 'lunes_viernes') ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Observaciones</label>
                                        <textarea name="observaciones" class="form-control" rows="2" maxlength="500" placeholder="Notas adicionales..."><?php echo htmlspecialchars($form_data['observaciones'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Informacion</h6>
                                    <p class="text-muted small">Este registro es para empleados que <strong>no tendran cuenta</strong> en la plataforma. La informacion solo sera gestionada por GTH/Contabilidad.</p>
                                    <p class="text-muted small mb-0">Si el empleado necesita acceso a la plataforma, debe crearse como <strong>usuario</strong> por el departamento de Sistemas.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Cancelar</a>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Crear Empleado</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const hb=document.querySelector('.hamburger-btn'),sb=document.querySelector('.sidebar'),ov=document.querySelector('.sidebar-overlay');
    if(hb&&sb){hb.addEventListener('click',function(){sb.classList.toggle('active');if(ov)ov.classList.toggle('active');});}
    if(ov){ov.addEventListener('click',function(){sb.classList.remove('active');this.classList.remove('active');});}
    </script>
</body>
</html>