<?php
/**
 * Apartado Maestro - Centro de Descargas
 * dashboard/maestro/index.php
 *
 * Cerebro de descargas del sistema. Aquí se ubicarán todos los reportes
 * y archivos descargables del sistema.
 *
 * Acceso: solo Sistemas / TI
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'Solo el departamento de Sistemas tiene acceso al Maestro.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

// ------- Datos de contexto para los formularios -------
$anio_actual = (int) date('Y');
$mes_actual  = (int) date('n');

$meses_es = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Rango de años: del primer año con solicitudes hasta el año siguiente al actual
$anio_min_query = $pdo->query("SELECT MIN(YEAR(fecha_solicitud)) FROM solicitudes_mantenimiento_ti");
$anio_min = (int) ($anio_min_query->fetchColumn() ?: $anio_actual);
if ($anio_min > $anio_actual) $anio_min = $anio_actual;
$anio_max = $anio_actual + 1;

$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maestro - Centro de Descargas</title>
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
        /* === Header del Maestro === */
        .maestro-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
        }
        .maestro-header h2 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .maestro-header .lead {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* === Secciones === */
        .maestro-seccion {
            margin-bottom: 2rem;
        }
        .maestro-seccion-titulo {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .maestro-seccion-titulo::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* === Cards de descarga === */
        .descarga-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            background: #fff;
            transition: all 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .descarga-card:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.07);
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .descarga-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            font-size: 1.3rem;
        }
        .descarga-card-icon.azul    { background: #dbeafe; color: #1e40af; }
        .descarga-card-icon.verde   { background: #d1fae5; color: #047857; }
        .descarga-card-icon.morado  { background: #ede9fe; color: #6d28d9; }
        .descarga-card-icon.naranja { background: #fed7aa; color: #c2410c; }

        .descarga-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        .descarga-card-desc {
            font-size: 0.825rem;
            color: #6b7280;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            min-height: 2.4em;
        }
        .descarga-card-meta {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 0.75rem;
        }

        .descarga-card-form {
            margin-top: auto;
        }
        .descarga-card-form .form-label {
            font-size: 0.75rem;
            color: #4b5563;
            margin-bottom: 0.2rem;
            font-weight: 500;
        }
        .descarga-card-form .form-control,
        .descarga-card-form .form-select {
            font-size: 0.85rem;
            padding: 0.35rem 0.6rem;
        }
        .descarga-card-form .form-text {
            font-size: 0.7rem;
        }
        .descarga-card .btn-descargar {
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.45rem 0.85rem;
            width: 100%;
        }

        .badge-codigo {
            background: #f1f5f9;
            color: #475569;
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            padding: 0.2rem 0.45rem;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Card "próximamente" */
        .descarga-card.disabled {
            opacity: 0.55;
            pointer-events: none;
        }

        @media (max-width: 575px) {
            .descarga-card { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php include __DIR__ . '/../../includes/sidebar/sidebar_ti.php'; ?>

    <main class="main-content">
        <div class="content-wrapper">

            <!-- Header del Maestro -->
            <div class="maestro-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1"><i class="bi bi-folder-symlink me-2"></i>Maestro</h2>
                        <p class="lead">Centro de descargas del sistema</p>
                    </div>
                    <div class="text-end">
                        <small class="d-block opacity-75">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                        </small>
                        <small class="opacity-75">
                            <i class="bi bi-calendar3 me-1"></i><?php echo date('d/m/Y'); ?>
                        </small>
                    </div>
                </div>
            </div>

            <?php if (function_exists('mostrar_alerta')) echo mostrar_alerta(); ?>

            <!-- =========================================================
                 SECCION: MANTENIMIENTOS DE TI
                 ========================================================= -->
            <div class="maestro-seccion">
                <div class="maestro-seccion-titulo">
                    <i class="bi bi-tools"></i>
                    Mantenimientos de TI
                </div>

                <div class="row g-3">

                    <!-- =====================================================
                         REPORTE 1: Programa Anual
                         ===================================================== -->
                    <div class="col-md-6 col-lg-4">
                        <div class="descarga-card">
                            <div class="descarga-card-icon azul">
                                <i class="bi bi-calendar-range"></i>
                            </div>
                            <div class="descarga-card-title">Programa Anual de Mantenimiento</div>
                            <div class="descarga-card-desc">
                                Calendario anual con la programación P/R de los equipos por mes.
                            </div>
                            <div class="descarga-card-meta">
                                <span class="badge-codigo">FR-TI-04</span> · 3 archivos por año
                            </div>

                            <form action="<?php echo URL_BASE; ?>dashboard/maestro/exportar_programa_anual.php"
                                  method="POST" class="descarga-card-form" target="_blank">

                                <div class="mb-2">
                                    <label class="form-label">Año</label>
                                    <select name="anio" class="form-select" required>
                                        <?php for ($a = $anio_max; $a >= $anio_min; $a--): ?>
                                            <option value="<?php echo $a; ?>" <?php echo $a === $anio_actual ? 'selected' : ''; ?>>
                                                <?php echo $a; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Tipo de archivo</label>
                                    <select name="tipo_archivo" class="form-select" required>
                                        <option value="computo_fisico">Equipos de Cómputo · Físico</option>
                                        <option value="computo_logico">Equipos de Cómputo · Lógico</option>
                                        <option value="impresoras">Impresoras (Físico + Lógico)</option>
                                    </select>
                                    <div class="form-text">Cada combinación se descarga en un archivo separado.</div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-descargar mt-2">
                                    <i class="bi bi-download me-1"></i>Descargar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- =====================================================
                         REPORTE 2: Reporte de Mantenimientos Preventivos
                         ===================================================== -->
                    <div class="col-md-6 col-lg-4">
                        <div class="descarga-card">
                            <div class="descarga-card-icon verde">
                                <i class="bi bi-clipboard2-data"></i>
                            </div>
                            <div class="descarga-card-title">Reporte de Mantenimientos Preventivos</div>
                            <div class="descarga-card-desc">
                                Listado detallado de mantenimientos finalizados con firmas de conformidad.
                            </div>
                            <div class="descarga-card-meta">
                                <span class="badge-codigo">FR-TI-05</span> · Por rango de fechas
                            </div>

                            <form action="<?php echo URL_BASE; ?>dashboard/maestro/exportar_reporte_mantenimientos.php"
                                  method="POST" class="descarga-card-form" target="_blank" id="formReporte2">

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label">Desde</label>
                                        <input type="date" name="fecha_desde" class="form-control" required
                                               value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Hasta</label>
                                        <input type="date" name="fecha_hasta" class="form-control" required
                                               value="<?php echo date('Y-m-t'); ?>">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Tipo de mantenimiento (opcional)</label>
                                    <select name="tipo_mantenimiento" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="fisico">Físico</option>
                                        <option value="logico">Lógico</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success btn-descargar mt-2">
                                    <i class="bi bi-download me-1"></i>Descargar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- =====================================================
                         REPORTE 3: Programa Mensual
                         ===================================================== -->
                    <div class="col-md-6 col-lg-4">
                        <div class="descarga-card">
                            <div class="descarga-card-icon morado">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div class="descarga-card-title">Programa Mensual de Mantenimiento</div>
                            <div class="descarga-card-desc">
                                Calendario diario por departamento de los mantenimientos del mes.
                            </div>
                            <div class="descarga-card-meta">
                                <span class="badge-codigo">FR-TI-06</span> · Por mes y tipo
                            </div>

                            <form action="<?php echo URL_BASE; ?>dashboard/maestro/exportar_programa_mensual.php"
                                  method="POST" class="descarga-card-form" target="_blank">

                                <div class="row g-2 mb-2">
                                    <div class="col-7">
                                        <label class="form-label">Mes</label>
                                        <select name="mes" class="form-select" required>
                                            <?php foreach ($meses_es as $num => $nombre): ?>
                                                <option value="<?php echo $num; ?>" <?php echo $num === $mes_actual ? 'selected' : ''; ?>>
                                                    <?php echo $nombre; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label">Año</label>
                                        <select name="anio" class="form-select" required>
                                            <?php for ($a = $anio_max; $a >= $anio_min; $a--): ?>
                                                <option value="<?php echo $a; ?>" <?php echo $a === $anio_actual ? 'selected' : ''; ?>>
                                                    <?php echo $a; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Tipo de mantenimiento</label>
                                    <select name="tipo_mantenimiento" class="form-select" required>
                                        <option value="fisico">Físico</option>
                                        <option value="logico">Lógico</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-purple btn-descargar mt-2"
                                        style="background:#6d28d9; color:#fff; border-color:#6d28d9;">
                                    <i class="bi bi-download me-1"></i>Descargar
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <!-- =========================================================
                 ESPACIO PARA OTRAS SECCIONES FUTURAS
                 ========================================================= -->
            <div class="maestro-seccion">
                <div class="maestro-seccion-titulo">
                    <i class="bi bi-three-dots"></i>
                    Próximamente
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="descarga-card disabled">
                            <div class="descarga-card-icon naranja">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div class="descarga-card-title">Inventario de Equipos</div>
                            <div class="descarga-card-desc">
                                Exportar el inventario completo de equipos electrónicos.
                            </div>
                            <div class="descarga-card-meta">
                                <span class="badge bg-secondary">Próximamente</span>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-descargar mt-2" disabled>
                                <i class="bi bi-clock me-1"></i>No disponible
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
<script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>

<script>
// Validacion del rango de fechas en el Reporte 2
document.getElementById('formReporte2').addEventListener('submit', function(e) {
    const desde = this.fecha_desde.value;
    const hasta = this.fecha_hasta.value;
    if (desde && hasta && desde > hasta) {
        e.preventDefault();
        alert('La fecha "Desde" no puede ser posterior a la fecha "Hasta".');
        return false;
    }
});
</script>

</body>
</html>