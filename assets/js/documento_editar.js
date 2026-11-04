/**
 * JavaScript para ediciÃƒÂ³n de documentos colaborativos
 * Maneja formularios de Apartado 1, Apartado 2 y completar documento
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // FORMULARIO APARTADO 1
    // ============================================
    const formApartado1 = document.getElementById('formApartado1');
    if (formApartado1) {
        // Manejar campo "Otro" del servicio
        const radioOtro = document.getElementById('edit_servicio_otro');
        const campoOtroEspecificar = document.getElementById('edit_servicio_otro_especificar');
        const radiosServicio = document.querySelectorAll('input[name="servicio_solicitado"]');
        
        radiosServicio.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'otro') {
                    campoOtroEspecificar.disabled = false;
                    campoOtroEspecificar.required = true;
                    campoOtroEspecificar.focus();
                } else {
                    campoOtroEspecificar.disabled = true;
                    campoOtroEspecificar.required = false;
                }
            });
        });
        
        // EnvÃƒÂ­o del formulario Apartado 1
        formApartado1.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnGuardar = document.getElementById('btnGuardarApartado1');
            const textoOriginal = btnGuardar.innerHTML;
            
            // Validar servicio "otro"
            const servicioSeleccionado = document.querySelector('input[name="servicio_solicitado"]:checked');
            if (servicioSeleccionado && servicioSeleccionado.value === 'otro') {
                if (!campoOtroEspecificar.value.trim()) {
                    mostrarAlerta('warning', 'Atenci\u00F3n', 'Por favor especifique el servicio cuando selecciona "Otro"');
                    campoOtroEspecificar.focus();
                    return;
                }
            }
            
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            
            const formData = new FormData(this);
            
            fetch('/Pagina_Solicitudes4/documentos/procesar_apartado1.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('success', '\u00C9xito', data.message);
                    
                    // Recargar despuÃƒÂ©s de 1.5 segundos
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    mostrarAlerta('danger', 'Error', data.message);
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('danger', 'Error', 'Error al procesar la solicitud');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });
    }
    
    // ============================================
    // FORMULARIO APARTADO 2
    // ============================================
    const formApartado2 = document.getElementById('formApartado2');
    if (formApartado2) {
        formApartado2.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnGuardar = document.getElementById('btnGuardarApartado2');
            const textoOriginal = btnGuardar.innerHTML;
            
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            
            const formData = new FormData(this);
            
            fetch('/Pagina_Solicitudes4/documentos/procesar_apartado2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarAlerta('success', '\u00C9xito', data.message);
                    
                    // Recargar despuÃƒÂ©s de 1.5 segundos
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    mostrarAlerta('danger', 'Error', data.message);
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('danger', 'Error', 'Error al procesar la solicitud');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            });
        });
    }
    
    // ============================================
    // BOTÃƒâ€œN COMPLETAR DOCUMENTO
    // ============================================
    const btnCompletarDocumento = document.getElementById('btnCompletarDocumento');
    if (btnCompletarDocumento) {
        btnCompletarDocumento.addEventListener('click', function() {
            const documentoId = this.getAttribute('data-documento-id');
            
            // ConfirmaciÃƒÂ³n con advertencia
            const confirmar = confirm(
                '\u26A0\uFE0F ATENCI\u00D3N\n\n' +
                'Al completar el documento:\n' +
                '\u2022 Se mover\u00E1 a la Base Global\n' +
                '\u2022 No se podr\u00E1n hacer m\u00E1s cambios\n' +
                '\u2022 Los comentarios se bloquear\u00E1n\n\n' +
                '\u00BFEst\u00E1s seguro de completar este documento?'
            );
            
            if (confirmar) {
                completarDocumento(documentoId, this);
            }
        });
    }
    
    // ============================================
    // AUTO-SCROLL A COMENTARIOS SI HAY HASH
    // ============================================
    if (window.location.hash === '#comentarios') {
        const comentariosTab = document.getElementById('comentarios-tab');
        if (comentariosTab) {
            comentariosTab.click();
        }
    }
    
    // ============================================
    // VALIDACIÃƒâ€œN DE FECHAS EN TIEMPO REAL
    // ============================================
    const fechaEntrega = document.querySelector('input[name="fecha_hora_entrega"]');
    if (fechaEntrega) {
        fechaEntrega.addEventListener('change', function() {
            const fechaSeleccionada = new Date(this.value);
            const ahora = new Date();
            
            if (fechaSeleccionada < ahora) {
                mostrarAlerta('warning', 'Advertencia', 'La fecha de entrega seleccionada es anterior a la fecha actual');
            }
        });
    }
    
    // ============================================
    // CONTADOR DE CARACTERES EN TEXTAREAS
    // ============================================
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (maxLength) {
            // Crear contador
            const contador = document.createElement('small');
            contador.className = 'form-text text-muted';
            contador.id = `contador_${textarea.name}`;
            textarea.parentNode.insertBefore(contador, textarea.nextSibling);
            
            // Actualizar contador
            const actualizarContador = () => {
                const restantes = maxLength - textarea.value.length;
                contador.textContent = `${textarea.value.length} / ${maxLength} caracteres`;
                
                if (restantes < 50) {
                    contador.classList.add('text-warning');
                    contador.classList.remove('text-muted');
                } else {
                    contador.classList.add('text-muted');
                    contador.classList.remove('text-warning');
                }
            };
            
            textarea.addEventListener('input', actualizarContador);
            actualizarContador();
        }
    });
});

// ============================================
// FUNCIÃƒâ€œN: COMPLETAR DOCUMENTO
// ============================================
function completarDocumento(documentoId, boton) {
    const textoOriginal = boton.innerHTML;
    
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Completando...';
    
    const formData = new FormData();
    formData.append('documento_id', documentoId);
    
    fetch('/Pagina_Solicitudes4/documentos/procesar_completar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de ÃƒÂ©xito con confetti (opcional)
            mostrarAlerta('success', '\uD83C\uDF89 Documento Completado', data.message);
            
            // Recargar despuÃƒÂ©s de 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            mostrarAlerta('danger', 'Error', data.message);
            boton.disabled = false;
            boton.innerHTML = textoOriginal;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarAlerta('danger', 'Error', 'Error al completar el documento');
        boton.disabled = false;
        boton.innerHTML = textoOriginal;
    });
}

// ============================================
// FUNCIÃƒâ€œN: MOSTRAR ALERTAS
// ============================================
function mostrarAlerta(tipo, titulo, mensaje) {
    // Icono segÃƒÂºn tipo
    const iconos = {
        success: 'check-circle-fill',
        danger: 'exclamation-triangle-fill',
        warning: 'exclamation-circle-fill',
        info: 'info-circle-fill'
    };
    
    const icono = iconos[tipo] || 'info-circle-fill';
    
    const alertaHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg" 
             style="z-index: 9999; min-width: 350px; max-width: 500px;" 
             role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-${icono} me-2" style="font-size: 1.5rem;"></i>
                <div class="flex-grow-1">
                    <strong>${titulo}</strong><br>
                    <small>${mensaje}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertaHTML);
    
    // Auto-cerrar despuÃƒÂ©s de 5 segundos
    setTimeout(() => {
        const alertas = document.querySelectorAll('.alert');
        if (alertas.length > 0) {
            const ultimaAlerta = alertas[alertas.length - 1];
            const bsAlert = bootstrap.Alert.getInstance(ultimaAlerta);
            if (bsAlert) {
                bsAlert.close();
            } else {
                ultimaAlerta.remove();
            }
        }
    }, 5000);
}

// ============================================
// FUNCIÃƒâ€œN: VALIDAR FORMULARIO ANTES DE ENVIAR
// ============================================
function validarFormulario(form) {
    const camposRequeridos = form.querySelectorAll('[required]');
    let valido = true;
    
    camposRequeridos.forEach(campo => {
        if (!campo.value.trim()) {
            valido = false;
            campo.classList.add('is-invalid');
            
            // Remover clase despuÃƒÂ©s de 3 segundos
            setTimeout(() => {
                campo.classList.remove('is-invalid');
            }, 3000);
        }
    });
    
    if (!valido) {
        mostrarAlerta('warning', 'Campos incompletos', 'Por favor completa todos los campos obligatorios');
    }
    
    return valido;
}

// ============================================
// PREVENIR PÃƒâ€°RDIDA DE CAMBIOS SIN GUARDAR
// ============================================
let cambiosSinGuardar = false;

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                cambiosSinGuardar = true;
            });
        });
        
        form.addEventListener('submit', function() {
            cambiosSinGuardar = false;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (cambiosSinGuardar) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
});

// ============================================
// DEBUG: Log de eventos (solo en desarrollo)
// ============================================
if (window.location.hostname === 'localhost') {
    console.log('Ã°Å¸â€�Â§ MÃƒÂ³dulo de ediciÃƒÂ³n de documentos cargado');
    console.log('Ã°Å¸â€œâ€ž Formularios encontrados:', document.querySelectorAll('form').length);
}