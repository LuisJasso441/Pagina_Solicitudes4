<?php
/**
 * Administracion de Empleados EPP
 * Ubicacion: dashboard/inventario_epp/empleados_epp.php
 * Acceso: solo Seguridad.
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';

$permisos_vale = verificar_permisos_vales();
if ($permisos_vale['departamento'] !== 'seguridad') {
    establecer_alerta('error', 'No tienes acceso a esta seccion.');
    header('Location: ' . URL_BASE . 'dashboard/inventario_epp/vales_epp.php');
    exit;
}

$filtros = [
    'departamento_id'   => (int) ($_GET['departamento_id'] ?? 0),
    'busqueda'          => $_GET['busqueda'] ?? '',
    'incluir_inactivos' => !empty($_GET['incluir_inactivos'])
];

$empleados     = obtener_empleados_epp_admin($filtros);
$departamentos = obtener_departamentos_para_empleados();
$page_title    = "Empleados EPP";

// Mapa para el modal de edicion
$emp_js = [];
foreach ($empleados as $e) {
    $emp_js[$e['id']] = [
        'nombre_completo' => $e['nombre_completo'] ?? '',
        'no_nomina'       => $e['no_nomina'] ?? '',
        'departamento_id' => $e['departamento_id']
    ];
}
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
        .tabla-vales-wrapper { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tabla-vales { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; }
        .tabla-vales thead th { background: #2c3e50; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #34495e; white-space: nowrap; }
        .tabla-vales tbody td { padding: 8px 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .tabla-vales tbody tr:nth-child(even) { background: #f8fafc; }
        .fila-inactiva { opacity: 0.55; }
        .filtros-bar { background: #fff; padding: 0.75rem 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1rem; }
        .filtros-bar .form-control, .filtros-bar .form-select { font-size: 0.8rem; padding: 0.3rem 0.5rem; }
        .filtros-bar .form-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6c757d; margin-bottom: 0.15rem; }
        .btn-accion { padding: 0.15rem 0.4rem; font-size: 0.75rem; border-radius: 4px; }
        .badge-origen { font-size: 0.68rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . "/../../includes/sidebar/sidebar_inventario.php"; ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-0" style="font-size: 1.4rem;">
                            <i class="bi bi-people text-primary"></i> <?php echo $page_title; ?>
                        </h2>
                        <small class="text-muted">Administración de empleados para vales de EPP</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/inventario_epp/vales_epp.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver a Vales
                    </a>
                </div>

                <?php echo mostrar_alerta(); ?>

                <!-- Filtros -->
                <form method="GET" class="filtros-bar">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Departamento</label>
                            <select name="departamento_id" class="form-select">
                                <option value="0">Todos</option>
                                <?php foreach ($departamentos as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $filtros['departamento_id'] === (int)$d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="busqueda" class="form-control" placeholder="ID o nombre..." value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="incluir_inactivos" id="incluirInactivos" value="1" <?php echo $filtros['incluir_inactivos'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="incluirInactivos" style="font-size:0.8rem;">Mostrar inactivos</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                                <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Tabla -->
                <div class="tabla-vales-wrapper">
                    <table class="tabla-vales">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>ID / Nómina</th>
                                <th>Nombre</th>
                                <th>Departamento</th>
                                <th style="width: 90px;">Origen</th>
                                <th style="width: 80px;">Estado</th>
                                <th style="width: 110px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empleados)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No se encontraron empleados.</td></tr>
                            <?php else: ?>
                            <?php foreach ($empleados as $i => $e):
                                $origen_badge = match($e['origen']) {
                                    'usuario' => 'bg-primary',
                                    'vale'    => 'bg-info text-dark',
                                    default   => 'bg-secondary'
                                };
                            ?>
                            <tr class="<?php echo $e['activo'] ? '' : 'fila-inactiva'; ?>">
                                <td class="text-center text-muted"><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($e['no_nomina'] ?: '—'); ?></strong></td>
                                <td><?php echo htmlspecialchars($e['nombre_completo'] ?: '(sin nombre)'); ?></td>
                                <td><?php echo htmlspecialchars($e['departamento_nombre'] ?? '—'); ?></td>
                                <td><span class="badge badge-origen <?php echo $origen_badge; ?>"><?php echo htmlspecialchars($e['origen']); ?></span></td>
                                <td>
                                    <?php if ($e['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary btn-accion" title="Editar"
                                            onclick="editarEmpleado(<?php echo $e['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-accion <?php echo $e['activo'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                            title="<?php echo $e['activo'] ? 'Desactivar' : 'Reactivar'; ?>"
                                            onclick="toggleEmpleado(<?php echo $e['id']; ?>, <?php echo $e['activo'] ? 0 : 1; ?>)">
                                        <i class="bi <?php echo $e['activo'] ? 'bi-person-x' : 'bi-person-check'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle"></i> <?php echo count($empleados); ?> empleado(s)
                </div>

            </div>
        </main>
    </div>

    <!-- Modal editar -->
    <div class="modal fade" id="modalEditarEmp" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="errorEditarEmp" style="font-size:0.85rem;"></div>
                    <input type="hidden" id="editEmpId">
                    <div class="mb-2">
                        <label class="form-label">ID / Nómina <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editEmpNomina" maxlength="30" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nombre completo <span class="text-muted">(opcional)</span></label>
                        <input type="text" class="form-control" id="editEmpNombre" maxlength="150" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Departamento <span class="text-danger">*</span></label>
                        <select class="form-select" id="editEmpDepto">
                            <?php foreach ($departamentos as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarEdicion">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    <script>
    const empleadosData = <?php echo json_encode($emp_js, JSON_UNESCAPED_UNICODE); ?>;
    const API_URL = '<?php echo URL_BASE; ?>dashboard/inventario_epp/api_vales_epp.php';

    const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarEmp'));
    const editEmpId     = document.getElementById('editEmpId');
    const editEmpNomina = document.getElementById('editEmpNomina');
    const editEmpNombre = document.getElementById('editEmpNombre');
    const editEmpDepto  = document.getElementById('editEmpDepto');
    const errorEditar   = document.getElementById('errorEditarEmp');
    const btnGuardar    = document.getElementById('btnGuardarEdicion');

    function editarEmpleado(id) {
        const e = empleadosData[id];
        if (!e) return;
        editEmpId.value = id;
        editEmpNomina.value = e.no_nomina || '';
        editEmpNombre.value = e.nombre_completo || '';
        editEmpDepto.value = e.departamento_id;
        errorEditar.classList.add('d-none');
        errorEditar.textContent = '';
        modalEditar.show();
    }

    btnGuardar.addEventListener('click', async function() {
        const nomina = editEmpNomina.value.trim();
        if (!nomina) {
            errorEditar.textContent = 'El ID / nómina es obligatorio.';
            errorEditar.classList.remove('d-none');
            return;
        }
        btnGuardar.disabled = true;
        try {
            const resp = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'actualizar_empleado',
                    id: editEmpId.value,
                    no_nomina: nomina,
                    nombre_completo: editEmpNombre.value.trim(),
                    departamento_id: editEmpDepto.value
                })
            });
            const data = await resp.json();
            if (!data.success) {
                errorEditar.textContent = data.message || 'No se pudo guardar.';
                errorEditar.classList.remove('d-none');
                btnGuardar.disabled = false;
                return;
            }
            location.reload();
        } catch (err) {
            errorEditar.textContent = 'Error de conexión.';
            errorEditar.classList.remove('d-none');
            btnGuardar.disabled = false;
        }
    });

    async function toggleEmpleado(id, activo) {
        const accionTxt = activo ? 'reactivar' : 'desactivar';
        if (!confirm('¿Seguro que deseas ' + accionTxt + ' este empleado?')) return;
        try {
            const resp = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'toggle_empleado', id: id, activo: activo })
            });
            const data = await resp.json();
            if (!data.success) { alert(data.message || 'No se pudo cambiar el estado.'); return; }
            location.reload();
        } catch (err) {
            alert('Error de conexión.');
        }
    }
    </script>
</body>
</html>