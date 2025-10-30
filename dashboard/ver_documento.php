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
        .documento-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .campo-bloqueado {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .apartado-section {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .apartado-section.editable {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }
        
        .apartado-section.bloqueado {
            background-color: #f8f9fa;
        }
        
        .comentario-item {
            border-left: 4px solid #dee2e6;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
            border-radius: 4px;
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
            max-height: 500px;
            overflow-y: auto;
        }
        
        .badge-prioridad {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
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
                        <h3 class="mb-2">
                            <i class="bi bi-file-earmark-text"></i>
                            <?= htmlspecialchars($documento['folio']) ?>
                        </h3>
                        <p class="mb-1">Solicitud de Servicio a Clientes</p>
                        <span class="badge bg-<?= $documento['prioridad'] == 'alta' ? 'danger' : ($documento['prioridad'] == 'media' ? 'warning' : 'success') ?> badge-prioridad">
                            Prioridad: <?= strtoupper($documento['prioridad']) ?>
                        </span>
                        <span class="badge bg-light text-dark badge-prioridad ms-2">
                            Estado: <?= ucfirst(str_replace('_', ' ', $documento['estado'])) ?>
                        </span>
                    </div>
                    <div class="col-md-4 text-end no-print">
                        <button class="btn btn-light" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                        <a href="documentos_colaborativos.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Información general -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Información del documento</h6>
                            <p class="mb-1"><strong>Folio:</strong> <?= htmlspecialchars($documento['folio']) ?></p>
                            <p class="mb-1"><strong>Creado por:</strong> <?= htmlspecialchars($documento['departamento_creador']) ?></p>
                            <p class="mb-1"><strong>Fecha creación:</strong> <?= date('d/m/Y H:i', strtotime($documento['fecha_creacion'])) ?></p>
                            <p class="mb-0"><strong>Última actualización:</strong> <?= date('d/m/Y H:i', strtotime($documento['fecha_ultima_edicion'])) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Permisos</h6>
                            <p class="mb-1">
                                <i class="bi bi-<?= $permisos['apartado1'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Editar Apartado 1: <?= $permisos['apartado1'] ? 'Sí' : 'No' ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-<?= $permisos['apartado2'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Editar Apartado 2: <?= $permisos['apartado2'] ? 'Sí' : 'No' ?>
                            </p>
                            <p class="mb-0">
                                <i class="bi bi-<?= $permisos['puede_comentar'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                Agregar comentarios: <?= $permisos['puede_comentar'] ? 'Sí' : 'No' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs: Documento / Comentarios -->
            <ul class="nav nav-tabs mb-4" id="documentoTabs" role="tablist">
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