<?php
/**
 * Gesti&oacute;n de Acceso de Equipos
 * dashboard/sistemas/ti_sistemas/acceso_equipos.php
 * 
 * Tabla acceso_equipos: hostnames, IPs, contrase&ntilde;as, whoami
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para acceder a esta secci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

$filtro_depto = $_GET['departamento'] ?? '';
$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

$where = ["1=1"];
$params = [];

if ($filtro_depto) {
    $where[] = "ae.departamento_id = ?";
    $params[] = intval($filtro_depto);
}
if ($filtro_estado && in_array($filtro_estado, ['activo', 'inactivo'])) {
    $where[] = "ae.estado = ?";
    $params[] = $filtro_estado;
}
if ($filtro_busqueda) {
    $where[] = "(ae.hostname LIKE ? OR ae.direccion_ip LIKE ? OR ae.usuario_asignado_nombre LIKE ? OR ae.whoami LIKE ?)";
    $b = "%{$filtro_busqueda}%";
    $params = array_merge($params, [$b, $b, $b, $b]);
}

$where_clause = implode(" AND ", $where);

$sql = "SELECT ae.*, d.nombre as departamento_nombre
        FROM acceso_equipos ae
        LEFT JOIN departamentos d ON ae.departamento_id = d.id
        WHERE {$where_clause}
        ORDER BY ae.hostname ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$total_accesos = count($accesos);

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso de Equipos - TI/Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">
    <style>
        .table-acceso th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; }
        .table-acceso td { font-size: 0.85rem; vertical-align: middle; }
        .hostname-mono { font-family: 'Courier New', monospace; font-weight: 600; }
        .ip-mono { font-family: 'Courier New', monospace; font-size: 0.82rem; }
        .pass-cell { cursor: pointer; font-family: 'Courier New', monospace; font-size: 0.82rem; }
        .pass-cell:hover { background: #f0f0f0; border-radius: 4px; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-key me-2"></i>Acceso de Equipos</h4>
                        <small class="text-muted"><?php echo $total_accesos; ?> registros de acceso</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/importar_inventario.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-upload me-1"></i>Importar
                        </a>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearAcceso">
                            <i class="bi bi-plus-circle me-1"></i>Nuevo Acceso
                        </button>
                    </div>
                </div>

                <?php if (function_exists('mostrar_alerta')) mostrar_alerta(); ?>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body py-2">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <input type="text" name="busqueda" class="form-control form-control-sm" 
                                       placeholder="Hostname, IP, usuario, whoami..."
                                       value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="departamento" class="form-select form-select-sm">
                                    <option value="">Todos los departamentos</option>
                                    <?php foreach ($departamentos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $filtro_depto == $d['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                                <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/acceso_equipos.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-info ms-auto" onclick="togglePasswords()">
                                    <i class="bi bi-eye" id="icoTogglePass"></i> Contrase&ntilde;as
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 table-acceso">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Hostname</th>
                                        <th>Departamento</th>
                                        <th>Direcci&oacute;n IP</th>
                                        <th>Contrase&ntilde;a</th>
                                        <th>Usuario Asignado</th>
                                        <th>whoami</th>
                                        <th class="text-center">Estado</th>
                                        <th>Comentarios</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($accesos)): ?>
                                        <tr><td colspan="10" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No hay registros de acceso.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($accesos as $i => $acc): ?>
                                        <tr>
                                            <td><small class="text-muted"><?php echo $i + 1; ?></small></td>
                                            <td><span class="hostname-mono text-primary"><?php echo htmlspecialchars($acc['hostname']); ?></span></td>
                                            <td><small><?php echo htmlspecialchars($acc['departamento_nombre'] ?? '—'); ?></small></td>
                                            <td><span class="ip-mono"><?php echo htmlspecialchars($acc['direccion_ip'] ?: '—'); ?></span></td>
                                            <td>
                                                <span class="pass-cell pass-hidden" 
                                                      onclick="toggleSinglePass(this)"
                                                      data-pass="<?php echo htmlspecialchars($acc['contrasena_equipo'] ?? ''); ?>">
                                                    &#9679;&#9679;&#9679;&#9679;&#9679;&#9679;
                                                </span>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($acc['usuario_asignado_nombre'] ?: '—'); ?></small></td>
                                            <td><small class="ip-mono"><?php echo htmlspecialchars($acc['whoami'] ?: '—'); ?></small></td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $acc['estado'] === 'activo' ? 'bg-success' : 'bg-secondary'; ?>" style="font-size:0.7rem;">
                                                    <?php echo ucfirst($acc['estado']); ?>
                                                </span>
                                            </td>
                                            <td><small><?php echo htmlspecialchars(mb_substr($acc['comentarios'] ?? '', 0, 40)); ?></small></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary btn-sm" title="Editar"
                                                            onclick="editarAcceso(<?php echo htmlspecialchars(json_encode($acc)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm" title="Eliminar"
                                                            onclick="eliminarAcceso(<?php echo $acc['id']; ?>, '<?php echo htmlspecialchars($acc['hostname'], ENT_QUOTES); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <!-- Modal Crear/Editar Acceso -->
    <div class="modal fade" id="modalCrearAcceso" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_acceso.php">
                    <input type="hidden" name="accion" value="crear" id="accionAcceso">
                    <input type="hidden" name="acceso_id" value="" id="accesoId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloModalAcceso"><i class="bi bi-key me-2"></i>Nuevo Acceso</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Hostname <span class="text-danger">*</span></label>
                                <input type="text" name="hostname" id="accHostname" class="form-control" required
                                       style="text-transform:uppercase; font-family:monospace;" maxlength="50">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Departamento</label>
                                <select name="departamento_id" id="accDepto" class="form-select">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($departamentos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Direcci&oacute;n IP</label>
                                <input type="text" name="direccion_ip" id="accIP" class="form-control" maxlength="45"
                                       placeholder="192.168.10.XX" style="font-family:monospace;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contrase&ntilde;a</label>
                                <input type="text" name="contrasena_equipo" id="accPass" class="form-control" maxlength="255"
                                       style="font-family:monospace;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usuario Asignado</label>
                                <input type="text" name="usuario_asignado_nombre" id="accUsuario" class="form-control" maxlength="200">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">whoami</label>
                                <input type="text" name="whoami" id="accWhoami" class="form-control" maxlength="100"
                                       style="font-family:monospace;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="estado" id="accEstado" class="form-select">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Comentarios</label>
                                <textarea name="comentarios" id="accComentarios" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarAcceso">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminarAcceso" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-1"></i>Confirmar</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>&iquest;Eliminar acceso de <strong id="hostnameEliminar"></strong>?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_acceso.php">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="acceso_id" id="eliminarAccesoId">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
    let passVisible = false;
    
    function togglePasswords() {
        passVisible = !passVisible;
        document.querySelectorAll('.pass-cell').forEach(el => {
            el.textContent = passVisible ? (el.dataset.pass || '—') : '\u25CF\u25CF\u25CF\u25CF\u25CF\u25CF';
        });
        document.getElementById('icoTogglePass').className = passVisible ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
    
    function toggleSinglePass(el) {
        if (el.dataset.showing === 'true') {
            el.textContent = '\u25CF\u25CF\u25CF\u25CF\u25CF\u25CF';
            el.dataset.showing = 'false';
        } else {
            el.textContent = el.dataset.pass || '—';
            el.dataset.showing = 'true';
        }
    }
    
    function editarAcceso(data) {
        document.getElementById('accionAcceso').value = 'editar';
        document.getElementById('accesoId').value = data.id;
        document.getElementById('accHostname').value = data.hostname || '';
        document.getElementById('accDepto').value = data.departamento_id || '';
        document.getElementById('accIP').value = data.direccion_ip || '';
        document.getElementById('accPass').value = data.contrasena_equipo || '';
        document.getElementById('accUsuario').value = data.usuario_asignado_nombre || '';
        document.getElementById('accWhoami').value = data.whoami || '';
        document.getElementById('accEstado').value = data.estado || 'activo';
        document.getElementById('accComentarios').value = data.comentarios || '';
        document.getElementById('tituloModalAcceso').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Acceso - ' + data.hostname;
        new bootstrap.Modal(document.getElementById('modalCrearAcceso')).show();
    }
    
    function eliminarAcceso(id, hostname) {
        document.getElementById('eliminarAccesoId').value = id;
        document.getElementById('hostnameEliminar').textContent = hostname;
        new bootstrap.Modal(document.getElementById('modalEliminarAcceso')).show();
    }
    
    // Reset modal al cerrar
    document.getElementById('modalCrearAcceso').addEventListener('hidden.bs.modal', function() {
        document.getElementById('accionAcceso').value = 'crear';
        document.getElementById('accesoId').value = '';
        document.getElementById('tituloModalAcceso').innerHTML = '<i class="bi bi-key me-2"></i>Nuevo Acceso';
        this.querySelector('form').reset();
    });
    </script>
</body>
</html>