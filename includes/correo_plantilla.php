<?php
/**
 * Plantilla HTML corporativa para correos
 * Portal de Solicitudes de Atención - Grupo Verden
 *
 * Uso:
 *   $html = renderizar_plantilla_correo(
 *       'Tienes tareas pendientes',
 *       '<p>Contenido HTML del correo aquí.</p>',
 *       [
 *           'preheader'  => 'Resumen de tus notificaciones sin leer',
 *           'subtitulo'  => 'Recordatorio semanal',
 *           'boton_cta'  => ['texto' => 'Ir al Portal', 'url' => URL_BASE],
 *           'mostrar_footer_contactos' => true,
 *       ]
 *   );
 *   enviar_correo('destino@x.com', 'Tienes tareas pendientes', $html);
 *
 * Todo el estilo es inline (compatibilidad Gmail/Outlook).
 * El layout está basado en tablas (compatibilidad Outlook 2007+).
 */

// Requiere config/correo.php (constantes) y config/config.php (URL_BASE, NOMBRE_EMPRESA)

/**
 * Paleta corporativa Grupo Verden
 */
function _correo_paleta(): array
{
    return [
        'verde_primario'   => '#038C73',
        'verde_oscuro'     => '#02734A',
        'verde_muy_oscuro' => '#01401C',
        'verde_menta'      => '#84D9C9',
        'negro'            => '#0D0D0D',
        'gris_texto'       => '#666666',
        'gris_footer_bg'   => '#f5f5f5',
        'gris_divisor'     => '#e5e5e5',
        'blanco'           => '#ffffff',
    ];
}

/**
 * Genera el HTML completo de un correo con la identidad Grupo Verden.
 *
 * @param string $titulo         Título principal del correo (se muestra grande)
 * @param string $contenido_html Cuerpo HTML del correo (lo que pongas aquí va tal cual)
 * @param array  $opciones       Opciones:
 *   - preheader   (string) Texto de preview en bandeja (~90 chars, opcional)
 *   - subtitulo   (string) Línea gris debajo del título (opcional)
 *   - boton_cta   (array)  ['texto' => 'X', 'url' => 'Y']  (opcional)
 *   - mostrar_footer_contactos (bool) Default true
 * @return string HTML completo del correo
 */
