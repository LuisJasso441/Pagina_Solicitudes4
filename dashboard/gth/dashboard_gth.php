<?php
/**
 * Dashboard principal para GTH y Contabilidad
 * dashboard/gth/dashboard_gth.php
 * 
 * Pagina de inicio exclusiva para departamentos GTH y Contabilidad.
 * Muestra resumen de vacaciones pendientes y accesos rapidos.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar sesion
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Verificar que sea GTH o Contabilidad
$depto_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_gth = in_array($depto_codigo, ['gth', 'gestion_talento', 'contabilidad']);
$es_sistemas = in_array($depto_codigo, ['sistemas', 'ti']);

if (!$es_gth && !$es_sistemas) {
    header('Location: ' . URL_BASE . 'dashboard/departamento.php');
    exit;
}

$pdo = conectarDB();

// Contadores de vacaciones
try {
    $stmt = $pdo->query("SELECT estado, COUNT(*) as total FROM solicitudes_vacaciones GROUP BY estado");
    $contadores_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $contadores_raw = [];
}

$pendientes_gth = $contadores_raw['pendiente_gth'] ?? 0;
$pendientes_admin = $contadores_raw['pendiente_admin'] ?? 0;
$completadas = $contadores_raw['completada'] ?? 0;
$rechazadas = ($contadores_raw['rechazada_admin'] ?? 0) + ($contadores_raw['rechazada_gth'] ?? 0);

// Solicitudes recientes pendientes GTH
try {
    $stmt_recientes = $pdo->prepare("
        SELECT sv.id, sv.folio, sv.fecha_inicio, sv.fecha_fin, sv.dias_solicitados, sv.estado, sv.fecha_creacion,
               u.nombre_completo, d.nombre AS departamento_nombre
        FROM solicitudes_vacaciones sv
        LEFT JOIN usuarios u ON sv.usuario_id = u.id
        LEFT JOIN departamentos d ON sv.departamento_id = d.id
        WHERE sv.estado = 'pendiente_gth'
        ORDER BY sv.fecha_creacion DESC
        LIMIT 5
    ");
    $stmt_recientes->execute();
    $recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recientes = [];
}

$fecha_hoy = obtener_fecha_actual_espanol();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard GTH - GrupoVerden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <style>
        .stat-card {
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .welcome-card {
            background: linear-gradient(135deg, #198754 0%, #0f5132 100%);
            border-radius: 16px;
            color: white;
        }
        .table-recientes th {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
        }
        .quick-link {
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            border: 1px solid #e9ecef;
        }
        .quick-link:hover {
            border-color: #198754;
            box-shadow: 0 4px 12px rgba(25,135,84,0.15);
            transform: translateY(-2px);
        }
        /* Dark mode para elementos custom */
        body[data-theme="dark"] .stat-card {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        body[data-theme="dark"] .stat-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        body[data-theme="dark"] .card {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        body[data-theme="dark"] .card-header.bg-white {
            background-color: var(--card-bg) !important;
            color: var(--text-primary);
            border-bottom-color: var(--border-color);
        }
        body[data-theme="dark"] .card-body {
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        body[data-theme="dark"] .table-light {
            --bs-table-bg: #353535;
            --bs-table-color: var(--text-primary);
        }
        body[data-theme="dark"] .quick-link {
            background-color: var(--card-bg) !important;
            border-color: var(--border-color);
        }
        body[data-theme="dark"] .quick-link:hover {
            border-color: #198754;
        }
        body[data-theme="dark"] .quick-link .fw-semibold {
            color: var(--text-primary) !important;
        }
        body[data-theme="dark"] .quick-link .text-muted {
            color: var(--text-secondary) !important;
        }
        body[data-theme="dark"] .bg-success.bg-opacity-10 {
            background-color: rgba(25, 135, 84, 0.15) !important;
        }
        body[data-theme="dark"] .bg-warning.bg-opacity-10 {
            background-color: rgba(255, 193, 7, 0.15) !important;
        }
        body[data-theme="dark"] .bg-info.bg-opacity-10 {
            background-color: rgba(13, 202, 240, 0.15) !important;
        }
        body[data-theme="dark"] .bg-danger.bg-opacity-10 {
            background-color: rgba(220, 53, 69, 0.15) !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../../includes/sidebar/sidebar_gth.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <!-- Alerta -->
                <?php echo mostrar_alerta(); ?>

                <!-- Bienvenida -->
                <div class="welcome-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h3 class="mb-1">
                                <i class="bi bi-people-fill me-2"></i>
                                Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                            </h3>
                            <p class="mb-0 opacity-75">
                                <?php echo htmlspecialchars($_SESSION['departamento_nombre']); ?> &bull; <?php echo $fecha_hoy; ?>
                            </p>
                            <?php if ($pendientes_gth > 0): ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_gth.php" class="btn btn-light btn-sm mt-2">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                <?php echo $pendientes_gth; ?> solicitud<?php echo $pendientes_gth > 1 ? 'es' : ''; ?> pendiente<?php echo $pendientes_gth > 1 ? 's' : ''; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <button class="btn-anuncios-trigger" 
                                onclick="if(!window._modalAnuncios){window._modalAnuncios=new bootstrap.Modal(document.getElementById('modalAnuncios'));}window._modalAnuncios.show();" 
                                title="Anuncios">
                            <i class="bi bi-megaphone-fill"></i>
                        </button>
                    </div>
                </div>

                <!-- Cards de estadisticas -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold"><?php echo $pendientes_gth; ?></div>
                                    <small class="text-muted">Pendientes GTH</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold"><?php echo $pendientes_admin; ?></div>
                                    <small class="text-muted">Pendientes Admin</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold"><?php echo $completadas; ?></div>
                                    <small class="text-muted">Completadas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                                <div>
                                    <div class="fs-4 fw-bold"><?php echo $rechazadas; ?></div>
                                    <small class="text-muted">Rechazadas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Solicitudes recientes -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history me-2 text-warning"></i>
                                    Solicitudes Pendientes de GTH
                                </h5>
                                <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_gth.php" class="btn btn-sm btn-outline-success">
                                    Ver todas <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recientes)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                                    No hay solicitudes pendientes de aprobacion GTH
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-recientes mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Folio</th>
                                                <th>Empleado</th>
                                                <th>Departamento</th>
                                                <th>Dias</th>
                                                <th>Periodo</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recientes as $sol): ?>
                                            <tr>
                                                <td><span class="badge bg-success bg-opacity-10 text-success"><?php echo htmlspecialchars($sol['folio']); ?></span></td>
                                                <td><?php echo htmlspecialchars($sol['nombre_completo'] ?? $sol['nombre_manual'] ?? ''); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($sol['departamento_nombre']); ?></small></td>
                                                <td><span class="fw-bold"><?php echo $sol['dias_solicitados']; ?></span></td>
                                                <td>
                                                    <small>
                                                        <?php echo date('d/m/Y', strtotime($sol['fecha_inicio'])); ?> - 
                                                        <?php echo date('d/m/Y', strtotime($sol['fecha_fin'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/ver_solicitud_vacaciones.php?id=<?php echo $sol['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
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

                    <!-- Accesos rapidos -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning me-2 text-warning"></i>
                                    Accesos Rapidos
                                </h5>
                            </div>
                            <div class="card-body d-flex flex-column gap-2">
                                <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/vacaciones_gth.php" class="quick-link d-flex align-items-center gap-3 p-3 bg-white">
                                    <i class="bi bi-clipboard2-check fs-4 text-success"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Panel GTH</div>
                                        <small class="text-muted">Aprobar solicitudes de vacaciones</small>
                                    </div>
                                    <?php if ($pendientes_gth > 0): ?>
                                    <span class="badge bg-warning text-dark ms-auto"><?php echo $pendientes_gth; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/mis_vacaciones.php" class="quick-link d-flex align-items-center gap-3 p-3 bg-white">
                                    <i class="bi bi-calendar-check fs-4 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Mis Vacaciones</div>
                                        <small class="text-muted">Ver mis solicitudes</small>
                                    </div>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php" class="quick-link d-flex align-items-center gap-3 p-3 bg-white">
                                    <i class="bi bi-clipboard-check fs-4 text-info"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Ordenes de Mantenimiento</div>
                                        <small class="text-muted">Ver ordenes de servicio</small>
                                    </div>
                                </a>
                                <a href="<?php echo URL_BASE; ?>dashboard/reservaciones/reservaciones_sala.php" class="quick-link d-flex align-items-center gap-3 p-3 bg-white">
                                    <i class="bi bi-calendar-event fs-4 text-secondary"></i>
                                    <div>
                                        <div class="fw-semibold text-dark">Sala de Juntas</div>
                                        <small class="text-muted">Reservar sala</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <<!-- Boton flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <!-- Modal de Nueva Solicitud -->
    <?php include __DIR__ . '/../../solicitudes/modal_crear.php'; ?>

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

    <!-- Sistema de notificaciones en tiempo real -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>

    <!-- Modal de Anuncios -->
    <?php include __DIR__ . '/../../includes/anuncios/modal_anuncios.php'; ?>

</body>
</html>