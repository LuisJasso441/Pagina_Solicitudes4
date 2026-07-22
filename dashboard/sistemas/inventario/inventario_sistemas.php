<?php
/**
 * Inventario de Sistemas (Insumos) - Lista Principal
 * CRUD simple con modal de agregar/editar + detección de duplicados
 * Ubicación: dashboard/sistemas/inventario/inventario_sistemas.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/sistemas/inventario/inventario_sistemas_funciones.php';

if (!es_usuario_sistemas()) {
    establecer_alerta('error', 'No tienes acceso al Inventario de Sistemas.');
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$filtros = [
    'categoria' => $_GET['categoria'] ?? '',
    'busqueda'  => $_GET['busqueda'] ?? ''
];

$datos_tabla = obtener_inventario_sistemas($filtros);
$stats = obtener_estadisticas_inventario_sistemas();
$page_title = "Inventario de Sistemas";
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
        .tabla-inventario-wrapper { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tabla-inventario { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; }
        .tabla-inventario thead th { 
            background: #2c3e50; color: #fff; padding: 10px 12px; font-weight: 600; 
            font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; 
            border: 1px solid #34495e; position: sticky; top: 0; z-index: 10; white-space: nowrap; 
        }
        .tabla-inventario tbody td { padding: 6px 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .tabla-inventario tbody tr:hover { background: #f0f7ff; }
        .tabla-inventario tbody tr:nth-child(even) { background: #f8fafc; }
        .tabla-inventario tbody tr:nth-child(even):hover { background: #f0f7ff; }
        
        .badge-categoria { font-weight: 600; padding: 3px 10px; border-radius: 4px; font-size: 0.78rem; }
        .badge-cableado { background: #e8f4fd; color: #1565c0; }
        .badge-perifericos { background: #f3e8ff; color: #7c3aed; }
        .badge-licencias { background: #fef3c7; color: #92400e; }
        .badge-equipos { background: #d1fae5; color: #065f46; }
        .badge-redes { background: #fce7f3; color: #9d174d; }
        .badge-almacenamiento { background: #e0e7ff; color: #3730a3; }
        .badge-componentes { background: #fed7aa; color: #9a3412; }
        .badge-baterias_pilas { background: #fef9c3; color: #854d0e; }
        .badge-impresion { background: #e0f2fe; color: #075985; }
        .badge-herramientas { background: #f5f5dc; color: #6b4423; }
        .badge-otros { background: #f1f5f9; color: #475569; }
        
        .umbral-ok { color: #059669; font-weight: 600; }
        .umbral-alerta { color: #dc3545; font-weight: 700; background: #fff5f5; }
        
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        
        .btn-accion { padding: 2px 8px; font-size: 0.78rem; border-radius: 4px; }
        
        body[data-theme="dark"] .tabla-inventario { background: var(--card-bg, #1e293b); }
        body[data-theme="dark"] .tabla-inventario tbody td { border-color: #334155; color: #e2e8f0; }
        body[data-theme="dark"] .tabla-inventario tbody tr:hover { background: #1e3a5f; }
        body[data-theme="dark"] .tabla-inventario tbody tr:nth-child(even) { background: #1a2332; }
        body[data-theme="dark"] .umbral-alerta { background: #3b1111; color: #fca5a5; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i><?php echo $page_title; ?></h4>
                        <small class="text-muted">Gesti&oacute;n de art&iacute;culos e insumos del &aacute;rea de Sistemas</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="abrirListaPrestamos()">
                            <i class="bi bi-arrow-left-right me-1"></i> Pr&eacute;stamos
                        </button>
                        <button class="btn btn-primary" onclick="abrirModal()">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Art&iacute;culo
                        </button>
                    </div>
                </div>
                
                <!-- Tarjetas -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-box-seam"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $stats['total_articulos']; ?></div><small class="text-muted">Art&iacute;culos</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: <?php echo $stats['sin_stock'] > 0 ? '#fee2e2' : '#f1f5f9'; ?>; color: <?php echo $stats['sin_stock'] > 0 ? '#dc2626' : '#64748b'; ?>;"><i class="bi bi-x-circle"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $stats['sin_stock']; ?></div><small class="text-muted">Sin Stock</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: <?php echo $stats['bajo_umbral'] > 0 ? '#fef3c7' : '#f1f5f9'; ?>; color: <?php echo $stats['bajo_umbral'] > 0 ? '#d97706' : '#64748b'; ?>;"><i class="bi bi-exclamation-triangle"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $stats['bajo_umbral']; ?></div><small class="text-muted">Bajo Umbral</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;"><i class="bi bi-tags"></i></div>
                                <div><div class="fw-bold fs-5"><?php echo $stats['categorias_activas']; ?></div><small class="text-muted">Categor&iacute;as Activas</small></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($stats['en_prestamo'] > 0): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card stat-card border-start border-4 border-warning">
                            <div class="card-body d-flex align-items-center gap-3 py-2">
                                <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="bi bi-arrow-left-right"></i></div>
                                <div>
                                    <div class="fw-bold fs-5 d-inline"><?php echo $stats['en_prestamo']; ?></div>
                                    <small class="text-muted ms-1">unidad(es) en pr&eacute;stamo / asignadas</small>
                                    <div><small class="text-muted" style="font-size: 0.72rem;">Diferencia entre cantidad total y disponible</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <form method="GET" class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Categor&iacute;a</label>
                        <select name="categoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach (INVENTARIO_SIS_CATEGORIAS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filtros['categoria'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Nombre o ubicaci&oacute;n..." value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                            <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </div>
                </form>
                
                <!-- Tabla -->
                <div class="tabla-inventario-wrapper">
                    <table class="tabla-inventario" id="tablaInventario">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Nombre</th>
                                <th style="width: 130px;">Categor&iacute;a</th>
                                <th style="width: 90px;">Cant. Total</th>
                                <th style="width: 100px;">Disponible</th>
                                <th>Ubicaci&oacute;n</th>
                                <th style="width: 90px;">Umbral</th>
                                <th style="width: 140px;">Actualizado</th>
                                <th style="width: 110px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($datos_tabla)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron art&iacute;culos.</td></tr>
                            <?php else: ?>
                            <?php foreach ($datos_tabla as $item): 
                                $bajo_umbral = ($item['umbral_minimo'] > 0 && $item['cantidad_disponible'] <= $item['umbral_minimo']);
                                $cat_key = $item['categoria'];
                                $cat_label = INVENTARIO_SIS_CATEGORIAS[$cat_key] ?? $cat_key;
                            ?>
                            <tr class="<?php echo $bajo_umbral ? 'umbral-alerta' : ''; ?>" id="fila-<?php echo $item['id']; ?>">
                                <td class="text-center text-muted"><?php echo $item['id']; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td><span class="badge-categoria badge-<?php echo $cat_key; ?>"><?php echo $cat_label; ?></span></td>
                                <td class="text-center"><?php echo $item['cantidad_total']; ?></td>
                                <td class="text-center fw-bold <?php echo $bajo_umbral ? '' : 'umbral-ok'; ?>">
                                    <?php echo $item['cantidad_disponible']; ?>
                                    <?php if ($bajo_umbral): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['ubicacion'] ?? '—'); ?></td>
                                <td class="text-center"><?php echo $item['umbral_minimo']; ?></td>
                                <td class="text-muted" style="font-size: 0.78rem;"><?php echo date('d/m/Y H:i', strtotime($item['updated_at'])); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-warning btn-accion" title="Registrar pr&eacute;stamo"
                                            onclick="abrirPrestamo(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre'], ENT_QUOTES); ?>', <?php echo (int)$item['cantidad_disponible']; ?>)"
                                            <?php echo ((int)$item['cantidad_disponible'] <= 0) ? 'disabled' : ''; ?>><i class="bi bi-box-arrow-up"></i></button>
                                    <button class="btn btn-outline-primary btn-accion" title="Editar" onclick="editarArticulo(<?php echo $item['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-danger btn-accion" title="Eliminar" onclick="eliminarArticulo(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre'], ENT_QUOTES); ?>')"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> <?php echo count($datos_tabla); ?> art&iacute;culo(s) en inventario.</div>
                
            </div>
        </main>
    </div>

    <!-- Modal Agregar/Editar -->
    <div class="modal fade" id="modalArticulo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo"><i class="bi bi-plus-circle me-2"></i>Nuevo Art&iacute;culo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="articuloId" value="0">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="artNombre" class="form-control" placeholder="Ej: Cable HDMI 2m" required>
                        <div id="alertaDuplicado" class="alert alert-warning mt-2 py-2 px-3 d-none" style="font-size: 0.82rem;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><span id="alertaDuplicadoTexto"></span>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Categor&iacute;a <span class="text-danger">*</span></label>
                            <select id="artCategoria" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach (INVENTARIO_SIS_CATEGORIAS as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ubicaci&oacute;n</label>
                            <input type="text" id="artUbicacion" class="form-control" placeholder="Ej: Almac&eacute;n TI">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cant. Total</label>
                            <input type="number" id="artCantTotal" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Disponible</label>
                            <input type="number" id="artCantDisponible" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Umbral M&iacute;nimo</label>
                            <input type="number" id="artUmbral" class="form-control" min="0" value="0">
                            <small class="text-muted">Alerta si disponible &le; umbral</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarArticulo()" id="btnGuardar"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================== -->
    <!-- MODAL: Registrar Préstamo              -->
    <!-- ====================================== -->
    <div class="modal fade" id="modalPrestamo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-up me-2"></i>Registrar Pr&eacute;stamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="prestArticuloId" value="0">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Art&iacute;culo</label>
                        <input type="text" id="prestArticuloNombre" class="form-control" readonly>
                        <small class="text-muted">Disponibles: <span id="prestDisponible" class="fw-semibold">0</span></small>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">&iquest;A qui&eacute;n se presta? <span class="text-danger">*</span></label>
                            <input type="text" id="prestPersona" class="form-control" placeholder="Nombre de la persona">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" id="prestCantidad" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Departamento / &Aacute;rea</label>
                        <input type="text" id="prestDepartamento" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea id="prestObservaciones" class="form-control" rows="2" placeholder="Opcional (motivo, fecha estimada de devoluci&oacute;n, etc.)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" id="btnGuardarPrestamo" onclick="guardarPrestamo()"><i class="bi bi-check-lg me-1"></i> Registrar pr&eacute;stamo</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================== -->
    <!-- MODAL: Registro de Préstamos           -->
    <!-- ====================================== -->
    <div class="modal fade" id="modalListaPrestamos" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Registro de Pr&eacute;stamos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <select id="filtroEstadoPrestamo" class="form-select form-select-sm" style="max-width: 220px;" onchange="cargarPrestamos()">
                            <option value="">Todos los estados</option>
                            <option value="prestado">Prestados</option>
                            <option value="parcial">Devoluci&oacute;n parcial</option>
                            <option value="devuelto">Devueltos</option>
                        </select>
                        <input type="text" id="buscarPrestamo" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Buscar persona, art&iacute;culo o &aacute;rea..." oninput="debounceBuscarPrestamos()">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Art&iacute;culo</th>
                                    <th>Persona</th>
                                    <th>&Aacute;rea</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-center">Pend.</th>
                                    <th>Fecha pr&eacute;stamo</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acci&oacute;n</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPrestamos">
                                <tr><td colspan="8" class="text-center text-muted py-3">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script>
    const API_URL = '<?php echo URL_BASE; ?>dashboard/sistemas/inventario/api_inventario_sistemas.php';
    let modalInstance = null;
    let timerDuplicado = null;

    document.addEventListener('DOMContentLoaded', function() {
        modalInstance = new bootstrap.Modal(document.getElementById('modalArticulo'));
        document.getElementById('artNombre').addEventListener('input', verificarDuplicado);
        document.getElementById('artCategoria').addEventListener('change', verificarDuplicado);
    });

    function verificarDuplicado() {
        clearTimeout(timerDuplicado);
        const nombre = document.getElementById('artNombre').value.trim();
        const categoria = document.getElementById('artCategoria').value;
        const alerta = document.getElementById('alertaDuplicado');
        if (!nombre || !categoria) { alerta.classList.add('d-none'); document.getElementById('artNombre').classList.remove('border-warning'); return; }
        timerDuplicado = setTimeout(() => {
            fetch(API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'verificar_duplicado', nombre, categoria, excluir_id: parseInt(document.getElementById('articuloId').value) || 0 })
            }).then(r => r.json()).then(res => {
                if (res.duplicado) { document.getElementById('alertaDuplicadoTexto').textContent = res.mensaje; alerta.classList.remove('d-none'); document.getElementById('artNombre').classList.add('border-warning'); }
                else { alerta.classList.add('d-none'); document.getElementById('artNombre').classList.remove('border-warning'); }
            }).catch(() => {});
        }, 500);
    }

    function abrirModal() {
        document.getElementById('articuloId').value = 0;
        document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuevo Art\u00edculo';
        ['artNombre','artUbicacion'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('artCategoria').value = '';
        ['artCantTotal','artCantDisponible','artUmbral'].forEach(id => document.getElementById(id).value = 0);
        document.getElementById('alertaDuplicado').classList.add('d-none');
        document.getElementById('artNombre').classList.remove('border-warning');
        modalInstance.show();
    }

    function editarArticulo(id) {
        fetch(API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'obtener', id }) })
        .then(r => r.json()).then(res => {
            if (res.success) {
                const d = res.data;
                document.getElementById('articuloId').value = d.id;
                document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar #' + d.id;
                document.getElementById('artNombre').value = d.nombre;
                document.getElementById('artCategoria').value = d.categoria;
                document.getElementById('artUbicacion').value = d.ubicacion || '';
                document.getElementById('artCantTotal').value = d.cantidad_total;
                document.getElementById('artCantDisponible').value = d.cantidad_disponible;
                document.getElementById('artUmbral').value = d.umbral_minimo;
                document.getElementById('alertaDuplicado').classList.add('d-none');
                document.getElementById('artNombre').classList.remove('border-warning');
                modalInstance.show();
            } else { alert(res.message); }
        }).catch(() => alert('Error de conexi\u00f3n.'));
    }

    function guardarArticulo() {
        const id = parseInt(document.getElementById('articuloId').value);
        const nombre = document.getElementById('artNombre').value.trim();
        const categoria = document.getElementById('artCategoria').value;
        if (!nombre) { alert('El nombre es obligatorio.'); return; }
        if (!categoria) { alert('Seleccione una categor\u00eda.'); return; }

        const btn = document.getElementById('btnGuardar');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        fetch(API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: id > 0 ? 'actualizar' : 'crear', id: id > 0 ? id : undefined, nombre, categoria,
                cantidad_total: parseInt(document.getElementById('artCantTotal').value) || 0,
                cantidad_disponible: parseInt(document.getElementById('artCantDisponible').value) || 0,
                ubicacion: document.getElementById('artUbicacion').value.trim(),
                umbral_minimo: parseInt(document.getElementById('artUmbral').value) || 0
            })
        }).then(r => r.json()).then(res => { if (res.success) { modalInstance.hide(); location.reload(); } else { alert(res.message); } })
        .catch(() => alert('Error de conexi\u00f3n.'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar'; });
    }

    function eliminarArticulo(id, nombre) {
        if (!confirm('\u00bfEliminar "' + nombre + '"?')) return;
        fetch(API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'eliminar', id }) })
        .then(r => r.json()).then(res => {
            if (res.success) {
                const f = document.getElementById('fila-' + id);
                if (f) { f.style.transition = 'opacity 0.3s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 300); }
                else location.reload();
            } else alert(res.message);
        }).catch(() => alert('Error de conexi\u00f3n.'));
    }

    // ==========================================================
    // PRÉSTAMOS DE ARTÍCULOS
    // ==========================================================
    const PRESTAMOS_API_URL = '<?php echo URL_BASE; ?>dashboard/sistemas/inventario/api_prestamos_sistemas.php';
    let modalPrestamoInstance = null;
    let modalListaPrestamosInstance = null;
    let timerBuscarPrestamos = null;
    let prestamosCambiaron = false;

    document.addEventListener('DOMContentLoaded', function() {
        modalPrestamoInstance = new bootstrap.Modal(document.getElementById('modalPrestamo'));
        modalListaPrestamosInstance = new bootstrap.Modal(document.getElementById('modalListaPrestamos'));

        // Si hubo devoluciones, refrescar la tabla de inventario al cerrar la lista
        document.getElementById('modalListaPrestamos').addEventListener('hidden.bs.modal', function() {
            if (prestamosCambiaron) location.reload();
        });
    });

    function abrirPrestamo(id, nombre, disponible) {
        document.getElementById('prestArticuloId').value = id;
        document.getElementById('prestArticuloNombre').value = nombre;
        document.getElementById('prestDisponible').textContent = disponible;
        document.getElementById('prestPersona').value = '';
        document.getElementById('prestDepartamento').value = '';
        document.getElementById('prestObservaciones').value = '';
        const cant = document.getElementById('prestCantidad');
        cant.value = 1;
        cant.max = disponible;
        modalPrestamoInstance.show();
    }

    function guardarPrestamo() {
        const articulo_id = parseInt(document.getElementById('prestArticuloId').value);
        const persona = document.getElementById('prestPersona').value.trim();
        const cantidad = parseInt(document.getElementById('prestCantidad').value) || 0;
        const disponible = parseInt(document.getElementById('prestDisponible').textContent) || 0;

        if (!persona) { alert('Indica a qui\u00e9n se presta el art\u00edculo.'); return; }
        if (cantidad < 1) { alert('La cantidad debe ser al menos 1.'); return; }
        if (cantidad > disponible) { alert('No hay suficientes unidades disponibles (' + disponible + ').'); return; }

        const btn = document.getElementById('btnGuardarPrestamo');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando...';

        fetch(PRESTAMOS_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'crear_prestamo',
                articulo_id: articulo_id,
                persona: persona,
                cantidad: cantidad,
                departamento: document.getElementById('prestDepartamento').value.trim(),
                observaciones: document.getElementById('prestObservaciones').value.trim()
            })
        }).then(r => r.json()).then(res => {
            if (res.success) { modalPrestamoInstance.hide(); location.reload(); }
            else { alert(res.message || 'No se pudo registrar el pr\u00e9stamo.'); }
        }).catch(() => alert('Error de conexi\u00f3n.'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Registrar pr\u00e9stamo'; });
    }

    function abrirListaPrestamos() {
        prestamosCambiaron = false;
        modalListaPrestamosInstance.show();
        cargarPrestamos();
    }

    function debounceBuscarPrestamos() {
        clearTimeout(timerBuscarPrestamos);
        timerBuscarPrestamos = setTimeout(cargarPrestamos, 400);
    }

    function cargarPrestamos() {
        const tbody = document.getElementById('tbodyPrestamos');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Cargando...</td></tr>';

        fetch(PRESTAMOS_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'listar_prestamos',
                estado: document.getElementById('filtroEstadoPrestamo').value,
                busqueda: document.getElementById('buscarPrestamo').value.trim()
            })
        }).then(r => r.json()).then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3"><i class="bi bi-inbox me-1"></i>Sin pr\u00e9stamos registrados.</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.map(function(p) {
                const pend = parseInt(p.pendiente) || 0;
                const badge = p.estado === 'devuelto' ? 'success' : (p.estado === 'parcial' ? 'info' : 'warning');
                const estadoTxt = p.estado === 'devuelto' ? 'Devuelto' : (p.estado === 'parcial' ? 'Parcial' : 'Prestado');
                const fecha = p.fecha_prestamo ? String(p.fecha_prestamo).substring(0, 16).replace('T', ' ') : '';
                const accion = pend > 0
                    ? '<button class="btn btn-sm btn-outline-success" onclick="devolverPrestamo(' + p.id + ', ' + pend + ')"><i class="bi bi-arrow-return-left"></i> Devolver</button>'
                    : '<span class="text-muted small">\u2014</span>';
                return '<tr>' +
                    '<td class="fw-semibold">' + escapeHtmlPrestamo(p.nombre_articulo) + '</td>' +
                    '<td>' + escapeHtmlPrestamo(p.persona) + '</td>' +
                    '<td>' + escapeHtmlPrestamo(p.departamento || '\u2014') + '</td>' +
                    '<td class="text-center">' + p.cantidad + '</td>' +
                    '<td class="text-center fw-bold">' + pend + '</td>' +
                    '<td class="small text-muted">' + fecha + '</td>' +
                    '<td><span class="badge bg-' + badge + '">' + estadoTxt + '</span></td>' +
                    '<td class="text-center">' + accion + '</td>' +
                    '</tr>';
            }).join('');
        }).catch(() => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Error al cargar.</td></tr>';
        });
    }

    function devolverPrestamo(id, pendiente) {
        let cant = prompt('\u00bfCu\u00e1ntas unidades se devuelven? (pendiente: ' + pendiente + ')', pendiente);
        if (cant === null) return;
        cant = parseInt(cant);
        if (!cant || cant < 1) { alert('Cantidad inv\u00e1lida.'); return; }
        if (cant > pendiente) { alert('Solo quedan ' + pendiente + ' por devolver.'); return; }

        fetch(PRESTAMOS_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'devolver_prestamo', prestamo_id: id, cantidad: cant })
        }).then(r => r.json()).then(res => {
            if (res.success) { prestamosCambiaron = true; cargarPrestamos(); }
            else alert(res.message || 'No se pudo registrar la devoluci\u00f3n.');
        }).catch(() => alert('Error de conexi\u00f3n.'));
    }

    function escapeHtmlPrestamo(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    </script>
    
    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>