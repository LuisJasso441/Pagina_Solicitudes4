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

                        <!-- 2) Equipo -> Departamento y Usuario (automáticos) -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-pc-display-horizontal me-2"></i>Equipo y destinatario
                            </div>

                            <div class="row g-3">
                                <!-- Equipo Electrónico (PRIMERO, depende del tipo de equipo) -->
                                <div class="col-12">
                                    <label class="form-label required">Equipo Electrónico</label>
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" id="buscarEquipo" class="form-control"
                                               placeholder="Buscar por código interno, hostname, marca, modelo, departamento o usuario..."
                                               autocomplete="off" disabled>
                                    </div>
                                    <select name="equipo_id" id="equipo_id"
                                            class="form-select" required disabled>
                                        <option value="">-- Seleccione primero el tipo de equipo --</option>
                                    </select>
                                    <small class="text-muted" id="equiposHint">
                                        Seleccione el tipo de equipo para ver el listado.
                                    </small>
                                </div>

                                <!-- Departamento (automático según el equipo) -->
                                <div class="col-md-6">
                                    <label class="form-label">Departamento</label>
                                    <input type="text" id="departamento_nombre" class="form-control bg-light"
                                           placeholder="Se asigna según el equipo" readonly tabindex="-1">
                                    <input type="hidden" name="departamento_id" id="departamento_id" value="">
                                </div>

                                <!-- Usuario (automático según el equipo, editable) -->
                                <div class="col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <select name="usuario_id" id="usuario_id" class="form-select" disabled>
                                        <option value="">-- Seleccione un equipo primero --</option>
                                    </select>
                                    <small class="text-muted" id="usuariosHint"></small>
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
                                <i class="bi bi-chat-left-text me-2"></i>Descripción del mantenimiento
                            </div>
                            <label class="form-label required">Describa el mantenimiento a realizar</label>
                            <textarea name="descripcion_problema" id="descripcion_problema"
                                      class="form-control" rows="5" required
                                      minlength="10" maxlength="2000"
                                      placeholder="Ej. Mantenimiento preventivo correspondiente al mes de marzo."><?php echo htmlspecialchars($form_data['descripcion_problema'] ?? ''); ?></textarea>
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
    // Si no hay preselección se imprime cadena vacía, NO '0'.
    // En JavaScript la cadena '0' es truthy y provocaba llamadas con id 0.
    usuario_id:   '<?php echo !empty($form_data['usuario_id']) ? intval($form_data['usuario_id']) : ''; ?>',
    equipo_id:    '<?php echo !empty($form_data['equipo_id'])  ? intval($form_data['equipo_id'])  : ''; ?>',
    grupo_equipo: '<?php echo htmlspecialchars($form_data['grupo_equipo'] ?? ''); ?>'
};

// =====================================================================
// FLUJO: TIPO DE EQUIPO -> EQUIPO -> (DEPARTAMENTO + USUARIO automáticos)
// =====================================================================
const selEquipo      = document.getElementById('equipo_id');
const inpBuscar      = document.getElementById('buscarEquipo');
const inpDeptoId     = document.getElementById('departamento_id');
const inpDeptoNombre = document.getElementById('departamento_nombre');
const selUsuario     = document.getElementById('usuario_id');
const inpGrupo       = document.getElementById('grupo_equipo');
const hintEquipos    = document.getElementById('equiposHint');
const hintUsuarios   = document.getElementById('usuariosHint');

let equiposCache = [];   // equipos del grupo seleccionado (respuesta de la API)

selEquipo.addEventListener('change', function () {
    aplicarEquipo(this.value, null);
});

inpBuscar.addEventListener('input', function () {
    renderEquipos(this.value);
});

function resetDestinatario() {
    inpDeptoId.value = '';
    inpDeptoNombre.value = '';
    selUsuario.innerHTML = '<option value="">-- Seleccione un equipo primero --</option>';
    selUsuario.disabled = true;
    hintUsuarios.textContent = '';
}

