<?php
/**
 * Nueva Solicitud de Vacaciones - MANUAL
 * dashboard/vacaciones/nueva_solicitud_manual.php
 *
 * Solo accesible por Admin de Area (es_admin_area = 1)
 * Permite crear solicitudes para empleados SIN cuenta en el sistema.
 * ACTUALIZADO: Dias festivos MX + jornada L-V/L-S + dias periodo anterior
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
        .manual-badge { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .emp-section { background: #fffbeb; border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .emp-section .section-title { color: #92400e; font-weight: 700; font-size: 1rem; margin-bottom: 15px; }
        .vac-section { background: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .vac-section .section-title { color: #166534; font-weight: 700; font-size: 1rem; margin-bottom: 15px; }
        .dias-highlight { font-size: 1.2rem; font-weight: 700; color: #198754; }
        .dias-highlight.excedido { color: #dc3545; }
        .firma-canvas-container { border: 2px dashed #ced4da; border-radius: 8px; background: #fff; min-height: 120px; }
        .firma-canvas-container.firmado { border-color: #198754; border-style: solid; }
        .firma-linea { border-top: 2px solid #000; padding-top: 6px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; text-align: center; }
        .festivo-info{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:8px 12px;font-size:.8rem;margin-top:8px;display:none}
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
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nombre Completo del Empleado <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_manual" class="form-control" required maxlength="150"
                                       value="<?php echo htmlspecialchars($form_data['nombre_manual'] ?? ''); ?>"
                                       placeholder="Ej: Juan Carlos Hernandez Lopez">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">No. Nomina</label>
                                <input type="text" name="no_nomina_manual" class="form-control" maxlength="20"
                                       value="<?php echo htmlspecialchars($form_data['no_nomina_manual'] ?? ''); ?>"
                                       placeholder="Ej: 1234">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                                <select name="departamento_id_manual" class="form-select" required>
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
                                <input type="text" name="puesto_manual" class="form-control" maxlength="100"
                                       value="<?php echo htmlspecialchars($form_data['puesto_manual'] ?? ''); ?>"
                                       placeholder="Ej: Operador de Planta">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Fecha de Ingreso <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_ingreso_manual" id="fecha_ingreso_manual" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_ingreso_manual'] ?? ''); ?>"
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Periodo</label>
                                <select name="periodo_pago_manual" class="form-select">
                                    <option value="">--</option>
                                    <option value="quincenal" <?php echo (($form_data['periodo_pago_manual'] ?? '') === 'quincenal') ? 'selected' : ''; ?>>Quincenal</option>
                                    <option value="semanal" <?php echo (($form_data['periodo_pago_manual'] ?? '') === 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Empresa</label>
                                <select name="empresa_manual" class="form-select">
                                    <option value="">--</option>
                                    <option value="resimex" <?php echo (($form_data['empresa_manual'] ?? '') === 'resimex') ? 'selected' : ''; ?>>Resimex</option>
                                    <option value="carganova" <?php echo (($form_data['empresa_manual'] ?? '') === 'carganova') ? 'selected' : ''; ?>>Carganova</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Jornada</label>
                                <select name="jornada_manual" id="jornada_manual" class="form-select">
                                    <option value="lunes_sabado" <?php echo (($form_data['jornada_manual'] ?? 'lunes_sabado') === 'lunes_sabado') ? 'selected' : ''; ?>>Lunes a S&aacute;bado</option>
                                    <option value="lunes_viernes" <?php echo (($form_data['jornada_manual'] ?? '') === 'lunes_viernes') ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                </select>
                            </div>
                        </div>

                        <!-- Info calculada -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-3">
                                <label class="form-label text-muted small">Antiguedad</label>
                                <div id="info_antiguedad" class="fw-bold text-primary">--</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small">Dias por LFT</label>
                                <div id="info_dias_lft" class="fw-bold text-info">--</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Dias ya tomados <span class="text-danger">*</span></label>
                                <input type="number" name="dias_ya_tomados" id="dias_ya_tomados" class="form-control" min="0" value="<?php echo htmlspecialchars($form_data['dias_ya_tomados'] ?? '0'); ?>" required>
                                <small class="text-muted">Dias que ya tomo fuera del sistema</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small">Dias Disponibles</label>
                                <div id="info_dias_disponibles" class="fw-bold text-success fs-5">--</div>
                            </div>
                        </div>

                        <!-- Dias pendientes del periodo pasado -->
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">&iquest;Tiene d&iacute;as pendientes de su periodo pasado?</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tiene_dias_periodo_anterior" id="diasAntNo" value="no" <?php echo (($form_data['tiene_dias_periodo_anterior'] ?? 'no') === 'no') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="diasAntNo">No</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tiene_dias_periodo_anterior" id="diasAntSi" value="si" <?php echo (($form_data['tiene_dias_periodo_anterior'] ?? '') === 'si') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="diasAntSi">S&iacute;</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" id="campoDiasAnteriores" style="display:none">
                                <label class="form-label fw-semibold">D&iacute;as pendientes del periodo anterior</label>
                                <input type="number" name="dias_periodo_anterior" id="dias_periodo_anterior" class="form-control" min="1" max="99" value="<?php echo htmlspecialchars($form_data['dias_periodo_anterior'] ?? ''); ?>" placeholder="Ej: 4">
                                <small class="text-muted">Estos d&iacute;as se tomar&aacute;n de primera mano</small>
                            </div>
                            <div class="col-md-8" id="infoDiasAnteriores" style="display:none">
                                <div class="alert alert-info py-2 mb-0 mt-4" style="font-size:.82rem">
                                    <i class="bi bi-info-circle me-1"></i> 
                                    El sistema tomar&aacute; primero los <strong><span id="txtDiasAnt">0</span> d&iacute;as</strong> del periodo anterior. 
                                    Solo podr&aacute; solicitar esa cantidad en esta solicitud. Para usar d&iacute;as del periodo actual, deber&aacute; crear otra solicitud.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCION 2: DIAS DE VACACIONES -->
                    <div class="vac-section">
                        <div class="section-title">
                            <i class="bi bi-calendar-range me-2"></i>Dias de Vacaciones
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Del: <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_inicio'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Al: <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['fecha_fin'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Regresa:</label>
                                <input type="date" name="fecha_regreso" id="fecha_regreso" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Dias Solicitados</label>
                                <div class="dias-highlight fs-4" id="diasSolicitados">0</div>
                            </div>
                        </div>
                        <div id="alertaExceso" class="alert alert-danger py-2" style="display:none;font-size:.85rem">
                            <i class="bi bi-exclamation-triangle me-1"></i> Excede los dias disponibles.
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
                                    <div class="firma-linea">Nombre y Firma del Jefe Inmediato / Admin de Area</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONES -->
                    <div class="d-flex justify-content-between mb-4">
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_admin.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="btnEnviar" disabled>
                            <i class="bi bi-send me-1"></i> Crear Solicitud
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let diasHabiles = 0, diasDisponibles = 0, cumpleUnAnio = false;

        // Festivos desde API
        let diasFestivos = [];
        let festivosDetalle = [];

        fetch('<?php echo URL_BASE; ?>api/dias_festivos.php?anio=' + new Date().getFullYear())
            .then(r => r.json())
            .then(data => {
                diasFestivos = data.festivos_fechas || [];
                festivosDetalle = data.festivos || [];
            })
            .catch(() => {});

        // Tabla LFT (Art. 76, Reforma 2023)
        const tablaLFT = {1:12,2:14,3:16,4:18,5:20,6:22,7:22,8:22,9:22,10:22,11:24,12:24,13:24,14:24,15:24,16:26,17:26,18:26,19:26,20:26};

        function diasLFT(anios) {
            if (anios <= 0) return 0;
            if (anios <= 20) return tablaLFT[anios] || 22;
            return 26 + Math.floor((anios - 16) / 5) * 2;
        }

        // Helper: formato fecha YYYY-MM-DD
        function fmtFecha(d) {
            return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        }

        // Helper: jornada (default L-S para manual)
        function getJornada() {
            return document.getElementById('jornada_manual')?.value || 'lunes_sabado';
        }

        // Helper: es dia habil?
        function esDiaHabil(fecha) {
            const dow = fecha.getDay();
            const fechaStr = fmtFecha(fecha);
            const jornada = getJornada();
            if (dow === 0) return false;
            if (jornada === 'lunes_viernes' && dow === 6) return false;
            if (diasFestivos.includes(fechaStr)) return false;
            return true;
        }

        // =====================================================
        // Calcular info del empleado al cambiar fecha_ingreso
        // =====================================================
        function calcularInfoEmpleado() {
            const fi = document.getElementById('fecha_ingreso_manual').value;
            const elAnt = document.getElementById('info_antiguedad');
            const elLFT = document.getElementById('info_dias_lft');
            const elDisp = document.getElementById('info_dias_disponibles');

            if (!fi) { elAnt.textContent = '--'; elLFT.textContent = '--'; elDisp.textContent = '--'; diasDisponibles = 0; validar(); return; }

            const ingreso = new Date(fi + 'T00:00:00');
            const hoy = new Date();
            let anios = hoy.getFullYear() - ingreso.getFullYear();
            let meses = hoy.getMonth() - ingreso.getMonth();
            let dias_diff = hoy.getDate() - ingreso.getDate();
            if (dias_diff < 0) { meses--; dias_diff += 30; }
            if (meses < 0) { anios--; meses += 12; }

            // Si no ha cumplido 1 anio, no tiene derecho a vacaciones
            if (anios < 1) {
                elAnt.textContent = anios + ' a\u00f1o' + (anios !== 1 ? 's' : '') + ', ' + meses + ' mes' + (meses !== 1 ? 'es' : '');
                elLFT.innerHTML = '<span class="text-danger">Sin derecho</span>';
                elDisp.innerHTML = '<span class="text-danger">0 d\u00edas</span>';
                diasDisponibles = 0;

                let alertaAnio = document.getElementById('alertaNoAnio');
                if (!alertaAnio) {
                    alertaAnio = document.createElement('div');
                    alertaAnio.id = 'alertaNoAnio';
                    alertaAnio.className = 'alert alert-warning mt-3';
                    alertaAnio.style.fontSize = '.85rem';
                    alertaAnio.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>El empleado a\u00fan no cumple su primer a\u00f1o de trabajo.</strong> De acuerdo con la Ley Federal del Trabajo, no tiene derecho a vacaciones hasta cumplir 1 a\u00f1o.';
                    document.getElementById('fecha_ingreso_manual').closest('.row').parentElement.appendChild(alertaAnio);
                }
                alertaAnio.style.display = 'block';
                cumpleUnAnio = false;
                validar();
                return;
            }

            // Ocultar alerta si ya cumplio
            const alertaAnio = document.getElementById('alertaNoAnio');
            if (alertaAnio) alertaAnio.style.display = 'none';
            cumpleUnAnio = true;

            elAnt.textContent = anios + ' a\u00f1o' + (anios !== 1 ? 's' : '') + ', ' + meses + ' mes' + (meses !== 1 ? 'es' : '');

            const diasCorrespondientes = diasLFT(Math.max(1, anios > 0 ? anios : 1));
            elLFT.textContent = diasCorrespondientes + ' d\u00edas';

            const tomados = parseInt(document.getElementById('dias_ya_tomados').value) || 0;
            diasDisponibles = Math.max(0, diasCorrespondientes - tomados);
            elDisp.textContent = diasDisponibles + ' d\u00edas';

            calcularDias();
        }

        document.getElementById('fecha_ingreso_manual').addEventListener('change', calcularInfoEmpleado);
        document.getElementById('dias_ya_tomados').addEventListener('input', calcularInfoEmpleado);
        document.getElementById('jornada_manual').addEventListener('change', calcularDias);

        // =====================================================
        // Radio buttons: dias periodo anterior
        // =====================================================
        const radioNo = document.getElementById('diasAntNo');
        const radioSi = document.getElementById('diasAntSi');
        const campoDiasAnt = document.getElementById('campoDiasAnteriores');
        const infoDiasAnt = document.getElementById('infoDiasAnteriores');
        const inputDiasAnt = document.getElementById('dias_periodo_anterior');
        const txtDiasAnt = document.getElementById('txtDiasAnt');
        let usandoPeriodoAnterior = false;
        let diasPeriodoAnterior = 0;

        function toggleDiasAnteriores() {
            const esSi = radioSi.checked;
            campoDiasAnt.style.display = esSi ? 'block' : 'none';
            infoDiasAnt.style.display = esSi ? 'block' : 'none';
            usandoPeriodoAnterior = esSi;
            if (!esSi) {
                inputDiasAnt.value = '';
                diasPeriodoAnterior = 0;
            } else {
                diasPeriodoAnterior = parseInt(inputDiasAnt.value) || 0;
                txtDiasAnt.textContent = diasPeriodoAnterior;
            }
            calcularDias();
        }

        radioNo.addEventListener('change', toggleDiasAnteriores);
        radioSi.addEventListener('change', toggleDiasAnteriores);
        inputDiasAnt.addEventListener('input', function() {
            diasPeriodoAnterior = parseInt(this.value) || 0;
            txtDiasAnt.textContent = diasPeriodoAnterior;
            calcularDias();
        });

        // Restaurar estado si hay datos previos
        if (radioSi.checked) toggleDiasAnteriores();

        // =====================================================
        // jSignature - Firma Empleado
        // =====================================================
        let firmaEmpleadoOk = false;
        const $firmaEmp = $("#firmaEmpleado");
        $firmaEmp.jSignature({ color:'#000', lineWidth:2, background:'#fff', width:'100%', height:120, 'decor-color':'transparent' });
        $firmaEmp.bind('change', function() {
            const d = $firmaEmp.jSignature('getData','base30');
            if (d && d.length > 1 && d[1].length > 0) {
                firmaEmpleadoOk = true;
                document.getElementById('firmaEmpleadoContainer').classList.add('firmado');
                document.getElementById('firmaEmpleadoEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firmado';
            }
            validar();
        });
        document.getElementById('btnLimpiarFirmaEmpleado').addEventListener('click', function() {
            $firmaEmp.jSignature('reset'); firmaEmpleadoOk = false;
            document.getElementById('firmaEmpleadoContainer').classList.remove('firmado');
            document.getElementById('firmaEmpleadoEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente';
            validar();
        });

        // =====================================================
        // jSignature - Firma Admin
        // =====================================================
        let firmaAdminOk = false;
        const $firmaAdm = $("#firmaAdmin");
        $firmaAdm.jSignature({ color:'#000', lineWidth:2, background:'#fff', width:'100%', height:120, 'decor-color':'transparent' });
        $firmaAdm.bind('change', function() {
            const d = $firmaAdm.jSignature('getData','base30');
            if (d && d.length > 1 && d[1].length > 0) {
                firmaAdminOk = true;
                document.getElementById('firmaAdminContainer').classList.add('firmado');
                document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firmado';
            }
            validar();
        });
        document.getElementById('btnLimpiarFirmaAdmin').addEventListener('click', function() {
            $firmaAdm.jSignature('reset'); firmaAdminOk = false;
            document.getElementById('firmaAdminContainer').classList.remove('firmado');
            document.getElementById('firmaAdminEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente';
            validar();
        });

        // =====================================================
        // Calcular dias habiles con festivos + periodo anterior
        // =====================================================
        function calcularDias() {
            const fi = document.getElementById('fecha_inicio').value;
            const ff = document.getElementById('fecha_fin').value;
            const elD = document.getElementById('diasSolicitados');
            const elA = document.getElementById('alertaExceso');
            const elR = document.getElementById('fecha_regreso');
            const elFI = document.getElementById('festivoInfo');
            const elFT = document.getElementById('festivoTexto');

            if (!fi || !ff) { elD.textContent='0'; elD.classList.remove('excedido'); elA.style.display='none'; elR.value=''; elFI.style.display='none'; diasHabiles=0; validar(); return; }

            const inicio = new Date(fi+'T00:00:00'), fin = new Date(ff+'T00:00:00');
            if (inicio > fin) { elD.textContent='0'; elD.classList.add('excedido'); diasHabiles=0; validar(); return; }

            let dias = 0;
            let festivosEnRango = [];
            const c = new Date(inicio);
            while (c <= fin) {
                const fechaStr = fmtFecha(c);
                if (esDiaHabil(c)) {
                    dias++;
                } else if (diasFestivos.includes(fechaStr) && c.getDay() !== 0) {
                    const detalle = festivosDetalle.find(f => f.fecha === fechaStr);
                    if (detalle) festivosEnRango.push(detalle.nombre + ' (' + fechaStr + ')');
                }
                c.setDate(c.getDate()+1);
            }

            diasHabiles = dias; elD.textContent = dias;

            // Fecha regreso
            const reg = new Date(fin); reg.setDate(reg.getDate()+1);
            for (let i = 0; i < 30; i++) {
                if (esDiaHabil(reg)) break;
                reg.setDate(reg.getDate()+1);
            }
            elR.value = fmtFecha(reg);

            // Info festivos
            if (festivosEnRango.length > 0) {
                elFT.textContent = 'D\u00edas festivos en el rango (no se cuentan): ' + festivosEnRango.join(', ');
                elFI.style.display = 'block';
            } else {
                elFI.style.display = 'none';
            }

            // Validacion de dias: periodo anterior vs disponibles
            if (usandoPeriodoAnterior && diasPeriodoAnterior > 0) {
                if (dias > diasPeriodoAnterior) {
                    elD.classList.add('excedido');
                    elA.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Solo puede tomar ' + diasPeriodoAnterior + ' d\u00edas del periodo anterior. Para m\u00e1s d\u00edas, cree otra solicitud con el periodo actual.';
                    elA.style.display = 'block';
                } else {
                    elD.classList.remove('excedido'); elA.style.display='none';
                }
            } else {
                if (diasDisponibles > 0 && dias > diasDisponibles) {
                    elD.classList.add('excedido');
                    elA.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Excede los dias disponibles.';
                    elA.style.display='block';
                } else {
                    elD.classList.remove('excedido'); elA.style.display='none';
                }
            }
            validar();
        }

        document.getElementById('fecha_inicio').addEventListener('change', function() {
            if(this.value) document.getElementById('fecha_fin').min = this.value;
            calcularDias();
        });
        document.getElementById('fecha_fin').addEventListener('change', calcularDias);

        // =====================================================
        // Validacion general
        // =====================================================
        function validar() {
            const nombreOk = document.querySelector('[name="nombre_manual"]').value.trim().length > 0;
            const fechaIngresoOk = document.getElementById('fecha_ingreso_manual').value !== '';
            const deptoOk = document.querySelector('[name="departamento_id_manual"]').value !== '';
            const fechasOk = document.getElementById('fecha_inicio').value && document.getElementById('fecha_fin').value;

            let diasOk = false;
            if (!cumpleUnAnio) {
                diasOk = false;
            } else if (usandoPeriodoAnterior && diasPeriodoAnterior > 0) {
                diasOk = diasHabiles > 0 && diasHabiles <= diasPeriodoAnterior;
            } else {
                diasOk = diasHabiles > 0 && (diasDisponibles <= 0 || diasHabiles <= diasDisponibles);
            }

            document.getElementById('btnEnviar').disabled = !(nombreOk && fechaIngresoOk && deptoOk && fechasOk && diasOk && firmaEmpleadoOk && firmaAdminOk);
        }

        document.querySelector('[name="nombre_manual"]').addEventListener('input', validar);
        document.querySelector('[name="departamento_id_manual"]').addEventListener('change', validar);

        // =====================================================
        // Submit
        // =====================================================
        document.getElementById('formSolicitudManual').addEventListener('submit', function(e) {
            if (!firmaEmpleadoOk || !firmaAdminOk) { e.preventDefault(); alert('Ambas firmas son obligatorias.'); return; }
            const fdEmp = $firmaEmp.jSignature('getData','image');
            if (fdEmp && fdEmp.length > 1) { document.getElementById('firma_empleado_manual').value = 'data:'+fdEmp[0]+','+fdEmp[1]; }
            else { e.preventDefault(); alert('Error al capturar firma del empleado.'); return; }
            const fdAdm = $firmaAdm.jSignature('getData','image');
            if (fdAdm && fdAdm.length > 1) { document.getElementById('firma_admin').value = 'data:'+fdAdm[0]+','+fdAdm[1]; }
            else { e.preventDefault(); alert('Error al capturar firma del admin.'); return; }
            const btn = document.getElementById('btnEnviar'); btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span> Creando...';
        });

        // Sidebar
        const hb=document.querySelector('.hamburger-btn'),sb=document.querySelector('.sidebar'),ov=document.querySelector('.sidebar-overlay');
        if(hb&&sb){hb.addEventListener('click',function(){sb.classList.toggle('active');if(ov)ov.classList.toggle('active');});}
        if(ov){ov.addEventListener('click',function(){sb.classList.remove('active');this.classList.remove('active');});}

        if (document.getElementById('fecha_ingreso_manual').value) calcularInfoEmpleado();
    });
    </script>
</body>
</html>