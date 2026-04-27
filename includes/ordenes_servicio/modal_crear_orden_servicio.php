<?php
/**
 * Modal: Crear Nueva Orden de Servicio
 * Apartado 1 - Para ser llenado por el solicitante
 * ⭐ MODIFICADO - Sin límites de archivos, acepta cualquier tipo
 * ⭐ VALIDACIÓN DE PERMISOS - Solo se muestra si el usuario tiene permiso de creador OSM
 * ⭐ CIERRE AUTOMÁTICO - Se cierra después de 20 minutos
 * ⭐ CÓDIGO ENCAPSULADO EN IIFE - Evita conflictos de variables globales
 */

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================
// VERIFICAR PERMISOS OSM - CREADOR
// ========================================
$puede_crear_osm_modal = false;

// Verificar que no sea Mantenimiento (ellos no crean, solo procesan)
$departamento_codigo_modal = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if ($departamento_codigo_modal !== 'mantenimiento') {
    try {
        require_once __DIR__ . '/../../config/database.php';
        $pdo_modal = conectarDB();
        $stmt_permisos = $pdo_modal->prepare("
            SELECT creador FROM permisos_osm WHERE user_id = :user_id
        ");
        $stmt_permisos->execute([':user_id' => $_SESSION['usuario_id']]);
        $permisos_osm = $stmt_permisos->fetch(PDO::FETCH_ASSOC);
        
        if ($permisos_osm && $permisos_osm['creador'] == 1) {
            $puede_crear_osm_modal = true;
        }
    } catch (Exception $e) {
        error_log("Error verificando permisos OSM en modal: " . $e->getMessage());
        $puede_crear_osm_modal = false;
    }
}

// Solo mostrar el modal si tiene permisos
if ($puede_crear_osm_modal):
?>

<!-- Modal Crear Orden de Servicio -->
<div class="modal fade" id="modalCrearOrdenServicio" tabindex="-1" aria-labelledby="modalCrearOrdenServicioLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCrearOrdenServicioLabel">
                    <i class="bi bi-plus-circle"></i> Nueva Orden de Servicio para Mantenimiento
                </h5>
                <!-- Indicador de tiempo restante -->
                <div id="tiempoRestanteContainerOSM" class="me-3" style="display: none;">
                    <span class="badge bg-warning text-dark" id="tiempoRestanteBadgeOSM">
                        <i class="bi bi-clock"></i> <span id="tiempoRestanteTextoOSM">20:00</span>
                    </span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formNuevaOrdenServicio" enctype="multipart/form-data">
                <div class="modal-body">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Nota:</strong> Los campos marcados con (*) son obligatorios. 
                        Algunos campos se llenan automáticamente con su información.
                    </div>
                    
                    <!-- Alerta de tiempo (oculta por defecto) -->
                    <div class="alert alert-warning d-none" id="alertaTiempoModalOSM">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>¡Atención!</strong> Este formulario se cerrará automáticamente en 
                        <span id="segundosRestantesOSM">120</span> segundos. 
                        Por favor, complete y envíe su orden.
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
                                           placeholder="Ej: Tracto rojo, instalación de tuberías, etc." required>
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
                                    <div id="listaArchivosOSM" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="mensajeResultadoOSM"></div>
                    
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnEnviarOrdenOSM">
                        <i class="bi bi-send"></i> Enviar Orden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ========================================
// ENCAPSULADO EN IIFE PARA EVITAR CONFLICTOS DE VARIABLES GLOBALES
// ========================================
(function() {
    'use strict';
    
    // ========================================
    // CONSTANTES DE TIEMPO (LOCALES AL SCOPE)
    // ========================================
    var TIEMPO_MAXIMO = 20 * 60 * 1000;      // 20 minutos en milisegundos
    var TIEMPO_ADVERTENCIA = 2 * 60 * 1000;  // 2 minutos antes de cerrar
    
    // Variables de estado (locales al scope)
    var timerModal = null;
    var timerAdvertencia = null;
    var timerContador = null;
    var tiempoInicio = null;
    
    // Referencias a elementos DOM
    var modalElement = document.getElementById('modalCrearOrdenServicio');
    var formElement = document.getElementById('formNuevaOrdenServicio');
    var evidenciaInput = document.getElementById('evidencia_archivos');
    var listaArchivos = document.getElementById('listaArchivosOSM');
    var mensajeDiv = document.getElementById('mensajeResultadoOSM');
    var btnEnviar = document.getElementById('btnEnviarOrdenOSM');
    
    // Verificar que los elementos existan
    if (!modalElement || !formElement) {
        console.warn('Modal OSM: Elementos no encontrados');
        return;
    }
    
    // ========================================
    // FUNCIONES DE TIMER
    // ========================================
    function formatearTiempo(milisegundos) {
        var totalSegundos = Math.floor(milisegundos / 1000);
        var minutos = Math.floor(totalSegundos / 60);
        var segundos = totalSegundos % 60;
        return (minutos < 10 ? '0' : '') + minutos + ':' + (segundos < 10 ? '0' : '') + segundos;
    }
    
    function actualizarContador() {
        if (!tiempoInicio) return;
        
        var tiempoTranscurrido = Date.now() - tiempoInicio;
        var tiempoRestante = TIEMPO_MAXIMO - tiempoTranscurrido;
        
        if (tiempoRestante <= 0) {
            cerrarModalPorTiempo();
            return;
        }
        
        var tiempoTexto = document.getElementById('tiempoRestanteTextoOSM');
        var tiempoBadge = document.getElementById('tiempoRestanteBadgeOSM');
        
        if (tiempoTexto) {
            tiempoTexto.textContent = formatearTiempo(tiempoRestante);
        }
        
        if (tiempoBadge) {
            if (tiempoRestante <= TIEMPO_ADVERTENCIA) {
                tiempoBadge.className = 'badge bg-danger';
            } else if (tiempoRestante <= 5 * 60 * 1000) {
                tiempoBadge.className = 'badge bg-warning text-dark';
            } else {
                tiempoBadge.className = 'badge bg-info';
            }
        }
        
        var segundosEl = document.getElementById('segundosRestantesOSM');
        if (segundosEl && tiempoRestante <= TIEMPO_ADVERTENCIA) {
            segundosEl.textContent = Math.floor(tiempoRestante / 1000);
        }
    }
    
    function mostrarAdvertencia() {
        var alerta = document.getElementById('alertaTiempoModalOSM');
        if (alerta) {
            alerta.classList.remove('d-none');
            var modalBody = modalElement.querySelector('.modal-body');
            if (modalBody) {
                modalBody.scrollTop = 0;
            }
        }
    }
    
    function cerrarModalPorTiempo() {
        if (mensajeDiv) {
            mensajeDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-clock-history"></i> El formulario se ha cerrado automáticamente por tiempo de inactividad (20 minutos).</div>';
        }
        
        setTimeout(function() {
            var modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
        }, 1500);
        
        limpiarTimers();
    }
    
    function iniciarTimers() {
        limpiarTimers();
        
        tiempoInicio = Date.now();
        
        var tiempoContainer = document.getElementById('tiempoRestanteContainerOSM');
        if (tiempoContainer) {
            tiempoContainer.style.display = 'block';
        }
        
        timerModal = setTimeout(cerrarModalPorTiempo, TIEMPO_MAXIMO);
        timerAdvertencia = setTimeout(mostrarAdvertencia, TIEMPO_MAXIMO - TIEMPO_ADVERTENCIA);
        timerContador = setInterval(actualizarContador, 1000);
        
        actualizarContador();
    }
    
    function limpiarTimers() {
        if (timerModal) {
            clearTimeout(timerModal);
            timerModal = null;
        }
        if (timerAdvertencia) {
            clearTimeout(timerAdvertencia);
            timerAdvertencia = null;
        }
        if (timerContador) {
            clearInterval(timerContador);
            timerContador = null;
        }
        
        tiempoInicio = null;
        
        var tiempoContainer = document.getElementById('tiempoRestanteContainerOSM');
        if (tiempoContainer) {
            tiempoContainer.style.display = 'none';
        }
        
        var alerta = document.getElementById('alertaTiempoModalOSM');
        if (alerta) {
            alerta.classList.add('d-none');
        }
    }
    
    // ========================================
    // EVENTOS DEL MODAL
    // ========================================
    modalElement.addEventListener('shown.bs.modal', function() {
        iniciarTimers();
        
        // Actualizar fecha y hora actuales
        var fechaInput = document.getElementById('fecha_entrada');
        var horaInput = document.getElementById('hora_entrada');
        if (fechaInput) fechaInput.value = new Date().toISOString().split('T')[0];
        if (horaInput) horaInput.value = new Date().toTimeString().slice(0, 5);
    });
    
    modalElement.addEventListener('hidden.bs.modal', function() {
        limpiarTimers();
        
        if (formElement) formElement.reset();
        if (listaArchivos) listaArchivos.innerHTML = '';
        if (mensajeDiv) mensajeDiv.innerHTML = '';
        
        var tiempoBadge = document.getElementById('tiempoRestanteBadgeOSM');
        if (tiempoBadge) {
            tiempoBadge.className = 'badge bg-warning text-dark';
        }
    });
    
    // ========================================
    // MOSTRAR ARCHIVOS SELECCIONADOS
    // ========================================
    if (evidenciaInput && listaArchivos) {
        evidenciaInput.addEventListener('change', function() {
            listaArchivos.innerHTML = '';
            
            if (this.files.length > 0) {
                var ul = document.createElement('ul');
                ul.className = 'list-group';
                
                for (var i = 0; i < this.files.length; i++) {
                    var file = this.files[i];
                    var li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    
                    var sizeMB = (file.size / 1024 / 1024).toFixed(2);
                    
                    li.innerHTML = '<div><i class="bi bi-file-earmark"></i> <strong>' + file.name + '</strong></div><span class="badge bg-secondary">' + sizeMB + ' MB</span>';
                    
                    ul.appendChild(li);
                }
                
                listaArchivos.appendChild(ul);
            }
        });
    }
    
    // ========================================
    // ENVIAR FORMULARIO
    // ========================================
    if (formElement && btnEnviar) {
        formElement.addEventListener('submit', function(e) {
            e.preventDefault();
            
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
            
            // Función async para manejar el envío
            (async function() {
                try {
                    var archivosSubidos = [];
                    var archivos = evidenciaInput ? evidenciaInput.files : [];
                    
                    if (archivos.length > 0) {
                        var formDataArchivos = new FormData();
                        for (var i = 0; i < archivos.length; i++) {
                            formDataArchivos.append('archivos[]', archivos[i]);
                        }
                        
                        var responseArchivos = await fetch('<?php echo URL_BASE; ?>ordenes_servicio/upload_evidencia.php', {
                            method: 'POST',
                            body: formDataArchivos
                        });
                        
                        var dataArchivos = await responseArchivos.json();
                        
                        if (!dataArchivos.success) {
                            throw new Error(dataArchivos.error || 'Error al subir archivos');
                        }
                        
                        archivosSubidos = dataArchivos.archivos;
                    }
                    
                    var formData = new FormData(formElement);
                    var datos = {
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
                    
                    var response = await fetch('<?php echo URL_BASE; ?>ordenes_servicio/procesar_crear_orden.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(datos)
                    });
                    
                    var data = await response.json();
                    
                    if (data.success) {
                        limpiarTimers();
                        
                        if (mensajeDiv) {
                            mensajeDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ¡Orden creada exitosamente! Folio: <strong>' + datos.folio + '</strong></div>';
                        }
                        
                        formElement.reset();
                        if (listaArchivos) listaArchivos.innerHTML = '';
                        
                        setTimeout(function() {
                            var modalInstance = bootstrap.Modal.getInstance(modalElement);
                            if (modalInstance) modalInstance.hide();
                            location.reload();
                        }, 2000);
                        
                    } else {
                        throw new Error(data.error || 'Error desconocido');
                    }
                    
                } catch (error) {
                    if (mensajeDiv) {
                        mensajeDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error: ' + error.message + '</div>';
                    }
                } finally {
                    btnEnviar.disabled = false;
                    btnEnviar.innerHTML = '<i class="bi bi-send"></i> Enviar Orden';
                }
            })();
        });
    }
    
})(); // Fin IIFE
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

#listaArchivosOSM .list-group-item {
    padding: 0.5rem 1rem;
}

#tiempoRestanteContainerOSM {
    animation: fadeInOSM 0.3s ease-in-out;
}

@keyframes fadeInOSM {
    from { opacity: 0; }
    to { opacity: 1; }
}

#tiempoRestanteBadgeOSM {
    font-size: 0.85rem;
    padding: 0.5em 0.8em;
}

#alertaTiempoModalOSM {
    animation: pulseOSM 1s infinite;
}

@keyframes pulseOSM {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

#tiempoRestanteBadgeOSM.bg-danger {
    animation: blinkOSM 0.8s infinite;
}

@keyframes blinkOSM {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<?php endif; // Fin de verificación de permisos ?>