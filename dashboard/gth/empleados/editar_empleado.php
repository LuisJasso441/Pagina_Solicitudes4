<?php
/**
 * Editar Empleado
 * dashboard/gth/empleados/editar_empleado.php
 * 
 * Siempre edita empleados_gth (tabla maestra)
 * Si esta vinculado a usuarios, se sincroniza automaticamente
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
$empleado_id = intval($_GET['id'] ?? 0);

if ($empleado_id <= 0) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'ID invalido.'];
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}

$stmt = $pdo->prepare("
    SELECT e.*, d.nombre AS departamento_nombre,
           u.id AS uid, u.usuario AS username
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    LEFT JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Empleado no encontrado.'];
    header('Location: ' . URL_BASE . 'dashboard/gth/empleados/listar_empleados.php'); exit;
}

$tiene_cuenta = !empty($empleado['usuario_id']);
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$form_data = $_SESSION['form_data_emp'] ?? $empleado;
$errores = $_SESSION['form_errors_emp'] ?? [];
unset($_SESSION['form_data_emp'], $_SESSION['form_errors_emp']);
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empleado - GTH</title>
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
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>

                <h3 class="mb-4">
                    <i class="bi bi-person-gear me-2"></i>Editar: <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                </h3>

                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show"><?php echo $alerta['mensaje']; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0"><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form action="<?php echo URL_BASE; ?>dashboard/gth/empleados/procesar_empleado.php" method="POST">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?php echo $empleado_id; ?>">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-section">
                                <div class="form-section-title"><i class="bi bi-person me-2"></i>Datos del Empleado</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Nombre Completo</label>
                                        <input type="text" name="nombre_completo" class="form-control" required maxlength="150" value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">No. Nomina</label>
                                        <input type="text" name="no_nomina" class="form-control" maxlength="20" value="<?php echo htmlspecialchars($form_data['no_nomina'] ?? ''); ?>">
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
                                        <input type="text" name="puesto" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($form_data['puesto'] ?? ''); ?>">
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
                                            <option value="lunes_sabado" <?php echo (($form_data['jornada'] ?? '') === 'lunes_sabado') ? 'selected' : ''; ?>>Lunes a Sabado</option>
                                            <option value="lunes_viernes" <?php echo (($form_data['jornada'] ?? '') === 'lunes_viernes') ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Observaciones</label>
                                        <textarea name="observaciones" class="form-control" rows="2" maxlength="500"><?php echo htmlspecialchars($form_data['observaciones'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Registro</h6>
                                    <dl class="mb-0" style="font-size:.85rem">
                                        <dt class="text-muted">Creado</dt>
                                        <dd><?php echo !empty($empleado['created_at']) ? date('d/m/Y H:i', strtotime($empleado['created_at'])) : '-'; ?></dd>
                                        <dt class="text-muted">Actualizado</dt>
                                        <dd class="mb-0"><?php echo !empty($empleado['updated_at']) ? date('d/m/Y H:i', strtotime($empleado['updated_at'])) : 'Nunca'; ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/gth/empleados/listar_empleados.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Regresar</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
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