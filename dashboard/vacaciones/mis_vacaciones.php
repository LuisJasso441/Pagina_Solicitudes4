<?php
/**
 * Mis Vacaciones - Dashboard del Empleado
 * dashboard/vacaciones/mis_vacaciones.php
 * 
 * Muestra: resumen de vacaciones (antigüedad, días LFT, tomados, disponibles)
 * + tabla de solicitudes del periodo actual
 * Accesible por TODOS los empleados desde su sidebar
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Conexión a BD
$pdo = conectarDB();

// Obtener resumen de vacaciones del usuario actual
$resumen = obtener_resumen_vacaciones($pdo, $_SESSION['usuario_id']);

// Detectar tipo de usuario para sidebar
$departamento_codigo = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
$es_ti = ($departamento_codigo === 'sistemas' || $departamento_codigo === 'ti');
$es_mantenimiento = ($departamento_codigo === 'mantenimiento');

// Alertas
$alerta = $_SESSION['alerta'] ?? null;
unset($_SESSION['alerta']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Vacaciones - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">

    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    
    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>

    <style>
        /* Cards de resumen */
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 0.82rem;
            color: #6c757d;
            font-weight: 500;
        }
        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: #adb5bd;
        }
        
        /* Tabla de solicitudes */
        .table-vacaciones th {
            background-color: #f8f9fa;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .table-vacaciones td {
            vertical-align: middle;
            font-size: 0.88rem;
        }
        .table-vacaciones tr:hover {
            background-color: #f1f8f4;
        }
        
        /* Periodo info */
        .periodo-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.85rem;
        }
        
        /* Alerta sin fecha */
        .alert-sin-fecha {
            border-left: 4px solid #ffc107;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stat-card .stat-value { font-size: 1.4rem; }
            .stat-card .stat-icon { width: 40px; height: 40px; font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php
        if ($es_mantenimiento) {
            include __DIR__ . '/../../includes/sidebar/sidebar_mantenimiento.php';
        } elseif ($es_ti) {
            include __DIR__ . '/../../includes/sidebar/sidebar_ti.php';
        } elseif (function_exists('es_usuario_gth') && es_usuario_gth()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_gth.php';
        } elseif (function_exists('es_usuario_colaborativo') && es_usuario_colaborativo()) {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>
        
        <!-- Contenido principal -->
        <main class="main-content">
            <div class="content-wrapper">
                
                <!-- Encabezado -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-calendar-check"></i> Mis Vacaciones</h1>
                            <p class="text-muted mb-0" style="font-size: 0.8rem;">
                                <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?> — 
                                <?php echo htmlspecialchars($_SESSION['departamento_nombre'] ?? ''); ?>
                            </p>
                        </div>
                        
                        <?php if ($resumen['tiene_fecha_ingreso'] && $resumen['dias_disponibles'] > 0): ?>
                        <div>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/nueva_solicitud_vacaciones.php" 
                               class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Nueva Solicitud
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Alertas del sistema -->
                <?php if ($alerta): ?>
                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $alerta['tipo'] === 'success' ? 'check-circle' : ($alerta['tipo'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                    <?php echo $alerta['mensaje']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!$resumen['tiene_fecha_ingreso']): ?>
                <!-- Sin fecha de ingreso -->
                <div class="alert alert-warning alert-sin-fecha">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Fecha de ingreso no registrada</h5>
                    <p class="mb-0">
                        Tu cuenta no tiene registrada una fecha de ingreso, por lo que no es posible calcular tus vacaciones.
                        Contacta al departamento de Sistemas o Gestión de Talento Humano para actualizar tu información.
                    </p>
                </div>
                
                <?php else: ?>
                
                <!-- Cards de resumen -->
                <div class="row g-3 mb-4">
                    <!-- Card: Antigüedad -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Antigüedad</div>
                                    </div>
                                </div>
                                <div class="stat-value text-primary">
                                    <?php 
                                    $ant = $resumen['antiguedad'];
                                    echo $ant['anios']; 
                                    ?>
                                    <span style="font-size: 0.9rem; font-weight: 400;">
                                        año<?php echo $ant['anios'] != 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                <div class="stat-sub">
                                    <?php echo $resumen['antiguedad_texto']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card: Días por periodo -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                                        <i class="bi bi-calendar-range"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Días por Periodo</div>
                                    </div>
                                </div>
                                <div class="stat-value text-info">
                                    <?php echo $resumen['dias_correspondientes']; ?>
                                    <span style="font-size: 0.9rem; font-weight: 400;">días</span>
                                </div>
                                <div class="stat-sub">
                                    Según LFT (año <?php echo $resumen['periodo']['anio_periodo'] ?? '-'; ?>°)
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card: Días tomados -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                                        <i class="bi bi-calendar-minus"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Días Tomados/Solicitados</div>
                                    </div>
                                </div>
                                <div class="stat-value text-warning">
                                    <?php echo $resumen['dias_tomados']; ?>
                                    <span style="font-size: 0.9rem; font-weight: 400;">días</span>
                                </div>
                                <div class="stat-sub">
                                    En este periodo vacacional
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card: Días disponibles -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div class="stat-label">Días Disponibles</div>
                                    </div>
                                </div>
                                <div class="stat-value text-success">
                                    <?php echo $resumen['dias_disponibles']; ?>
                                    <span style="font-size: 0.9rem; font-weight: 400;">días</span>
                                </div>
                                <div class="stat-sub">
                                    <?php if ($resumen['dias_disponibles'] <= 0): ?>
                                        <span class="text-danger">Sin días disponibles</span>
                                    <?php else: ?>
                                        Disponibles para solicitar
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info del periodo -->
                <?php if ($resumen['periodo']): ?>
                <div class="periodo-info mb-4">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <div>
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            <strong>Periodo vacacional actual:</strong>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark border">
                                <?php echo fecha_corta_es($resumen['periodo']['inicio']); ?>
                            </span>
                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                            <span class="badge bg-light text-dark border">
                                <?php echo fecha_corta_es($resumen['periodo']['fin']); ?>
                            </span>
                        </div>
                        <div class="text-muted">
                            <small>
                                Ingreso: <?php echo fecha_corta_es($resumen['fecha_ingreso']); ?> |
                                No. Nómina: <?php echo htmlspecialchars($_SESSION['no_nomina'] ?? 'N/A'); ?> |
                                Puesto: <?php echo htmlspecialchars($_SESSION['puesto'] ?? 'N/A'); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de solicitudes del periodo -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul me-2"></i>Mis Solicitudes del Periodo
                            </h5>
                            <span class="badge bg-secondary">
                                <?php echo count($resumen['solicitudes']); ?> solicitud<?php echo count($resumen['solicitudes']) != 1 ? 'es' : ''; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($resumen['solicitudes'])): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-1">No tienes solicitudes en este periodo.</p>
                            <?php if ($resumen['dias_disponibles'] > 0): ?>
                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/nueva_solicitud_vacaciones.php" 
                               class="btn btn-outline-primary btn-sm mt-2">
                                <i class="bi bi-plus-circle me-1"></i> Crear tu primera solicitud
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vacaciones mb-0">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Fecha Inicio</th>
                                        <th>Fecha Fin</th>
                                        <th class="text-center">Días</th>
                                        <th>Estado</th>
                                        <th>Creada</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumen['solicitudes'] as $sol): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($sol['folio']); ?></strong>
                                        </td>
                                        <td><?php echo fecha_corta_es($sol['fecha_inicio']); ?></td>
                                        <td><?php echo fecha_corta_es($sol['fecha_fin']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                <?php echo $sol['dias_solicitados']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo badge_estado_vacaciones($sol['estado']); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo fecha_corta_es($sol['fecha_creacion']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo URL_BASE; ?>dashboard/vacaciones/ver_solicitud_vacaciones.php?id=<?php echo $sol['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($sol['estado'] === 'pendiente_admin'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-cancelar" 
                                                    data-id="<?php echo $sol['id']; ?>" 
                                                    data-folio="<?php echo htmlspecialchars($sol['folio']); ?>"
                                                    title="Cancelar solicitud">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
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
                
                <!-- Tabla de referencia LFT (colapsable) -->
                <div class="mt-4">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#tablaLFT">
                        <i class="bi bi-book me-1"></i> Ver tabla de días por antigüedad (LFT)
                    </button>
                    <div class="collapse mt-2" id="tablaLFT">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6><i class="bi bi-info-circle me-1"></i> Días de vacaciones según la Ley Federal del Trabajo (Art. 76, Reforma 2023)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.82rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Año</th>
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <th class="text-center <?php echo ($resumen['periodo'] && $resumen['periodo']['anio_periodo'] == $i) ? 'table-success fw-bold' : ''; ?>">
                                                    <?php echo $i; ?>°
                                                </th>
                                                <?php endfor; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <th class="table-light">Días</th>
                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <td class="text-center <?php echo ($resumen['periodo'] && $resumen['periodo']['anio_periodo'] == $i) ? 'table-success fw-bold' : ''; ?>">
                                                    <?php echo dias_vacaciones_lft($i); ?>
                                                </td>
                                                <?php endfor; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    A partir del 6° año se incrementan 2 días cada 5 años de servicio.
                                    Los días hábiles se cuentan de Lunes a Sábado.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; // tiene_fecha_ingreso ?>
                
            </div>
        </main>
    </div>
    
    <!-- Modal de confirmación para cancelar -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>Cancelar Solicitud
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas cancelar la solicitud <strong id="folioACancelar"></strong>?</p>
                    <p class="text-muted mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener</button>
                    <form id="formCancelar" method="POST" action="<?php echo URL_BASE; ?>dashboard/vacaciones/procesar_vacaciones.php" class="d-inline">
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="solicitud_id" id="idACancelar" value="">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-1"></i> Sí, cancelar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Botones de cancelar
        document.querySelectorAll('.btn-cancelar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('idACancelar').value = this.dataset.id;
                document.getElementById('folioACancelar').textContent = this.dataset.folio;
                new bootstrap.Modal(document.getElementById('modalCancelar')).show();
            });
        });
        
        // Sidebar hamburger (patrón existente)
        const hamburgerBtn = document.querySelector('.hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
                this.setAttribute('aria-expanded', sidebar.classList.contains('active'));
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                this.classList.remove('active');
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
            });
        }
    });
    </script>
</body>
</html>