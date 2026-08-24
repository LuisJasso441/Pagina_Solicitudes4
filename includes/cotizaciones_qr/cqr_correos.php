<?php
/**
 * Correos automáticos del módulo Cotizaciones QR (CQR)
 * Portal de Solicitudes de Atención - VerdenCore
 *
 * Ubicación: includes/cotizaciones_qr/cqr_correos.php
 * Encoding:  UTF-8 sin BOM
 *
 * Este archivo es el "pegamento" entre los eventos del CQR y la infraestructura
 * compartida de correos (enviar_correo, renderizar_plantilla_correo,
 * registrar_correo_enviado). No implementa transporte SMTP ni plantilla base;
 * solo conecta eventos con envíos.
 *
 * Reglas de oro (todas garantizadas por diseño):
 *   1. Ninguna función pública lanza excepción hacia arriba.
 *      El flujo principal (guardar cotización / responder) nunca se rompe
 *      por un fallo de correo.
 *   2. Si no hay destinatario configurado en .env, el envío se omite
 *      silenciosamente. Se loguea en correo_errores.log para diagnóstico.
 *   3. Cooldown anti-spam: 1 correo por (destinatario + tipo) cada
 *      CQR_COOLDOWN_MINUTOS minutos. Segundos intentos se saltan
 *      silenciosamente sin log de error (no es un error, es diseño).
 *   4. Todo intento efectivo (no saltado por cooldown/destinatario vacío)
 *      queda registrado en la tabla correos_enviados con exito=0/1.
 *
 * API pública (invocar solo estas dos desde el módulo CQR):
 *   - cqr_enviar_correo_nueva_cotizacion($cotizacion)
 *   - cqr_enviar_correo_respuesta_normatividad($cotizacion, $decision)
 *
 * Ambas retornan bool: true si el correo salió, false si se omitió o falló.
 */

// Dependencias explícitas. require_once = idempotente si el consumidor ya las cargó.
require_once DIR_CONFIG . 'correo.php';
require_once DIR_ROOT . 'includes/correo_funciones.php';
require_once DIR_ROOT . 'includes/correo_plantilla.php';


// =====================================================
// API PÚBLICA - EVENTO 1: Ventas crea cotización
// =====================================================

/**
 * Envía correo a Normatividad cuando Ventas crea una nueva cotización.
 *
 * Se debe invocar DESPUÉS del $pdo->commit() en crear_cotizacion_qr(),
 * nunca dentro de la transacción.
 *
 * @param array $cotizacion Fila de la cotización recién creada. Claves usadas:
 *   - id                    (int)    obligatorio - para construir URL de detalle
 *   - folio                 (string) obligatorio - para asunto y cuerpo
 *   - departamento_creador  (string) opcional    - default 'Ventas'
 *   - nombre_cliente        (string) opcional    - se muestra en el cuerpo
 *   - tipo_cliente          (string) opcional    - 'nuevo'|'frecuente', se muestra en el cuerpo
 * @return bool true = enviado, false = omitido o falló (siempre silencioso)
 */
