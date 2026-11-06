<?php
/**
 * Programación General de Mantenimientos (Solo Sistemas)
 * dashboard/sistemas/ti_sistemas/mantenimientos/programacion_mantenimientos.php
 *
 * Muestra por departamento los equipos de cómputo activos (All in One, PC, Laptop)
 * para asignarles tipo de mantenimiento y fecha. Al crear la programación se genera
 * una solicitud "pendiente" por equipo (ver procesar_programacion.php).
 *
 * - Sin pendientes: tipo y fecha obligatorios
 * - Con mantenimiento pendiente: solo se avisa; cualquier tipo, opcional
 * - Pendientes con fecha pasada: marcados como "Vencido"
 * - Sin usuario activo o sin departamento: no programables (corregir en inventario)
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/mantenimientos_ti/programacion_funciones.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas  = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'Solo el departamento de Sistemas puede programar mantenimientos.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// ------- Datos previos en caso de error -------
$form_data = $_SESSION['form_data_prog'] ?? [];
$errores   = $_SESSION['form_errors_prog'] ?? [];
unset($_SESSION['form_data_prog'], $_SESSION['form_errors_prog']);

// ------- Datos -------
$tipos_equipo  = prog_tipos_computo();
$tipos_mant    = prog_tipos_mantenimiento();
$equipos       = prog_obtener_equipos($pdo);
$departamentos = prog_obtener_departamentos($pdo);

$por_depto = [];
$sin_depto = [];
$conteo = [
    'programable' => 0, 'con_pendiente' => 0,
    'sin_usuario' => 0, 'sin_departamento' => 0, 'vencidos' => 0,
];

foreach ($equipos as $eq) {
    $conteo[$eq['estado_fila']]++;
    foreach ($eq['pendientes'] as $p) {
        if ($p['vencido']) $conteo['vencidos']++;
    }
    if (!empty($eq['departamento_id'])) {
        $por_depto[intval($eq['departamento_id'])][] = $eq;
    } else {
        $sin_depto[] = $eq;
    }
}

$total_capturables = $conteo['programable'] + $conteo['con_pendiente'];
$hoy = date('Y-m-d');
$current_page = basename(__FILE__);

// =====================================================================
// RENDER DE FILA
// =====================================================================
function prog_render_fila(array $eq, array $form_data, string $hoy, array $tipos_equipo, array $tipos_mant): void {
    $id     = intval($eq['id']);
    $estado = $eq['estado_fila'];
    $codigo = trim($eq['codigo_interno'] ?? '');
    $host   = trim($eq['hostname'] ?? '');
    $marca_modelo = trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''));
    $tipo_nombre  = $tipos_equipo[$eq['tipo_equipo']] ?? $eq['tipo_equipo'];

    $tipo_sel  = $form_data[$id]['tipo']  ?? '';
    $fecha_sel = $form_data[$id]['fecha'] ?? '';

    $clases = [
        'programable'      => 'js-fila-prog',
        'con_pendiente'    => 'js-fila-pendiente fila-pendiente',
        'sin_usuario'      => 'fila-bloqueada',
        'sin_departamento' => 'fila-bloqueada',
    ];
    $iconos_mant = ['fisico' => 'bi-tools', 'logico' => 'bi-cpu'];
    $badges_mant = ['fisico' => 'bg-primary', 'logico' => 'bg-success'];
    ?>
    <tr class="<?php echo $clases[$estado] ?? ''; ?>">
        <!-- Usuario(s) -->
        <td>
            <?php if (empty($eq['usuarios'])): ?>
                <small class="text-muted fst-italic">Sin usuario asignado</small>
            <?php else: ?>
                <?php foreach ($eq['usuarios'] as $u): ?>
                    <div><i class="bi bi-person me-1 text-muted"></i><?php echo prog_h($u['nombre']); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </td>

        <!-- Equipo (+ mantenimientos pendientes) -->
        <td>
            <span class="fw-semibold"><?php echo prog_h($codigo !== '' ? $codigo : ($host !== '' ? $host : '—')); ?></span>
            <?php if ($codigo !== '' && $host !== '' && strcasecmp($codigo, $host) !== 0): ?>
                <small class="text-muted">(<?php echo prog_h($host); ?>)</small>
            <?php endif; ?>
            <br>
            <small class="text-muted">
                <?php echo prog_h($tipo_nombre); ?><?php echo $marca_modelo !== '' ? ' · ' . prog_h($marca_modelo) : ''; ?>
            </small>

            <?php foreach ($eq['pendientes'] as $t => $p): ?>
                <div class="prog-pendiente mt-1">
                    <span class="badge <?php echo $badges_mant[$t]; ?>">
                        <i class="bi <?php echo $iconos_mant[$t]; ?>"></i> <?php echo $tipos_mant[$t]; ?>
                    </span>
                    <small>
                        <?php echo $p['estado'] === 'en_proceso' ? 'En proceso' : 'Pendiente'; ?>
                        · <?php echo $p['fecha_deseada'] ? date('d/m/Y', strtotime($p['fecha_deseada'])) : 'Sin fecha'; ?>
                        · <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=<?php echo intval($p['id']); ?>"><?php echo prog_h($p['folio']); ?></a>
                    </small>
                    <?php if ($p['vencido']): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-circle"></i> Vencido</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </td>

        <?php if ($estado === 'programable' || $estado === 'con_pendiente'):
            $obligatorio = ($estado === 'programable');
        ?>
            <!-- Tipo de Mantenimiento -->
            <td>
                <select name="programacion[<?php echo $id; ?>][tipo]"
                        class="form-select form-select-sm js-tipo" <?php echo $obligatorio ? 'required' : ''; ?>>
                    <option value=""><?php echo $obligatorio ? '-- Seleccionar --' : '-- Opcional --'; ?></option>
                    <?php foreach ($tipos_mant as $t => $nombre_t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $tipo_sel === $t ? 'selected' : ''; ?>>
                            <?php echo $nombre_t; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$obligatorio): ?>
                    <small class="text-warning-emphasis d-block mt-1">
                        <i class="bi bi-exclamation-triangle me-1"></i>Ya tiene mantenimiento pendiente. Programar es opcional.
                    </small>
                <?php endif; ?>
            </td>
            <!-- Fecha -->
            <td>
                <input type="date" name="programacion[<?php echo $id; ?>][fecha]"
                       class="form-control form-control-sm js-fecha"
                       min="<?php echo $hoy; ?>"
                       value="<?php echo prog_h($fecha_sel); ?>" <?php echo $obligatorio ? 'required' : ''; ?>>
            </td>

        <?php elseif ($estado === 'sin_usuario'): ?>
            <td colspan="2" class="small text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Sin usuario activo asignado. Asígnalo en el inventario para poder programarlo.
            </td>

        <?php else: ?>
            <td colspan="2" class="small text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Sin departamento en el inventario. Asígnalo para poder programarlo.
            </td>
        <?php endif; ?>
    </tr>
    <?php
}

/**
 * Tabla de equipos (encabezado según el diseño)
 */
