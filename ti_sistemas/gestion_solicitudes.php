<?php
/**
 * Ver todas las solicitudes (solo TI)
 * Panel completo de gestión con filtros y acciones
 * 
 * ACTUALIZADO:
 * - Muestra nombre_solicitante de la tabla (editable por usuario)
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

// Parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? limpiar_dato($_GET['estado']) : '';
$filtro_prioridad = isset($_GET['prioridad']) ? limpiar_dato($_GET['prioridad']) : '';
$busqueda = isset($_GET['buscar']) ? limpiar_dato($_GET['buscar']) : '';

// Obtener solicitudes
try {
    $pdo = conectarDB();
    
    // ACTUALIZADO: Usa COALESCE para mostrar nombre_solicitante o nombre de usuario
    $sql = "SELECT s.*, 
                   COALESCE(s.nombre_solicitante, u.nombre_completo) as solicitante_nombre, 
                   u.departamento
            FROM solicitudes_atencion s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtro_estado)) {
        $sql .= " AND s.estado = ?";
        $params[] = $filtro_estado;
    }
    
    if (!empty($filtro_prioridad)) {
        $sql .= " AND s.prioridad = ?";
        $params[] = $filtro_prioridad;
    }
    
    if (!empty($busqueda)) {
        // ACTUALIZADO: También busca en nombre_solicitante
        $sql .= " AND (s.folio LIKE ? OR s.descripcion LIKE ? OR u.nombre_completo LIKE ? OR s.nombre_solicitante LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $sql .= " ORDER BY 
              CASE s.estado 
                  WHEN 'pendiente' THEN 1 
                  WHEN 'en_proceso' THEN 2 
                  ELSE 3 
              END,
              CASE s.prioridad
                  WHEN 'critica' THEN 1
                  WHEN 'alta' THEN 2
                  WHEN 'media' THEN 3
                  WHEN 'baja' THEN 4
              END,
              s.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    // Contadores
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
            COUNT(*) as total
        FROM solicitudes_atencion
    ");
    $contadores = $stmt->fetch();
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar solicitudes: ' . $e->getMessage());
    $solicitudes = [];
    $contadores = ['pendientes' => 0, 'en_proceso' => 0, 'finalizadas' => 0, 'total' => 0];
}

/**
 * Obtener clase de badge según estado
 */
function obtener_badge_estado($estado) {
    $badges = [
        'pendiente' => 'bg-warning text-dark',
        'en_proceso' => 'bg-info text-dark',
        'finalizada' => 'bg-success',
        'cancelada' => 'bg-secondary'
    ];
    return $badges[$estado] ?? 'bg-secondary';
}

/**
 * Obtener clase de badge según prioridad
 */