function cqr_enviar_correo_nueva_cotizacion(array $cotizacion): bool
{
    // Guardarrail: id y folio son imprescindibles
    if (empty($cotizacion['id']) || empty($cotizacion['folio'])) {
        _cqr_log_diagnostico('cqr_enviar_correo_nueva_cotizacion: falta id o folio en cotizacion');
        return false;
    }

    $destinatario_email  = trim(CQR_CORREO_NORMATIVIDAD);
    $destinatario_nombre = trim(CQR_CORREO_NORMATIVIDAD_NOMBRE);

    // Si no hay destinatario configurado, omitir silenciosamente
    if ($destinatario_email === '') {
        _cqr_log_diagnostico('CQR_CORREO_NORMATIVIDAD vacio en .env; envio omitido para folio=' . $cotizacion['folio']);
        return false;
    }

    // Cooldown anti-spam
    if (_cqr_destinatario_recibio_correo_reciente($destinatario_email, CQR_TIPO_CORREO_NUEVA, (int) CQR_COOLDOWN_MINUTOS)) {
        // No es un error, es diseño: no logueamos
        return false;
    }

    // === Preparar contenido ===
    $folio            = (string) $cotizacion['folio'];
    $depto_solicitante = trim($cotizacion['departamento_creador'] ?? '') !== ''
        ? (string) $cotizacion['departamento_creador']
        : 'Ventas';
    $nombre_cliente   = trim($cotizacion['nombre_cliente'] ?? '');
    $tipo_cliente_raw = trim($cotizacion['tipo_cliente'] ?? '');
    $tipo_cliente_txt = $tipo_cliente_raw === 'frecuente'
        ? 'Cliente frecuente'
        : ($tipo_cliente_raw === 'nuevo' ? 'Cliente nuevo' : '');

    $asunto = "Nueva cotización recibida - {$folio}";

    // Escape de todo lo que viene de BD antes de meterlo en HTML
    $folio_esc          = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');
    $depto_esc          = htmlspecialchars($depto_solicitante, ENT_QUOTES, 'UTF-8');
    $nombre_cliente_esc = htmlspecialchars($nombre_cliente, ENT_QUOTES, 'UTF-8');
    $tipo_cliente_esc   = htmlspecialchars($tipo_cliente_txt, ENT_QUOTES, 'UTF-8');

    $filas_datos = [
        ['Folio', $folio_esc],
        ['Departamento solicitante', $depto_esc],
    ];
    if ($nombre_cliente_esc !== '') {
        $filas_datos[] = ['Cliente', $nombre_cliente_esc];
    }
    if ($tipo_cliente_esc !== '') {
        $filas_datos[] = ['Tipo de cliente', $tipo_cliente_esc];
    }

    $contenido_html = '<p style="margin:0 0 16px 0;">'
        . 'Se ha registrado una nueva cotización que requiere revisión de Normatividad.'
        . '</p>'
        . _cqr_tabla_datos($filas_datos);

    $url_detalle = _cqr_construir_url_detalle((int) $cotizacion['id']);

    $html = renderizar_plantilla_correo(
        'Nueva cotización recibida',
        $contenido_html,
        [
            'preheader'                => "Cotización {$folio} pendiente de revisión",
            'subtitulo'                => 'Módulo Cotizaciones Químicos / Residuos',
            'boton_cta'                => ['texto' => 'Ver cotización', 'url' => $url_detalle],
            'mostrar_footer_contactos' => true,
        ]
    );

    // === Enviar y registrar ===
    return _cqr_enviar_y_registrar(
        $destinatario_email,
        $destinatario_nombre,
        $asunto,
        $html,
        CQR_TIPO_CORREO_NUEVA,
        [
            'cotizacion_id' => (int) $cotizacion['id'],
            'folio'         => $folio,
            'evento'        => 'nueva_cotizacion',
        ]
    );
}


// =====================================================
// API PÚBLICA - EVENTO 2: Normatividad responde
// =====================================================

/**
 * Envía correo a Ventas cuando Normatividad emite su decisión (aceptada/rechazada).
 *
 * Se debe invocar DESPUÉS del $pdo->commit() en responder_cotizacion_qr(),
 * nunca dentro de la transacción.
 *
 * @param array  $cotizacion Fila de la cotización actualizada. Claves usadas:
 *   - id     (int)    obligatorio
 *   - folio  (string) obligatorio
 * @param string $decision 'aceptada' | 'rechazada' (única validez esperada
 *                         por el flujo de responder_cotizacion_qr)
 * @return bool true = enviado, false = omitido o falló (siempre silencioso)
 */