function prog_render_tabla(array $lista, array $form_data, string $hoy, array $tipos_equipo, array $tipos_mant): void {
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-prog">
            <thead>
                <tr>
                    <th>Nombre de usuario</th>
                    <th>Equipo</th>
                    <th>Tipo de Mantenimiento</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $eq) prog_render_fila($eq, $form_data, $hoy, $tipos_equipo, $tipos_mant); ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación de Mantenimientos - TI / Sistemas</title>
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
        .resumen-card { border: 1px solid #dee2e6; }
        .resumen-card .num   { font-size: 1.5rem; font-weight: 700; line-height: 1; }
        .resumen-card .label { font-size: 0.72rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.4px; }
        .resumen-card.b-primary   { border-top: 3px solid #0d6efd; }
        .resumen-card.b-info      { border-top: 3px solid #0dcaf0; }
        .resumen-card.b-secondary { border-top: 3px solid #6c757d; }
        .resumen-card.b-danger    { border-top: 3px solid #dc3545; }
        .resumen-card.b-warning   { border-top: 3px solid #ffc107; }

        .prog-depto .card-header h6 { font-weight: 600; }
        .table-prog { margin-bottom: 0; }
        .table-prog th {
            font-size: 0.78rem;
            font-weight: 600;
            text-align: center;
            background: #f8f9fa;
        }
        .table-prog td { vertical-align: middle; font-size: 0.85rem; }
        .table-prog th:nth-child(1), .table-prog td:nth-child(1) { width: 25%; }
        .table-prog th:nth-child(2), .table-prog td:nth-child(2) { width: 35%; }
        .table-prog th:nth-child(3), .table-prog td:nth-child(3) { width: 20%; }
        .table-prog th:nth-child(4), .table-prog td:nth-child(4) { width: 20%; }

        .fila-bloqueada td  { background-color: #fff8e1; }
        .fila-pendiente td  { background-color: #f4f9ff; }
        .prog-pendiente .badge { font-size: 0.68rem; }

        .prog-rapido .form-select,
        .prog-rapido .form-control { width: auto; min-width: 140px; }

        /* Barra inferior fija con contador y botón */
        .prog-barra {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: #fff;
            border-top: 1px solid #dee2e6;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
            padding: 0.75rem 1rem;
            margin: 0 -0.25rem;
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php include __DIR__ . '/../../../../includes/sidebar/sidebar_ti.php'; ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Programación de Mantenimientos</h4>
                        <small class="text-muted">Equipos de cómputo (All in One, PC y Laptop) por departamento</small>
                    </div>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/calendario_mantenimientos.php"
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-calendar3 me-1"></i>Ver calendario
                </a>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>No se creó la programación. Corrige lo siguiente:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errores as $err): ?>
                            <li><?php echo prog_h($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Resumen -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md">
                    <div class="card resumen-card b-primary">
                        <div class="card-body text-center py-2">
                            <div class="num text-primary"><?php echo $conteo['programable']; ?></div>
                            <div class="label">Por programar</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card resumen-card b-info">
                        <div class="card-body text-center py-2">
                            <div class="num text-info"><?php echo $conteo['con_pendiente']; ?></div>
                            <div class="label">Con mantenimiento pendiente</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card resumen-card b-danger">
                        <div class="card-body text-center py-2">
                            <div class="num text-danger"><?php echo $conteo['vencidos']; ?></div>
                            <div class="label">Pendientes vencidos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md">
                    <div class="card resumen-card b-warning">
                        <div class="card-body text-center py-2">
                            <div class="num text-warning"><?php echo $conteo['sin_usuario'] + $conteo['sin_departamento']; ?></div>
                            <div class="label">Sin usuario / depto.</div>
                        </div>
                    </div>
                </div>
            </div>

            <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/mantenimientos/procesar_programacion.php"
                  method="POST" id="formProgramacion" novalidate>

                <?php foreach ($departamentos as $d):
                    $lista  = $por_depto[intval($d['id'])] ?? [];
                    $n_prog = count(array_filter($lista, fn($e) => $e['estado_fila'] === 'programable'));
                ?>
                    <div class="card prog-depto mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0">
                                <i class="bi bi-building me-2"></i>Departamento: <?php echo prog_h($d['nombre']); ?>
                                <span class="badge bg-secondary ms-1"><?php echo count($lista); ?></span>
                            </h6>

                            <?php if ($n_prog > 1): ?>
                                <!-- Llenado rápido (solo filas obligatorias del departamento) -->
                                <div class="prog-rapido d-flex align-items-center gap-1 flex-wrap"
                                     title="Se aplica a los equipos sin mantenimientos pendientes">
                                    <small class="text-muted me-1">Llenado rápido:</small>
                                    <select class="form-select form-select-sm js-rapido-tipo">
                                        <option value="">Tipo...</option>
                                        <option value="fisico">Físico</option>
                                        <option value="logico">Lógico</option>
                                    </select>
                                    <input type="date" class="form-control form-control-sm js-rapido-fecha" min="<?php echo $hoy; ?>">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-rapido-aplicar">
                                        <i class="bi bi-arrow-down-square me-1"></i>Aplicar a todos
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($lista)): ?>
                                <div class="text-center text-muted small py-3">
                                    <i class="bi bi-inbox me-1"></i>Este departamento no cuenta con equipos de cómputo asignados.
                                </div>
                            <?php else: ?>
                                <?php prog_render_tabla($lista, $form_data, $hoy, $tipos_equipo, $tipos_mant); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($sin_depto)): ?>
                    <div class="card prog-depto border-warning mb-3">
                        <div class="card-header bg-warning-subtle">
                            <h6 class="mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>Equipos sin departamento en inventario
                                <span class="badge bg-secondary ms-1"><?php echo count($sin_depto); ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php prog_render_tabla($sin_depto, $form_data, $hoy, $tipos_equipo, $tipos_mant); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Barra inferior -->
                <div class="prog-barra d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small">
                        <?php if ($total_capturables > 0): ?>
                            <i class="bi bi-list-check me-1"></i>
                            <?php if ($conteo['programable'] > 0): ?>
                                <strong id="contadorObligatorios">0</strong> de <strong><?php echo $conteo['programable']; ?></strong>
                                obligatorio(s) completos
                            <?php endif; ?>
                            <?php if ($conteo['con_pendiente'] > 0): ?>
                                <?php echo $conteo['programable'] > 0 ? ' · ' : ''; ?>
                                <strong id="contadorOpcionales">0</strong> opcional(es) capturados
                            <?php endif; ?>
                        <?php else: ?>
                            <i class="bi bi-check-circle text-success me-1"></i>
                            No hay equipos pendientes por programar.
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-primary" id="btnCrearProgramacion"
                        <?php echo $total_capturables === 0 ? 'disabled' : ''; ?>>
                        <i class="bi bi-calendar-check me-1"></i>Crear Programación
                    </button>
                </div>
            </form>

        </div>
    </main>
</div>

<!-- Modal confirmación -->
<div class="modal fade" id="modalConfirmarProgramacion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-check me-2"></i>Crear Programación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Se crearán <strong id="modalTotal">0</strong> solicitud(es) de mantenimiento
                    en estado <strong>Pendiente</strong>.
                </p>
                <p class="small text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Los usuarios no recibirán notificación. Las fechas podrán editarse después desde el detalle
                    de cada mantenimiento mientras siga pendiente.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarProgramacion">
                    <i class="bi bi-check-lg me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

<script>
const HOY        = '<?php echo $hoy; ?>';
const form       = document.getElementById('formProgramacion');
const btnCrear   = document.getElementById('btnCrearProgramacion');
const contObl    = document.getElementById('contadorObligatorios');
const contOpc    = document.getElementById('contadorOpcionales');
const modalTotal = document.getElementById('modalTotal');
const modalConf  = new bootstrap.Modal(document.getElementById('modalConfirmarProgramacion'));

function valores(tr) {
    return {
        t: tr.querySelector('.js-tipo'),
        f: tr.querySelector('.js-fecha'),
    };
}
function filaCompleta(tr) {
    const { t, f } = valores(tr);
    return t.value !== '' && f.value !== '' && f.value >= HOY;
}

// =====================================================================
// CONTADORES
// =====================================================================
function contar() {
    let obl = 0, opc = 0;
    form.querySelectorAll('tr.js-fila-prog').forEach(tr => { if (filaCompleta(tr)) obl++; });
    form.querySelectorAll('tr.js-fila-pendiente').forEach(tr => { if (filaCompleta(tr)) opc++; });
    return { obl, opc };
}
function actualizarContador() {
    const { obl, opc } = contar();
    if (contObl) contObl.textContent = obl;
    if (contOpc) contOpc.textContent = opc;
}

form.addEventListener('change', e => {
    if (e.target.matches('.js-tipo, .js-fecha')) {
        const tr = e.target.closest('tr');
        const { t, f } = valores(tr);
        t.classList.remove('is-invalid');
        f.classList.remove('is-invalid');
        actualizarContador();
    }
});

// =====================================================================
// LLENADO RÁPIDO POR DEPARTAMENTO (solo filas obligatorias)
// =====================================================================
document.querySelectorAll('.js-rapido-aplicar').forEach(btn => {
    btn.addEventListener('click', () => {
        const card  = btn.closest('.prog-depto');
        const tipo  = card.querySelector('.js-rapido-tipo').value;
        const fecha = card.querySelector('.js-rapido-fecha').value;

        if (!tipo && !fecha) {
            alert('Elige un tipo de mantenimiento y/o una fecha para aplicar.');
            return;
        }
        if (fecha && fecha < HOY) {
            alert('La fecha no puede ser anterior a hoy.');
            return;
        }

        card.querySelectorAll('tr.js-fila-prog').forEach(tr => {
            const { t, f } = valores(tr);
            if (tipo)  { t.value = tipo;  t.classList.remove('is-invalid'); }
            if (fecha) { f.value = fecha; f.classList.remove('is-invalid'); }
        });
        actualizarContador();
    });
});

// =====================================================================
// VALIDACIÓN Y CONFIRMACIÓN
// - Obligatorias: tipo y fecha
// - Opcionales: si se captura uno de los dos, se requiere el otro
// =====================================================================
btnCrear.addEventListener('click', () => {
    const invalidos = [];

    const marcar = (el, malo) => {
        el.classList.toggle('is-invalid', malo);
        if (malo) invalidos.push(el);
    };

    form.querySelectorAll('tr.js-fila-prog').forEach(tr => {
        const { t, f } = valores(tr);
        marcar(t, t.value === '');
        marcar(f, f.value === '' || f.value < HOY);
    });

    form.querySelectorAll('tr.js-fila-pendiente').forEach(tr => {
        const { t, f } = valores(tr);
        const algo = t.value !== '' || f.value !== '';
        marcar(t, algo && t.value === '');
        marcar(f, algo && (f.value === '' || f.value < HOY));
    });

    if (invalidos.length) {
        invalidos[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        invalidos[0].focus({ preventScroll: true });
        const filas = new Set(invalidos.map(el => el.closest('tr'))).size;
        alert(`Faltan datos en ${filas} equipo(s). Cada equipo a programar debe tener tipo de mantenimiento y una fecha a partir de hoy.`);
        return;
    }

    const { obl, opc } = contar();
    const total = obl + opc;
    if (total === 0) {
        alert('No hay mantenimientos por crear. Captura al menos un equipo.');
        return;
    }

    modalTotal.textContent = total;
    modalConf.show();
});

document.getElementById('btnConfirmarProgramacion').addEventListener('click', function () {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creando...';
    form.submit();
});

actualizarContador();
</script>

</body>
</html>