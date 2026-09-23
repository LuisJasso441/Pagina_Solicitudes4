<?php
/**
 * Catálogo de Tipos de Envase + Especificaciones — v2 (estética temática SEC)
 * dashboard/salidas_envases/catalogo/tipos_envase.php
 *
 * Permisos:
 *   - Almacén de Residuos: CRUD completo.
 *   - Logística y Ventas: solo lectura.
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../includes/salidas_envases/tipos_envase_funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$dept = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
$permitidos = ['logistica', 'almacen_residuos', 'ventas'];
if (!in_array($dept, $permitidos, true)) {
    header('Location: ' . URL_BASE . 'dashboard/inicio.php');
    exit;
}

$puede_editar = puede_administrar_tipos_envase();

$tipos = obtener_tipos_envase(true); // incluye inactivos para admin
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Envase | <?php echo defined('NOMBRE_SISTEMA') ? NOMBRE_SISTEMA : 'Verden'; ?></title>
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
        /* Variables SEC en scope global — los modales de Bootstrap se
           mueven al final del <body>, así que necesitan estas vars ahí. */
        body {
            --sec-verde:       #2f7d5b;
            --sec-verde-osc:   #1e5a3f;
            --sec-verde-soft:  rgba(47, 125, 91, 0.10);
            --sec-ambar:       #d97706;
            --sec-ambar-soft:  rgba(217, 119, 6, 0.12);
            --sec-azul:        #1d4ed8;
            --sec-azul-soft:   rgba(29, 78, 216, 0.12);
            --sec-rojo:        #b91c1c;
            --sec-rojo-soft:   rgba(185, 28, 28, 0.12);
            --sec-tierra:      #92603a;
            --sec-tierra-soft: rgba(146, 96, 58, 0.12);

            --sec-bg-base:     #f9f6ee;
            --sec-card:        #ffffff;
            --sec-text:        #1c2b21;
            --sec-text-muted:  #6b7268;
            --sec-border:      #e3ddce;
            --sec-border-soft: #efe9dc;
            --sec-shadow-sm:   0 2px 6px rgba(30, 90, 63, 0.05);
            --sec-shadow-md:   0 8px 20px -8px rgba(30, 90, 63, 0.14);
            --sec-hover-bg:    #f9f6ee;
        }

        .sec-page {
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sec-text);
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        /* Header */
        .sec-page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--sec-verde);
        }
        .sec-page-head__title { display: flex; align-items: center; gap: 0.85rem; }
        .sec-page-head__stamp {
            width: 48px; height: 48px;
            border-radius: 10px;
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 1.5px solid var(--sec-verde);
        }
        .sec-page-head h1 { margin: 0; font-size: 1.35rem; font-weight: 700; color: var(--sec-text); }
        .sec-page-head__subtitle { margin: 0; font-size: 0.82rem; color: var(--sec-text-muted); }
        .sec-actions-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        /* Botones */
        .sec-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.95rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--sec-border);
            background: var(--sec-card);
            color: var(--sec-text);
            box-shadow: var(--sec-shadow-sm);
            cursor: pointer;
            transition: all .18s;
        }
        .sec-btn:hover { transform: translateY(-1px); box-shadow: var(--sec-shadow-md); color: var(--sec-text); }
        .sec-btn--primary {
            background: var(--sec-verde);
            border-color: var(--sec-verde);
            border-left: 4px solid var(--sec-verde-osc);
            color: #fff;
        }
        .sec-btn--primary:hover { background: var(--sec-verde-osc); color: #fff; }
        .sec-btn:disabled, .sec-btn.disabled { opacity: 0.55; cursor: not-allowed; }
        .sec-btn--sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; }
        .sec-btn i { font-size: 1rem; }

        /* Grid de tipos */
        .sec-tipos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
        }

        /* Card de un Tipo — "expediente operativo" */
        .sec-tipo-card {
            position: relative;
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 10px;
            box-shadow: var(--sec-shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .sec-tipo-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--sec-verde);
            border-radius: 10px 10px 0 0;
        }
        .sec-tipo-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--sec-shadow-md);
            border-color: var(--sec-verde);
        }
        .sec-tipo-card--inactivo { opacity: 0.62; }
        .sec-tipo-card--inactivo::before { background: var(--sec-tierra); }

        .sec-tipo-card__head {
            padding: 1rem 1.2rem 0.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.7rem;
        }
        .sec-tipo-card__title {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            flex: 1;
            min-width: 0;
        }
        .sec-tipo-card__icon {
            flex-shrink: 0;
            width: 38px; height: 38px;
            border-radius: 8px;
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .sec-tipo-card__nombre {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sec-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .sec-tipo-card__count {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-muted);
            margin-top: 2px;
        }
        .sec-badge-inactivo {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.66rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--sec-tierra-soft);
            color: var(--sec-tierra);
            border-radius: 4px;
        }

        /* Dropdown de acciones */
        .sec-menu-btn {
            width: 30px; height: 30px;
            border-radius: 6px;
            border: 1px solid var(--sec-border);
            background: var(--sec-card);
            color: var(--sec-text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .15s;
        }
        .sec-menu-btn:hover, .sec-menu-btn[aria-expanded="true"] {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border-color: var(--sec-verde);
        }
        .sec-dropdown-menu {
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 8px;
            box-shadow: var(--sec-shadow-md);
            padding: 4px;
            min-width: 140px;
        }
        .sec-dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.7rem;
            font-size: 0.85rem;
            border-radius: 5px;
            color: var(--sec-text);
        }
        .sec-dropdown-menu .dropdown-item:hover {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
        }
        .sec-dropdown-menu .dropdown-item.text-danger { color: var(--sec-rojo); }
        .sec-dropdown-menu .dropdown-item.text-danger:hover {
            background: var(--sec-rojo-soft);
            color: var(--sec-rojo);
        }

        /* Lista de especificaciones — checklist operativo */
        .sec-tipo-card__body {
            padding: 0.5rem 1.2rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex: 1;
        }
        .sec-espec-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .sec-espec-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem 0;
            border-bottom: 1px dashed var(--sec-border);
            font-size: 0.87rem;
        }
        .sec-espec-item:last-child { border-bottom: none; }
        .sec-espec-item--inactiva { opacity: 0.55; }
        .sec-espec-item__label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--sec-text);
            min-width: 0;
            flex: 1;
        }
        .sec-espec-item__bullet {
            width: 6px; height: 6px;
            border-radius: 2px;
            background: var(--sec-verde);
            flex-shrink: 0;
        }
        .sec-espec-item--inactiva .sec-espec-item__bullet { background: var(--sec-tierra); }
        .sec-espec-item__actions {
            display: flex;
            gap: 0.25rem;
            flex-shrink: 0;
        }
        .sec-mini-btn {
            width: 26px; height: 26px;
            border-radius: 5px;
            background: transparent;
            border: 1px solid var(--sec-border);
            color: var(--sec-text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .15s;
            font-size: 0.82rem;
        }
        .sec-mini-btn:hover {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border-color: var(--sec-verde);
        }
        .sec-mini-btn--danger:hover {
            background: var(--sec-rojo-soft);
            color: var(--sec-rojo);
            border-color: var(--sec-rojo);
        }
        .sec-espec-empty {
            color: var(--sec-text-muted);
            font-size: 0.82rem;
            padding: 0.5rem 0;
            font-style: italic;
        }
        .sec-add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: 1.5px dashed var(--sec-border);
            background: transparent;
            color: var(--sec-text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            margin-top: 0.5rem;
        }
        .sec-add-btn:hover {
            background: var(--sec-verde-soft);
            border-color: var(--sec-verde);
            color: var(--sec-verde);
            border-style: solid;
        }

        /* Empty state */
        .sec-empty {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--sec-text-muted);
            font-size: 0.9rem;
            background: var(--sec-card);
            border: 1px dashed var(--sec-border);
            border-radius: 10px;
        }
        .sec-empty i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.6rem;
            opacity: 0.4;
        }

        /* Form controls modal */
        .sec-form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sec-text-muted);
            margin-bottom: 0.35rem;
            display: block;
        }
        .sec-form-label .req { color: var(--sec-rojo); }
        .sec-input, .sec-select {
            width: 100%;
            padding: 0.55rem 0.85rem;
            font-size: 0.9rem;
            font-family: inherit;
            color: var(--sec-text);
            background: var(--sec-card);
            border: 1px solid var(--sec-border);
            border-radius: 6px;
            transition: border-color .15s, box-shadow .15s;
        }
        .sec-input:focus, .sec-select:focus {
            outline: none;
            border-color: var(--sec-verde);
            box-shadow: 0 0 0 3px var(--sec-verde-soft);
        }

        /* Radio tile del modal "Nuevo/Existente" */
        .sec-mode-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .sec-mode-tile {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 0.85rem;
            border: 1.5px solid var(--sec-border);
            border-radius: 8px;
            cursor: pointer;
            background: var(--sec-card);
            color: var(--sec-text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            transition: all .15s;
        }
        .sec-mode-tile i { font-size: 1.1rem; }
        .sec-mode-tile input[type="radio"] { display: none; }
        .sec-mode-tile:hover { border-color: var(--sec-verde); }
        .sec-mode-tile.selected {
            background: var(--sec-verde-soft);
            color: var(--sec-verde);
            border-color: var(--sec-verde);
        }

        /* Switch activo */
        .sec-switch-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 0.75rem;
            background: var(--sec-bg-base);
            border: 1px solid var(--sec-border);
            border-radius: 6px;
        }
        .sec-switch-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--sec-text);
            margin: 0;
        }

        /* Divider dentro de modal con etiqueta */
        .sec-modal-divider {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0.5rem 0 0.85rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sec-text-muted);
        }
        .sec-modal-divider::before,
        .sec-modal-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--sec-border);
        }

        /* Label con icono */
        .sec-form-label i {
            color: var(--sec-verde);
            font-size: 0.9rem;
            margin-right: 0.3rem;
        }

        /* Hint bajo input */
        .sec-input-hint {
            display: block;
            font-size: 0.75rem;
            color: var(--sec-text-muted);
            margin-top: 0.35rem;
            font-style: italic;
        }
        .sec-input-hint i { font-size: 0.85rem; }

        /* ========= Modales de Bootstrap con paleta SEC ========= */
        .modal-content {
            background: var(--sec-card);
            color: var(--sec-text);
            border: 1px solid var(--sec-border);
            border-radius: 12px;
            box-shadow: var(--sec-shadow-md);
        }
        .modal-header {
            border-bottom: 1px solid var(--sec-border);
            background: var(--sec-bg-base);
            padding: 0.9rem 1.2rem;
            border-radius: 12px 12px 0 0;
        }
        .modal-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--sec-text);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-title i { color: var(--sec-verde); font-size: 1.15rem; }
        .modal-body {
            padding: 1.15rem 1.2rem;
        }
        .modal-footer {
            border-top: 1px solid var(--sec-border);
            background: var(--sec-bg-base);
            padding: 0.75rem 1.2rem;
            border-radius: 0 0 12px 12px;
            gap: 0.5rem;
        }
        .modal-footer > * { margin: 0; }

        /* Responsive */
        @media (max-width: 640px) {
            .sec-page-head { padding-bottom: 0.85rem; }
            .sec-page-head h1 { font-size: 1.15rem; }
            .sec-page-head__stamp { width: 42px; height: 42px; font-size: 1.3rem; }
            .sec-tipos-grid { grid-template-columns: 1fr; }
            .sec-tipo-card__head { padding: 0.85rem 1rem 0.4rem; }
            .sec-tipo-card__body { padding: 0.4rem 1rem 0.85rem; }
            .sec-mode-group { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar/sidebar_sec.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">

                <div class="sec-page">

                    <!-- Header -->
                    <div class="sec-page-head">
                        <div class="sec-page-head__title">
                            <div class="sec-page-head__stamp"><i class="bi bi-box2"></i></div>
                            <div>
                                <h1>Tipos de Envase</h1>
                                <p class="sec-page-head__subtitle">
                                    <?php echo $puede_editar
                                        ? 'Gestiona el catálogo de envases y sus especificaciones.'
                                        : 'Consulta el catálogo de envases (solo lectura).'; ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($puede_editar): ?>
                        <div class="sec-actions-row">
                            <button type="button" class="sec-btn" disabled title="Función próximamente disponible">
                                <i class="bi bi-file-earmark-arrow-up"></i> Importar
                            </button>
                            <button type="button" class="sec-btn sec-btn--primary"
                                    data-bs-toggle="modal" data-bs-target="#modalNuevoTipo">
                                <i class="bi bi-plus-circle"></i> Agregar
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Listado -->
                    <?php if (empty($tipos)): ?>
                        <div class="sec-empty">
                            <i class="bi bi-inbox"></i>
                            Aún no hay tipos de envase registrados.
                            <?php if ($puede_editar): ?>
                                <br><small>Presiona <strong>Agregar</strong> para crear el primero.</small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sec-tipos-grid">
                            <?php foreach ($tipos as $tipo):
                                $especs = obtener_especificaciones($tipo['id'], true);
                                $n = count($especs);
                                $activo = (int) $tipo['activo'] === 1;
                            ?>
                            <div class="sec-tipo-card <?php echo $activo ? '' : 'sec-tipo-card--inactivo'; ?>">
                                <div class="sec-tipo-card__head">
                                    <div class="sec-tipo-card__title">
                                        <div class="sec-tipo-card__icon"><i class="bi bi-box"></i></div>
                                        <div style="min-width: 0; flex: 1;">
                                            <h3 class="sec-tipo-card__nombre">
                                                <span><?php echo htmlspecialchars($tipo['nombre']); ?></span>
                                                <?php if (!$activo): ?>
                                                    <span class="sec-badge-inactivo">Inactivo</span>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="sec-tipo-card__count">
                                                <?php echo $n . ' especificaci' . ($n === 1 ? 'ón' : 'ones'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($puede_editar): ?>
                                    <div class="dropdown">
                                        <button class="sec-menu-btn" data-bs-toggle="dropdown" aria-label="Acciones">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end sec-dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#"
                                                   onclick="editarTipo(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>, <?php echo (int) $tipo['activo']; ?>); return false;">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#"
                                                   onclick="eliminarTipo(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>); return false;">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="sec-tipo-card__body">
                                    <?php if (empty($especs)): ?>
                                        <p class="sec-espec-empty">Sin especificaciones.</p>
                                    <?php else: ?>
                                        <ul class="sec-espec-list">
                                            <?php foreach ($especs as $espec):
                                                $esp_activo = (int) $espec['activo'] === 1;
                                            ?>
                                            <li class="sec-espec-item <?php echo $esp_activo ? '' : 'sec-espec-item--inactiva'; ?>">
                                                <span class="sec-espec-item__label">
                                                    <span class="sec-espec-item__bullet"></span>
                                                    <span><?php echo htmlspecialchars($espec['nombre']); ?></span>
                                                    <?php if (!$esp_activo): ?>
                                                        <span class="sec-badge-inactivo">Inactiva</span>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($puede_editar): ?>
                                                <span class="sec-espec-item__actions">
                                                    <button type="button" class="sec-mini-btn" title="Editar"
                                                            onclick="editarEspec(<?php echo (int) $espec['id']; ?>, <?php echo htmlspecialchars(json_encode($espec['nombre']), ENT_QUOTES); ?>, <?php echo (int) $espec['activo']; ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="sec-mini-btn sec-mini-btn--danger" title="Eliminar"
                                                            onclick="eliminarEspec(<?php echo (int) $espec['id']; ?>, <?php echo htmlspecialchars(json_encode($espec['nombre']), ENT_QUOTES); ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </span>
                                                <?php endif; ?>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if ($puede_editar): ?>
                                    <button type="button" class="sec-add-btn"
                                            onclick="agregarEspecRapido(<?php echo (int) $tipo['id']; ?>, <?php echo htmlspecialchars(json_encode($tipo['nombre']), ENT_QUOTES); ?>)">
                                        <i class="bi bi-plus-circle"></i> Agregar especificación
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php if ($puede_editar): ?>
    <!-- MODAL: Nuevo Tipo / Especificación -->
    <div class="modal fade" id="modalNuevoTipo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/guardar_tipo_envase.php" id="formNuevoTipo">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-box2"></i> Nuevo tipo de envase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Selector de modo -->
                        <div class="mb-4">
                            <label class="sec-form-label">Modo</label>
                            <div class="sec-mode-group">
                                <label class="sec-mode-tile" data-modo="nuevo">
                                    <input type="radio" name="modo" value="nuevo" checked>
                                    <i class="bi bi-plus-circle"></i>
                                    Nuevo tipo de envase
                                </label>
                                <label class="sec-mode-tile" data-modo="existente">
                                    <input type="radio" name="modo" value="existente">
                                    <i class="bi bi-plus-square"></i>
                                    Tipo de envase existente
                                </label>
                            </div>
                            <small style="color: var(--sec-text-muted); font-size: 0.78rem; display: block; margin-top: 0.55rem;">
                                <strong>Nuevo:</strong> crea un tipo desde cero con su primera especificación.<br>
                                <strong>Existente:</strong> agrega una especificación nueva a un tipo ya registrado.
                            </small>
                        </div>

                        <div class="sec-modal-divider">
                            <i class="bi bi-pencil-square"></i> Datos del registro
                        </div>

                        <div class="row g-3">
                            <!-- Columna Tipo (nuevo o existente según modo) -->
                            <div class="col-md-6" id="colTipoNuevo">
                                <label class="sec-form-label"><i class="bi bi-box2"></i>Tipo de envase <span class="req">*</span></label>
                                <input type="text" class="sec-input" name="tipo_nuevo" id="inputTipoNuevo"
                                       maxlength="100" placeholder="Ej. Tambo, Tote, Garrafa" required>
                                <small class="sec-input-hint">
                                    <i class="bi bi-info-circle"></i> Categoría general del envase.
                                </small>
                            </div>

                            <div class="col-md-6" id="colTipoExistente" style="display:none;">
                                <label class="sec-form-label"><i class="bi bi-box2"></i>Tipo de envase <span class="req">*</span></label>
                                <select class="sec-select" name="tipo_id" id="selectTipoExistente">
                                    <option value="">Seleccione un tipo…</option>
                                    <?php foreach ($tipos as $t): if (!$t['activo']) continue; ?>
                                    <option value="<?php echo (int) $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                $tipos_activos = array_filter($tipos, static fn($t) => (int) $t['activo'] === 1);
                                if (empty($tipos_activos)):
                                ?>
                                <small style="color: var(--sec-ambar); font-size: 0.78rem; display: block; margin-top: 0.4rem;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Aún no hay tipos activos. Cambia a modo "Nuevo tipo".
                                </small>
                                <?php endif; ?>
                            </div>

                            <!-- Columna Especificación -->
                            <div class="col-md-6">
                                <label class="sec-form-label"><i class="bi bi-tag"></i>Especificación <span class="req">*</span></label>
                                <input type="text" class="sec-input" name="especificacion" id="inputEspec"
                                       maxlength="150" placeholder="Ej. Tambo abierto, Tote 1000L" required>
                                <small class="sec-input-hint">
                                    <i class="bi bi-info-circle"></i> Detalle o capacidad específica del envase.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="sec-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="sec-btn sec-btn--primary">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Editar Tipo -->
    <div class="modal fade" id="modalEditarTipo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/actualizar_tipo_envase.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar tipo de envase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editTipoId">
                        <div class="mb-3">
                            <label class="sec-form-label"><i class="bi bi-box2"></i>Nombre <span class="req">*</span></label>
                            <input type="text" class="sec-input" name="nombre" id="editTipoNombre" maxlength="100" required>
                        </div>
                        <div class="sec-switch-row">
                            <div class="form-check form-switch m-0" style="min-height: auto;">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="form-check-input" name="activo" id="editTipoActivo" value="1" style="margin-top: 0;">
                            </div>
                            <label class="sec-switch-label" for="editTipoActivo">Activo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="sec-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="sec-btn sec-btn--primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Editar Especificación -->
    <div class="modal fade" id="modalEditarEspec" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/actualizar_especificacion.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar especificación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editEspecId">
                        <div class="mb-3">
                            <label class="sec-form-label"><i class="bi bi-tag"></i>Nombre <span class="req">*</span></label>
                            <input type="text" class="sec-input" name="nombre" id="editEspecNombre" maxlength="150" required>
                        </div>
                        <div class="sec-switch-row">
                            <div class="form-check form-switch m-0" style="min-height: auto;">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" class="form-check-input" name="activo" id="editEspecActivo" value="1" style="margin-top: 0;">
                            </div>
                            <label class="sec-switch-label" for="editEspecActivo">Activa</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="sec-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="sec-btn sec-btn--primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Agregar Especificación rápido -->
    <div class="modal fade" id="modalAgregarEspec" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/guardar_tipo_envase.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Agregar especificación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="modo" value="existente">
                        <input type="hidden" name="tipo_id" id="rapidoTipoId">
                        <div class="mb-3" style="padding: 0.55rem 0.75rem; background: var(--sec-verde-soft); border-left: 3px solid var(--sec-verde); border-radius: 4px;">
                            <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--sec-text-muted); font-weight: 600;">Tipo:</span>
                            <strong id="rapidoTipoNombre" style="color: var(--sec-verde); margin-left: 4px;"></strong>
                        </div>
                        <div class="mb-2">
                            <label class="sec-form-label"><i class="bi bi-tag"></i>Especificación <span class="req">*</span></label>
                            <input type="text" class="sec-input" name="especificacion" id="rapidoEspec"
                                   maxlength="150" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="sec-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="sec-btn sec-btn--primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formularios silenciosos para eliminación -->
    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/eliminar_tipo_envase.php"
          id="formEliminarTipo" style="display:none;">
        <input type="hidden" name="id" id="eliminarTipoId">
    </form>
    <form method="POST" action="<?php echo URL_BASE; ?>dashboard/salidas_envases/catalogo/eliminar_especificacion.php"
          id="formEliminarEspec" style="display:none;">
        <input type="hidden" name="id" id="eliminarEspecId">
    </form>
    <?php endif; // fin bloque solo Almacén ?>

    <!-- Toast flash -->
    <?php if (isset($_SESSION['flash_msg'])): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
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

    <script>
        // Página siempre en tema claro
        document.body.setAttribute('data-theme', 'light');
    </script>

    <?php if ($puede_editar): ?>
    <script>
    (function () {
        // Alternar modo del modal principal (Nuevo/Existente)
        const modeTiles = document.querySelectorAll('.sec-mode-tile');
        const colNuevo  = document.getElementById('colTipoNuevo');
        const colExist  = document.getElementById('colTipoExistente');
        const inpNuevo  = document.getElementById('inputTipoNuevo');
        const selExist  = document.getElementById('selectTipoExistente');

        function marcarModoSeleccionado() {
            const modo = document.querySelector('input[name="modo"]:checked').value;
            modeTiles.forEach(t => {
                t.classList.toggle('selected', t.dataset.modo === modo);
            });
        }

        function aplicarModo() {
            const modo = document.querySelector('input[name="modo"]:checked').value;
            if (modo === 'nuevo') {
                colNuevo.style.display = '';
                colExist.style.display = 'none';
                inpNuevo.required = true;
                selExist.required = false;
                selExist.value = '';
            } else {
                colNuevo.style.display = 'none';
                colExist.style.display = '';
                inpNuevo.required = false;
                selExist.required = true;
                inpNuevo.value = '';
            }
            marcarModoSeleccionado();
        }

        modeTiles.forEach(t => {
            t.addEventListener('click', () => {
                // Bootstrap fires change on radio, pero por seguridad forzamos aquí también
                setTimeout(aplicarModo, 0);
            });
        });

        // Reset al cerrar modal
        document.getElementById('modalNuevoTipo').addEventListener('hidden.bs.modal', function () {
            document.getElementById('formNuevoTipo').reset();
            document.querySelector('input[name="modo"][value="nuevo"]').checked = true;
            aplicarModo();
        });

        marcarModoSeleccionado();
    })();

    function editarTipo(id, nombre, activo) {
        document.getElementById('editTipoId').value = id;
        document.getElementById('editTipoNombre').value = nombre;
        document.getElementById('editTipoActivo').checked = (activo === 1 || activo === '1');
        new bootstrap.Modal(document.getElementById('modalEditarTipo')).show();
    }

    function editarEspec(id, nombre, activo) {
        document.getElementById('editEspecId').value = id;
        document.getElementById('editEspecNombre').value = nombre;
        document.getElementById('editEspecActivo').checked = (activo === 1 || activo === '1');
        new bootstrap.Modal(document.getElementById('modalEditarEspec')).show();
    }

    function agregarEspecRapido(tipoId, tipoNombre) {
        document.getElementById('rapidoTipoId').value = tipoId;
        document.getElementById('rapidoTipoNombre').textContent = tipoNombre;
        document.getElementById('rapidoEspec').value = '';
        new bootstrap.Modal(document.getElementById('modalAgregarEspec')).show();
    }

    function eliminarTipo(id, nombre) {
        if (confirm('¿Eliminar el tipo "' + nombre + '"?\n\nEsto también eliminará todas sus especificaciones.')) {
            document.getElementById('eliminarTipoId').value = id;
            document.getElementById('formEliminarTipo').submit();
        }
    }

    function eliminarEspec(id, nombre) {
        if (confirm('¿Eliminar la especificación "' + nombre + '"?')) {
            document.getElementById('eliminarEspecId').value = id;
            document.getElementById('formEliminarEspec').submit();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>