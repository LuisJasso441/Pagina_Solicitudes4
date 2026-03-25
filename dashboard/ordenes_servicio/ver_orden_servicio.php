<?php
/**
 * Vista Detallada - Orden de Servicio para Mantenimiento
 * Muestra los 3 apartados con modo visualización/edición
 * VERSIÓN CORREGIDA - Firmas digitales funcionando
 * 
 * ACTUALIZADO: Incluye modal para motivo de devolución y campo visual en Apartado 1
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ordenes_servicio/ordenes_servicio_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Obtener ID de la orden
$orden_id = $_GET['id'] ?? null;

if (!$orden_id) {
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Obtener orden
$orden = obtener_orden_por_id($orden_id);

if (!$orden) {
    $_SESSION['error'] = "Orden no encontrada.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Verificar permisos
$permisos = verificar_permiso_orden($orden_id, $_SESSION['usuario_id'], $_SESSION['departamento']);

if (!$permisos['puede_ver']) {
    $_SESSION['error'] = "No tienes permiso para ver esta orden.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
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

// ========================================
// FUNCIONES HELPER PARA ARCHIVOS OSM
// ========================================

/**
 * Obtiene la ruta correcta de un archivo OSM
 * Compatible con datos nuevos y antiguos
 */
function obtener_ruta_archivo_osm($archivo) {
    // Prioridad 1: nombre_guardado (formato nuevo)
    if (isset($archivo['nombre_guardado']) && !empty($archivo['nombre_guardado'])) {
        return 'Imagenes_OSM/' . $archivo['nombre_guardado'];
    }
    // Prioridad 2: nombre_archivo
    if (isset($archivo['nombre_archivo']) && !empty($archivo['nombre_archivo'])) {
        return 'Imagenes_OSM/' . $archivo['nombre_archivo'];
    }
    // Prioridad 3: ruta completa
    if (isset($archivo['ruta']) && !empty($archivo['ruta'])) {
        return $archivo['ruta'];
    }
    return '';
}

/**
 * Verifica si un archivo es una imagen por su extensión
 */
function es_imagen_osm($nombre_archivo) {
    $extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    return in_array($extension, $extensiones_imagen);
}

/**
 * Obtiene el icono apropiado según la extensión del archivo
 */
function obtener_icono_archivo_osm($nombre_archivo) {
    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    
    $iconos = [
        'pdf' => 'bi-file-pdf text-danger',
        'doc' => 'bi-file-word text-primary',
        'docx' => 'bi-file-word text-primary',
        'xls' => 'bi-file-excel text-success',
        'xlsx' => 'bi-file-excel text-success',
        'ppt' => 'bi-file-ppt text-warning',
        'pptx' => 'bi-file-ppt text-warning',
        'txt' => 'bi-file-text text-muted',
        'zip' => 'bi-file-zip text-info',
        'rar' => 'bi-file-zip text-info',
        'mp4' => 'bi-file-play text-primary',
        'mp3' => 'bi-file-music text-success',
        'avi' => 'bi-file-play text-primary',
        'mov' => 'bi-file-play text-primary',
    ];
    
    return $iconos[$extension] ?? 'bi-file-earmark text-secondary';
}

/**
 * Formatea el tamaño de archivo en formato legible
 */
