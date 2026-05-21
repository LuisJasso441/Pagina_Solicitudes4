<?php
/**
 * Solicitar Mantenimiento de Equipo (Nuevo Flujo - Solo Sistemas)
 * dashboard/sistemas/ti_sistemas/mantenimientos/solicitar_mantenimiento.php
 *
 * Sistemas crea, selecciona usuario y equipo, y envia la solicitud al usuario
 * en un solo paso (estado inicial: pendiente).
 *
 * NOTA: Las evidencias NO se suben aqui. Se gestionan en ver_mantenimiento.php
 * cuando la solicitud entra en estado "en_proceso".
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'Solo el departamento de Sistemas puede crear solicitudes de mantenimiento.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// ------- Cargar departamentos -------
$departamentos = $pdo->query("
    SELECT id, codigo, nombre 
    FROM departamentos 
    WHERE activo = 1 
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// ------- Datos previos en caso de error -------
$form_data = $_SESSION['form_data'] ?? [];
$errores   = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

// ------- Cards de grupos -------
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
    <title>Solicitar Mantenimiento - TI / Sistemas</title>
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

    <style>
        .form-section {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .form-section-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            color: #495057;
            display: flex;
            align-items: center;
        }
        .form-label.required::after {
            content: " *";
            color: #dc3545;
            font-weight: 700;
        }

        /* Cards de grupo de equipo */
        .grupo-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1.25rem 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
            height: 100%;
            user-select: none;
        }
        .grupo-card:hover {
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(13,110,253,.12);
        }
        .grupo-card.selected {
            border-color: #0d6efd;
            background: #e7f1ff;
            box-shadow: 0 4px 10px rgba(13,110,253,.18);
        }
        .grupo-card .icono {
            font-size: 2rem;
            line-height: 1;
            margin-bottom: 0.4rem;
            color: #6c757d;
        }
        .grupo-card.selected .icono { color: #0d6efd; }
        .grupo-card .label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
        }

        /* Tarjetas de tipo de mantenimiento */
        .tipo-mant-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
            height: 100%;
        }
        .tipo-mant-card:hover { border-color: #0d6efd; }
        .tipo-mant-card.selected { border-color: #0d6efd; background: #e7f1ff; }

        /* Prioridad pills */
        .prioridad-option {
            border: 2px solid #dee2e6;
            border-radius: 50rem;
            padding: 0.4rem 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.15s;
            background: #fff;
            user-select: none;
        }
        .prioridad-option:hover { border-color: #0d6efd; }
        .prioridad-option .dot {
            width: 14px; height: 14px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        .prioridad-option.alta   .dot { background: #dc3545; }
        .prioridad-option.media  .dot { background: #ffc107; }
        .prioridad-option.baja   .dot { background: #198754; }
        .prioridad-option.selected.alta   { background: #f8d7da; border-color: #dc3545; }
        .prioridad-option.selected.media  { background: #fff3cd; border-color: #ffc107; }
        .prioridad-option.selected.baja   { background: #d1e7dd; border-color: #198754; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/../../../../includes/sidebar/sidebar_ti.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado -->
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h4 class="mb-0">
                    <i class="bi bi-tools me-2"></i>Solicitud de Mantenimiento a Equipos Electrónicos
                </h4>
            </div>

            <!-- Errores -->
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Por favor corrige lo siguiente:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errores as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/procesar_mantenimiento.php"
                  method="POST" id="formMantenimiento" novalidate>
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="grupo_equipo" id="grupo_equipo"
                       value="<?php echo htmlspecialchars($form_data['grupo_equipo'] ?? ''); ?>">

                <div class="row">
                    <!-- Columna principal -->
                    <div class="col-lg-8">

                        <!-- 1) Cards: ¿Qué tipo de equipo? -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-grid me-2"></i>¿Qué tipo de equipo necesita mantenimiento?
                            </div>
                            <div class="row g-3">
                                <?php foreach ($grupos_cards as $key => $info): ?>
                                    <div class="col-6 col-md">
                                        <div class="grupo-card <?php echo (($form_data['grupo_equipo'] ?? '') === $key) ? 'selected' : ''; ?>"
                                             data-grupo="<?php echo $key; ?>"
                                             onclick="seleccionarGrupo('<?php echo $key; ?>')">
                                            <div class="icono"><i class="bi <?php echo $info['icono']; ?>"></i></div>
                                            <div class="label"><?php echo $info['nombre']; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 2) Departamento -> Usuario -> Equipo -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-building me-2"></i>Destinatario y equipo
                            </div>

                            <div class="row g-3">
                                <!-- Departamento (PRIMERO) -->
                                <div class="col-md-6">
                                    <label class="form-label required">Departamento</label>
                                    <select name="departamento_id" id="departamento_id"
                                            class="form-select" required>
                                        <option value="">-- Seleccionar Departamento --</option>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo $d['id']; ?>"
                                                <?php echo (($form_data['departamento_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Usuario (depende del Departamento) -->
                                <div class="col-md-6">
                                    <label class="form-label required">Usuario</label>
                                    <select name="usuario_id" id="usuario_id"
                                            class="form-select" required disabled>
                                        <option value="">-- Seleccione un departamento primero --</option>
                                    </select>
                                    <small class="text-muted" id="usuariosHint"></small>
                                </div>

                                <!-- Equipo Electrónico (depende de Departamento + Grupo) -->
                                <div class="col-12">
                                    <label class="form-label required">Equipo Electrónico</label>
                                    <select name="equipo_id" id="equipo_id"
                                            class="form-select" required disabled>
                                        <option value="">-- Seleccione departamento y tipo de equipo --</option>
                                    </select>
                                    <small class="text-muted" id="equiposHint">
                                        El listado se filtra por el departamento y el tipo de equipo seleccionado.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- 3) Tipo de Mantenimiento -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-gear me-2"></i>Tipo de Mantenimiento
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="tipo-mant-card <?php echo (($form_data['tipo_mantenimiento'] ?? '') === 'logico') ? 'selected' : ''; ?>"
                                         onclick="seleccionarTipoMant('logico')" id="cardLogico">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="tipo_mantenimiento"
                                                   id="tipoLogico" value="logico" required
                                                   <?php echo (($form_data['tipo_mantenimiento'] ?? '') === 'logico') ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="tipoLogico">
                                                <i class="bi bi-cpu text-primary me-1"></i> Lógico
                                            </label>
                                            <small class="d-block text-muted mt-1">
                                                Respaldar información, eliminación de archivos, limpieza de software,
                                                actualizaciones del equipo.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="tipo-mant-card <?php echo (($form_data['tipo_mantenimiento'] ?? '') === 'fisico') ? 'selected' : ''; ?>"
                                         onclick="seleccionarTipoMant('fisico')" id="cardFisico">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="tipo_mantenimiento"
                                                   id="tipoFisico" value="fisico" required
                                                   <?php echo (($form_data['tipo_mantenimiento'] ?? '') === 'fisico') ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="tipoFisico">
                                                <i class="bi bi-tools text-success me-1"></i> Físico
                                            </label>
                                            <small class="d-block text-muted mt-1">
                                                Limpiar, reparar y optimizar los componentes tangibles del equipo.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4) Prioridad -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-flag me-2"></i>Prioridad
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
                                $prioridad_sel = $form_data['prioridad'] ?? 'media';
                                foreach ($prioridades as $key => $label):
                                ?>
                                    <label class="prioridad-option <?php echo $key; ?> <?php echo $prioridad_sel === $key ? 'selected' : ''; ?>"
                                           onclick="seleccionarPrioridad('<?php echo $key; ?>')">
                                        <span class="dot"></span>
                                        <input type="radio" name="prioridad" value="<?php echo $key; ?>"
                                               class="d-none" <?php echo $prioridad_sel === $key ? 'checked' : ''; ?>>
                                        <span><?php echo $label; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 5) Fecha y Hora programada -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-calendar-event me-2"></i>
                                Fecha y Hora programada para realizar el mantenimiento
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Fecha</label>
                                    <input type="date" name="fecha_deseada" id="fecha_deseada"
                                           class="form-control" required
                                           value="<?php echo htmlspecialchars($form_data['fecha_deseada'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Hora</label>
                                    <input type="time" name="hora_deseada" id="hora_deseada"
                                           class="form-control" required
                                           value="<?php echo htmlspecialchars($form_data['hora_deseada'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 6) Descripción -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-chat-left-text me-2"></i>Descripción del problema
                            </div>
                            <label class="form-label required">Describa el problema o motivo del mantenimiento</label>
                            <textarea name="descripcion_problema" id="descripcion_problema"
                                      class="form-control" rows="5" required
                                      minlength="10" maxlength="2000"
                                      placeholder="Describa el motivo del mantenimiento, síntomas observados, mensajes de error, etc."><?php echo htmlspecialchars($form_data['descripcion_problema'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Mínimo 10 caracteres.</small>
                                <small class="text-muted"><span id="charCount">0</span>/2000</small>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex gap-2 mb-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Enviar Solicitud
                            </button>
                            <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php"
                               class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancelar
                            </a>
                        </div>

                    </div>

                    <!-- Columna lateral -->
                    <div class="col-lg-4">
                        <div class="card border-info mb-3">
                            <div class="card-header bg-info text-white py-2">
                                <i class="bi bi-info-circle me-2"></i>Información
                            </div>
                            <div class="card-body small">
                                <p class="mb-2">
                                    <strong>Creada por:</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Departamento creador:</strong><br>
                                    Sistemas / TI
                                </p>
                                <hr class="my-2">
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    La solicitud se enviará al usuario seleccionado con estado <strong>"Pendiente"</strong>
                                    y se le notificará automáticamente. Las evidencias se podrán adjuntar al iniciar el mantenimiento.
                                </p>
                            </div>
                        </div>

                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark py-2">
                                <i class="bi bi-lightbulb me-2"></i>Flujo
                            </div>
                            <div class="card-body small">
                                <ol class="ps-3 mb-0">
                                    <li>Sistemas crea y envía (Pendiente)</li>
                                    <li>Sistemas inicia mantenimiento (En Proceso)</li>
                                    <li>Sistemas finaliza (1er cierre - Finalizada)</li>
                                    <li>Usuario firma (2do cierre - Cerrada)</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

<script>
const URL_API = '<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/api_mantenimiento.php';

const PRESELECCION = {
    departamento_id: '<?php echo intval($form_data['departamento_id'] ?? 0); ?>',
    usuario_id:      '<?php echo intval($form_data['usuario_id'] ?? 0); ?>',
    equipo_id:       '<?php echo intval($form_data['equipo_id'] ?? 0); ?>',
    grupo_equipo:    '<?php echo htmlspecialchars($form_data['grupo_equipo'] ?? ''); ?>'
};

// =====================================================================
// CASCADA DEPARTAMENTO -> USUARIO -> EQUIPO
// =====================================================================
const selDepto   = document.getElementById('departamento_id');
const selUsuario = document.getElementById('usuario_id');
const selEquipo  = document.getElementById('equipo_id');
const inpGrupo   = document.getElementById('grupo_equipo');

selDepto.addEventListener('change', async function() {
    const deptoId = this.value;
    resetUsuario();
    resetEquipo();
    if (deptoId) {
        await cargarUsuarios(deptoId);
        if (inpGrupo.value) {
            await cargarEquipos(deptoId, inpGrupo.value, '');
        }
    }
});

selUsuario.addEventListener('change', async function() {
    const deptoId = selDepto.value;
    const userId  = this.value;
    if (deptoId && inpGrupo.value) {
        await cargarEquipos(deptoId, inpGrupo.value, userId);
    }
});

function resetUsuario() {
    selUsuario.innerHTML = '<option value="">-- Seleccione un departamento primero --</option>';
    selUsuario.disabled = true;
    document.getElementById('usuariosHint').textContent = '';
}

function resetEquipo() {
    selEquipo.innerHTML = '<option value="">-- Seleccione departamento y tipo de equipo --</option>';
    selEquipo.disabled = true;
}

async function cargarUsuarios(deptoId) {
    selUsuario.innerHTML = '<option value="">Cargando...</option>';
    selUsuario.disabled = true;
    try {
        const r = await fetch(URL_API + '?accion=usuarios&departamento_id=' + encodeURIComponent(deptoId));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Error al cargar usuarios');

        if (!j.data.length) {
            selUsuario.innerHTML = '<option value="">-- Sin usuarios activos en este departamento --</option>';
            document.getElementById('usuariosHint').textContent = 'No hay usuarios activos asignados a este departamento.';
            return;
        }
        let html = '<option value="">-- Seleccionar Usuario --</option>';
        j.data.forEach(u => {
            const sel = (String(u.id) === String(PRESELECCION.usuario_id)) ? 'selected' : '';
            html += `<option value="${u.id}" ${sel}>${escapeHtml(u.nombre_completo)}</option>`;
        });
        selUsuario.innerHTML = html;
        selUsuario.disabled = false;
        document.getElementById('usuariosHint').textContent = `${j.data.length} usuario(s) disponibles.`;
    } catch (err) {
        console.error(err);
        selUsuario.innerHTML = '<option value="">-- Error al cargar usuarios --</option>';
    }
}

async function cargarEquipos(deptoId, grupo, usuarioId) {
    selEquipo.innerHTML = '<option value="">Cargando...</option>';
    selEquipo.disabled = true;
    try {
        let url = URL_API + '?accion=equipos&departamento_id=' + encodeURIComponent(deptoId)
                          + '&grupo=' + encodeURIComponent(grupo);
        if (usuarioId) url += '&usuario_id=' + encodeURIComponent(usuarioId);

        const r = await fetch(url);
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Error al cargar equipos');

        if (!j.data.length) {
            selEquipo.innerHTML = '<option value="">-- Sin equipos en este departamento para el tipo seleccionado --</option>';
            document.getElementById('equiposHint').innerHTML =
                '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No hay equipos activos del tipo seleccionado en este departamento.</span>';
            return;
        }

        let html = '<option value="">-- Seleccionar Equipo --</option>';
        j.data.forEach(e => {
            const partes = [];
            if (e.hostname)       partes.push(e.hostname);
            if (e.codigo_interno) partes.push(e.codigo_interno);
            const ref = partes.join(' / ') || '(sin identificador)';
            const tipo = e.tipo_equipo || '';
            const marca_modelo = [e.marca, e.modelo].filter(Boolean).join(' ');
            const ubic = e.ubicacion ? ' - ' + e.ubicacion : '';
            const star = e.asignado_al_usuario ? '⭐ ' : '';
            const texto = `${star}${ref} (${tipo})${marca_modelo ? ' · ' + marca_modelo : ''}${ubic}`;
            const sel = (String(e.id) === String(PRESELECCION.equipo_id)) ? 'selected' : '';
            html += `<option value="${e.id}" ${sel}>${escapeHtml(texto)}</option>`;
        });
        selEquipo.innerHTML = html;
        selEquipo.disabled = false;
        document.getElementById('equiposHint').innerHTML =
            `<span class="text-muted">${j.data.length} equipo(s). ⭐ = asignado al usuario seleccionado.</span>`;
    } catch (err) {
        console.error(err);
        selEquipo.innerHTML = '<option value="">-- Error al cargar equipos --</option>';
    }
}

// =====================================================================
// CARDS DE GRUPO
// =====================================================================
function seleccionarGrupo(grupo) {
    document.querySelectorAll('.grupo-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.grupo-card[data-grupo="${grupo}"]`);
    if (card) card.classList.add('selected');
    inpGrupo.value = grupo;

    const deptoId = selDepto.value;
    if (deptoId) {
        cargarEquipos(deptoId, grupo, selUsuario.value || '');
    }
}

// =====================================================================
// TIPO DE MANTENIMIENTO (cards)
// =====================================================================
function seleccionarTipoMant(tipo) {
    document.getElementById('cardLogico').classList.remove('selected');
    document.getElementById('cardFisico').classList.remove('selected');
    document.getElementById('card' + (tipo === 'logico' ? 'Logico' : 'Fisico')).classList.add('selected');
    document.getElementById('tipo' + (tipo === 'logico' ? 'Logico' : 'Fisico')).checked = true;
}

// =====================================================================
// PRIORIDAD (pills)
// =====================================================================
function seleccionarPrioridad(p) {
    document.querySelectorAll('.prioridad-option').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.prioridad-option.${p}`).classList.add('selected');
    document.querySelector(`input[name="prioridad"][value="${p}"]`).checked = true;
}

// =====================================================================
// CONTADOR DE CARACTERES
// =====================================================================
const txtDesc = document.getElementById('descripcion_problema');
const charCount = document.getElementById('charCount');
function actualizarContador() {
    charCount.textContent = txtDesc.value.length;
}
txtDesc.addEventListener('input', actualizarContador);
actualizarContador();

// =====================================================================
// VALIDACION AL ENVIAR
// =====================================================================
document.getElementById('formMantenimiento').addEventListener('submit', function(e) {
    if (!inpGrupo.value) {
        e.preventDefault();
        alert('Selecciona el tipo de equipo (Cómputo, Periféricos, Impresoras, Red u Otros).');
        return false;
    }
    if (!selDepto.value) {
        e.preventDefault();
        alert('Selecciona un departamento.');
        return false;
    }
    if (!selUsuario.value) {
        e.preventDefault();
        alert('Selecciona un usuario destinatario.');
        return false;
    }
    if (!selEquipo.value) {
        e.preventDefault();
        alert('Selecciona un equipo del inventario.');
        return false;
    }
    const tipoMant = document.querySelector('input[name="tipo_mantenimiento"]:checked');
    if (!tipoMant) {
        e.preventDefault();
        alert('Selecciona el tipo de mantenimiento (Lógico o Físico).');
        return false;
    }
});

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
}

(async function init() {
    if (PRESELECCION.departamento_id) {
        await cargarUsuarios(PRESELECCION.departamento_id);
        if (PRESELECCION.grupo_equipo) {
            await cargarEquipos(
                PRESELECCION.departamento_id,
                PRESELECCION.grupo_equipo,
                PRESELECCION.usuario_id
            );
        }
    }
})();
</script>

</body>
</html>