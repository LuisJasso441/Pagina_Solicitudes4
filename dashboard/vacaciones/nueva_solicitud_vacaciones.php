<?php
/**
 * Nueva Solicitud de Vacaciones
 * dashboard/vacaciones/nueva_solicitud_vacaciones.php
 * Formato basado en documento oficial GrupoVerden
 * ACTUALIZADO: Dias festivos MX + jornada L-V/L-S
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$pdo = conectarDB();
$resumen = obtener_resumen_vacaciones($pdo, $_SESSION['usuario_id']);

if (!$resumen['tiene_fecha_ingreso']) {
    $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'No tienes fecha de ingreso registrada. Contacta a Sistemas o GTH.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php'); exit;
}

// Dias del periodo anterior (no vencidos)
$periodo_anterior = $resumen['periodo_anterior'] ?? ['dias_pendientes' => 0];
$dias_periodo_ant = $periodo_anterior['dias_pendientes'] ?? 0;
$usa_periodo_anterior = ($dias_periodo_ant > 0);

// Si no tiene dias del periodo actual NI del anterior, no puede crear
if ($resumen['dias_disponibles'] <= 0 && $dias_periodo_ant <= 0) {
    $_SESSION['alerta'] = ['tipo' => 'warning', 'mensaje' => 'No tienes d&iacute;as de vacaciones disponibles.'];
    header('Location: ' . URL_BASE . 'dashboard/vacaciones/mis_vacaciones.php'); exit;
}

$form_data = $_SESSION['form_data_vacaciones'] ?? [];
unset($_SESSION['form_data_vacaciones']);
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);

$departamento_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_ti = ($departamento_codigo === 'sistemas' || $departamento_codigo === 'ti');
$es_mantenimiento = ($departamento_codigo === 'mantenimiento');

$periodo_texto = $resumen['periodo']['anio_periodo'] ?? 0;
$dias_correspondientes = $resumen['dias_correspondientes'];
// Si usa periodo anterior, los dias disponibles son los del periodo anterior
$dias_pendientes = $usa_periodo_anterior ? $dias_periodo_ant : $resumen['dias_disponibles'];
if ($usa_periodo_anterior && !empty($periodo_anterior['periodo'])) {
    $periodo_vacacional = substr($periodo_anterior['periodo']['inicio'], 0, 4) . '-' . substr($periodo_anterior['periodo']['fin'], 0, 4) . ' (anterior)';
} else {
    $periodo_vacacional = $resumen['periodo'] 
        ? (substr($resumen['periodo']['inicio'], 0, 4) . '-' . substr($resumen['periodo']['fin'], 0, 4))
        : '-';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud de Vacaciones - <?php echo NOMBRE_SISTEMA; ?></title>
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
        .doc-container{background:#fff;border:2px solid #000;max-width:900px;margin:0 auto}
        .doc-header-table{width:100%;border-collapse:collapse;border-bottom:2px solid #000}
        .doc-header-table td{border:1px solid #000;padding:8px 12px;vertical-align:middle}
        .doc-header-table .logo-cell{width:20%;text-align:center;font-size:.8rem;color:#999}
        .doc-header-table .title-cell{width:40%;text-align:center;font-size:1.3rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
        .doc-header-table .info-cell{width:40%;padding:0}
        .doc-header-table .info-cell table{width:100%;border-collapse:collapse}
        .doc-header-table .info-cell table td{border:1px solid #000;padding:4px 8px;font-size:.82rem}
        .doc-header-table .info-cell table td.lbl{font-weight:700;text-transform:uppercase;width:55%}
        .doc-header-table .info-cell table td.val{text-align:center}
        .doc-body{padding:20px}
        .info-row{display:flex;flex-wrap:wrap;border-bottom:1px solid #dee2e6;padding:6px 0}
        .info-label{font-weight:600;font-size:.82rem;text-transform:uppercase;color:#333;min-width:160px}
        .info-value{flex:1;font-size:.9rem;border-bottom:1px dotted #999;min-width:150px}
        .tabla-periodo{width:100%;border-collapse:collapse;margin:15px 0}
        .tabla-periodo th,.tabla-periodo td{border:1px solid #000;padding:8px 12px;font-size:.88rem}
        .tabla-periodo th{background:#f0f0f0;font-weight:600;text-transform:uppercase;text-align:left;width:70%}
        .tabla-periodo td{text-align:center;font-weight:700;font-size:1rem}
        .tabla-periodo td.editable{background:#fffde7}
        .fechas-row{display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin:15px 0}
        .fecha-group{display:flex;align-items:center;gap:8px}
        .fecha-group label{font-weight:700;font-size:.9rem;text-transform:uppercase}
        .fecha-group input[type="date"]{border:1px solid #000;border-radius:0;padding:6px 10px;font-size:.9rem}
        .firmas-row{display:flex;justify-content:space-between;gap:20px;margin-top:30px}
        .firma-box{flex:1;text-align:center;min-width:200px}
        .firma-canvas-container{border:2px dashed #ced4da;border-radius:4px;background:#fff;min-height:120px;margin-bottom:5px}
        .firma-canvas-container.firmado{border-color:#198754;border-style:solid}
        .firma-linea{border-top:2px solid #000;padding-top:6px;font-size:.78rem;font-weight:600;text-transform:uppercase}
        .firma-placeholder{border-top:2px solid #000;padding-top:6px;min-height:120px;display:flex;align-items:center;justify-content:center;color:#adb5bd;font-size:.82rem}
        .dias-highlight{font-size:1.2rem;font-weight:700;color:#198754;transition:all .3s}
        .dias-highlight.excedido{color:#dc3545}
        .fecha-solicitud{text-align:right;padding:10px 20px;border-bottom:1px solid #dee2e6;font-size:.88rem}
        .festivo-info{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:8px 12px;font-size:.8rem;margin-top:8px;display:none}
        @media(max-width:768px){.firmas-row{flex-direction:column}.fechas-row{flex-direction:column;gap:10px}}
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
                        <h1><i class="bi bi-calendar-plus"></i> Nueva Solicitud de Vacaciones</h1>
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php" class="btn btn-outline-secondary">
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

                <form id="formSolicitud" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="firma_empleado" id="firma_empleado" value="">
                    <input type="hidden" name="dias_correspondientes" value="<?php echo $dias_correspondientes; ?>">
                    <input type="hidden" name="dias_pendientes" value="<?php echo $dias_pendientes; ?>">
                    <input type="hidden" name="periodo_vacacional" value="<?php echo $periodo_vacacional; ?>">
                    <input type="hidden" name="usa_periodo_anterior" value="<?php echo $usa_periodo_anterior ? '1' : '0'; ?>">

                    <?php if ($usa_periodo_anterior): ?>
                    <div class="alert alert-warning mb-3" style="max-width:900px;margin:0 auto">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Tienes <?php echo $dias_periodo_ant; ?> d&iacute;a<?php echo $dias_periodo_ant != 1 ? 's' : ''; ?> pendiente<?php echo $dias_periodo_ant != 1 ? 's' : ''; ?> del periodo anterior.</strong>
                        Esta solicitud cubrir&aacute; esos d&iacute;as. Solo podr&aacute;s solicitar hasta <?php echo $dias_periodo_ant; ?> d&iacute;a<?php echo $dias_periodo_ant != 1 ? 's' : ''; ?>.
                        Para usar d&iacute;as del periodo actual, crea otra solicitud despu&eacute;s de cubrir los pendientes.
                        <?php if (!empty($periodo_anterior['periodo']['fecha_vencimiento'])): ?>
                        <br><small class="text-muted">Estos d&iacute;as vencen el <?php echo fecha_corta_es($periodo_anterior['periodo']['fecha_vencimiento']); ?>.</small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="doc-container shadow-sm mb-4">
                        <table class="doc-header-table">
                            <tr>
                                <td class="logo-cell" rowspan="1">
                                    <img src="<?php echo URL_BASE; ?>assets/img/LOGO RESIMEX.jpg" alt="Logo Grupo Verden" style="max-width:100px">
                                </td>
                                <td class="title-cell" rowspan="1">
                                    Solicitud de<br>Vacaciones
                                </td>
                                <td class="info-cell">
                                    <table>
                                        <tr><td class="lbl">C&oacute;digo:</td><td class="val">FR-GT-07</td></tr>
                                        <tr><td class="lbl">No. de Revisi&oacute;n:</td><td class="val">00</td></tr>
                                        <tr><td class="lbl">Fecha de Revisi&oacute;n:</td><td class="val">02/08/2021</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <div class="fecha-solicitud"><strong>Fecha de Solicitud:</strong> <?php echo fecha_larga_es(date('Y-m-d')); ?></div>
                        <div class="doc-body">

                            <!-- Datos del empleado -->
                            <div class="mb-4">
                                <div class="row g-2">
                                    <div class="col-md-8"><div class="info-row"><span class="info-label">Nombre:</span><span class="info-value"><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></span></div></div>
                                    <div class="col-md-4"><div class="info-row"><span class="info-label">No. Nomina:</span><span class="info-value"><?php echo htmlspecialchars($_SESSION['no_nomina'] ?? 'N/A'); ?></span></div></div>
                                    <div class="col-md-6"><div class="info-row"><span class="info-label">Departamento:</span><span class="info-value"><?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?></span></div></div>
                                    <div class="col-md-6"><div class="info-row"><span class="info-label">Puesto:</span><span class="info-value"><?php echo htmlspecialchars($_SESSION['puesto'] ?? 'N/A'); ?></span></div></div>
                                    <div class="col-md-6"><div class="info-row"><span class="info-label">Fecha de Ingreso:</span><span class="info-value"><?php echo fecha_corta_es($resumen['fecha_ingreso']); ?></span></div></div>
                                </div>
                            </div>

                            <!-- Tabla periodo -->
                            <table class="tabla-periodo">
                                <tr><th>Periodo Vacacional Corriente</th><td><?php echo $periodo_vacacional; ?></td></tr>
                                <tr><th>D&iacute;as de Vacaciones Correspondientes al Periodo</th><td><?php echo $dias_correspondientes; ?></td></tr>
                                <tr><th>D&iacute;as Pendientes de Vacaciones</th><td><?php echo $dias_pendientes; ?></td></tr>
                                <tr><th>D&iacute;as Solicitados</th><td class="editable"><span class="dias-highlight" id="diasSolicitados">0</span></td></tr>
                                <tr><th>Saldo de D&iacute;as Pendientes</th><td><span id="saldoDias"><?php echo $dias_pendientes; ?></span></td></tr>
                            </table>

                            <div id="alertaExceso" class="alert alert-danger py-2 mt-2" style="display:none;font-size:.85rem">
                                <i class="bi bi-exclamation-triangle me-1"></i> Excede los d&iacute;as disponibles.
                            </div>

                            <p class="text-muted mt-2 mb-1" style="font-size:.8rem">
                                <i class="bi bi-info-circle me-1"></i>
                                De acuerdo con la Ley Federal de Trabajo, Art&iacute;culos 76 y 80. Se excluyen domingos, d&iacute;as festivos oficiales y los extras de la empresa.
                            </p>
                            <div id="festivoInfo" class="festivo-info">
                                <i class="bi bi-calendar-x me-1"></i> <span id="festivoTexto"></span>
                            </div>

                            <!-- DEL / AL / REGRESA -->
                            <h6 class="text-uppercase fw-bold mt-4 mb-3">D&iacute;as de Vacaciones</h6>
                            <div class="fechas-row">
                                <div class="fecha-group">
                                    <label>Del:</label>
                                    <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?php echo htmlspecialchars($form_data['fecha_inicio'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="fecha-group">
                                    <label>Al:</label>
                                    <input type="date" name="fecha_fin" id="fecha_fin" required value="<?php echo htmlspecialchars($form_data['fecha_fin'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="fecha-group">
                                    <label>Regresa:</label>
                                    <input type="date" name="fecha_regreso" id="fecha_regreso" readonly class="bg-light">
                                </div>
                            </div>
                            <small class="text-muted" id="diasInfo">Seleccione las fechas de inicio y fin</small>

                            <!-- 3 Firmas -->
                            <div class="firmas-row">
                                <div class="firma-box">
                                    <div class="firma-canvas-container" id="firmaContainer">
                                        <div id="firma" style="width:100%;height:120px"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small id="firmaEstado" class="text-muted"><i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente</small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFirma"><i class="bi bi-eraser"></i> Limpiar</button>
                                    </div>
                                    <div class="firma-linea">Nombre y Firma del Solicitante</div>
                                </div>
                                <div class="firma-box">
                                    <div class="firma-placeholder"><i class="bi bi-hourglass-split me-1"></i> Pendiente</div>
                                    <div class="firma-linea">Nombre y Firma<br>Jefe Inmediato</div>
                                </div>
                                <div class="firma-box">
                                    <div class="firma-placeholder"><i class="bi bi-hourglass-split me-1"></i> Pendiente</div>
                                    <div class="firma-linea">Nombre y Firma<br>Gesti&oacute;n de Talento Humano</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mb-4" style="max-width:900px;margin:0 auto">
                        <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i> Cancelar</a>
                        <button type="submit" class="btn btn-success btn-lg" id="btnEnviar" disabled><i class="bi bi-send me-1"></i> Enviar Solicitud</button>
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
        const diasPendientes = <?php echo $dias_pendientes; ?>;
        let firmaOk = false, diasHabiles = 0;

        // Festivos y jornada desde API
        let diasFestivos = [];
        let festivosDetalle = [];
        let jornadaEmpleado = 'lunes_sabado';

        fetch('<?php echo URL_BASE; ?>api/vacaciones/dias_festivos.php?anio=' + new Date().getFullYear() + '&usuario_id=<?php echo $_SESSION["usuario_id"]; ?>')
            .then(r => r.json())
            .then(data => {
                diasFestivos = data.festivos_fechas || [];
                festivosDetalle = data.festivos || [];
                jornadaEmpleado = data.jornada || 'lunes_sabado';
            })
            .catch(() => {});

        // Helper: formato fecha YYYY-MM-DD
        function fmtFecha(d) {
            return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        }

        // Helper: es dia habil?
        function esDiaHabil(fecha) {
            const dow = fecha.getDay();
            const fechaStr = fmtFecha(fecha);
            if (dow === 0) return false; // domingo
            if (jornadaEmpleado === 'lunes_viernes' && dow === 6) return false; // sabado si L-V
            if (diasFestivos.includes(fechaStr)) return false; // festivo
            return true;
        }

        // jSignature
        const $firma = $("#firma");
        $firma.jSignature({ color:'#000000', lineWidth:2, background:'#ffffff', width:'100%', height:120, 'decor-color':'transparent' });

        $firma.bind('change', function() {
            const d = $firma.jSignature('getData','base30');
            if (d && d.length > 1 && d[1].length > 0) {
                firmaOk = true;
                document.getElementById('firmaContainer').classList.add('firmado');
                document.getElementById('firmaEstado').innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Firmado';
            }
            validar();
        });

        document.getElementById('btnLimpiarFirma').addEventListener('click', function() {
            $firma.jSignature('reset'); firmaOk = false;
            document.getElementById('firmaContainer').classList.remove('firmado');
            document.getElementById('firmaEstado').innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Pendiente';
            validar();
        });

        // Calculo dias habiles con festivos y jornada
        function calcular() {
            const fi = document.getElementById('fecha_inicio').value;
            const ff = document.getElementById('fecha_fin').value;
            const elD = document.getElementById('diasSolicitados');
            const elS = document.getElementById('saldoDias');
            const elI = document.getElementById('diasInfo');
            const elA = document.getElementById('alertaExceso');
            const elR = document.getElementById('fecha_regreso');
            const elFI = document.getElementById('festivoInfo');
            const elFT = document.getElementById('festivoTexto');

            if (!fi || !ff) { elD.textContent='0'; elD.classList.remove('excedido'); elS.textContent=diasPendientes; elI.textContent='Seleccione las fechas'; elA.style.display='none'; elR.value=''; elFI.style.display='none'; diasHabiles=0; validar(); return; }

            const inicio = new Date(fi+'T00:00:00'), fin = new Date(ff+'T00:00:00');
            if (inicio > fin) { elD.textContent='0'; elD.classList.add('excedido'); elI.innerHTML='<span class="text-danger">Fecha "Al" debe ser posterior a "Del"</span>'; diasHabiles=0; validar(); return; }

            // Contar dias habiles y detectar festivos en rango
            let dias = 0;
            let festivosEnRango = [];
            const c = new Date(inicio);
            while (c <= fin) {
                const fechaStr = fmtFecha(c);
                if (esDiaHabil(c)) {
                    dias++;
                } else if (diasFestivos.includes(fechaStr) && c.getDay() !== 0) {
                    // Es festivo y no domingo (mostrar info)
                    const detalle = festivosDetalle.find(f => f.fecha === fechaStr);
                    if (detalle) festivosEnRango.push(detalle.nombre + ' (' + fechaStr + ')');
                }
                c.setDate(c.getDate()+1);
            }

            diasHabiles = dias; elD.textContent = dias;
            const saldo = diasPendientes - dias; elS.textContent = saldo;

            // Fecha regreso = siguiente dia habil
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

            const jornadaTxt = jornadaEmpleado === 'lunes_viernes' ? 'Lunes a Viernes' : 'Lunes a S\u00e1bado';

            if (dias > diasPendientes) {
                elD.classList.add('excedido'); elS.style.color='#dc3545';
                elI.innerHTML='<span class="text-danger">Excede los '+diasPendientes+' dias disponibles</span>'; elA.style.display='block';
            } else {
                elD.classList.remove('excedido'); elS.style.color='';
                elI.textContent=dias+' dia'+(dias!==1?'s':'')+' habil'+(dias!==1?'es':'')+' ('+jornadaTxt+', excl. festivos)'; elA.style.display='none';
            }
            validar();
        }

        document.getElementById('fecha_inicio').addEventListener('change', function() { if(this.value) document.getElementById('fecha_fin').min=this.value; calcular(); });
        document.getElementById('fecha_fin').addEventListener('change', calcular);

        function validar() {
            document.getElementById('btnEnviar').disabled = !(document.getElementById('fecha_inicio').value && document.getElementById('fecha_fin').value && diasHabiles > 0 && diasHabiles <= diasPendientes && firmaOk);
        }

        document.getElementById('formSolicitud').addEventListener('submit', function(e) {
            if (!firmaOk) { e.preventDefault(); alert('Debe firmar la solicitud.'); return; }
            const fd = $firma.jSignature('getData','image');
            if (fd && fd.length > 1) { document.getElementById('firma_empleado').value = 'data:'+fd[0]+','+fd[1]; }
            else { e.preventDefault(); alert('Error al capturar firma.'); return; }
            const btn = document.getElementById('btnEnviar'); btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';
        });

        // Sidebar
        const hb=document.querySelector('.hamburger-btn'),sb=document.querySelector('.sidebar'),ov=document.querySelector('.sidebar-overlay');
        if(hb&&sb){hb.addEventListener('click',function(){sb.classList.toggle('active');if(ov)ov.classList.toggle('active');});}
        if(ov){ov.addEventListener('click',function(){sb.classList.remove('active');this.classList.remove('active');});}

        if(document.getElementById('fecha_inicio').value && document.getElementById('fecha_fin').value) calcular();
    });
    </script>
</body>
</html>