<?php
/**
 * Envío de recordatorios de inactividad a usuarios del portal.
 * Portal de Solicitudes de Atención - Grupo Verden
 *
 * QUÉ HACE
 * Detecta usuarios activos con correo configurado que llevan varios días
 * sin entrar (según RECORDATORIO_DIAS_INACTIVIDAD) y que además tienen
 * notificaciones no leídas. Les envía un correo con la plantilla corporativa
 * indicando cuántas tareas tienen pendientes por módulo.
 *
 * CÓMO EJECUTARLO
 *   docker compose exec web php /var/www/html/Pagina_Solicitudes4/cli/enviar_recordatorios_inactivos.php [flags]
 *
 * FLAGS
 *   --dry-run           No envía correos, no registra en BD. Solo muestra qué haría.
 *   --usuario=ID        Corre solo para el usuario con ese ID (útil para pruebas).
 *   --forzar-cooldown   Ignora el cooldown; envía aunque haya recibido en últimos días.
 *
 * EJEMPLOS
 *   Vista previa completa sin enviar nada:
 *     php cli/enviar_recordatorios_inactivos.php --dry-run
 *
 *   Prueba real de un solo usuario, ignorando cooldown:
 *     php cli/enviar_recordatorios_inactivos.php --usuario=9 --forzar-cooldown
 *
 *   Ejecución real (lunes 8am por cron):
 *     php cli/enviar_recordatorios_inactivos.php
 *
 * SALIDA
 *   - Escritura en pantalla con formato legible.
 *   - Log en logs/recordatorios_YYYY-MM-DD.log (uno por corrida).
 *   - Trazabilidad en tabla `correos_enviados`.
 */

// ============================================================
// BLOQUE 1 - Bootstrap y guardas
// ============================================================

// Solo permitir ejecución desde CLI. Nunca desde web.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.' . PHP_EOL);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/correo_funciones.php';
require_once __DIR__ . '/../includes/correo_plantilla.php';
require_once __DIR__ . '/../includes/notificaciones_modulos.php';

// ============================================================
// BLOQUE 2 - Parser de argumentos
// ============================================================

$opciones = [
    'dry_run'          => false,
    'usuario_id'       => null,
    'forzar_cooldown'  => false,
];

foreach ($argv as $i => $arg) {
    if ($i === 0) continue; // el nombre del script
    if ($arg === '--dry-run') {
        $opciones['dry_run'] = true;
    } elseif ($arg === '--forzar-cooldown') {
        $opciones['forzar_cooldown'] = true;
    } elseif (strpos($arg, '--usuario=') === 0) {
        $valor = substr($arg, strlen('--usuario='));
        if (!ctype_digit($valor) || (int) $valor <= 0) {
            fwrite(STDERR, "Error: --usuario debe ser un ID numérico positivo. Recibido: '$valor'" . PHP_EOL);
            exit(1);
        }
        $opciones['usuario_id'] = (int) $valor;
    } else {
        fwrite(STDERR, "Argumento no reconocido: '$arg'" . PHP_EOL);
        fwrite(STDERR, "Flags válidos: --dry-run, --usuario=ID, --forzar-cooldown" . PHP_EOL);
        exit(1);
    }
}

// ============================================================
// BLOQUE 3 - Preparar log y estadísticas
// ============================================================

$log_dir = DIR_ROOT . 'logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/recordatorios_' . date('Y-m-d') . '.log';

/**
 * Escribe en el log y también en pantalla.
 */
