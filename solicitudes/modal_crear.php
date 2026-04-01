<?php
/**
 * Modal para crear nueva solicitud
 * Se carga dentro del dashboard
 * 
 * ACTUALIZADO: Campo de evidencia agregado (Imagenes_Tickets)
 * ACTUALIZADO: Campo de nombre del solicitante ahora es editable
 */

// Este archivo se incluye desde el dashboard, por lo que ya tiene acceso a la sesión
?>

<!-- Modal de Nueva Solicitud -->
<div class="modal fade modal-custom" id="modalNuevaSolicitud" tabindex="-1" aria-labelledby="modalNuevaSolicitudLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            
            <!-- Header del Modal -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalNuevaSolicitudLabel">
                    <i class="bi bi-file-earmark-plus"></i> Nueva Solicitud de Atención
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body del Modal -->
            <div class="modal-body">
                
                <!-- Contenedor de alertas -->
                <div id="alertContainer"></div>

                <!-- Formulario -->
                <form id="formNuevaSolicitud" enctype="multipart/form-data">
                    
                    <!-- Nombre del solicitante (campo vacío, el usuario debe escribirlo) -->
                    <div class="mb-3">
                        <label class="form-label required">Nombre del solicitante</label>
                        <input type="text" class="form-control" name="nombre_completo" 
                            placeholder="Escribe el nombre del solicitante"
                            required>
                        <small class="text-muted">Escribe el nombre de quien realiza la solicitud</small>
                    </div>

                    <!-- Departamento (precargado y deshabilitado) -->
                    <div class="mb-3">
                        <label class="form-label required">Departamento</label>
                        <input type="text" class="form-control" name="departamento" 
                               value="<?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?>" 
                               readonly>
                        <small class="text-muted">Tu departamento</small>
                    </div>

                    <!-- Tipo de soporte -->
                    <div class="mb-3">
                        <label for="tipo_soporte" class="form-label required">Tipo de soporte</label>
                        <select class="form-select" id="tipo_soporte" name="tipo_soporte" required>
                            <option value="">Seleccione una opción</option>
                            <option value="Apoyo">Apoyo</option>
                            <option value="Problema">Problema</option>
                        </select>
                    </div>

                    <!-- Tipo de apoyo (condicional) -->
                    <div class="mb-3 conditional-field" id="tipo_apoyo_field">
                        <label for="tipo_apoyo" class="form-label required">Tipo de apoyo</label>
                        <select class="form-select" id="tipo_apoyo" name="tipo_apoyo">
                            <option value="">Seleccione una opción</option>
                            <option value="Préstamo de Herramientas y/o insumos">Préstamo de Herramientas y/o insumos (pinzas, desarmadores, alcohol isopropílico, etc.)</option>
                            <option value="Instalación de Software">Instalación de Software (Autocad, PDF Reader, Zoom, LightShot, etc.)</option>
                            <option value="Solicitud/Actualización de información">Solicitud/Actualización de información</option>
                            <option value="Instalación y/o Configuración de SUA">Instalación y/o Configuración de SUA</option>
                            <option value="Cambios AJUSTES predeterminados">Cambios AJUSTES predeterminados (Solicitud de Outlook, navegador, etc.)</option>
                            <option value="Edición y manipulación de Archivos">Edición y manipulación de Archivos</option>
                            <option value="Copia de Archivos en CD/DVD/USB">Copia de Archivos en CD/DVD/USB</option>
                            <option value="Revisión de contenido">Revisión de contenido (enlaces, correos, archivos, etc.)</option>
                            <option value="Impresión a color">Impresión a color</option>
                            <option value="Reemplazo de Pilas">Reemplazo de Pilas</option>
                            <option value="Preparar sala de Juntas">Preparar sala de Juntas</option>
                            <option value="Activación OFFICE 365">Activación OFFICE 365</option>
                            <option value="Recuperación de correos">Recuperación de correos - fuera de año en curso</option>
                            <option value="Asignación de Equipos">Asignación de Equipos (Teléfono Red, PC, Laptops, USB, etc.)</option>
                            <option value="Acceso a contenido web no visible">Acceso a contenido web no visible</option>
                            <option value="Acceso a carpetas compartidas">Acceso a carpetas compartidas en servidor</option>
                            <option value="Ayuda en archivos de Excel">Ayuda en archivos de Excel</option>
                            <option value="Conflictos con correo electrónico">Conflictos con correo electrónico</option>
                            <option value="Revisión de grabaciones de cámaras de seguridad">Revisión de grabaciones de cámaras de seguridad</option>
                        </select>
                    </div>

                    <!-- Tipo de problema (condicional) -->
                    <div class="mb-3 conditional-field" id="tipo_problema_field">
                        <label for="tipo_problema" class="form-label required">Tipo de problema</label>
                        <select class="form-select" id="tipo_problema" name="tipo_problema">
                            <option value="">Seleccione una opción</option>
                            <option value="No puedo imprimir">No puedo imprimir</option>
                            <option value="Acceso a carpetas compartidas">Acceso a carpetas compartidas en servidor</option>
                            <option value="No puedo acceder al correo">No puedo acceder al correo</option>
                            <option value="Error de Red - Sin internet">Error de Red - Sin internet</option>
                            <option value="Cambio de toner">Cambio de toner</option>
                            <option value="Revisión de teléfono RED">Revisión de teléfono RED - Empresarial</option>
                            <option value="Error en archivo">Error en archivo (Excel, Word, Power Point, PDF, etc)</option>
                            <option value="Solicitud de Respaldo">Solicitud de Respaldo (Correos, PC, Dispositivos, etc.)</option>
                            <option value="PC Bloqueada, error de acceso">PC Bloqueada, error de acceso</option>
                            <option value="Revisión de hardware">Revisión de hardware (PC, cámaras CCTV, impresoras, accesorios)</option>
                        </select>
                    </div>

                    <!-- ========================================== -->
                    <!-- NUEVO: Campo de Evidencia del Error -->
                    <!-- ========================================== -->
                    <div class="mb-3">
                        <label for="evidencia_error" class="form-label">
                            <i class="bi bi-image"></i> Evidencia del error (si aplica)
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="evidencia_error" 
                               name="evidencia_error[]" 
                               multiple
                               accept="image/*,.pdf,.doc,.docx,.txt">
                        <small class="text-muted">
                            Opcional. Puedes adjuntar capturas de pantalla o archivos relacionados con el error. 
                            Formatos permitidos: Imágenes (JPG, PNG, GIF), PDF, DOC, DOCX, TXT. Máximo 5 archivos, 10MB cada uno.
                        </small>
                        
                        <!-- Vista previa de archivos seleccionados -->
                        <div id="previewArchivos" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-3">
                        <label for="descripcion" class="form-label required">Descripción detallada</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="4" placeholder="Describe con detalle tu solicitud..." required></textarea>
                        <small class="text-muted">Proporciona toda la información necesaria</small>
                    </div>

                    <!-- Prioridad -->
                    <div class="mb-4">
                        <label class="form-label required">Nivel de prioridad</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="prioridad" 
                                   id="prioridad_critica" value="critica" required>
                            <label class="form-check-label" for="prioridad_critica">
                                <strong class="text-danger">Crítica</strong> - Urgente, bloquea operaciones
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="prioridad" 
                                   id="prioridad_alta" value="alta">
                            <label class="form-check-label" for="prioridad_alta">
                                <strong class="text-warning">Alta</strong> - Importante, afecta trabajo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="prioridad" 
                                   id="prioridad_media" value="media" checked>
                            <label class="form-check-label" for="prioridad_media">
                                <strong class="text-info">Media</strong> - Normal, sin urgencia
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="prioridad" 
                                   id="prioridad_baja" value="baja">
                            <label class="form-check-label" for="prioridad_baja">
                                <strong class="text-secondary">Baja</strong> - Puede esperar
                            </label>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Footer del Modal -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-form-cancel" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="button" class="btn btn-form-submit" id="btnEnviarSolicitud">
                    <i class="bi bi-send"></i> Enviar Solicitud
                </button>
            </div>

        </div>
    </div>
