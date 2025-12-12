<?php
/**
 * Formulario del documento colaborativo
 * Muestra Apartado 1 y Apartado 2 con controles de edición según permisos
 * Variables disponibles: $documento, $permisos, $servicio_texto
 * ⭐ ACTUALIZADO - Fecha/Hora recibido arriba de Resumen + Fecha/Hora entrega al final
 */

// Función auxiliar para verificar si es imagen (solo si no existe)
if (!function_exists('es_imagen_ssc')) {
    function es_imagen_ssc($extension) {
        $extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array(strtolower($extension), $extensiones_imagen);
    }
}

// Función para obtener icono según extensión (solo si no existe)
if (!function_exists('obtener_icono_ssc')) {
    function obtener_icono_ssc($extension) {
        $iconos = [
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'doc' => 'bi-file-earmark-word text-primary',
            'docx' => 'bi-file-earmark-word text-primary',
            'xls' => 'bi-file-earmark-excel text-success',
            'xlsx' => 'bi-file-earmark-excel text-success',
            'ppt' => 'bi-file-earmark-ppt text-warning',
            'pptx' => 'bi-file-earmark-ppt text-warning',
            'zip' => 'bi-file-earmark-zip text-secondary',
            'rar' => 'bi-file-earmark-zip text-secondary',
            'mp4' => 'bi-file-earmark-play text-info',
            'avi' => 'bi-file-earmark-play text-info',
            'mov' => 'bi-file-earmark-play text-info',
            'mp3' => 'bi-file-earmark-music text-purple',
            'wav' => 'bi-file-earmark-music text-purple',
            'txt' => 'bi-file-earmark-text text-muted',
        ];
        return $iconos[strtolower($extension)] ?? 'bi-file-earmark text-secondary';
    }
}
?>

