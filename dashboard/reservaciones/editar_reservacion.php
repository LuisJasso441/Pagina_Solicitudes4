<?php
/**
 * Editar Reservación
 * Ubicación: dashboard/reservaciones/editar_reservacion.php
 */
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/reservaciones/reservaciones_funciones.php';

if (!sesion_activa()) { header('Location: ' . URL_BASE . 'auth/InicioSesion.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { $_SESSION['error'] = 'ID inválido'; header('Location: ' . URL_BASE . 'dashboard/reservaciones/reservaciones_sala.php'); exit; }

$reservacion = obtener_reservacion_por_id($id);
if (!$reservacion) { $_SESSION['error'] = 'No encontrada'; header('Location: ' . URL_BASE . 'dashboard/reservaciones/reservaciones_sala.php'); exit; }
if ($reservacion['estado'] !== 'activa') { $_SESSION['error'] = 'Solo activas'; header('Location: ' . URL_BASE . 'dashboard/reservaciones/reservaciones_sala.php'); exit; }
if (!puede_modificar_reservacion($reservacion, $_SESSION['usuario_id'], $_SESSION['departamento'])) {
    $_SESSION['error'] = 'Sin permiso'; header('Location: ' . URL_BASE . 'dashboard/reservaciones/reservaciones_sala.php'); exit;
}

$departamentos = obtener_departamentos_activos();
$lugares = obtener_lugares_disponibles();

$form_data = $_SESSION['form_data'] ?? [];
$errores = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

$hora_inicio_12 = date('h:i', strtotime($reservacion['hora_inicio']));
$ampm_inicio = date('A', strtotime($reservacion['hora_inicio']));
$hora_fin_12 = date('h:i', strtotime($reservacion['hora_fin']));
$ampm_fin = date('A', strtotime($reservacion['hora_fin']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reservación - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/reservaciones.css">
</head>
<body>
    <div class="dashboard-container">
        <?php 
        $depto = strtolower(trim($_SESSION['departamento'] ?? ''));
        if ($depto === 'sistemas') { include __DIR__ . '/../../includes/sidebar/sidebar_ti.php'; }
        elseif ($depto === 'mantenimiento') { include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php'; }
        elseif (function_exists('es_usuario_epp') && es_usuario_epp()) { include __DIR__ . '/../../includes/sidebar/sidebar_inventario.php'; }
        elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) { include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; }
        else { include __DIR__ . '/../../includes/sidebar/sidebar_normal.php'; }
        ?>
        
        <main class="main-content">
            <div class="container-fluid p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Reservación</h4>
                        <small class="text-muted">ID #<?php echo $id; ?></small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
                </div>
                
                <?php if (!empty($errores)): ?>
                <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i>
                    <?php foreach ($errores as $err): ?><div><?php echo htmlspecialchars($err); ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div id="alerta-conflicto" class="alert alert-danger py-2 d-none"><i class="bi bi-exclamation-octagon me-1"></i> <span id="conflicto-texto"></span></div>
                <div id="alerta-disponible" class="alert alert-success py-2 d-none"><i class="bi bi-check-circle me-1"></i> Horario disponible</div>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form action="<?php echo URL_BASE; ?>api/reservaciones/editar_reservacion.php" method="POST" id="form-reservacion">
                            <input type="hidden" name="reservacion_id" value="<?php echo $id; ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="solicitante_nombre" class="form-label fw-semibold">Nombre del Solicitante <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="solicitante_nombre" name="solicitante_nombre"
                                           value="<?php echo htmlspecialchars($form_data['solicitante_nombre'] ?? $reservacion['solicitante_nombre']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="departamento_id" class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="departamento_id" name="departamento_id" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($departamentos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo (($form_data['departamento_id'] ?? $reservacion['departamento_id']) == $d['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="lugar" class="form-label fw-semibold">Lugar de Reunión <span class="text-danger">*</span></label>
                                    <select class="form-select" id="lugar" name="lugar" required>
                                        <?php foreach ($lugares as $cod => $nom): ?>
                                        <option value="<?php echo $cod; ?>" <?php echo (($form_data['lugar'] ?? $reservacion['lugar'] ?? 'sala_juntas') === $cod) ? 'selected' : ''; ?>>
                                            <?php echo $nom; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="fecha_reservacion" class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="fecha_reservacion" name="fecha_reservacion"
                                           value="<?php echo htmlspecialchars($form_data['fecha_reservacion'] ?? $reservacion['fecha_reservacion']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Hora de Inicio <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="hora_inicio" name="hora_inicio" placeholder="HH:MM" maxlength="5" 
                                               value="<?php echo htmlspecialchars($form_data['hora_inicio'] ?? $hora_inicio_12); ?>" required>
                                        <select class="form-select" id="ampm_inicio" name="ampm_inicio" style="max-width:75px">
                                            <option value="AM" <?php echo ($form_data['ampm_inicio'] ?? $ampm_inicio) === 'AM' ? 'selected' : ''; ?>>AM</option>
                                            <option value="PM" <?php echo ($form_data['ampm_inicio'] ?? $ampm_inicio) === 'PM' ? 'selected' : ''; ?>>PM</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Hora de Término <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="hora_fin" name="hora_fin" placeholder="HH:MM" maxlength="5"
                                               value="<?php echo htmlspecialchars($form_data['hora_fin'] ?? $hora_fin_12); ?>" required>
                                        <select class="form-select" id="ampm_fin" name="ampm_fin" style="max-width:75px">
                                            <option value="AM" <?php echo ($form_data['ampm_fin'] ?? $ampm_fin) === 'AM' ? 'selected' : ''; ?>>AM</option>
                                            <option value="PM" <?php echo ($form_data['ampm_fin'] ?? $ampm_fin) === 'PM' ? 'selected' : ''; ?>>PM</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="motivo" class="form-label fw-semibold">Motivo de la Reunión</label>
                                    <input type="text" class="form-control" id="motivo" name="motivo" maxlength="255"
                                           placeholder="Ej: Revisión de avances del proyecto, Capacitación de seguridad..."
                                           value="<?php echo htmlspecialchars($form_data['motivo'] ?? $reservacion['motivo'] ?? ''); ?>">
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="btn-reservar"><i class="bi bi-save me-1"></i> Guardar Cambios</button>
                                <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (file_exists(__DIR__ . '/../../assets/js/sidebar.js')): ?>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar.js"></script>
    <?php endif; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const URL_BASE = '<?php echo URL_BASE; ?>';
        const EXCLUIR_ID = <?php echo $id; ?>;
        const btnReservar = document.getElementById('btn-reservar');
        const alertaConflicto = document.getElementById('alerta-conflicto');
        const alertaDisponible = document.getElementById('alerta-disponible');
        const conflictoTexto = document.getElementById('conflicto-texto');
        let hayConflicto = false, verificando = false;
        
        function mascararHora(input) {
            input.addEventListener('input', function() {
                let v = this.value.replace(/[^\d]/g, '');
                if (v.length >= 3) v = v.substring(0,2) + ':' + v.substring(2,4);
                this.value = v.substring(0,5);
            });
        }
        mascararHora(document.getElementById('hora_inicio'));
        mascararHora(document.getElementById('hora_fin'));
        
        function convertirA24h(hora, ampm) {
            if (!hora || !hora.includes(':')) return '';
            let [h, m] = hora.split(':').map(Number);
            if (ampm === 'PM' && h !== 12) h += 12;
            if (ampm === 'AM' && h === 12) h = 0;
            return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':00';
        }
        
        function verificarDisponibilidad() {
            const fecha = document.getElementById('fecha_reservacion').value;
            const horaInicio = document.getElementById('hora_inicio').value;
            const ampmInicio = document.getElementById('ampm_inicio').value;
            const horaFin = document.getElementById('hora_fin').value;
            const ampmFin = document.getElementById('ampm_fin').value;
            const lugar = document.getElementById('lugar').value;
            
            alertaConflicto.classList.add('d-none'); alertaDisponible.classList.add('d-none');
            hayConflicto = false; btnReservar.disabled = false;
            
            if (!fecha || !horaInicio || horaInicio.length < 4 || !horaFin || horaFin.length < 4) return;
            const inicio24 = convertirA24h(horaInicio, ampmInicio);
            const fin24 = convertirA24h(horaFin, ampmFin);
            if (!inicio24 || !fin24 || fin24 <= inicio24) return;
            if (verificando) return; verificando = true;
            
            fetch(`${URL_BASE}api/reservaciones/verificar_disponibilidad.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fecha, hora_inicio: inicio24, hora_fin: fin24, lugar, excluir_id: EXCLUIR_ID })
            }).then(r => r.json()).then(data => {
                verificando = false;
                if (data.disponible) { alertaDisponible.classList.remove('d-none'); hayConflicto = false; btnReservar.disabled = false; }
                else if (data.conflicto) {
                    const c = data.conflicto;
                    conflictoTexto.textContent = `Horario no disponible en ${c.lugar}. Reservación de ${c.solicitante} (${c.departamento}) de ${c.hora_inicio} a ${c.hora_fin}`;
                    alertaConflicto.classList.remove('d-none'); hayConflicto = true; btnReservar.disabled = true;
                }
            }).catch(() => { verificando = false; });
        }
        
        ['fecha_reservacion', 'hora_inicio', 'hora_fin', 'ampm_inicio', 'ampm_fin', 'lugar'].forEach(id => {
            document.getElementById(id).addEventListener('change', verificarDisponibilidad);
            document.getElementById(id).addEventListener('blur', verificarDisponibilidad);
        });
        
        document.getElementById('form-reservacion').addEventListener('submit', function(e) {
            if (hayConflicto) { e.preventDefault(); alertaConflicto.classList.remove('d-none'); }
        });
    });
    </script>
</body>
</html>