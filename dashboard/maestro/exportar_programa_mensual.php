<?php
/**
 * Exportar Programa Mensual de Mantenimiento (FR-TI-06)
 * dashboard/maestro/exportar_programa_mensual.php
 *
 * v3.5 - PARCHE ZIP para preservar shapes (textboxes EJECUTÓ/VALIDÓ)
 *      - PhpSpreadsheet descarta xdr:sp (shapes) al guardar; este script
 *        las reinyecta directamente en el ZIP del archivo final
 *      - 24 departamentos del inventario en orden alfabetico
 *      - Logo, hoja XLOOKUP, NOTAS y firmas validacion preservados
 *
 * Filtros recibidos por POST:
 *   - mes (1-12)
 *   - anio (4 digitos)
 *   - tipo_mantenimiento ('fisico' | 'logico')
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// =====================================================================
// SEGURIDAD
// =====================================================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'No tiene permisos para acceder a esta función.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// VALIDAR FILTROS
// =====================================================================
$mes  = intval($_POST['mes']  ?? 0);
$anio = intval($_POST['anio'] ?? 0);
$tipo_mantenimiento = $_POST['tipo_mantenimiento'] ?? '';

if ($mes < 1 || $mes > 12) {
    establecer_alerta('error', 'Mes inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if ($anio < 2020 || $anio > 2100) {
    establecer_alerta('error', 'Año inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if (!in_array($tipo_mantenimiento, ['fisico', 'logico'])) {
    establecer_alerta('error', 'Tipo de mantenimiento inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// LOCALIZAR PLANTILLA
// =====================================================================
$ruta_plantilla = __DIR__ . '/../../plantillas_reportes/Programa_mensual_de_mantenimiento_preventivo_de_TI.xlsx';

if (!file_exists($ruta_plantilla)) {
    establecer_alerta('error', 'Plantilla no encontrada: plantillas_reportes/Programa_mensual_de_mantenimiento_preventivo_de_TI.xlsx');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// CONSTANTES
// =====================================================================
$MESES_ES = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$DIAS_ES_INICIAL = ['L', 'M', 'M', 'J', 'V', 'S', 'D']; // Lun-Dom

$DEPARTAMENTOS = [
    'Almacén de Refacciones',
    'Almacén de Residuos',
    'Atención a Clientes',
    'Calidad',
    'Comercial',
    'Contabilidad',
    'Crédito y Cobranza',
    'Dirección',
    'Facturación',
    'Gestión de Talento Humano',
    'Laboratorio',
    'Logística',
    'Mantenimiento',
    'Normatividad',
    'Otros',
    'PTAR',
    'Sala de Capacitaciones',
    'Sala de Juntas',
    'Seguridad',
    'Sistemas',
    'SITE',
    'Tesorería',
    'Ventas',
    'Vigilancia',
];
$NUM_DEPTOS = count($DEPARTAMENTOS); // 24

$INDICE_POR_NOMBRE = [];
foreach ($DEPARTAMENTOS as $idx => $nombre) {
    $INDICE_POR_NOMBRE[normalizarTexto($nombre)] = $idx;
}

// =====================================================================
// PASO PREVIO: EXTRAER DRAWINGS ORIGINALES DE LA PLANTILLA
// (antes de procesar con PhpSpreadsheet, que los descarta)
// =====================================================================
$drawings_originales = extraerDrawingsOriginales($ruta_plantilla);

// =====================================================================
// CONSULTAR DATOS
// =====================================================================
$pdo = conectarDB();

$sql = "
    SELECT 
        sm.id, sm.folio, sm.fecha_deseada, sm.fecha_finalizacion,
        sm.estado, sm.tipo_mantenimiento, sm.equipo_id, sm.departamento_id,
        d.codigo  AS depto_codigo,
        d.nombre  AS depto_nombre,
        ie.tipo_equipo
    FROM solicitudes_mantenimiento_ti sm
    LEFT JOIN departamentos d ON sm.departamento_id = d.id
    LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
    WHERE sm.tipo_mantenimiento = :tipo
      AND sm.estado != 'cancelada'
      AND (
            (YEAR(sm.fecha_deseada) = :anio_p AND MONTH(sm.fecha_deseada) = :mes_p)
         OR (YEAR(sm.fecha_finalizacion) = :anio_e AND MONTH(sm.fecha_finalizacion) = :mes_e
             AND sm.estado IN ('finalizada','cerrada'))
      )
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':tipo'   => $tipo_mantenimiento,
    ':anio_p' => $anio,
    ':mes_p'  => $mes,
    ':anio_e' => $anio,
    ':mes_e'  => $mes,
]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// AGRUPAR CONTEOS
// =====================================================================
$conteos = [];
$indice_otros = $INDICE_POR_NOMBRE['otros'] ?? 14;

$aliases = [
    'recursos_humanos'           => 'gestion_de_talento_humano',
    'gth'                        => 'gestion_de_talento_humano',
    'ti'                         => 'sistemas',
    'ti_sistemas'                => 'sistemas',
    'mantenimiento_industrial'   => 'mantenimiento',
    'seguridad_industrial'       => 'seguridad',
    'atencion_clientes'          => 'atencion_a_clientes',
    'almacen_refacciones'        => 'almacen_de_refacciones',
    'almacen_residuos'           => 'almacen_de_residuos',
    'credito_cobranza'           => 'credito_y_cobranza',
    'sala_juntas'                => 'sala_de_juntas',
    'sala_capacitaciones'        => 'sala_de_capacitaciones',
    'sala_capacitacion'          => 'sala_de_capacitaciones',
];

foreach ($solicitudes as $s) {
    $idx = null;

    $clave_nombre = normalizarTexto($s['depto_nombre'] ?? '');
    if (isset($INDICE_POR_NOMBRE[$clave_nombre])) {
        $idx = $INDICE_POR_NOMBRE[$clave_nombre];
    }

    if ($idx === null) {
        $clave_codigo = normalizarTexto($s['depto_codigo'] ?? '');
        if (isset($INDICE_POR_NOMBRE[$clave_codigo])) {
            $idx = $INDICE_POR_NOMBRE[$clave_codigo];
        }
    }

    if ($idx === null) {
        $clave_n  = normalizarTexto($s['depto_codigo'] ?? '');
        $clave_n2 = normalizarTexto($s['depto_nombre'] ?? '');
        foreach ([$clave_n, $clave_n2] as $clave) {
            if (isset($aliases[$clave]) && isset($INDICE_POR_NOMBRE[$aliases[$clave]])) {
                $idx = $INDICE_POR_NOMBRE[$aliases[$clave]];
                break;
            }
        }
    }

    if ($idx === null) {
        $idx = $indice_otros;
    }

    if (!isset($conteos[$idx])) {
        $conteos[$idx] = [];
    }

    if (!empty($s['fecha_deseada'])) {
        $ts_d = strtotime($s['fecha_deseada']);
        if ($ts_d && (int)date('Y', $ts_d) === $anio && (int)date('n', $ts_d) === $mes) {
            $dia_p = (int)date('j', $ts_d);
            if (!isset($conteos[$idx][$dia_p])) {
                $conteos[$idx][$dia_p] = ['programado' => 0, 'ejecutado' => 0];
            }
            $conteos[$idx][$dia_p]['programado']++;
        }
    }

    if (!empty($s['fecha_finalizacion']) && in_array($s['estado'], ['finalizada', 'cerrada'])) {
        $ts_f = strtotime($s['fecha_finalizacion']);
        if ($ts_f && (int)date('Y', $ts_f) === $anio && (int)date('n', $ts_f) === $mes) {
            $dia_e = (int)date('j', $ts_f);
            if (!isset($conteos[$idx][$dia_e])) {
                $conteos[$idx][$dia_e] = ['programado' => 0, 'ejecutado' => 0];
            }
            $conteos[$idx][$dia_e]['ejecutado']++;
        }
    }
}

// =====================================================================
// CARGAR PLANTILLA Y TRANSFORMAR
// =====================================================================
try {
    $spreadsheet = IOFactory::load($ruta_plantilla);

    $hoja_principal = $spreadsheet->getSheetByName('Mensual (2)');
    if (!$hoja_principal) {
        throw new Exception('No se encontró la hoja "Mensual (2)" en la plantilla.');
    }
    $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($hoja_principal));
    $sheet = $spreadsheet->getActiveSheet();

    $FILAS_EXTRA = ($NUM_DEPTOS - 17) * 2; // 14
    $FILA_INSERT = 45;
    $FILA_P_REF = 43;
    $FILA_E_REF = 44;
    $MAX_COL = 36;

    // PASO 1: Capturar merges en filas >= 45
    $merges_a_desplazar = [];
    $merges_actuales = $sheet->getMergeCells();
    foreach ($merges_actuales as $rangoMerge => $_) {
        $coords = Coordinate::splitRange($rangoMerge);
        list($cellStart, $cellEnd) = $coords[0];
        $startCol = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellStart));
        $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);
        $endCol   = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellEnd));
        $endRow   = (int) preg_replace('/[A-Z]/', '', $cellEnd);

        if ($startRow >= $FILA_INSERT) {
            $merges_a_desplazar[] = [$startCol, $startRow, $endCol, $endRow];
        }
    }

    // PASO 2: Quitar merges en cols B-C de filas 11-44
    foreach ($merges_actuales as $rangoMerge => $_) {
        $coords = Coordinate::splitRange($rangoMerge);
        list($cellStart, $_) = $coords[0];
        $startCol = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellStart));
        $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);

        if ($startRow >= 11 && $startRow <= 44 && in_array($startCol, [2, 3])) {
            try { $sheet->unmergeCells($rangoMerge); } catch (Exception $e) {}
        }
    }

    for ($fila = 11; $fila <= 44; $fila++) {
        for ($col = 2; $col <= 5; $col++) {
            $coord = Coordinate::stringFromColumnIndex($col) . $fila;
            $sheet->setCellValue($coord, null);
        }
    }

    // PASO 3: Quitar merges >= 45
    foreach ($merges_actuales as $rangoMerge => $_) {
        $coords = Coordinate::splitRange($rangoMerge);
        list($cellStart, $_) = $coords[0];
        $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);

        if ($startRow >= $FILA_INSERT) {
            try { $sheet->unmergeCells($rangoMerge); } catch (Exception $e) {}
        }
    }

    // PASO 4: Capturar alturas
    $altura_p = $sheet->getRowDimension($FILA_P_REF)->getRowHeight();
    $altura_e = $sheet->getRowDimension($FILA_E_REF)->getRowHeight();
    if ($altura_p < 0 || $altura_p === null) $altura_p = 15.0;
    if ($altura_e < 0 || $altura_e === null) $altura_e = 15.0;

    // PASO 5: Insertar 14 filas
    if ($FILAS_EXTRA > 0) {
        $sheet->insertNewRowBefore($FILA_INSERT, $FILAS_EXTRA);
    }

    // PASO 6: Recrear merges desplazados
    foreach ($merges_a_desplazar as list($minCol, $minRow, $maxCol, $maxRow)) {
        $rango = Coordinate::stringFromColumnIndex($minCol) . ($minRow + $FILAS_EXTRA) . ':'
               . Coordinate::stringFromColumnIndex($maxCol) . ($maxRow + $FILAS_EXTRA);
        try {
            $sheet->mergeCells($rango);
        } catch (Exception $e) {}
    }

    // PASO 7: Aplicar formato + altura a las nuevas filas
    for ($i = 0; $i < $FILAS_EXTRA; $i++) {
        $fila_destino = $FILA_INSERT + $i;
        $fila_origen  = ($i % 2 === 0) ? $FILA_P_REF : $FILA_E_REF;
        $altura_ref   = ($i % 2 === 0) ? $altura_p : $altura_e;

        $sheet->getRowDimension($fila_destino)->setRowHeight($altura_ref);

        for ($col = 1; $col <= $MAX_COL; $col++) {
            $col_letter = Coordinate::stringFromColumnIndex($col);
            $sheet->duplicateStyle(
                $sheet->getStyle($col_letter . $fila_origen),
                $col_letter . $fila_destino
            );
        }
    }

    // PASO 8: Escribir 24 deptos
    foreach ($DEPARTAMENTOS as $idx => $depto) {
        $fila_p = 11 + ($idx * 2);
        $fila_e = $fila_p + 1;

        $sheet->setCellValue('B' . $fila_p, $idx + 1);
        $sheet->setCellValue('C' . $fila_p, $depto);
        $sheet->setCellValue('D' . $fila_p, 'Programado');
        $sheet->setCellValue('D' . $fila_e, 'Ejecutado');
        $sheet->setCellValue('E' . $fila_p, "=+SUM(F{$fila_p}:AJ{$fila_p})");
        $sheet->setCellValue('E' . $fila_e, "=+SUM(F{$fila_e}:AJ{$fila_e})");
    }

    // PASO 9: Aplicar merges B y C
    for ($idx = 0; $idx < $NUM_DEPTOS; $idx++) {
        $fila_p = 11 + ($idx * 2);
        $fila_e = $fila_p + 1;
        try { $sheet->mergeCells("B{$fila_p}:B{$fila_e}"); } catch (Exception $e) {}
        try { $sheet->mergeCells("C{$fila_p}:C{$fila_e}"); } catch (Exception $e) {}
    }

    // PASO 10: Cabeceras
    $sheet->setCellValue('D6', $MESES_ES[$mes]);
    $sheet->setCellValue('D7', $anio);
    $sheet->setCellValue('AB6', ucfirst($tipo_mantenimiento));

    // PASO 11: Cabecera de dias
    $dias_en_mes = (int)date('t', mktime(0, 0, 0, $mes, 1, $anio));
    for ($d = 1; $d <= 31; $d++) {
        $col_idx = 5 + $d;
        $col_letter = Coordinate::stringFromColumnIndex($col_idx);

        if ($d <= $dias_en_mes) {
            $ts_dia = mktime(0, 0, 0, $mes, $d, $anio);
            $dia_semana = (int)date('N', $ts_dia);
            $inicial = $DIAS_ES_INICIAL[$dia_semana - 1];

            $sheet->setCellValue($col_letter . '9', $inicial);
            $sheet->setCellValue($col_letter . '10', $d);
        } else {
            $sheet->setCellValue($col_letter . '9', '');
            $sheet->setCellValue($col_letter . '10', '');
        }
    }

    // PASO 12: Llenar conteos
    foreach ($conteos as $idx => $dias_data) {
        if ($idx >= $NUM_DEPTOS) continue;
        $fila_p = 11 + ($idx * 2);
        $fila_e = $fila_p + 1;

        foreach ($dias_data as $dia => $valores) {
            if ($dia < 1 || $dia > 31 || $dia > $dias_en_mes) continue;

            $col_idx = 5 + $dia;
            $col_letter = Coordinate::stringFromColumnIndex($col_idx);

            if ($valores['programado'] > 0) {
                $sheet->setCellValue($col_letter . $fila_p, $valores['programado']);
                $sheet->getStyle($col_letter . $fila_p)
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if ($valores['ejecutado'] > 0) {
                $sheet->setCellValue($col_letter . $fila_e, $valores['ejecutado']);
                $sheet->getStyle($col_letter . $fila_e)
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
    }

    // PASO 13: Metadata
    $spreadsheet->getProperties()
                ->setCreator('VerdenCore - Mantenimientos TI')
                ->setLastModifiedBy($_SESSION['nombre_completo'] ?? 'Sistemas')
                ->setTitle('Programa Mensual ' . $MESES_ES[$mes] . ' ' . $anio)
                ->setSubject('Programa Mensual de Mantenimiento Preventivo de TI')
                ->setDescription('Tipo: ' . ucfirst($tipo_mantenimiento) . ' | Mes: ' . $MESES_ES[$mes] . ' ' . $anio);

    $sheet->setSelectedCell('A1');

    // =====================================================================
    // GUARDAR EN ARCHIVO TEMPORAL Y APLICAR PARCHE ZIP
    // =====================================================================
    $archivo_temporal = tempnam(sys_get_temp_dir(), 'pmant_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->setIncludeCharts(true);
    $writer->save($archivo_temporal);

    // Aplicar parche ZIP: reinyectar drawings con shapes EJECUTÓ/VALIDÓ
    aplicarParcheZip($archivo_temporal, $drawings_originales, $FILA_INSERT, $FILAS_EXTRA);

    // =====================================================================
    // ENVIAR ARCHIVO
    // =====================================================================
    $tipo_label = ($tipo_mantenimiento === 'fisico') ? 'Fisico' : 'Logico';
    $nombre_archivo = "Programa_Mensual_TI_{$tipo_label}_{$MESES_ES[$mes]}_{$anio}.xlsx";

    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Length: ' . filesize($archivo_temporal));
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Pragma: public');

    readfile($archivo_temporal);
    @unlink($archivo_temporal);
    exit;

} catch (Exception $e) {
    error_log("Error generando Programa Mensual: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    establecer_alerta('error', 'Error al generar el reporte: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// HELPERS
// =====================================================================

/**
 * Normaliza un texto para hacer matching:
 * minusculas, sin acentos, espacios -> guiones bajos
 */