function formatear_tamanio_osm($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
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
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
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
        
        /* Estilos para firmas */
        .firma-container {
            border: 1px dashed #6c757d;
            border-radius: 5px;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            margin-top: 5px;
        }
        .firma-container .jSignature {
            border: 1px solid #667eea !important;
            border-radius: 4px;
            background: white !important;
            cursor: crosshair;
            margin: 5px auto;
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
        
        /* Estilos de estado */
        .estado-badge {
            font-size: 0.7rem;
            padding: 0.35em 0.65em;
        }
        
        /* Cards de evidencia para Apartado 1 */
        .evidencias-grid-apartado1 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .evidencia-card-osm {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .evidencia-card-osm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        }
        .evidencia-card-osm .preview-container {
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
        }
        .evidencia-card-osm .preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .evidencia-card-osm .preview-container .file-icon {
            font-size: 2.5rem;
        }
        .evidencia-card-osm .card-body {
            padding: 8px;
        }
        .evidencia-card-osm .file-name {
            font-size: 0.7rem;
            color: #495057;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 5px;
        }
        .evidencia-card-osm .btn-group-sm .btn {
            padding: 2px 6px;
            font-size: 0.65rem;
        }
        
        /* Estilo para campo de motivo de devolución */
        .campo-devolucion {
            border-left: 4px solid #ffc107 !important;
            background: #fff8e1 !important;
        }
        .campo-devolucion label {
            color: #856404 !important;
        }
        .campo-devolucion .valor {
            color: #856404 !important;
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
            .evidencias-grid-apartado1 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php 
        // Determinar qué sidebar cargar según el departamento
        $departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
        
        if ($departamento === 'mantenimiento') {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($departamento === 'ti' || $departamento === 'sistemas' || $departamento === 'ti_sistemas') {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (es_usuario_gth()) {
            include __DIR__ . '/../../../includes/sidebar/sidebar_gth.php';
        } else {
            // Para usuarios colaborativos
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        }
        ?>
        
        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- ENCABEZADO -->
                <div class="btn-group-acciones">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="bi bi-clipboard-check"></i> 
                                Orden: <?php echo htmlspecialchars($orden['folio']); ?>
                            </h2>
                            <span class="badge estado-badge 
                                <?php 
                                switch($orden['estado']) {
                                    case 'pendiente_mantenimiento': echo 'bg-warning text-dark'; break;
                                    case 'en_proceso': echo 'bg-info'; break;
                                    case 'pendiente_usuario': echo 'bg-primary'; break;
                                    case 'devuelto': echo 'bg-danger'; break;
                                    case 'completado': echo 'bg-success'; break;
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php 
                                $estados_texto = [
                                    'pendiente_mantenimiento' => 'Pendiente Mantenimiento',
                                    'en_proceso' => 'En Proceso',
                                    'pendiente_usuario' => 'Pendiente Usuario',
                                    'devuelto' => 'Devuelta',
                                    'completado' => 'Completada'
                                ];
                                echo $estados_texto[$orden['estado']] ?? $orden['estado']; 
                                ?>
                            </span>
                        </div>
                        <div class="col-md-6 text-end">
                            <!-- Botón Guardar PDF (solo para órdenes completadas) -->
                            <?php if ($orden['estado'] === 'completado'): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/generar_pdf_orden.php?id=<?php echo $orden_id; ?>" 
                                class="btn btn-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i> Guardar PDF
                                </a>
                            <?php endif; ?>
                            
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
                <!-- APARTADO 1: ORDEN DE SERVICIO PARA MANTENIMIENTO -->
                <!-- ============================================ -->
                <div class="apartado-section">
                    <div class="apartado-header">
                        <h4><i class="bi bi-file-earmark-text"></i> APARTADO 1: Orden de Servicio para Mantenimiento</h4>
                    </div>
                    
                    <?php if ($modo_edicion && $permisos['es_propietario']): ?>
                        <!-- MODO EDICIÓN - Solo propietario -->
                        <form action="<?php echo URL_BASE; ?>ordenes_servicio/procesar_editar_apartado1.php" method="POST">
                            <input type="hidden" name="orden_id" value="<?php echo $orden_id; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Empresa *</label>
                                    <select name="empresa" class="form-select" required>
                                        <option value="RESIMEX" <?php echo ($apartado1['empresa'] ?? '') === 'RESIMEX' ? 'selected' : ''; ?>>RESIMEX</option>
                                        <option value="CARGANOVA" <?php echo ($apartado1['empresa'] ?? '') === 'CARGANOVA' ? 'selected' : ''; ?>>CARGANOVA</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Folio *</label>
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
                            
                            <!-- EVIDENCIAS CON CARDS -->
                            <?php if (!empty($apartado1['evidencia_archivos'])): ?>
                                <div class="col-12">
                                    <div class="campo-visualizacion">
                                        <label><i class="bi bi-images"></i> Evidencia de la Falla</label>
                                        <div class="evidencias-grid-apartado1">
                                            <?php foreach ($apartado1['evidencia_archivos'] as $archivo): 
                                                $ruta_archivo = obtener_ruta_archivo_osm($archivo);
                                                $nombre_original = $archivo['nombre_original'] ?? basename($ruta_archivo);
                                                $es_imagen = es_imagen_osm($nombre_original);
                                            ?>
                                                <div class="evidencia-card-osm">
                                                    <div class="preview-container">
                                                        <?php if ($es_imagen): ?>
                                                            <img src="<?php echo URL_BASE . $ruta_archivo; ?>" 
                                                                 alt="<?php echo htmlspecialchars($nombre_original); ?>">
                                                        <?php else: ?>
                                                            <i class="bi <?php echo obtener_icono_archivo_osm($nombre_original); ?> file-icon"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="file-name" title="<?php echo htmlspecialchars($nombre_original); ?>">
                                                            <?php echo htmlspecialchars($nombre_original); ?>
                                                        </div>
                                                        <div class="btn-group btn-group-sm w-100">
                                                            <a href="<?php echo URL_BASE . $ruta_archivo; ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-primary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="<?php echo URL_BASE . $ruta_archivo; ?>" 
                                                               download="<?php echo htmlspecialchars($nombre_original); ?>" 
                                                               class="btn btn-outline-success">
                                                                <i class="bi bi-download"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- MOTIVO DE DEVOLUCIÓN (solo si existe) -->
                            <?php if (!empty($apartado1['motivo_devolucion'])): ?>
                                <div class="col-12">
                                    <div class="campo-visualizacion campo-devolucion">
                                        <label>
                                            <i class="bi bi-exclamation-triangle"></i> Motivo de Devolución
                                        </label>
                                        <div class="valor">
                                            <?php echo nl2br(htmlspecialchars($apartado1['motivo_devolucion'])); ?>
                                        </div>
                                        <small class="text-muted" style="color: #856404 !important;">
                                            <i class="bi bi-person"></i> Devuelto por: <?php echo htmlspecialchars($apartado1['devuelto_por'] ?? '-'); ?> 
                                            <i class="bi bi-calendar ms-2"></i> <?php echo isset($apartado1['fecha_devolucion']) ? date('d/m/Y H:i', strtotime($apartado1['fecha_devolucion'])) : '-'; ?>
                                        </small>
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
                                               value="<?php echo $apartado2['hora_inicio'] ?? date('H:i'); ?>" required>
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
                                    <div class="col-md-6">
                                        <label class="form-label">Código de Equipo</label>
                                        <input type="text" name="codigo_equipo" class="form-control" 
                                               value="<?php echo htmlspecialchars($apartado2['codigo_equipo'] ?? ''); ?>"
                                               placeholder="Dejar vacío para NA">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Horómetro</label>
                                        <input type="text" name="horometro" class="form-control" 
                                               value="<?php echo htmlspecialchars($apartado2['horometro'] ?? ''); ?>"
                                               placeholder="Dejar vacío para NA">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descripción de Reparación *</label>
                                        <textarea name="descripcion_reparacion" class="form-control" rows="3" required><?php echo htmlspecialchars($apartado2['descripcion_reparacion'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <!-- Personal Asignado -->
                                    <div class="col-12">
                                        <label class="form-label">Personal Asignado</label>
                                        <div id="personal-container">
                                            <?php if (!empty($apartado2['personal_asignado'])): ?>
                                                <?php foreach ($apartado2['personal_asignado'] as $index => $persona): ?>
                                                    <div class="input-group mb-2">
                                                        <input type="text" name="personal_asignado[<?php echo $index; ?>][nombre]" 
                                                               class="form-control" value="<?php echo htmlspecialchars($persona['nombre']); ?>"
                                                               placeholder="Nombre del personal">
                                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="input-group mb-2">
                                                    <input type="text" name="personal_asignado[0][nombre]" class="form-control" placeholder="Nombre del personal">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="agregarPersonal()">
                                            <i class="bi bi-plus-circle"></i> Agregar Personal
                                        </button>
                                    </div>
                                    
                                    <div class="col-12">
                                        <hr>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="procesarApartado2('guardar')">
                                            <i class="bi bi-save"></i> Guardar Borrador
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="procesarApartado2('enviar')">
                                            <i class="bi bi-send"></i> Enviar al Usuario
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- MODO VISUALIZACIÓN -->
                            <?php if (!empty($apartado2)): ?>
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
                                    <div class="col-md-6">
                                        <div class="campo-visualizacion">
                                            <label>Código de Equipo</label>
                                            <div class="valor"><?php 
                                                $codigo_equipo = $apartado2['codigo_equipo'] ?? '';
                                                echo htmlspecialchars($codigo_equipo !== '' ? $codigo_equipo : 'NA'); 
                                            ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="campo-visualizacion">
                                            <label>Horómetro</label>
                                            <div class="valor"><?php 
                                                $horometro = $apartado2['horometro'] ?? '';
                                                echo htmlspecialchars($horometro !== '' ? $horometro : 'NA'); 
                                            ?></div>
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
                                           <?php echo !$permisos['es_propietario'] ? 'readonly' : ''; ?>>
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
                            
                            <!-- Firma del Responsable de Mantenimiento -->
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
    
    <!-- Modal: Motivo de Devolución -->
    <div class="modal fade" id="modalMotivoDevolucion" tabindex="-1" aria-labelledby="modalMotivoDevolucionLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalMotivoDevolucionLabel">
                        <i class="bi bi-arrow-return-left"></i> Motivo de la Devolución
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Por favor, indique el motivo por el cual está devolviendo esta orden a Mantenimiento:</p>
                    <div class="mb-3">
                        <textarea class="form-control" id="motivo_devolucion_texto" rows="4" 
                                  placeholder="Escriba el motivo de la devolución..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-warning" onclick="enviarDevolucion()">
                        <i class="bi bi-send"></i> Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery (necesario para jSignature) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- jSignature para firmas digitales -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jSignature/2.1.3/jSignature.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
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
                // Mostrar modal para capturar el motivo
                const modal = new bootstrap.Modal(document.getElementById('modalMotivoDevolucion'));
                document.getElementById('motivo_devolucion_texto').value = '';
                modal.show();
            }
            
            function enviarDevolucion() {
                const motivo = document.getElementById('motivo_devolucion_texto').value.trim();
                
                if (!motivo) {
                    alert('Por favor, ingrese el motivo de la devolución.');
                    return;
                }
                
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalMotivoDevolucion'));
                modal.hide();
                
                fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_devolver_orden.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        orden_id: <?php echo $orden_id; ?>,
                        motivo: motivo
                    })
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
                
                const nombreSolicitante = document.getElementById('nombre_solicitante').value;
                const nombreResponsable = document.getElementById('nombre_responsable_mant').value;
                
                if (!nombreSolicitante.trim() || !nombreResponsable.trim()) {
                    alert('Por favor, complete los nombres antes de finalizar.');
                    return;
                }
                
                fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_finalizar_orden.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        orden_id: <?php echo $orden_id; ?>,
                        apartado3: {
                            nombre_solicitante: nombreSolicitante,
                            firma_solicitante: 'data:' + firmaSolData[0] + ',' + firmaSolData[1],
                            nombre_responsable_mantenimiento: nombreResponsable,
                            firma_responsable_mantenimiento: 'data:' + firmaMantData[0] + ',' + firmaMantData[1]
                        }
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('¡Orden finalizada exitosamente!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        <?php endif; ?>
        
        // Función para agregar personal (Apartado 2)
        let personalIndex = <?php echo !empty($apartado2['personal_asignado']) ? count($apartado2['personal_asignado']) : 1; ?>;
        
        function agregarPersonal() {
            const container = document.getElementById('personal-container');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" name="personal_asignado[${personalIndex}][nombre]" class="form-control" placeholder="Nombre del personal">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            container.appendChild(div);
            personalIndex++;
        }
        
        // Procesar Apartado 2
        function procesarApartado2(accion) {
            const form = document.getElementById('formApartado2');
            const formData = new FormData(form);
            
            // Construir objeto de datos
            const data = {
                orden_id: <?php echo $orden_id; ?>,
                fecha_atencion: formData.get('fecha_atencion'),
                hora_inicio: formData.get('hora_inicio'),
                fecha_termino: formData.get('fecha_termino'),
                hora_termino: formData.get('hora_termino'),
                codigo_equipo: formData.get('codigo_equipo'),
                horometro: formData.get('horometro'),
                descripcion_reparacion: formData.get('descripcion_reparacion'),
                personal_asignado: []
            };
            
            // Obtener personal asignado
            const personalInputs = document.querySelectorAll('[name^="personal_asignado"]');
            const personalMap = {};
            personalInputs.forEach(input => {
                const match = input.name.match(/personal_asignado\[(\d+)\]\[nombre\]/);
                if (match) {
                    const idx = match[1];
                    personalMap[idx] = { nombre: input.value };
                }
            });
            data.personal_asignado = Object.values(personalMap);
            
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