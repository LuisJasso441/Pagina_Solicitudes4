<?php
/**
 * Ver detalle de Movimiento EPP
 * Ubicación: dashboard/inventario_epp/ver_movimiento.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/inventario_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    establecer_alerta('error', 'No tienes acceso a este módulo.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { establecer_alerta('error', 'ID no válido.'); header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php?vista=movimientos'); exit; }

$movimiento = obtener_movimiento_por_id($id);
if (!$movimiento) { establecer_alerta('error', 'Movimiento no encontrado.'); header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php?vista=movimientos'); exit; }

$es_entrada = $movimiento['tipo_movimiento'] === 'Entrada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimiento #<?php echo $id; ?> | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/formularios.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
    <style>
        .detail-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1.25rem; }
        .detail-card h5 { font-size: 1rem; color: #2c3e50; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        .info-row { display: flex; padding: 0.5rem 0; border-bottom: 1px solid #f1f3f5; font-size: 0.85rem; }
        .info-label { width: 200px; font-weight: 600; color: #6c757d; flex-shrink: 0; }
        .info-value { color: #2d3748; }
        .tipo-badge { font-size: 1.1rem; padding: 0.4rem 1rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;">
                            <i class="bi bi-arrow-left-right text-primary"></i> Detalle del Movimiento
                        </h2>
                        <small class="text-muted">Movimiento #<?php echo $id; ?></small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php?vista=movimientos" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver a Movimientos
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="detail-card">
                            <h5><i class="bi bi-info-circle"></i> Información del Movimiento</h5>
                            
                            <div class="info-row">
                                <div class="info-label">Tipo de Movimiento</div>
                                <div class="info-value">
                                    <span class="badge tipo-badge <?php echo $es_entrada ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="bi <?php echo $es_entrada ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle'; ?>"></i>
                                        <?php echo $movimiento['tipo_movimiento']; ?>
                                    </span>
                                    <?php if ($movimiento['es_automatico']): ?>
                                    <span class="badge bg-secondary ms-2">Automático</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha del Movimiento</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Categoría</div>
                                <div class="info-value"><?php echo htmlspecialchars($movimiento['categoria']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Artículo</div>
                                <div class="info-value">
                                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $movimiento['inventario_epp_id']; ?>">
                                        <?php echo htmlspecialchars($movimiento['articulo']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php if ($movimiento['talla']): ?>
                            <div class="info-row">
                                <div class="info-label">Talla / Calzado</div>
                                <div class="info-value"><?php echo htmlspecialchars($movimiento['talla']); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <div class="info-label">Cantidad</div>
                                <div class="info-value fw-bold" style="font-size: 1.1rem;"><?php echo $movimiento['cantidad']; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Stock Resultante</div>
                                <div class="info-value"><?php echo $movimiento['stock_resultante']; ?></div>
                            </div>
                            <?php if ($movimiento['nombre_trabajador']): ?>
                            <div class="info-row">
                                <div class="info-label">Nombre del Trabajador</div>
                                <div class="info-value"><?php echo htmlspecialchars($movimiento['nombre_trabajador']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($movimiento['observaciones']): ?>
                            <div class="info-row">
                                <div class="info-label">Observaciones</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($movimiento['observaciones'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <hr class="my-3">
                            
                            <div class="info-row">
                                <div class="info-label">Registrado por</div>
                                <div class="info-value"><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?> (<?php echo htmlspecialchars($movimiento['departamento']); ?>)</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha de Registro</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($movimiento['fecha_creacion'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="detail-card text-center">
                            <h5><i class="bi bi-box-seam"></i> Stock Actual del Artículo</h5>
                            <div style="font-size: 2.5rem; font-weight: 700; color: <?php echo ($movimiento['stock_actual'] ?? 0) > 0 ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $movimiento['stock_actual'] ?? 'N/A'; ?>
                            </div>
                            <small class="text-muted"><?php echo $movimiento['unidad'] ?? ''; ?></small>
                            <hr>
                            <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/ver_epp.php?id=<?php echo $movimiento['inventario_epp_id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> Ver Artículo Completo
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
    
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        bodyElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
        themeToggle.addEventListener('click', () => {
            const newTheme = bodyElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => themeToggle.classList.remove('rotating'), 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
</body>
</html>