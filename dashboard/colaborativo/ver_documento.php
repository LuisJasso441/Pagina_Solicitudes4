<?php
/**
 * Vista detallada de documento colaborativo
 * Permite editar según permisos y departamento
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_colaborativos.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_comentarios.php';

// Verificar autenticación
if (!sesion_activa()) {
    header('Location: /Pagina_Solicitudes4/login.php');
    exit;
}

// Obtener ID del documento
$documento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$documento_id) {
    header('Location: documentos_colaborativos.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];

// Obtener documento
$documento = obtener_documento($documento_id);

if (!$documento) {
    header('Location: documentos_colaborativos.php?error=not_found');
    exit;
}

// Verificar permisos
$permisos = verificar_permisos_edicion($usuario_id, $departamento, $documento);

// ⭐ Verificar si es Laboratorio (para funcionalidad "Marcar como visto")
$es_laboratorio = strtolower($departamento) === 'laboratorio';

// ⭐ Obtener nombre del usuario que marcó como visto (si aplica)
$usuario_marcado_visto_nombre = null;
if ($documento['marcado_visto'] && $documento['usuario_marcado_visto']) {
    try {
        $pdo_temp = conectarDB();
        $stmt_temp = $pdo_temp->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
        $stmt_temp->execute([$documento['usuario_marcado_visto']]);
        $usuario_marcado_visto_nombre = $stmt_temp->fetchColumn();
    } catch (Exception $e) {
        error_log("Error al obtener nombre de usuario: " . $e->getMessage());
    }
}

// Obtener comentarios
$comentarios = obtener_comentarios_documento($documento_id);

// Obtener servicios para mostrar
$servicios_nombres = [
    'tratamiento_agua' => 'Tratamiento de agua',
    'revision_productos' => 'Productos químicos y/o residuos',
    'calibracion_equipos' => 'Calibración y/o verificación de equipos',
    'otro' => 'Otro'
];

$servicio_texto = $servicios_nombres[$documento['servicio_solicitado']] ?? 'N/A';
if ($documento['servicio_solicitado'] == 'otro' && $documento['servicio_otro_especificar']) {
    $servicio_texto = $documento['servicio_otro_especificar'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($documento['folio']) ?> - SSC</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        /* ==========================================================
           VISTA DE DOCUMENTO COLABORATIVO (prefijo .doc-*)
           Todo vive bajo .doc-view para no afectar sidebar u otras páginas
        =========================================================== */
        .doc-view {
            --doc-1: #667eea;
            --doc-2: #764ba2;
            --doc-soft: rgba(102, 126, 234, 0.10);
            --doc-text: #1e2235;
            --doc-muted: #6b7084;
            --doc-border: #e6e8f0;
            --doc-card: #ffffff;
            --doc-shadow: 0 10px 28px -16px rgba(60, 50, 120, 0.35);
            --doc-mono: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--doc-text);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* ---------- Encabezado ---------- */
        .doc-head {
            background: var(--doc-card);
            border: 1px solid var(--doc-border);
            border-radius: 16px;
            box-shadow: var(--doc-shadow);
            overflow: hidden;
        }
        .doc-head__top {
            position: relative;
            overflow: hidden;
            padding: 1.3rem 1.5rem 1.1rem;
            background: linear-gradient(120deg, var(--doc-1) 0%, var(--doc-2) 100%);
            color: #fff;
        }
        .doc-head__top::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1px);
            background-size: 16px 16px;
            mask-image: linear-gradient(110deg, #000 0%, transparent 70%);
            -webkit-mask-image: linear-gradient(110deg, #000 0%, transparent 70%);
            pointer-events: none;
        }
        .doc-head__row {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .doc-head__id { display: flex; align-items: center; gap: 0.95rem; min-width: 0; }
        .doc-head__icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
        }
        .doc-head__folio {
            margin: 0;
            font-family: var(--doc-mono);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            word-break: break-all;
        }
        .doc-head__sub { margin: 0.15rem 0 0; font-size: 0.85rem; opacity: 0.92; }
        .doc-head__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .doc-btn-glass {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.9rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: background .2s ease, transform .2s ease;
        }
        .doc-btn-glass:hover { background: rgba(255, 255, 255, 0.28); color: #fff; transform: translateY(-1px); }
        .doc-btn-glass--solid { background: #fff; color: var(--doc-2); border-color: #fff; }
        .doc-btn-glass--solid:hover { background: #f3f0ff; color: var(--doc-2); }

        /* Chips de estado */
        .doc-chips {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .doc-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.95);
            color: var(--doc-text);
        }
        .doc-chip__dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .doc-chip--alta    { color: #b91c1c; }
        .doc-chip--media   { color: #b45309; }
        .doc-chip--baja    { color: #15803d; }
        .doc-chip--ok      { color: #15803d; }
        .doc-chip--proceso { color: var(--doc-2); }

        /* Barra de metadatos */
        .doc-head__meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.9rem 1.5rem;
            padding: 0.9rem 1.5rem;
        }
        .doc-meta { display: flex; flex-wrap: wrap; gap: 0.7rem 1.75rem; margin: 0; }
        .doc-meta__item dt {
            margin: 0;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--doc-muted);
        }
        .doc-meta__item dd { margin: 0.1rem 0 0; font-size: 0.86rem; font-weight: 500; color: var(--doc-text); }
        /* Tabla de Sistema de Gestión (FR-CC-12) */
        .doc-sg { width: auto; min-width: 280px; margin: 0; font-size: 0.72rem; }
        .doc-sg td { padding: 0.25rem 0.6rem; border-color: var(--doc-border); }
        .doc-sg td:first-child { font-weight: 600; color: var(--doc-muted); background: #f6f7fb; }
        .doc-sg td:last-child { text-align: center; font-family: var(--doc-mono); color: var(--doc-text); }

        /* Marcar como visto */
        .doc-visto {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 0.45rem 0.6rem 0.45rem 0.85rem;
            border-radius: 12px;
            background: var(--doc-soft);
            border: 1px solid rgba(102, 126, 234, 0.25);
        }
        .doc-visto__label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--doc-2);
            white-space: nowrap;
        }
        .doc-visto .btn-group .btn { padding: 0.2rem 0.7rem; font-size: 0.76rem; background: #fff; }
        .doc-visto .btn-check:checked + .btn-outline-success { background-color: #198754; border-color: #198754; color: #fff; }
        .doc-visto .btn-check:checked + .btn-outline-secondary { background-color: #6c757d; border-color: #6c757d; color: #fff; }

        /* ---------- Pestañas + permisos ---------- */
        .doc-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .doc-tabs {
            gap: 0.35rem;
            padding: 0.3rem;
            background: var(--doc-card);
            border: 1px solid var(--doc-border);
            border-radius: 12px;
        }
        .doc-tabs .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 1rem;
            border: 0;
            border-radius: 9px;
            background: transparent;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--doc-muted);
            transition: background .2s ease, color .2s ease;
        }
        .doc-tabs .nav-link:hover { color: var(--doc-2); background: var(--doc-soft); }
        .doc-tabs .nav-link.active {
            color: #fff;
            background: linear-gradient(120deg, var(--doc-1), var(--doc-2));
            box-shadow: 0 6px 14px -8px rgba(118, 75, 162, 0.7);
        }
        .doc-tabs__count {
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            background: var(--doc-soft);
            color: var(--doc-2);
        }
        .doc-tabs .nav-link.active .doc-tabs__count { background: rgba(255, 255, 255, 0.25); color: #fff; }

        .doc-perms { display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem; }
        .doc-perms__label {
            margin-right: 0.2rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--doc-muted);
        }
        .doc-perm {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .doc-perm.is-on  { background: rgba(22, 163, 74, 0.10); color: #15803d; border-color: rgba(22, 163, 74, 0.25); }
        .doc-perm.is-off { background: #f1f2f6; color: #8a8fa3; border-color: var(--doc-border); }

        /* ---------- Estilos que usan los includes (formulario y comentarios) ---------- */
        .doc-view .card { margin-bottom: 0.75rem; border-color: var(--doc-border); border-radius: 12px; }
        .doc-view .card-body { padding: 0.85rem; }
        .doc-view .card-body p { font-size: 0.82rem; margin-bottom: 0.25rem; }
        .doc-view .btn { font-size: 0.8rem; padding: 0.35rem 0.75rem; }
        .doc-view .btn-sm { font-size: 0.72rem; padding: 0.2rem 0.5rem; }

        .doc-view .apartado-section {
            border: 1px solid var(--doc-border);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: 1rem;
            background: var(--doc-card);
            box-shadow: 0 4px 14px -10px rgba(60, 50, 120, 0.25);
        }
        .doc-view .apartado-section.editable {
            border-color: rgba(102, 126, 234, 0.45);
            border-left: 4px solid var(--doc-1);
            background: linear-gradient(180deg, rgba(102, 126, 234, 0.06) 0%, #fff 140px);
        }
        .doc-view .apartado-section.bloqueado {
            border-left: 4px solid #cbd5e1;
            background: #fafbfc;
        }
        .doc-view .campo-bloqueado {
            background-color: #f4f5f8;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .doc-view .comentarios-panel { max-height: 420px; overflow-y: auto; }
        .doc-view .comentario-item {
            border-left: 3px solid var(--doc-border);
            padding: 0.65rem 0.75rem;
            margin-bottom: 0.6rem;
            background: #fff;
            border-radius: 8px;
            font-size: 0.85rem;
            box-shadow: 0 1px 0 var(--doc-border);
        }
        .doc-view .comentario-item.tipo-normal     { border-left-color: #6c757d; }
        .doc-view .comentario-item.tipo-aclaracion { border-left-color: #0dcaf0; }
        .doc-view .comentario-item.tipo-correccion { border-left-color: #fd7e14; }
        .doc-view .comentario-item.tipo-solicitud  { border-left-color: var(--doc-1); }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .doc-head__top { padding: 1.1rem 1.1rem 1rem; }
            .doc-head__meta { padding: 0.85rem 1.1rem; }
            .doc-head__folio { font-size: 1.1rem; }
            .doc-head__icon { width: 44px; height: 44px; font-size: 1.3rem; }
            .doc-head__actions { width: 100%; }
            .doc-head__actions a { flex: 1; justify-content: center; }
            .doc-visto { width: 100%; }
            .doc-sg { width: 100%; min-width: 0; }
            .doc-toolbar { flex-direction: column; align-items: stretch; }
            .doc-tabs .nav-item { flex: 1; }
            .doc-tabs .nav-link { width: 100%; justify-content: center; }
        }

        /* ---------- Impresión ---------- */
        @media print {
            .no-print { display: none !important; }
            .doc-head__top {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php $doc_completado = (($documento['estado'] ?? '') === 'completado'); ?>

    <div class="dashboard-container">

        <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                <div class="doc-view">

                    <!-- Encabezado del documento -->
                    <section class="doc-head">
                        <div class="doc-head__top">
                            <div class="doc-head__row">
                                <div class="doc-head__id">
                                    <div class="doc-head__icon"><i class="bi bi-file-earmark-text"></i></div>
                                    <div>
                                        <h1 class="doc-head__folio"><?= htmlspecialchars($documento['folio']) ?></h1>
                                        <p class="doc-head__sub">
                                            Solicitud de Servicio a Clientes &middot; <?= htmlspecialchars($servicio_texto) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="doc-head__actions no-print">
                                    <a href="documentos_colaborativos.php" class="doc-btn-glass">
                                        <i class="bi bi-arrow-left"></i> Volver
                                    </a>
                                    <a href="<?= URL_BASE ?>documentos/generar_pdf_ssc.php?id=<?= $documento['id'] ?>" class="doc-btn-glass doc-btn-glass--solid">
                                        <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                                    </a>
                                </div>
                            </div>

                            <div class="doc-chips">
                                <span class="doc-chip doc-chip--<?= htmlspecialchars($documento['prioridad']) ?>">
                                    <span class="doc-chip__dot"></span>
                                    Prioridad <?= ucfirst(htmlspecialchars($documento['prioridad'])) ?>
                                </span>
                                <span class="doc-chip <?= $doc_completado ? 'doc-chip--ok' : 'doc-chip--proceso' ?>">
                                    <i class="bi bi-<?= $doc_completado ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
                                    <?= $doc_completado ? 'Completado' : 'En proceso' ?>
                                </span>
                            </div>
                        </div>

                        <div class="doc-head__meta">
                            <dl class="doc-meta">
                                <div class="doc-meta__item">
                                    <dt>Creado por</dt>
                                    <dd><?= htmlspecialchars($documento['departamento_creador']) ?></dd>
                                </div>
                                <div class="doc-meta__item">
                                    <dt>Fecha de creaci&oacute;n</dt>
                                    <dd><?= date('d/m/Y H:i', strtotime($documento['fecha_creacion'])) ?></dd>
                                </div>
                                <div class="doc-meta__item">
                                    <dt>&Uacute;ltima actualizaci&oacute;n</dt>
                                    <dd><?= !empty($documento['fecha_ultima_edicion']) ? date('d/m/Y H:i', strtotime($documento['fecha_ultima_edicion'])) : '&mdash;' ?></dd>
                                </div>
                                </dl>

                            <!-- Sistema de Gestión (parte del proceso) -->
                            <table class="table table-bordered table-sm doc-sg">
                                <tbody>
                                    <tr><td>Código de Sistema De Gestión</td><td>FR-CC-12</td></tr>
                                    <tr><td>No. de Revisión</td><td>02</td></tr>
                                    <tr><td>Fecha de Revisión</td><td>26/01/2026</td></tr>
                                </tbody>
                            </table>

                            <!-- Marcar como visto -->
                            <div class="doc-visto">
                                <span class="doc-visto__label"><i class="bi bi-eye-fill"></i> Marcado como visto</span>
                                <div class="d-flex align-items-center flex-wrap">
                                    <?php if ($es_laboratorio): ?>
                                    <div class="btn-group no-print" role="group" id="grupoMarcarVisto">
                                        <input type="radio" class="btn-check" name="marcado_visto" id="visto_si" value="1"
                                               <?= $documento['marcado_visto'] ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success btn-sm" for="visto_si">
                                            <i class="bi bi-check-lg"></i> Sí
                                        </label>

                                        <input type="radio" class="btn-check" name="marcado_visto" id="visto_no" value="0"
                                               <?= !$documento['marcado_visto'] ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-secondary btn-sm" for="visto_no">
                                            <i class="bi bi-x-lg"></i> No
                                        </label>
                                    </div>
                                    <?php elseif ($documento['marcado_visto']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> No</span>
                                    <?php endif; ?>

                                    <?php if ($documento['marcado_visto'] && $usuario_marcado_visto_nombre): ?>
                                    <span class="ms-3 text-muted small" id="infoMarcadoVisto">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($usuario_marcado_visto_nombre) ?>
                                        <i class="bi bi-clock ms-1"></i> <?= date('d/m/Y H:i', strtotime($documento['fecha_marcado_visto'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Pestañas + permisos -->
                    <div class="doc-toolbar no-print">
                        <ul class="nav doc-tabs" id="documentoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="documento-tab" data-bs-toggle="tab" data-bs-target="#documento" type="button" role="tab">
                                    <i class="bi bi-file-text"></i> Documento
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="comentarios-tab" data-bs-toggle="tab" data-bs-target="#comentarios" type="button" role="tab">
                                    <i class="bi bi-chat-dots"></i> Comentarios
                                    <span class="doc-tabs__count"><?= count($comentarios) ?></span>
                                </button>
                            </li>
                        </ul>

                        <div class="doc-perms">
                            <span class="doc-perms__label">Tus permisos</span>
                            <?php
                            $doc_permisos_ui = [
                                'Apartado 1'  => $permisos['apartado1'],
                                'Apartado 2'  => $permisos['apartado2'],
                                'Comentarios' => $permisos['puede_comentar'],
                            ];
                            foreach ($doc_permisos_ui as $etiqueta => $permitido): ?>
                            <span class="doc-perm <?= $permitido ? 'is-on' : 'is-off' ?>" title="<?= $permitido ? 'Permitido' : 'Solo lectura' ?>">
                                <i class="bi bi-<?= $permitido ? 'check-lg' : 'lock-fill' ?>"></i> <?= $etiqueta ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Contenido de pestañas -->
                    <div class="tab-content" id="documentoTabsContent">
                        <div class="tab-pane fade show active" id="documento" role="tabpanel">
                            <?php include __DIR__ . '/../../includes/colaborativo/documento_formulario.php'; ?>
                        </div>
                        <div class="tab-pane fade" id="comentarios" role="tabpanel">
                            <?php include __DIR__ . '/../../includes/colaborativo/documento_comentarios_ui.php'; ?>
                        </div>
                    </div>

                </div><!-- /.doc-view -->
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/documento_editar.js"></script>
    
    <?php if ($es_laboratorio): ?>
    <!-- ⭐ Script para Marcar como visto (solo Laboratorio) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const radiosVisto = document.querySelectorAll('input[name="marcado_visto"]');
        const documentoId = <?= $documento['id'] ?>;
        
        radiosVisto.forEach(radio => {
            radio.addEventListener('change', function() {
                const marcadoVisto = this.value;
                
                // Mostrar indicador de carga
                const grupoBtn = document.getElementById('grupoMarcarVisto');
                const originalHTML = grupoBtn.innerHTML;
                grupoBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                
                // Enviar petición AJAX
                fetch('<?= URL_BASE ?>documentos/procesar_marcar_visto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `documento_id=${documentoId}&marcado_visto=${marcadoVisto}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mostrar mensaje de éxito
                        mostrarAlertaVisto('success', data.message);
                        
                        // Actualizar info si se marcó como visto
                        if (data.marcado_visto == 1) {
                            let infoSpan = document.getElementById('infoMarcadoVisto');
                            if (!infoSpan) {
                                infoSpan = document.createElement('span');
                                infoSpan.id = 'infoMarcadoVisto';
                                infoSpan.className = 'ms-3 text-muted small';
                                grupoBtn.parentElement.appendChild(infoSpan);
                            }
                            infoSpan.innerHTML = `<i class="bi bi-person"></i> ${data.usuario} <i class="bi bi-clock ms-1"></i> ${data.fecha}`;
                        } else {
                            // Si se desmarcó, ocultar info
                            const infoSpan = document.getElementById('infoMarcadoVisto');
                            if (infoSpan) {
                                infoSpan.remove();
                            }
                        }
                        
                        // Restaurar botones
                        grupoBtn.innerHTML = `
                            <input type="radio" class="btn-check" name="marcado_visto" id="visto_si" value="1" ${data.marcado_visto == 1 ? 'checked' : ''}>
                            <label class="btn btn-outline-success btn-sm" for="visto_si">
                                <i class="bi bi-check-lg"></i> Sí
                            </label>
                            
                            <input type="radio" class="btn-check" name="marcado_visto" id="visto_no" value="0" ${data.marcado_visto == 0 ? 'checked' : ''}>
                            <label class="btn btn-outline-secondary btn-sm" for="visto_no">
                                <i class="bi bi-x-lg"></i> No
                            </label>
                        `;
                        
                        // Re-agregar event listeners
                        document.querySelectorAll('input[name="marcado_visto"]').forEach(r => {
                            r.addEventListener('change', arguments.callee);
                        });
                        
                    } else {
                        mostrarAlertaVisto('danger', data.message);
                        grupoBtn.innerHTML = originalHTML;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarAlertaVisto('danger', 'Error al procesar la solicitud');
                    grupoBtn.innerHTML = originalHTML;
                });
            });
        });
        
        // Función para mostrar alertas
        function mostrarAlertaVisto(tipo, mensaje) {
            const alertaHTML = `
                <div class="alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     style="z-index: 9999; min-width: 300px;" role="alert">
                    <i class="bi bi-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${mensaje}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', alertaHTML);
            
            // Auto-cerrar después de 4 segundos
            setTimeout(() => {
                const alerta = document.querySelector('.alert');
                if (alerta) {
                    alerta.remove();
                }
            }, 4000);
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>