// ---------------------------------------------------------------------
// Cargar equipos del grupo (todos los departamentos)
// ---------------------------------------------------------------------
async function cargarEquipos(grupo, restaurar = false) {
    resetDestinatario();
    equiposCache = [];
    inpBuscar.value = '';
    inpBuscar.disabled = true;
    selEquipo.innerHTML = '<option value="">Cargando...</option>';
    selEquipo.disabled = true;

    try {
        const r = await fetch(URL_API + '?accion=equipos&grupo=' + encodeURIComponent(grupo));
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Error al cargar equipos');

        equiposCache = j.data;

        if (!equiposCache.length) {
            selEquipo.innerHTML = '<option value="">-- Sin equipos activos de este tipo --</option>';
            hintEquipos.innerHTML =
                '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No hay equipos activos del tipo seleccionado.</span>';
            return;
        }

        renderEquipos('');
        selEquipo.disabled = false;
        inpBuscar.disabled = false;
        hintEquipos.innerHTML = `<span class="text-muted">${equiposCache.length} equipo(s) disponibles. Al elegir uno se asignan su departamento y usuario.</span>`;

        // Restaurar selección tras un error de validación
        if (restaurar && PRESELECCION.equipo_id &&
            equiposCache.some(e => String(e.id) === PRESELECCION.equipo_id)) {
            selEquipo.value = PRESELECCION.equipo_id;
            await aplicarEquipo(PRESELECCION.equipo_id, PRESELECCION.usuario_id);
        }
    } catch (err) {
        console.error(err);
        selEquipo.innerHTML = '<option value="">-- Error al cargar equipos --</option>';
    }
}

function textoEquipo(e) {
    const id   = e.codigo_interno || e.hostname || '(sin identificador)';
    const host = (e.hostname && e.codigo_interno &&
                  e.hostname.toLowerCase() !== e.codigo_interno.toLowerCase()) ? ` (${e.hostname})` : '';
    const marcaModelo = [e.marca, e.modelo].filter(Boolean).join(' ');
    const depto = e.departamento_nombre ? ' — ' + e.departamento_nombre : '';
    return `${id}${host} · ${e.tipo_equipo || ''}${marcaModelo ? ' · ' + marcaModelo : ''}${depto}`;
}

// Pinta el select aplicando el filtro de búsqueda
function renderEquipos(filtro) {
    const f = (filtro || '').trim().toLowerCase();
    const actual = selEquipo.value;

    const lista = !f ? equiposCache : equiposCache.filter(e =>
        [e.codigo_interno, e.hostname, e.marca, e.modelo, e.ubicacion,
         e.departamento_nombre, e.usuario_asignado_nombre, e.usuario_asignado_2_nombre]
            .filter(Boolean).join(' ').toLowerCase().includes(f)
    );

    let html = `<option value="">-- Seleccionar Equipo (${lista.length}) --</option>`;
    lista.forEach(e => {
        const sel = String(e.id) === actual ? 'selected' : '';
        html += `<option value="${e.id}" ${sel}>${escapeHtml(textoEquipo(e))}</option>`;
    });
    selEquipo.innerHTML = html;

    if (f && lista.length === 1) {
        // Un solo resultado: se selecciona solo
        selEquipo.value = String(lista[0].id);
        if (String(lista[0].id) !== actual) aplicarEquipo(selEquipo.value, null);
    } else if (actual && selEquipo.value !== actual) {
        // El equipo que estaba elegido quedó fuera del filtro
        resetDestinatario();
    }
}

// ---------------------------------------------------------------------
// Al elegir un equipo: llenar departamento y usuario
// usuarioPre = null  -> usar la regla automática (1er usuario asignado)
// usuarioPre = 'N'/'' -> restaurar lo que venía en el formulario
// ---------------------------------------------------------------------
async function aplicarEquipo(equipoId, usuarioPre) {
    const eq = equiposCache.find(e => String(e.id) === String(equipoId));
    if (!eq) { resetDestinatario(); return; }

    inpDeptoId.value     = eq.departamento_id || '';
    inpDeptoNombre.value = eq.departamento_nombre || '(Sin departamento en inventario)';

    await cargarUsuarios(eq, usuarioPre);
}