function renderizar_plantilla_correo(string $titulo, string $contenido_html, array $opciones = []): string
{
    $c = _correo_paleta();

    // Opciones con defaults
    $preheader                 = trim($opciones['preheader'] ?? '');
    $subtitulo                 = trim($opciones['subtitulo'] ?? '');
    $boton_cta                 = $opciones['boton_cta'] ?? null;
    $mostrar_footer_contactos  = $opciones['mostrar_footer_contactos'] ?? true;

    // Escape del título (el contenido lo pasa quien llama, ya HTML)
    $titulo_esc    = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $subtitulo_esc = htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8');
    $preheader_esc = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
    $empresa_esc   = htmlspecialchars(NOMBRE_EMPRESA, ENT_QUOTES, 'UTF-8');
    $logo_url_esc  = htmlspecialchars(CORREO_LOGO_URL, ENT_QUOTES, 'UTF-8');
    $anio_actual   = date('Y');

    // === Bloques opcionales ===

    // Preheader oculto (aparece como preview en bandeja de entrada)
    $preheader_html = '';
    if ($preheader_esc !== '') {
        $preheader_html = '<div style="display:none; max-height:0; overflow:hidden; mso-hide:all; visibility:hidden; opacity:0; color:transparent; height:0; width:0; font-size:0; line-height:0;">'
            . $preheader_esc
            . '&#847;&zwnj;&nbsp;&#8199;&#65279;&#847;&zwnj;&nbsp;&#8199;&#65279;&#847;&zwnj;&nbsp;&#8199;&#65279;'
            . '</div>';
    }

    // Subtítulo
    $subtitulo_html = '';
    if ($subtitulo_esc !== '') {
        $subtitulo_html = '<p style="margin:0 0 20px 0; color:' . $c['gris_texto'] . '; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.5;">'
            . $subtitulo_esc
            . '</p>';
    }

    // Botón CTA (table-based para compatibilidad Outlook)
    $boton_html = '';
    if (is_array($boton_cta) && !empty($boton_cta['texto']) && !empty($boton_cta['url'])) {
        $btn_texto = htmlspecialchars($boton_cta['texto'], ENT_QUOTES, 'UTF-8');
        $btn_url   = htmlspecialchars($boton_cta['url'], ENT_QUOTES, 'UTF-8');
        $boton_html = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px auto 8px auto;">
                <tr>
                    <td align="center" style="background-color:' . $c['verde_primario'] . '; border-radius:6px;">
                        <a href="' . $btn_url . '"
                           style="display:inline-block; padding:12px 28px; color:' . $c['blanco'] . '; text-decoration:none;
                                  font-family:Arial,Helvetica,sans-serif; font-weight:bold; font-size:14px;
                                  line-height:1; mso-padding-alt:0;">
                            ' . $btn_texto . '
                        </a>
                    </td>
                </tr>
            </table>';
    }

    // Footer de contactos
    $footer_contactos_html = '';
    if ($mostrar_footer_contactos) {
        $contactos = [
            ['email' => CORREO_CONTACTO_1_EMAIL, 'label' => CORREO_CONTACTO_1_LABEL],
            ['email' => CORREO_CONTACTO_2_EMAIL, 'label' => CORREO_CONTACTO_2_LABEL],
            ['email' => CORREO_CONTACTO_3_EMAIL, 'label' => CORREO_CONTACTO_3_LABEL],
        ];
        $items = [];
        foreach ($contactos as $ct) {
            $email = trim($ct['email']);
            $label = trim($ct['label']);
            if ($email === '') {
                continue; // no mostrar contactos sin email configurado
            }
            $email_esc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            $label_esc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $items[] = '<a href="mailto:' . $email_esc . '" style="color:' . $c['verde_oscuro'] . '; text-decoration:none; font-weight:bold;">'
                     . $label_esc
                     . '</a>: <a href="mailto:' . $email_esc . '" style="color:' . $c['gris_texto'] . '; text-decoration:none;">'
                     . $email_esc . '</a>';
        }

        if (!empty($items)) {
            $footer_contactos_html = '
                <p style="margin:0 0 12px 0; color:' . $c['negro'] . '; font-family:Arial,Helvetica,sans-serif; font-size:12px; font-weight:bold;">
                    &iquest;Necesitas ayuda?
                </p>
                <p style="margin:0 0 16px 0; color:' . $c['gris_texto'] . '; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.8;">
                    ' . implode('<br>', $items) . '
                </p>';
        }
    }

    // === Ensamblado del HTML ===
    $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{$titulo_esc}</title>
</head>
<body style="margin:0; padding:0; background-color:{$c['gris_footer_bg']}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    {$preheader_html}

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$c['gris_footer_bg']};">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <!-- Contenedor principal 600px -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px; width:100%; background-color:{$c['blanco']}; border-radius:8px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.05);">

                    <!-- HEADER -->
                    <tr>
                        <td align="center" style="background-color:{$c['verde_menta']}; padding:28px 20px;">
                            <img src="{$logo_url_esc}" alt="{$empresa_esc}"
                                 style="height:56px; width:auto; max-width:80%; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr>

                    <!-- CONTENIDO -->
                    <tr>
                        <td style="padding:36px 36px 28px 36px; font-family:Arial,Helvetica,sans-serif; color:{$c['negro']}; font-size:15px; line-height:1.6;">
                            <h1 style="margin:0 0 8px 0; color:{$c['verde_muy_oscuro']}; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:bold; line-height:1.3;">
                                {$titulo_esc}
                            </h1>
                            {$subtitulo_html}
                            <div style="color:{$c['negro']}; font-size:15px; line-height:1.6;">
                                {$contenido_html}
                            </div>
                            {$boton_html}
                        </td>
                    </tr>

                    <!-- SEPARADOR -->
                    <tr>
                        <td style="padding:0 36px;">
                            <div style="height:1px; background-color:{$c['gris_divisor']}; line-height:1px; font-size:1px;">&nbsp;</div>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="padding:24px 36px 28px 36px; background-color:{$c['blanco']}; text-align:center;">
                            {$footer_contactos_html}
                            <p style="margin:0; color:{$c['gris_texto']}; font-family:Arial,Helvetica,sans-serif; font-size:11px; line-height:1.5;">
                                Este correo fue generado autom&aacute;ticamente por el Portal de Solicitudes.<br>
                                Por favor no respondas directamente a este mensaje.
                            </p>
                        </td>
                    </tr>

                </table>

                <!-- Copyright bajo el contenedor -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%;">
                    <tr>
                        <td align="center" style="padding:16px 12px 4px 12px;">
                            <p style="margin:0; color:{$c['gris_texto']}; font-family:Arial,Helvetica,sans-serif; font-size:11px; line-height:1.4;">
                                &copy; {$anio_actual} {$empresa_esc}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    return $html;
}