<?php
/**
 * Modal para crear nuevo documento colaborativo SSC
 */

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar funciones CSRF
require_once __DIR__ . '/csrf.php';
?>

<!-- Modal: Nuevo Documento Colaborativo -->
<div class="modal fade" id="modalNuevoDocumento" tabindex="-1" aria-labelledby="modalNuevoDocumentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevoDocumentoLabel">
                    <i class="bi bi-file-earmark-plus"></i>
                    Nuevo Documento: Solicitud de Servicio a Clientes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formNuevoDocumento" action="/Pagina_Solicitudes4/documentos/procesar_crear.php" method="POST">
                <?php echo campo_csrf(); ?>
                
                <div class="modal-body">
                    <!-- Logo y título del formulario -->
                    <div class="text-center mb-4 pb-3 border-bottom">
                        <h5 class="text-success fw-bold">GrupoVerden</h5>
                        <p class="text-muted mb-0">SOLICITUD DE SERVICIO A CLIENTES</p>
                    </div>
                    
                    <!-- APARTADO 1: Creación -->
                    <div class="bg-light p-3 mb-3">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-1-circle-fill"></i> APARTADO 1: Datos de Solicitud
                        </h6>
                        
                        <div class="row">
                            <!-- Solicitado por -->
                            <div class="col-md-6 mb-3">
                                <label for="solicitado_por" class="form-label">
                                    Solicitado por <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="solicitado_por" 
                                       name="solicitado_por" 
                                       value="<?= htmlspecialchars($nombre_usuario ?? '') ?>"
                                       required>
                            </div>
                            
                            <!-- Fecha de solicitud (automática) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de solicitud</label>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= date('d/m/Y H:i') ?>" 
                                       disabled>
                                <small class="form-text text-muted">Se registrará automáticamente</small>
                            </div>
                            
                            <!-- Área o proceso solicitante -->
                            <div class="col-12 mb-3">
                                <label for="area_proceso_solicitante" class="form-label">
                                    Área o proceso solicitante <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="area_proceso_solicitante" 
                                       name="area_proceso_solicitante" 
                                       placeholder="Ej: Área de Ventas Norte, Departamento de Calidad, etc."
                                       required>
                            </div>
                        </div>
                        
                        <!-- Servicio solicitado -->
                        <div class="mb-3">
                            <label class="form-label d-block">
                                Servicio solicitado <span class="text-danger">*</span>
                            </label>
                            
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="servicio_solicitado" 
                                               id="servicio_tratamiento" 
                                               value="tratamiento_agua"
                                               required>
                                        <label class="form-check-label" for="servicio_tratamiento">
                                            Tratamiento de agua
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="servicio_solicitado" 
                                               id="servicio_evaluacion" 
                                               value="evaluacion_productos">
                                        <label class="form-check-label" for="servicio_evaluacion">
                                            Evaluación de productos químicos
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="servicio_solicitado" 
                                               id="servicio_calibracion" 
                                               value="calibracion_equipos">
                                        <label class="form-check-label" for="servicio_calibracion">
                                            Calibración y/o verificación de equipos
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="servicio_solicitado" 
                                               id="servicio_otro" 
                                               value="otro">
                                        <label class="form-check-label" for="servicio_otro">
                                            Otro. Especifique:
                                        </label>
                                    </div>
                                    <input type="text" 
                                           class="form-control mt-2" 
                                           id="servicio_otro_especificar" 
                                           name="servicio_otro_especificar" 
                                           placeholder="Describa el servicio solicitado"
                                           disabled>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Prioridad -->
                        <div class="mb-3">
                            <label class="form-label d-block">
                                Prioridad <span class="text-danger">*</span>
                            </label>
                            
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="prioridad" id="prioridad_baja" value="baja" required>
                                <label class="btn btn-outline-success" for="prioridad_baja">
                                    <i class="bi bi-arrow-down-circle"></i> Baja
                                </label>
                                
                                <input type="radio" class="btn-check" name="prioridad" id="prioridad_media" value="media" checked>
                                <label class="btn btn-outline-warning" for="prioridad_media">
                                    <i class="bi bi-dash-circle"></i> Media
                                </label>
                                
                                <input type="radio" class="btn-check" name="prioridad" id="prioridad_alta" value="alta">
                                <label class="btn btn-outline-danger" for="prioridad_alta">
                                    <i class="bi bi-arrow-up-circle"></i> Alta
                                </label>
                            </div>
                        </div>
                        
                        <!-- Descripción del servicio -->
                        <div class="mb-3">
                            <label for="descripcion_servicio" class="form-label">
                                Descripción del servicio <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="descripcion_servicio" 
                                      name="descripcion_servicio" 
                                      rows="4" 
                                      placeholder="Describa detalladamente el servicio que requiere..."
                                      required></textarea>
                            <small class="form-text text-muted">
                                Sea lo más específico posible para agilizar el proceso
                            </small>
                        </div>
                    </div>
                    
                    <!-- Información del Apartado 2 (solo visual) -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Apartado 2</strong> será completado por el departamento de Laboratorio una vez que reciban y procesen su solicitud.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarDocumento">
                        <i class="bi bi-save"></i> Crear Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para habilitar campo "Otro" cuando se selecciona
document.addEventListener('DOMContentLoaded', function() {
    const servicioOtro = document.getElementById('servicio_otro');
    const servicioOtroEspecificar = document.getElementById('servicio_otro_especificar');
    const radiosServicio = document.querySelectorAll('input[name="servicio_solicitado"]');
    
    radiosServicio.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'otro') {
                servicioOtroEspecificar.disabled = false;
                servicioOtroEspecificar.required = true;
                servicioOtroEspecificar.focus();
            } else {
                servicioOtroEspecificar.disabled = true;
                servicioOtroEspecificar.required = false;
                servicioOtroEspecificar.value = '';
            }
        });
    });
    
    // Manejo del formulario
    const form = document.getElementById('formNuevoDocumento');
    const btnGuardar = document.getElementById('btnGuardarDocumento');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validar que si eligió "otro", haya especificado
        const servicioSeleccionado = document.querySelector('input[name="servicio_solicitado"]:checked');
        if (servicioSeleccionado && servicioSeleccionado.value === 'otro') {
            if (!servicioOtroEspecificar.value.trim()) {
                alert('Por favor especifique el servicio solicitado');
                servicioOtroEspecificar.focus();
                return;
            }
        }
        
        // Deshabilitar botón para evitar doble clic
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        
        // Enviar formulario
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('modalNuevoDocumento')).hide();
                
                // Mostrar mensaje de éxito
                mostrarAlerta('success', data.message, 'Folio: ' + data.folio);
                
                // Recargar página después de 2 segundos
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                mostrarAlerta('danger', 'Error', data.message);
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="bi bi-save"></i> Crear Documento';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarAlerta('danger', 'Error', 'Error al procesar la solicitud');
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="bi bi-save"></i> Crear Documento';
        });
    });
});

// Función para mostrar alertas
function mostrarAlerta(tipo, titulo, mensaje) {
    const alertaHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
             style="z-index: 9999; min-width: 300px;" role="alert">
            <strong>${titulo}</strong> ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertaHTML);
    
    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        const alerta = document.querySelector('.alert');
        if (alerta) {
            alerta.remove();
        }
    }, 5000);
}
</script>