<?php
/**
 * Componente UI para el dropdown de notificaciones
 * Incluir en el navbar de todos los dashboards
 */

if (!sesion_activa()) return;

$usuario_id = $_SESSION['usuario_id'];

// Obtener notificaciones recientes
require_once __DIR__ . '/notificaciones.php';
$notificaciones_recientes = obtener_notificaciones_pendientes($usuario_id, 5);
$total_no_leidas = count($notificaciones_recientes);
?>

<!-- Estilos del componente -->
<style>
.notificaciones-wrapper {
    position: relative;
}

.notificaciones-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: bold;
    display: none;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notificaciones-dropdown {
    min-width: 350px;
    max-width: 400px;
    max-height: 500px;
    overflow-y: auto;
}

.notificacion-item {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
}

.notificacion-item:hover {
    background-color: #f8f9fa;
}

.notificacion-item.nueva {
    background-color: #e7f3ff;
    animation: fadeIn 0.5s ease;
}

.notificacion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.notificacion-mensaje {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 0.5rem 0;
}

.notificacion-cerrar {
    padding: 0.25rem;
    color: #6c757d;
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

.notificacion-cerrar:hover {
    color: #dc3545;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.conexion-estado {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.conexion-estado.conectado {
    background-color: #28a745;
    box-shadow: 0 0 5px #28a745;
    animation: blink 2s infinite;
}

.conexion-estado.desconectado {
    background-color: #dc3545;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Tema oscuro */
body[data-theme="dark"] .notificacion-item {
    border-bottom-color: #404040;
}

body[data-theme="dark"] .notificacion-item:hover {
    background-color: #3a3a3a;
}

body[data-theme="dark"] .notificacion-item.nueva {
    background-color: #1e3a5f;
}

body[data-theme="dark"] .dropdown-menu {
    background-color: #2d2d2d;
    border-color: #404040;
}

body[data-theme="dark"] .notificacion-mensaje {
    color: #b0b0b0;
}
</style>

<!-- Botón de notificaciones -->
<div class="notificaciones-wrapper dropdown me-3">
    <button class="btn btn-link position-relative" type="button" 
            id="dropdownNotificaciones" data-bs-toggle="dropdown" 
            aria-expanded="false" title="Notificaciones">
        <i class="bi bi-bell fs-5"></i>
        <span class="notificaciones-badge" id="notificaciones-badge" 
              style="<?php echo $total_no_leidas > 0 ? 'display: inline-block;' : 'display: none;'; ?>">
            <?php echo $total_no_leidas > 9 ? '9+' : $total_no_leidas; ?>
        </span>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end notificaciones-dropdown p-0" 
         aria-labelledby="dropdownNotificaciones">
        
        <!-- Header del dropdown -->
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h6 class="mb-0">
                <span class="conexion-estado desconectado" id="conexion-estado"></span>
                Notificaciones
            </h6>
            <?php if ($total_no_leidas > 0): ?>
            <button class="btn btn-sm btn-link text-muted p-0" 
                    onclick="marcarTodasLeidas()" 
                    title="Marcar todas como leídas">
                <i class="bi bi-check2-all"></i>
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Lista de notificaciones -->
        <div id="notificaciones-lista">
            <?php if (empty($notificaciones_recientes)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-0">No hay notificaciones</p>
            </div>
            <?php else: ?>
                <?php foreach ($notificaciones_recientes as $notif): ?>
                <div class="notificacion-item" data-id="<?php echo $notif['id']; ?>">
                    <div class="notificacion-content" style="padding-right: 30px;">
                        <div class="notificacion-header">
                            <span class="badge bg-<?php 
                                echo $notif['tipo'] === 'nueva_solicitud' ? 'primary' : 'success'; 
                            ?>">
                                <?php echo htmlspecialchars($notif['titulo']); ?>
                            </span>
                            <small class="text-muted">
                                <?php echo formatear_fecha($notif['fecha_creacion'], true); ?>
                            </small>
                        </div>
                        <p class="notificacion-mensaje mb-2">
                            <?php echo htmlspecialchars($notif['mensaje']); ?>
                        </p>
                        <?php 
                        $datos = json_decode($notif['datos_json'], true);
                        if (!empty($datos['url'])): 
                        ?>
                        <a href="<?php echo htmlspecialchars($datos['url']); ?>" 
                           class="btn btn-sm btn-outline-primary"
                           onclick="sistemaNotificaciones.marcarComoLeida(<?php echo $notif['id']; ?>)">
                            <i class="bi bi-eye"></i> Ver detalles
                        </a>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-sm btn-link notificacion-cerrar" 
                            onclick="event.stopPropagation(); sistemaNotificaciones.marcarComoLeida(<?php echo $notif['id']; ?>)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Footer del dropdown -->
        <div class="p-2 border-top text-center">
            <a href="<?php echo URL_BASE; ?>notificaciones/todas.php" 
               class="btn btn-sm btn-link text-decoration-none">
                Ver todas las notificaciones
            </a>
        </div>
        
    </div>
</div>

<script>
// Función auxiliar para marcar todas como leídas
async function marcarTodasLeidas() {
    try {
        const response = await fetch('<?php echo URL_BASE; ?>notificaciones/marcar_todas_leidas.php', {
            method: 'POST'
        });
        
        if (response.ok) {
            // Limpiar lista
            document.getElementById('notificaciones-lista').innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">No hay notificaciones</p>
                </div>
            `;
            
            // Ocultar badge
            document.getElementById('notificaciones-badge').style.display = 'none';
        }
    } catch (error) {
        console.error('Error al marcar todas como leídas:', error);
    }
}
</script>