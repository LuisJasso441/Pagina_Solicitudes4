<?php
/**
 * Ver detalle de artículo EPP
 * Ubicación: dashboard/inventario_epp/ver_epp.php
 * 
 * Muestra toda la información del artículo + historial de movimientos
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
if (!$id) {
    establecer_alerta('error', 'ID no válido.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

$epp = obtener_epp_por_id($id);
if (!$epp) {
    establecer_alerta('error', 'Artículo no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/inventario_epp.php');
    exit;
}

// Obtener historial de movimientos de este artículo
$movimientos = obtener_movimientos_epp(['inventario_epp_id' => $id]);

$page_title = "Ver EPP: " . $epp['articulo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo NOMBRE_SISTEMA; ?></title>
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
        .detail-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 1.25rem; margin-bottom: 1rem; }
        .detail-card h5 { font-size: 1rem; color: #2c3e50; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        .info-row { display: flex; padding: 0.4rem 0; border-bottom: 1px solid #f1f3f5; font-size: 0.85rem; }
        .info-label { width: 180px; font-weight: 600; color: #6c757d; flex-shrink: 0; }
        .info-value { color: #2d3748; }
        .badge-entrada { background: #28a745; }
        .badge-salida { background: #dc3545; }
        .stock-grande { font-size: 2rem; font-weight: 700; }
        .stock-ok { color: #28a745; }
        .stock-bajo { color: #ffc107; }
        .stock-cero { color: #dc3545; }
        .tabla-movimientos { font-size: 0.8rem; }
        .tabla-movimientos th { background: #f8f9fa; font-size: 0.75rem; text-transform: uppercase; }
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
                            <i class="bi bi-shield-check text-success"></i> <?php echo htmlspecialchars($epp['articulo']); ?>
                        </h2>
                        <small class="text-muted"><?php echo htmlspecialchars($epp['categoria']); ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($permisos['puede_crear']): ?>
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/registrar_movimiento.php?epp_id=<?php echo $epp['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-left-right"></i> Registrar Movimiento
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/inventario_epp.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Info del artículo -->
                    <div class="col-md-7">
                        <div class="detail-card">
                            <h5><i class="bi bi-info-circle"></i> Información del Artículo</h5>
                            
                            <div class="info-row">
                                <div class="info-label">Categoría</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['categoria']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Artículo</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['articulo']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Unidad</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['unidad']); ?></div>
                            </div>
                            <?php if ($epp['lote_identificador']): ?>
                            <div class="info-row">
                                <div class="info-label">Lote</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['lote_identificador']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($epp['talla']): ?>
                            <div class="info-row">
                                <div class="info-label">Talla / Calzado</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['talla']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($epp['precio']): ?>
                            <div class="info-row">
                                <div class="info-label">Precio</div>
                                <div class="info-value">$<?php echo number_format($epp['precio'], 2); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($epp['nombre_proveedor']): ?>
                            <div class="info-row">
                                <div class="info-label">Proveedor</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['nombre_proveedor']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($epp['observaciones']): ?>
                            <div class="info-row">
                                <div class="info-label">Observaciones</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($epp['observaciones'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <div class="info-label">Agregado por</div>
                                <div class="info-value"><?php echo htmlspecialchars($epp['usuario_creador_nombre']); ?> (<?php echo htmlspecialchars($epp['departamento_creador']); ?>)</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha de creación</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($epp['fecha_creacion'])); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Última edición</div>
                                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($epp['fecha_ultima_edicion'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock -->
                    <div class="col-md-5">
                        <div class="detail-card text-center">
                            <h5><i class="bi bi-box-seam"></i> Stock Actual</h5>
                            <?php 
                            $stock_class = 'stock-ok';
                            if ($epp['stock'] == 0) $stock_class = 'stock-cero';
                            elseif ($epp['stock'] <= 5) $stock_class = 'stock-bajo';
                            ?>
                            <div class="stock-grande <?php echo $stock_class; ?>"><?php echo number_format($epp['stock']); ?></div>
                            <small class="text-muted"><?php echo $epp['unidad']; ?></small>
                        </div>
                        
                        <div class="detail-card">
                            <h5><i class="bi bi-bar-chart"></i> Resumen de Movimientos</h5>
                            <?php
                            $total_entradas = 0;
                            $total_salidas = 0;
                            foreach ($movimientos as $m) {
                                if ($m['tipo_movimiento'] === 'Entrada') $total_entradas += $m['cantidad'];
                                else $total_salidas += $m['cantidad'];
                            }
                            ?>
                            <div class="d-flex justify-content-around">
                                <div class="text-center">
                                    <div class="fw-bold text-success" style="font-size: 1.5rem;"><?php echo $total_entradas; ?></div>
                                    <small class="text-muted">Total Entradas</small>
                                </div>
                                <div class="text-center">
                                    <div class="fw-bold text-danger" style="font-size: 1.5rem;"><?php echo $total_salidas; ?></div>
                                    <small class="text-muted">Total Salidas</small>
                                </div>
                                <div class="text-center">
                                    <div class="fw-bold text-primary" style="font-size: 1.5rem;"><?php echo count($movimientos); ?></div>
                                    <small class="text-muted">Movimientos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historial de Movimientos -->
                <div class="detail-card">
                    <h5><i class="bi bi-clock-history"></i> Historial de Movimientos</h5>
                    
                    <?php if (empty($movimientos)): ?>
                    <p class="text-muted text-center py-3">No hay movimientos registrados.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover tabla-movimientos mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Stock Resultante</th>
                                    <th>Trabajador</th>
                                    <th>Observaciones</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $mov['tipo_movimiento'] === 'Entrada' ? 'badge-entrada' : 'badge-salida'; ?>">
                                            <?php echo $mov['tipo_movimiento']; ?>
                                        </span>
                                        <?php if ($mov['es_automatico']): ?>
                                        <span class="badge bg-secondary" style="font-size: 0.65rem;">Auto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?php echo $mov['cantidad']; ?></td>
                                    <td><?php echo $mov['stock_resultante']; ?></td>
                                    <td><?php echo $mov['nombre_trabajador'] ? htmlspecialchars($mov['nombre_trabajador']) : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php echo $mov['observaciones'] ? htmlspecialchars(mb_substr($mov['observaciones'], 0, 60)) . (mb_strlen($mov['observaciones']) > 60 ? '...' : '') : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
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