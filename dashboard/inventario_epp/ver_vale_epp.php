<?php
/**
 * Ver Vale de Entrega de EPP
 * Almacén de Refacciones puede confirmar entrega aquí
 * Ubicación: dashboard/inventario_epp/ver_vale_epp.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
$permisos_vale = verificar_permisos_vales();

$permisos_vale = verificar_permisos_vales();

if (!$permisos['tiene_acceso'] && !$permisos_vale['puede_ver']) {
    establecer_alerta('error', 'No tienes acceso a esta seccion.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$vale_id = (int) ($_GET['id'] ?? 0);
if (!$vale_id) {
    establecer_alerta('error', 'Vale no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
    exit;
}

$vale = obtener_vale_epp($vale_id);
if (!$vale) {
    establecer_alerta('error', 'Vale no encontrado.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
    exit;
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'confirmar_entrega' && $permisos_vale['puede_confirmar']) {
        $resultado = confirmar_entrega_vale($vale_id, [
            'usuario_id' => $_SESSION['usuario_id'],
            'usuario_nombre' => $_SESSION['nombre_completo'],
            'departamento' => $_SESSION['departamento'],
            'observaciones_entrega' => trim($_POST['observaciones_entrega'] ?? '')
        ]);
        establecer_alerta($resultado['success'] ? 'success' : 'error', $resultado['message']);
        header('Location: ' . URL_BASE . 'dashboard/inventario_epp/ver_vale_epp.php?id=' . $vale_id);
        exit;
    }
    
    if ($accion === 'cancelar' && $permisos_vale['puede_cancelar']) {
        $resultado = cancelar_vale_epp($vale_id, [
            'usuario_id' => $_SESSION['usuario_id'],
            'usuario_nombre' => $_SESSION['nombre_completo'],
            'motivo_cancelacion' => trim($_POST['motivo_cancelacion'] ?? '')
        ]);
        establecer_alerta($resultado['success'] ? 'success' : 'error', $resultado['message']);
        header('Location: ' . URL_BASE . 'dashboard/inventario_epp/ver_vale_epp.php?id=' . $vale_id);
        exit;
    }
}

// Recargar vale después de acción
$vale = obtener_vale_epp($vale_id);
$page_title = "Vale " . $vale['folio'];

$estado_class = match($vale['estado']) {
    'Pendiente' => 'warning',
    'Entregado' => 'success',
    'Cancelado' => 'danger',
    default => 'secondary'
};
$estado_icon = match($vale['estado']) {
    'Pendiente' => 'bi-clock',
    'Entregado' => 'bi-check-circle-fill',
    'Cancelado' => 'bi-x-circle-fill',
    default => 'bi-question-circle'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/dashboard.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/base/variables.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css" rel="stylesheet">
    <link href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css" rel="stylesheet">
    <style>
        .vale-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; max-width: 900px; }
        .vale-card-header { padding: 1.25rem 1.5rem; color: #fff; }
        .vale-card-body { padding: 1.5rem; }
        .vale-info-row { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .vale-info-item { flex: 1; min-width: 200px; }
        .vale-info-item .label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c757d; letter-spacing: 0.3px; }
        .vale-info-item .value { font-size: 1rem; font-weight: 500; color: #2c3e50; }
        .tabla-lineas { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .tabla-lineas thead th { background: #f8f9fa; padding: 8px 10px; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; color: #495057; border-bottom: 2px solid #dee2e6; }
        .tabla-lineas tbody td { padding: 8px 10px; border-bottom: 1px solid #e9ecef; }
        .badge-motivo { font-size: 0.75rem; font-weight: 600; padding: 3px 8px; border-radius: 4px; }
        .badge-nuevo { background: #d4edda; color: #155724; }
        .badge-cambio { background: #cce5ff; color: #004085; }
        .badge-reemplazo { background: #fff3cd; color: #856404; }
        .badge-otro { background: #e2e3e5; color: #383d41; }
        .acciones-box { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .timeline-item { display: flex; gap: 0.75rem; padding: 0.5rem 0; }
        .timeline-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .timeline-text { font-size: 0.85rem; }
        .timeline-text .fecha { font-size: 0.75rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php
        $depto_sb = $_SESSION['departamento_codigo'] ?? strtolower(trim($_SESSION['departamento'] ?? ''));
        if ($depto_sb === 'almacen_residuos') {
            $sidebar_file = __DIR__ . "/../../includes/sidebar/sidebar_sec.php";
            if (file_exists($sidebar_file)) include $sidebar_file;
        } else {
            include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php";
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.3rem;">
                            <i class="bi bi-file-earmark-text text-danger"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Detalle del vale de entrega de EPP</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/descargar_vale_pdf.php?id=<?php echo $vale['id']; ?>" 
                           class="btn btn-outline-danger btn-sm" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                        <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver a Vales
                        </a>
                    </div>
                </div>
                
                <?php echo mostrar_alerta(); ?>
                
                <div class="vale-card">
                    <!-- Header con estado -->
                    <div class="vale-card-header bg-<?php echo $estado_class; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0" style="font-size: 1.1rem;">
                                    <i class="bi <?php echo $estado_icon; ?>"></i> 
                                    Vale <?php echo htmlspecialchars($vale['folio']); ?>
                                </h4>
                                <small style="opacity: 0.85;">Estado: <?php echo $vale['estado']; ?></small>
                                <?php if ($vale['estado'] === 'Pendiente'):
                                    $expiracion = verificar_expiracion_vale($vale['fecha_creacion']);
                                ?>
                                <br><small style="opacity: 0.9;"><i class="bi bi-clock"></i> Vigencia: <?php echo $expiracion['texto']; ?><?php if ($expiracion['expirado']): ?> <span class="badge bg-danger">EXPIRADO</span><?php endif; ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-light text-dark" style="font-size: 0.85rem;">
                                <?php echo date('d/m/Y H:i', strtotime($vale['fecha_creacion'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="vale-card-body">
                        
                        <!-- Información del empleado -->
                        <div class="vale-info-row">
                            <div class="vale-info-item">
                                <div class="label">Nombre del Empleado</div>
                                <div class="value"><i class="bi bi-person"></i> <?php echo htmlspecialchars($vale['nombre_empleado']); ?></div>
                            </div>
                            <div class="vale-info-item">
                                <div class="label">Área</div>
                                <div class="value"><i class="bi bi-building"></i> <?php echo htmlspecialchars($vale['area']); ?></div>
                            </div>
                            <div class="vale-info-item">
                                <div class="label">Creado por</div>
                                <div class="value"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($vale['creado_por_nombre']); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($vale['observaciones']): ?>
                        <div class="mb-3">
                            <div class="label" style="font-size:0.7rem;font-weight:600;text-transform:uppercase;color:#6c757d;">Observaciones</div>
                            <div style="font-size:0.9rem;color:#495057;"><?php echo nl2br(htmlspecialchars($vale['observaciones'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Tabla de artículos -->
                        <h6 class="mt-3 mb-2" style="font-size:0.85rem;font-weight:600;color:#2c3e50;">
                            <i class="bi bi-list-check"></i> Artículos (<?php echo count($vale['lineas']); ?>)
                        </h6>
                        
                        <div style="overflow-x: auto;">
                            <table class="tabla-lineas">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Descripción</th>
                                        <th style="width: 80px;">Talla</th>
                                        <th style="width: 80px;">Cantidad</th>
                                        <th style="width: 100px;">Motivo</th>
                                        <?php if ($vale['estado'] === 'Pendiente'): ?>
                                        <th style="width: 90px;">Stock Disp.</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vale['lineas'] as $i => $linea): 
                                        $motivo_class = match($linea['motivo']) {
                                            'Nuevo' => 'badge-nuevo',
                                            'Cambio' => 'badge-cambio',
                                            'Reemplazo' => 'badge-reemplazo',
                                            default => 'badge-otro'
                                        };
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted"><?php echo $i + 1; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($linea['descripcion']); ?>
                                            <?php if ($linea['categoria']): ?>
                                            <br><small class="text-muted">[<?php echo htmlspecialchars($linea['categoria']); ?>]</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $linea['talla'] ? htmlspecialchars($linea['talla']) : '-'; ?></td>
                                        <td class="text-center fw-bold"><?php echo $linea['cantidad']; ?></td>
                                        <td>
                                            <span class="badge-motivo <?php echo $motivo_class; ?>">
                                                <?php echo $linea['motivo']; ?>
                                                <?php if ($linea['motivo'] === 'Otro' && $linea['motivo_otro']): ?>
                                                : <?php echo htmlspecialchars($linea['motivo_otro']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <?php if ($vale['estado'] === 'Pendiente'): ?>
                                        <td class="text-center">
                                            <?php if ($linea['stock_actual'] !== null): ?>
                                                <?php
                                                $suficiente = (int)$linea['stock_actual'] >= (int)$linea['cantidad'];
                                                ?>
                                                <span class="fw-bold <?php echo $suficiente ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $linea['stock_actual']; ?>
                                                    <?php if (!$suficiente): ?><i class="bi bi-exclamation-triangle"></i><?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Timeline -->
                        <h6 class="mt-4 mb-2" style="font-size:0.85rem;font-weight:600;color:#2c3e50;">
                            <i class="bi bi-clock-history"></i> Historial
                        </h6>
                        
                        <div class="timeline-item">
                            <div class="timeline-dot bg-primary"></div>
                            <div class="timeline-text">
                                <strong>Creado</strong> por <?php echo htmlspecialchars($vale['creado_por_nombre']); ?>
                                <div class="fecha"><?php echo date('d/m/Y H:i', strtotime($vale['fecha_creacion'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($vale['estado'] === 'Entregado'): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-success"></div>
                            <div class="timeline-text">
                                <strong>Entregado</strong> por <?php echo htmlspecialchars($vale['entregado_por_nombre']); ?>
                                <?php if ($vale['observaciones_entrega']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($vale['observaciones_entrega']); ?></small>
                                <?php endif; ?>
                                <div class="fecha"><?php echo date('d/m/Y H:i', strtotime($vale['fecha_entrega'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vale['estado'] === 'Cancelado'): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-danger"></div>
                            <div class="timeline-text">
                                <strong>Cancelado</strong> por <?php echo htmlspecialchars($vale['cancelado_por_nombre']); ?>
                                <?php if ($vale['motivo_cancelacion']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($vale['motivo_cancelacion']); ?></small>
                                <?php endif; ?>
                                <div class="fecha"><?php echo date('d/m/Y H:i', strtotime($vale['fecha_cancelacion'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- ============================================ -->
                        <!-- ACCIONES según permisos y estado -->
                        <!-- ============================================ -->
                        <?php if ($vale['estado'] === 'Pendiente'): ?>
                        <?php
                        $vale_expirado = false;
                        $exp_check = verificar_expiracion_vale($vale['fecha_creacion']);
                        $vale_expirado = $exp_check['expirado'];
                        ?>
                        
                            <!-- Almacen: Confirmar entrega (solo si no expirado) -->
                            <?php if ($permisos_vale['puede_confirmar'] && !$vale_expirado): ?>
                            <div class="acciones-box">
                                <h6 style="font-size: 0.85rem; font-weight: 700; color: #155724;">
                                    <i class="bi bi-check-circle"></i> Confirmar Entrega
                                </h6>
                                <p style="font-size: 0.8rem; color: #6c757d; margin-bottom: 0.75rem;">
                                    Al confirmar, el stock se descontara automaticamente del inventario.
                                </p>
                                <form method="POST" onsubmit="return confirm('Confirmar entrega del vale <?php echo $vale['folio']; ?>? Se descontara el stock de cada articulo.');">
                                    <input type="hidden" name="accion" value="confirmar_entrega">
                                    <div class="mb-2">
                                        <textarea name="observaciones_entrega" class="form-control form-control-sm" rows="2" 
                                                  placeholder="Observaciones de la entrega (opcional)"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-circle"></i> Confirmar Entrega
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($permisos_vale['puede_confirmar'] && $vale_expirado): ?>
                            <div class="acciones-box" style="border-color: #f5c6cb;">
                                <p class="text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> Vale Expirado</p>
                                <p style="font-size: 0.8rem; color: #6c757d; margin-bottom: 0;">Han pasado mas de 72 horas desde la creacion. Este vale debe cancelarse y crear uno nuevo.</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Cancelar (solo Seguridad) -->
                            <?php if ($permisos_vale['puede_cancelar']): ?>
                            <div class="acciones-box mt-2" style="border-color: #f5c6cb;">
                                <h6 style="font-size: 0.85rem; font-weight: 700; color: #721c24;">
                                    <i class="bi bi-x-circle"></i> Cancelar Vale
                                </h6>
                                <form method="POST" onsubmit="return confirm('¿Cancelar el vale <?php echo $vale['folio']; ?>? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="accion" value="cancelar">
                                    <div class="mb-2">
                                        <textarea name="motivo_cancelacion" class="form-control form-control-sm" rows="2" 
                                                  placeholder="Motivo de cancelación" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-x-circle"></i> Cancelar Vale
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        
                        <?php endif; ?>
                        
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
</body>
</html>