function cqr_enviar_correo_respuesta_normatividad(array $cotizacion, string $decision): bool
{
    // Guardarrail: id, folio y decisión válida
    if (empty($cotizacion['id']) || empty($cotizacion['folio'])) {
        _cqr_log_diagnostico('cqr_enviar_correo_respuesta_normatividad: falta id o folio');
        return false;
    }
    if (!in_array($decision, ['aceptada', 'rechazada'], true)) {
        _cqr_log_diagnostico('cqr_enviar_correo_respuesta_normatividad: decision invalida="' . $decision . '"');
        return false;
    }

    $destinatario_email  = trim(CQR_CORREO_VENTAS);
    $destinatario_nombre = trim(CQR_CORREO_VENTAS_NOMBRE);

    if ($destinatario_email === '') {
        _cqr_log_diagnostico('CQR_CORREO_VENTAS vacio en .env; envio omitido para folio=' . $cotizacion['folio']);
        return false;
    }

    if (_cqr_destinatario_recibio_correo_reciente($destinatario_email, CQR_TIPO_CORREO_RESPUESTA, (int) CQR_COOLDOWN_MINUTOS)) {
        return false;
    }

    // === Preparar contenido ===
    $folio          = (string) $cotizacion['folio'];
    $decision_texto = $decision === 'aceptada' ? 'Aceptada' : 'Rechazada';

    $folio_esc          = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');
    $decision_texto_esc = htmlspecialchars($decision_texto, ENT_QUOTES, 'UTF-8');

    $asunto = "Respuesta a tu cotización: {$decision_texto} - {$folio}";

    $mensaje_intro = $decision === 'aceptada'
        ? 'Normatividad ha revisado tu cotización y la respuesta es <strong>Aceptada</strong>.'
        : 'Normatividad ha revisado tu cotización y la respuesta es <strong>Rechazada</strong>.';

    $contenido_html = '<p style="margin:0 0 16px 0;">' . $mensaje_intro . '</p>'
        . _cqr_tabla_datos([
            ['Folio',      $folio_esc],
            ['Nuevo estado', $decision_texto_esc],
        ])
        . '<p style="margin:16px 0 0 0; color:#666666; font-size:14px;">'
        . 'Consulta el detalle completo (incluyendo comentarios y resultados) desde el botón de abajo.'
        . '</p>';

    $url_detalle = _cqr_construir_url_detalle((int) $cotizacion['id']);

    $html = renderizar_plantilla_correo(
        'Respuesta a tu cotización: ' . $decision_texto,
        $contenido_html,
        [
            'preheader'                => "Cotización {$folio} marcada como {$decision_texto}",
            'subtitulo'                => 'Módulo Cotizaciones Químicos / Residuos',
            'boton_cta'                => ['texto' => 'Ver cotización', 'url' => $url_detalle],
            'mostrar_footer_contactos' => true,
        ]
    );

    return _cqr_enviar_y_registrar(
        $destinatario_email,
        $destinatario_nombre,
        $asunto,
        $html,
        CQR_TIPO_CORREO_RESPUESTA,
        [
            'cotizacion_id' => (int) $cotizacion['id'],
            'folio'         => $folio,
            'evento'        => 'respuesta_normatividad',
            'decision'      => $decision,
        ]
    );
}


// =====================================================
// HELPERS PRIVADOS (no llamar desde fuera)
// =====================================================

/**
 * Envuelve enviar_correo() + registrar_correo_enviado() en una sola operación.
 * Cualquier excepción se atrapa y se convierte en false + log. Nunca escala.
 *
 * @return bool true si el envío fue exitoso
 */
function _cqr_enviar_y_registrar(
    string $destinatario_email,
    string $destinatario_nombre,
    string $asunto,
    string $html,
    string $tipo,
    array  $metadata
): bool {
    try {
        $ok = enviar_correo(
            [['email' => $destinatario_email, 'nombre' => $destinatario_nombre]],
            $asunto,
            $html
        );
    } catch (\Throwable $e) {
        // enviar_correo() ya atrapa sus propias excepciones y retorna bool,
        // pero blindamos por si algo en la preparación de argumentos falla.
        _cqr_log_diagnostico('_cqr_enviar_y_registrar excepcion enviar_correo: ' . $e->getMessage());
        $ok = false;
    }

    // Registrar el intento (exitoso o fallido) para trazabilidad
    try {
        registrar_correo_enviado([
            'usuario_id'    => _cqr_resolver_usuario_id_por_email($destinatario_email),
            'destinatario'  => $destinatario_email,
            'tipo'          => $tipo,
            'asunto'        => $asunto,
            'exito'         => $ok,
            'error_mensaje' => $ok ? null : 'Envio fallido; ver logs/correo_errores.log',
            'metadata'      => $metadata,
        ]);
    } catch (\Throwable $e) {
        // registrar_correo_enviado ya loguea internamente; blindaje adicional
        _cqr_log_diagnostico('_cqr_enviar_y_registrar excepcion registrar: ' . $e->getMessage());
    }

    return $ok;
}


/**
 * ¿Este email recibió un correo de este tipo en los últimos $minutos minutos?
 * Hermano local de usuario_recibio_correo_reciente(), pero:
 *   - Filtra por destinatario (email) en lugar de usuario_id.
 *     Los destinatarios de CQR vienen del .env y no son necesariamente
 *     un usuario con id en la BD.
 *   - Ventana en MINUTOS (INTERVAL MINUTE), no días. CQR opera en escala
 *     de segundos/minutos, no semanal.
 *
 * Ante error de BD: retorna true (conservador, evita spam si algo raro pasa).
 *
 * @return bool true si ya recibió (NO reenviar); false si es seguro enviar
 */
