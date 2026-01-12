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
    <title><?= htmlspecialchars($documento['folio']) ?> - Sistema TI</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <style>
        /* ====== ESTILOS COMPACTOS ====== */
        .documento-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .documento-header h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        .documento-header p {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        .campo-bloqueado {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .apartado-section {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .apartado-section.editable {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }
        
        .apartado-section.bloqueado {
            background-color: #f8f9fa;
        }
        
        .comentario-item {
            border-left: 3px solid #dee2e6;
            padding: 0.6rem;
            margin-bottom: 0.6rem;
            background-color: white;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .comentario-item.tipo-normal {
            border-left-color: #6c757d;
        }
        
        .comentario-item.tipo-aclaracion {
            border-left-color: #0dcaf0;
        }
        
        .comentario-item.tipo-correccion {
            border-left-color: #fd7e14;
        }
        
        .comentario-item.tipo-solicitud {
            border-left-color: #0d6efd;
        }
        
        .comentarios-panel {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .badge-prioridad {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
        
        /* Cards compactas */
        .card {
            margin-bottom: 0.75rem;
        }
        .card-body {
            padding: 0.75rem;
        }
        .card-subtitle {
            font-size: 0.7rem;
            margin-bottom: 0.5rem !important;
        }
        .card-body p {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        
        /* Tabs compactos */
        .nav-tabs .nav-link {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
        .nav-tabs .badge {
            font-size: 0.65rem;
        }
        
        /* Botones compactos */
        .btn {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }
        .btn-sm {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }
        
        /* Permisos compactos */
        .card-body p i {
            font-size: 0.9rem;
        }
        
        /* Container padding */
        .container-fluid.p-4 {
            padding: 1rem !important;
        }
        
        /* ⭐ Card Marcar como visto */
        .card-marcar-visto {
            border: 2px solid #17a2b8;
            border-radius: 8px;
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
        }
        .card-marcar-visto .btn-group .btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        .card-marcar-visto .btn-check:checked + .btn-outline-success {
            background-color: #198754;
            border-color: #198754;
            color: white;
        }
        .card-marcar-visto .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .documento-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>
    
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
        
        <div class="container-fluid p-4">
            <!-- Header del documento -->
            <div class="documento-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3>
                            <i class="bi bi-file-earmark-text"></i>
                            <?= htmlspecialchars($documento['folio']) ?>
                        </h3>
                        <p>Solicitud de Servicio a Clientes</p>
                        <span class="badge bg-<?= $documento['prioridad'] == 'alta' ? 'danger' : ($documento['prioridad'] == 'media' ? 'warning' : 'success') ?> badge-prioridad">
                            Prioridad: <?= ucfirst($documento['prioridad']) ?>
                        </span>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="documentos_colaborativos.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                        <a href="<?= URL_BASE ?>documentos/generar_pdf_ssc.php?id=<?= $documento['id'] ?>" class="btn btn-light btn-sm">
                            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- ⭐ NUEVA CARD: Marcar como visto -->
            <div class="card mb-3 card-marcar-visto">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-eye-fill text-info me-2 fs-5"></i>
                            <span class="fw-bold">Marcar como visto</span>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <?php if ($es_laboratorio): ?>
                            <!-- Laboratorio: Puede editar -->
                            <div class="btn-group" role="group" id="grupoMarcarVisto">
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
                            <?php else: ?>
                            <!-- Normatividad/Ventas: Solo visualización -->
                            <?php if ($documento['marcado_visto']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Sí
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-x-circle"></i> No
                                </span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Info de quién marcó -->
                            <?php if ($documento['marcado_visto'] && $usuario_marcado_visto_nombre): ?>
                            <span class="ms-3 text-muted small" id="infoMarcadoVisto">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($usuario_marcado_visto_nombre) ?>
                                <i class="bi bi-clock ms-1"></i> <?= date('d/m/Y H:i', strtotime($documento['fecha_marcado_visto'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info cards compactas -->
            <div class="row mb-3">
                <!-- Información del documento -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle text-muted">Información del documento</h6>
                            <p><strong>Folio:</strong> <?= htmlspecialchars($documento['folio']) ?></p>
                            <p><strong>Creado por:</strong> <?= htmlspecialchars($documento['departamento_creador']) ?></p>
                            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($documento['fecha_creacion'])) ?></p>
                            <p class="mb-0"><strong>Actualizado:</strong> <?= date('d/m/Y H:i', strtotime($documento['fecha_ultima_edicion'])) ?></p>
                        </div>
                    </div>
                </div>
                <!-- Permisos de edición -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle text-muted">Permisos de edición</h6>
                            <p>
                                <i class="bi bi-<?= $permisos['apartado1'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Apartado 1: <?= $permisos['apartado1'] ? 'Sí' : 'No' ?>
                            </p>
                            <p>
                                <i class="bi bi-<?= $permisos['apartado2'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Apartado 2: <?= $permisos['apartado2'] ? 'Sí' : 'No' ?>
                            </p>
                            <p class="mb-0">
                                <i class="bi bi-<?= $permisos['puede_comentar'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Comentarios: <?= $permisos['puede_comentar'] ? 'Sí' : 'No' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs: Documento / Comentarios -->
            <ul class="nav nav-tabs mb-3" id="documentoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="documento-tab" data-bs-toggle="tab" data-bs-target="#documento" type="button">
                        <i class="bi bi-file-text"></i> Documento
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="comentarios-tab" data-bs-toggle="tab" data-bs-target="#comentarios" type="button">
                        <i class="bi bi-chat-dots"></i> Comentarios
                        <span class="badge bg-primary"><?= count($comentarios) ?></span>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="documentoTabsContent">
                <!-- TAB: Documento -->
                <div class="tab-pane fade show active" id="documento" role="tabpanel">
                    <?php include __DIR__ . '/../../includes/colaborativo/documento_formulario.php'; ?>
                </div>
                
                <!-- TAB: Comentarios -->
                <div class="tab-pane fade" id="comentarios" role="tabpanel">
                    <?php include __DIR__ . '/../../includes/colaborativo/documento_comentarios_ui.php'; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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