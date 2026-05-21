<?php
/**
 * Crear Mantenimiento Histórico - Formulario
 * dashboard/sistemas/ti_sistemas/mantenimientos/crear_mantenimiento_historico.php
 *
 * v4 - Sistema de cascada Grupo -> Departamento -> Usuario -> Equipo
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

// =====================================================================
// SEGURIDAD
// =====================================================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento     = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti            = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_ti) {
    establecer_alerta('error', 'No tiene permisos para acceder a esta función.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// =====================================================================
// DATOS PARA EL FORMULARIO
// =====================================================================
$pdo = conectarDB();

$departamentos = $pdo->query("
    SELECT id, nombre 
    FROM departamentos 
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$grupos_cards = [
    'computo'     => ['nombre' => 'Cómputo',     'icono' => 'bi-pc-display'],
    'perifericos' => ['nombre' => 'Periféricos', 'icono' => 'bi-mouse'],
    'impresoras'  => ['nombre' => 'Impresoras',  'icono' => 'bi-printer'],
    'red'         => ['nombre' => 'Red',         'icono' => 'bi-wifi'],
    'otros'       => ['nombre' => 'Otros',       'icono' => 'bi-three-dots'],
];

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Mantenimiento Histórico - TI / Sistemas</title>
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
        .form-section { background-color:#fff; border:1px solid #e9ecef; border-radius:0.5rem; padding:1rem 1.25rem; margin-bottom:1rem; }
        .form-section-title { font-weight:600; font-size:0.95rem; margin-bottom:0.75rem; color:#495057; display:flex; align-items:center; }
        .form-label.required::after { content:" *"; color:#dc3545; font-weight:700; }
        .grupo-card { border:2px solid #dee2e6; border-radius:8px; padding:1.25rem 0.75rem; text-align:center; cursor:pointer; transition:all 0.2s ease; background:#fff; height:100%; user-select:none; }
        .grupo-card:hover { border-color:#0d6efd; transform:translateY(-2px); box-shadow:0 4px 10px rgba(13,110,253,.12); }
        .grupo-card.selected { border-color:#0d6efd; background:#e7f1ff; box-shadow:0 4px 10px rgba(13,110,253,.18); }
        .grupo-card .icono { font-size:2rem; line-height:1; margin-bottom:0.4rem; color:#6c757d; }
        .grupo-card.selected .icono { color:#0d6efd; }
        .grupo-card .label { font-size:0.9rem; font-weight:600; color:#495057; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php
    if ($es_mantenimiento) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_mantenimiento.php';
    } elseif ($es_ti) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_ti.php';
    } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_colaborativo.php';
    } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_gth.php';
    } else {
        include __DIR__ . '/../../../../includes/sidebar/sidebar_normal.php';
    }
    ?>

    <main class="main-content">
        <div class="content-wrapper">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" class="text-decoration-none">
                                    Mantenimientos
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Registrar histórico</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">
                        <i class="bi bi-clock-history text-info"></i>
                        Registrar Mantenimiento Histórico
                    </h2>
                    <small class="text-muted">
                        Registra mantenimientos que ya se realizaron sin pasar por el flujo normal
                    </small>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" 
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <?php echo mostrar_alerta(); ?>

            <div class="alert alert-info border-0 mb-3">
                <i class="bi bi-info-circle"></i>
                Este registro se guardará como un mantenimiento <strong>ya cerrado</strong>.
                No se enviarán notificaciones ni se solicitarán firmas.
            </div>

            <form method="POST" 
                  action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/procesar_crear_historico.php" 
                  id="form-historico">

                <input type="hidden" name="grupo_equipo" id="grupo_equipo" value="">

                <div class="row">
                    <div class="col-lg-10 col-xl-9 mx-auto">

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-grid me-2"></i>¿Qué tipo de equipo recibió mantenimiento?
                            </div>
                            <div class="row g-3">
                                <?php foreach ($grupos_cards as $key => $info): ?>
                                    <div class="col-6 col-md">
                                        <div class="grupo-card" data-grupo="<?php echo $key; ?>" onclick="seleccionarGrupo('<?php echo $key; ?>')">
                                            <div class="icono"><i class="bi <?php echo $info['icono']; ?>"></i></div>
                                            <div class="label"><?php echo $info['nombre']; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-building me-2"></i>Destinatario y equipo
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Departamento</label>
                                    <select name="departamento_id" id="departamento_id" class="form-select" required>
                                        <option value="">-- Seleccionar Departamento --</option>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Usuario</label>
                                    <select name="usuario_id" id="usuario_id" class="form-select" required disabled>
                                        <option value="">-- Seleccione un departamento primero --</option>
                                    </select>
                                    <small class="text-muted" id="usuariosHint"></small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label required">Equipo Electrónico</label>
                                    <select name="equipo_id" id="equipo_id" class="form-select" required disabled>
                                        <option value="">-- Seleccione departamento y tipo de equipo --</option>
                                    </select>
                                    <small class="text-muted" id="equiposHint">
                                        El listado se filtra por el departamento y el tipo de equipo seleccionado.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-gear me-2"></i>Detalles del mantenimiento
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Tipo de mantenimiento</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_mantenimiento" id="tipo_fisico" value="fisico" required>
                                            <label class="form-check-label" for="tipo_fisico"><i class="bi bi-tools"></i> Físico</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="tipo_mantenimiento" id="tipo_logico" value="logico" required>
                                            <label class="form-check-label" for="tipo_logico"><i class="bi bi-cpu"></i> Lógico</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="fecha_realizado" class="form-label required">Fecha en que se realizó</label>
                                    <input type="date" name="fecha_realizado" id="fecha_realizado" class="form-control" 
                                           max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted">No puede ser una fecha futura.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-file-text me-2"></i>Descripción del trabajo realizado
                                <small class="text-muted fw-normal ms-2">(opcional)</small>
                            </div>
                            <textarea name="descripcion_trabajo" id="descripcion_trabajo" class="form-control" rows="4" maxlength="2000"
                                      placeholder="Detalles del mantenimiento que se realizó..."></textarea>
                            <small class="text-muted">Máximo 2000 caracteres. Solo aparece en el Reporte Mensual (FR-TI-05).</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mb-4">
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-info text-white"><i class="bi bi-save"></i> Guardar registro</button>
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
const URL_API = '<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/api_mantenimiento.php';
const selDepto = document.getElementById('departamento_id');
const selUsuario = document.getElementById('usuario_id');
const selEquipo = document.getElementById('equipo_id');
const inpGrupo = document.getElementById('grupo_equipo');

selDepto.addEventListener('change', async function() {
    const deptoId = this.value;
    resetUsuario(); resetEquipo();
    if (deptoId) {
        await cargarUsuarios(deptoId);
        if (inpGrupo.value) await cargarEquipos(deptoId, inpGrupo.value, '');
    }
});
selUsuario.addEventListener('change', async function() {
    const deptoId = selDepto.value, userId = this.value;
    if (deptoId && inpGrupo.value) await cargarEquipos(deptoId, inpGrupo.value, userId);
});
function resetUsuario() {
    selUsuario.innerHTML = '<option value="">-- Seleccione un departamento primero --</option>';
    selUsuario.disabled = true; document.getElementById('usuariosHint').textContent = '';
}
function resetEquipo() {
    selEquipo.innerHTML = '<option value="">-- Seleccione departamento y tipo de equipo --</option>';
    selEquipo.disabled = true;
}
async function cargarUsuarios(deptoId) {
    selUsuario.innerHTML = '<option value="">Cargando...</option>'; selUsuario.disabled = true;
    try {
        const r = await fetch(URL_API + '?accion=usuarios&departamento_id=' + encodeURIComponent(deptoId));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Error');
        if (!j.data.length) {
            selUsuario.innerHTML = '<option value="">-- Sin usuarios activos en este departamento --</option>';
            document.getElementById('usuariosHint').textContent = 'No hay usuarios activos asignados a este departamento.';
            return;
        }
        let html = '<option value="">-- Seleccionar Usuario --</option>';
        j.data.forEach(u => { html += `<option value="${u.id}">${escapeHtml(u.nombre_completo)}</option>`; });
        selUsuario.innerHTML = html; selUsuario.disabled = false;
        document.getElementById('usuariosHint').textContent = `${j.data.length} usuario(s) disponibles.`;
    } catch (err) { console.error(err); selUsuario.innerHTML = '<option value="">-- Error al cargar usuarios --</option>'; }
}
async function cargarEquipos(deptoId, grupo, usuarioId) {
    selEquipo.innerHTML = '<option value="">Cargando...</option>'; selEquipo.disabled = true;
    try {
        let url = URL_API + '?accion=equipos&departamento_id=' + encodeURIComponent(deptoId) + '&grupo=' + encodeURIComponent(grupo);
        if (usuarioId) url += '&usuario_id=' + encodeURIComponent(usuarioId);
        const r = await fetch(url); const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Error');
        if (!j.data.length) {
            selEquipo.innerHTML = '<option value="">-- Sin equipos en este departamento para el tipo seleccionado --</option>';
            document.getElementById('equiposHint').innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No hay equipos activos del tipo seleccionado en este departamento.</span>';
            return;
        }
        let html = '<option value="">-- Seleccionar Equipo --</option>';
        j.data.forEach(e => {
            const partes = [];
            if (e.hostname) partes.push(e.hostname);
            if (e.codigo_interno) partes.push(e.codigo_interno);
            const ref = partes.join(' / ') || '(sin identificador)';
            const tipo = e.tipo_equipo || '';
            const mm = [e.marca, e.modelo].filter(Boolean).join(' ');
            const ubic = e.ubicacion ? ' - ' + e.ubicacion : '';
            const star = e.asignado_al_usuario ? '⭐ ' : '';
            const texto = `${star}${ref} (${tipo})${mm ? ' · ' + mm : ''}${ubic}`;
            html += `<option value="${e.id}">${escapeHtml(texto)}</option>`;
        });
        selEquipo.innerHTML = html; selEquipo.disabled = false;
        document.getElementById('equiposHint').innerHTML = `<span class="text-muted">${j.data.length} equipo(s). ⭐ = asignado al usuario seleccionado.</span>`;
    } catch (err) { console.error(err); selEquipo.innerHTML = '<option value="">-- Error al cargar equipos --</option>'; }
}
function seleccionarGrupo(grupo) {
    document.querySelectorAll('.grupo-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.grupo-card[data-grupo="${grupo}"]`);
    if (card) card.classList.add('selected');
    inpGrupo.value = grupo;
    const deptoId = selDepto.value;
    if (deptoId) cargarEquipos(deptoId, grupo, selUsuario.value || '');
}
document.getElementById('form-historico').addEventListener('submit', function(e) {
    if (!inpGrupo.value) { e.preventDefault(); alert('Selecciona el tipo de equipo.'); return false; }
    if (!selDepto.value) { e.preventDefault(); alert('Selecciona un departamento.'); return false; }
    if (!selUsuario.value) { e.preventDefault(); alert('Selecciona un usuario.'); return false; }
    if (!selEquipo.value) { e.preventDefault(); alert('Selecciona un equipo del inventario.'); return false; }
    const tipoMant = document.querySelector('input[name="tipo_mantenimiento"]:checked');
    if (!tipoMant) { e.preventDefault(); alert('Selecciona el tipo de mantenimiento.'); return false; }
    const equipoText = selEquipo.options[selEquipo.selectedIndex].text;
    const fecha = document.getElementById('fecha_realizado').value;
    const mensaje = `¿Confirmas registrar el siguiente mantenimiento histórico?\n\nEquipo: ${equipoText}\nTipo: ${tipoMant.value === 'fisico' ? 'Físico' : 'Lógico'}\nFecha: ${fecha}\n\nEste registro se guardará como cerrado y aparecerá en los reportes.`;
    if (!confirm(mensaje)) e.preventDefault();
});
function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
</script>

</body>
</html>