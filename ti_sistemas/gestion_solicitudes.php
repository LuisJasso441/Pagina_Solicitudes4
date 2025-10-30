<?php
/**
 * Ver todas las solicitudes (solo TI)
 * Panel completo de gestión con filtros y acciones
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
    
    $sql = "SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
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
        $sql .= " AND (s.folio LIKE ? OR s.descripcion LIKE ? OR u.nombre_completo LIKE ?)";
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
                        <h2 class="welcome-text">Gestión de Solicitudes</h2>
                        <p class="text-muted mb-0">
                            Panel completo de administración
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo URL_BASE; ?>dashboard/ti_sistemas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php echo mostrar_alerta(); ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-warning mx-auto mb-2">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['pendientes']; ?></h3>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-info mx-auto mb-2">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['en_proceso']; ?></h3>
                                <small class="text-muted">En Proceso</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box icon-box-success mx-auto mb-2">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h3 class="mb-0"><?php echo $contadores['finalizadas']; ?></h3>
                                <small class="text-muted">Finalizadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-custom text-center">
                            <div class="card-body">
                                <div class="icon-box mx-auto mb-2" style="background: #e0e7ff; color: #6366f1;">
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul"></i> Listado Completo</span>
                        <span class="badge bg-primary"><?php echo count($solicitudes); ?> resultado(s)</span>
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
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($sol['estado'] != 'finalizada'): ?>
                                            <a href="<?php echo URL_BASE; ?>ti_sistemas/cambiar_estado.php?folio=<?php echo urlencode($sol['folio']); ?>" 
                                               class="btn btn-sm btn-outline-success">
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

</body>
</html>