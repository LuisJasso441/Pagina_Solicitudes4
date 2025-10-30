<?php
/**
 * Página principal de Documentos Colaborativos
 * Vista según el departamento del usuario
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/documentos_colaborativos.php';
require_once __DIR__ . '/../includes/documentos_comentarios.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar que el usuario tenga acceso (Normatividad, Ventas o Laboratorio)
$departamentos_permitidos = ['normatividad', 'ventas', 'laboratorio'];
if (!in_array($dept_lower, $departamentos_permitidos)) {
    header('Location: ' . URL_BASE . 'dashboard/departamento.php');
    exit;
}

// Determinar vista y permisos
$puede_crear = in_array($dept_lower, ['normatividad', 'ventas']);
$es_laboratorio = $dept_lower == 'laboratorio';

// Obtener filtros
$filtro_ubicacion = $_GET['ubicacion'] ?? 'local';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir filtros
$filtros = ['ubicacion' => $filtro_ubicacion];

if (!empty($filtro_estado)) {
    $filtros['estado'] = $filtro_estado;
}

if (!empty($filtro_fecha_desde)) {
    $filtros['fecha_desde'] = $filtro_fecha_desde . ' 00:00:00';
}

if (!empty($filtro_fecha_hasta)) {
    $filtros['fecha_hasta'] = $filtro_fecha_hasta . ' 23:59:59';
}

// Si es base local, mostrar solo del departamento del usuario
if ($filtro_ubicacion == 'local' && !$es_laboratorio) {
    $filtros['departamento'] = $departamento;
}

// Obtener documentos
$documentos = listar_documentos($filtros, $usuario_id, $departamento);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Colaborativos - Sistema TI</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <style>
        .documento-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
        }
        
        .documento-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .documento-card.prioridad-alta {
            border-left-color: #dc3545;
        }
        
        .documento-card.prioridad-media {
            border-left-color: #ffc107;
        }
        
        .documento-card.prioridad-baja {
            border-left-color: #28a745;
        }
        
        .badge-estado {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .folio-badge {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .comentarios-count {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .comentarios-count i {
            margin-right: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar_colaborativo.php'; ?>
    
    <div class="main-content">
        
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                Documentos Colaborativos
                            </h2>
                            <p class="text-muted mb-0">Solicitudes de Servicio a Clientes (SSC)</p>
                        </div>
                        
                        <?php if ($puede_crear): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoDocumento">
                            <i class="bi bi-plus-circle"></i> Nuevo Documento
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tabs: Base Local / Base Global -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $filtro_ubicacion == 'local' ? 'active' : '' ?>" 
                       href="?ubicacion=local">
                        <i class="bi bi-folder"></i> Base Local
                        <?php if (!$es_laboratorio): ?>
                            (<?= $departamento ?>)
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $filtro_ubicacion == 'global' ? 'active' : '' ?>" 
                       href="?ubicacion=global">
                        <i class="bi bi-globe"></i> Base Global (Completados)
                    </a>
                </li>
            </ul>
            
            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="ubicacion" value="<?= htmlspecialchars($filtro_ubicacion) ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos</option>
                                <option value="borrador" <?= $filtro_estado == 'borrador' ? 'selected' : '' ?>>Borrador</option>
                                <option value="enviado" <?= $filtro_estado == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                <option value="en_seguimiento" <?= $filtro_estado == 'en_seguimiento' ? 'selected' : '' ?>>En Seguimiento</option>
                                <option value="completado" <?= $filtro_estado == 'completado' ? 'selected' : '' ?>>Completado</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="?ubicacion=<?= htmlspecialchars($filtro_ubicacion) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de Documentos -->
            <div class="row">
                <?php if (empty($documentos)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No hay documentos para mostrar con los filtros seleccionados.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($documentos as $doc): ?>
                        <?php
                        $prioridad_class = 'prioridad-' . $doc['prioridad'];
                        $estado_badge = [
                            'borrador' => 'secondary',
                            'enviado' => 'info',
                            'en_seguimiento' => 'warning',
                            'completado' => 'success'
                        ];
                        $badge_color = $estado_badge[$doc['estado']] ?? 'secondary';
                        
                        // Obtener número de comentarios
                        $num_comentarios = contar_comentarios_documento($doc['id']);
                        ?>
                        
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card documento-card <?= $prioridad_class ?> h-100">
                                <div class="card-body">
                                    <!-- Folio y Estado -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="folio-badge text-primary">
                                            <?= htmlspecialchars($doc['folio']) ?>
                                        </span>
                                        <span class="badge bg-<?= $badge_color ?> badge-estado">
                                            <?= ucfirst(str_replace('_', ' ', $doc['estado'])) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Prioridad -->
                                    <div class="mb-2">
                                        <span class="badge bg-<?= $doc['prioridad'] == 'alta' ? 'danger' : ($doc['prioridad'] == 'media' ? 'warning' : 'success') ?>">
                                            <?= strtoupper($doc['prioridad']) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Información principal -->
                                    <h6 class="card-title mb-2"><?= htmlspecialchars($doc['solicitado_por']) ?></h6>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="bi bi-building"></i>
                                        <?= htmlspecialchars($doc['area_proceso_solicitante']) ?>
                                    </p>
                                    
                                    <!-- Servicio -->
                                    <p class="card-text small mb-2">
                                        <strong>Servicio:</strong>
                                        <?php
                                        $servicios = [
                                            'tratamiento_agua' => 'Tratamiento de agua',
                                            'evaluacion_productos' => 'Evaluación de productos químicos',
                                            'calibracion_equipos' => 'Calibración y/o verificación de equipos',
                                            'otro' => $doc['servicio_otro_especificar'] ?? 'Otro'
                                        ];
                                        echo htmlspecialchars($servicios[$doc['servicio_solicitado']] ?? 'N/A');
                                        ?>
                                    </p>
                                    
                                    <!-- Descripción (truncada) -->
                                    <p class="card-text small text-muted">
                                        <?= htmlspecialchars(mb_substr($doc['descripcion_servicio'], 0, 100)) ?>
                                        <?= mb_strlen($doc['descripcion_servicio']) > 100 ? '...' : '' ?>
                                    </p>
                                    
                                    <!-- Fechas -->
                                    <div class="small text-muted mb-3">
                                        <div><i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($doc['fecha_solicitud'])) ?></div>
                                        <?php if ($doc['fecha_completado']): ?>
                                        <div><i class="bi bi-check-circle"></i> Completado: <?= date('d/m/Y', strtotime($doc['fecha_completado'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Comentarios y acciones -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="comentarios-count">
                                            <i class="bi bi-chat-dots"></i>
                                            <?= $num_comentarios ?> comentario<?= $num_comentarios != 1 ? 's' : '' ?>
                                        </span>
                                        
                                        <a href="ver_documento.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($puede_crear): ?>
    <!-- Modal Nuevo Documento -->
    <?php include __DIR__ . '/../includes/modal_nuevo_documento.php'; ?>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>