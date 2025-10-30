<?php
/**
 * Interfaz de usuario para comentarios de documentos colaborativos
 * Vista de lectura y formulario de publicación
 */

// Generar token CSRF para formularios
$csrf_token = generar_token_csrf();
?>

<div class="row">
    <!-- Panel izquierdo: Lista de comentarios -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-chat-dots"></i>
                    Comentarios (<?= count($comentarios) ?>)
                </h5>
            </div>
            <div class="card-body comentarios-panel">
                <?php if (count($comentarios) > 0): ?>
                    <?php foreach ($comentarios as $comentario): ?>
                        <div class="comentario-item tipo-<?= htmlspecialchars($comentario['tipo_mensaje']) ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($comentario['usuario_autor_nombre']) ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($comentario['departamento_autor']) ?></span>
                                    
                                    <?php
                                    $tipo_badges = [
                                        'normal' => ['text' => 'Comentario', 'color' => 'secondary'],
                                        'aclaracion' => ['text' => 'Aclaración', 'color' => 'info'],
                                        'correccion' => ['text' => 'Corrección', 'color' => 'warning'],
                                        'solicitud' => ['text' => 'Solicitud', 'color' => 'primary']
                                    ];
                                    
                                    $badge_info = $tipo_badges[$comentario['tipo_mensaje']] ?? $tipo_badges['normal'];
                                    ?>
                                    
                                    <span class="badge bg-<?= $badge_info['color'] ?> ms-1">
                                        <?= $badge_info['text'] ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($comentario['fecha_hora_publicacion'])) ?>
                                    </small>
                                    
                                    <?php
                                    // Mostrar botón eliminar si es el autor o es admin
                                    $puede_eliminar = ($comentario['usuario_autor_id'] == $usuario_id) || (strtolower($departamento) === 'ti_sistemas');
                                    if ($puede_eliminar && $documento['estado'] !== 'completado'):
                                    ?>
                                        <button class="btn btn-sm btn-outline-danger ms-2" 
                                                onclick="eliminarComentario(<?= $comentario['id'] ?>)"
                                                title="Eliminar comentario">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($comentario['texto_comentario'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                        <p class="mt-3">No hay comentarios aún</p>
                        <p>Sé el primero en comentar este documento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Panel derecho: Formulario de nuevo comentario -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square"></i>
                    Nuevo Comentario
                </h5>
            </div>
            <div class="card-body">
                <?php if ($permisos['puede_comentar'] && $documento['estado'] !== 'completado'): ?>
                    <form id="formNuevoComentario">
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="documento_id" value="<?= $documento['id'] ?>">
                        <input type="hidden" name="folio" value="<?= htmlspecialchars($documento['folio']) ?>">
                        
                        <!-- Tipo de mensaje -->
                        <div class="mb-3">
                            <label class="form-label">Tipo de mensaje</label>
                            <select class="form-select" name="tipo_mensaje" required>
                                <option value="normal">💬 Comentario normal</option>
                                <option value="aclaracion">❓ Solicitar aclaración</option>
                                <option value="correccion">✏️ Sugerir corrección</option>
                                <option value="solicitud">📋 Solicitud de información</option>
                            </select>
                            <small class="form-text text-muted">
                                Selecciona el tipo de comentario para mejor organización
                            </small>
                        </div>
                        
                        <!-- Texto del comentario -->
                        <div class="mb-3">
                            <label class="form-label">Comentario</label>
                            <textarea class="form-control" 
                                      name="texto_comentario" 
                                      rows="6" 
                                      maxlength="1000"
                                      placeholder="Escribe tu comentario aquí..."
                                      required></textarea>
                            <small class="form-text text-muted">
                                Mínimo 5 caracteres, máximo 1000
                            </small>
                        </div>
                        
                        <!-- Botón publicar -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success" id="btnPublicarComentario">
                                <i class="bi bi-send"></i>
                                Publicar Comentario
                            </button>
                        </div>
                    </form>
                <?php elseif ($documento['estado'] === 'completado'): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-lock"></i>
                        <strong>Documento completado</strong>
                        <p class="mb-0">No se pueden agregar más comentarios a documentos completados.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Sin permisos</strong>
                        <p class="mb-0">No tienes permiso para comentar en este documento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Leyenda de tipos de comentarios -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Tipos de comentarios</h6>
            </div>
            <div class="card-body">
                <small>
                    <div class="mb-2">
                        <span class="badge bg-secondary">Comentario</span>
                        Observaciones generales
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-info">Aclaración</span>
                        Dudas o consultas
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-warning">Corrección</span>
                        Sugerencias de cambio
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-primary">Solicitud</span>
                        Peticiones específicas
                    </div>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para comentarios -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formComentario = document.getElementById('formNuevoComentario');
    
    if (formComentario) {
        formComentario.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btnPublicar = document.getElementById('btnPublicarComentario');
            const textoOriginal = btnPublicar.innerHTML;
            
            // Validar longitud del comentario
            const textoComentario = formComentario.querySelector('[name="texto_comentario"]');
            if (textoComentario.value.trim().length < 5) {
                mostrarAlerta('warning', 'Atención', 'El comentario debe tener al menos 5 caracteres');
                textoComentario.focus();
                return;
            }
            
            btnPublicar.disabled = true;
            btnPublicar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Publicando...';
            
            const formData = new FormData(this);
            
            fetch('/Pagina_Solicitudes4/documentos/procesar_comentario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Log para debug
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));
                return response.text();
            })
            .then(text => {
                // Log del texto raw antes de parsear
                console.log('Response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        mostrarAlerta('success', 'Éxito', data.message);
                        
                        // Limpiar formulario
                        textoComentario.value = '';
                        formComentario.querySelector('[name="tipo_mensaje"]').value = 'normal';
                        
                        // Recargar después de 1.5 segundos
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        mostrarAlerta('danger', 'Error', data.message);
                        btnPublicar.disabled = false;
                        btnPublicar.innerHTML = textoOriginal;
                    }
                } catch (parseError) {
                    console.error('Error parsing JSON:', parseError);
                    console.error('Raw response:', text);
                    mostrarAlerta('danger', 'Error', 'Respuesta inválida del servidor. Revisa la consola para más detalles.');
                    btnPublicar.disabled = false;
                    btnPublicar.innerHTML = textoOriginal;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarAlerta('danger', 'Error', 'Error al procesar la solicitud');
                btnPublicar.disabled = false;
                btnPublicar.innerHTML = textoOriginal;
            });
        });
    }
});

// Función para eliminar comentario
function eliminarComentario(comentarioId) {
    if (!confirm('¿Estás seguro de eliminar este comentario?\n\nEsta acción no se puede deshacer.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
    formData.append('accion', 'eliminar');
    formData.append('comentario_id', comentarioId);
    
    fetch('/Pagina_Solicitudes4/documentos/procesar_comentario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarAlerta('success', 'Éxito', data.message);
            
            // Recargar después de 1 segundo
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            mostrarAlerta('danger', 'Error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarAlerta('danger', 'Error', 'Error al eliminar el comentario');
    });
}

// Función mostrarAlerta (si no existe en documento_editar.js)
if (typeof mostrarAlerta === 'undefined') {
    function mostrarAlerta(tipo, titulo, mensaje) {
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
        
        // Auto-cerrar después de 5 segundos
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
}
</script>