<?php
/**
 * Inventario de Envases
 * dashboard/salidas_envases/inventario/inventario.php
 *
 * Permisos:
 *   - Almacén de Residuos: puede registrar movimientos.
 *   - Logística y Ventas: solo lectura.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';
require_once __DIR__ . '/../../../includes/salidas_envases/inventario_funciones.php';

// ---- Autenticación ----
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ---- Autorización ----
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$puede_editar = puede_administrar_inventario();

// ---- Filtros ----
$filtro_tipo_id  = (int) ($_GET['tipo_id'] ?? 0);
$filtro_busqueda = trim($_GET['busqueda'] ?? '');

$filtros_activos = [];
if ($filtro_tipo_id > 0)          $filtros_activos['tipo_id']  = $filtro_tipo_id;
if ($filtro_busqueda !== '')      $filtros_activos['busqueda'] = $filtro_busqueda;

// ---- Datos ----
$tipos      = obtener_tipos_envase(false);
$inventario = obtener_inventario($filtros_activos);

// Mapa de stock por especificación (para el JS del modal)
$stock_map = [];
foreach ($inventario as $item) {
    $stock_map[(int) $item['especificacion_id']] = (int) $item['cantidad_actual'];
}

// Métricas
$total_specs         = count($inventario);
$total_envases       = array_sum(array_map(static fn($i) => (int) $i['cantidad_actual'], $inventario));
$specs_con_stock     = count(array_filter($inventario, static fn($i) => (int) $i['cantidad_actual'] > 0));
$specs_sin_registro  = count(array_filter($inventario, static fn($i) => (int) $i['tiene_registro'] === 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Envases - Verden</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <style>
        .metric-card { border-left: 4px solid var(--bs-primary); }
        .metric-card.success { border-left-color: var(--bs-success); }
        .metric-card.warning { border-left-color: var(--bs-warning); }
        .metric-card.secondary { border-left-color: var(--bs-secondary); }
        .stock-alto { color: #198754; font-weight: 600; }
        .stock-medio { color: #fd7e14; font-weight: 600; }
        .stock-cero { color: #6c757d; font-weight: 500; }
        .badge-sin-registro { background-color: #e9ecef; color: #6c757d; }
        .preview-calc {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 12px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Encabezado -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0"><i class="bi bi-boxes"></i> Inventario de Envases</h1>
                    <small class="text-muted">
                        <?php echo $puede_editar
                            ? 'Registra entradas, salidas y ajustes de stock.'
                            : 'Consulta el inventario de envases (solo lectura).'; ?>
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-clock-history"></i> Historial
                    </a>
                    <?php if ($puede_editar): ?>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                        <i class="bi bi-plus-circle"></i> Registrar Movimiento
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Métricas -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card metric-card">
                        <div class="card-body">
                            <small class="text-muted">Especificaciones</small>
                            <h4 class="mb-0"><?php echo $total_specs; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card metric-card success">
                        <div class="card-body">
                            <small class="text-muted">Total envases</small>
                            <h4 class="mb-0"><?php echo number_format($total_envases); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card metric-card warning">
                        <div class="card-body">
                            <small class="text-muted">Con stock</small>
                            <h4 class="mb-0"><?php echo $specs_con_stock; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card metric-card secondary">
                        <div class="card-body">
                            <small class="text-muted">Sin registro</small>
                            <h4 class="mb-0"><?php echo $specs_sin_registro; ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Buscar</label>
                            <input type="text" class="form-control form-control-sm" name="busqueda"
                                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                                   placeholder="Buscar tipo o especificación…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Tipo de envase</label>
                            <select class="form-select form-select-sm" name="tipo_id">
                                <option value="0">Todos los tipos</option>
                                <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo (int) $t['id']; ?>"
                                    <?php echo $filtro_tipo_id === (int) $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-funnel"></i> Filtrar
                            </button>
                            <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/inventario.php"
                               class="btn btn-sm btn-outline-secondary">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla -->
            <?php if (empty($inventario)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No hay especificaciones registradas
                    <?php echo !empty($filtros_activos) ? 'con los filtros seleccionados' : 'aún'; ?>.
                    <?php if ($total_specs === 0 && empty($filtros_activos)): ?>
                        Solicita a Logística que agregue tipos de envase primero.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:140px;">Tipo</th>
                                    <th style="min-width:180px;">Especificación</th>
                                    <th class="text-end" style="min-width:110px;">Stock actual</th>
                                    <th style="min-width:150px;">Última actualización</th>
                                    <th style="min-width:150px;">Actualizado por</th>
                                    <th class="text-center" style="min-width:100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventario as $item):
                                    $stock = (int) $item['cantidad_actual'];
                                    $tiene_reg = (int) $item['tiene_registro'] === 1;
                                    $cls = $stock === 0 ? 'stock-cero' : ($stock < 10 ? 'stock-medio' : 'stock-alto');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['tipo_nombre']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($item['especificacion_nombre']); ?>
                                        <?php if (!$tiene_reg): ?>
                                            <span class="badge badge-sin-registro ms-1">Sin registro</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?php echo $cls; ?>">
                                            <?php echo number_format($stock); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['actualizado_en']): ?>
                                            <small><?php echo date('d/m/Y H:i', strtotime($item['actualizado_en'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($item['actualizado_por_nombre'] ?? '—'); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/movimientos_inventario.php?especificacion_id=<?php echo (int) $item['especificacion_id']; ?>"
                                           class="btn btn-sm btn-outline-secondary" title="Ver historial">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <?php if ($puede_editar): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="abrirMovimientoPara(<?php echo (int) $item['especificacion_id']; ?>)"
                                                title="Registrar movimiento">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($puede_editar): ?>
    <!-- ================== MODAL: Registrar movimiento ================== -->
    <div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/inventario/guardar_movimiento_inventario.php" id="formMovimiento">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Registrar Movimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tipo de movimiento -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de movimiento: <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoEntrada" value="entrada" checked>
                                <label class="btn btn-outline-success" for="tipoEntrada">
                                    <i class="bi bi-arrow-down-circle"></i> Entrada
                                </label>
                                <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoSalida" value="salida">
                                <label class="btn btn-outline-danger" for="tipoSalida">
                                    <i class="bi bi-arrow-up-circle"></i> Salida
                                </label>
                                <input type="radio" class="btn-check" name="tipo_movimiento" id="tipoAjuste" value="ajuste">
                                <label class="btn btn-outline-warning" for="tipoAjuste">
                                    <i class="bi bi-sliders"></i> Ajuste
                                </label>
                            </div>
                            <small class="text-muted d-block mt-2" id="tipoAyuda">
                                <strong>Entrada:</strong> agrega envases al stock actual.
                            </small>
                        </div>

                        <!-- Especificación -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Especificación: <span class="text-danger">*</span></label>
                            <select class="form-select" name="especificacion_id" id="selectEspec" required>
                                <option value="">Seleccione especificación…</option>
                                <?php
                                // Agrupar por tipo para el optgroup
                                $agrupados = [];
                                foreach ($inventario as $item) {
                                    if ((int) $item['especificacion_activa'] === 0) continue;
                                    if ((int) $item['tipo_activo'] === 0) continue;
                                    $agrupados[$item['tipo_nombre']][] = $item;
                                }
                                foreach ($agrupados as $tipo_nombre => $items):
                                ?>
                                <optgroup label="<?php echo htmlspecialchars($tipo_nombre); ?>">
                                    <?php foreach ($items as $it): ?>
                                    <option value="<?php echo (int) $it['especificacion_id']; ?>"
                                            data-stock="<?php echo (int) $it['cantidad_actual']; ?>">
                                        <?php echo htmlspecialchars($it['especificacion_nombre']); ?>
                                        (stock: <?php echo (int) $it['cantidad_actual']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label class="form-label fw-bold" id="labelCantidad">
                                Cantidad a agregar: <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" name="cantidad" id="inputCantidad"
                                   min="0" step="1" required>
                            <small class="text-muted" id="cantidadAyuda">
                                Ingresa un número entero mayor a 0.
                            </small>
                        </div>

                        <!-- Preview -->
                        <div class="preview-calc mb-3" id="previewCalc" style="display:none;">
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted d-block">Stock actual</small>
                                    <strong id="previewActual">—</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Cambio</small>
                                    <strong id="previewCambio">—</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Stock resultante</small>
                                    <strong id="previewResultante">—</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Motivo -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Motivo / Referencia:</label>
                            <textarea class="form-control" name="motivo" id="inputMotivo" rows="2"
                                      maxlength="500" placeholder="Ej. Compra a proveedor XYZ, conteo físico mensual, baja por daño…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            <i class="bi bi-check-circle"></i> Registrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast de mensajes flash -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="toast show" role="alert">
            <div class="toast-header bg-<?php echo $_SESSION['flash_tipo'] ?? 'primary'; ?> text-white">
                <strong class="me-auto">
                    <?php echo ($_SESSION['flash_tipo'] ?? '') === 'success' ? 'Éxito' : (($_SESSION['flash_tipo'] ?? '') === 'danger' ? 'Error' : 'Aviso'); ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"><?php echo htmlspecialchars($_SESSION['flash_msg']); ?></div>
        </div>
    </div>
    <?php unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>

    <?php if ($puede_editar): ?>
    <script>
    (function () {
        const selectEspec  = document.getElementById('selectEspec');
        const inputCant    = document.getElementById('inputCantidad');
        const labelCant    = document.getElementById('labelCantidad');
        const cantAyuda    = document.getElementById('cantidadAyuda');
        const tipoAyuda    = document.getElementById('tipoAyuda');
        const preview      = document.getElementById('previewCalc');
        const prevActual   = document.getElementById('previewActual');
        const prevCambio   = document.getElementById('previewCambio');
        const prevResult   = document.getElementById('previewResultante');
        const btnGuardar   = document.getElementById('btnGuardar');

        function getTipoSeleccionado() {
            return document.querySelector('input[name="tipo_movimiento"]:checked').value;
        }

        function getStockActual() {
            const opt = selectEspec.options[selectEspec.selectedIndex];
            if (!opt || !opt.value) return null;
            return parseInt(opt.dataset.stock || '0', 10);
        }

        function actualizarLabels() {
            const tipo = getTipoSeleccionado();
            switch (tipo) {
                case 'entrada':
                    labelCant.innerHTML = 'Cantidad a agregar: <span class="text-danger">*</span>';
                    cantAyuda.textContent = 'Ingresa un número entero mayor a 0.';
                    tipoAyuda.innerHTML = '<strong>Entrada:</strong> agrega envases al stock actual.';
                    break;
                case 'salida':
                    labelCant.innerHTML = 'Cantidad a retirar: <span class="text-danger">*</span>';
                    cantAyuda.textContent = 'No puede exceder el stock actual.';
                    tipoAyuda.innerHTML = '<strong>Salida:</strong> resta envases del stock (baja por daño, transferencia externa, etc.).';
                    break;
                case 'ajuste':
                    labelCant.innerHTML = 'Nuevo stock (cantidad final): <span class="text-danger">*</span>';
                    cantAyuda.textContent = 'Ingresa la cantidad final que quedará en inventario (puede ser 0).';
                    tipoAyuda.innerHTML = '<strong>Ajuste:</strong> corrige el stock actual con el valor que ingreses (típicamente tras un conteo físico).';
                    break;
            }
        }

        function actualizarPreview() {
            const stock = getStockActual();
            const cant  = parseInt(inputCant.value, 10);
            if (stock === null || isNaN(cant)) {
                preview.style.display = 'none';
                btnGuardar.disabled = false;
                return;
            }
            const tipo = getTipoSeleccionado();
            let resultante = stock;
            let cambio = 0;
            let valido = true;
            let errorMsg = '';

            switch (tipo) {
                case 'entrada':
                    if (cant <= 0) { valido = false; errorMsg = 'Debe ser > 0'; }
                    resultante = stock + cant;
                    cambio = cant;
                    break;
                case 'salida':
                    if (cant <= 0) { valido = false; errorMsg = 'Debe ser > 0'; }
                    if (cant > stock) { valido = false; errorMsg = 'Excede stock actual'; }
                    resultante = stock - cant;
                    cambio = -cant;
                    break;
                case 'ajuste':
                    if (cant < 0) { valido = false; errorMsg = 'No puede ser negativo'; }
                    resultante = cant;
                    cambio = cant - stock;
                    break;
            }

            preview.style.display = '';
            prevActual.textContent = stock.toLocaleString();
            prevCambio.textContent = (cambio >= 0 ? '+' : '') + cambio.toLocaleString();
            prevCambio.className = cambio > 0 ? 'text-success' : (cambio < 0 ? 'text-danger' : 'text-muted');
            prevResult.textContent = valido ? resultante.toLocaleString() : '⚠ ' + errorMsg;
            prevResult.className = valido ? 'text-primary' : 'text-danger';

            btnGuardar.disabled = !valido;
        }

        document.querySelectorAll('input[name="tipo_movimiento"]').forEach(r => {
            r.addEventListener('change', () => { actualizarLabels(); actualizarPreview(); });
        });
        selectEspec.addEventListener('change', actualizarPreview);
        inputCant.addEventListener('input', actualizarPreview);

        actualizarLabels();

        // Función global para abrir el modal preseleccionando una especificación
        window.abrirMovimientoPara = function (especId) {
            selectEspec.value = especId;
            inputCant.value = '';
            document.getElementById('inputMotivo').value = '';
            actualizarPreview();
            new bootstrap.Modal(document.getElementById('modalMovimiento')).show();
        };

        // Reset al cerrar
        document.getElementById('modalMovimiento').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formMovimiento').reset();
            document.getElementById('tipoEntrada').checked = true;
            actualizarLabels();
            preview.style.display = 'none';
            btnGuardar.disabled = false;
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>