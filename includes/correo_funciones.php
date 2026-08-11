<?php
/**
 * Funciones centralizadas de envío de correo
 * Portal de Solicitudes de Atención - VerdenCore
 *
 * Wrapper alrededor de PHPMailer. La regla de oro:
 *   - NUNCA rompe el flujo del proceso principal.
 *   - Retorna bool (true = éxito, false = fallo).
 *   - Todos los errores se registran en logs/correo_errores.log.
 *
 * Uso mínimo:
 *   enviar_correo('destino@ejemplo.com', 'Asunto', '<p>Hola</p>');
 *
 * Uso con múltiples destinatarios y opciones:
 *   enviar_correo(
 *       [
 *           ['email' => 'a@x.com', 'nombre' => 'Ana'],
 *           ['email' => 'b@x.com', 'nombre' => 'Beto'],
 *       ],
 *       'Asunto',
 *       '<p>Hola</p>',
 *       'Hola (texto plano)',
 *       [
 *           'cc'       => ['jefe@x.com'],
 *           'bcc'      => ['auditoria@x.com'],
 *           'reply_to' => ['email' => 'contacto@x.com', 'nombre' => 'Contacto'],
 *           'adjuntos' => [
 *               '/ruta/al/archivo.pdf',
 *               ['path' => '/otra/ruta.pdf', 'nombre' => 'reporte.pdf'],
 *           ],
 *       ]
 *   );
 */

// Requiere: config/correo.php (SMTP_*) y config/config.php (DIR_ROOT) ya cargados
require_once DIR_ROOT . 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Envía un correo electrónico usando la configuración SMTP global.
 *
 * @param string|array $destinatarios Email único, arreglo de emails,
 *                                    o arreglo de ['email' => ..., 'nombre' => ...]
 * @param string       $asunto        Asunto del correo
 * @param string       $cuerpo_html   Cuerpo en HTML
 * @param string       $cuerpo_texto  Cuerpo en texto plano (opcional).
 *                                    Si se omite, se genera desde el HTML.
 * @param array        $opciones      cc, bcc, reply_to, adjuntos (todos opcionales)
 * @return bool                       true si se envió; false si hubo error (se loguea).
 */
function enviar_correo($destinatarios, string $asunto, string $cuerpo_html, string $cuerpo_texto = '', array $opciones = []): bool
{
    // Normalizar destinatarios a arreglo de ['email' => ..., 'nombre' => ...]
    $lista_destinatarios = _correo_normalizar_destinatarios($destinatarios);

    if (empty($lista_destinatarios)) {
        _correo_log_error('Sin destinatarios válidos', [], $asunto);
        return false;
    }

    if (trim($asunto) === '') {
        _correo_log_error('Asunto vacío', $lista_destinatarios, $asunto);
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        // === Configuración general ===
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // === Configuración SMTP ===
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = (int) SMTP_PORT;

        // Auth solo si hay usuario definido
        if (SMTP_USER !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
        } else {
            $mail->SMTPAuth = false;
        }

        // Encriptación
        switch (SMTP_ENCRYPTION) {
            case 'tls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'ssl':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'none':
            default:
                // MailHog en Docker: sin cifrado. También desactivamos AutoTLS
                // porque PHPMailer intenta STARTTLS por su cuenta si el servidor
                // lo anuncia, y eso rompería la conexión con MailHog.
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
                break;
        }

        // Debug (útil en desarrollo)
        if (SMTP_DEBUG > 0) {
            $mail->SMTPDebug = SMTP_DEBUG;
            $mail->Debugoutput = function ($str, $level) {
                error_log('[SMTP][' . $level . '] ' . trim($str));
            };
        }

        // === Remitente ===
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Reply-To opcional
        if (!empty($opciones['reply_to']) && is_array($opciones['reply_to']) && !empty($opciones['reply_to']['email'])) {
            $mail->addReplyTo(
                $opciones['reply_to']['email'],
                $opciones['reply_to']['nombre'] ?? ''
            );
        }

        // === Destinatarios ===
        foreach ($lista_destinatarios as $dest) {
            $mail->addAddress($dest['email'], $dest['nombre']);
        }

        // CC opcional
        if (!empty($opciones['cc']) && is_array($opciones['cc'])) {
            foreach (_correo_normalizar_destinatarios($opciones['cc']) as $cc) {
                $mail->addCC($cc['email'], $cc['nombre']);
            }
        }

        // BCC opcional
        if (!empty($opciones['bcc']) && is_array($opciones['bcc'])) {
            foreach (_correo_normalizar_destinatarios($opciones['bcc']) as $bcc) {
                $mail->addBCC($bcc['email'], $bcc['nombre']);
            }
        }

        // Adjuntos opcionales
        if (!empty($opciones['adjuntos']) && is_array($opciones['adjuntos'])) {
            foreach ($opciones['adjuntos'] as $adj) {
                if (is_string($adj) && file_exists($adj)) {
                    $mail->addAttachment($adj);
                } elseif (is_array($adj) && !empty($adj['path']) && file_exists($adj['path'])) {
                    $mail->addAttachment($adj['path'], $adj['nombre'] ?? '');
                }
            }
        }

        // === Contenido ===
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->AltBody = $cuerpo_texto !== '' ? $cuerpo_texto : strip_tags($cuerpo_html);

        $mail->send();
        return true;

    } catch (PHPMailerException $e) {
        _correo_log_error($e->getMessage(), $lista_destinatarios, $asunto);
        return false;
    } catch (\Throwable $e) {
        _correo_log_error('Excepción inesperada: ' . $e->getMessage(), $lista_destinatarios, $asunto);
        return false;
    }
}

