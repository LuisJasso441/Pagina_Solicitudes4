<?php
/**
 * Modal: Crear Nueva Orden de Servicio
 * Apartado 1 - Para ser llenado por el solicitante
 * ⭐ MODIFICADO - Sin límites de archivos, acepta cualquier tipo
 */
?>

<!-- Modal Crear Orden de Servicio -->
<div class="modal fade" id="modalCrearOrdenServicio" tabindex="-1" aria-labelledby="modalCrearOrdenServicioLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCrearOrdenServicioLabel">
                    <i class="bi bi-plus-circle"></i> Nueva Orden de Servicio para Mantenimiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formNuevaOrdenServicio" enctype="multipart/form-data">
                <div class="modal-body">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Nota:</strong> Los campos marcados con (*) son obligatorios. 
                        Algunos campos se llenan automáticamente con su información.
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-file-text"></i> Información General</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Empresa -->
                                <div class="col-md-6">
                                    <label for="empresa" class="form-label">Empresa *</label>
                                    <select class="form-select" id="empresa" name="empresa" required>
                                        <option value="">Seleccione</option>
                                        <option value="RESIMEX">RESIMEX</option>
                                        <option value="CARGANOVA">CARGANOVA</option>
                                    </select>
                                </div>
                                
                                <!-- Folio -->
                                <div class="col-md-6">
                                    <label for="folio" class="form-label">Folio de Mantenimiento *</label>
                                    <input type="text" class="form-control" id="folio" name="folio" 
                                           placeholder="Ej: OM-2024-001" required>
                                    <div class="form-text">Ingrese un folio único para esta orden</div>
                                </div>
                                
                                <!-- Área Solicitante (auto-completado) -->
                                <div class="col-md-6">
                                    <label for="area_solicitante" class="form-label">Área Solicitante</label>
                                    <input type="text" class="form-control" id="area_solicitante" name="area_solicitante" 
                                           value="<?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?>" readonly>
                                </div>
                                
                                <!-- Fecha de Entrada -->
                                <div class="col-md-3">
                                    <label for="fecha_entrada" class="form-label">Fecha de Entrada *</label>
                                    <input type="date" class="form-control" id="fecha_entrada" name="fecha_entrada" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <!-- Hora de Entrada -->
                                <div class="col-md-3">
                                    <label for="hora_entrada" class="form-label">Hora de Entrada *</label>
                                    <input type="time" class="form-control" id="hora_entrada" name="hora_entrada" 
                                           value="<?php echo date('H:i'); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-gear"></i> Detalles de la Solicitud</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Unidad/Equipo -->
                                <div class="col-md-6">
                                    <label for="unidad_equipo" class="form-label">Unidad/Equipo *</label>
                                    <input type="text" class="form-control" id="unidad_equipo" name="unidad_equipo" 
                                           placeholder="Ej: Camión Torton T-123, Laptop HP ProBook 450" required>
                                    <div class="form-text">Indique el equipo o unidad a dar mantenimiento</div>
                                </div>
                                
                                <!-- Nombre del Solicitante (auto-completado) -->
                                <div class="col-md-6">
                                    <label for="nombre_solicitante" class="form-label">Nombre del Solicitante</label>
                                    <input type="text" class="form-control" id="nombre_solicitante" name="nombre_solicitante" 
                                           value="<?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>" readonly>
                                    <div class="form-text">Su nombre</div>
                                </div>
                                
                                <!-- Prioridad -->
                                <div class="col-md-6">
                                    <label for="prioridad" class="form-label">Prioridad *</label>
                                    <select class="form-select" id="prioridad" name="prioridad" required>
                                        <option value="">Seleccione</option>
                                        <option value="Alta">Alta</option>
                                        <option value="Media">Media</option>
                                        <option value="Baja">Baja</option>
                                        <option value="Normal">Normal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Descripción de la Falla</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Descripción de la Falla -->
                                <div class="col-12">
                                    <label for="descripcion_falla" class="form-label">Descripción de la Falla *</label>
                                    <textarea class="form-control" id="descripcion_falla" name="descripcion_falla" 
                                              rows="4" placeholder="Describa detalladamente la falla o problema..." required></textarea>
                                </div>
                                
                                <!-- Evidencia (Archivos) -->
                                <div class="col-12">
                                    <label for="evidencia_archivos" class="form-label">Evidencia de la Falla (opcional)</label>
                                    <input type="file" class="form-control" id="evidencia_archivos" name="evidencia_archivos[]" 
                                           multiple accept="*/*">
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i>
                                        Puede seleccionar múltiples archivos (imágenes, videos, documentos, etc.)
                                    </div>
                                    
                                    <!-- Lista de archivos seleccionados -->
                                    <div id="listaArchivos" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Área de mensajes -->
                    <div id="mensajeResultado"></div>
                    
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnEnviarOrden">
                        <i class="bi bi-send"></i> Enviar Orden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Mostrar archivos seleccionados