async function cargarUsuarios(eq, usuarioPre) {
    selUsuario.innerHTML = '<option value="">Cargando...</option>';
    selUsuario.disabled = true;

    // Usuarios asignados al equipo (solo activos), en orden: 1ro y 2do
    const asignados = [];
    if (eq.usuario_asignado_id && String(eq.usuario_asignado_activo) === '1') {
        asignados.push({ id: String(eq.usuario_asignado_id), nombre: eq.usuario_asignado_nombre });
    }
    if (eq.usuario_asignado_2_id && String(eq.usuario_asignado_2_activo) === '1') {
        asignados.push({ id: String(eq.usuario_asignado_2_id), nombre: eq.usuario_asignado_2_nombre });
    }

    // Resto de usuarios del departamento (para poder cambiarlo manualmente)
    let usuariosDepto = [];
    if (eq.departamento_id) {
        try {
            const r = await fetch(URL_API + '?accion=usuarios&departamento_id=' + encodeURIComponent(eq.departamento_id));
            const j = await r.json();
            if (j.success) usuariosDepto = j.data;
        } catch (err) {
            console.error(err);
        }
    }

    const idsAsignados = asignados.map(a => a.id);
    const otros = usuariosDepto.filter(u => !idsAsignados.includes(String(u.id)));

    let html = '<option value="">-- Sin usuario --</option>';
    if (asignados.length) {
        html += '<optgroup label="Asignados al equipo">';
        asignados.forEach(a => html += `<option value="${a.id}">⭐ ${escapeHtml(a.nombre)}</option>`);
        html += '</optgroup>';
    }
    if (otros.length) {
        html += '<optgroup label="Otros usuarios del departamento">';
        otros.forEach(u => html += `<option value="${u.id}">${escapeHtml(u.nombre_completo)}</option>`);
        html += '</optgroup>';
    }
    selUsuario.innerHTML = html;
    selUsuario.disabled = false;

    // Selección por defecto
    if (usuarioPre !== null) {
        selUsuario.value = usuarioPre;                      // restaurar formulario
    } else {
        selUsuario.value = asignados.length ? asignados[0].id : '';
    }

    // Mensaje de ayuda
    if (asignados.length === 2) {
        hintUsuarios.innerHTML = '<span class="text-info"><i class="bi bi-people me-1"></i>Equipo con 2 usuarios: se eligió el principal, puedes cambiar al segundo (⭐).</span>';
    } else if (asignados.length === 1) {
        hintUsuarios.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Usuario asignado al equipo seleccionado automáticamente.</span>';
    } else {
        hintUsuarios.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>El equipo no tiene usuario asignado. Puedes enviarla sin usuario o elegir uno del departamento.</span>';
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
    cargarEquipos(grupo);
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
    if (!selEquipo.value) {
        e.preventDefault();
        alert('Selecciona un equipo del inventario.');
        return false;
    }
    if (!inpDeptoId.value) {
        e.preventDefault();
        alert('El equipo seleccionado no tiene departamento asignado en el inventario. Corrígelo en el inventario antes de crear la solicitud.');
        return false;
    }
    const tipoMant = document.querySelector('input[name="tipo_mantenimiento"]:checked');
    if (!tipoMant) {
        e.preventDefault();
        alert('Selecciona el tipo de mantenimiento (Lógico o Físico).');
        return false;
    }
    if (!selUsuario.value &&
        !confirm('La solicitud se enviará sin usuario destinatario y nadie recibirá la notificación. ¿Deseas continuar?')) {
        e.preventDefault();
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
    if (PRESELECCION.grupo_equipo) {
        await cargarEquipos(PRESELECCION.grupo_equipo, true);
    }
})();
</script>

</body>
</html>