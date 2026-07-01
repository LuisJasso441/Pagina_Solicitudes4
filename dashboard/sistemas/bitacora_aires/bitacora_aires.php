<?php
/**
 * Bitácora de Registro de Aires Acondicionados
 * Dashboard principal — diseño tipo plantilla Excel
 * Ubicación: dashboard/sistemas/bitacora_aires/bitacora_aires.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/sistemas/bitacora_aires/bitacora_aires_funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$usuario_id        = (int)$_SESSION['usuario_id'];
$nombre_completo   = $_SESSION['nombre_completo'] ?? '';
$departamento     = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_mantenimiento = ($departamento === 'mantenimiento');
$es_ti            = in_array($departamento, ['sistemas', 'ti', 'ti_sistemas']);

// Datos del usuario para el modal de "Pedir Control"
$info_depto         = ba_obtener_departamento_usuario($usuario_id);
$departamento_id    = $info_depto['id'] ?? null;
$departamento_nombre = $info_depto['nombre'] ?? ($_SESSION['departamento_nombre'] ?? 'Sin departamento');

// Registros visibles
$registros = ba_obtener_registros($usuario_id);
$tiene_prestamo_activo = ba_usuario_tiene_prestamo_activo($usuario_id);

$alerta = obtener_alerta();

// =====================================================================
// LOGO — Resolución de imagen para el encabezado
// =====================================================================
// Para usar un logo específico de esta bitácora, sube el archivo a:
//   assets/img/logo_bitacora_aires.png
// Si no existe, se usa el logo general del sistema (assets/img/Logo.png).
$logo_path_fs = __DIR__ . '/../../../assets/img/logo_bitacora_aires.png';
$logo_url     = null;
if (file_exists($logo_path_fs)) {
    $logo_url = URL_BASE . 'assets/img/logo_bitacora_aires.png';
} elseif (file_exists(__DIR__ . '/../../../assets/img/Logo.png')) {
    $logo_url = URL_BASE . 'assets/img/Logo.png';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Registro Aires Acondicionados | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden Core'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">

    <!-- Notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>

    <style>
        /* ============================================================
           BITÁCORA AIRES — Diseño basado en la plantilla impresa
           ============================================================ */
        .bitacora-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        .bitacora-header {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }
        .ba-logo-box {
            width: 150px;
            min-height: 88px;
            background: #fff;
            border: 1px solid #d6d4cc;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9a9892;
            flex-shrink: 0;
            overflow: hidden;
            padding: 6px;
        }
        .ba-logo-box.is-placeholder {
            border-style: dashed;
            border-color: #b4b2a9;
        }
        .ba-logo-box img {
            max-width: 100%;
            max-height: 76px;
            object-fit: contain;
            display: block;
        }
        .ba-logo-box i {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        .ba-logo-box small {
            font-size: 0.7rem;
        }
        .ba-title-box {
            flex: 1;
            background: #fff;
            border: 1px solid #d6d4cc;
            border-radius: 6px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ba-title-box h1 {
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.5px;
            margin: 0;
            color: #1a1a1a;
            text-transform: uppercase;
        }

        .ba-encargado {
            background: #fff;
            border: 1px solid #d6d4cc;
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 14px;
            font-size: 0.88rem;
        }
        .ba-encargado strong { font-weight: 600; }
        .ba-encargado .ba-nombre-enc { color: #5f5e5a; }

        .ba-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .ba-btn-pedir {
            background: #e6f1fb;
            color: #185fa5;
            border: 1px solid #b5d4f4;
            padding: 7px 14px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .ba-btn-pedir:hover:not(:disabled) {
            background: #d4e5f6;
            color: #185fa5;
        }
        .ba-btn-pedir:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .ba-btn-descargar {
            background: #fff;
            color: #1a1a1a;
            border: 1px solid #b4b2a9;
            padding: 7px 14px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.15s;
        }
        .ba-btn-descargar:hover {
            background: #faf9f5;
            color: #1a1a1a;
        }

        .ba-table-wrapper {
            background: #fff;
            border: 1px solid #b4b2a9;
            border-radius: 6px;
            overflow-x: auto;
        }
        .ba-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            table-layout: fixed;
            min-width: 900px;
        }
        .ba-table thead tr {
            background: #ebe9e1;
        }
        .ba-table th {
            padding: 10px 6px;
            border: 1px solid #d6d4cc;
            font-weight: 600;
            text-align: center;
            font-size: 0.78rem;
            color: #1a1a1a;
        }
        .ba-table td {
            padding: 8px 6px;
            border: 1px solid #d6d4cc;
            text-align: center;
            height: 42px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .ba-table td.name-cell {
            text-align: left;
            padding-left: 10px;
        }
        .ba-table tr.activo-prestamo { cursor: pointer; }
        .ba-table tr.activo-prestamo:hover { background: #faf9f5; }
        .ba-table tr.activo-prestamo:hover .ba-return-hint { opacity: 1; }
        .ba-return-hint {
            color: #185fa5;
            font-size: 0.7rem;
            font-style: italic;
            opacity: 0;
            transition: opacity 0.15s;
        }
        .ba-sig-img {
            max-height: 28px;
            max-width: 100%;
            vertical-align: middle;
        }
        .ba-empty-row td {
            color: #9a9892;
            padding: 14px 6px;
        }

        /* Anchos de columna que imitan la plantilla impresa */
        .ba-col-fecha     { width: 11%; }
        .ba-col-nombre    { width: 22%; }
        .ba-col-area      { width: 16%; }
        .ba-col-hora-p    { width: 11%; }
        .ba-col-firma-p   { width: 12%; }
        .ba-col-hora-e    { width: 11%; }
        .ba-col-firma-e   { width: 17%; }

        /* ============================================================
           Modales / firma digital
           ============================================================ */
        .ba-modal .modal-content {
            border-radius: 10px;
        }
        .ba-field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
            background: #faf9f5;
            border: 1px solid #d6d4cc;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .ba-field-grid .ba-field-full { grid-column: 1 / -1; }
        .ba-field-grid label {
            font-size: 0.7rem;
            color: #5f5e5a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }
        .ba-field-grid .ba-value {
            font-size: 0.88rem;
            color: #1a1a1a;
        }
        .ba-sig-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .ba-sig-clear {
            background: transparent;
            border: none;
            color: #185fa5;
            font-size: 0.78rem;
            cursor: pointer;
            padding: 2px 6px;
        }
        .ba-sig-clear:hover { text-decoration: underline; }
        .ba-firma-pad {
            border: 1.5px dashed #b4b2a9 !important;
            border-radius: 6px;
            background: #fff !important;
            cursor: crosshair;
            margin-bottom: 0;
        }
        .ba-firma-pad.has-sig {
            border-style: solid !important;
            border-color: #185fa5 !important;
        }

        @media (max-width: 768px) {
            .ba-field-grid { grid-template-columns: 1fr; }
            .ba-title-box h1 { font-size: 0.92rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
        if ($es_mantenimiento) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_colaborativo.php';
        } elseif (in_array(strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''), ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php';
        } else {
            include __DIR__ . '/../../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo htmlspecialchars($alerta['tipo'] === 'error' ? 'danger' : $alerta['tipo']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($alerta['mensaje']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
                <?php endif; ?>

                <div class="bitacora-wrapper">

                    <!-- Encabezado: Logo + Título -->
                    <div class="bitacora-header">
                        <div class="ba-logo-box <?php echo $logo_url ? '' : 'is-placeholder'; ?>">
                            <?php if ($logo_url): ?>
                                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo">
                            <?php else: ?>
                                <i class="bi bi-image"></i>
                                <small>Logo</small>
                            <?php endif; ?>
                        </div>
                        <div class="ba-title-box">
                            <h1>Bitácora de Registro Aires Acondicionados</h1>
                        </div>
                    </div>

                    <!-- Nombre del encargado (estático según plantilla) -->
                    <div class="ba-encargado">
                        <strong>Nombre del encargado:</strong>
                        <span class="ba-nombre-enc">Christopher Hernández Hernández</span>
                    </div>

                    <!-- Acciones -->
                    <div class="ba-actions">
                        <button class="ba-btn-pedir"
                                id="btnPedirControl"
                                <?php echo $tiene_prestamo_activo ? 'disabled title="Ya tienes un control en préstamo activo"' : ''; ?>>
                            <i class="bi bi-plus-lg"></i>
                            Pedir Control
                        </button>

                        <?php if ($es_ti): ?>
                            <a class="ba-btn-descargar" href="<?php echo URL_BASE; ?>dashboard/sistemas/bitacora_aires/descargar_bitacora_aires.php">
                                <i class="bi bi-download"></i>
                                Descargar archivo
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Tabla principal -->
                    <div class="ba-table-wrapper">
                        <table class="ba-table">
                            <colgroup>
                                <col class="ba-col-fecha">
                                <col class="ba-col-nombre">
                                <col class="ba-col-area">
                                <col class="ba-col-hora-p">
                                <col class="ba-col-firma-p">
                                <col class="ba-col-hora-e">
                                <col class="ba-col-firma-e">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Nombre Completo</th>
                                    <th>Área - Lugar</th>
                                    <th>Hora préstamo</th>
                                    <th>Firma</th>
                                    <th>Hora entrega</th>
                                    <th>Firma</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registros)): ?>
                                    <tr class="ba-empty-row">
                                        <td colspan="7" style="padding: 30px 6px;">
                                            <?php if ($es_ti): ?>
                                                Aún no hay registros en la bitácora.
                                            <?php else: ?>
                                                No tienes préstamos registrados. Da clic en <strong>"Pedir Control"</strong> para registrar uno nuevo.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registros as $reg):
                                        $activo    = ($reg['estado'] === 'activo');
                                        $es_mio    = ((int)$reg['usuario_id'] === $usuario_id);
                                        $clickable = $activo && $es_mio;

                                        $fecha_fmt = date('d/m/Y', strtotime($reg['fecha_prestamo']));
                                        $hora_p    = $reg['hora_prestamo'] ? substr($reg['hora_prestamo'], 0, 5) : '';
                                        $hora_e    = $reg['hora_entrega'] ? substr($reg['hora_entrega'], 0, 5) : '';
                                    ?>
                                    <tr
                                        <?php if ($clickable): ?>
                                            class="activo-prestamo"
                                            data-id="<?php echo (int)$reg['id']; ?>"
                                            onclick="abrirModalDevolucion(<?php echo (int)$reg['id']; ?>)"
                                            title="Clic para registrar la devolución"
                                        <?php endif; ?>
                                    >
                                        <td><?php echo htmlspecialchars($fecha_fmt); ?></td>
                                        <td class="name-cell"><?php echo htmlspecialchars($reg['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['departamento_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($hora_p); ?></td>
                                        <td>
                                            <?php if (!empty($reg['firma_prestamo'])): ?>
                                                <img src="<?php echo htmlspecialchars($reg['firma_prestamo']); ?>" class="ba-sig-img" alt="Firma préstamo">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hora_e): ?>
                                                <?php echo htmlspecialchars($hora_e); ?>
                                            <?php elseif ($clickable): ?>
                                                <span class="ba-return-hint">Clic para devolver</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($reg['firma_entrega'])): ?>
                                                <img src="<?php echo htmlspecialchars($reg['firma_entrega']); ?>" class="ba-sig-img" alt="Firma entrega">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- ============================================================
         MODAL: Pedir Control
         ============================================================ -->
    <div class="modal fade ba-modal" id="modalPedirControl" tabindex="-1" aria-labelledby="modalPedirControlLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalPedirControlLabel">
                <i class="bi bi-clipboard-plus me-1"></i> Pedir Control
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-3">Confirma tus datos y firma para registrar el préstamo del control.</p>

            <div class="ba-field-grid">
                <div>
                    <label>Fecha</label>
                    <div class="ba-value" id="pedirFecha">—</div>
                </div>
                <div>
                    <label>Hora préstamo</label>
                    <div class="ba-value" id="pedirHora">—</div>
                </div>
                <div class="ba-field-full">
                    <label>Nombre Completo</label>
                    <div class="ba-value"><?php echo htmlspecialchars($nombre_completo); ?></div>
                </div>
                <div class="ba-field-full">
                    <label>Área - Lugar</label>
                    <div class="ba-value"><?php echo htmlspecialchars($departamento_nombre); ?></div>
                </div>
            </div>

            <div class="ba-sig-label">
                <span><i class="bi bi-pen me-1"></i> Firma</span>
                <button type="button" class="ba-sig-clear" id="btnLimpiarFirmaPedir">Limpiar</button>
            </div>
            <div id="firmaPedir" class="ba-firma-pad"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnEnviarPedir" disabled>
                <i class="bi bi-send me-1"></i> Enviar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================
         MODAL: Devolver Control
         ============================================================ -->
    <div class="modal fade ba-modal" id="modalDevolverControl" tabindex="-1" aria-labelledby="modalDevolverControlLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalDevolverControlLabel">
                <i class="bi bi-clipboard-check me-1"></i> Devolver Control
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-3">Firma para registrar la devolución del control.</p>

            <div class="ba-field-grid">
                <div>
                    <label>Fecha</label>
                    <div class="ba-value" id="devFecha">—</div>
                </div>
                <div>
                    <label>Hora entrega</label>
                    <div class="ba-value" id="devHora">—</div>
                </div>
                <div class="ba-field-full">
                    <label>Nombre Completo</label>
                    <div class="ba-value" id="devNombre">—</div>
                </div>
                <div class="ba-field-full">
                    <label>Área - Lugar</label>
                    <div class="ba-value" id="devArea">—</div>
                </div>
            </div>

            <div class="ba-sig-label">
                <span><i class="bi bi-pen me-1"></i> Firma de devolución</span>
                <button type="button" class="ba-sig-clear" id="btnLimpiarFirmaDev">Limpiar</button>
            </div>
            <div id="firmaDev" class="ba-firma-pad"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnEnviarDev" disabled>
                <i class="bi bi-check2-circle me-1"></i> Confirmar devolución
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form oculto para enviar acción de pedir préstamo -->
    <form id="formPedir" method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/bitacora_aires/procesar_bitacora_aires.php" style="display:none;">
        <input type="hidden" name="accion" value="pedir">
        <input type="hidden" name="firma_prestamo" id="inputFirmaPedir" value="">
    </form>

    <!-- Form oculto para enviar acción de devolución -->
    <form id="formDevolver" method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/bitacora_aires/procesar_bitacora_aires.php" style="display:none;">
        <input type="hidden" name="accion" value="devolver">
        <input type="hidden" name="registro_id" id="inputRegistroId" value="">
        <input type="hidden" name="firma_entrega" id="inputFirmaEntrega" value="">
    </form>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <script>
    // ============================================================
    // Datos del usuario (preparados en PHP)
    // ============================================================
    const BA_USUARIO = {
        nombre: <?php echo json_encode($nombre_completo); ?>,
        departamento: <?php echo json_encode($departamento_nombre); ?>
    };

    // Mapa de registros con datos completos (para modal de devolución)
    const BA_REGISTROS = {
        <?php foreach ($registros as $reg):
            if ($reg['estado'] !== 'activo' || (int)$reg['usuario_id'] !== $usuario_id) continue;
        ?>
        <?php echo (int)$reg['id']; ?>: {
            id: <?php echo (int)$reg['id']; ?>,
            nombre: <?php echo json_encode($reg['nombre_completo']); ?>,
            area: <?php echo json_encode($reg['departamento_nombre']); ?>,
            fecha: <?php echo json_encode(date('d/m/Y', strtotime($reg['fecha_prestamo']))); ?>
        },
        <?php endforeach; ?>
    };

    // ============================================================
    // Utilidades
    // ============================================================
    function pad(n) { return String(n).padStart(2, '0'); }
    function formatearFecha(d) {
        return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
    }
    function formatearHora(d) {
        return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    // ============================================================
    // jSignature wrappers
    // ============================================================
    let firmaPedir = null;
    let firmaDev   = null;
    let registroDevolucionActual = null;

    function inicializarFirmaPedir() {
        const $pad = $('#firmaPedir');
        $pad.empty();
        try {
            firmaPedir = $pad.jSignature({
                color: '#000000',
                lineWidth: 2,
                background: '#ffffff',
                width: '100%',
                height: 140,
                'decor-color': '#b4b2a9'
            });
        } catch (e) {
            console.error('Error inicializando firma préstamo:', e);
            return;
        }
        const checkBtn = () => {
            const data = firmaPedir.jSignature('getData', 'base30');
            const dibujado = Array.isArray(data) && data[1] && data[1].length > 6;
            $('#btnEnviarPedir').prop('disabled', !dibujado);
            if (dibujado) $pad.addClass('has-sig');
            else $pad.removeClass('has-sig');
        };
        $pad.on('change', checkBtn);
        $pad.on('mouseup touchend', () => setTimeout(checkBtn, 30));
    }

    function inicializarFirmaDev() {
        const $pad = $('#firmaDev');
        $pad.empty();
        try {
            firmaDev = $pad.jSignature({
                color: '#000000',
                lineWidth: 2,
                background: '#ffffff',
                width: '100%',
                height: 140,
                'decor-color': '#b4b2a9'
            });
        } catch (e) {
            console.error('Error inicializando firma devolución:', e);
            return;
        }
        const checkBtn = () => {
            const data = firmaDev.jSignature('getData', 'base30');
            const dibujado = Array.isArray(data) && data[1] && data[1].length > 6;
            $('#btnEnviarDev').prop('disabled', !dibujado);
            if (dibujado) $pad.addClass('has-sig');
            else $pad.removeClass('has-sig');
        };
        $pad.on('change', checkBtn);
        $pad.on('mouseup touchend', () => setTimeout(checkBtn, 30));
    }

    // ============================================================
    // Modal: Pedir Control
    // ============================================================
    document.getElementById('btnPedirControl')?.addEventListener('click', function() {
        if (this.disabled) return;
        const ahora = new Date();
        document.getElementById('pedirFecha').textContent = formatearFecha(ahora);
        document.getElementById('pedirHora').textContent  = formatearHora(ahora);
        document.getElementById('btnEnviarPedir').disabled = true;

        const modal = new bootstrap.Modal(document.getElementById('modalPedirControl'));
        modal.show();

        // Esperar a que el modal sea visible para inicializar canvas
        document.getElementById('modalPedirControl').addEventListener('shown.bs.modal', inicializarFirmaPedir, { once: true });
    });

    document.getElementById('btnLimpiarFirmaPedir')?.addEventListener('click', () => {
        if (firmaPedir) {
            firmaPedir.jSignature('reset');
            $('#firmaPedir').removeClass('has-sig');
            document.getElementById('btnEnviarPedir').disabled = true;
        }
    });

    document.getElementById('btnEnviarPedir')?.addEventListener('click', function() {
        if (!firmaPedir) return;
        const fd = firmaPedir.jSignature('getData', 'image');
        if (!fd || fd.length < 2) {
            alert('Por favor, dibuja tu firma antes de enviar.');
            return;
        }
        const dataUrl = 'data:' + fd[0] + ',' + fd[1];
        document.getElementById('inputFirmaPedir').value = dataUrl;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
        document.getElementById('formPedir').submit();
    });

    // ============================================================
    // Modal: Devolver Control
    // ============================================================
    window.abrirModalDevolucion = function(registroId) {
        const reg = BA_REGISTROS[registroId];
        if (!reg) return;

        registroDevolucionActual = registroId;
        const ahora = new Date();

        document.getElementById('devFecha').textContent  = reg.fecha;
        document.getElementById('devHora').textContent   = formatearHora(ahora);
        document.getElementById('devNombre').textContent = reg.nombre;
        document.getElementById('devArea').textContent   = reg.area;
        document.getElementById('inputRegistroId').value = registroId;
        document.getElementById('btnEnviarDev').disabled = true;

        const modal = new bootstrap.Modal(document.getElementById('modalDevolverControl'));
        modal.show();

        document.getElementById('modalDevolverControl').addEventListener('shown.bs.modal', () => {
            // Actualizar hora cada vez (toma la hora del momento que se abre)
            document.getElementById('devHora').textContent = formatearHora(new Date());
            inicializarFirmaDev();
        }, { once: true });
    };

    document.getElementById('btnLimpiarFirmaDev')?.addEventListener('click', () => {
        if (firmaDev) {
            firmaDev.jSignature('reset');
            $('#firmaDev').removeClass('has-sig');
            document.getElementById('btnEnviarDev').disabled = true;
        }
    });

    document.getElementById('btnEnviarDev')?.addEventListener('click', function() {
        if (!firmaDev || !registroDevolucionActual) return;
        const fd = firmaDev.jSignature('getData', 'image');
        if (!fd || fd.length < 2) {
            alert('Por favor, dibuja tu firma antes de confirmar.');
            return;
        }
        const dataUrl = 'data:' + fd[0] + ',' + fd[1];
        document.getElementById('inputFirmaEntrega').value = dataUrl;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
        document.getElementById('formDevolver').submit();
    });
    </script>

</body>
</html>