<!-- APARTADO 1: Creación (Normatividad/Ventas) -->
<div class="apartado-section <?= $permisos['apartado1'] ? 'editable' : 'bloqueado' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-primary mb-0">
            <i class="bi bi-1-circle-fill"></i> APARTADO 1: Datos de Solicitud
        </h5>
        <?php if ($permisos['apartado1']): ?>
            <span class="badge bg-success">
                <i class="bi bi-pencil"></i> Editable
            </span>
        <?php else: ?>
            <span class="badge bg-secondary">
                <i class="bi bi-lock"></i> Bloqueado
            </span>
        <?php endif; ?>
    </div>
    
    <form id="formApartado1" <?= !$permisos['apartado1'] ? 'style="pointer-events: none;"' : '' ?>>
        <?php 
        require_once __DIR__ . '/../csrf.php';
        if ($permisos['apartado1']) {
            echo campo_csrf();
        }
        ?>
        <input type="hidden" name="documento_id" value="<?= $documento['id'] ?>">
        
        <div class="row">
            <!-- Solicitado por -->
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Solicitado por:</label>
                <input type="text" 
                       class="form-control <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                       name="solicitado_por" 
                       value="<?= htmlspecialchars($documento['solicitado_por']) ?>"
                       <?= !$permisos['apartado1'] ? 'readonly' : 'required' ?>>
            </div>
            
            <!-- Nombre del Cliente -->
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Nombre del Cliente:</label>
                <input type="text" 
                       class="form-control <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                       name="nombre_cliente" 
                       value="<?= htmlspecialchars($documento['nombre_cliente']) ?>"
                       <?= !$permisos['apartado1'] ? 'readonly' : 'required' ?>>
            </div>
            
            <!-- Fecha de solicitud -->
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Fecha de solicitud:</label>
                <input type="text" 
                       class="form-control campo-bloqueado" 
                       value="<?= date('d/m/Y H:i', strtotime($documento['fecha_solicitud'])) ?>" 
                       readonly>
            </div>
            
            <!-- Área o proceso solicitante -->
            <div class="col-12 mb-3">
                <label class="form-label fw-bold">Área o proceso solicitante:</label>
                <input type="text" 
                       class="form-control <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                       name="area_proceso_solicitante" 
                       value="<?= htmlspecialchars($documento['area_proceso_solicitante']) ?>"
                       <?= !$permisos['apartado1'] ? 'readonly' : 'required' ?>>
            </div>
        </div>
        
        <!-- Sección: Detalles del servicio solicitado -->
        <div class="bg-success bg-opacity-10 p-3 mb-3 rounded">
            <h6 class="text-success mb-3 fw-bold">DETALLES DEL SERVICIO SOLICITADO</h6>
            
            <!-- Servicio solicitado -->
            <div class="mb-3">
                <label class="form-label fw-bold">Servicio solicitado:</label>
                
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="servicio_solicitado" 
                                   id="edit_servicio_tratamiento" 
                                   value="tratamiento_agua"
                                   <?= $documento['servicio_solicitado'] == 'tratamiento_agua' ? 'checked' : '' ?>
                                   <?= !$permisos['apartado1'] ? 'disabled' : 'required' ?>>
                            <label class="form-check-label" for="edit_servicio_tratamiento">
                                Tratamiento de agua
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="servicio_solicitado" 
                                   id="edit_servicio_revision" 
                                   value="revision_productos"
                                   <?= $documento['servicio_solicitado'] == 'revision_productos' ? 'checked' : '' ?>
                                   <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="edit_servicio_revision">
                                Revisión de productos químicos
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="servicio_solicitado" 
                                   id="edit_servicio_calibracion" 
                                   value="calibracion_equipos"
                                   <?= $documento['servicio_solicitado'] == 'calibracion_equipos' ? 'checked' : '' ?>
                                   <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="edit_servicio_calibracion">
                                Calibración y/o verificación de equipos
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="servicio_solicitado" 
                                   id="edit_servicio_otro" 
                                   value="otro"
                                   <?= $documento['servicio_solicitado'] == 'otro' ? 'checked' : '' ?>
                                   <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="edit_servicio_otro">
                                Otro. Especifique:
                            </label>
                        </div>
                        <input type="text" 
                               class="form-control mt-2 <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                               id="edit_servicio_otro_especificar" 
                               name="servicio_otro_especificar" 
                               value="<?= htmlspecialchars($documento['servicio_otro_especificar'] ?? '') ?>"
                               placeholder="Describa el servicio solicitado"
                               <?= $documento['servicio_solicitado'] != 'otro' ? 'disabled' : '' ?>
                               <?= !$permisos['apartado1'] ? 'readonly' : '' ?>>
                    </div>
                </div>
            </div>
            
            <!-- Prioridad -->
            <div class="mb-3">
                <label class="form-label fw-bold">Prioridad:</label>
                
                <div class="btn-group w-100" role="group">
                    <input type="radio" 
                           class="btn-check" 
                           name="prioridad" 
                           id="edit_prioridad_baja" 
                           value="baja"
                           <?= $documento['prioridad'] == 'baja' ? 'checked' : '' ?>
                           <?= !$permisos['apartado1'] ? 'disabled' : 'required' ?>>
                    <label class="btn btn-outline-success" for="edit_prioridad_baja">
                        <i class="bi bi-arrow-down-circle"></i> Baja
                    </label>
                    
                    <input type="radio" 
                           class="btn-check" 
                           name="prioridad" 
                           id="edit_prioridad_media" 
                           value="media"
                           <?= $documento['prioridad'] == 'media' ? 'checked' : '' ?>
                           <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                    <label class="btn btn-outline-warning" for="edit_prioridad_media">
                        <i class="bi bi-dash-circle"></i> Media
                    </label>
                    
                    <input type="radio" 
                           class="btn-check" 
                           name="prioridad" 
                           id="edit_prioridad_alta" 
                           value="alta"
                           <?= $documento['prioridad'] == 'alta' ? 'checked' : '' ?>
                           <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                    <label class="btn btn-outline-danger" for="edit_prioridad_alta">
                        <i class="bi bi-arrow-up-circle"></i> Alta
                    </label>
                </div>
            </div>
            
            <!-- Descripción del servicio -->
            <div class="mb-3">
                <label class="form-label fw-bold">Descripción del servicio:</label>
                <textarea class="form-control <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                          name="descripcion_servicio" 
                          rows="5" 
                          <?= !$permisos['apartado1'] ? 'readonly' : 'required' ?>><?= htmlspecialchars($documento['descripcion_servicio']) ?></textarea>
            </div>
        </div>
        
        <!-- Botón guardar Apartado 1 -->
        <?php if ($permisos['apartado1'] && $documento['estado'] != 'completado'): ?>
        <div class="text-end">
            <button type="submit" class="btn btn-primary" id="btnGuardarApartado1">
                <i class="bi bi-save"></i> Guardar Apartado 1
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- APARTADO 2: Seguimiento (Laboratorio) -->
<div class="apartado-section <?= $permisos['apartado2'] ? 'editable' : 'bloqueado' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-primary mb-0">
            <i class="bi bi-2-circle-fill"></i> APARTADO 2: Seguimiento y Resultados
        </h5>
        <?php if ($permisos['apartado2']): ?>
            <span class="badge bg-success">
                <i class="bi bi-pencil"></i> Editable
            </span>
        <?php else: ?>
            <span class="badge bg-secondary">
                <i class="bi bi-lock"></i> Bloqueado
            </span>
        <?php endif; ?>
    </div>
    
    <?php if (empty($documento['recibe_solicitud']) && !$permisos['apartado2']): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Este apartado será completado por el departamento de Laboratorio.
        </div>
    <?php else: ?>
        <!-- FORMULARIO PARA CAMPOS EDITABLES -->
        <form id="formApartado2" enctype="multipart/form-data" <?= !$permisos['apartado2'] ? 'style="pointer-events: none;"' : '' ?>>
            <input type="hidden" name="documento_id" value="<?= $documento['id'] ?>">
            
            <div class="row">
                <!-- Recibe solicitud -->
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Recibe solicitud:</label>
                    <input type="text" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="recibe_solicitud" 
                           value="<?= htmlspecialchars($documento['recibe_solicitud'] ?? $nombre_usuario) ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>>
                </div>
                
                <!-- ⭐ Fecha de recibido (MOVIDO ARRIBA) -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha de recibido:</label>
                    <input type="date" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="fecha_recibido" 
                           value="<?= $documento['fecha_recibido'] ?? '' ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>>
                </div>
                
                <!-- ⭐ Hora de recibido (MOVIDO ARRIBA) -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Hora de recibido:</label>
                    <input type="time" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="hora_recibido" 
                           value="<?= $documento['hora_recibido'] ?? '' ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>>
                </div>
                
                <!-- Resumen de resultados (AHORA DESPUÉS DE FECHA/HORA RECIBIDO) -->
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Resumen de resultados:</label>
                    <textarea class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                              name="resumen_resultados" 
                              rows="6" 
                              placeholder="Describa los resultados obtenidos, análisis realizados y conclusiones..."
                              <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>><?= htmlspecialchars($documento['resumen_resultados'] ?? '') ?></textarea>
                </div>
                
                <!-- Campo para subir archivos (solo Laboratorio) -->
                <?php if ($permisos['apartado2'] && $documento['estado'] != 'completado'): ?>
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Subir archivos (opcional):</label>
                    <input type="file" 
                           class="form-control" 
                           name="archivos_apartado2[]" 
                           id="archivos_apartado2"
                           multiple
                           accept="*/*">
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i>
                        Puede seleccionar múltiples archivos (imágenes, videos, documentos, etc.)
                    </small>
                </div>
                <?php endif; ?>
                
                <!-- ⭐ NUEVO: Fecha de entrega -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha de entrega:</label>
                    <input type="date" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="fecha_entrega" 
                           value="<?= $documento['fecha_entrega'] ?? '' ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : '' ?>>
                </div>
                
                <!-- ⭐ NUEVO: Hora de entrega -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Hora de entrega:</label>
                    <input type="time" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="hora_entrega" 
                           value="<?= $documento['hora_entrega'] ?? '' ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : '' ?>>
                </div>
            </div>
        </form>
        
        <!-- ⭐ ARCHIVOS CARGADOS - ANTES DE LOS BOTONES -->
        <?php if (!empty($documento['archivos_apartado2'])): ?>
            <?php 
            $archivos = json_decode($documento['archivos_apartado2'], true);
            if (is_array($archivos) && count($archivos) > 0): 
            ?>
            <div class="mb-3">
                <h6 class="fw-bold"><i class="bi bi-folder2-open"></i> Archivos adjuntos (<?= count($archivos) ?>)</h6>
                
                <div class="row mt-3">
                    <?php foreach ($archivos as $archivo): ?>
                        <?php 
                        $es_img = es_imagen_ssc($archivo['extension']);
                        $url_archivo = URL_BASE . 'Imagenes_SSC/' . htmlspecialchars($archivo['nombre_archivo']);
                        ?>
                        
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card h-100 shadow-sm archivo-card">
                                <!-- Previsualización -->
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light" 
                                     style="height: 150px; overflow: hidden;">
                                    <?php if ($es_img): ?>
                                        <!-- Si es imagen, mostrar thumbnail -->
                                        <a href="<?= $url_archivo ?>" target="_blank" class="w-100 h-100">
                                            <img src="<?= $url_archivo ?>" 
                                                 alt="<?= htmlspecialchars($archivo['nombre_original']) ?>"
                                                 class="img-fluid"
                                                 style="width: 100%; height: 150px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'bi bi-image text-muted\' style=\'font-size: 4rem;\'></i>';">
                                        </a>
                                    <?php else: ?>
                                        <!-- Si no es imagen, mostrar icono grande -->
                                        <a href="<?= $url_archivo ?>" target="_blank" class="text-decoration-none text-center">
                                            <i class="<?= obtener_icono_ssc($archivo['extension']) ?>" style="font-size: 4rem;"></i>
                                            <div class="mt-2">
                                                <span class="badge bg-secondary"><?= strtoupper($archivo['extension']) ?></span>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Info del archivo (solo nombre) -->
                                <div class="card-body p-2">
                                    <p class="card-text small mb-0 text-truncate" title="<?= htmlspecialchars($archivo['nombre_original']) ?>">
                                        <strong><?= htmlspecialchars($archivo['nombre_original']) ?></strong>
                                    </p>
                                </div>
                                
                                <!-- Botón de descarga/ver -->
                                <div class="card-footer bg-transparent p-2">
                                    <a href="<?= $url_archivo ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary w-100">
                                        <i class="bi bi-<?= $es_img ? 'eye' : 'download' ?>"></i>
                                        <?= $es_img ? 'Ver imagen' : 'Descargar' ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- ⭐ BOTONES AL FINAL -->
        <?php if ($permisos['apartado2'] && $documento['estado'] != 'completado'): ?>
        <div class="text-end">
            <button type="submit" form="formApartado2" class="btn btn-primary me-2" id="btnGuardarApartado2">
                <i class="bi bi-save"></i> Guardar Apartado 2
            </button>
            
            <?php if (!empty($documento['resumen_resultados']) && !empty($documento['fecha_recibido'])): ?>
            <button type="button" class="btn btn-success" id="btnCompletarDocumento" data-documento-id="<?= $documento['id'] ?>">
                <i class="bi bi-check-circle"></i> Completar y Enviar a Base Global
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Información de finalización -->
<?php if ($documento['estado'] == 'completado'): ?>
<div class="alert alert-success">
    <h6 class="alert-heading">
        <i class="bi bi-check-circle-fill"></i> Documento Completado
    </h6>
    <p class="mb-0">
        Este documento fue completado el <strong><?= date('d/m/Y H:i', strtotime($documento['fecha_completado'])) ?></strong>
        y se encuentra en la base global. No se pueden realizar más cambios.
    </p>
</div>
<?php endif; ?>

<!-- Estilos adicionales para los archivos -->
<style>
.archivo-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.archivo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
.archivo-card .card-img-top a {
    display: flex;
    align-items: center;
    justify-content: center;
}
.archivo-card .card-img-top img {
    transition: transform 0.3s ease;
}
.archivo-card:hover .card-img-top img {
    transform: scale(1.05);
}
</style>