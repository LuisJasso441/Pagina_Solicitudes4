<?php
/**
 * Configuración SMTP del sistema
 * Portal de Solicitudes de Atención - VerdenCore
 *
 * Lee variables de entorno (docker-compose.yml en desarrollo,
 * configuración del servidor en producción). Nunca hardcodear
 * credenciales en este archivo.
 *
 * Valores por defecto pensados para MailHog en Docker.
 */

// Host SMTP
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mailhog');
}

// Puerto SMTP
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 1025));
}

// Usuario SMTP (vacío = sin autenticación, típico en MailHog)
if (!defined('SMTP_USER')) {
    $_smtp_user = getenv('SMTP_USER');
    define('SMTP_USER', $_smtp_user !== false ? $_smtp_user : '');
    unset($_smtp_user);
}

// Contraseña SMTP
if (!defined('SMTP_PASS')) {
    $_smtp_pass = getenv('SMTP_PASS');
    define('SMTP_PASS', $_smtp_pass !== false ? $_smtp_pass : '');
    unset($_smtp_pass);
}

// Encriptación: 'tls' (STARTTLS, puerto 587), 'ssl' (SMTPS, puerto 465) o 'none' (sin cifrado)
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', strtolower(getenv('SMTP_ENCRYPTION') ?: 'none'));
}

// Remitente por defecto
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@verden.local');
}

if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Portal Verden');
}

// Nivel de debug de PHPMailer
//   0 = silencioso (recomendado en producción)
//   1 = mensajes del cliente
//   2 = mensajes del cliente y servidor (útil para diagnosticar SMTP en desarrollo)
if (!defined('SMTP_DEBUG')) {
    define('SMTP_DEBUG', (int) (getenv('SMTP_DEBUG') ?: 0));
}

// ====================================
// URL ABSOLUTA DEL PORTAL
// ====================================
// Se usa en enlaces dentro de correos (botones CTA "Ver ...").
// OBLIGATORIA: URL_BASE en produccion es relativa ("/") y romperia los links.
// Idempotente: si config/config.php ya la definio, este define() no hace nada.
if (!defined('URL_BASE_ABSOLUTA')) {
    define('URL_BASE_ABSOLUTA', getenv('URL_BASE_ABSOLUTA') ?: 'http://localhost/Pagina_Solicitudes4/');
}

// ====================================
// CORREOS DE CONTACTO (footer de plantillas)
// ====================================
// Se leen desde variables de entorno. Si algún email queda vacío,
// ese contacto no aparece en el footer del correo.

if (!defined('CORREO_CONTACTO_1_EMAIL')) {
    define('CORREO_CONTACTO_1_EMAIL', getenv('SMTP_CONTACTO_1_EMAIL') ?: '');
}
if (!defined('CORREO_CONTACTO_1_LABEL')) {
    define('CORREO_CONTACTO_1_LABEL', getenv('SMTP_CONTACTO_1_LABEL') ?: 'Soporte TI');
}

if (!defined('CORREO_CONTACTO_2_EMAIL')) {
    define('CORREO_CONTACTO_2_EMAIL', getenv('SMTP_CONTACTO_2_EMAIL') ?: '');
}
if (!defined('CORREO_CONTACTO_2_LABEL')) {
    define('CORREO_CONTACTO_2_LABEL', getenv('SMTP_CONTACTO_2_LABEL') ?: 'Sistemas');
}

if (!defined('CORREO_CONTACTO_3_EMAIL')) {
    define('CORREO_CONTACTO_3_EMAIL', getenv('SMTP_CONTACTO_3_EMAIL') ?: '');
}
if (!defined('CORREO_CONTACTO_3_LABEL')) {
    define('CORREO_CONTACTO_3_LABEL', getenv('SMTP_CONTACTO_3_LABEL') ?: 'Contacto general');
}

// ====================================
// LOGO PARA CORREOS
// ====================================
if (!defined('CORREO_LOGO_URL')) {
    define('CORREO_LOGO_URL', URL_BASE . 'assets/img/logo_correo.png');
}

// ====================================
// RECORDATORIOS DE INACTIVIDAD
// ====================================

// Días de inactividad para considerar a un usuario "candidato a recordatorio".
// Ejemplo: 3 = si no entra desde hace 3+ días, es candidato.
if (!defined('RECORDATORIO_DIAS_INACTIVIDAD')) {
    define('RECORDATORIO_DIAS_INACTIVIDAD', (int) (getenv('RECORDATORIO_DIAS_INACTIVIDAD') ?: 3));
}

// Ventana de cooldown (en días): tiempo mínimo entre dos recordatorios al mismo usuario.
// Para "Escenario A" (semanal los lunes) => 7 días.
if (!defined('RECORDATORIO_COOLDOWN_DIAS')) {
    define('RECORDATORIO_COOLDOWN_DIAS', (int) (getenv('RECORDATORIO_COOLDOWN_DIAS') ?: 7));
}

// Tipo canónico usado en la tabla `correos_enviados` para este tipo de correo.
// No poner en variable de entorno, es un identificador de código.
if (!defined('RECORDATORIO_TIPO_CORREO')) {
    define('RECORDATORIO_TIPO_CORREO', 'recordatorio_inactividad');
}

// ====================================
// COTIZACIONES QR (CQR) - Correos automáticos
// ====================================
// Destinatarios fijos por rol para los eventos del módulo CQR.
// Fuente de verdad: variables de entorno (docker-compose en dev, .env en producción).
// Si un email queda vacío, el envío se omite silenciosamente (fail-safe).

if (!defined('CQR_CORREO_NORMATIVIDAD')) {
    define('CQR_CORREO_NORMATIVIDAD', getenv('CQR_CORREO_NORMATIVIDAD') ?: '');
}
if (!defined('CQR_CORREO_NORMATIVIDAD_NOMBRE')) {
    define('CQR_CORREO_NORMATIVIDAD_NOMBRE', getenv('CQR_CORREO_NORMATIVIDAD_NOMBRE') ?: '');
}
if (!defined('CQR_CORREO_VENTAS')) {
    define('CQR_CORREO_VENTAS', getenv('CQR_CORREO_VENTAS') ?: '');
}
if (!defined('CQR_CORREO_VENTAS_NOMBRE')) {
    define('CQR_CORREO_VENTAS_NOMBRE', getenv('CQR_CORREO_VENTAS_NOMBRE') ?: '');
}

// Cooldown anti-spam: minutos mínimos entre correos del mismo tipo al mismo destinatario.
// Mismo patrón que RECORDATORIO_COOLDOWN_DIAS pero en minutos (CQR trabaja en escala de segundos).
if (!defined('CQR_COOLDOWN_MINUTOS')) {
    define('CQR_COOLDOWN_MINUTOS', (int) (getenv('CQR_COOLDOWN_MINUTOS') ?: 5));
}

// Tipos canónicos usados en la columna `correos_enviados.tipo` para este módulo.
// No van por variable de entorno, son identificadores de código.
if (!defined('CQR_TIPO_CORREO_NUEVA')) {
    define('CQR_TIPO_CORREO_NUEVA', 'cqr_nueva_cotizacion');
}
if (!defined('CQR_TIPO_CORREO_RESPUESTA')) {
    define('CQR_TIPO_CORREO_RESPUESTA', 'cqr_respuesta_recibida');
}