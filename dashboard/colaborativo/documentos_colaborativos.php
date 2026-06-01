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
require_once __DIR__ . '/../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$departamento = $_SESSION['departamento'];
$dept_lower = strtolower($departamento);

// Verificar que el usuario tenga acceso (Normatividad, Ventas, Laboratorio o Dirección)
$departamentos_permitidos = ['normatividad', 'ventas', 'laboratorio', 'direccion', 'dirección', 'ptar'];
if (!in_array($dept_lower, $departamentos_permitidos)) {
    header('Location: ' . URL_BASE . 'dashboard/departamento.php');
    exit;
}

// Determinar vista y permisos
$puede_crear = in_array($dept_lower, ['normatividad', 'ventas', 'direccion', 'dirección', 'ptar']);
$es_laboratorio = $dept_lower == 'laboratorio';
$es_direccion = in_array($dept_lower, ['direccion', 'dirección']);

// Obtener filtros
$filtro_ubicacion = $_GET['ubicacion'] ?? 'local';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';
$filtro_departamento = $_GET['departamento'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir filtros
$filtros = ['ubicacion' => $filtro_ubicacion];

if (!empty($filtro_estado)) {
    $filtros['estado'] = $filtro_estado;
}

if (!empty($filtro_cliente)) {
    $filtros['cliente'] = $filtro_cliente;
}

if (!empty($filtro_departamento) && $filtro_ubicacion == 'global') {
    $filtros['departamento'] = $filtro_departamento;
}

if (!empty($filtro_fecha_desde)) {
    $filtros['fecha_desde'] = $filtro_fecha_desde . ' 00:00:00';
}

if (!empty($filtro_fecha_hasta)) {
    $filtros['fecha_hasta'] = $filtro_fecha_hasta . ' 23:59:59';
}

// Si es base local, mostrar solo del departamento del usuario
// Excepción: Laboratorio y Dirección ven TODAS las solicitudes
if ($filtro_ubicacion == 'local' && !$es_laboratorio && !$es_direccion) {
    $filtros['departamento'] = $departamento;
}

// Obtener documentos
$documentos = listar_documentos($filtros, $usuario_id, $departamento);

// ========================================
// OBTENER CONTEOS PARA STAT-CARDS
// ========================================
try {
    $pdo = conectarDB();
    
    if ($es_laboratorio) {
        // Laboratorio: Solicitudes pendientes (enviadas a laboratorio, no completadas)
        $sql_pendientes = "SELECT COUNT(*) FROM documentos_colaborativos 
                          WHERE estado IN ('enviado', 'en_seguimiento') 
                          AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_pendientes);
        $count_pendientes = $stmt->fetchColumn();
        
        // Laboratorio: Solicitudes finalizadas (todas, de todos los departamentos)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'";
        $stmt = $pdo->query($sql_finalizadas);
        $count_finalizadas_global = $stmt->fetchColumn();
        
        // ⭐ Laboratorio: Solicitudes completadas en el MES ACTUAL
        $sql_completadas_mes = "SELECT COUNT(*) FROM documentos_colaborativos 
                               WHERE estado = 'completado'
                               AND MONTH(fecha_completado) = MONTH(CURRENT_DATE())
                               AND YEAR(fecha_completado) = YEAR(CURRENT_DATE())";
        $stmt = $pdo->query($sql_completadas_mes);
        $count_completadas_mes = $stmt->fetchColumn();
        
    } elseif ($es_direccion) {
        // ⭐ Dirección: Ve TODAS las solicitudes de todos los departamentos
        
        // Solicitudes pendientes (todas, enviadas pero no completadas)
        $sql_pendientes = "SELECT COUNT(*) FROM documentos_colaborativos 
                          WHERE estado IN ('enviado', 'en_seguimiento') 
                          AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_pendientes);
        $count_pendientes_direccion = $stmt->fetchColumn();
        
        // Solicitudes en seguimiento (todas)
        $sql_seguimiento = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'en_seguimiento' 
                           AND ubicacion = 'local'";
        $stmt = $pdo->query($sql_seguimiento);
        $count_seguimiento_direccion = $stmt->fetchColumn();
        
        // Solicitudes finalizadas (todas, de todos los departamentos)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'";
        $stmt = $pdo->query($sql_finalizadas);
        $count_finalizadas_global = $stmt->fetchColumn();
        
    } else {
        // Ventas/Normatividad: Solicitudes enviadas (del departamento del usuario)
        $sql_enviadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                        WHERE estado = 'enviado' 
                        AND ubicacion = 'local'
                        AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_enviadas);
        $stmt->execute([':departamento' => $departamento]);
        $count_enviadas = $stmt->fetchColumn();
        
        // Ventas/Normatividad: Solicitudes en seguimiento (del departamento del usuario)
        $sql_seguimiento = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'en_seguimiento' 
                           AND ubicacion = 'local'
                           AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_seguimiento);
        $stmt->execute([':departamento' => $departamento]);
        $count_seguimiento = $stmt->fetchColumn();
        
        // Ventas/Normatividad: Solicitudes finalizadas (SOLO del departamento del usuario)
        $sql_finalizadas = "SELECT COUNT(*) FROM documentos_colaborativos 
                           WHERE estado = 'completado'
                           AND departamento_creador = :departamento";
        $stmt = $pdo->prepare($sql_finalizadas);
        $stmt->execute([':departamento' => $departamento]);
        $count_finalizadas = $stmt->fetchColumn();
    }
    
    // Obtener lista de clientes únicos para el filtro
    $sql_clientes = "SELECT DISTINCT nombre_cliente FROM documentos_colaborativos 
                    WHERE nombre_cliente IS NOT NULL AND nombre_cliente != '' 
                    ORDER BY nombre_cliente";
    $stmt = $pdo->query($sql_clientes);
    $clientes_lista = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Error al obtener conteos: " . $e->getMessage());
    $count_enviadas = 0;
    $count_seguimiento = 0;
    $count_finalizadas = 0;
    $count_pendientes = 0;
    $count_finalizadas_global = 0;
    $count_completadas_mes = 0;
    $count_pendientes_direccion = 0;
    $count_seguimiento_direccion = 0;
    $clientes_lista = [];
}
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
        
        /* Stat Cards */
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .stat-card .card-body {
            padding: 1rem 1.25rem;
        }
        
        .stat-card .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .stat-card.enviadas .stat-icon {
            background: rgba(13, 110, 253, 0.15);
            color: #0d6efd;
        }
        
        .stat-card.seguimiento .stat-icon {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .stat-card.finalizadas .stat-icon {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
        }
        
        .stat-card.pendientes .stat-icon {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        /* ⭐ Stat card para completadas del mes */
        .stat-card.mes-actual .stat-icon {
            background: rgba(13, 202, 240, 0.15);
            color: #0dcaf0;
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
            
            <!-- ========================================
                 STAT CARDS - Según ubicación seleccionada
                 ======================================== -->
            <?php if ($filtro_ubicacion == 'local'): ?>
                <!-- STAT CARDS PARA BASE LOCAL -->
                <?php if ($es_laboratorio): ?>
                <!-- Laboratorio en Base Local -->
                <div class="row mb-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card pendientes">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_pendientes ?></div>
                                        <div class="stat-label">Solicitudes Pendientes</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif ($es_direccion): ?>
                <!-- ⭐ Dirección en Base Local - Ve TODAS las solicitudes -->
                <div class="row mb-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card pendientes">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_pendientes_direccion ?></div>
                                        <div class="stat-label">Pendientes (Todas)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card seguimiento">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-eye"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_seguimiento_direccion ?></div>
                                        <div class="stat-label">En Seguimiento (Todas)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Ventas/Normatividad en Base Local -->
                <div class="row mb-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card enviadas">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-send"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_enviadas ?></div>
                                        <div class="stat-label">Solicitudes Enviadas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card seguimiento">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-eye"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_seguimiento ?></div>
                                        <div class="stat-label">En Seguimiento</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- STAT CARDS PARA BASE GLOBAL -->
                <div class="row mb-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card finalizadas">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div>
                                        <?php if ($es_laboratorio || $es_direccion): ?>
                                        <div class="stat-number"><?= $count_finalizadas_global ?></div>
                                        <?php else: ?>
                                        <div class="stat-number"><?= $count_finalizadas ?></div>
                                        <?php endif; ?>
                                        <div class="stat-label">Solicitudes Finalizadas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($es_laboratorio): ?>
                    <!-- ⭐ NUEVA CARD: Completadas del mes actual (solo Laboratorio) -->
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card stat-card mes-actual">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon me-3">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?= $count_completadas_mes ?></div>
                                        <?php
                                        $meses_es = [
                                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                        ];
                                        $mes_actual = $meses_es[(int)date('n')];
                                        ?>
                                        <div class="stat-label">Completadas en <?= $mes_actual ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="card mb-3">
                <div class="card-body p-3">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="ubicacion" value="<?= htmlspecialchars($filtro_ubicacion) ?>">
                        
                        <?php if ($filtro_ubicacion == 'local'): ?>
                        <!-- FILTROS PARA BASE LOCAL -->
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos</option>
                                <option value="borrador" <?= $filtro_estado == 'borrador' ? 'selected' : '' ?>>Borrador</option>
                                <option value="enviado" <?= $filtro_estado == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                <option value="en_seguimiento" <?= $filtro_estado == 'en_seguimiento' ? 'selected' : '' ?>>En Seguimiento</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Cliente</label>
                            <select name="cliente" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($clientes_lista as $cliente): ?>
                                <option value="<?= htmlspecialchars($cliente) ?>" <?= $filtro_cliente == $cliente ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Fecha desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                        </div>
                        
                        <?php else: ?>
                        <!-- FILTROS PARA BASE GLOBAL -->
                        <div class="col-md-3">
                            <label class="form-label">Departamento</label>
                            <select name="departamento" class="form-select">
                                <option value="">Todos</option>
                                <option value="Ventas" <?= $filtro_departamento == 'Ventas' ? 'selected' : '' ?>>Ventas</option>
                                <option value="Normatividad" <?= $filtro_departamento == 'Normatividad' ? 'selected' : '' ?>>Normatividad</option>
                                <option value="PTAR" <?= $filtro_departamento == 'PTAR' ? 'selected' : '' ?>>PTAR</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Cliente</label>
                            <select name="cliente" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($clientes_lista as $cliente): ?>
                                <option value="<?= htmlspecialchars($cliente) ?>" <?= $filtro_cliente == $cliente ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Fecha desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                            <a href="?ubicacion=<?= $filtro_ubicacion ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
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
                                            'revision_productos' => 'Productos químicos y/o residuos',
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
                                    
                                    <!-- Tabla de Sistema de Gestión -->
                                    <table class="table table-bordered table-sm mt-3 mb-0" style="font-size: 0.75rem;">
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold bg-light" style="width: 60%;">Código de Sistema De Gestión</td>
                                                <td class="text-center">FR-CC-12</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold bg-light">No. de Revisión</td>
                                                <td class="text-center">02</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold bg-light">Fecha de Revisión</td>
                                                <td class="text-center">26/01/2026</td>
                                            </tr>
                                        </tbody>
                                    </table>
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