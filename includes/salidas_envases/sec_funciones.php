<?php
/**
 * Funciones del módulo SEC — Salidas de Envases para Clientes
 *
 * Ubicación: includes/salidas_envases/sec_funciones.php
 *
 * Reglas:
 * - Cada línea de SEC se vincula a un slot específico de una unidad.
 * - Capacidad: se valida que la suma de cantidades por tipo no exceda la capacidad
 *   de la unidad (advertir y rechazar).
 * - Edición permitida sólo hasta que Almacén firme "Entrega".
 */

require_once __DIR__ . '/../../config/database.php';
if (!defined('URL_BASE')) {
    require_once __DIR__ . '/../../config/config.php';
}

// ====================================================================
// FOLIO
// ====================================================================

/**
 * Generar folio: SEC-DDMMYYYY-XXX (XXX correlativo por fecha del documento)
 */
function generar_folio_sec($fecha_documento) {
    try {
        $pdo = conectarDB();
        $ts = strtotime($fecha_documento);
        $prefijo = 'SEC-' . date('dmY', $ts) . '-';

        $stmt = $pdo->prepare("
            SELECT folio FROM salidas_envases_clientes
            WHERE folio LIKE ?
            ORDER BY folio DESC LIMIT 1
        ");
        $stmt->execute([$prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        $siguiente = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $siguiente = (int)end($partes) + 1;
        }

        return $prefijo . str_pad($siguiente, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generar_folio_sec: " . $e->getMessage());
        return 'SEC-' . date('dmY', strtotime($fecha_documento)) . '-001';
    }
}

// ====================================================================
// CONSULTAS
// ====================================================================

/**
 * Obtener SEC por ID con todas sus líneas y datos relacionados
 */
function obtener_sec_por_id($id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT s.*,
                   uc.nombre_completo AS creador_nombre,
                   ueg.nombre_completo AS entrega_user_nombre,
                   urc.nombre_completo AS recibe_user_nombre,
                   ucl.nombre_completo AS cerrada_por_nombre,
                   uca.nombre_completo AS cancelada_por_nombre
            FROM salidas_envases_clientes s
            LEFT JOIN usuarios uc  ON uc.id  = s.usuario_creador_id
            LEFT JOIN usuarios ueg ON ueg.id = s.entrega_usuario_id
            LEFT JOIN usuarios urc ON urc.id = s.recibe_usuario_id
            LEFT JOIN usuarios ucl ON ucl.id = s.cerrada_por
            LEFT JOIN usuarios uca ON uca.id = s.cancelada_por
            WHERE s.id = ?
        ");
        $stmt->execute([(int)$id]);
        $sec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sec) return false;

        $sec['lineas'] = obtener_lineas_sec($id);
        return $sec;
    } catch (Exception $e) {
        error_log("Error obtener_sec_por_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener líneas de una SEC con info de unidad y slot
 */
function obtener_lineas_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT sl.*,
                   u.nombre AS unidad_nombre,
                   u.placas AS unidad_placas,
                   u.capacidad_tmb, u.capacidad_tote, u.capacidad_gfa, u.capacidad_jaula,
                   e.id AS slot_id_ref,
                   e.hora_inicio AS slot_hora_inicio,
                   e.hora_fin    AS slot_hora_fin,
                   d.fecha       AS slot_fecha
            FROM sec_lineas sl
            LEFT JOIN unidades_transporte u           ON u.id = sl.unidad_transporte_id
            LEFT JOIN sec_espacios_disponibles e      ON e.id = sl.slot_id
            LEFT JOIN sec_disponibilidad_unidades d   ON d.id = e.disponibilidad_id
            WHERE sl.sec_id = ?
            ORDER BY sl.orden ASC, sl.id ASC
        ");
        $stmt->execute([(int)$sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_lineas_sec: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener SEC agrupadas por día (para dashboard)
 *
 * @param array $filtros [fecha_desde, fecha_hasta, estado]
 * @return array Estructura: ['YYYY-MM-DD' => [sec1, sec2, ...]]
 */
function obtener_sec_agrupadas_por_dia($filtros = []) {
    try {
        $pdo = conectarDB();
        $sql = "
            SELECT s.*,
                   uc.nombre_completo AS creador_nombre
            FROM salidas_envases_clientes s
            LEFT JOIN usuarios uc ON uc.id = s.usuario_creador_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND s.fecha_documento >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND s.fecha_documento <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND s.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (s.folio LIKE ? OR s.solicita_nombre LIKE ?)";
            $params[] = '%' . $filtros['busqueda'] . '%';
            $params[] = '%' . $filtros['busqueda'] . '%';
        }

        $sql .= " ORDER BY s.fecha_documento DESC, s.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $secs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar líneas y agrupar por fecha
        $agrupadas = [];
        foreach ($secs as $sec) {
            $sec['lineas'] = obtener_lineas_sec($sec['id']);
            $fecha = $sec['fecha_documento'];
            if (!isset($agrupadas[$fecha])) $agrupadas[$fecha] = [];
            $agrupadas[$fecha][] = $sec;
        }

        return $agrupadas;
    } catch (Exception $e) {
        error_log("Error obtener_sec_agrupadas_por_dia: " . $e->getMessage());
        return [];
    }
}

// ====================================================================
// DISPONIBILIDAD: slots libres por unidad/fecha
// ====================================================================

/**
 * Obtener slots libres de una unidad en una fecha
 */
function obtener_slots_libres_unidad_fecha($unidad_id, $fecha) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT e.id, e.hora_inicio, e.hora_fin,
                   d.id AS disponibilidad_id,
                   d.hora_inicio_ruta, d.hora_termino_ruta
            FROM sec_espacios_disponibles e
            INNER JOIN sec_disponibilidad_unidades d ON d.id = e.disponibilidad_id
            WHERE d.unidad_transporte_id = ?
              AND d.fecha = ?
              AND e.ocupado = 0
            ORDER BY e.hora_inicio ASC
        ");
        $stmt->execute([(int)$unidad_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_slots_libres_unidad_fecha: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener unidades con al menos un slot libre en la fecha
 */
function obtener_unidades_con_slots_libres($fecha) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.*
            FROM unidades_transporte u
            INNER JOIN sec_disponibilidad_unidades d ON d.unidad_transporte_id = u.id
            INNER JOIN sec_espacios_disponibles e ON e.disponibilidad_id = d.id
            WHERE u.activa = 1
              AND d.fecha = ?
              AND e.ocupado = 0
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_unidades_con_slots_libres: " . $e->getMessage());
        return [];
    }
}

// ====================================================================
// VALIDACIÓN DE LÍNEAS
// ====================================================================

/**
 * Validar conjunto de líneas de SEC
 * Espera $lineas como array de:
 *   ['cantidad' => N, 'tipo_envase' => 'TMB|TOTE|GFA|JAULA', 'slot_id' => X]
 *
 * Reglas:
 * - cantidad > 0
 * - tipo_envase válido
 * - slot_id pertenece a una unidad activa
 * - slot esté libre (ocupado=0)
 * - capacidad: suma de cantidades por tipo en cada unidad ≤ capacidad de esa unidad
 */
function validar_lineas_sec($lineas, $sec_id_excluir = null) {
    $errores = [];

    if (!is_array($lineas) || count($lineas) === 0) {
        $errores[] = 'Debes registrar al menos una línea en la SEC.';
        return $errores;
    }

    $tipos_validos = ['TMB', 'TOTE', 'GFA', 'JAULA'];
    $pdo = conectarDB();

    // Acumulador: para validar capacidades agregadas por unidad
    $acumulado_unidad = []; // [unidad_id => ['TMB' => N, ...]]
    $slots_usados = [];     // para detectar reuso del mismo slot

    foreach ($lineas as $i => $linea) {
        $nro = $i + 1;
        $cantidad    = (int)($linea['cantidad'] ?? 0);
        $tipo_envase = strtoupper(trim($linea['tipo_envase'] ?? ''));
        $slot_id     = (int)($linea['slot_id'] ?? 0);

        if ($cantidad <= 0) {
            $errores[] = "Línea $nro: la cantidad debe ser mayor a 0.";
            continue;
        }
        if (!in_array($tipo_envase, $tipos_validos, true)) {
            $errores[] = "Línea $nro: tipo de envase inválido.";
            continue;
        }
        if ($slot_id <= 0) {
            $errores[] = "Línea $nro: debes seleccionar una unidad y horario.";
            continue;
        }
        if (in_array($slot_id, $slots_usados, true)) {
            $errores[] = "Línea $nro: el mismo slot está asignado a otra línea de esta SEC.";
            continue;
        }
        $slots_usados[] = $slot_id;

        // Verificar slot libre y obtener unidad
        $stmt = $pdo->prepare("
            SELECT e.id, e.ocupado, e.sec_id, e.hora_inicio, e.hora_fin,
                   d.unidad_transporte_id, d.fecha,
                   u.nombre AS unidad_nombre, u.activa AS unidad_activa,
                   u.capacidad_tmb, u.capacidad_tote, u.capacidad_gfa, u.capacidad_jaula
            FROM sec_espacios_disponibles e
            INNER JOIN sec_disponibilidad_unidades d ON d.id = e.disponibilidad_id
            INNER JOIN unidades_transporte u ON u.id = d.unidad_transporte_id
            WHERE e.id = ?
        ");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$slot) {
            $errores[] = "Línea $nro: el slot seleccionado no existe.";
            continue;
        }
        // Slot ocupado por OTRA SEC (excluyendo la SEC en edición si aplica)
        if ((int)$slot['ocupado'] === 1 && (int)$slot['sec_id'] !== (int)$sec_id_excluir) {
            $errores[] = "Línea $nro: el slot {$slot['hora_inicio']} - {$slot['hora_fin']} de {$slot['unidad_nombre']} ya fue ocupado por otra SEC.";
            continue;
        }

        // Acumular cantidad por tipo en la unidad
        $uid = (int)$slot['unidad_transporte_id'];
        if (!isset($acumulado_unidad[$uid])) {
            $acumulado_unidad[$uid] = [
                'nombre' => $slot['unidad_nombre'],
                'TMB' => 0, 'TOTE' => 0, 'GFA' => 0, 'JAULA' => 0,
                'cap_TMB'   => (int)$slot['capacidad_tmb'],
                'cap_TOTE'  => (int)$slot['capacidad_tote'],
                'cap_GFA'   => (int)$slot['capacidad_gfa'],
                'cap_JAULA' => (int)$slot['capacidad_jaula'],
            ];
        }
        $acumulado_unidad[$uid][$tipo_envase] += $cantidad;
    }

    // Validar capacidades
    foreach ($acumulado_unidad as $uid => $info) {
        foreach (['TMB', 'TOTE', 'GFA', 'JAULA'] as $tipo) {
            $pedido = $info[$tipo];
            $cap    = $info["cap_$tipo"];
            if ($pedido > $cap) {
                $errores[] = "Unidad '{$info['nombre']}': se piden $pedido envases $tipo pero la capacidad máxima es $cap.";
            }
        }
    }

    return $errores;
}

// ====================================================================
// CREAR SEC
// ====================================================================

/**
 * Crear nueva SEC con sus líneas (transaccional)
 *
 * @param array $datos    [fecha_documento, solicita_nombre, solicita_firma, departamento_creador]
 * @param array $lineas   Cada línea: [cantidad, tipo_envase, slot_id]
 * @param int   $usuario_id
 * @return array ['success' => bool, 'id' => int|null, 'folio' => string|null, 'errores' => array]
 */
function crear_sec($datos, $lineas, $usuario_id) {
    $errores = [];

    // Validaciones básicas
    $fecha = trim($datos['fecha_documento'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errores[] = 'Fecha del documento inválida.';
    }
    $solicita_nombre = trim($datos['solicita_nombre'] ?? '');
    if ($solicita_nombre === '') {
        $errores[] = 'El nombre de quien solicita es obligatorio.';
    }
    $solicita_firma = $datos['solicita_firma'] ?? '';
    if (strpos($solicita_firma, 'data:image') !== 0) {
        $errores[] = 'La firma de Solicita es obligatoria.';
    }
    $departamento_creador = trim($datos['departamento_creador'] ?? '');
    if ($departamento_creador === '') {
        $errores[] = 'No se pudo determinar el departamento creador.';
    }

    if (!empty($errores)) {
        return ['success' => false, 'id' => null, 'folio' => null, 'errores' => $errores];
    }

    // Validar líneas
    $errores_lineas = validar_lineas_sec($lineas);
    if (!empty($errores_lineas)) {
        return ['success' => false, 'id' => null, 'folio' => null, 'errores' => $errores_lineas];
    }

    $pdo = conectarDB();
    $pdo->beginTransaction();
    try {
        // Generar folio
        $folio = generar_folio_sec($fecha);

        // Insertar SEC principal
        $stmt = $pdo->prepare("
            INSERT INTO salidas_envases_clientes (
                folio, fecha_documento,
                usuario_creador_id, departamento_creador,
                solicita_nombre, solicita_firma, solicita_fecha, solicita_usuario_id,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'enviada')
        ");
        $stmt->execute([
            $folio, $fecha,
            (int)$usuario_id, $departamento_creador,
            $solicita_nombre, $solicita_firma, (int)$usuario_id
        ]);
        $sec_id = (int)$pdo->lastInsertId();

        // Insertar líneas + ocupar slots
        $stmt_linea = $pdo->prepare("
            INSERT INTO sec_lineas (sec_id, cantidad, tipo_envase, unidad_transporte_id, slot_id, orden)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_unidad_slot = $pdo->prepare("
            SELECT d.unidad_transporte_id
            FROM sec_espacios_disponibles e
            INNER JOIN sec_disponibilidad_unidades d ON d.id = e.disponibilidad_id
            WHERE e.id = ?
        ");
        $stmt_ocupar = $pdo->prepare("
            UPDATE sec_espacios_disponibles
            SET ocupado = 1, sec_id = ?
            WHERE id = ? AND ocupado = 0
        ");

        $orden = 1;
        foreach ($lineas as $linea) {
            $slot_id = (int)$linea['slot_id'];

            $stmt_unidad_slot->execute([$slot_id]);
            $unidad_id = (int)$stmt_unidad_slot->fetchColumn();

            $stmt_linea->execute([
                $sec_id,
                (int)$linea['cantidad'],
                strtoupper($linea['tipo_envase']),
                $unidad_id,
                $slot_id,
                $orden++
            ]);

            // Ocupar slot (con verificación adicional anti race-condition)
            $stmt_ocupar->execute([$sec_id, $slot_id]);
            if ($stmt_ocupar->rowCount() === 0) {
                throw new Exception("El slot ID $slot_id ya no está disponible. Otro usuario lo tomó.");
            }
        }

        $pdo->commit();

        // Notificación (fuera de la transacción; falla silenciosa si hay error)
        $sec_creada = obtener_sec_por_id($sec_id);
        if ($sec_creada) notificar_sec_creada($sec_creada);

        return ['success' => true, 'id' => $sec_id, 'folio' => $folio, 'errores' => []];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error crear_sec: " . $e->getMessage());
        return ['success' => false, 'id' => null, 'folio' => null,
                'errores' => ['Error al guardar la SEC: ' . $e->getMessage()]];
    }
}

// ====================================================================
// HELPERS DE PRESENTACIÓN
// ====================================================================

/**
 * Color y etiqueta para badge de estado
 */
function info_estado_sec($estado) {
    $map = [
        'enviada'    => ['bg-primary',  'Enviada'],
        'entregada'  => ['bg-info',     'Entregada'],
        'recibida'   => ['bg-warning text-dark', 'Recibida'],
        'cerrada'    => ['bg-success',  'Cerrada'],
        'cancelada'  => ['bg-secondary', 'Cancelada'],
    ];
    return $map[$estado] ?? ['bg-light text-dark', $estado];
}

/**
 * Verificar si la SEC se puede editar
 * Editable mientras Almacén NO haya firmado "Entrega" (estado=enviada)
 */
function sec_es_editable($sec) {
    if (!is_array($sec)) return false;
    return $sec['estado'] === 'enviada';
}

/**
 * Verificar si la SEC se puede cancelar
 * Se puede cancelar mientras no esté cerrada ni cancelada
 */
function sec_es_cancelable($sec) {
    if (!is_array($sec)) return false;
    return !in_array($sec['estado'], ['cerrada', 'cancelada'], true);
}

/**
 * Formato largo de fecha en español
 * "Lunes, 29 de Junio de 2026"
 */
function sec_fecha_larga_es($fecha) {
    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $ts = strtotime($fecha);
    return $dias[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)-1] . ' de ' . date('Y', $ts);
}

// ====================================================================
// FIRMA ENTREGA (Almacén de Residuos — primer usuario)
// ====================================================================

function firmar_entrega_sec($sec_id, $nombre, $firma_base64, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec)                          return ['success' => false, 'errores' => ['La SEC no existe.']];
    if ($sec['estado'] !== 'enviada')   return ['success' => false, 'errores' => ['La SEC ya no está en estado "Enviada".']];
    if (trim($nombre) === '')           return ['success' => false, 'errores' => ['El nombre es obligatorio.']];
    if (strpos($firma_base64, 'data:image') !== 0) return ['success' => false, 'errores' => ['La firma es obligatoria.']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE salidas_envases_clientes
            SET entrega_nombre = ?, entrega_firma = ?, entrega_fecha = NOW(),
                entrega_usuario_id = ?, estado = 'entregada'
            WHERE id = ? AND estado = 'enviada'
        ");
        $stmt->execute([trim($nombre), $firma_base64, (int)$usuario_id, (int)$sec_id]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'errores' => ['La SEC cambió de estado mientras firmabas. Recarga.']];
        }

        // Notificación a Logística + Ventas
        $sec_actualizada = obtener_sec_por_id($sec_id);
        if ($sec_actualizada) notificar_sec_firmada_entrega($sec_actualizada);

        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error firmar_entrega_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al guardar firma: ' . $e->getMessage()]];
    }
}

// ====================================================================
// FIRMA RECIBE + CONDICIONES (Almacén — segundo usuario, distinto a Entrega)
// ====================================================================

function firmar_recibe_sec($sec_id, $nombre, $firma_base64, $usuario_id, $condiciones) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec)                                  return ['success' => false, 'errores' => ['La SEC no existe.']];
    if ($sec['estado'] !== 'entregada')         return ['success' => false, 'errores' => ['La SEC no está en estado "Entregada".']];
    if ((int)$sec['entrega_usuario_id'] === (int)$usuario_id) {
        return ['success' => false, 'errores' => ['No puedes firmar "Recibe": tú firmaste "Entrega". Debe firmarlo otro usuario de Almacén.']];
    }
    if (trim($nombre) === '')                   return ['success' => false, 'errores' => ['El nombre es obligatorio.']];
    if (strpos($firma_base64, 'data:image') !== 0) return ['success' => false, 'errores' => ['La firma es obligatoria.']];

    $b1 = !empty($condiciones['b1']) ? 1 : 0;
    $r2 = !empty($condiciones['r2']) ? 1 : 0;
    $a3 = !empty($condiciones['a3']) ? 1 : 0;
    $c4 = !empty($condiciones['c4']) ? 1 : 0;

    if ($b1 + $r2 + $a3 + $c4 === 0) {
        return ['success' => false, 'errores' => ['Debes marcar al menos una condición.']];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE salidas_envases_clientes
            SET recibe_nombre = ?, recibe_firma = ?, recibe_fecha = NOW(),
                recibe_usuario_id = ?,
                condicion_b1 = ?, condicion_r2 = ?, condicion_a3 = ?, condicion_c4 = ?,
                estado = 'recibida'
            WHERE id = ? AND estado = 'entregada'
        ");
        $stmt->execute([
            trim($nombre), $firma_base64, (int)$usuario_id,
            $b1, $r2, $a3, $c4, (int)$sec_id
        ]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'errores' => ['La SEC cambió de estado mientras firmabas. Recarga.']];
        }

        // Notificación a Logística + Ventas
        $sec_actualizada = obtener_sec_por_id($sec_id);
        if ($sec_actualizada) notificar_sec_firmada_recibe($sec_actualizada);

        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error firmar_recibe_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al guardar firma: ' . $e->getMessage()]];
    }
}

// ====================================================================
// EVIDENCIAS
// ====================================================================

function obtener_evidencias_sec($sec_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT ev.*, u.nombre_completo AS subida_por_nombre
            FROM sec_evidencias ev
            LEFT JOIN usuarios u ON u.id = ev.subida_por
            WHERE ev.sec_id = ?
            ORDER BY ev.fecha_subida DESC
        ");
        $stmt->execute([(int)$sec_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obtener_evidencias_sec: " . $e->getMessage());
        return [];
    }
}

/**
 * Subir evidencia (imagen). Carpeta destino: Imagenes_SEC/<sec_id>/
 *
 * @param int   $sec_id
 * @param array $file       Entry de $_FILES (con keys tmp_name, name, type, size, error)
 * @param int   $usuario_id
 * @return array ['success' => bool, 'errores' => array]
 */
function subir_evidencia_sec($sec_id, $file, $usuario_id) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'errores' => ['Error al subir archivo (código ' . ($file['error'] ?? '?') . ').']];
    }

    $tamano_max = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $tamano_max) {
        return ['success' => false, 'errores' => ['El archivo excede 5 MB.']];
    }

    // Validar mime real (no confiar en el header del navegador)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($file['tmp_name']);
    $mimes_validos = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_real, $mimes_validos, true)) {
        return ['success' => false, 'errores' => ['Sólo se permiten imágenes JPG, PNG o WebP. (Detectado: ' . $mime_real . ')']];
    }

    // Crear carpeta destino
    $dir = __DIR__ . '/../../Imagenes_SEC/' . (int)$sec_id;
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success' => false, 'errores' => ['No se pudo crear la carpeta destino.']];
        }
    }

    // Nombre único
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $ext_map[$mime_real];
    $nombre_archivo_disco = uniqid('ev_', true) . '.' . $ext;
    $ruta_fisica = $dir . '/' . $nombre_archivo_disco;
    $ruta_relativa = 'Imagenes_SEC/' . (int)$sec_id . '/' . $nombre_archivo_disco;

    if (!move_uploaded_file($file['tmp_name'], $ruta_fisica)) {
        return ['success' => false, 'errores' => ['No se pudo guardar el archivo.']];
    }

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO sec_evidencias (sec_id, nombre_archivo, ruta, tipo_mime, tamano, subida_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$sec_id,
            basename($file['name']),
            $ruta_relativa,
            $mime_real,
            (int)$file['size'],
            (int)$usuario_id
        ]);
        return ['success' => true, 'errores' => [], 'id' => (int)$pdo->lastInsertId()];
    } catch (Exception $e) {
        @unlink($ruta_fisica);
        error_log("Error subir_evidencia_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al registrar la evidencia: ' . $e->getMessage()]];
    }
}

