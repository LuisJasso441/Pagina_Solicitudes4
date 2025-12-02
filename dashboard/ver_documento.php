<?php
/**
 * Vista detallada de documento colaborativo
 * Permite editar según permisos y departamento
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/documentos_colaborativos.php';
require_once __DIR__ . '/../includes/documentos_comentarios.php';

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

// Obtener comentarios
$comentarios = obtener_comentarios_documento($documento_id);

// Obtener servicios para mostrar
$servicios_nombres = [
    'tratamiento_agua' => 'Tratamiento de agua',
    'evaluacion_productos' => 'Evaluación de productos químicos',
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
    <link rel="stylesheet" href="/Pagina_Solicitudes4/assets/css/dashboard.css">
    
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
    <?php include __DIR__ . '/../includes/sidebar_colaborativo.php'; ?>
    
    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        
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
                            <?= strtoupper($documento['prioridad']) ?>
                        </span>
                        <span class="badge bg-light text-dark badge-prioridad ms-1">
                            <?= ucfirst(str_replace('_', ' ', $documento['estado'])) ?>
                        </span>
                    </div>
                    <div class="col-md-4 text-end no-print">
                        <button class="btn btn-light btn-sm" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                        <a href="documentos_colaborativos.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Información general -->
            <div class="row mb-3">
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
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle text-muted">Permisos</h6>
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
                    <?php include __DIR__ . '/../includes/documento_formulario.php'; ?>
                </div>
                
                <!-- TAB: Comentarios -->
                <div class="tab-pane fade" id="comentarios" role="tabpanel">
                    <?php include __DIR__ . '/../includes/documento_comentarios_ui.php'; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/Pagina_Solicitudes4/assets/js/notificaciones.js"></script>
    <script src="/Pagina_Solicitudes4/assets/js/documento_editar.js"></script>
</body>
</html>