/**
 * Normaliza una entrada de destinatarios a un arreglo homogéneo
 * de ['email' => ..., 'nombre' => ...].
 *
 * Acepta:
 *   - 'a@x.com'
 *   - ['a@x.com', 'b@x.com']
 *   - [['email' => 'a@x.com', 'nombre' => 'Ana']]
 *
 * Filtra emails inválidos silenciosamente.
 */
function _correo_normalizar_destinatarios($entrada): array
{
    $lista = [];

    if (is_string($entrada)) {
        $entrada = [$entrada];
    }

    if (!is_array($entrada)) {
        return $lista;
    }

    foreach ($entrada as $item) {
        if (is_string($item)) {
            $email = trim($item);
            $nombre = '';
        } elseif (is_array($item) && !empty($item['email'])) {
            $email = trim($item['email']);
            $nombre = trim($item['nombre'] ?? '');
        } else {
            continue;
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $lista[] = ['email' => $email, 'nombre' => $nombre];
        }
    }

    return $lista;
}

/**
 * Registra un error de envío en logs/correo_errores.log.
 * Nunca lanza excepción (usa @ para no romper el flujo principal
 * si el disco está lleno o la carpeta no es escribible).
 */
function _correo_log_error(string $mensaje, array $destinatarios, string $asunto): void
{
    $log_dir = DIR_ROOT . 'logs';

    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/correo_errores.log';

    $emails = [];
    foreach ($destinatarios as $d) {
        if (isset($d['email'])) {
            $emails[] = $d['email'];
        }
    }

    $linea = sprintf(
        "[%s] destinatarios=%s | asunto=%s | error=%s%s",
        date('Y-m-d H:i:s'),
        implode(',', $emails) ?: '(ninguno)',
        $asunto,
        $mensaje,
        PHP_EOL
    );

    @file_put_contents($log_file, $linea, FILE_APPEND | LOCK_EX);
}

// ====================================
// TRAZABILIDAD EN BASE DE DATOS
// ====================================

/**
 * Registra el resultado de un envío en la tabla `correos_enviados`.
 * Se llama después de invocar enviar_correo() para dejar constancia.
 *
 * Esta función NUNCA lanza excepción hacia arriba: si falla el INSERT
 * (por ejemplo, BD caída), se registra en log y se retorna false, pero
 * no rompe el flujo principal.
 *
 * @param array $datos Arreglo con las llaves:
 *   - usuario_id      (int|null)  ID del usuario destinatario. NULL si es genérico.
 *   - destinatario    (string)    Email destino real
 *   - tipo            (string)    Identificador del tipo de correo (ej: 'recordatorio_inactividad')
 *   - asunto          (string)    Asunto enviado
 *   - exito           (bool)      true = envío OK, false = falló
 *   - error_mensaje   (string)    Solo si !exito. Opcional.
 *   - metadata        (array|null) Datos JSON opcionales
 * @return int|false ID insertado, o false si el INSERT falla
 */