function _cqr_destinatario_recibio_correo_reciente(string $email, string $tipo, int $minutos): bool
{
    $email = trim($email);
    if ($email === '' || $tipo === '' || $minutos <= 0) {
        return false;
    }

    try {
        if (!function_exists('conectarDB')) {
            require_once DIR_CONFIG . 'database.php';
        }
        $pdo = conectarDB();

        // NOTA: $minutos se interpola casteado a int en el SQL (no como placeholder)
        // porque DATE_SUB(NOW(), INTERVAL :placeholder MINUTE) es sensible al modo
        // ATTR_EMULATE_PREPARES: cuando esta activo (default en algunas versiones de
        // PDO/MariaDB), el placeholder se envuelve entre comillas y el INTERVAL puede
        // evaluar a NULL, causando comparaciones erraticas. Con casting a int seguro
        // (no es user input, es constante propia) evitamos el pitfall en ambos modos.
        $minutos_seguros = (int) $minutos;
        $sql = "SELECT 1 FROM correos_enviados
                WHERE destinatario = :destinatario
                  AND tipo = :tipo
                  AND exito = 1
                  AND fecha_envio >= DATE_SUB(NOW(), INTERVAL {$minutos_seguros} MINUTE)
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':destinatario', $email, PDO::PARAM_STR);
        $stmt->bindValue(':tipo',         $tipo,  PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;

    } catch (\Throwable $e) {
        _cqr_log_diagnostico('_cqr_destinatario_recibio_correo_reciente error BD: ' . $e->getMessage());
        // Conservador: ante error, asumir que SI recibio (para no arriesgarse a spam)
        return true;
    }
}


/**
 * Busca un usuario cuyo correo coincida y devuelve su id.
 * Uso: poblar `correos_enviados.usuario_id` para auditoría cruzada
 * cuando el destinatario del .env coincide con un usuario real.
 *
 * @return int|null id del usuario si hay match; null si no hay o si falla la consulta
 */
function _cqr_resolver_usuario_id_por_email(string $email): ?int
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    try {
        if (!function_exists('conectarDB')) {
            require_once DIR_CONFIG . 'database.php';
        }
        $pdo = conectarDB();

        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->bindValue(':correo', $email, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;

    } catch (\Throwable $e) {
        _cqr_log_diagnostico('_cqr_resolver_usuario_id_por_email error BD: ' . $e->getMessage());
        return null;
    }
}


/**
 * Construye la URL absoluta al detalle de la cotización, para usar en emails.
 * OBLIGATORIO usar URL_BASE_ABSOLUTA (en produccion URL_BASE es relativa y
 * generaria links rotos en correos).
 */
function _cqr_construir_url_detalle(int $cotizacion_id): string
{
    return URL_BASE_ABSOLUTA . 'dashboard/cotizaciones_qr/ver_cotizacion_qr.php?id=' . $cotizacion_id;
}


/**
 * Genera una tabla HTML simple etiqueta/valor, con estilo inline compatible
 * con Gmail/Outlook. Reutilizada por ambos eventos.
 *
 * @param array<int, array{0:string,1:string}> $filas Pares [etiqueta, valor_ya_escapado]
 */
function _cqr_tabla_datos(array $filas): string
{
    if (empty($filas)) {
        return '';
    }

    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" '
          . 'style="width:100%; border-collapse:collapse; margin:0 0 8px 0;">';

    foreach ($filas as $fila) {
        $etiqueta = htmlspecialchars($fila[0], ENT_QUOTES, 'UTF-8');
        $valor    = (string) $fila[1]; // asumido ya escapado por el llamador
        $html .= '<tr>'
              . '<td style="padding:8px 12px 8px 0; color:#666666; font-family:Arial,Helvetica,sans-serif; font-size:14px; vertical-align:top; width:180px; border-bottom:1px solid #f0f0f0;">'
              . $etiqueta
              . '</td>'
              . '<td style="padding:8px 0; color:#0D0D0D; font-family:Arial,Helvetica,sans-serif; font-size:14px; vertical-align:top; border-bottom:1px solid #f0f0f0;">'
              . $valor
              . '</td>'
              . '</tr>';
    }

    $html .= '</table>';
    return $html;
}


/**
 * Log local a logs/correo_errores.log con prefijo [CQR].
 * Reutiliza el archivo de log de la infraestructura para tener un único
 * lugar donde buscar problemas de correo.
 */
function _cqr_log_diagnostico(string $mensaje): void
{
    $log_dir = DIR_ROOT . 'logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $linea = sprintf("[%s] [CQR] %s%s", date('Y-m-d H:i:s'), $mensaje, PHP_EOL);
    @file_put_contents($log_dir . '/correo_errores.log', $linea, FILE_APPEND | LOCK_EX);
}