</div>

<script>
// ====================================
// LÓGICA DEL FORMULARIO DE SOLICITUD
// ====================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Lógica condicional para tipo de soporte
    const tipoSoporteSelect = document.getElementById('tipo_soporte');
    const tipoApoyoField = document.getElementById('tipo_apoyo_field');
    const tipoProblemaField = document.getElementById('tipo_problema_field');
    const tipoApoyoSelect = document.getElementById('tipo_apoyo');
    const tipoProblemaSelect = document.getElementById('tipo_problema');
    
    tipoSoporteSelect.addEventListener('change', function() {
        // Ocultar ambos campos
        tipoApoyoField.style.display = 'none';
        tipoProblemaField.style.display = 'none';
        
        // Quitar required
        tipoApoyoSelect.removeAttribute('required');
        tipoProblemaSelect.removeAttribute('required');
        
        // Limpiar valores
        tipoApoyoSelect.value = '';
        tipoProblemaSelect.value = '';
        
        // Mostrar según selección
        if (this.value === 'Apoyo') {
            tipoApoyoField.style.display = 'block';
            tipoApoyoSelect.setAttribute('required', 'required');
        } else if (this.value === 'Problema') {
            tipoProblemaField.style.display = 'block';
            tipoProblemaSelect.setAttribute('required', 'required');
        }
    });
    
    // ====================================
    // Vista previa de archivos seleccionados
    // ====================================
    const inputEvidencia = document.getElementById('evidencia_error');
    const previewContainer = document.getElementById('previewArchivos');
    
    inputEvidencia.addEventListener('change', function() {
        previewContainer.innerHTML = '';
        
        if (this.files.length > 5) {
            mostrarAlerta('warning', 'Solo se permiten máximo 5 archivos. Se tomarán los primeros 5.');
        }
        
        const archivos = Array.from(this.files).slice(0, 5);
        
        archivos.forEach((file, index) => {
            // Validar tamaño (10MB máximo)
            if (file.size > 10 * 1024 * 1024) {
                mostrarAlerta('warning', `El archivo "${file.name}" excede el límite de 10MB y será ignorado.`);
                return;
            }
            
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary d-flex align-items-center gap-1';
            badge.style.fontSize = '0.85rem';
            badge.style.padding = '0.5rem 0.75rem';
            
            // Icono según tipo de archivo
            let icono = 'bi-file-earmark';
            if (file.type.startsWith('image/')) icono = 'bi-file-image';
            else if (file.type === 'application/pdf') icono = 'bi-file-pdf';
            else if (file.type.includes('word')) icono = 'bi-file-word';
            
            badge.innerHTML = `
                <i class="bi ${icono}"></i>
                <span>${file.name.length > 20 ? file.name.substring(0, 17) + '...' : file.name}</span>
                <small class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</small>
            `;
            
            previewContainer.appendChild(badge);
        });
    });
    
    // ====================================
    // Enviar formulario con AJAX
    // ====================================
    document.getElementById('btnEnviarSolicitud').addEventListener('click', function() {
        const form = document.getElementById('formNuevaSolicitud');
        
        // Validar formulario
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Deshabilitar botón
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
        
        // Preparar datos (incluyendo archivos)
        const formData = new FormData(form);
        
        // Enviar con AJAX
        fetch('<?php echo URL_BASE; ?>solicitudes/procesar_crear.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar alerta de éxito
                mostrarAlerta('success', data.message);
                
                // Limpiar formulario
                form.reset();
                previewContainer.innerHTML = '';
                tipoApoyoField.style.display = 'none';
                tipoProblemaField.style.display = 'none';
                
                // Cerrar modal después de 2 segundos
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevaSolicitud')).hide();
                    
                    // Recargar página para actualizar lista
                    location.reload();
                }, 2000);
                
            } else {
                // Mostrar error
                mostrarAlerta('danger', data.message);
                
                // Rehabilitar botón
                document.getElementById('btnEnviarSolicitud').disabled = false;
                document.getElementById('btnEnviarSolicitud').innerHTML = '<i class="bi bi-send"></i> Enviar Solicitud';
            }
        })
        .catch(error => {
            mostrarAlerta('danger', 'Error al enviar la solicitud. Intenta nuevamente.');
            console.error('Error:', error);
            
            // Rehabilitar botón
            document.getElementById('btnEnviarSolicitud').disabled = false;
            document.getElementById('btnEnviarSolicitud').innerHTML = '<i class="bi bi-send"></i> Enviar Solicitud';
        });
    });
    
    // Función para mostrar alertas dentro del modal
    function mostrarAlerta(tipo, mensaje) {
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Auto-ocultar después de 5 segundos si es éxito
        if (tipo === 'success') {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
    }
    
});
</script>

<style>
/* Estilos adicionales para el campo de evidencia */
#previewArchivos .badge {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#previewArchivos .badge i {
    font-size: 1rem;
}

/* Estilo para el input de archivos */
#evidencia_error {
    border: 2px dashed #dee2e6;
    padding: 0.75rem;
    border-radius: 0.375rem;
    transition: border-color 0.2s ease;
}

#evidencia_error:hover {
    border-color: #0d6efd;
}

#evidencia_error:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
</style>