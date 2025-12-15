<?php
/**
 * Página principal de Documentos Colaborativos
 * Vista según el departamento del usuario
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_colaborativos.php';
require_once __DIR__ . '/../../includes/colaborativo/documentos_comentarios.php';

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
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }

        .folio-badge {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .comentarios-count {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .comentarios-count i {
            margin-right: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php'; ?>
    
    <div class="main-content">

        <div class="container-fluid p-2">
            <!-- Header -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                SOLICITUDES DE SERVICIO A CLIENTES
                            </h3>
                        </div>
                        
                        <?php if ($puede_crear): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoDocumento">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud de Servicio
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tabs: Base Local / Base Global -->
            <ul class="nav nav-tabs mb-3" role="tablist">
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
            <div class="card mb-3">
                <div class="card-body p-3">
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
                            <label class="form-label">Fecha desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="?ubicacion=<?= $filtro_ubicacion ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de documentos -->
            <div class="row">
                <?php if (empty($documentos)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle fs-4"></i>
                            <p class="mb-0 mt-2">No hay documentos que mostrar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($documentos as $doc): 
                        // Obtener número de comentarios
                        $num_comentarios = contar_comentarios_documento($doc['id']);
                        
                        // Determinar clase de prioridad
                        $prioridad_class = '';
                        if ($doc['prioridad'] == 'alta') {
                            $prioridad_class = 'prioridad-alta';
                        } elseif ($doc['prioridad'] == 'media') {
                            $prioridad_class = 'prioridad-media';
                        } else {
                            $prioridad_class = 'prioridad-baja';
                        }
                        
                        // Badge de estado
                        $estado_badges = [
                            'borrador' => 'bg-secondary',
                            'enviado' => 'bg-primary',
                            'en_seguimiento' => 'bg-warning text-dark',
                            'completado' => 'bg-success'
                        ];
                        $badge_class = $estado_badges[$doc['estado']] ?? 'bg-secondary';
                    ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card documento-card <?= $prioridad_class ?>">
                                <div class="card-body">
                                    <!-- Header: Folio y Estado -->
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="folio-badge text-primary">
                                            <?= htmlspecialchars($doc['folio']) ?>
                                        </span>
                                        <span class="badge badge-estado <?= $badge_class ?>">
                                            <?= ucfirst(str_replace('_', ' ', $doc['estado'])) ?>
                                        </span>
                                    </div>

                                    <!-- Cliente -->
                                    <h6 class="card-title mb-1">
                                        <?= htmlspecialchars($doc['nombre_cliente'] ?? 'Sin cliente') ?>
                                    </h6>

                                    <!-- Departamento y Servicio -->
                                    <p class="card-text small text-muted mb-2">
                                        <i class="bi bi-building"></i> <?= htmlspecialchars($doc['departamento_creador']) ?><br>
                                        <i class="bi bi-gear"></i> 
                                        <?php
                                        $servicios = [
                                            'tratamiento_agua' => 'Tratamiento de agua',
                                            'revision_productos' => 'Revisión de productos químicos',
                                            'calibracion_equipos' => 'Calibración y/o verificación de equipos',
                                            'otro' => 'Otro'
                                        ];
                                        echo htmlspecialchars($servicios[$doc['servicio_solicitado']] ?? 'N/A');
                                        ?>
                                    </p>

                                    <!-- Descripción (truncada) -->
                                    <p class="card-text small text-muted">
                                        <?= htmlspecialchars(mb_substr($doc['descripcion_servicio'], 0, 80)) ?>
                                        <?= mb_strlen($doc['descripcion_servicio']) > 80 ? '...' : '' ?>
                                    </p>

                                    <!-- Fechas -->
                                    <div class="small text-muted mb-2">
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
    <?php include __DIR__ . '/../../includes/colaborativo/modal_nuevo_documento.php'; ?>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>