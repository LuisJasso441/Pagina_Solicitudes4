<?php
/**
 * Formulario del documento colaborativo
 * Muestra Apartado 1 y Apartado 2 con controles de edición según permisos
 * Variables disponibles: $documento, $permisos, $servicio_texto
 */
?>

<!-- Logo y encabezado del formulario -->
<div class="text-center mb-4 pb-3 border-bottom">
    <h4 class="text-success fw-bold mb-2">
        <i class="bi bi-building"></i> GrupoVerden
    </h4>
    <h5 class="text-primary">SOLICITUD DE SERVICIO A CLIENTES</h5>
    <p class="text-muted mb-0">Folio: <strong><?= htmlspecialchars($documento['folio']) ?></strong></p>
</div>

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
        require_once __DIR__ . '/csrf.php';
        if ($permisos['apartado1']) {
            echo campo_csrf();
        }
        ?>
        <input type="hidden" name="documento_id" value="<?= $documento['id'] ?>">
        
        <div class="row">
            <!-- Solicitado por -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Solicitado por:</label>
                <input type="text" 
                       class="form-control <?= !$permisos['apartado1'] ? 'campo-bloqueado' : '' ?>" 
                       name="solicitado_por" 
                       value="<?= htmlspecialchars($documento['solicitado_por']) ?>"
                       <?= !$permisos['apartado1'] ? 'readonly' : 'required' ?>>
            </div>
            
            <!-- Fecha de solicitud -->
            <div class="col-md-6 mb-3">
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
                                   id="edit_servicio_evaluacion" 
                                   value="evaluacion_productos"
                                   <?= $documento['servicio_solicitado'] == 'evaluacion_productos' ? 'checked' : '' ?>
                                   <?= !$permisos['apartado1'] ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="edit_servicio_evaluacion">
                                Evaluación de productos químicos
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
        <form id="formApartado2" <?= !$permisos['apartado2'] ? 'style="pointer-events: none;"' : '' ?>>
            <input type="hidden" name="documento_id" value="<?= $documento['id'] ?>">
            
            <div class="row">
                <!-- Recibe solicitud -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Recibe solicitud:</label>
                    <input type="text" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="recibe_solicitud" 
                           value="<?= htmlspecialchars($documento['recibe_solicitud'] ?? $nombre_usuario) ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>>
                </div>
                
                <!-- Fecha y hora de recibido -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha y hora de recibido:</label>
                    <input type="text" 
                           class="form-control campo-bloqueado" 
                           value="<?= $documento['fecha_hora_recibido'] ? date('d/m/Y H:i', strtotime($documento['fecha_hora_recibido'])) : 'Se registrará automáticamente' ?>" 
                           readonly>
                </div>
                
                <!-- Resumen de resultados -->
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Resumen de resultados:</label>
                    <textarea class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                              name="resumen_resultados" 
                              rows="6" 
                              placeholder="Describa los resultados obtenidos, análisis realizados y conclusiones..."
                              <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>><?= htmlspecialchars($documento['resumen_resultados'] ?? '') ?></textarea>
                </div>
                
                <!-- Fecha y hora de entrega -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Fecha y hora de entrega:</label>
                    <input type="datetime-local" 
                           class="form-control <?= !$permisos['apartado2'] ? 'campo-bloqueado' : '' ?>" 
                           name="fecha_hora_entrega" 
                           value="<?= $documento['fecha_hora_entrega'] ? date('Y-m-d\TH:i', strtotime($documento['fecha_hora_entrega'])) : '' ?>"
                           <?= !$permisos['apartado2'] ? 'readonly' : 'required' ?>>
                </div>
            </div>
            
            <!-- Botones Apartado 2 -->
            <?php if ($permisos['apartado2'] && $documento['estado'] != 'completado'): ?>
            <div class="text-end">
                <button type="submit" class="btn btn-primary me-2" id="btnGuardarApartado2">
                    <i class="bi bi-save"></i> Guardar Apartado 2
                </button>
                
                <?php if (!empty($documento['resumen_resultados']) && !empty($documento['fecha_hora_entrega'])): ?>
                <button type="button" class="btn btn-success" id="btnCompletarDocumento" data-documento-id="<?= $documento['id'] ?>">
                    <i class="bi bi-check-circle"></i> Completar y Enviar a Base Global
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
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