function normalizarTexto($txt) {
    if ($txt === null) return '';
    $txt = mb_strtolower(trim($txt), 'UTF-8');
    $reemplazos = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n',
        ' '=>'_', '-'=>'_'
    ];
    $txt = strtr($txt, $reemplazos);
    $txt = preg_replace('/[^a-z0-9_]/', '', $txt);
    $txt = preg_replace('/_+/', '_', $txt);
    return trim($txt, '_');
}

/**
 * Extrae los archivos de drawing y media originales de la plantilla XLSX.
 * Estos archivos contienen las shapes (textboxes EJECUTÓ/VALIDÓ) que
 * PhpSpreadsheet descarta al re-guardar.
 *
 * @return array<string, string>  nombre_archivo => contenido_binario
 */
function extraerDrawingsOriginales($ruta_plantilla) {
    $drawings = [];
    $zip = new ZipArchive();
    if ($zip->open($ruta_plantilla) !== true) {
        return $drawings;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nombre = $zip->getNameIndex($i);
        if (strpos($nombre, 'xl/drawings/') === 0
            || strpos($nombre, 'xl/media/') === 0) {
            $drawings[$nombre] = $zip->getFromIndex($i);
        }
    }

    $zip->close();
    return $drawings;
}

/**
 * Aplica el "parche ZIP" al archivo generado por PhpSpreadsheet:
 *   1. Reemplaza xl/drawings/drawing1.xml con el original (con shapes),
 *      ajustando las referencias de fila para los shapes que estan
 *      despues de la fila donde insertamos.
 *   2. Restaura xl/drawings/_rels/drawing1.xml.rels original.
 *   3. Agrega de vuelta xl/media/imageN.jpeg (logo).
 *
 * NOTA: NO se restaura xl/worksheets/_rels/sheet1.xml.rels porque ese lo
 * regenera PhpSpreadsheet con los IDs correctos para apuntar al drawing.
 */
