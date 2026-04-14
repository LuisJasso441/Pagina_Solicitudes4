<?php
/**
 * Crear/Agregar Nuevo Equipo al Inventario v2.0
 * dashboard/sistemas/ti_sistemas/crear_equipo.php
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

$departamentos = $pdo->query("SELECT id, codigo, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);

// Equipos existentes para el select de personal_asignado (solo equipos de cómputo)
$equipos_padre = $pdo->query("SELECT hostname FROM inventario_equipos WHERE tipo_equipo IN ('all_in_one','pc','laptop','computadora') AND hostname IS NOT NULL ORDER BY hostname")->fetchAll(PDO::FETCH_COLUMN);

$form_data = $_SESSION['form_data'] ?? [];
$errores = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

$current_page = basename(__FILE__);

// Tipos de equipo
$tipos_equipo = [
    'all_in_one'   => ['nombre' => 'All In One',    'icono' => 'bi-display'],
    'pc'           => ['nombre' => 'PC',             'icono' => 'bi-pc-display'],
    'laptop'       => ['nombre' => 'Laptop',         'icono' => 'bi-laptop'],
    'monitor'      => ['nombre' => 'Monitor',        'icono' => 'bi-display'],
    'mouse'        => ['nombre' => 'Mouse',          'icono' => 'bi-mouse'],
    'teclado'      => ['nombre' => 'Teclado',        'icono' => 'bi-keyboard'],
    'impresora'    => ['nombre' => 'Impresora',      'icono' => 'bi-printer'],
    'access_point' => ['nombre' => 'Access Point',   'icono' => 'bi-wifi'],
    'switch'       => ['nombre' => 'Switch',         'icono' => 'bi-diagram-3'],
    'camara'       => ['nombre' => 'C&aacute;mara',  'icono' => 'bi-camera-video'],
    'celular'      => ['nombre' => 'Celular',        'icono' => 'bi-phone'],
    'pantalla_tv'  => ['nombre' => 'Pantalla TV',    'icono' => 'bi-tv'],
    'nobreak'      => ['nombre' => 'Nobreak',        'icono' => 'bi-battery-charging'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Equipo - TI/Sistemas</title>
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
        .form-section { margin-bottom: 1.5rem; }
        .form-section-title {
            font-weight: 600; font-size: 0.95rem; color: #2c3e50;
            padding-bottom: 0.5rem; border-bottom: 2px solid #e9ecef; margin-bottom: 1rem;
        }
        .tipo-card {
            text-align: center; padding: 0.8rem 0.5rem; border: 2px solid #e9ecef;
            border-radius: 10px; cursor: pointer; transition: all 0.2s;
        }
        .tipo-card:hover { border-color: #0d6efd; background: rgba(13,110,253,0.03); }
        .tipo-card.selected { border-color: #0d6efd; background: rgba(13,110,253,0.08); }
        .tipo-card .icon-container { font-size: 1.3rem; margin-bottom: 0.3rem; color: #0d6efd; }
        .tipo-card span { font-size: 0.75rem; }
        label.required::after { content: ' *'; color: #dc3545; }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_ti.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Agregar Equipo</h4>
                        <small class="text-muted">Registrar nuevo equipo en el inventario</small>
                    </div>
                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </a>
                </div>

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <strong>Errores encontrados:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/procesar_equipo.php" 
                      method="POST" id="formCrearEquipo">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Tipo de Equipo -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="form-section">
                                        <div class="form-section-title">
                                            <i class="bi bi-tag me-2"></i>Tipo de Equipo
                                        </div>
                                        <div class="row g-2">
                                            <?php foreach ($tipos_equipo as $key => $tipo): ?>
                                                <div class="col-4 col-md-3 col-lg-2">
                                                    <div class="tipo-card <?php echo (($form_data['tipo_equipo'] ?? '') === $key) ? 'selected' : ''; ?>"
                                                         onclick="seleccionarTipo('<?php echo $key; ?>')">
                                                        <div class="icon-container"><i class="bi <?php echo $tipo['icono']; ?>"></i></div>
                                                        <span class="fw-medium"><?php echo $tipo['nombre']; ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="tipo_equipo" id="inputTipoEquipo" 
                                               value="<?php echo htmlspecialchars($form_data['tipo_equipo'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Identificaci&oacute;n -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="form-section">
                                        <div class="form-section-title">
                                            <i class="bi bi-upc-scan me-2"></i>Identificaci&oacute;n del Equipo
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label required">Hostname</label>
                                                <input type="text" name="hostname" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['hostname'] ?? ''); ?>"
                                                       required maxlength="50" placeholder="Ej: GVTESO01, GVSIST02"
                                                       style="text-transform:uppercase; font-family:monospace;">
                                                <small class="text-muted">Identificador &uacute;nico del equipo en la red</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">C&oacute;digo Interno</label>
                                                <input type="text" name="codigo_interno" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['codigo_interno'] ?? ''); ?>"
                                                       maxlength="50" placeholder="Opcional (se usa hostname si est&aacute; vac&iacute;o)"
                                                       style="text-transform:uppercase;">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">N&uacute;mero de Serie</label>
                                                <input type="text" name="numero_serie" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['numero_serie'] ?? ''); ?>"
                                                       maxlength="100" placeholder="Serie del fabricante">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Marca</label>
                                                <input type="text" name="marca" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['marca'] ?? ''); ?>"
                                                       maxlength="100" placeholder="Ej: HP, ASUS, Dell">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Modelo</label>
                                                <input type="text" name="modelo" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['modelo'] ?? ''); ?>"
                                                       maxlength="100" placeholder="Ej: V241E, Latitude 5490">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Asignaci&oacute;n -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="form-section">
                                        <div class="form-section-title">
                                            <i class="bi bi-person-badge me-2"></i>Asignaci&oacute;n y Ubicaci&oacute;n
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label required">Ubicaci&oacute;n F&iacute;sica</label>
                                                <input type="text" name="ubicacion" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['ubicacion'] ?? ''); ?>"
                                                       required maxlength="200" placeholder="Ej: 1 EDIFICIO, 2 EDIFICIO">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Departamento</label>
                                                <select name="departamento_id" class="form-select">
                                                    <option value="">Sin asignar</option>
                                                    <?php foreach ($departamentos as $d): ?>
                                                        <option value="<?php echo $d['id']; ?>" 
                                                                <?php echo (($form_data['departamento_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($d['nombre']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Personal Asignado (Hostname padre)</label>
                                                <input type="text" name="personal_asignado" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['personal_asignado'] ?? ''); ?>"
                                                       maxlength="50" placeholder="Ej: GVCONT01 (para perif&eacute;ricos)"
                                                       list="listaEquiposPadre" style="text-transform:uppercase; font-family:monospace;">
                                                <datalist id="listaEquiposPadre">
                                                    <?php foreach ($equipos_padre as $ep): ?>
                                                        <option value="<?php echo htmlspecialchars($ep); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                                <small class="text-muted">Para perif&eacute;ricos: hostname del equipo al que pertenece</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Usuario Asignado (Plataforma)</label>
                                                <select name="usuario_asignado_id" class="form-select">
                                                    <option value="">Sin asignar</option>
                                                    <?php foreach ($usuarios as $u): ?>
                                                        <option value="<?php echo $u['id']; ?>"
                                                                <?php echo (($form_data['usuario_asignado_id'] ?? '') == $u['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($u['nombre_completo']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detalles adicionales -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="form-section">
                                        <div class="form-section-title">
                                            <i class="bi bi-gear me-2"></i>Detalles Adicionales
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Estado</label>
                                                <select name="estado" class="form-select">
                                                    <option value="activo" <?php echo (($form_data['estado'] ?? 'activo') === 'activo') ? 'selected' : ''; ?>>Activo</option>
                                                    <option value="inactivo" <?php echo (($form_data['estado'] ?? '') === 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                                                    <option value="en_reparacion" <?php echo (($form_data['estado'] ?? '') === 'en_reparacion') ? 'selected' : ''; ?>>En Reparaci&oacute;n</option>
                                                    <option value="dado_de_baja" <?php echo (($form_data['estado'] ?? '') === 'dado_de_baja') ? 'selected' : ''; ?>>Dado de Baja</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Fecha de Adquisici&oacute;n</label>
                                                <input type="date" name="fecha_adquisicion" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['fecha_adquisicion'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Correo Asignado</label>
                                                <input type="email" name="correo_asignado" class="form-control" 
                                                       value="<?php echo htmlspecialchars($form_data['correo_asignado'] ?? ''); ?>"
                                                       placeholder="correo@empresa.com">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Notas</label>
                                                <textarea name="notas" class="form-control" rows="3" 
                                                          placeholder="Observaciones, especificaciones t&eacute;cnicas..."><?php echo htmlspecialchars($form_data['notas'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Panel lateral de acciones -->
                        <div class="col-lg-4">
                            <div class="card position-sticky" style="top:1rem;">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">Acciones</h6>
                                    <button type="submit" class="btn btn-primary w-100 mb-2">
                                        <i class="bi bi-check-circle me-1"></i>Guardar Equipo
                                    </button>
                                    <button type="submit" name="agregar_otro" value="1" class="btn btn-outline-primary w-100 mb-3">
                                        <i class="bi bi-plus me-1"></i>Guardar y Agregar Otro
                                    </button>
                                    <hr>
                                    <a href="<?php echo URL_BASE; ?>dashboard/sistemas/ti_sistemas/inventario.php" class="btn btn-outline-secondary w-100">
                                        Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js"></script>
    
    <script>
    function seleccionarTipo(tipo) {
        document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('selected'));
        event.currentTarget.closest('.tipo-card').classList.add('selected');
        document.getElementById('inputTipoEquipo').value = tipo;
    }
    
    // Validar antes de enviar
    document.getElementById('formCrearEquipo').addEventListener('submit', function(e) {
        if (!document.getElementById('inputTipoEquipo').value) {
            e.preventDefault();
            alert('Debe seleccionar un tipo de equipo.');
        }
    });
    </script>
</body>
</html>