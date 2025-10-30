<?php
/**
 * Cambiar estado de una solicitud (solo TI)
 * Permite atender, actualizar y finalizar solicitudes
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que sea usuario de TI
if (!es_usuario_ti()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_tecnico = $_SESSION['nombre_completo'];

// Obtener folio
$folio = isset($_GET['folio']) ? limpiar_dato($_GET['folio']) : '';

if (empty($folio)) {
    establecer_alerta('error', 'Folio no especificado');
    redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
}

// Obtener solicitud
try {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.folio = ?
    ");
    $stmt->execute([$folio]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        establecer_alerta('error', 'Solicitud no encontrada');
        redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
    }
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar la solicitud');
    redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nuevo_estado = limpiar_dato($_POST['estado']);
        $comentarios = limpiar_dato($_POST['comentarios']);
        
        $sql = "UPDATE solicitudes_atencion SET 
                estado = ?,
                comentarios_ti = ?,
                atendido_por = ?,
                fecha_actualizacion = NOW()";
        
        $params = [$nuevo_estado, $comentarios, $usuario_id];
        
        // Si se finaliza, agregar fecha de atención
        if ($nuevo_estado == 'finalizada') {
            $sql .= ", fecha_atencion = NOW()";
        }
        
        $sql .= " WHERE folio = ?";
        $params[] = $folio;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // ====================================
        // ENVIAR NOTIFICACIÓN AL SOLICITANTE
        // ====================================
        require_once __DIR__ . '/../includes/notificaciones.php';
        
        notificar_cambio_estado(
            $folio,
            $solicitud['usuario_id'],
            $nuevo_estado,
            $comentarios
        );
        
        establecer_alerta('success', 'Solicitud actualizada correctamente');
        redirigir(URL_BASE . 'solicitudes/ver.php?folio=' . urlencode($folio));
        
    } catch (Exception $e) {
        establecer_alerta('error', 'Error al actualizar: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Solicitud - <?php echo htmlspecialchars($folio); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">Gestionar Solicitud</h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-file-earmark-text"></i> 
                            <?php echo htmlspecialchars($solicitud['folio']); ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($folio); ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-eye"></i> Ver Detalle
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <div class="row">
                    
                    <!-- Información de la Solicitud -->
                    <div class="col-lg-5 mb-4">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> Información de la Solicitud
                            </div>
                            <div class="card-body">
                                <p><strong>Folio:</strong><br><?php echo htmlspecialchars($solicitud['folio']); ?></p>
                                <p><strong>Solicitante:</strong><br><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></p>
                                <p><strong>Departamento:</strong><br><?php echo htmlspecialchars($solicitud['departamento']); ?></p>
                                <p><strong>Tipo:</strong><br><?php echo htmlspecialchars($solicitud['tipo_soporte']); ?></p>
                                <p><strong>Prioridad:</strong><br>
                                    <span class="badge <?php 
                                        if ($solicitud['prioridad'] == 'critica') echo 'bg-danger';
                                        elseif ($solicitud['prioridad'] == 'alta') echo 'bg-warning text-dark';
                                        elseif ($solicitud['prioridad'] == 'media') echo 'bg-info text-dark';
                                        else echo 'bg-secondary';
                                    ?>"><?php echo ucfirst($solicitud['prioridad']); ?></span>
                                </p>
                                <p><strong>Estado Actual:</strong><br>
                                    <span class="badge <?php 
                                        if ($solicitud['estado'] == 'pendiente') echo 'bg-warning text-dark';
                                        elseif ($solicitud['estado'] == 'en_proceso') echo 'bg-info text-dark';
                                        elseif ($solicitud['estado'] == 'finalizada') echo 'bg-success';
                                        else echo 'bg-secondary';
                                    ?>"><?php echo obtener_texto_estado($solicitud['estado']); ?></span>
                                </p>
                                <p><strong>Descripción:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Actualización -->
                    <div class="col-lg-7 mb-4">
                        <div class="card card-custom">
                            <div class="card-header">
                                <i class="bi bi-gear"></i> Actualizar Estado
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    
                                    <!-- Cambiar Estado -->
                                    <div class="mb-3">
                                        <label for="estado" class="form-label required">Nuevo Estado</label>
                                        <select class="form-select" id="estado" name="estado" required>
                                            <option value="">Seleccione...</option>
                                            <option value="pendiente" <?php echo $solicitud['estado'] == 'pendiente' ? 'selected' : ''; ?>>
                                                Pendiente
                                            </option>
                                            <option value="en_proceso" <?php echo $solicitud['estado'] == 'en_proceso' ? 'selected' : ''; ?>>
                                                En Proceso
                                            </option>
                                            <option value="finalizada" <?php echo $solicitud['estado'] == 'finalizada' ? 'selected' : ''; ?>>
                                                Finalizada
                                            </option>
                                            <option value="cancelada">
                                                Cancelada
                                            </option>
                                        </select>
                                        <small class="text-muted">Estado actual: <?php echo obtener_texto_estado($solicitud['estado']); ?></small>
                                    </div>

                                    <!-- Comentarios -->
                                    <div class="mb-4">
                                        <label for="comentarios" class="form-label required">Comentarios / Observaciones</label>
                                        <textarea class="form-control" id="comentarios" name="comentarios" rows="6" required 
                                                  placeholder="Describe las acciones realizadas o la solución aplicada..."><?php echo htmlspecialchars($solicitud['comentarios_ti'] ?? ''); ?></textarea>
                                        <small class="text-muted">Estos comentarios serán visibles para el solicitante</small>
                                    </div>

                                    <!-- Info del técnico -->
                                    <div class="alert alert-info">
                                        <i class="bi bi-person-check"></i> 
                                        <strong>Atendido por:</strong> <?php echo htmlspecialchars($nombre_tecnico); ?>
                                    </div>

                                    <!-- Botones -->
                                    <div class="d-flex justify-content-between">
                                        <a href="<?php echo URL_BASE; ?>ti_sistemas/todas_solicitudes.php" class="btn btn-secondary">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-gradient">
                                            <i class="bi bi-check-circle"></i> Actualizar Solicitud
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>

                        <!-- Historial (si existe) -->
                        <?php if (!empty($solicitud['comentarios_ti'])): ?>
                        <div class="card card-custom mt-4">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Comentarios Anteriores
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['comentarios_ti'])); ?></p>
                                <?php if ($solicitud['fecha_actualizacion']): ?>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> 
                                    <?php echo formatear_fecha($solicitud['fecha_actualizacion'], true); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>

                </div>

            </div>
        </main>

    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        bodyElement.setAttribute('data-theme', currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = bodyElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => {
                themeToggle.classList.remove('rotating');
            }, 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Confirmación al cambiar a finalizada
        document.getElementById('estado').addEventListener('change', function() {
            if (this.value === 'finalizada') {
                if (!confirm('¿Está seguro de marcar esta solicitud como finalizada? Esta acción indica que el problema está resuelto.')) {
                    this.value = '<?php echo $solicitud['estado']; ?>';
                }
            }
        });
    </script>

</body>
</html>