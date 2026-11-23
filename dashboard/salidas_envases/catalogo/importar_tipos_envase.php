<?php
/**
 * Handler AJAX: importación masiva de Tipos de Envase + Especificaciones.
 * dashboard/salidas_envases/catalogo/importar_tipos_envase.php
 *
 * Flujo en dos pasos (responde JSON):
 *   1) accion=analizar  (multipart, campo "archivo")
 *        Lee el .xlsx / .csv directo de tmp_name (sin moverlo — SELinux),
 *        valida renglón por renglón y devuelve el preview.
 *        Lo importable se guarda en $_SESSION['sec_import_tipos'].
 *   2) accion=confirmar (campo "token")
 *        Re-valida contra BD dentro de una transacción e inserta.
 *        Deja el resumen en $_SESSION['flash_msg'] y el JS recarga la página.
 *
 * Formato del archivo (2 columnas, 1 renglón por especificación):
 *   A = Tipo | B = Especificación   (encabezado opcional en el renglón 1)
 *
 * Reglas:
 *   - Tipo nuevo → se crea con sus especificaciones.
 *   - Tipo que YA EXISTE en BD (activo o inactivo) → se le agregan las especificaciones
 *     que aún no tiene; las que ya tiene se omiten.
 *   - Especificación repetida dentro del mismo tipo en el archivo → se omite.
 *   - Tipo o especificación vacíos, o tipo > 100 / especificación > 150 caracteres → error (renglón omitido).
 *
 * Permisos: solo Almacén de Residuos (puede_administrar_tipos_envase()).
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/permisos_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

const IMPORT_MAX_MB        = 15;                          // debe ser < upload_max_filesize del servidor
const IMPORT_MAX_BYTES     = IMPORT_MAX_MB * 1024 * 1024;
const IMPORT_MAX_RENGLONES = 5000;
const IMPORT_TTL_SEGUNDOS  = 1800;           // 30 min para confirmar el preview

function responder_json($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Autenticación + autorización ----
if (!isset($_SESSION['usuario_id'])) {
    responder_json(['ok' => false, 'msg' => 'Tu sesión expiró. Inicia sesión nuevamente.'], 401);
}
if (!puede_administrar_tipos_envase()) {
    responder_json(['ok' => false, 'msg' => 'No tienes permisos para importar al catálogo.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'msg' => 'Método no permitido.'], 405);
}

// Si el archivo excede post_max_size, PHP vacía $_POST y $_FILES por completo
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    responder_json(['ok' => false, 'msg' => 'El archivo excede el tamaño máximo permitido por el servidor.'], 413);
}
if (!verificar_csrf_ajax()) {
    responder_json(['ok' => false, 'msg' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'], 403);
}

$accion     = $_POST['accion'] ?? '';
$usuario_id = (int) $_SESSION['usuario_id'];

// =====================================================================
// HELPERS
// =====================================================================

/** Limpia una celda: BOM, espacios duros, espacios repetidos. */
function limpiar_celda($valor) {
    if ($valor === null) return '';
    $valor = (string) $valor;
    $valor = str_replace("\xEF\xBB\xBF", '', $valor);
    $valor = preg_replace('/[\s\x{00A0}]+/u', ' ', $valor) ?? $valor;
    return trim($valor);
}

/**
 * Clave de comparación: minúsculas y sin acentos.
 * Imita el comportamiento de la collation *_ci de MariaDB para detectar
 * duplicados ("Bidón" = "bidon") antes de chocar con el UNIQUE.
 */
function clave_comparacion($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ]);
}

/** Lee un CSV: quita BOM, convierte Windows-1252 → UTF-8 y detecta separador (, ; tab). */
function leer_renglones_csv($ruta) {
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        throw new RuntimeException('No se pudo leer el archivo.');
    }
    if (strncmp($contenido, "\xEF\xBB\xBF", 3) === 0) {
        $contenido = substr($contenido, 3);
    }
    // "CSV (delimitado por comas)" de Excel en Windows se guarda en ANSI
    if (!mb_check_encoding($contenido, 'UTF-8')) {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    $primera_linea = strtok($contenido, "\r\n") ?: '';
    $separador = ',';
    $max = substr_count($primera_linea, ',');
    foreach ([';', "\t"] as $candidato) {
        $n = substr_count($primera_linea, $candidato);
        if ($n > $max) { $max = $n; $separador = $candidato; }
    }

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $contenido);
    rewind($stream);

    $renglones = [];
    while (($fila = fgetcsv($stream, 0, $separador, '"', '')) !== false) {
        $renglones[] = [$fila[0] ?? '', $fila[1] ?? ''];
        if (count($renglones) > IMPORT_MAX_RENGLONES + 1) break; // +1 por encabezado
    }
    fclose($stream);
    return $renglones;
}

