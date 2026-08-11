<?php
/**
 * Prueba de envío de correo
 * Solo accesible para usuarios del departamento de Sistemas.
 *
 * Envía un correo a la dirección indicada usando la función central
 * enviar_correo(). En desarrollo (MailHog) cualquier dirección es válida;
 * el correo se captura en http://localhost:8025
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/correo_funciones.php';
require_once __DIR__ . '/../../includes/correo_plantilla.php';

// Verificar sesión
if (empty($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Solo Sistemas
$departamento = strtolower(trim($_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

// Estado del formulario
$destinatario_prev = '';
$asunto_prev = '';
$mensaje_prev = '';
$feedback = null; // ['tipo' => 'success'|'danger', 'texto' => '...']

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destinatario_prev = trim($_POST['destinatario'] ?? '');
    $asunto_prev = trim($_POST['asunto'] ?? '');
    $mensaje_prev = trim($_POST['mensaje'] ?? '');

    if (!filter_var($destinatario_prev, FILTER_VALIDATE_EMAIL)) {
        $feedback = ['tipo' => 'danger', 'texto' => 'Ingresa un correo destinatario válido.'];
    } elseif ($asunto_prev === '') {
        $feedback = ['tipo' => 'danger', 'texto' => 'Ingresa un asunto.'];
    } elseif ($mensaje_prev === '') {
        $feedback = ['tipo' => 'danger', 'texto' => 'Ingresa un mensaje.'];
    } else {
        // Construir el correo usando la plantilla corporativa Grupo Verden
        $usuario_actual = htmlspecialchars($_SESSION['nombre_completo'] ?? 'Sistemas');

        $contenido  = '<p style="margin:0 0 16px 0;">' . nl2br(htmlspecialchars($mensaje_prev)) . '</p>';
        $contenido .= '<p style="margin:24px 0 0 0; color:#666666; font-size:13px;">'
                    . 'Enviado por <strong>' . $usuario_actual . '</strong> desde ' . htmlspecialchars(NOMBRE_SISTEMA)
                    . ' &middot; ' . date('Y-m-d H:i:s')
                    . '</p>';

        $html = renderizar_plantilla_correo(
            $asunto_prev,
            $contenido,
            [
                'preheader' => 'Correo de prueba del sistema SMTP',
                'subtitulo' => 'Correo de prueba - verifica que la plantilla se ve correctamente en tu cliente de correo',
                'boton_cta' => [
                    'texto' => 'Ir al Portal',
                    'url'   => URL_BASE,
                ],
            ]
        );

        $ok = enviar_correo($destinatario_prev, $asunto_prev, $html);

        // Registrar el resultado en `correos_enviados` para trazabilidad
        registrar_correo_enviado([
            'usuario_id'    => (int) ($_SESSION['usuario_id'] ?? 0) ?: null,
            'destinatario'  => $destinatario_prev,
            'tipo'          => 'prueba_manual',
            'asunto'        => $asunto_prev,
            'exito'         => $ok,
            'error_mensaje' => $ok ? null : 'Envío fallido (ver logs/correo_errores.log)',
            'metadata'      => [
                'origen' => 'dashboard/sistemas/correo_prueba.php',
                'enviado_por' => $_SESSION['nombre_completo'] ?? null,
            ],
        ]);

        if ($ok) {
            $feedback = [
                'tipo' => 'success',
                'texto' => 'Correo enviado correctamente. Revisa la bandeja de MailHog. Registro guardado en <code>correos_enviados</code>.',
            ];
        } else {
            $feedback = [
                'tipo' => 'danger',
                'texto' => 'No se pudo enviar el correo. Revisa <code>logs/correo_errores.log</code> para el detalle.',
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de correo &middot; <?php echo htmlspecialchars(NOMBRE_SISTEMA); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-envelope-check me-2"></i>Prueba de envío de correo (SMTP)</h5>
                    <a href="http://localhost:8025" target="_blank" class="btn btn-sm btn-light">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir MailHog
                    </a>
                </div>
                <div class="card-body">

                    <?php if ($feedback): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($feedback['tipo']); ?> alert-dismissible fade show" role="alert">
                            <?php echo $feedback['texto']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-secondary small">
                        <strong>Configuración SMTP actual:</strong><br>
                        Host: <code><?php echo htmlspecialchars(SMTP_HOST); ?></code> &middot;
                        Puerto: <code><?php echo (int) SMTP_PORT; ?></code> &middot;
                        Encriptación: <code><?php echo htmlspecialchars(SMTP_ENCRYPTION); ?></code><br>
                        Remitente: <code><?php echo htmlspecialchars(SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>'); ?></code>
                    </div>

                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="destinatario" class="form-label">Destinatario</label>
                            <input type="email" class="form-control" id="destinatario" name="destinatario"
                                   value="<?php echo htmlspecialchars($destinatario_prev !== '' ? $destinatario_prev : 'prueba@verden.local'); ?>"
                                   required>
                            <div class="form-text">En Docker + MailHog cualquier dirección funciona; se captura toda.</div>
                        </div>

                        <div class="mb-3">
                            <label for="asunto" class="form-label">Asunto</label>
                            <input type="text" class="form-control" id="asunto" name="asunto"
                                   value="<?php echo htmlspecialchars($asunto_prev !== '' ? $asunto_prev : 'Prueba de correo desde VerdenCore'); ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="mensaje" class="form-label">Mensaje</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="6" required><?php
                                echo htmlspecialchars($mensaje_prev !== '' ? $mensaje_prev : "Este es un correo de prueba enviado desde el módulo de Sistemas.\n\nSi lo ves en MailHog, la configuración SMTP está funcionando correctamente.");
                            ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo URL_BASE; ?>index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>Enviar prueba
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>