function aplicarParcheZip($ruta_archivo, $drawings_originales, $fila_corte, $offset) {
    if (empty($drawings_originales)) return;

    $ruta_temporal = $ruta_archivo . '.patch';

    $zip_in = new ZipArchive();
    if ($zip_in->open($ruta_archivo) !== true) {
        throw new Exception('No se pudo abrir el archivo para parchear.');
    }

    $zip_out = new ZipArchive();
    if ($zip_out->open($ruta_temporal, ZipArchive::CREATE) !== true) {
        $zip_in->close();
        throw new Exception('No se pudo crear el archivo temporal.');
    }

    $nombres_existentes = [];

    // Copiar archivos del ZIP original, reemplazando los necesarios
    for ($i = 0; $i < $zip_in->numFiles; $i++) {
        $nombre = $zip_in->getNameIndex($i);
        $nombres_existentes[] = $nombre;
        $contenido = $zip_in->getFromIndex($i);

        if ($nombre === 'xl/drawings/drawing1.xml') {
            // Reemplazar con el original ajustado
            if (isset($drawings_originales[$nombre])) {
                $contenido = ajustarFilasDrawing($drawings_originales[$nombre], $fila_corte, $offset);
            }
        } elseif ($nombre === 'xl/drawings/_rels/drawing1.xml.rels') {
            // Restaurar rels del drawing
            if (isset($drawings_originales[$nombre])) {
                $contenido = $drawings_originales[$nombre];
            }
        }

        $zip_out->addFromString($nombre, $contenido);
    }

    // Agregar archivos faltantes (image1.jpeg, etc.)
    foreach ($drawings_originales as $nombre => $contenido) {
        if (!in_array($nombre, $nombres_existentes)) {
            if ($nombre === 'xl/drawings/drawing1.xml') {
                $contenido = ajustarFilasDrawing($contenido, $fila_corte, $offset);
            }
            $zip_out->addFromString($nombre, $contenido);
        }
    }

    $zip_in->close();
    $zip_out->close();

    // Reemplazar archivo original con el parcheado
    if (!@rename($ruta_temporal, $ruta_archivo)) {
        @copy($ruta_temporal, $ruta_archivo);
        @unlink($ruta_temporal);
    }
}

/**
 * Ajusta las referencias de fila en el XML del drawing.
 * Las filas en drawing XML estan 0-indexed (fila Excel 1 = XML 0).
 * Las shapes ancladas a filas >= (fila_corte - 1) deben sumarles offset.
 *
 * @param string $xml         Contenido XML del drawing
 * @param int    $fila_corte  Numero de fila Excel (1-indexed) a partir de la cual ajustar
 * @param int    $offset      Cantidad de filas a sumar
 * @return string             XML modificado
 */
function ajustarFilasDrawing($xml, $fila_corte, $offset) {
    $fila_corte_xml = $fila_corte - 1;

    return preg_replace_callback(
        '/<xdr:row>(\d+)<\/xdr:row>/',
        function ($m) use ($fila_corte_xml, $offset) {
            $n = (int) $m[1];
            if ($n >= $fila_corte_xml) {
                return '<xdr:row>' . ($n + $offset) . '</xdr:row>';
            }
            return $m[0];
        },
        $xml
    );
}