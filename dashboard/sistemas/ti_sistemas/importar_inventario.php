<?php
/**
 * Importar Inventario desde Excel
 * dashboard/sistemas/ti_sistemas/importar_inventario.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta secci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Obtener resultados de importación si existen
$resultados = $_SESSION['importacion_resultados'] ?? null;
unset($_SESSION['importacion_resultados']);

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Inventario - TI/Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <style>
        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: var(--card-bg, #fff);
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: #0d6efd;
            background: rgba(13, 110, 253, 0.03);
        }
        .upload-zone .upload-icon {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
        .upload-zone.has-file {
            border-color: #198754;
            background: rgba(25, 135, 84, 0.03);
        }
        .upload-zone.has-file .upload-icon {
            color: #198754;
        }
        .result-card {
            border-left: 4px solid;
            border-radius: 8px;
        }
        .result-card.success { border-left-color: #198754; }
        .result-card.warning { border-left-color: #ffc107; }
        .result-card.danger  { border-left-color: #dc3545; }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        .mapping-table td { font-size: 0.85rem; }
        .mapping-table .bi { margin-right: 0.3rem; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-upload me-2"></i>Importar Inventario</h4>
                        <small class="text-muted">Cargar datos desde archivo Excel</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Volver al Inventario
                    </a>
                </div>

                <?php if (function_exists('mostrar_alerta')) mostrar_alerta(); ?>

                <!-- Resultados de importación (si los hay) -->
                <?php if ($resultados): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Resultados de la Importaci&oacute;n</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Inventario -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><i class="bi bi-pc-display me-1"></i>Inventario de Equipos</h6>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="result-card success p-3 flex-fill">
                                        <div class="stat-number text-success"><?php echo $resultados['inventario']['insertados']; ?></div>
                                        <small class="text-muted">Nuevos</small>
                                    </div>
                                    <div class="result-card warning p-3 flex-fill">
                                        <div class="stat-number text-warning"><?php echo $resultados['inventario']['actualizados']; ?></div>
                                        <small class="text-muted">Actualizados</small>
                                    </div>
                                    <div class="result-card danger p-3 flex-fill">
                                        <div class="stat-number text-danger"><?php echo count($resultados['inventario']['errores']); ?></div>
                                        <small class="text-muted">Errores</small>
                                    </div>
                                </div>
                                <?php if ($resultados['inventario']['omitidos'] > 0): ?>
                                    <small class="text-muted"><i class="bi bi-skip-forward"></i> <?php echo $resultados['inventario']['omitidos']; ?> filas omitidas (sin hostname)</small>
                                <?php endif; ?>
                                <?php if (!empty($resultados['inventario']['errores'])): ?>
                                    <div class="alert alert-danger mt-2 py-2" style="font-size:0.82rem;">
                                        <strong>Errores:</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach (array_slice($resultados['inventario']['errores'], 0, 10) as $err): ?>
                                                <li><?php echo $err; ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($resultados['inventario']['errores']) > 10): ?>
                                                <li>... y <?php echo count($resultados['inventario']['errores']) - 10; ?> m&aacute;s</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Acceso -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3"><i class="bi bi-key me-1"></i>Acceso de Equipos</h6>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="result-card success p-3 flex-fill">
                                        <div class="stat-number text-success"><?php echo $resultados['acceso']['insertados']; ?></div>
                                        <small class="text-muted">Nuevos</small>
                                    </div>
                                    <div class="result-card warning p-3 flex-fill">
                                        <div class="stat-number text-warning"><?php echo $resultados['acceso']['actualizados']; ?></div>
                                        <small class="text-muted">Actualizados</small>
                                    </div>
                                    <div class="result-card danger p-3 flex-fill">
                                        <div class="stat-number text-danger"><?php echo count($resultados['acceso']['errores']); ?></div>
                                        <small class="text-muted">Errores</small>
                                    </div>
                                </div>
                                <?php if ($resultados['acceso']['omitidos'] > 0): ?>
                                    <small class="text-muted"><i class="bi bi-skip-forward"></i> <?php echo $resultados['acceso']['omitidos']; ?> filas omitidas (sin hostname)</small>
                                <?php endif; ?>
                                <?php if (!empty($resultados['acceso']['errores'])): ?>
                                    <div class="alert alert-danger mt-2 py-2" style="font-size:0.82rem;">
                                        <strong>Errores:</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach (array_slice($resultados['acceso']['errores'], 0, 10) as $err): ?>
                                                <li><?php echo $err; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Formulario de carga -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_importacion.php" 
                                      method="POST" enctype="multipart/form-data" id="formImportar">
                                    
                                    <div class="upload-zone mb-4" id="uploadZone" onclick="document.getElementById('inputArchivo').click()">
                                        <input type="file" name="archivo_excel" id="inputArchivo" 
                                               accept=".xlsx,.xls" class="d-none" required>
                                        <div class="upload-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                                        <h5 id="uploadLabel">Arrastra tu archivo Excel aqu&iacute;</h5>
                                        <p class="text-muted mb-0" id="uploadSubLabel">o haz clic para seleccionarlo</p>
                                        <small class="text-muted">Formatos aceptados: .xlsx, .xls</small>
                                    </div>
                                    
                                    <h6 class="fw-bold mb-3">&iquest;Qu&eacute; hojas importar?</h6>
                                    <div class="mb-4">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="hojas[]" 
                                                   value="inventario" id="chkInventario" checked>
                                            <label class="form-check-label" for="chkInventario">
                                                <i class="bi bi-pc-display text-primary me-1"></i>
                                                <strong>Inventario de Equipos</strong>
                                                <small class="text-muted d-block">Hoja: INVENTARIO_EQUIPOS_ELECTRONICOS &rarr; hostname, tipo, marca, modelo, serie, etc.</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hojas[]" 
                                                   value="acceso" id="chkAcceso" checked>
                                            <label class="form-check-label" for="chkAcceso">
                                                <i class="bi bi-key text-warning me-1"></i>
                                                <strong>Acceso de Equipos</strong>
                                                <small class="text-muted d-block">Hoja: ACCESO_EQUIPOS &rarr; hostname, IP, contrase&ntilde;a, usuario, whoami</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info py-2 mb-4" style="font-size:0.85rem;">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <strong>Comportamiento:</strong> Si un hostname ya existe en la base de datos, 
                                        el registro se <strong>actualizar&aacute;</strong> con los datos del Excel. 
                                        Los registros nuevos se insertar&aacute;n autom&aacute;ticamente.
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg w-100" id="btnImportar" disabled>
                                        <i class="bi bi-upload me-2"></i>Importar Datos
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Instrucciones / Mapeo -->
                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Formato Esperado</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mapping-table mb-0">
                                    <thead class="table-light">
                                        <tr><th colspan="2">Hoja: INVENTARIO_EQUIPOS_ELECTRONICOS</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td><i class="bi bi-check-circle text-success"></i>HOSTNAME</td><td>Identificador &uacute;nico (requerido)</td></tr>
                                        <tr><td><i class="bi bi-check-circle text-success"></i>TIPO</td><td>ALL IN ONE, PC, LAPTOP, etc.</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>MARCA</td><td>Fabricante</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>MODELO</td><td>Modelo del equipo</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>NUMERO DE SERIE</td><td>N&uacute;mero de serie</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>DEPARTAMENTO</td><td>Se vincula por nombre</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>UBICACION</td><td>Ubicaci&oacute;n f&iacute;sica</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>ESTADO</td><td>ACTIVO / INACTIVO</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>PERSONAL ASIGNADO</td><td>Hostname del equipo padre</td></tr>
                                    </tbody>
                                    <thead class="table-light">
                                        <tr><th colspan="2">Hoja: ACCESO_EQUIPOS</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td><i class="bi bi-check-circle text-success"></i>HOSTNAME</td><td>Identificador &uacute;nico (requerido)</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>DEPARTAMENTO</td><td>Se vincula por nombre</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>DIRECCI&Oacute;N IP</td><td>IP del equipo</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>CONTRASE&Ntilde;A</td><td>Contrase&ntilde;a del equipo</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>USUARIO ASIGNADO</td><td>Nombre de la persona</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>ESTADO</td><td>ACTIVO / INACTIVO</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>whoami</td><td>Usuario Windows</td></tr>
                                        <tr><td><i class="bi bi-dash-circle text-muted"></i>Comentarios</td><td>Notas adicionales</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-lightbulb me-1"></i>Tipos Reconocidos</h6>
                            </div>
                            <div class="card-body" style="font-size:0.85rem;">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php 
                                    $tipos_badge = [
                                        'ALL IN ONE' => 'primary', 'PC' => 'primary', 'LAPTOP' => 'primary',
                                        'MONITOR' => 'info', 'MOUSE' => 'secondary', 'TECLADO' => 'secondary',
                                        'IMPRESORA' => 'purple', 'ACCESS POINT' => 'warning', 'SWITCH' => 'warning',
                                        'C&Aacute;MARA' => 'success', 'CELULAR' => 'orange'
                                    ];
                                    foreach ($tipos_badge as $t => $color): ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo $t; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
    const uploadZone = document.getElementById('uploadZone');
    const inputArchivo = document.getElementById('inputArchivo');
    const btnImportar = document.getElementById('btnImportar');
    const uploadLabel = document.getElementById('uploadLabel');
    const uploadSubLabel = document.getElementById('uploadSubLabel');

    // Cambio de archivo
    inputArchivo.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            uploadZone.classList.add('has-file');
            uploadLabel.textContent = file.name;
            uploadSubLabel.textContent = (file.size / 1024).toFixed(1) + ' KB';
            btnImportar.disabled = false;
        } else {
            resetUpload();
        }
    });

    // Drag & Drop
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    uploadZone.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            inputArchivo.files = e.dataTransfer.files;
            inputArchivo.dispatchEvent(new Event('change'));
        }
    });

    function resetUpload() {
        uploadZone.classList.remove('has-file');
        uploadLabel.textContent = 'Arrastra tu archivo Excel aquí';
        uploadSubLabel.textContent = 'o haz clic para seleccionarlo';
        btnImportar.disabled = true;
    }

    // Loading state al enviar
    document.getElementById('formImportar').addEventListener('submit', function() {
        btnImportar.disabled = true;
        btnImportar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importando...';
    });
    </script>
</body>
</html>