/** Lee la primera hoja de un .xlsx (columnas A y B). */
function leer_renglones_xlsx($ruta) {
    $lector = IOFactory::createReader('Xlsx');
    $lector->setReadDataOnly(true);
    // Solo carga columnas A:B y hasta el límite de renglones: aunque el archivo
    // traiga más hojas/columnas/formato, la memoria no se dispara.
    $lector->setReadFilter(new class implements IReadFilter {
        public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
            return ($columnAddress === 'A' || $columnAddress === 'B') && $row <= IMPORT_MAX_RENGLONES + 2;
        }
    });
    $libro = $lector->load($ruta);
    $hoja  = $libro->getSheet(0);

    $ultima = min($hoja->getHighestDataRow(), IMPORT_MAX_RENGLONES + 2);
    $renglones = [];
    for ($r = 1; $r <= $ultima; $r++) {
        $renglones[] = [
            $hoja->getCell('A' . $r)->getCalculatedValue(),
            $hoja->getCell('B' . $r)->getCalculatedValue(),
        ];
    }
    $libro->disconnectWorksheets();
    unset($libro);
    return $renglones;
}

// =====================================================================
// PASO 1: ANALIZAR
// =====================================================================
if ($accion === 'analizar') {

    // ---- Validación del upload ----
    $archivo = $_FILES['archivo'] ?? null;
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errores_upload = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente. Intenta de nuevo.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: falta carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo.',
        ];
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        responder_json(['ok' => false, 'msg' => $errores_upload[$codigo] ?? 'Error desconocido al subir el archivo.'], 400);
    }
    if ($archivo['size'] > IMPORT_MAX_BYTES) {
        responder_json(['ok' => false, 'msg' => 'El archivo excede ' . IMPORT_MAX_MB . ' MB.'], 400);
    }
    if (!is_uploaded_file($archivo['tmp_name'])) {
        responder_json(['ok' => false, 'msg' => 'Archivo inválido.'], 400);
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'csv'], true)) {
        responder_json(['ok' => false, 'msg' => 'Solo se permiten archivos .xlsx o .csv.'], 400);
    }

    // MIME real (el del navegador no es confiable)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $mimes_validos = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'],
    ];
    $mime_ok = in_array($mime, $mimes_validos[$extension], true)
            || ($extension === 'csv' && strpos((string) $mime, 'text/') === 0); // finfo a veces etiqueta CSV raro
    if (!$mime_ok) {
        responder_json(['ok' => false, 'msg' => 'El contenido del archivo no corresponde a un .' . $extension . ' válido.'], 400);
    }

    // ---- Lectura ----
    try {
        $crudos = $extension === 'xlsx'
            ? leer_renglones_xlsx($archivo['tmp_name'])
            : leer_renglones_csv($archivo['tmp_name']);
    } catch (Throwable $e) {
        error_log('importar_tipos_envase (lectura): ' . $e->getMessage());
        responder_json(['ok' => false, 'msg' => 'No se pudo leer el archivo. Verifica que no esté dañado ni protegido con contraseña.'], 400);
    }

    // Encabezado opcional: si la celda A1 dice "Tipo…" se salta
    $inicio = 0;
    if (!empty($crudos) && strpos(clave_comparacion(limpiar_celda($crudos[0][0])), 'tipo') === 0) {
        $inicio = 1;
    }

    // ---- Catálogo actual en BD (tipos activos e inactivos + sus especificaciones) ----
    try {
        $pdo = conectarDB();
        $existentes = [];   // clave_tipo => ['id', 'nombre', 'activo']
        foreach ($pdo->query("SELECT id, nombre, activo FROM sec_tipos_envase")->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $existentes[clave_comparacion(limpiar_celda($t['nombre']))] = [
                'id'     => (int) $t['id'],
                'nombre' => $t['nombre'],
                'activo' => (int) $t['activo'] === 1,
            ];
        }
        $especs_bd = [];    // tipo_id|clave_espec => true
        foreach ($pdo->query("SELECT tipo_envase_id, nombre FROM sec_especificaciones")->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $especs_bd[$e['tipo_envase_id'] . '|' . clave_comparacion(limpiar_celda($e['nombre']))] = true;
        }
    } catch (Throwable $e) {
        error_log('importar_tipos_envase (tipos existentes): ' . $e->getMessage());
        responder_json(['ok' => false, 'msg' => 'Error al consultar el catálogo actual.'], 500);
    }

    // ---- Validación renglón por renglón ----
    $filas        = [];   // para el preview
    $plan         = [];   // clave_tipo => ['tipo_id' => ?int, 'nombre' => ..., 'especificaciones' => [...]]
    $vistos_espec = [];   // clave_tipo|clave_espec => true
    $con_datos    = 0;

    for ($i = $inicio, $total = count($crudos); $i < $total; $i++) {
        $tipo  = limpiar_celda($crudos[$i][0]);
        $espec = limpiar_celda($crudos[$i][1]);

        if ($tipo === '' && $espec === '') continue; // renglón vacío
        $con_datos++;
        if ($con_datos > IMPORT_MAX_RENGLONES) {
            responder_json(['ok' => false, 'msg' => 'El archivo excede el máximo de ' . IMPORT_MAX_RENGLONES . ' renglones.'], 400);
        }

        $fila = ['renglon' => $i + 1, 'tipo' => $tipo, 'especificacion' => $espec, 'estado' => '', 'detalle' => ''];
        $clave_tipo = clave_comparacion($tipo);

        if ($tipo === '') {
            $fila['estado'] = 'error';    $fila['detalle'] = 'Tipo vacío';
        } elseif ($espec === '') {
            $fila['estado'] = 'error';    $fila['detalle'] = 'Especificación vacía';
        } elseif (mb_strlen($tipo) > 100) {
            $fila['estado'] = 'error';    $fila['detalle'] = 'Tipo excede 100 caracteres';
        } elseif (mb_strlen($espec) > 150) {
            $fila['estado'] = 'error';    $fila['detalle'] = 'Especificación excede 150 caracteres';
        } elseif (isset($existentes[$clave_tipo])
                  && isset($especs_bd[$existentes[$clave_tipo]['id'] . '|' . clave_comparacion($espec)])) {
            $fila['estado'] = 'omitido';  $fila['detalle'] = 'Ya existe en "' . $existentes[$clave_tipo]['nombre'] . '"';
        } elseif (isset($vistos_espec[$clave_tipo . '|' . clave_comparacion($espec)])) {
            $fila['estado'] = 'omitido';  $fila['detalle'] = 'Duplicada en el archivo';
        } else {
            $vistos_espec[$clave_tipo . '|' . clave_comparacion($espec)] = true;
            if (isset($existentes[$clave_tipo])) {
                // Tipo existente: la especificación se agrega a él (se respeta el nombre de BD)
                $ex = $existentes[$clave_tipo];
                if (!isset($plan[$clave_tipo])) {
                    $plan[$clave_tipo] = ['tipo_id' => $ex['id'], 'nombre' => $ex['nombre'], 'especificaciones' => []];
                }
                $fila['detalle'] = 'Se agrega a "' . $ex['nombre'] . '"' . ($ex['activo'] ? '' : ' (tipo inactivo)');
            } elseif (!isset($plan[$clave_tipo])) {
                // El primer renglón define cómo se escribe el nombre del tipo nuevo
                $plan[$clave_tipo] = ['tipo_id' => null, 'nombre' => $tipo, 'especificaciones' => []];
                $fila['detalle'] = 'Tipo nuevo';
            } elseif ($plan[$clave_tipo]['nombre'] !== $tipo) {
                $fila['detalle'] = 'Se agrupa en "' . $plan[$clave_tipo]['nombre'] . '"';
            }
            $plan[$clave_tipo]['especificaciones'][] = $espec;
            $fila['estado'] = 'crear';
        }
        $filas[] = $fila;
    }

    if ($con_datos === 0) {
        responder_json(['ok' => false, 'msg' => 'El archivo no contiene datos. Columna A = Tipo, columna B = Especificación.'], 400);
    }

    $resumen = [
        'tipos_crear'      => count(array_filter($plan, static fn($t) => $t['tipo_id'] === null)),
        'tipos_existentes' => count(array_filter($plan, static fn($t) => $t['tipo_id'] !== null)),
        'especs_crear' => array_sum(array_map(static fn($t) => count($t['especificaciones']), $plan)),
        'omitidos'     => count(array_filter($filas, static fn($f) => $f['estado'] === 'omitido')),
        'errores'      => count(array_filter($filas, static fn($f) => $f['estado'] === 'error')),
    ];

    $token = bin2hex(random_bytes(16));
    $_SESSION['sec_import_tipos'] = [
        'token'   => $token,
        'creado'  => time(),
        'plan'    => array_values($plan),
        'resumen' => $resumen,
    ];

    responder_json([
        'ok'      => true,
        'token'   => $resumen['especs_crear'] > 0 ? $token : null,
        'archivo' => $archivo['name'],
        'resumen' => $resumen,
        'filas'   => $filas,
    ]);
}