function eliminar_evidencia_sec($evidencia_id) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT ruta FROM sec_evidencias WHERE id = ?");
        $stmt->execute([(int)$evidencia_id]);
        $ruta = $stmt->fetchColumn();
        if (!$ruta) return ['success' => false, 'errores' => ['La evidencia no existe.']];

        $stmt = $pdo->prepare("DELETE FROM sec_evidencias WHERE id = ?");
        $stmt->execute([(int)$evidencia_id]);

        $ruta_fisica = __DIR__ . '/../../' . $ruta;
        if (is_file($ruta_fisica)) @unlink($ruta_fisica);

        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error eliminar_evidencia_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al eliminar: ' . $e->getMessage()]];
    }
}

// ====================================================================
// EDITAR LÍNEAS DE SEC (sólo si estado = enviada)
// ====================================================================

function actualizar_lineas_sec($sec_id, $lineas_nuevas, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec)                              return ['success' => false, 'errores' => ['La SEC no existe.']];
    if ($sec['estado'] !== 'enviada')       return ['success' => false, 'errores' => ['Sólo se pueden editar líneas mientras la SEC está en estado "Enviada".']];

    // Validar excluyendo slots de esta misma SEC (que estén marcados ocupados por ella)
    $errores = validar_lineas_sec($lineas_nuevas, $sec_id);
    if (!empty($errores)) return ['success' => false, 'errores' => $errores];

    $pdo = conectarDB();
    $pdo->beginTransaction();
    try {
        // 1) Liberar todos los slots actuales de esta SEC
        $stmt = $pdo->prepare("
            UPDATE sec_espacios_disponibles
            SET ocupado = 0, sec_id = NULL
            WHERE sec_id = ?
        ");
        $stmt->execute([(int)$sec_id]);

        // 2) Borrar líneas actuales
        $stmt = $pdo->prepare("DELETE FROM sec_lineas WHERE sec_id = ?");
        $stmt->execute([(int)$sec_id]);

        // 3) Insertar líneas nuevas + ocupar slots
        $stmt_linea = $pdo->prepare("
            INSERT INTO sec_lineas (sec_id, cantidad, tipo_envase, unidad_transporte_id, slot_id, orden)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_unidad = $pdo->prepare("
            SELECT d.unidad_transporte_id
            FROM sec_espacios_disponibles e
            INNER JOIN sec_disponibilidad_unidades d ON d.id = e.disponibilidad_id
            WHERE e.id = ?
        ");
        $stmt_ocupar = $pdo->prepare("
            UPDATE sec_espacios_disponibles
            SET ocupado = 1, sec_id = ?
            WHERE id = ? AND ocupado = 0
        ");

        $orden = 1;
        foreach ($lineas_nuevas as $linea) {
            $slot_id = (int)$linea['slot_id'];
            $stmt_unidad->execute([$slot_id]);
            $unidad_id = (int)$stmt_unidad->fetchColumn();

            $stmt_linea->execute([
                $sec_id,
                (int)$linea['cantidad'],
                strtoupper($linea['tipo_envase']),
                $unidad_id,
                $slot_id,
                $orden++
            ]);

            $stmt_ocupar->execute([$sec_id, $slot_id]);
            if ($stmt_ocupar->rowCount() === 0) {
                throw new Exception("El slot ID $slot_id ya no está disponible. Otro usuario lo tomó.");
            }
        }

        $pdo->commit();
        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error actualizar_lineas_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al actualizar: ' . $e->getMessage()]];
    }
}

// ====================================================================
// CANCELAR SEC (libera slots, mantiene firmas en BD para auditoría)
// ====================================================================

function cancelar_sec($sec_id, $motivo, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec)                                                      return ['success' => false, 'errores' => ['La SEC no existe.']];
    if (in_array($sec['estado'], ['cerrada', 'cancelada'], true))   return ['success' => false, 'errores' => ['La SEC ya está ' . $sec['estado'] . '.']];

    $pdo = conectarDB();
    $pdo->beginTransaction();
    try {
        // Liberar todos los slots
        $stmt = $pdo->prepare("
            UPDATE sec_espacios_disponibles
            SET ocupado = 0, sec_id = NULL
            WHERE sec_id = ?
        ");
        $stmt->execute([(int)$sec_id]);

        // Marcar SEC como cancelada (firmas Entrega/Recibe se conservan para auditoría)
        $stmt = $pdo->prepare("
            UPDATE salidas_envases_clientes
            SET estado = 'cancelada',
                cancelada_por = ?,
                cancelada_fecha = NOW(),
                motivo_cancelacion = ?
            WHERE id = ?
        ");
        $stmt->execute([(int)$usuario_id, trim($motivo), (int)$sec_id]);

        $pdo->commit();

        // Notificación a Almacén + Ventas
        $sec_cancelada = obtener_sec_por_id($sec_id);
        if ($sec_cancelada) notificar_sec_cancelada($sec_cancelada);

        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error cancelar_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al cancelar: ' . $e->getMessage()]];
    }
}

// ====================================================================
// CERRAR SEC (sólo si está en estado 'recibida')
// ====================================================================

function cerrar_sec($sec_id, $usuario_id) {
    $sec = obtener_sec_por_id($sec_id);
    if (!$sec)                            return ['success' => false, 'errores' => ['La SEC no existe.']];
    if ($sec['estado'] !== 'recibida')    return ['success' => false, 'errores' => ['Sólo se puede cerrar una SEC en estado "Recibida". Estado actual: ' . $sec['estado'] . '.']];

    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            UPDATE salidas_envases_clientes
            SET estado = 'cerrada',
                cerrada_por = ?,
                cerrada_fecha = NOW()
            WHERE id = ? AND estado = 'recibida'
        ");
        $stmt->execute([(int)$usuario_id, (int)$sec_id]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'errores' => ['La SEC cambió de estado mientras tanto. Recarga.']];
        }

        // Notificación a Almacén + Ventas
        $sec_cerrada = obtener_sec_por_id($sec_id);
        if ($sec_cerrada) notificar_sec_cerrada($sec_cerrada);

        return ['success' => true, 'errores' => []];
    } catch (Exception $e) {
        error_log("Error cerrar_sec: " . $e->getMessage());
        return ['success' => false, 'errores' => ['Error al cerrar: ' . $e->getMessage()]];
    }
}

// ====================================================================
// NOTIFICACIONES SSE
// Insertan en la tabla `notificaciones`; el stream.php ya las propaga.
// Fallan silenciosamente (log) para no revertir la operación principal.
// ====================================================================

/**
 * Obtener IDs de usuarios activos de los códigos de departamento dados
 */
function _sec_usuarios_destino($pdo, $codigos_depto) {
    if (empty($codigos_depto)) return [];
    $placeholders = implode(',', array_fill(0, count($codigos_depto), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id
        FROM usuarios u
        INNER JOIN departamentos d ON u.departamento_id = d.id
        WHERE d.codigo IN ($placeholders) AND u.activo = 1
    ");
    $stmt->execute($codigos_depto);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Insertar notificación a un conjunto de usuarios
 */
function _sec_insertar_notificaciones($pdo, $usuarios_ids, $tipo, $titulo, $mensaje, $sec_id, $folio) {
    if (empty($usuarios_ids)) return;

    $url_base = defined('URL_BASE') ? URL_BASE : '/Pagina_Solicitudes4/';
    $datos_json = json_encode([
        'sec_id' => (int)$sec_id,
        'folio'  => $folio,
        'url'    => $url_base . 'dashboard/salidas_envases/ver_sec.php?id=' . (int)$sec_id
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_destino, datos_json, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    foreach ($usuarios_ids as $uid) {
        try {
            $stmt->execute([$tipo, $titulo, $mensaje, (int)$uid, $datos_json]);
        } catch (Exception $e) {
            error_log("Error notif SEC a usuario $uid: " . $e->getMessage());
        }
    }
}

/**
 * Notificar creación de SEC.
 * Destinatarios: Almacén de Residuos + (Logística o Ventas, el que NO la creó).
 */
function notificar_sec_creada($sec) {
    try {
        $pdo = conectarDB();
        $dept_creador = strtolower($sec['departamento_creador']);
        $codigos = ['almacen_residuos'];
        if     ($dept_creador === 'logistica') $codigos[] = 'ventas';
        elseif ($dept_creador === 'ventas')    $codigos[] = 'logistica';

        $usuarios = _sec_usuarios_destino($pdo, $codigos);
        _sec_insertar_notificaciones(
            $pdo, $usuarios,
            'sec_creada',
            '📦 Nueva Salida de Envases',
            "Se creó la SEC {$sec['folio']} por " . ucfirst($dept_creador) . ". Pendiente entrega de Almacén.",
            $sec['id'], $sec['folio']
        );
    } catch (Exception $e) {
        error_log("Error notificar_sec_creada: " . $e->getMessage());
    }
}

/**
 * Notificar firma de Entrega.
 * Destinatarios: Logística + Ventas.
 */
function notificar_sec_firmada_entrega($sec) {
    try {
        $pdo = conectarDB();
        $usuarios = _sec_usuarios_destino($pdo, ['logistica', 'ventas']);
        _sec_insertar_notificaciones(
            $pdo, $usuarios,
            'sec_entrega_firmada',
            '✍️ Entrega firmada',
            "Almacén firmó 'Entrega' en la SEC {$sec['folio']}. Pendiente firma de Recibe.",
            $sec['id'], $sec['folio']
        );
    } catch (Exception $e) {
        error_log("Error notificar_sec_firmada_entrega: " . $e->getMessage());
    }
}

/**
 * Notificar firma de Recibe.
 * Destinatarios: Logística + Ventas.
 */
function notificar_sec_firmada_recibe($sec) {
    try {
        $pdo = conectarDB();
        $usuarios = _sec_usuarios_destino($pdo, ['logistica', 'ventas']);
        _sec_insertar_notificaciones(
            $pdo, $usuarios,
            'sec_recibe_firmada',
            '✅ Recibe firmado',
            "Almacén firmó 'Recibe' en la SEC {$sec['folio']}. Lista para que Logística la cierre.",
            $sec['id'], $sec['folio']
        );
    } catch (Exception $e) {
        error_log("Error notificar_sec_firmada_recibe: " . $e->getMessage());
    }
}

/**
 * Notificar cierre de SEC.
 * Destinatarios: Almacén + Ventas.
 */
function notificar_sec_cerrada($sec) {
    try {
        $pdo = conectarDB();
        $usuarios = _sec_usuarios_destino($pdo, ['almacen_residuos', 'ventas']);
        _sec_insertar_notificaciones(
            $pdo, $usuarios,
            'sec_cerrada',
            '🟢 SEC cerrada',
            "La SEC {$sec['folio']} fue cerrada exitosamente por Logística.",
            $sec['id'], $sec['folio']
        );
    } catch (Exception $e) {
        error_log("Error notificar_sec_cerrada: " . $e->getMessage());
    }
}

/**
 * Notificar cancelación de SEC.
 * Destinatarios: Almacén + Ventas.
 */
function notificar_sec_cancelada($sec) {
    try {
        $pdo = conectarDB();
        $usuarios = _sec_usuarios_destino($pdo, ['almacen_residuos', 'ventas']);
        $motivo = !empty($sec['motivo_cancelacion']) ? ' Motivo: ' . $sec['motivo_cancelacion'] : '';
        _sec_insertar_notificaciones(
            $pdo, $usuarios,
            'sec_cancelada',
            '🔴 SEC cancelada',
            "La SEC {$sec['folio']} fue cancelada por Logística.{$motivo}",
            $sec['id'], $sec['folio']
        );
    } catch (Exception $e) {
        error_log("Error notificar_sec_cancelada: " . $e->getMessage());
    }
}