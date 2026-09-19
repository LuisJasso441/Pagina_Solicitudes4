<?php
/**
 * Historial de Movimientos de Inventario
 * dashboard/salidas_envases/inventario/movimientos_inventario.php
 *
 * Solo lectura para todos los departamentos autorizados.
 * Almacén de Residuos ve además el botón para volver a la vista principal.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/inventario_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if (!in_array($dept, ['logistica', 'almacen_residuos', 'ventas'], true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

// ---- Filtros ----
$f_espec  = (int) ($_GET['especificacion_id'] ?? 0);
$f_tipo   = trim($_GET['tipo_movimiento'] ?? '');
$f_desde  = trim($_GET['fecha_desde'] ?? '');
$f_hasta  = trim($_GET['fecha_hasta'] ?? '');
$pagina   = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pag  = 30;

$filtros_activos = [];
if ($f_espec > 0)     $filtros_activos['especificacion_id'] = $f_espec;
if ($f_tipo !== '')   $filtros_activos['tipo_movimiento']   = $f_tipo;
if ($f_desde !== '')  $filtros_activos['fecha_desde']       = $f_desde;
if ($f_hasta !== '')  $filtros_activos['fecha_hasta']       = $f_hasta;

$total       = contar_movimientos($filtros_activos);
$total_pags  = max(1, (int) ceil($total / $por_pag));
$pagina      = min($pagina, $total_pags);
$offset      = ($pagina - 1) * $por_pag;
$movimientos = obtener_movimientos($filtros_activos, $por_pag, $offset);

// Para el dropdown de especificaciones
$todas_specs = obtener_especificaciones(null, true);

$tipos_movimiento_labels = [
    'entrada'    => ['label' => 'Entrada',    'clase' => 'success', 'icono' => 'arrow-down-circle', 'signo' => '+'],
    'salida'     => ['label' => 'Salida',     'clase' => 'danger',  'icono' => 'arrow-up-circle',   'signo' => '−'],
    'ajuste'     => ['label' => 'Ajuste',     'clase' => 'warning', 'icono' => 'sliders',            'signo' => '±'],
    'devolucion' => ['label' => 'Devolución', 'clase' => 'info',    'icono' => 'arrow-return-left','signo' => '+'],
    'inicial'    => ['label' => 'Inicial',    'clase' => 'primary', 'icono' => 'flag',              'signo' => '+'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Inventario - Verden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-clock-history"></i> Historial de Movimientos</h1>
                    <small class="text-muted">Registro completo de cambios en el inventario.</small>
                </div>
                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al inventario
                </a>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Especificación</label>
                            <select class="form-select form-select-sm" name="especificacion_id">
                                <option value="0">Todas</option>
                                <?php foreach ($todas_specs as $s): ?>
                                <option value="<?php echo (int) $s['id']; ?>"
                                    <?php echo $f_espec === (int) $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['tipo_nombre'] . ' — ' . $s['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Tipo</label>
                            <select class="form-select form-select-sm" name="tipo_movimiento">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_movimiento_labels as $key => $data): ?>
                                <option value="<?php echo $key; ?>" <?php echo $f_tipo === $key ? 'selected' : ''; ?>>
                                    <?php echo $data['label']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Desde</label>
                            <input type="date" class="form-control form-control-sm" name="fecha_desde"
                                   value="<?php echo htmlspecialchars($f_desde); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Hasta</label>
                            <input type="date" class="form-control form-control-sm" name="fecha_hasta"
                                   value="<?php echo htmlspecialchars($f_hasta); ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php"
                               class="btn btn-sm btn-outline-secondary flex-grow-1">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info total -->
            <p class="text-muted small">
                Mostrando <?php echo count($movimientos); ?> de <?php echo number_format($total); ?> movimientos.
            </p>

            <?php if (empty($movimientos)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No hay movimientos registrados con los filtros seleccionados.
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:140px;">Fecha</th>
                                    <th style="min-width:110px;">Tipo</th>
                                    <th style="min-width:200px;">Especificación</th>
                                    <th class="text-end" style="min-width:90px;">Anterior</th>
                                    <th class="text-end" style="min-width:100px;">Movimiento</th>
                                    <th class="text-end" style="min-width:90px;">Resultante</th>
                                    <th>Motivo</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $m):
                                    $tipoData = $tipos_movimiento_labels[$m['tipo_movimiento']] ?? [
                                        'label' => $m['tipo_movimiento'],
                                        'clase' => 'secondary',
                                        'icono' => 'question-circle',
                                        'signo' => '',
                                    ];
                                    // Para ajuste, el signo depende del delta
                                    if ($m['tipo_movimiento'] === 'ajuste') {
                                        $delta = (int) $m['cantidad_resultante'] - (int) $m['cantidad_anterior'];
                                        $signo = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
                                        $cant_display = $signo . number_format(abs($delta));
                                    } else {
                                        $cant_display = $tipoData['signo'] . number_format((int) $m['cantidad_movimiento']);
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($m['creado_en'])); ?></small><br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($m['creado_en'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $tipoData['clase']; ?>">
                                            <i class="bi bi-<?php echo $tipoData['icono']; ?>"></i>
                                            <?php echo $tipoData['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['tipo_nombre']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($m['especificacion_nombre']); ?></small>
                                    </td>
                                    <td class="text-end"><?php echo number_format((int) $m['cantidad_anterior']); ?></td>
                                    <td class="text-end fw-bold text-<?php echo $tipoData['clase']; ?>">
                                        <?php echo $cant_display; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo number_format((int) $m['cantidad_resultante']); ?></td>
                                    <td>
                                        <?php if (!empty($m['motivo'])): ?>
                                            <small><?php echo htmlspecialchars($m['motivo']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                        <?php if (!empty($m['referencia_tipo'])): ?>
                                            <br><small class="text-muted"><i class="bi bi-link-45deg"></i>
                                                <?php echo htmlspecialchars($m['referencia_tipo']); ?>
                                                <?php echo $m['referencia_id'] ? '#' . (int) $m['referencia_id'] : ''; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($m['usuario_nombre'] ?? '—'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <?php if ($total_pags > 1):
                    $base_url = URL_BASE . 'dashboard/salidas_envases/inventario/movimientos_inventario.php?';
                    $qs = http_build_query(array_filter([
                        'especificacion_id' => $f_espec ?: null,
                        'tipo_movimiento'   => $f_tipo ?: null,
                        'fecha_desde'       => $f_desde ?: null,
                        'fecha_hasta'       => $f_hasta ?: null,
                    ]));
                    $sep = $qs ? '&' : '';
                ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url . $qs . $sep . 'pagina=' . max(1, $pagina - 1); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">Página <?php echo $pagina; ?> de <?php echo $total_pags; ?></span>
                        </li>
                        <li class="page-item <?php echo $pagina >= $total_pags ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url . $qs . $sep . 'pagina=' . min($total_pags, $pagina + 1); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
</body>
</html>