function obtener_badge_prioridad($prioridad) {
    $badges = [
        'critica' => 'bg-danger',
        'alta' => 'bg-warning text-dark',
        'media' => 'bg-info text-dark',
        'baja' => 'bg-secondary'
    ];
    return $badges[$prioridad] ?? 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas las Solicitudes - TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Header -->
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="welcome-text">Gestión de Solicitudes</h2>
                        <p class="text-muted mb-0">
                            Panel completo de administración
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-warning text-dark">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['pendientes']; ?></h3>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-info text-white">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['en_proceso']; ?></h3>
                                <small class="text-muted">En Proceso</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-success text-white">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['finalizadas']; ?></h3>
                                <small class="text-muted">Finalizadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-primary text-white">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['total']; ?></h3>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card card-custom mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="en_proceso" <?php echo $filtro_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                                    <option value="finalizada" <?php echo $filtro_estado == 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                    <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Prioridad</label>
                                <select name="prioridad" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <option value="critica" <?php echo $filtro_prioridad == 'critica' ? 'selected' : ''; ?>>Crítica</option>
                                    <option value="alta" <?php echo $filtro_prioridad == 'alta' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="media" <?php echo $filtro_prioridad == 'media' ? 'selected' : ''; ?>>Media</option>
                                    <option value="baja" <?php echo $filtro_prioridad == 'baja' ? 'selected' : ''; ?>>Baja</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="buscar" class="form-control" 
                                       placeholder="Folio, descripción o solicitante..."
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Solicitudes -->
                <div class="card card-custom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Listado Completo</h5>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-success btn-sm" onclick="descargarExcelSolicitudes()">
                                <i class="bi bi-file-earmark-excel me-1"></i>Descargar DB (Mes actual)
                            </button>
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDescargarRango">
                                <i class="bi bi-calendar-range me-1"></i>Descargar por rango
                            </button>
                            <span class="badge bg-primary"><?php echo count($solicitudes); ?> resultado(s)</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($solicitudes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-3 mb-0">No hay solicitudes para mostrar</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Solicitante</th>
                                        <th>Departamento</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($sol['solicitante_nombre']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($sol['departamento']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($sol['tipo_soporte']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $desc = htmlspecialchars($sol['descripcion']);
                                            echo strlen($desc) > 40 ? substr($desc, 0, 40) . '...' : $desc;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_prioridad($sol['prioridad']); ?>">
                                                <?php echo ucfirst($sol['prioridad']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo obtener_badge_estado($sol['estado']); ?>">
                                                <?php echo obtener_texto_estado($sol['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatear_fecha($sol['fecha_creacion']); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($sol['estado'] != 'finalizada' && $sol['estado'] != 'cancelada'): ?>
                                            <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                               class="btn btn-sm btn-outline-success" title="Gestionar">
                                                <i class="bi bi-gear"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
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
    </script>

    <!-- Modal de procesamiento de descarga -->
    <div class="modal fade" id="modalProcesandoDescarga" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Procesando...</span>
                    </div>
                    <h5 class="mb-2">Procesando descarga...</h5>
                    <p class="text-muted mb-3">Su archivo se está procesando para descargarse.<br>Por favor espere.</p>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para seleccionar rango de fechas -->
    <div class="modal fade" id="modalDescargarRango" tabindex="-1" aria-labelledby="modalDescargarRangoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDescargarRangoLabel">
                        <i class="bi bi-calendar-range me-2"></i>Descargar por Rango de Fechas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Seleccione el rango de fechas para filtrar las solicitudes a descargar.</p>
                    
                    <!-- Mensaje de error -->
                    <div id="errorRangoFechas" class="alert alert-danger d-none" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span id="errorRangoTexto"></span>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rango_fecha_desde" class="form-label">
                                <i class="bi bi-calendar-event me-1"></i>Fecha Desde
                            </label>
                            <input type="date" class="form-control" id="rango_fecha_desde" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rango_fecha_hasta" class="form-label">
                                <i class="bi bi-calendar-event me-1"></i>Fecha Hasta
                            </label>
                            <input type="date" class="form-control" id="rango_fecha_hasta" required>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Se descargarán todas las solicitudes creadas dentro del rango seleccionado.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-info" onclick="descargarExcelRango()">
                        <i class="bi bi-download me-1"></i>Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Función para descargar Excel del mes actual
    function descargarExcelSolicitudes() {
        const modal = new bootstrap.Modal(document.getElementById('modalProcesandoDescarga'));
        modal.show();
        
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = 'descargar_solicitudes_excel.php';
        document.body.appendChild(iframe);
        
        setTimeout(function() {
            modal.hide();
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 1000);
        }, 5000);
    }
    
    // Función para descargar Excel por rango de fechas
    function descargarExcelRango() {
        const fechaDesde = document.getElementById('rango_fecha_desde').value;
        const fechaHasta = document.getElementById('rango_fecha_hasta').value;
        const errorDiv = document.getElementById('errorRangoFechas');
        const errorTexto = document.getElementById('errorRangoTexto');
        
        // Ocultar error previo
        errorDiv.classList.add('d-none');
        
        // Validar campos obligatorios
        if (!fechaDesde || !fechaHasta) {
            errorTexto.textContent = 'Ambas fechas son obligatorias.';
            errorDiv.classList.remove('d-none');
            return;
        }
        
        // Validar que fecha desde no sea mayor que fecha hasta
        if (fechaDesde > fechaHasta) {
            errorTexto.textContent = 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".';
            errorDiv.classList.remove('d-none');
            return;
        }
        
        // Cerrar modal de rango
        const modalRango = bootstrap.Modal.getInstance(document.getElementById('modalDescargarRango'));
        modalRango.hide();
        
        // Mostrar modal de procesamiento
        const modalProcesando = new bootstrap.Modal(document.getElementById('modalProcesandoDescarga'));
        modalProcesando.show();
        
        // Crear un iframe oculto para la descarga con parámetros
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = 'descargar_solicitudes_excel_rango.php?fecha_desde=' + 
                     encodeURIComponent(fechaDesde) + '&fecha_hasta=' + encodeURIComponent(fechaHasta);
        document.body.appendChild(iframe);
        
        // Cerrar modal después de un tiempo prudente
        setTimeout(function() {
            modalProcesando.hide();
            // Limpiar campos del formulario
            document.getElementById('rango_fecha_desde').value = '';
            document.getElementById('rango_fecha_hasta').value = '';
            setTimeout(function() {
                document.body.removeChild(iframe);
            }, 1000);
        }, 5000);
    }
    </script>

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

</body>
</html>