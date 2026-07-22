<?php
/**
 * Cambiar estado de una solicitud (solo TI)
 * Permite atender, actualizar y finalizar solicitudes
 * 
 * ACTUALIZADO:
 * - Botón "Volver al Dashboard"
 * - Registro en historial_estados al cambiar estado
 * - Notificación al solicitante
 * - Rediseño con temática tecnológica (estilo dashboard TI/Sistemas)
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que sea usuario de TI
if (!es_usuario_ti()) {
    establecer_alerta('error', 'No tiene acceso a este panel');
    redirigir(URL_BASE . 'dashboard/departamento.php');
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_tecnico = $_SESSION['nombre_completo'];

// Obtener folio
$folio = isset($_GET['folio']) ? limpiar_dato($_GET['folio']) : '';

if (empty($folio)) {
    establecer_alerta('error', 'Folio no especificado');
    redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
}

// Obtener solicitud
try {
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.nombre_completo as solicitante_nombre, u.departamento
        FROM solicitudes_atencion s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        WHERE s.folio = ?
    ");
    $stmt->execute([$folio]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        establecer_alerta('error', 'Solicitud no encontrada');
        redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
    }
    
} catch (Exception $e) {
    establecer_alerta('error', 'Error al cargar la solicitud');
    redirigir(URL_BASE . 'ti_sistemas/todas_solicitudes.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nuevo_estado = limpiar_dato($_POST['estado']);
        $comentarios = limpiar_dato($_POST['comentarios']);
        $estado_anterior = $solicitud['estado']; // Guardar estado anterior para el historial
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        $sql = "UPDATE solicitudes_atencion SET 
                estado = ?,
                comentarios_ti = ?,
                atendido_por = ?,
                fecha_actualizacion = NOW()";
        
        $params = [$nuevo_estado, $comentarios, $usuario_id];
        
        // Si se finaliza, agregar fecha de atención
        if ($nuevo_estado == 'finalizada') {
            $sql .= ", fecha_atencion = NOW()";
        }
        
        $sql .= " WHERE folio = ?";
        $params[] = $folio;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // ====================================
        // REGISTRAR EN HISTORIAL DE ESTADOS
        // ====================================
        $stmt_historial = $pdo->prepare("
            INSERT INTO historial_estados 
            (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt_historial->execute([
            $solicitud['id'],
            $estado_anterior,
            $nuevo_estado,
            $comentarios,
            $usuario_id
        ]);
        
        // Confirmar transacción
        $pdo->commit();
        
        // ====================================
        // ENVIAR NOTIFICACIÓN AL SOLICITANTE
        // ====================================
        require_once __DIR__ . '/../includes/notificaciones.php';
        
        notificar_cambio_estado(
            $folio,
            $solicitud['usuario_id'],
            $nuevo_estado,
            $comentarios
        );
        
        establecer_alerta('success', 'Solicitud actualizada correctamente');
        redirigir(URL_BASE . 'solicitudes/ver.php?folio=' . urlencode($folio));
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        establecer_alerta('error', 'Error al actualizar: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Solicitud - <?php echo htmlspecialchars($folio); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/dashboard.css">
    <!-- CSS Modular Responsive -->
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/base/variables.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/components/hamburger.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/layouts/dashboard-layout.css">
    <link rel="stylesheet" href="<?php echo URL_BASE; ?>assets/css/utilities/responsive.css">

    <!-- ==========================================================
         ESTILOS SCOPED (no afectan otras páginas)
         Misma temática tecnológica del dashboard TI/Sistemas
         Prefijo .sis-* bajo el contenedor .sis-dash
    =========================================================== -->
    <style>
        .sis-dash {
            /* ----- Modo claro (por defecto) ----- */
            --sis-card-bg: #ffffff;
            --sis-panel-bg: #ffffff;
            --sis-text: #0f172a;
            --sis-text-muted: #64748b;
            --sis-border: rgba(15, 23, 42, 0.08);
            --sis-shadow: 0 10px 30px -14px rgba(2, 6, 23, 0.18);
            --sis-shadow-hover: 0 20px 44px -16px rgba(37, 99, 235, 0.28);
            --sis-track: #f1f5f9;
            --sis-grid-line: rgba(79, 70, 229, 0.07);
            --sis-hero-grad: linear-gradient(120deg, #312e81 0%, #4338ca 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            --sis-input-bg: #ffffff;
            --sis-mono: ui-monospace, 'SFMono-Regular', 'JetBrains Mono', Menlo, Consolas, monospace;

            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--sis-text);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        body[data-theme="dark"] .sis-dash {
            /* ----- Modo oscuro ----- */
            --sis-card-bg: #131a2c;
            --sis-panel-bg: #131a2c;
            --sis-text: #e2e8f0;
            --sis-text-muted: #94a3b8;
            --sis-border: rgba(148, 163, 184, 0.16);
            --sis-shadow: 0 10px 30px -14px rgba(0, 0, 0, 0.6);
            --sis-shadow-hover: 0 20px 44px -16px rgba(8, 145, 178, 0.45);
            --sis-track: rgba(255, 255, 255, 0.04);
            --sis-grid-line: rgba(34, 211, 238, 0.07);
            --sis-hero-grad: linear-gradient(120deg, #1e1b4b 0%, #3730a3 42%, #0e7490 100%);
            --sis-panel-head: linear-gradient(120deg, #3730a3 0%, #1d4ed8 55%, #0e7490 100%);
            --sis-input-bg: #0f1626;
        }

        /* ---------- Hero ---------- */
        .sis-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 1.5rem 1.9rem;
            background: var(--sis-hero-grad);
            color: #fff;
            box-shadow: 0 18px 42px -18px rgba(37, 99, 235, 0.6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .sis-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 26px 26px;
            mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            -webkit-mask-image: linear-gradient(120deg, rgba(0,0,0,0.9), transparent 75%);
            pointer-events: none;
        }
        .sis-hero::after {
            content: "";
            position: absolute;
            top: -70px;
            right: -30px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.35) 0%, transparent 70%);
            pointer-events: none;
        }
        .sis-hero__left {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1.1rem;
        }
        .sis-hero__icon {
            flex-shrink: 0;
            width: 58px;
            height: 58px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
        }
        .sis-hero__title { margin: 0; font-size: 1.45rem; font-weight: 700; line-height: 1.1; }
        .sis-hero__subtitle {
            margin: 0.3rem 0 0;
            font-size: 0.88rem;
            opacity: 0.92;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .sis-hero__folio { font-family: var(--sis-mono); font-weight: 600; letter-spacing: -0.01em; }
        .sis-hero__right { position: relative; z-index: 1; display: inline-flex; gap: 0.6rem; flex-wrap: wrap; }
        .sis-hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 11px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.32);
            transition: background .2s ease, transform .2s ease;
            white-space: nowrap;
        }
        .sis-hero-btn:hover { background: rgba(255, 255, 255, 0.3); color: #fff; transform: translateY(-2px); }

        /* ---------- Paneles ---------- */
        .sis-columns {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 1.25rem;
            align-items: start;
        }
        .sis-panel {
            background: var(--sis-panel-bg);
            border: 1px solid var(--sis-border);
            border-radius: 18px;
            box-shadow: var(--sis-shadow);
            overflow: hidden;
        }
        .sis-panel + .sis-panel { margin-top: 1.25rem; }
        .sis-panel__head {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.95rem 1.3rem;
            background: var(--sis-panel-head);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
        }
        .sis-panel__body { padding: 1.4rem; }

        /* ---------- Información de la solicitud ---------- */
        .sis-info-row { padding: 0.7rem 0; border-bottom: 1px solid var(--sis-border); }
        .sis-info-row:first-child { padding-top: 0; }
        .sis-info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .sis-info-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sis-text-muted);
            margin-bottom: 0.25rem;
        }
        .sis-info-value { font-size: 0.94rem; color: var(--sis-text); word-break: break-word; }
        .sis-info-value--folio { font-family: var(--sis-mono); font-weight: 600; color: #2563eb; }
        body[data-theme="dark"] .sis-info-value--folio { color: #67e8f9; }

        .sis-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.72rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .sis-chip--critica    { background: rgba(239,68,68,0.15);  color: #b91c1c; }
        .sis-chip--alta       { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--media      { background: rgba(6,182,212,0.15);  color: #0e7490; }
        .sis-chip--baja       { background: rgba(100,116,139,0.15);color: #475569; }
        .sis-chip--pendiente  { background: rgba(245,158,11,0.15); color: #b45309; }
        .sis-chip--en_proceso { background: rgba(59,130,246,0.15); color: #1d4ed8; }
        .sis-chip--finalizada { background: rgba(16,185,129,0.15); color: #047857; }
        .sis-chip--cancelada  { background: rgba(100,116,139,0.15);color: #475569; }
        body[data-theme="dark"] .sis-chip--critica    { color: #fca5a5; }
        body[data-theme="dark"] .sis-chip--alta       { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--media      { color: #67e8f9; }
        body[data-theme="dark"] .sis-chip--baja       { color: #cbd5e1; }
        body[data-theme="dark"] .sis-chip--pendiente  { color: #fcd34d; }
        body[data-theme="dark"] .sis-chip--en_proceso { color: #93c5fd; }
        body[data-theme="dark"] .sis-chip--finalizada { color: #6ee7b7; }
        body[data-theme="dark"] .sis-chip--cancelada  { color: #cbd5e1; }

        /* ---------- Formulario ---------- */
        .sis-field { margin-bottom: 1.2rem; }
        .sis-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--sis-text);
            margin-bottom: 0.4rem;
        }
        .sis-label.required::after { content: " *"; color: #ef4444; }
        .sis-dash .form-select,
        .sis-dash .form-control {
            background-color: var(--sis-input-bg);
            border: 1px solid var(--sis-border);
            color: var(--sis-text);
            border-radius: 11px;
            font-size: 0.9rem;
            padding: 0.6rem 0.85rem;
        }
        .sis-dash .form-select:focus,
        .sis-dash .form-control:focus {
            background-color: var(--sis-input-bg);
            color: var(--sis-text);
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.18);
        }
        body[data-theme="dark"] .sis-dash .form-control::placeholder { color: #64748b; }
        .sis-hint { display: block; margin-top: 0.4rem; font-size: 0.78rem; color: var(--sis-text-muted); }

        /* Aviso "Atendido por" */
        .sis-tecnico {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: var(--sis-text);
            font-size: 0.9rem;
            margin-bottom: 1.3rem;
        }
        .sis-tecnico i { color: #0891b2; font-size: 1.1rem; }
        body[data-theme="dark"] .sis-tecnico i { color: #67e8f9; }

        /* Botones */
        .sis-form-actions {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .sis-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.3rem;
            border-radius: 11px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: filter .2s ease, transform .2s ease;
        }
        .sis-btn:hover { transform: translateY(-2px); filter: brightness(1.06); }
        .sis-btn--cancel {
            color: var(--sis-text-muted);
            background: var(--sis-track);
            border-color: var(--sis-border);
        }
        .sis-btn--cancel:hover { color: var(--sis-text); }
        .sis-btn--submit {
            color: #fff;
            background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #0891b2 100%);
            box-shadow: 0 10px 24px -12px rgba(37, 99, 235, 0.85);
        }

        /* Comentarios anteriores */
        .sis-prev-comment { font-size: 0.92rem; color: var(--sis-text); white-space: pre-line; }
        .sis-prev-date { display: inline-flex; align-items: center; gap: 0.35rem; margin-top: 0.6rem; font-size: 0.8rem; color: var(--sis-text-muted); }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) {
            .sis-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .sis-hero { padding: 1.3rem 1.25rem; }
            .sis-hero__title { font-size: 1.25rem; }
            .sis-hero__icon { width: 50px; height: 50px; font-size: 1.5rem; }
            .sis-hero__right { width: 100%; }
            .sis-hero-btn { flex: 1; justify-content: center; }
            .sis-form-actions { flex-direction: column-reverse; }
            .sis-btn { width: 100%; justify-content: center; }
        }
    </style>

    <!-- Sistema de notificaciones -->
    <script src="<?php echo URL_BASE; ?>assets/js/notificaciones.js" defer></script>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/../includes/sidebar/sidebar_ti.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <div class="content-wrapper">

                <div class="sis-dash">

                    <!-- Hero -->
                    <div class="sis-hero">
                        <div class="sis-hero__left">
                            <div class="sis-hero__icon">
                                <i class="bi bi-sliders"></i>
                            </div>
                            <div>
                                <h1 class="sis-hero__title">Gestionar Solicitud</h1>
                                <p class="sis-hero__subtitle">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span class="sis-hero__folio"><?php echo htmlspecialchars($solicitud['folio']); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="sis-hero__right">
                            <a href="<?php echo URL_BASE; ?>solicitudes/ver.php?folio=<?php echo urlencode($folio); ?>" class="sis-hero-btn">
                                <i class="bi bi-eye"></i> Ver Solicitud
                            </a>
                            <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="sis-hero-btn">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php echo mostrar_alerta(); ?>

                    <div class="sis-columns">

                        <!-- Información de la Solicitud -->
                        <div>
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <i class="bi bi-info-circle"></i> Informaci&oacute;n de la Solicitud
                                </div>
                                <div class="sis-panel__body">
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Folio</span>
                                        <span class="sis-info-value sis-info-value--folio"><?php echo htmlspecialchars($solicitud['folio']); ?></span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Solicitante</span>
                                        <span class="sis-info-value"><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Departamento</span>
                                        <span class="sis-info-value"><?php echo htmlspecialchars($solicitud['departamento']); ?></span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Tipo</span>
                                        <span class="sis-info-value"><?php echo htmlspecialchars($solicitud['tipo_soporte']); ?></span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Prioridad</span>
                                        <span class="sis-info-value">
                                            <span class="sis-chip sis-chip--<?php echo htmlspecialchars($solicitud['prioridad']); ?>">
                                                <?php echo ucfirst($solicitud['prioridad']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Estado Actual</span>
                                        <span class="sis-info-value">
                                            <span class="sis-chip sis-chip--<?php echo htmlspecialchars($solicitud['estado']); ?>">
                                                <?php echo obtener_texto_estado($solicitud['estado']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="sis-info-row">
                                        <span class="sis-info-label">Descripci&oacute;n</span>
                                        <span class="sis-info-value"><?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de Actualización -->
                        <div>
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <i class="bi bi-gear"></i> Actualizar Estado
                                </div>
                                <div class="sis-panel__body">
                                    <form method="POST">

                                        <!-- Cambiar Estado -->
                                        <div class="sis-field">
                                            <label for="estado" class="sis-label required">Nuevo Estado</label>
                                            <select class="form-select" id="estado" name="estado" required>
                                                <option value="">Seleccione...</option>
                                                <option value="pendiente" <?php echo $solicitud['estado'] == 'pendiente' ? 'selected' : ''; ?>>
                                                    Pendiente
                                                </option>
                                                <option value="en_proceso" <?php echo $solicitud['estado'] == 'en_proceso' ? 'selected' : ''; ?>>
                                                    En Proceso
                                                </option>
                                                <option value="finalizada" <?php echo $solicitud['estado'] == 'finalizada' ? 'selected' : ''; ?>>
                                                    Finalizada
                                                </option>
                                                <option value="cancelada">
                                                    Cancelada
                                                </option>
                                            </select>
                                            <small class="sis-hint">Estado actual: <?php echo obtener_texto_estado($solicitud['estado']); ?></small>
                                        </div>

                                        <!-- Comentarios -->
                                        <div class="sis-field">
                                            <label for="comentarios" class="sis-label required">Comentarios / Observaciones</label>
                                            <textarea class="form-control" id="comentarios" name="comentarios" rows="6" required 
                                                      placeholder="Describe las acciones realizadas o la solución aplicada..."><?php echo htmlspecialchars($solicitud['comentarios_ti'] ?? ''); ?></textarea>
                                            <small class="sis-hint">Estos comentarios ser&aacute;n visibles para el solicitante y quedar&aacute;n registrados en el historial</small>
                                        </div>

                                        <!-- Info del técnico -->
                                        <div class="sis-tecnico">
                                            <i class="bi bi-person-check"></i>
                                            <span><strong>Atendido por:</strong> <?php echo htmlspecialchars($nombre_tecnico); ?></span>
                                        </div>

                                        <!-- Botones -->
                                        <div class="sis-form-actions">
                                            <a href="<?php echo URL_BASE; ?>ti_sistemas/gestion_solicitudes.php" class="sis-btn sis-btn--cancel">
                                                <i class="bi bi-x-circle"></i> Cancelar
                                            </a>
                                            <button type="submit" class="sis-btn sis-btn--submit">
                                                <i class="bi bi-check-circle"></i> Actualizar Solicitud
                                            </button>
                                        </div>

                                    </form>
                                </div>
                            </div>

                            <!-- Historial (si existe) -->
                            <?php if (!empty($solicitud['comentarios_ti'])): ?>
                            <div class="sis-panel">
                                <div class="sis-panel__head">
                                    <i class="bi bi-clock-history"></i> Comentarios Anteriores
                                </div>
                                <div class="sis-panel__body">
                                    <p class="sis-prev-comment mb-0"><?php echo nl2br(htmlspecialchars($solicitud['comentarios_ti'])); ?></p>
                                    <?php if ($solicitud['fecha_actualizacion']): ?>
                                    <span class="sis-prev-date">
                                        <i class="bi bi-clock"></i>
                                        <?php echo formatear_fecha($solicitud['fecha_actualizacion'], true); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>

                    </div>

                </div><!-- /.sis-dash -->

            </div>
        </main>

    </div>

    <!-- Botón flotante de cambio de tema -->
    <button class="theme-toggle-float" id="themeToggle" aria-label="Cambiar tema">
        <span class="icon-sun"><i class="bi bi-sun-fill"></i></span>
        <span class="icon-moon"><i class="bi bi-moon-fill"></i></span>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar Toggle JS -->
    <script src="<?php echo URL_BASE; ?>assets/js/sidebar-toggle.js"></script>
    
    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const bodyElement = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        bodyElement.setAttribute('data-theme', currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = bodyElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            themeToggle.classList.add('rotating');
            setTimeout(() => {
                themeToggle.classList.remove('rotating');
            }, 500);
            bodyElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Confirmación al cambiar a finalizada o cancelada
        document.getElementById('estado').addEventListener('change', function() {
            if (this.value === 'finalizada') {
                if (!confirm('¿Está seguro de marcar esta solicitud como finalizada? Esta acción indica que el problema está resuelto.')) {
                    this.value = '<?php echo $solicitud['estado']; ?>';
                }
            } else if (this.value === 'cancelada') {
                if (!confirm('¿Está seguro de cancelar esta solicitud? Esta acción no se puede deshacer fácilmente.')) {
                    this.value = '<?php echo $solicitud['estado']; ?>';
                }
            }
        });
    </script>

</body>
</html>