function registrar_correo_enviado(array $datos)
{
    // Validar campos obligatorios
    $requeridos = ['destinatario', 'tipo', 'asunto'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) {
            _correo_log_error(
                "registrar_correo_enviado: falta campo requerido '$campo'",
                [['email' => $datos['destinatario'] ?? '?']],
                $datos['asunto'] ?? '?'
            );
            return false;
        }
    }

    try {
        // Requiere config/database.php cargado (ya lo está en el flujo normal)
        if (!function_exists('conectarDB')) {
            require_once DIR_CONFIG . 'database.php';
        }
        $pdo = conectarDB();

        $sql = "INSERT INTO correos_enviados 
                    (usuario_id, destinatario, tipo, asunto, exito, error_mensaje, metadata, fecha_envio)
                VALUES 
                    (:usuario_id, :destinatario, :tipo, :asunto, :exito, :error_mensaje, :metadata, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $datos['usuario_id'] ?? null, isset($datos['usuario_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':destinatario', $datos['destinatario'], PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $datos['tipo'], PDO::PARAM_STR);
        $stmt->bindValue(':asunto', mb_substr($datos['asunto'], 0, 255), PDO::PARAM_STR);
        $stmt->bindValue(':exito', !empty($datos['exito']) ? 1 : 0, PDO::PARAM_INT);

        $error_msg = $datos['error_mensaje'] ?? null;
        $stmt->bindValue(':error_mensaje', $error_msg, $error_msg === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $metadata_json = null;
        if (!empty($datos['metadata']) && is_array($datos['metadata'])) {
            $metadata_json = json_encode($datos['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $stmt->bindValue(':metadata', $metadata_json, $metadata_json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();
        return (int) $pdo->lastInsertId();

    } catch (\Throwable $e) {
        _correo_log_error(
            'registrar_correo_enviado INSERT falló: ' . $e->getMessage(),
            [['email' => $datos['destinatario']]],
            $datos['asunto']
        );
        return false;
    }
}

/**
 * ¿A este usuario ya se le envió un correo del tipo indicado en los últimos $dias días?
 * Se usa para implementar el cooldown / anti-spam antes de intentar un envío.
 *
 * @param int    $usuario_id ID del usuario
 * @param string $tipo       Tipo de correo (ej: 'recordatorio_inactividad')
 * @param int    $dias       Ventana en días hacia atrás (default 7)
 * @param bool   $solo_exitosos Si true, solo cuenta los envíos exitosos. Default true.
 * @return bool  true si ya se le envió (no reenviar); false si es seguro enviarle
 */
function usuario_recibio_correo_reciente(int $usuario_id, string $tipo, int $dias = 7, bool $solo_exitosos = true): bool
{
    if ($usuario_id <= 0 || $tipo === '' || $dias <= 0) {
        return false;
    }

    try {
        if (!function_exists('conectarDB')) {
            require_once DIR_CONFIG . 'database.php';
        }
        $pdo = conectarDB();

        $sql = "SELECT 1 FROM correos_enviados 
                WHERE usuario_id = :usuario_id 
                  AND tipo = :tipo 
                  AND fecha_envio >= DATE_SUB(NOW(), INTERVAL :dias DAY)";
        if ($solo_exitosos) {
            $sql .= " AND exito = 1";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;

    } catch (\Throwable $e) {
        _correo_log_error(
            'usuario_recibio_correo_reciente falló: ' . $e->getMessage(),
            [],
            "cooldown_check tipo=$tipo usuario=$usuario_id"
        );
        // En caso de error de BD, ser conservador: asumir que SÍ recibió (para no spamear)
        return true;
    }
}