document.getElementById('evidencia_archivos').addEventListener('change', function(e) {
    const listaArchivos = document.getElementById('listaArchivos');
    listaArchivos.innerHTML = '';
    
    if (this.files.length > 0) {
        const ul = document.createElement('ul');
        ul.className = 'list-group';
        
        Array.from(this.files).forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            
            li.innerHTML = `
                <div>
                    <i class="bi bi-file-earmark"></i> 
                    <strong>${file.name}</strong>
                </div>
                <span class="badge bg-secondary">${sizeMB} MB</span>
            `;
            
            ul.appendChild(li);
        });
        
        listaArchivos.appendChild(ul);
    }
});

// Enviar formulario
document.getElementById('formNuevaOrdenServicio').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btnEnviar = document.getElementById('btnEnviarOrden');
    const mensajeDiv = document.getElementById('mensajeResultado');
    
    // Deshabilitar botón
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    
    try {
        // Primero subir archivos si los hay
        let archivosSubidos = [];
        const archivos = document.getElementById('evidencia_archivos').files;
        
        if (archivos.length > 0) {
            const formDataArchivos = new FormData();
            for (let file of archivos) {
                formDataArchivos.append('archivos[]', file);
            }
            
            const responseArchivos = await fetch('<?php echo URL_BASE; ?>ordenes_servicio/upload_evidencia.php', {
                method: 'POST',
                body: formDataArchivos
            });
            
            const dataArchivos = await responseArchivos.json();
            
            if (!dataArchivos.success) {
                throw new Error(dataArchivos.error || 'Error al subir archivos');
            }
            
            archivosSubidos = dataArchivos.archivos;
        }
        
        // Preparar datos del formulario
        const formData = new FormData(this);
        const datos = {
            empresa: formData.get('empresa'),
            folio: formData.get('folio'),
            area_solicitante: formData.get('area_solicitante'),
            fecha_entrada: formData.get('fecha_entrada'),
            hora_entrada: formData.get('hora_entrada'),
            unidad_equipo: formData.get('unidad_equipo'),
            nombre_solicitante: formData.get('nombre_solicitante'),
            prioridad: formData.get('prioridad'),
            descripcion_falla: formData.get('descripcion_falla'),
            evidencia_archivos: archivosSubidos
        };
        
        // Enviar orden
        const response = await fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_crear_orden.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datos)
        });
        
        const data = await response.json();
        
        if (data.success) {
            mensajeDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> 
                    ¡Orden creada exitosamente! Folio: <strong>${datos.folio}</strong>
                </div>
            `;
            
            // Limpiar formulario
            this.reset();
            document.getElementById('listaArchivos').innerHTML = '';
            
            // Cerrar modal después de 2 segundos
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalCrearOrdenServicio')).hide();
                location.reload();
            }, 2000);
            
        } else {
            throw new Error(data.error || 'Error desconocido');
        }
        
    } catch (error) {
        mensajeDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> 
                Error: ${error.message}
            </div>
        `;
    } finally {
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = '<i class="bi bi-send"></i> Enviar Orden';
    }
});

// Limpiar mensajes al cerrar modal
document.getElementById('modalCrearOrdenServicio').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formNuevaOrdenServicio').reset();
    document.getElementById('listaArchivos').innerHTML = '';
    document.getElementById('mensajeResultado').innerHTML = '';
});
</script>

<style>
.modal-xl .card {
    border: 1px solid #dee2e6;
}

.modal-xl .card-header {
    font-weight: 600;
}

.form-text {
    font-size: 0.875rem;
}

#listaArchivos .list-group-item {
    padding: 0.5rem 1rem;
}
</style>