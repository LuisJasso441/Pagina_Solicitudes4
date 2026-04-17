<?php
/**
 * Nueva Solicitud de Vacaciones - MANUAL
 * dashboard/vacaciones/nueva_solicitud_manual.php
 *
 * Solo accesible por Admin de Area (es_admin_area = 1)
 * Permite crear solicitudes para empleados SIN cuenta en el sistema.
 * ACTUALIZADO: Dropdown de empleados desde empleados_gth + auto-fill
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

if (empty($_SESSION['es_admin_area'])) {
    $_SESSION['alerta'] = ['tipo' => 'danger', 'mensaje' => 'Solo los Administradores de Area pueden crear solicitudes manuales.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/vacaciones_admin.php');
    exit;
}

$pdo = conectarDB();

// Obtener empleados sin cuenta para el dropdown
$stmt_emp_sin_cuenta = $pdo->prepare("
    SELECT e.id, e.nombre_completo, e.no_nomina, e.puesto,
           e.departamento_id, d.nombre AS departamento_nombre
    FROM empleados_gth e
    LEFT JOIN departamentos d ON e.departamento_id = d.id
    WHERE e.activo = 1 AND e.usuario_id IS NULL
    ORDER BY e.nombre_completo ASC
");
$stmt_emp_sin_cuenta->execute();
$empleados_sin_cuenta = $stmt_emp_sin_cuenta->fetchAll(PDO::FETCH_ASSOC);

$stmt_deptos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre");
$departamentos_lista = $stmt_deptos->fetchAll(PDO::FETCH_ASSOC);

$form_data = $_SESSION['form_data_vacaciones_manual'] ?? [];
unset($_SESSION['form_data_vacaciones_manual']);
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$departamento_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_ti = ($departamento_codigo === 'sistemas' || $departamento_codigo === 'ti');
$es_mantenimiento = ($departamento_codigo === 'mantenimiento');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Manual de Vacaciones - <?php echo NOMBRE_SISTEMA; ?></title>
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
    
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .manual-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
            vertical-align: middle;
        }
        .emp-section {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
        }
        .emp-section .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: #198754;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }
        .firma-canvas-container {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            overflow: hidden;
        }
        .firma-linea {
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
            border-top: 1px solid #adb5bd;
            padding-top: 4px;
            margin: 0 20px;
        }
        .festivo-info {
            display: none;
            padding: 8px 12px;
            background: #fff3cd;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #856404;
            margin-top: 8px;
        }
        .info-empleado-card {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
            display: none;
        }
        .info-empleado-card .info-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.88rem;
        }
        .info-empleado-card .info-item .label {
            color: #6c757d;
            font-weight: 500;
        }
        .info-empleado-card .info-item .value {
            font-weight: 600;
            color: #1f2937;
        }
        body[data-theme="dark"] .emp-section {
            background: var(--card-bg, #1e293b);
            border-color: var(--border-color, #334155);
        }
        body[data-theme="dark"] .info-empleado-card {
            background: linear-gradient(135deg, #064e3b33, #06543833);
            border-color: #065f46;
        }
        body[data-theme="dark"] .info-empleado-card .info-item .value {
            color: #e2e8f0;
        }
        body[data-theme="dark"] .firma-canvas-container {
            background: #1e293b;
            border-color: #475569;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php
        if ($es_mantenimiento) {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_gth.php';
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>
        <main class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1>
                                <i class="bi bi-calendar-plus"></i> Solicitud Manual de Vacaciones
                                <span class="manual-badge ms-2"><i class="bi bi-pencil-square me-1"></i>Manual</span>
                            </h1>
                            <p class="text-muted mb-0" style="font-size:0.85rem">
                                Para empleados sin cuenta en la plataforma. Creada por: <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                            </p>
                        </div>
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Regresar
                        </a>
                    </div>
                </div>

                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $alerta['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (empty($empleados_sin_cuenta)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No hay empleados sin cuenta de usuario registrados en el sistema. 
                    Para crear solicitudes manuales, primero registre empleados en el módulo de GTH.
                </div>
                <?php else: ?>

                <form id="formSolicitudManual" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php">
                    <input type="hidden" name="accion" value="crear_manual">
                    <input type="hidden" name="firma_empleado_manual" id="firma_empleado_manual" value="">
                    <input type="hidden" name="firma_admin" id="firma_admin" value="">

                    <!-- SECCION 1: DATOS DEL EMPLEADO -->
                    <div class="emp-section">
                        <div class="section-title">
                            <i class="bi bi-person-badge me-2"></i>Datos del Empleado
                        </div>
                        <div class="row g-3">
                            <!-- DROPDOWN DE EMPLEADOS SIN CUENTA -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Seleccionar Empleado <span class="text-danger">*</span></label>
                                <select name="empleado_gth_id" id="empleado_gth_id" class="form-select" required>
                                    <option value="">-- Seleccione un empleado --</option>
                                    <?php foreach ($empleados_sin_cuenta as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"
                                            <?php echo (($form_data['empleado_gth_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                        <?php if (!empty($emp['no_nomina'])): ?> (<?php echo htmlspecialchars($emp['no_nomina']); ?>)<?php endif; ?>
                                        — <?php echo htmlspecialchars($emp['departamento_nombre'] ?? 'Sin depto'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Solo se muestran empleados registrados en GTH sin cuenta de plataforma</small>
                                <!-- Campo oculto para enviar el nombre al procesador -->
                                <input type="hidden" name="nombre_manual" id="nombre_manual" value="<?php echo htmlspecialchars($form_data['nombre_manual'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">No. Nómina</label>
                                <input type="text" name="no_nomina_manual" id="no_nomina_manual" class="form-control" maxlength="20" readonly
                                       value="<?php echo htmlspecialchars($form_data['no_nomina_manual'] ?? ''); ?>"
                                       placeholder="Se llena automáticamente">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                                <select name="departamento_id_manual" id="departamento_id_manual" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($departamentos_lista as $depto): ?>
                                    <option value="<?php echo $depto['id']; ?>" <?php echo (($form_data['departamento_id_manual'] ?? '') == $depto['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($depto['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Puesto</label>
                                <input type="text" name="puesto_manual" id="puesto_manual" class="form-control" maxlength="100" readonly
                                       value="<?php echo htmlspecialchars($form_data['puesto_manual'] ?? ''); ?>"
                                       placeholder="Se llena automáticamente">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fecha de Ingreso <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_ingreso_manual" id="fecha_ingreso_manual" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_ingreso_manual'] ?? ''); ?>"
                                       max="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Periodo de Pago</label>
                                <select name="periodo_pago_manual" id="periodo_pago_manual" class="form-select" disabled>
                                    <option value="">Seleccionar...</option>
                                    <option value="quincenal" <?php echo (($form_data['periodo_pago_manual'] ?? '') === 'quincenal') ? 'selected' : ''; ?>>Quincenal</option>
                                    <option value="semanal" <?php echo (($form_data['periodo_pago_manual'] ?? '') === 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                </select>
                                <!-- Hidden para enviar valor cuando está disabled -->
                                <input type="hidden" name="periodo_pago_manual" id="periodo_pago_manual_hidden" value="<?php echo htmlspecialchars($form_data['periodo_pago_manual'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Empresa</label>
                                <select name="empresa_manual" id="empresa_manual" class="form-select" disabled>
                                    <option value="">Seleccionar...</option>
                                    <option value="verdenlabs" <?php echo (($form_data['empresa_manual'] ?? '') === 'verdenlabs') ? 'selected' : ''; ?>>VerdenLabs</option>
                                    <option value="resimex" <?php echo (($form_data['empresa_manual'] ?? '') === 'resimex') ? 'selected' : ''; ?>>Resimex</option>
                                    <option value="carganova" <?php echo (($form_data['empresa_manual'] ?? '') === 'carganova') ? 'selected' : ''; ?>>Carganova</option>
                                </select>
                                <input type="hidden" name="empresa_manual" id="empresa_manual_hidden" value="<?php echo htmlspecialchars($form_data['empresa_manual'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Jornada</label>
                                <select name="jornada_manual" id="jornada_manual" class="form-select" disabled>
                                    <option value="lunes_sabado" <?php echo (($form_data['jornada_manual'] ?? 'lunes_sabado') === 'lunes_sabado') ? 'selected' : ''; ?>>Lunes a Sábado</option>
                                    <option value="lunes_viernes" <?php echo (($form_data['jornada_manual'] ?? '') === 'lunes_viernes') ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                </select>
                                <input type="hidden" name="jornada_manual" id="jornada_manual_hidden" value="<?php echo htmlspecialchars($form_data['jornada_manual'] ?? 'lunes_sabado'); ?>">
                            </div>
                        </div>

                        <!-- Info calculada del empleado seleccionado -->
                        <div class="info-empleado-card" id="infoEmpleadoCard">
                            <h6 class="fw-bold mb-2"><i class="bi bi-person-lines-fill me-1 text-success"></i> Información del empleado seleccionado</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="info-item">
                                        <span class="label">Antigüedad</span>
                                        <span class="value" id="info_antiguedad">--</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-item">
                                        <span class="label">Días por LFT</span>
                                        <span class="value text-info" id="info_dias_lft">--</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-item">
                                        <span class="label">Días ya tomados</span>
                                        <span class="value text-warning" id="info_dias_tomados">0</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-item">
                                        <span class="label">Días disponibles</span>
                                        <span class="value text-success" id="info_dias_disponibles">--</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campo oculto días ya tomados -->
                        <input type="hidden" name="dias_ya_tomados" id="dias_ya_tomados" value="<?php echo htmlspecialchars($form_data['dias_ya_tomados'] ?? '0'); ?>">
                    </div>

                    <!-- SECCION 2: PERIODO DE VACACIONES -->
                    <div class="emp-section">
                        <div class="section-title">
                            <i class="bi bi-calendar-range me-2"></i>Periodo de Vacaciones
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fecha Inicio <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_inicio'] ?? ''); ?>"
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fecha Fin <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_fin'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fecha de Regreso</label>
                                <input type="date" name="fecha_regreso" id="fecha_regreso" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['fecha_regreso'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Días hábiles solicitados</label>
                                <div id="dias_habiles_calc" class="fw-bold text-primary fs-5">--</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Saldo después de solicitud</label>
                                <div id="saldo_despues" class="fw-bold fs-5">--</div>
                            </div>
                        </div>
                        <div id="festivoInfo" class="festivo-info">
                            <i class="bi bi-calendar-x me-1"></i> <span id="festivoTexto"></span>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label fw-semibold">Motivo (opcional)</label>
                            <textarea name="motivo" class="form-control" rows="2" maxlength="500" placeholder="Motivo de las vacaciones..."><?php echo htmlspecialchars($form_data['motivo'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- SECCION 3: FIRMAS -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bi bi-pen me-2"></i>Firmas</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="firma-canvas-container" id="firmaEmpleadoContainer">
                                        <div id="firmaEmpleado" style="width:100%;height:120px"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center my-2">
                                        <small id="firmaEmpleadoEstado" class="text-muted"><i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente</small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirmaEmpleado"><i class="bi bi-eraser"></i> Limpiar</button>
                                    </div>
                                    <div class="firma-linea">Nombre y Firma del Solicitante</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="firma-canvas-container" id="firmaAdminContainer">
                                        <div id="firmaAdmin" style="width:100%;height:120px"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center my-2">
                                        <small id="firmaAdminEstado" class="text-muted"><i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente</small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirmaAdmin"><i class="bi bi-eraser"></i> Limpiar</button>
                                    </div>
                                    <div class="firma-linea">Firma del Admin de Área (<?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONES -->
                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success" id="btnEnviar">
                            <i class="bi bi-send me-1"></i> Crear Solicitud Manual
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // =====================================================
        // SIDEBAR TOGGLE
        // =====================================================
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
                this.setAttribute('aria-expanded', sidebar.classList.contains('active'));
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                this.classList.remove('active');
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
            });
        }

        // =====================================================
        // VARIABLES
        // =====================================================
        const URL_BASE = '<?php echo URL_BASE; ?>';
        const selectEmpleado = document.getElementById('empleado_gth_id');
        const nombreManualInput = document.getElementById('nombre_manual');
        const nominaInput = document.getElementById('no_nomina_manual');
        const deptoSelect = document.getElementById('departamento_id_manual');
        const puestoInput = document.getElementById('puesto_manual');
        const fechaIngresoInput = document.getElementById('fecha_ingreso_manual');
        const periodoPagoSelect = document.getElementById('periodo_pago_manual');
        const periodoPagoHidden = document.getElementById('periodo_pago_manual_hidden');
        const empresaSelect = document.getElementById('empresa_manual');
        const empresaHidden = document.getElementById('empresa_manual_hidden');
        const jornadaSelect = document.getElementById('jornada_manual');
        const jornadaHidden = document.getElementById('jornada_manual_hidden');
        const diasYaTomadosInput = document.getElementById('dias_ya_tomados');
        const infoCard = document.getElementById('infoEmpleadoCard');
        const infoAntiguedad = document.getElementById('info_antiguedad');
        const infoDiasLft = document.getElementById('info_dias_lft');
        const infoDiasTomados = document.getElementById('info_dias_tomados');
        const infoDiasDisponibles = document.getElementById('info_dias_disponibles');

        let festivos_fechas = [];
        let empleadoData = null;

        // =====================================================
        // DROPDOWN: Al seleccionar un empleado, cargar sus datos
        // =====================================================
        selectEmpleado.addEventListener('change', function() {
            const empId = this.value;
            
            if (!empId) {
                limpiarDatosEmpleado();
                return;
            }

            // Llamar al API para obtener datos completos del empleado
            fetch(URL_BASE + 'api/vacaciones/empleados_sin_cuenta.php?id=' + empId)
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                    }
                    return r.text();
                })
                .then(text => {
                    // Intentar parsear JSON, si falla mostrar el texto crudo
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch(e) {
                        console.error('Respuesta no es JSON:', text);
                        alert('Error del servidor. Revisa la consola (F12) para más detalles.');
                        limpiarDatosEmpleado();
                        return;
                    }

                    if (data.error) {
                        alert(data.error);
                        limpiarDatosEmpleado();
                        return;
                    }

                    const emp = data.empleado;
                    empleadoData = emp;

                    // Rellenar campos
                    nombreManualInput.value = emp.nombre_completo || '';
                    nominaInput.value = emp.no_nomina || '';
                    puestoInput.value = emp.puesto || '';
                    
                    // Departamento
                    if (emp.departamento_id) {
                        deptoSelect.value = emp.departamento_id;
                    }

                    // Fecha ingreso
                    fechaIngresoInput.value = emp.fecha_ingreso || '';

                    // Periodo de pago
                    if (emp.periodo_pago) {
                        periodoPagoSelect.value = emp.periodo_pago;
                        periodoPagoHidden.value = emp.periodo_pago;
                    } else {
                        periodoPagoSelect.value = '';
                        periodoPagoHidden.value = '';
                    }

                    // Empresa
                    if (emp.empresa) {
                        empresaSelect.value = emp.empresa;
                        empresaHidden.value = emp.empresa;
                    } else {
                        empresaSelect.value = '';
                        empresaHidden.value = '';
                    }

                    // Jornada
                    if (emp.jornada) {
                        jornadaSelect.value = emp.jornada;
                        jornadaHidden.value = emp.jornada;
                    } else {
                        jornadaSelect.value = 'lunes_sabado';
                        jornadaHidden.value = 'lunes_sabado';
                    }

                    // Días ya tomados
                    diasYaTomadosInput.value = emp.dias_tomados || 0;

                    // Info card
                    infoAntiguedad.textContent = emp.antiguedad_texto || '--';
                    infoDiasLft.textContent = emp.dias_lft || '--';
                    infoDiasTomados.textContent = emp.dias_tomados || '0';
                    const disponibles = Math.max(0, (emp.dias_lft || 0) - (emp.dias_tomados || 0));
                    infoDiasDisponibles.textContent = disponibles;
                    infoCard.style.display = 'block';

                    // Recargar festivos con la jornada del empleado
                    cargarFestivos(emp.jornada || 'lunes_sabado');
                    
                    // Recalcular días si ya hay fechas
                    calcularDiasHabiles();
                })
                .catch(err => {
                    console.error('Error cargando empleado:', err);
                    alert('Error al cargar datos del empleado');
                });
        });

        function limpiarDatosEmpleado() {
            empleadoData = null;
            nombreManualInput.value = '';
            nominaInput.value = '';
            puestoInput.value = '';
            deptoSelect.value = '';
            fechaIngresoInput.value = '';
            periodoPagoSelect.value = '';
            periodoPagoHidden.value = '';
            empresaSelect.value = '';
            empresaHidden.value = '';
            jornadaSelect.value = 'lunes_sabado';
            jornadaHidden.value = 'lunes_sabado';
            diasYaTomadosInput.value = '0';
            infoCard.style.display = 'none';
            infoAntiguedad.textContent = '--';
            infoDiasLft.textContent = '--';
            infoDiasTomados.textContent = '0';
            infoDiasDisponibles.textContent = '--';
            document.getElementById('dias_habiles_calc').textContent = '--';
            document.getElementById('saldo_despues').textContent = '--';
        }

        // =====================================================
        // FESTIVOS
        // =====================================================
        function cargarFestivos(jornada) {
            const anio = new Date().getFullYear();
            let url = URL_BASE + 'api/vacaciones/dias_festivos.php?anio=' + anio;
            
            // Si tenemos el empleado_gth_id, usarlo para jornada
            const empId = selectEmpleado.value;
            if (empId) {
                url += '&empleado_gth_id=' + empId;
            }

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    festivos_fechas = data.festivos_fechas || [];
                    calcularDiasHabiles();
                })
                .catch(err => console.error('Error festivos:', err));
        }

        // Cargar festivos iniciales
        cargarFestivos('lunes_sabado');

        // =====================================================
        // CALCULO DE DIAS HABILES
        // =====================================================
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');

        fechaInicioInput.addEventListener('change', calcularDiasHabiles);
        fechaFinInput.addEventListener('change', calcularDiasHabiles);

        function calcularDiasHabiles() {
            const inicio = fechaInicioInput.value;
            const fin = fechaFinInput.value;
            const diasCalcEl = document.getElementById('dias_habiles_calc');
            const saldoDespuesEl = document.getElementById('saldo_despues');
            const festivoInfoEl = document.getElementById('festivoInfo');
            const festivoTextoEl = document.getElementById('festivoTexto');

            if (!inicio || !fin) {
                diasCalcEl.textContent = '--';
                saldoDespuesEl.textContent = '--';
                festivoInfoEl.style.display = 'none';
                return;
            }

            if (fin < inicio) {
                diasCalcEl.innerHTML = '<span class="text-danger">Fecha fin debe ser posterior</span>';
                saldoDespuesEl.textContent = '--';
                return;
            }

            // Determinar jornada
            const jornada = jornadaHidden.value || 'lunes_sabado';
            const incluyeSabado = (jornada === 'lunes_sabado');

            let dias = 0;
            let festivosEnRango = [];
            let current = new Date(inicio + 'T00:00:00');
            const end = new Date(fin + 'T00:00:00');

            while (current <= end) {
                const dow = current.getDay(); // 0=dom, 6=sab
                const fechaStr = current.toISOString().split('T')[0];

                if (dow === 0) {
                    // Domingo: nunca
                } else if (dow === 6 && !incluyeSabado) {
                    // Sábado sin jornada L-S
                } else if (festivos_fechas.includes(fechaStr)) {
                    festivosEnRango.push(fechaStr);
                } else {
                    dias++;
                }
                current.setDate(current.getDate() + 1);
            }

            diasCalcEl.textContent = dias + ' día(s)';

            // Mostrar festivos en rango
            if (festivosEnRango.length > 0) {
                festivoTextoEl.textContent = festivosEnRango.length + ' día(s) festivo(s) excluido(s) del conteo';
                festivoInfoEl.style.display = 'block';
            } else {
                festivoInfoEl.style.display = 'none';
            }

            // Calcular saldo
            if (empleadoData) {
                const disponibles = Math.max(0, (empleadoData.dias_lft || 0) - (empleadoData.dias_tomados || 0));
                const saldo = disponibles - dias;
                saldoDespuesEl.textContent = saldo + ' día(s)';
                saldoDespuesEl.className = 'fw-bold fs-5 ' + (saldo < 0 ? 'text-danger' : 'text-success');
            } else {
                saldoDespuesEl.textContent = '--';
            }
        }

        // =====================================================
        // FIRMAS (jSignature)
        // =====================================================
        let firmaEmpleadoOk = false;
        let firmaAdminOk = false;

        const $firmaEmp = $('#firmaEmpleado').jSignature({ color: '#000', lineWidth: 2, 'decor-color': 'transparent', width: '100%', height: 120 });
        const $firmaAdm = $('#firmaAdmin').jSignature({ color: '#000', lineWidth: 2, 'decor-color': 'transparent', width: '100%', height: 120 });

        // Detectar firma (base30 solo para saber si firmó)
        $firmaEmp.bind('change', function() {
            const d = $firmaEmp.jSignature('getData', 'base30');
            if (d && d.length > 1 && d[1].length > 0) {
                firmaEmpleadoOk = true;
                document.getElementById('firmaEmpleadoEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firmado';
            }
        });

        $firmaAdm.bind('change', function() {
            const d = $firmaAdm.jSignature('getData', 'base30');
            if (d && d.length > 1 && d[1].length > 0) {
                firmaAdminOk = true;
                document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firmado';
            }
        });

        document.getElementById('btnLimpiarFirmaEmpleado').addEventListener('click', function() {
            $firmaEmp.jSignature('reset');
            firmaEmpleadoOk = false;
            document.getElementById('firma_empleado_manual').value = '';
            document.getElementById('firmaEmpleadoEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente';
        });

        document.getElementById('btnLimpiarFirmaAdmin').addEventListener('click', function() {
            $firmaAdm.jSignature('reset');
            firmaAdminOk = false;
            document.getElementById('firma_admin').value = '';
            document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente';
        });

        // =====================================================
        // VALIDACIÓN Y CAPTURA AL ENVIAR
        // =====================================================
        document.getElementById('formSolicitudManual')?.addEventListener('submit', function(e) {
            if (!selectEmpleado.value) {
                e.preventDefault();
                alert('Debe seleccionar un empleado.');
                return false;
            }
            if (!firmaAdminOk) {
                e.preventDefault();
                alert('La firma del administrador es obligatoria.');
                return false;
            }

            // Capturar firmas en formato image (data:image/png;base64,...)
            const fdEmp = $firmaEmp.jSignature('getData', 'image');
            if (fdEmp && fdEmp.length > 1) {
                document.getElementById('firma_empleado_manual').value = 'data:' + fdEmp[0] + ',' + fdEmp[1];
            }

            const fdAdm = $firmaAdm.jSignature('getData', 'image');
            if (fdAdm && fdAdm.length > 1) {
                document.getElementById('firma_admin').value = 'data:' + fdAdm[0] + ',' + fdAdm[1];
            } else {
                e.preventDefault();
                alert('Error al capturar la firma del administrador.');
                return false;
            }

            // Deshabilitar botón para evitar doble envío
            const btn = document.getElementById('btnEnviar');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Creando...';
        });

        // =====================================================
        // Si hay form_data previo (después de error), re-trigger
        // =====================================================
        <?php if (!empty($form_data['empleado_gth_id'])): ?>
        selectEmpleado.dispatchEvent(new Event('change'));
        <?php endif; ?>
    });
    </script>
</body>
</html>