function loglinea(string $mensaje, string $log_file): void
{
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje;
    echo $linea . PHP_EOL;
    @file_put_contents($log_file, $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$stats = [
    'candidatos'          => 0,
    'sin_notificaciones'  => 0,
    'saltados_cooldown'   => 0,
    'enviados_ok'         => 0,
    'enviados_error'      => 0,
];

// Encabezado
loglinea('==================================================================', $log_file);
loglinea('INICIO - Envío de recordatorios de inactividad', $log_file);
loglinea('Modo:            ' . ($opciones['dry_run'] ? 'DRY-RUN (no envía)' : 'REAL (envía correos)'), $log_file);
loglinea('Umbral días:     ' . RECORDATORIO_DIAS_INACTIVIDAD, $log_file);
loglinea('Cooldown días:   ' . RECORDATORIO_COOLDOWN_DIAS . ($opciones['forzar_cooldown'] ? ' (IGNORADO por --forzar-cooldown)' : ''), $log_file);
if ($opciones['usuario_id'] !== null) {
    loglinea('Usuario único:   ID ' . $opciones['usuario_id'], $log_file);
}
loglinea('==================================================================', $log_file);

// ============================================================
// BLOQUE 4 - Query de candidatos
// ============================================================

try {
    $pdo = conectarDB();
} catch (\Throwable $e) {
    loglinea('ERROR FATAL: no se pudo conectar a la BD: ' . $e->getMessage(), $log_file);
    exit(1);
}

$sql_candidatos = "SELECT id, usuario, nombre_completo, correo, ultimo_acceso
                   FROM usuarios
                   WHERE activo = 1
                     AND correo IS NOT NULL
                     AND correo <> ''
                     AND ultimo_acceso IS NOT NULL
                     AND ultimo_acceso <= DATE_SUB(NOW(), INTERVAL :dias DAY)";
if ($opciones['usuario_id'] !== null) {
    $sql_candidatos .= " AND id = :usuario_id";
}
$sql_candidatos .= " ORDER BY ultimo_acceso ASC";

try {
    $stmt = $pdo->prepare($sql_candidatos);
    $stmt->bindValue(':dias', RECORDATORIO_DIAS_INACTIVIDAD, PDO::PARAM_INT);
    if ($opciones['usuario_id'] !== null) {
        $stmt->bindValue(':usuario_id', $opciones['usuario_id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    loglinea('ERROR FATAL: query de candidatos falló: ' . $e->getMessage(), $log_file);
    exit(1);
}

$stats['candidatos'] = count($candidatos);
loglinea("Candidatos encontrados: {$stats['candidatos']}", $log_file);
loglinea('', $log_file);

if ($stats['candidatos'] === 0) {
    loglinea('No hay usuarios que evaluar. Cerrando.', $log_file);
    exit(0);
}

// ============================================================
// BLOQUE 5-8 - Procesar cada candidato
// ============================================================

$modulos = obtener_modulos_notificaciones();

foreach ($candidatos as $usuario) {
    $uid            = (int) $usuario['id'];
    $login_usuario  = $usuario['usuario'];
    $nombre         = $usuario['nombre_completo'];
    $correo         = $usuario['correo'];
    $ultimo_acceso  = $usuario['ultimo_acceso'];
    $dias_inactivo  = (int) ((strtotime('now') - strtotime($ultimo_acceso)) / 86400);

    loglinea("--- Usuario ID=$uid ($login_usuario) '$nombre' - $dias_inactivo días inactivo ---", $log_file);

    // ---- Cooldown ----
    if (!$opciones['forzar_cooldown']) {
        if (usuario_recibio_correo_reciente($uid, RECORDATORIO_TIPO_CORREO, RECORDATORIO_COOLDOWN_DIAS)) {
            $stats['saltados_cooldown']++;
            loglinea("  SALTADO: ya recibió recordatorio en últimos " . RECORDATORIO_COOLDOWN_DIAS . " días.", $log_file);
            continue;
        }
    }

    // ---- Contar notificaciones no leídas por tipo ----
    try {
        $stmt_notif = $pdo->prepare(
            "SELECT tipo, COUNT(*) AS total
             FROM notificaciones
             WHERE usuario_destino = :uid AND leida = 0
             GROUP BY tipo"
        );
        $stmt_notif->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt_notif->execute();
        $tipos_no_leidos = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        loglinea("  ERROR consultando notificaciones: " . $e->getMessage(), $log_file);
        continue;
    }

    // ---- Agrupar por módulo ----
    $conteo_por_modulo = []; // clave_modulo => total
    $total_no_leidos   = 0;
    $tipos_descartados = 0;

    foreach ($tipos_no_leidos as $fila) {
        $tipo    = $fila['tipo'];
        $total   = (int) $fila['total'];
        $modulo  = resolver_modulo_por_tipo($tipo);

        if ($modulo === null) {
            // Tipo no mapeado (vacaciones, firma_usuario) → descartar silenciosamente
            $tipos_descartados += $total;
            continue;
        }

        if (!isset($conteo_por_modulo[$modulo])) {
            $conteo_por_modulo[$modulo] = 0;
        }
        $conteo_por_modulo[$modulo] += $total;
        $total_no_leidos            += $total;
    }

    if ($tipos_descartados > 0) {
        loglinea("  (Descartadas $tipos_descartados notificaciones de módulos excluidos)", $log_file);
    }

    // Decisión Y: sin notificaciones válidas → no enviar
    if ($total_no_leidos === 0) {
        $stats['sin_notificaciones']++;
        loglinea("  SALTADO: no tiene notificaciones no leídas de módulos activos.", $log_file);
        continue;
    }

    // Ordenar módulos según su 'orden' definido en notificaciones_modulos.php
    uksort($conteo_por_modulo, function ($a, $b) use ($modulos) {
        return ($modulos[$a]['orden'] ?? 999) <=> ($modulos[$b]['orden'] ?? 999);
    });

    loglinea("  Notificaciones no leídas: $total_no_leidos en " . count($conteo_por_modulo) . " módulo(s).", $log_file);
    foreach ($conteo_por_modulo as $mod => $tot) {
        loglinea("    - {$modulos[$mod]['etiqueta']}: $tot", $log_file);
    }

    // ---- Generar contenido HTML ----
    $primer_nombre = explode(' ', trim($nombre))[0] ?: 'usuario';
    $primer_nombre_esc = htmlspecialchars($primer_nombre, ENT_QUOTES, 'UTF-8');

    $contenido  = '<p style="margin:0 0 16px 0;">Estimado(a) <strong>' . $primer_nombre_esc . '</strong>,</p>';
    $contenido .= '<p style="margin:0 0 16px 0;">'
                . 'Le escribimos porque hemos notado que no ha ingresado al Portal de Solicitudes desde hace '
                . '<strong>' . $dias_inactivo . ' días</strong>. '
                . 'Durante este tiempo, se han acumulado <strong>' . $total_no_leidos . '</strong> '
                . 'notificacion' . ($total_no_leidos === 1 ? '' : 'es') . ' sin leer que requieren su atención.'
                . '</p>';

    $contenido .= '<p style="margin:24px 0 8px 0; font-weight:bold; color:#01401C;">Resumen por módulo:</p>';

    // Tabla-based para compatibilidad con Outlook
    $contenido .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" '
                . 'style="width:100%; border-collapse:collapse; margin:0 0 20px 0;">';
    foreach ($conteo_por_modulo as $mod => $tot) {
        $etiqueta_esc = htmlspecialchars($modulos[$mod]['etiqueta'], ENT_QUOTES, 'UTF-8');
        $url_esc      = htmlspecialchars($modulos[$mod]['url'], ENT_QUOTES, 'UTF-8');
        $tot_esc      = (int) $tot;
        $contenido .= '<tr>'
                    . '<td style="padding:10px 12px; border-bottom:1px solid #e5e5e5; font-family:Arial,Helvetica,sans-serif; font-size:14px;">'
                    . '<a href="' . $url_esc . '" style="color:#02734A; text-decoration:none; font-weight:bold;">' . $etiqueta_esc . '</a>'
                    . '</td>'
                    . '<td align="right" style="padding:10px 12px; border-bottom:1px solid #e5e5e5; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#01401C; font-weight:bold; white-space:nowrap;">'
                    . $tot_esc . ' pendiente' . ($tot_esc === 1 ? '' : 's')
                    . '</td>'
                    . '</tr>';
    }
    $contenido .= '</table>';

    $contenido .= '<p style="margin:0 0 8px 0;">'
                . 'Le solicitamos ingresar al portal para revisar y dar seguimiento a estas tareas.'
                . '</p>';

    $html = renderizar_plantilla_correo(
        'Tiene tareas pendientes en el Portal',
        $contenido,
        [
            'preheader' => "Tiene $total_no_leidos notificaciones sin leer en el Portal de Solicitudes.",
            'subtitulo' => 'Recordatorio automático de tareas pendientes',
            'boton_cta' => [
                'texto' => 'Ir al Portal',
                'url'   => URL_BASE,
            ],
        ]
    );

    $asunto = 'Tiene ' . $total_no_leidos . ' notificacion'
            . ($total_no_leidos === 1 ? '' : 'es')
            . ' pendient' . ($total_no_leidos === 1 ? 'e' : 'es')
            . ' en el Portal';

    // ---- Enviar (o simular) ----
    if ($opciones['dry_run']) {
        loglinea("  DRY-RUN: se enviaría a $correo con asunto \"$asunto\"", $log_file);
        $stats['enviados_ok']++;
        continue;
    }

    $envio_ok = enviar_correo(
        [['email' => $correo, 'nombre' => $nombre]],
        $asunto,
        $html
    );

    // ---- Registrar en `correos_enviados` ----
    registrar_correo_enviado([
        'usuario_id'   => $uid,
        'destinatario' => $correo,
        'tipo'         => RECORDATORIO_TIPO_CORREO,
        'asunto'       => $asunto,
        'exito'        => $envio_ok,
        'error_mensaje'=> $envio_ok ? null : 'Envío fallido (ver logs/correo_errores.log)',
        'metadata'     => [
            'dias_inactivo'       => $dias_inactivo,
            'total_no_leidos'     => $total_no_leidos,
            'tipos_descartados'   => $tipos_descartados,
            'modulos'             => $conteo_por_modulo,
            'forzar_cooldown'     => $opciones['forzar_cooldown'],
        ],
    ]);

    if ($envio_ok) {
        $stats['enviados_ok']++;
        loglinea("  ENVIADO OK a $correo", $log_file);
    } else {
        $stats['enviados_error']++;
        loglinea("  ERROR de envío a $correo (ver logs/correo_errores.log)", $log_file);
    }
}

// ============================================================
// BLOQUE 9 - Resumen final
// ============================================================

loglinea('', $log_file);
loglinea('==================================================================', $log_file);
loglinea('RESUMEN', $log_file);
loglinea('  Candidatos evaluados:           ' . $stats['candidatos'], $log_file);
loglinea('  Saltados por cooldown:          ' . $stats['saltados_cooldown'], $log_file);
loglinea('  Saltados por 0 notificaciones:  ' . $stats['sin_notificaciones'], $log_file);
loglinea('  Enviados con éxito:             ' . $stats['enviados_ok'] . ($opciones['dry_run'] ? ' (simulado)' : ''), $log_file);
loglinea('  Enviados con error:             ' . $stats['enviados_error'], $log_file);
loglinea('FIN', $log_file);
loglinea('==================================================================', $log_file);

exit(0);