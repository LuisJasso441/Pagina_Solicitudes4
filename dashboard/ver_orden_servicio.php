<?php
/**
 * Vista Detallada - Orden de Servicio para Mantenimiento
 * Muestra los 3 apartados con modo visualización/edición
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Obtener ID de la orden
$orden_id = $_GET['id'] ?? null;

if (!$orden_id) {
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio_mantenimiento.php');
    exit;
}

// Obtener orden
$orden = obtener_orden_por_id($orden_id);

if (!$orden) {
    $_SESSION['error'] = "Orden no encontrada.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio_mantenimiento.php');
    exit;
}

// Verificar permisos
$permisos = verificar_permiso_orden($orden_id, $_SESSION['usuario_id'], $_SESSION['departamento']);

if (!$permisos['puede_ver']) {
    $_SESSION['error'] = "No tienes permiso para ver esta orden.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio_mantenimiento.php');
    exit;
}

// Determinar modo de visualización
$modo_edicion = isset($_GET['modo']) && $_GET['modo'] === 'editar' && $permisos['puede_editar'];

// Para Apartado 3: siempre en modo edición si el estado es pendiente_usuario
$modo_edicion_apartado3 = ($orden['estado'] === 'pendiente_usuario');

$apartado1 = $orden['apartado1'] ?? [];
$apartado2 = $orden['apartado2'] ?? [];
$apartado3 = $orden['apartado3'] ?? [];

// Obtener nombre del usuario creador de la orden
try {
    $db = conectarDB();
    $stmt_creador = $db->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $stmt_creador->execute([$orden['usuario_id']]);
    $nombre_usuario_creador = $stmt_creador->fetchColumn();
} catch (Exception $e) {
    $nombre_usuario_creador = 'Usuario no encontrado';
    error_log("Error obteniendo usuario creador: " . $e->getMessage());
}

$es_mantenimiento = ($_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento']))) === 'mantenimiento';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden <?php echo htmlspecialchars($orden['folio']); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    
    <style>
        /* ====== ESTILOS COMPACTOS ====== */
        .apartado-section {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            background: #fff;
        }
        .apartado-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 5px 5px 0 0;
            margin: -12px -12px 12px -12px;
        }
        .apartado-header h4 {
            font-size: 0.9rem;
            margin: 0;
        }
        .campo-visualizacion {
            background: #f8f9fa;
            padding: 6px 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            border-left: 3px solid #667eea;
        }
        .campo-visualizacion label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 2px;
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .campo-visualizacion .valor {
            color: #212529;
            font-size: 0.85rem;
        }
        .firma-container {
            border: 1px dashed #6c757d;
            border-radius: 5px;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            margin-top: 5px;
        }
        .firma-container > div {
            border: 1px solid #667eea !important;
            border-radius: 4px;
            background: white !important;
            cursor: crosshair;
            margin: 5px auto;
        }
        .firma-container canvas {
            border: 1px solid #667eea !important;
            border-radius: 4px;
            background: white !important;
            cursor: crosshair;
            display: block;
        }
        .firma-imagen {
            max-width: 100%;
            max-height: 80px;
            border: 1px solid #dee2e6;
            background: white;
        }
        .btn-group-acciones {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }
        
        /* Formularios compactos */
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #495057;
        }
        .form-control, .form-select {
            font-size: 0.85rem;
            padding: 0.35rem 0.6rem;
        }
        textarea.form-control {
            min-height: 60px;
        }
        .row.g-3 {
            --bs-gutter-y: 0.5rem;
            --bs-gutter-x: 0.75rem;
        }
        .row.g-4 {
            --bs-gutter-y: 0.75rem;
        }
        
        /* Encabezado compacto */
        .btn-group-acciones h2 {
            font-size: 1.1rem;
        }
        .btn-group-acciones .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.5rem;
        }
        
        /* Botones más pequeños */
        .btn {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }
        .btn-sm {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }
        .btn-lg {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        /* Títulos de sección */
        h5 {
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        /* Alerts compactos */
        .alert {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        /* Responsive para botones en móvil */
        @media (max-width: 768px) {
            .btn-group-acciones .row {
                flex-direction: column;
            }
            .btn-group-acciones .col-md-6 {
                width: 100%;
                margin-bottom: 10px;
            }
            .btn-group-acciones .text-end {
                text-align: center !important;
            }
            .btn-group-acciones .btn {
                width: 100%;
                margin-bottom: 8px;
            }
            .btn-group-acciones h2 {
                font-size: 1rem;
                text-align: center;
            }
        }
    </style>
    
    <!-- jSignature para firmas digitales -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jSignature@2.1.3/libs/jSignature.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php 
        // Determinar qué sidebar cargar según el departamento
        $departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
        
        if ($departamento === 'mantenimiento') {
            include __DIR__ . '/../includes/sidebar_mantenimiento.php';
        } elseif ($departamento === 'ti' || $departamento === 'sistemas' || $departamento === 'ti_sistemas') {
            include __DIR__ . '/../includes/sidebar_ti.php';
        } else {
            // Para usuarios colaborativos
            include __DIR__ . '/../includes/sidebar_colaborativo.php';
        }
        ?>
        
        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper" style="padding: 1rem;">
                    
                    <!-- Encabezado con botones de acción -->
                    <div class="card shadow-sm mb-3 btn-group-acciones">
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h2 class="mb-0">
                                        <i class="bi bi-file-earmark-text text-primary"></i> 
                                        <strong><?php echo htmlspecialchars($orden['folio']); ?></strong>
                                    </h2>
                                    <span class="text-muted" style="font-size: 0.8rem;">
                                        <?php
                                        $estado_badges = [
                                            'pendiente_mantenimiento' => ['class' => 'warning', 'text' => 'Pendiente de Mantenimiento'],
                                            'en_proceso' => ['class' => 'info', 'text' => 'En Proceso'],
                                            'pendiente_usuario' => ['class' => 'primary', 'text' => 'Pendiente de Validación'],
                                            'devuelto' => ['class' => 'danger', 'text' => 'Devuelta para Corrección'],
                                            'completado' => ['class' => 'success', 'text' => 'Completada']
                                        ];
                                        $badge = $estado_badges[$orden['estado']];
                                        ?>
                                        <span class="badge bg-<?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span>
                                    </span>
                                </div>
                                <div class="col-md-6 text-end">
                                    <!-- Botón Volver -->
                                    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-arrow-left"></i> Volver
                                    </a>
                                    
                                    <!-- Botón Editar (solo si puede editar y no está en modo edición) -->
                                    <?php if ($permisos['puede_editar'] && !$modo_edicion && $orden['estado'] !== 'pendiente_usuario'): ?>
                                        <a href="?id=<?php echo $orden_id; ?>&modo=editar" class="btn btn-primary btn-sm">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Botón Cancelar Edición -->
                                    <?php if ($modo_edicion): ?>
                                        <a href="?id=<?php echo $orden_id; ?>" class="btn btn-secondary btn-sm">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- ============================================ -->
                    <!-- APARTADO 1: PARA SER LLENADO POR EL SOLICITANTE -->
                    <!-- ============================================ -->
                    <div class="apartado-section">
                        <div class="apartado-header">
                            <h4><i class="bi bi-person-fill"></i> APARTADO 1: Información del Solicitante</h4>
                        </div>
                        
                        <?php if ($modo_edicion && isset($permisos['puede_editar_apartado1']) && $permisos['puede_editar_apartado1']): ?>
                            <!-- MODO EDICIÓN - Solo usuario propietario -->
                            <form id="formApartado1" method="POST" action="<?php echo URL_BASE; ?>ordenes_servicio/procesar_editar_apartado1.php">
                                <input type="hidden" name="orden_id" value="<?php echo $orden_id; ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Empresa *</label>
                                        <select name="empresa" class="form-select" required>
                                            <option value="RESIMEX" <?php echo ($apartado1['empresa'] ?? '') == 'RESIMEX' ? 'selected' : ''; ?>>RESIMEX</option>
                                            <option value="CARGANOVA" <?php echo ($apartado1['empresa'] ?? '') == 'CARGANOVA' ? 'selected' : ''; ?>>CARGANOVA</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Folio de Mantenimiento *</label>
                                        <input type="text" name="folio" class="form-control" value="<?php echo htmlspecialchars($apartado1['folio'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Área Solicitante</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($apartado1['area_solicitante'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Unidad/Equipo *</label>
                                        <input type="text" name="unidad_equipo" class="form-control" value="<?php echo htmlspecialchars($apartado1['unidad_equipo'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del Solicitante</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($apartado1['nombre_solicitante'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Prioridad *</label>
                                        <input type="text" name="prioridad" class="form-control" value="<?php echo htmlspecialchars($apartado1['prioridad'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descripción de la Falla *</label>
                                        <textarea name="descripcion_falla" class="form-control" rows="3" required><?php echo htmlspecialchars($apartado1['descripcion_falla'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- MODO VISUALIZACIÓN -->
                            <div class="row g-3">
                                <div class="col-md-4 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Empresa</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['empresa'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Folio de Mantenimiento</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['folio'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Área Solicitante</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['area_solicitante'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Fecha de Entrada</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['fecha_entrada'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Hora de Entrada</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['hora_entrada'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Unidad/Equipo</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['unidad_equipo'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="campo-visualizacion">
                                        <label>Prioridad</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['prioridad'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="campo-visualizacion">
                                        <label>Nombre del Solicitante</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado1['nombre_solicitante'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="campo-visualizacion">
                                        <label>Descripción de la Falla</label>
                                        <div class="valor"><?php echo nl2br(htmlspecialchars($apartado1['descripcion_falla'] ?? '-')); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($apartado1['evidencia_archivos'])): ?>
                                    <div class="col-12">
                                        <div class="campo-visualizacion">
                                            <label>Evidencia de la Falla</label>
                                            <div class="valor">
                                                <?php foreach ($apartado1['evidencia_archivos'] as $archivo): ?>
                                                    <a href="<?php echo URL_BASE . $archivo['ruta']; ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1 mb-1">
                                                        <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($archivo['nombre_original']); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- APARTADO 2: PARA SER LLENADO POR MANTENIMIENTO -->
                    <!-- ============================================ -->
                    <?php if (in_array($orden['estado'], ['en_proceso', 'pendiente_usuario', 'devuelto', 'completado']) || ($es_mantenimiento && $modo_edicion)): ?>
                        <div class="apartado-section">
                            <div class="apartado-header">
                                <h4><i class="bi bi-tools"></i> APARTADO 2: Área de Mantenimiento</h4>
                            </div>
                            
                            <?php if ($modo_edicion && $es_mantenimiento): ?>
                                <!-- MODO EDICIÓN - Solo Mantenimiento -->
                                <form id="formApartado2" method="POST">
                                    <input type="hidden" name="orden_id" value="<?php echo $orden_id; ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Fecha de Atención *</label>
                                            <input type="date" name="fecha_atencion" class="form-control" 
                                                   value="<?php echo $apartado2['fecha_atencion'] ?? date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Hora de Inicio *</label>
                                            <input type="time" name="hora_inicio" class="form-control" 
                                                   value="<?php echo $apartado2['hora_inicio'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Fecha de Término</label>
                                            <input type="date" name="fecha_termino" class="form-control" 
                                                   value="<?php echo $apartado2['fecha_termino'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <label class="form-label">Hora de Término</label>
                                            <input type="time" name="hora_termino" class="form-control" 
                                                   value="<?php echo $apartado2['hora_termino'] ?? ''; ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Descripción de Reparación de Falla *</label>
                                            <textarea name="descripcion_reparacion" class="form-control" rows="3" required><?php echo htmlspecialchars($apartado2['descripcion_reparacion'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Personal Asignado -->
                                        <div class="col-12">
                                            <label class="form-label">Personal Asignado</label>
                                        </div>
                                        <?php 
                                        $personal = $apartado2['personal_asignado'] ?? [
                                            ['nombre' => '', 'firma' => ''],
                                            ['nombre' => '', 'firma' => ''],
                                            ['nombre' => '', 'firma' => '']
                                        ];
                                        for ($i = 0; $i < 3; $i++): 
                                        ?>
                                            <div class="col-md-4">
                                                <input type="text" name="personal_nombre[]" class="form-control" 
                                                       placeholder="Nombre <?php echo $i+1; ?>"
                                                       value="<?php echo htmlspecialchars($personal[$i]['nombre'] ?? ''); ?>">
                                            </div>
                                        <?php endfor; ?>
                                        
                                        <div class="col-12 mt-2">
                                            <button type="button" class="btn btn-success btn-sm me-2" onclick="guardarApartado2('guardar')">
                                                <i class="bi bi-save"></i> Guardar
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="guardarApartado2('enviar')">
                                                <i class="bi bi-send"></i> Enviar a Usuario
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- MODO VISUALIZACIÓN -->
                                <div class="row g-3">
                                    <div class="col-md-3 col-6">
                                        <div class="campo-visualizacion">
                                            <label>Fecha de Atención</label>
                                            <div class="valor"><?php echo htmlspecialchars($apartado2['fecha_atencion'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="campo-visualizacion">
                                            <label>Hora de Inicio</label>
                                            <div class="valor"><?php echo htmlspecialchars($apartado2['hora_inicio'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="campo-visualizacion">
                                            <label>Fecha de Término</label>
                                            <div class="valor"><?php echo htmlspecialchars($apartado2['fecha_termino'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="campo-visualizacion">
                                            <label>Hora de Término</label>
                                            <div class="valor"><?php echo htmlspecialchars($apartado2['hora_termino'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="campo-visualizacion">
                                            <label>Descripción de Reparación</label>
                                            <div class="valor"><?php echo nl2br(htmlspecialchars($apartado2['descripcion_reparacion'] ?? '-')); ?></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($apartado2['personal_asignado'])): ?>
                                        <div class="col-12">
                                            <label class="form-label" style="font-size: 0.75rem;">Personal Asignado</label>
                                            <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($apartado2['personal_asignado'] as $persona): ?>
                                                <?php if (!empty($persona['nombre'])): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($persona['nombre']); ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- ============================================ -->
                    <!-- APARTADO 3: CIERRE DE ORDEN DE SERVICIO -->
                    <!-- ============================================ -->
                    <?php if ($modo_edicion_apartado3): ?>
                        <div class="apartado-section">
                            <div class="apartado-header">
                                <h4><i class="bi bi-check-circle"></i> APARTADO 3: Cierre de Orden de Servicio</h4>
                            </div>
                            
                            <div class="row g-3">
                                <!-- Firma del Solicitante -->
                                <div class="col-md-6">
                                    <h5>Solicitante</h5>
                                    <div class="mb-2">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" id="nombre_solicitante" class="form-control" 
                                               value="<?php echo htmlspecialchars($apartado3['nombre_solicitante'] ?? $nombre_usuario_creador); ?>" 
                                               readonly>
                                    </div>
                                    <div class="firma-container">
                                        <label class="form-label" style="font-size: 0.7rem;">Firma del Solicitante</label>
                                        <div id="firma_solicitante" style="width: 100%; height: 100px;"></div>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="limpiarFirma('solicitante')">
                                            <i class="bi bi-trash"></i> Limpiar
                                        </button>
                                        <?php if ($permisos['es_propietario']): ?>
                                            <button type="button" class="btn btn-sm btn-primary mt-1" onclick="guardarFirma('solicitante')">
                                                <i class="bi bi-save"></i> Guardar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Firma de Mantenimiento -->
                                <div class="col-md-6">
                                    <h5>Responsable de Mantenimiento</h5>
                                    <div class="mb-2">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" id="nombre_responsable_mant" class="form-control" 
                                               value="<?php echo htmlspecialchars($apartado3['nombre_responsable_mantenimiento'] ?? ''); ?>"
                                               <?php echo !$es_mantenimiento ? 'readonly' : ''; ?>>
                                    </div>
                                    <div class="firma-container">
                                        <label class="form-label" style="font-size: 0.7rem;">Firma del Responsable</label>
                                        <div id="firma_mantenimiento" style="width: 100%; height: 100px;"></div>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="limpiarFirma('mantenimiento')">
                                            <i class="bi bi-trash"></i> Limpiar
                                        </button>
                                        <?php if ($es_mantenimiento): ?>
                                            <button type="button" class="btn btn-sm btn-primary mt-1" onclick="guardarFirma('mantenimiento')">
                                                <i class="bi bi-save"></i> Guardar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Botones de acción del usuario -->
                                <?php if ($permisos['es_propietario']): ?>
                                    <div class="col-12 text-center mt-3">
                                        <button type="button" class="btn btn-warning me-2" onclick="devolverOrden()">
                                            <i class="bi bi-arrow-return-left"></i> Devolver
                                        </button>
                                        <button type="button" class="btn btn-success" onclick="finalizarOrden()">
                                            <i class="bi bi-check-circle"></i> Finalizar
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($orden['estado'] === 'completado' && !empty($apartado3)): ?>
                        <!-- Mostrar firmas en modo visualización -->
                        <div class="apartado-section">
                            <div class="apartado-header">
                                <h4><i class="bi bi-check-circle"></i> APARTADO 3: Cierre de Orden de Servicio</h4>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h5>Solicitante</h5>
                                    <div class="campo-visualizacion">
                                        <label>Nombre</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado3['nombre_solicitante'] ?? '-'); ?></div>
                                    </div>
                                    <?php if (!empty($apartado3['firma_solicitante'])): ?>
                                        <div class="firma-container mt-2">
                                            <label style="font-size: 0.7rem;">Firma</label>
                                            <img src="<?php echo $apartado3['firma_solicitante']; ?>" class="firma-imagen" alt="Firma Solicitante">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h5>Responsable de Mantenimiento</h5>
                                    <div class="campo-visualizacion">
                                        <label>Nombre</label>
                                        <div class="valor"><?php echo htmlspecialchars($apartado3['nombre_responsable_mantenimiento'] ?? '-'); ?></div>
                                    </div>
                                    <?php if (!empty($apartado3['firma_responsable_mantenimiento'])): ?>
                                        <div class="firma-container mt-2">
                                            <label style="font-size: 0.7rem;">Firma</label>
                                            <img src="<?php echo $apartado3['firma_responsable_mantenimiento']; ?>" class="firma-imagen" alt="Firma Mantenimiento">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </main>
        
        </div>
    </div>
    
    <!-- jQuery (necesario para jSignature) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- jSignature para firmas digitales -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        console.log('jQuery cargado:', typeof jQuery !== 'undefined');
        console.log('jSignature cargado:', typeof $.fn.jSignature !== 'undefined');
        
        // Inicializar firmas digitales
        <?php if ($modo_edicion_apartado3): ?>
            let firmaSolicitante, firmaMantenimiento;
            
            $(document).ready(function() {
                console.log('DOM ready, inicializando firmas...');
                
                // Inicializar firma del solicitante
                try {
                    firmaSolicitante = $('#firma_solicitante').jSignature({
                        color: '#000000',
                        lineWidth: 2,
                        background: '#ffffff',
                        width: '100%',
                        height: 100,
                        'decor-color': '#667eea'
                    });
                    console.log('Firma solicitante inicializada');
                } catch(e) {
                    console.error('Error inicializando firma solicitante:', e);
                }
                
                // Inicializar firma del responsable
                try {
                    firmaMantenimiento = $('#firma_mantenimiento').jSignature({
                        color: '#000000',
                        lineWidth: 2,
                        background: '#ffffff',
                        width: '100%',
                        height: 100,
                        'decor-color': '#667eea'
                    });
                    console.log('Firma responsable inicializada');
                } catch(e) {
                    console.error('Error inicializando firma responsable:', e);
                }
                
                // Cargar firmas existentes si las hay
                <?php if (!empty($apartado3['firma_solicitante'])): ?>
                    firmaSolicitante.jSignature('setData', '<?php echo $apartado3['firma_solicitante']; ?>');
                <?php endif; ?>
                
                <?php if (!empty($apartado3['firma_responsable_mantenimiento'])): ?>
                    firmaMantenimiento.jSignature('setData', '<?php echo $apartado3['firma_responsable_mantenimiento']; ?>');
                <?php endif; ?>
            });
            
            function limpiarFirma(tipo) {
                if (tipo === 'solicitante') {
                    firmaSolicitante.jSignature('reset');
                } else {
                    firmaMantenimiento.jSignature('reset');
                }
            }
            
            function guardarFirma(tipo) {
                const firma = tipo === 'solicitante' ? firmaSolicitante : firmaMantenimiento;
                const nombre = tipo === 'solicitante' ? 
                    document.getElementById('nombre_solicitante').value :
                    document.getElementById('nombre_responsable_mant').value;
                
                if (!nombre.trim()) {
                    alert('Por favor, ingrese el nombre antes de guardar la firma.');
                    return;
                }
                
                const firmaData = firma.jSignature('getData', 'image');
                
                // Enviar vía AJAX
                fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_firmar_apartado3.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        orden_id: <?php echo $orden_id; ?>,
                        tipo_firma: tipo,
                        nombre: nombre,
                        firma: 'data:' + firmaData[0] + ',' + firmaData[1]
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Firma guardada correctamente');
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
            
            function devolverOrden() {
                if (!confirm('¿Está seguro de devolver esta orden para corrección?')) return;
                
                fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_devolver_orden.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({orden_id: <?php echo $orden_id; ?>})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Orden devuelta correctamente');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
            
            function finalizarOrden() {
                // Validar firmas
                const firmaSolData = firmaSolicitante.jSignature('getData', 'image');
                const firmaMantData = firmaMantenimiento.jSignature('getData', 'image');
                
                if (!firmaSolData || !firmaMantData) {
                    alert('Por favor, asegúrese de que ambas firmas estén completas.');
                    return;
                }
                
                if (!confirm('¿Está seguro de finalizar esta orden? Esta acción no se puede deshacer.')) return;
                
                const apartado3Data = {
                    nombre_solicitante: document.getElementById('nombre_solicitante').value,
                    firma_solicitante: 'data:' + firmaSolData[0] + ',' + firmaSolData[1],
                    nombre_responsable_mantenimiento: document.getElementById('nombre_responsable_mant').value,
                    firma_responsable_mantenimiento: 'data:' + firmaMantData[0] + ',' + firmaMantData[1]
                };
                
                fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_finalizar_orden.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        orden_id: <?php echo $orden_id; ?>,
                        apartado3: apartado3Data
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('¡Orden finalizada correctamente!');
                        window.location = '<?php echo URL_BASE; ?>dashboard/ordenes_servicio_mantenimiento.php';
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        <?php endif; ?>
        
        // Guardar Apartado 2
        function guardarApartado2(accion) {
            const form = document.getElementById('formApartado2');
            const formData = new FormData(form);
            
            const data = {
                orden_id: <?php echo $orden_id; ?>,
                accion: accion,
                fecha_atencion: formData.get('fecha_atencion'),
                hora_inicio: formData.get('hora_inicio'),
                fecha_termino: formData.get('fecha_termino'),
                hora_termino: formData.get('hora_termino'),
                descripcion_reparacion: formData.get('descripcion_reparacion'),
                personal_asignado: []
            };
            
            // Obtener nombres del personal
            const nombres = formData.getAll('personal_nombre[]');
            nombres.forEach(nombre => {
                if (nombre.trim()) {
                    data.personal_asignado.push({nombre: nombre, firma: ''});
                }
            });
            
            const url = accion === 'guardar' ? 
                '<?php echo URL_BASE; ?>ordenes_servicio/procesar_guardar_apartado2.php' :
                '<?php echo URL_BASE; ?>ordenes_servicio/procesar_enviar_apartado2.php';
            
            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(accion === 'guardar' ? 'Guardado correctamente' : 'Enviado correctamente al usuario');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
    
    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>