<?php
/**
 * Procesar Programación General de Mantenimientos (Solo Sistemas)
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_programacion.php
 *
 * Crea una solicitud de mantenimiento por cada equipo de cómputo programable,
 * igual que "Nueva Solicitud" (estado inicial: pendiente, grupo: computo,
 * prioridad: media, atendido_por: quien programa).
 *
 * - Equipos sin pendientes: tipo y fecha obligatorios (todo o nada)
 * - Equipos con mantenimiento pendiente: opcional, cualquier tipo (solo se avisa)
 * - Físico: se guardan en solicitudes_mantenimiento_perifericos la computadora
 *   y/o los periféricos seleccionados (al menos uno)
 * - Folio: MANT-{fecha en que se programa}-NNNN
 * - No se notifica a los usuarios; solo se genera una notificación de
 *   confirmación para quien crea la programación
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/mantenimientos_ti/programacion_funciones.php';

$URL_PROG = URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/programacion_mantenimientos.php';

// ------- Verificar sesion -------
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// ------- Restringir a Sistemas -------
$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas  = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'Solo el departamento de Sistemas puede programar mantenimientos.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $URL_PROG);
    exit;
}

$entrada = $_POST['programacion'] ?? [];
if (!is_array($entrada)) {
    $entrada = [];
}

$pdo = conectarDB();
$hoy = date('Y-m-d');

$nombres_tipo = ['fisico' => 'físico', 'logico' => 'lógico'];

try {
    $pdo->beginTransaction();

    // -----------------------------------------------------------------
    // 1) Recalcular equipos dentro de la transacción
    //    - programable: tipo y fecha obligatorios
    //    - con_pendiente: opcional, cualquier tipo
    // -----------------------------------------------------------------
    $equipos    = prog_obtener_equipos($pdo);
    $candidatos = array_filter($equipos, fn($e) => in_array($e['estado_fila'], ['programable', 'con_pendiente'], true));

    $errores = [];

    // Equipos enviados con datos que ya no se pueden programar (programados por
    // alguien más, dados de baja o cambiados en inventario mientras se capturaba)
    foreach ($entrada as $id_enviado => $datos) {
        $id_enviado = intval($id_enviado);
        $con_datos  = is_array($datos)
                      && (trim($datos['tipo'] ?? '') !== '' || trim($datos['fecha'] ?? '') !== '');
        if ($con_datos && !isset($candidatos[$id_enviado])) {
            $etq = isset($equipos[$id_enviado]) ? prog_etiqueta_equipo($equipos[$id_enviado]) : "ID {$id_enviado}";
            $errores[] = "El equipo {$etq} ya no está disponible para programar (ya fue programado o cambió en el inventario).";
        }
    }

    $filas = [];
    foreach ($candidatos as $id => $eq) {
        $etq         = prog_etiqueta_equipo($eq);
        $obligatorio = ($eq['estado_fila'] === 'programable');
        $tipo        = is_array($entrada[$id] ?? null) ? trim($entrada[$id]['tipo']  ?? '') : '';
        $fecha       = is_array($entrada[$id] ?? null) ? trim($entrada[$id]['fecha'] ?? '') : '';

        // Fila opcional sin capturar: se omite
        if (!$obligatorio && $tipo === '' && $fecha === '') {
            continue;
        }

        if ($tipo === '') {
            $errores[] = "Equipo {$etq}: falta el tipo de mantenimiento.";
            continue;
        }
        if (!array_key_exists($tipo, prog_tipos_mantenimiento())) {
            $errores[] = "Equipo {$etq}: tipo de mantenimiento no válido.";
            continue;
        }
        if ($fecha === '') {
            $errores[] = "Equipo {$etq}: falta la fecha.";
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            $errores[] = "Equipo {$etq}: la fecha no es válida.";
            continue;
        }
        if ($fecha < $hoy) {
            $errores[] = "Equipo {$etq}: la fecha no puede ser anterior a hoy.";
            continue;
        }

        // Periféricos: solo en mantenimiento Físico. El propio id del equipo = "Computadora".
        $componentes = [];
        if ($tipo === 'fisico') {
            $seleccion = $entrada[$id]['perifericos'] ?? [];
            $seleccion = is_array($seleccion) ? array_unique(array_map('intval', $seleccion)) : [];

            foreach ($seleccion as $sel_id) {
                if ($sel_id === intval($id)) {
                    $componentes[] = [
                        'equipo_id'      => intval($id),
                        'tipo_equipo'    => $eq['tipo_equipo'],
                        'codigo_interno' => $eq['codigo_interno'],
                        'es_principal'   => 1,
                    ];
                } elseif (isset($eq['perifericos'][$sel_id])) {
                    $per = $eq['perifericos'][$sel_id];
                    $componentes[] = [
                        'equipo_id'      => $sel_id,
                        'tipo_equipo'    => $per['tipo_equipo'],
                        'codigo_interno' => $per['codigo_interno'],
                        'es_principal'   => 0,
                    ];
                } else {
                    $errores[] = "Equipo {$etq}: uno de los periféricos seleccionados ya no está vinculado al equipo.";
                    continue 2;
                }
            }

            if (empty($componentes)) {
                $errores[] = "Equipo {$etq}: selecciona al menos un elemento (computadora o periférico) para el mantenimiento físico.";
                continue;
            }
            // Computadora primero, después periféricos
            usort($componentes, fn($a, $b) => $b['es_principal'] <=> $a['es_principal']);
        }

        $filas[] = [
            'equipo'      => $eq,
            'tipo'        => $tipo,
            'fecha'       => $fecha,
            'componentes' => $componentes,
        ];
    }

    if (empty($errores) && empty($filas)) {
        $errores[] = 'No hay mantenimientos por crear. Captura al menos un equipo.';
    }

    if (!empty($errores)) {
        $pdo->rollBack();
        $total = count($errores);
        if ($total > 10) {
            $errores   = array_slice($errores, 0, 10);
            $errores[] = '... y ' . ($total - 10) . ' error(es) más.';
        }
        prog_regresar_con_errores($errores, $URL_PROG);
    }

    // -----------------------------------------------------------------
    // 2) Consecutivo de folio por FECHA EN QUE SE PROGRAMA (hoy):
    //    MANT-{YYYYMMDD de hoy}-NNNN, sin importar la fecha programada.
    //    FOR UPDATE serializa dos programaciones simultáneas.
    // -----------------------------------------------------------------
    $prefijo = 'MANT-' . date('Ymd') . '-';
    $stmt_folio = $pdo->prepare("SELECT folio FROM solicitudes_mantenimiento_ti
                                 WHERE folio LIKE ?
                                 ORDER BY folio DESC
                                 LIMIT 1
                                 FOR UPDATE");
    $stmt_folio->execute([$prefijo . '%']);
    $ultimo_folio = $stmt_folio->fetchColumn();
    $consecutivo  = $ultimo_folio ? intval(substr($ultimo_folio, -4)) : 0;

    // -----------------------------------------------------------------
    // 3) Insertar solicitudes + historial (mismo formato que "crear")
    // -----------------------------------------------------------------
    $sql_ins = "INSERT INTO solicitudes_mantenimiento_ti
                (folio, tipo_equipo, grupo_equipo, equipo_id, tipo_mantenimiento, prioridad,
                 fecha_deseada, hora_deseada, usuario_id, departamento_id,
                 descripcion_problema, estado, atendido_por, fecha_solicitud)
                VALUES (?, ?, 'computo', ?, ?, 'media', ?, NULL, ?, ?, ?, 'pendiente', ?, NOW())";
    $stmt_ins = $pdo->prepare($sql_ins);

    $sql_hist = "INSERT INTO historial_mantenimientos_ti
                 (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                 VALUES (?, NULL, 'pendiente', 'Mantenimiento creado desde la Programación General', ?, NOW())";
    $stmt_hist = $pdo->prepare($sql_hist);

    $sql_comp = "INSERT INTO solicitudes_mantenimiento_perifericos
                 (solicitud_id, equipo_id, tipo_equipo, codigo_interno, es_principal)
                 VALUES (?, ?, ?, ?, ?)";
    $stmt_comp = $pdo->prepare($sql_comp);

    $nombres_tipo_eq = prog_nombres_tipo_equipo();

    $folios = [];
    foreach ($filas as $f) {
        $consecutivo++;
        if ($consecutivo > 9999) {
            throw new Exception('Se alcanzó el límite de folios del día.');
        }
        $folio = $prefijo . str_pad($consecutivo, 4, '0', STR_PAD_LEFT);
        $eq    = $f['equipo'];

        $descripcion = 'Mantenimiento preventivo ' . $nombres_tipo[$f['tipo']]
                     . ' programado desde la Programación General de Mantenimientos.';

        // En Físico, la descripción lista lo incluido (visible en ver_mantenimiento.php)
        if (!empty($f['componentes'])) {
            $lista = array_map(function ($c) use ($nombres_tipo_eq) {
                $nombre = $c['es_principal'] ? 'Computadora' : ($nombres_tipo_eq[$c['tipo_equipo']] ?? $c['tipo_equipo']);
                return $nombre . ($c['codigo_interno'] ? " ({$c['codigo_interno']})" : '');
            }, $f['componentes']);
            $descripcion .= "\nIncluye: " . implode(', ', $lista) . '.';
        }

        $stmt_ins->execute([
            $folio,
            $eq['tipo_equipo'],
            intval($eq['id']),
            $f['tipo'],
            $f['fecha'],
            intval($eq['usuario_destino_id']),
            intval($eq['departamento_id']),
            $descripcion,
            $_SESSION['usuario_id'],
        ]);

        $solicitud_id = $pdo->lastInsertId();
        $stmt_hist->execute([$solicitud_id, $_SESSION['usuario_id']]);

        foreach ($f['componentes'] as $c) {
            $stmt_comp->execute([
                $solicitud_id,
                $c['equipo_id'],
                $c['tipo_equipo'],
                $c['codigo_interno'] ?: null,
                $c['es_principal'],
            ]);
        }

        $folios[] = $folio;
    }

    // -----------------------------------------------------------------
    // 4) Notificación de confirmación para quien programa
    // -----------------------------------------------------------------
    $total_creados = count($folios);
    $folio_ini     = $folios[0];
    $folio_fin     = $folios[$total_creados - 1];

    $titulo  = 'Programación de Mantenimientos creada';
    $mensaje = "Se programaron {$total_creados} mantenimiento(s) correctamente. Folios {$folio_ini} a {$folio_fin}.";
    $datos   = json_encode([
        'total'         => $total_creados,
        'folio_inicial' => $folio_ini,
        'folio_final'   => $folio_fin,
        'url'           => URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php?registro=programado',
    ]);

    $stmt = $pdo->prepare("INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
                           VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['mantenimiento_nuevo', $titulo, $mensaje, $_SESSION['usuario_id'], $datos]);

    $pdo->commit();

    establecer_alerta('success', "Programación creada: {$total_creados} mantenimiento(s) en estado Pendiente (folios {$folio_ini} a {$folio_fin}).");
    header('Location: ' . $URL_PROG);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al crear programación de mantenimientos: ' . $e->getMessage());
    prog_regresar_con_errores(
        ['Error al crear la programación. No se guardó ningún mantenimiento. Intente nuevamente.'],
        $URL_PROG
    );
}

// =====================================================================
// HELPERS
// =====================================================================

/**
 * Regresa a la vista conservando lo capturado
 */
function prog_regresar_con_errores(array $errores, string $url): void {
    $_SESSION['form_errors_prog'] = $errores;
    $_SESSION['form_data_prog']   = is_array($_POST['programacion'] ?? null) ? $_POST['programacion'] : [];
    header('Location: ' . $url);
    exit;
}