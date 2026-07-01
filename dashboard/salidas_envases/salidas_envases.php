<?php
/**
 * Dashboard SEC — Salidas de Envases para Clientes
 * Lista agrupada por día, formato similar al Excel de control.
 *
 * Ubicación: dashboard/salidas_envases/salidas_envases.php
 *
 * Acceso: Logística, Ventas, Almacén de Residuos, Dirección
 *         (cualquier usuario con permisos_sec.lector = 1)
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../includes/permisos_helper.php';
require_once __DIR__ . '/../../includes/salidas_envases/sec_funciones.php';

verificar_sesion();

if (sesion_expirada()) {
    destruir_sesion();
    session_start();
    establecer_alerta('warning', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
    redirigir(URL_BASE . 'auth/InicioSesion.php');
}
actualizar_sesion();

if (!puede_leer_sec()) {
    establecer_alerta('error', 'No tienes acceso al módulo de Salidas de Envases.');
    redirigir(URL_BASE . 'dashboard/index.php');
}

$puede_crear = puede_crear_sec();
$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');

// Filtros
$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'estado'      => $_GET['estado']      ?? '',
    'busqueda'    => $_GET['busqueda']    ?? '',
];

$agrupadas = obtener_sec_agrupadas_por_dia($filtros);

// Mensajes flash
$mensajes = [
    'creada'      => ['success', 'SEC creada correctamente.'],
    'actualizada' => ['success', 'SEC actualizada correctamente.'],
    'cancelada'   => ['warning', 'SEC cancelada.'],
    'cerrada'     => ['success', 'SEC cerrada exitosamente.'],
    'error'       => ['danger',  'Ocurrió un error al procesar la solicitud.'],
];
$msg_flash = $_GET['msg'] ?? null;
$folio_flash = $_GET['folio'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salidas de Envases | <?php echo NOMBRE_SISTEMA; ?></title>
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
        .dia-header {
            background: linear-gradient(90deg, #f8f9fa 0%, #fff 100%);
            border-left: 4px solid #14b8a6;
        }
        .dia-header .accordion-button:not(.collapsed) {
            background: linear-gradient(90deg, #d1fae5 0%, #fff 100%);
            color: #065f46;
        }
        .tabla-sec {
            font-size: 0.82rem;
        }
        .tabla-sec th {
            background-color: #f1f3f5;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            font-size: 0.78rem;
            padding: 8px 4px;
        }
        .tabla-sec td {
            vertical-align: middle;
            padding: 6px 4px;
        }
        .tabla-sec .col-tipo,
        .tabla-sec .col-cond {
            text-align: center;
            width: 50px;
        }
        .tabla-sec .marca {
            color: #14b8a6;
            font-weight: 700;
            font-size: 1rem;
        }
        .tabla-sec .pendiente {
            color: #6c757d;
            font-style: italic;
            font-size: 0.75rem;
        }
        .placa-cell { font-family: 'Courier New', monospace; font-weight: 600; }
        .estado-badge { font-size: 0.7rem; padding: 3px 8px; }
        .sec-row-first td { border-top: 2px solid #dee2e6; }
        .filtros-card .form-control,
        .filtros-card .form-select { font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="dashboard-container">

        <?php
        if (in_array($dept, ['logistica', 'almacen_residuos'])) {
            include __DIR__ . '/../../includes/sidebar/sidebar_sec.php';
        } elseif ($dept === 'ventas') {
            include __DIR__ . '/../../includes/sidebar/sidebar_colaborativo.php';
        } else {
            include __DIR__ . '/../../includes/sidebar/sidebar_normal.php';
        }
        ?>

        <main class="main-content">
            <div class="content-wrapper">

                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1><i class="bi bi-box-arrow-right"></i> Salidas de Envases</h1>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Control de salidas de envases para clientes del almacén
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (in_array($dept, ['logistica', 'ventas']) || $dept === 'almacen_residuos'): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/disponibilidad_unidades.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-calendar-event"></i> Calendario
                                </a>
                            <?php endif; ?>
                            <?php if ($dept === 'logistica'): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/unidades_transporte.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-truck"></i> Unidades
                                </a>
                            <?php endif; ?>
                            <?php if ($puede_crear): ?>
                                <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/nueva_sec.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Nueva SEC
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Mensaje flash -->
                <?php if ($msg_flash && isset($mensajes[$msg_flash])): ?>
                    <div class="alert alert-<?php echo $mensajes[$msg_flash][0]; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($mensajes[$msg_flash][1]); ?>
                        <?php if ($folio_flash): ?>
                            <strong><?php echo htmlspecialchars($folio_flash); ?></strong>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-3 filtros-card">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Fecha desde</label>
                                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Fecha hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Estado</label>
                                <select name="estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="enviada"   <?php echo $filtros['estado']==='enviada'?'selected':''; ?>>Enviada</option>
                                    <option value="entregada" <?php echo $filtros['estado']==='entregada'?'selected':''; ?>>Entregada</option>
                                    <option value="recibida"  <?php echo $filtros['estado']==='recibida'?'selected':''; ?>>Recibida</option>
                                    <option value="cerrada"   <?php echo $filtros['estado']==='cerrada'?'selected':''; ?>>Cerrada</option>
                                    <option value="cancelada" <?php echo $filtros['estado']==='cancelada'?'selected':''; ?>>Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Búsqueda (folio o solicita)</label>
                                <input type="text" name="busqueda" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtros['busqueda']); ?>" placeholder="SEC-... o nombre">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-funnel"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Listado agrupado por día -->
                <?php if (empty($agrupadas)): ?>
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0">No hay SEC registradas con los filtros actuales.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="accordionDias">
                        <?php $dia_idx = 0; foreach ($agrupadas as $fecha => $secs_del_dia): $dia_idx++; ?>
                            <?php
                                $fecha_formato = sec_fecha_larga_es($fecha);
                                $colapsado = $dia_idx > 1 ? 'collapsed' : '';
                                $expandido = $dia_idx === 1 ? 'show' : '';
                            ?>
                            <div class="accordion-item mb-2">
                                <h2 class="accordion-header dia-header">
                                    <button class="accordion-button <?php echo $colapsado; ?>" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#diaCollapse<?php echo $dia_idx; ?>">
                                        <i class="bi bi-calendar3 me-2"></i>
                                        <strong><?php echo $fecha_formato; ?></strong>
                                        <span class="ms-3 badge bg-secondary"><?php echo count($secs_del_dia); ?> SEC</span>
                                    </button>
                                </h2>
                                <div id="diaCollapse<?php echo $dia_idx; ?>" class="accordion-collapse collapse <?php echo $expandido; ?>">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover tabla-sec mb-0">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2">Folio</th>
                                                        <th rowspan="2" style="width: 70px;">Cant.</th>
                                                        <th colspan="4">Tipo de envase</th>
                                                        <th rowspan="2">Unidad</th>
                                                        <th rowspan="2">Solicita</th>
                                                        <th rowspan="2">Entrega</th>
                                                        <th rowspan="2">Recibe</th>
                                                        <th colspan="4">Condiciones</th>
                                                        <th rowspan="2">Estado</th>
                                                        <th rowspan="2" style="width: 70px;"></th>
                                                    </tr>
                                                    <tr>
                                                        <th class="col-tipo">TMB</th>
                                                        <th class="col-tipo">TOTE</th>
                                                        <th class="col-tipo">GFA</th>
                                                        <th class="col-tipo">JAULA</th>
                                                        <th class="col-cond" title="Buenas">B1</th>
                                                        <th class="col-cond" title="Regulares">R2</th>
                                                        <th class="col-cond" title="Abierto">A3</th>
                                                        <th class="col-cond" title="Cerrado">C4</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($secs_del_dia as $sec):
                                                        $lineas = $sec['lineas'];
                                                        $n_lineas = max(1, count($lineas));
                                                        $info_estado = info_estado_sec($sec['estado']);
                                                        $primera = true;
                                                    ?>
                                                        <?php foreach ($lineas as $idx => $linea): ?>
                                                            <tr class="<?php echo $primera ? 'sec-row-first' : ''; ?>">
                                                                <?php if ($primera): ?>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <strong><?php echo htmlspecialchars($sec['folio']); ?></strong>
                                                                    </td>
                                                                <?php endif; ?>

                                                                <td class="text-center"><strong><?php echo (int)$linea['cantidad']; ?></strong></td>
                                                                <td class="col-tipo"><?php echo $linea['tipo_envase']==='TMB'   ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                <td class="col-tipo"><?php echo $linea['tipo_envase']==='TOTE'  ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                <td class="col-tipo"><?php echo $linea['tipo_envase']==='GFA'   ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                <td class="col-tipo"><?php echo $linea['tipo_envase']==='JAULA' ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                <td>
                                                                    <?php if ($linea['unidad_nombre']): ?>
                                                                        <?php echo htmlspecialchars($linea['unidad_nombre']); ?>
                                                                        <br><small class="placa-cell text-muted"><?php echo htmlspecialchars($linea['unidad_placas']); ?></small>
                                                                    <?php else: ?>
                                                                        <span class="pendiente">N/A</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <?php if ($primera): ?>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php if ($sec['solicita_nombre']): ?>
                                                                            <?php echo htmlspecialchars($sec['solicita_nombre']); ?>
                                                                            <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                        <?php else: ?>
                                                                            <span class="pendiente">Pendiente</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php if ($sec['entrega_nombre']): ?>
                                                                            <?php echo htmlspecialchars($sec['entrega_nombre']); ?>
                                                                            <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                        <?php else: ?>
                                                                            <span class="pendiente">Pendiente</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>">
                                                                        <?php if ($sec['recibe_nombre']): ?>
                                                                            <?php echo htmlspecialchars($sec['recibe_nombre']); ?>
                                                                            <i class="bi bi-check-circle-fill text-success" title="Firmado"></i>
                                                                        <?php else: ?>
                                                                            <span class="pendiente">Pendiente</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="col-cond" rowspan="<?php echo $n_lineas; ?>"><?php echo $sec['condicion_b1'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                    <td class="col-cond" rowspan="<?php echo $n_lineas; ?>"><?php echo $sec['condicion_r2'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                    <td class="col-cond" rowspan="<?php echo $n_lineas; ?>"><?php echo $sec['condicion_a3'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                    <td class="col-cond" rowspan="<?php echo $n_lineas; ?>"><?php echo $sec['condicion_c4'] ? '<span class="marca">✕</span>' : ''; ?></td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>" class="text-center">
                                                                        <span class="badge estado-badge <?php echo $info_estado[0]; ?>"><?php echo $info_estado[1]; ?></span>
                                                                    </td>
                                                                    <td rowspan="<?php echo $n_lineas; ?>" class="text-center">
                                                                        <a href="<?php echo URL_BASE; ?>dashboard/salidas_envases/ver_sec.php?id=<?php echo (int)$sec['id']; ?>"
                                                                           class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                                            <i class="bi bi-eye"></i>
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                            <?php $primera = false; ?>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
</body>
</html>