// =====================================================================
// PASO 2: CONFIRMAR
// =====================================================================
if ($accion === 'confirmar') {
    $pendiente = $_SESSION['sec_import_tipos'] ?? null;
    $token     = (string) ($_POST['token'] ?? '');

    if (!$pendiente || $token === '' || !hash_equals($pendiente['token'], $token)) {
        responder_json(['ok' => false, 'msg' => 'La importación ya no está disponible. Vuelve a analizar el archivo.'], 409);
    }
    // Se consume de inmediato: un segundo clic / segundo request no vuelve a insertar
    unset($_SESSION['sec_import_tipos']);
    session_write_close();

    if (time() - (int) $pendiente['creado'] > IMPORT_TTL_SEGUNDOS) {
        responder_json(['ok' => false, 'msg' => 'El análisis expiró (30 min). Vuelve a subir el archivo.'], 409);
    }

    $tipos_creados   = 0;
    $especs_creadas  = 0;
    $especs_en_exist = 0; // de las creadas, cuántas fueron a tipos que ya existían
    $omitidos_extra  = 0; // cambios en el catálogo entre el preview y la confirmación

    $pdo = null;
    try {
        $pdo = conectarDB();
        $pdo->beginTransaction();

        $stmt_existe = $pdo->prepare("SELECT id FROM sec_tipos_envase WHERE nombre = ? LIMIT 1");
        $stmt_por_id = $pdo->prepare("SELECT id FROM sec_tipos_envase WHERE id = ?");
        $stmt_tipo   = $pdo->prepare("INSERT INTO sec_tipos_envase (nombre, creado_por) VALUES (?, ?)");
        $stmt_espec  = $pdo->prepare("INSERT INTO sec_especificaciones (tipo_envase_id, nombre, creado_por) VALUES (?, ?, ?)");

        foreach ($pendiente['plan'] as $tipo) {
            $tipo_id   = $tipo['tipo_id'];
            $existente = $tipo_id !== null;

            if ($existente) {
                // Re-validación: ¿lo eliminaron mientras se revisaba el preview?
                $stmt_por_id->execute([$tipo_id]);
                if ($stmt_por_id->fetchColumn() === false) {
                    $omitidos_extra += count($tipo['especificaciones']);
                    continue;
                }
            } else {
                // Re-validación: ¿lo crearon mientras se revisaba el preview? → se reutiliza
                $stmt_existe->execute([$tipo['nombre']]);
                $id_actual = $stmt_existe->fetchColumn();
                if ($id_actual !== false) {
                    $tipo_id   = (int) $id_actual;
                    $existente = true;
                } else {
                    $stmt_tipo->execute([$tipo['nombre'], $usuario_id]);
                    $tipo_id = (int) $pdo->lastInsertId();
                    $tipos_creados++;
                }
            }

            foreach ($tipo['especificaciones'] as $nombre_espec) {
                try {
                    $stmt_espec->execute([$tipo_id, $nombre_espec, $usuario_id]);
                    $especs_creadas++;
                    if ($existente) $especs_en_exist++;
                } catch (PDOException $e) {
                    // 23000 = UNIQUE (tipo_envase_id, nombre): la collation la considera duplicada
                    if ($e->getCode() !== '23000') throw $e;
                    $omitidos_extra++;
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        error_log('importar_tipos_envase (confirmar): ' . $e->getMessage());
        responder_json(['ok' => false, 'msg' => 'Error al importar. No se guardó ningún registro.'], 500);
    }

    $omitidos = (int) $pendiente['resumen']['omitidos'] + (int) $pendiente['resumen']['errores'] + $omitidos_extra;
    $msg = "Importación completada: {$tipos_creados} tipo(s) creado(s), {$especs_creadas} especificación(es) creada(s)";
    if ($especs_en_exist > 0) {
        $msg .= " ({$especs_en_exist} en tipos existentes)";
    }
    $msg .= $omitidos > 0 ? ", {$omitidos} renglón(es) omitido(s)." : '.';

    session_start();
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = 'success';

    responder_json(['ok' => true, 'msg' => $msg]);
}

responder_json(['ok' => false, 'msg' => 'Acción inválida.'], 400);