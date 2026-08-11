<?php
/**
 * Exportar Programa Anual de Mantenimientos Preventivos (FR-TI-04)
 * dashboard/maestro/exportar_programa_anual.php
 *
 * v1.3 - Tipos de equipo con valores EXACTOS del inventario:
 *        - Cómputo: all_in_one, pc, laptop, computadora
 *        - Impresoras: impresora
 *
 * v1.2 - Cabecera correcta (D6 fecha, H6 tipo) + altura filas 30pts
 * v1.1 - Fix campo 'ubicacion'
 *
 * Filtros POST:
 *   - anio          (4 digitos)
 *   - tipo_archivo  ('computo_fisico' | 'computo_logico' | 'impresoras')
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
$anio = (int) ($_POST['anio'] ?? 0);
$tipo_archivo = trim($_POST['tipo_archivo'] ?? '');

if ($anio < 2020 || $anio > 2100) {
    establecer_alerta('error', 'Año inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if (!in_array($tipo_archivo, ['computo_fisico', 'computo_logico', 'impresoras'])) {
    establecer_alerta('error', 'Tipo de archivo inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// CONFIGURACION POR TIPO DE ARCHIVO
// =====================================================================
$CONFIG = [
    'computo_fisico' => [
        'label'        => 'Cómputo Físico',
        'nombre_hoja'  => 'Cómputo - Físico',
        'descripcion'  => 'Revisión, limpieza del equipo (pantalla, uso e integridad UA, RAM/cache, actualizaciones, etc.).',
        'tipos_equipo' => ['all_in_one', 'pc', 'laptop', 'computadora'],
        'tipos_mant'   => ['fisico'],
    ],
    'computo_logico' => [
        'label'        => 'Cómputo Lógico',
        'nombre_hoja'  => 'Cómputo - Lógico',
        'descripcion'  => 'Revisión de drivers y actualizaciones. Respaldo de información crítica. Revisión de accesos y cuentas correctas (correos, licencias, etc.).',
        'tipos_equipo' => ['all_in_one', 'pc', 'laptop', 'computadora'],
        'tipos_mant'   => ['logico'],
    ],
    'impresoras' => [
        'label'        => 'Impresoras',
        'nombre_hoja'  => 'Impresoras',
        'descripcion'  => 'Revisión, limpieza del equipo (niveles de tóner / tinta, rodillos, exterior, inyectores, etc.).',
        'tipos_equipo' => ['impresora'],
        'tipos_mant'   => ['fisico', 'logico'],
    ],
];

$conf = $CONFIG[$tipo_archivo];

// Mapping tipo_equipo -> caracteristica especifica (valores EXACTOS del inventario)
$CARACTERISTICAS = [
    'pc'          => 'PC gabinete armado',
    'computadora' => 'PC gabinete armado',
    'all_in_one'  => 'PC AIO',
    'laptop'      => 'PC laptop',
    'impresora'   => 'Impresora monocromática, toner',
];

// =====================================================================
// LOCALIZAR PLANTILLA
// =====================================================================
$ruta_plantilla = __DIR__ . '/../../plantillas_reportes/Programa_anual_de_mantenimientos_preventivos_de_TI.xlsx';

if (!file_exists($ruta_plantilla)) {
    establecer_alerta('error', 'Plantilla no encontrada: plantillas_reportes/Programa_anual_de_mantenimientos_preventivos_de_TI.xlsx');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// CONSULTAR DATOS
// =====================================================================
$pdo = conectarDB();

$ph_tipos_eq   = implode(',', array_fill(0, count($conf['tipos_equipo']), '?'));
$ph_tipos_mant = implode(',', array_fill(0, count($conf['tipos_mant']),   '?'));

$sql = "
    SELECT 
        ie.id              AS equipo_id,
        ie.hostname        AS hostname,
        ie.codigo_interno  AS codigo_interno,
        ie.tipo_equipo     AS tipo_equipo,
        ie.ubicacion       AS ubicacion,
        sm.tipo_mantenimiento,
        sm.fecha_deseada,
        sm.fecha_finalizacion,
        sm.estado
    FROM inventario_equipos ie
    INNER JOIN solicitudes_mantenimiento_ti sm ON sm.equipo_id = ie.id
    WHERE ie.tipo_equipo IN ({$ph_tipos_eq})
      AND sm.tipo_mantenimiento IN ({$ph_tipos_mant})
      AND sm.estado != 'cancelada'
      AND (
            (YEAR(sm.fecha_deseada) = ?)
         OR (YEAR(sm.fecha_finalizacion) = ? AND sm.estado IN ('finalizada','cerrada'))
      )
    ORDER BY ie.hostname ASC, sm.fecha_deseada ASC
";

$params = array_merge(
    $conf['tipos_equipo'],
    $conf['tipos_mant'],
    [$anio, $anio]
);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// AGRUPAR POR EQUIPO
// =====================================================================
$equipos = [];

foreach ($rows as $r) {
    $eid = (int) $r['equipo_id'];

    if (!isset($equipos[$eid])) {
        $equipos[$eid] = [
            'hostname'    => $r['hostname'] ?: $r['codigo_interno'] ?: '—',
            'ubicacion'   => $r['ubicacion'] ?? '',
            'tipo_equipo' => strtolower(trim($r['tipo_equipo'] ?? '')),
            'programados' => [],
            'realizados'  => [],
        ];
    }

    if (!empty($r['fecha_deseada'])) {
        $ts = strtotime($r['fecha_deseada']);
        if ($ts !== false) {
            $a = (int) date('Y', $ts);
            $m = (int) date('n', $ts);
            if ($a === $anio && $m >= 1 && $m <= 12) {
                $equipos[$eid]['programados'][$m] = true;
            }
        }
    }

    if (!empty($r['fecha_finalizacion']) && in_array($r['estado'], ['finalizada', 'cerrada'])) {
        $ts = strtotime($r['fecha_finalizacion']);
        if ($ts !== false) {
            $a = (int) date('Y', $ts);
            $m = (int) date('n', $ts);
            if ($a === $anio && $m >= 1 && $m <= 12) {
                $equipos[$eid]['realizados'][$m] = true;
            }
        }
    }
}

// Filtrar: solo equipos con al menos 1 programado
$equipos = array_filter($equipos, function ($eq) {
    return !empty($eq['programados']);
});

$equipos = array_values($equipos);
$total_equipos = count($equipos);

// =====================================================================
// EXTRAER DRAWINGS Y MEDIA ORIGINALES
// =====================================================================
$drawings_originales = extraerDrawingsOriginales($ruta_plantilla);

// =====================================================================
// CARGAR PLANTILLA Y TRANSFORMAR
// =====================================================================
try {
    $spreadsheet = IOFactory::load($ruta_plantilla);
    $sheet = $spreadsheet->getSheet(0);

    // Renombrar hoja
    $sheet->setTitle($conf['nombre_hoja']);

    $FILA_INICIO_DATOS = 9;
    $EQUIPOS_BASE      = 15;
    $FILA_FOOTER       = 39;

    // Insertar filas si > 15 equipos
    $filas_extra = 0;
    if ($total_equipos > $EQUIPOS_BASE) {
        $filas_extra = ($total_equipos - $EQUIPOS_BASE) * 2;
        insertarFilasExtra($sheet, $FILA_FOOTER, $filas_extra);
    }

    // ============================================================
    // CABECERAS (fila 6)
    // ============================================================
    // D6 (sin merge): Fecha de actualizacion
    $sheet->setCellValue('D6', date('d/m/Y'));

    // H6 (merged H6:M6): Tipo de mantenimiento (label "Descripción:" se mantiene)
    $sheet->setCellValue('H6', $conf['label']);

    // Q6 (merged Q6:R6): Año
    $sheet->setCellValue('Q6', $anio);

    // ============================================================
    // LLENAR DATOS
    // ============================================================
    $VERDE_RGB = '92D050';
    $GRIS_RGB  = 'A6A6A6';

    foreach ($equipos as $i => $eq) {
        $fila_p = $FILA_INICIO_DATOS + ($i * 2);
        $fila_r = $fila_p + 1;

        $caracteristica = $CARACTERISTICAS[$eq['tipo_equipo']] ?? 'Equipo informático';

        $sheet->setCellValue('B' . $fila_p, $eq['hostname']);
        $sheet->setCellValue('C' . $fila_p, $eq['ubicacion'] ?: '—');
        $sheet->setCellValue('D' . $fila_p, $caracteristica);
        $sheet->setCellValue('E' . $fila_p, $conf['descripcion']);

        // Altura aumentada para wrap del texto de descripcion
        $sheet->getRowDimension($fila_p)->setRowHeight(30);
        $sheet->getRowDimension($fila_r)->setRowHeight(30);

        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->getStyle($col . $fila_p)
                  ->getAlignment()
                  ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                  ->setVertical(Alignment::VERTICAL_CENTER)
                  ->setWrapText(true);
        }

        // Pintar meses programados (VERDE)
        foreach (array_keys($eq['programados']) as $mes) {
            $col = 8 + $mes;
            $col_letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($col_letter . $fila_p)
                  ->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()
                  ->setRGB($VERDE_RGB);
        }

        // Pintar meses realizados (GRIS)
        foreach (array_keys($eq['realizados']) as $mes) {
            $col = 8 + $mes;
            $col_letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($col_letter . $fila_r)
                  ->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()
                  ->setRGB($GRIS_RGB);
        }
    }

    // ============================================================
    // METADATA
    // ============================================================
    $spreadsheet->getProperties()
                ->setCreator('VerdenCore - Mantenimientos TI')
                ->setLastModifiedBy($_SESSION['nombre_completo'] ?? 'Sistemas')
                ->setTitle('Programa Anual - ' . $conf['label'] . ' ' . $anio)
                ->setSubject('FR-TI-04 Programa Anual')
                ->setDescription('Total equipos: ' . $total_equipos);

    $sheet->setSelectedCell('A1');

    // ============================================================
    // GUARDAR Y APLICAR PARCHE ZIP
    // ============================================================
    $archivo_temporal = tempnam(sys_get_temp_dir(), 'panual_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($archivo_temporal);

    aplicarParcheZip($archivo_temporal, $drawings_originales, $FILA_FOOTER, $filas_extra);

    // ============================================================
    // ENVIAR ARCHIVO
    // ============================================================
    $nombre_archivo = 'Programa_Anual_' . str_replace(' ', '_', $conf['label']) . '_' . $anio . '.xlsx';
    $nombre_archivo = str_replace(['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'],
                                  ['a','e','i','o','u','n','A','E','I','O','U','N'],
                                  $nombre_archivo);

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
    error_log("Error generando Programa Anual: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    establecer_alerta('error', 'Error al generar el reporte: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// HELPERS
// =====================================================================

function insertarFilasExtra($sheet, $fila_footer, $filas_extra) {
    if ($filas_extra <= 0) return;

    $merges_a_desplazar = [];
    foreach ($sheet->getMergeCells() as $rangoMerge => $_) {
        $coords = Coordinate::splitRange($rangoMerge);
        list($cellStart, $cellEnd) = $coords[0];
        $startCol = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellStart));
        $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);
        $endCol   = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellEnd));
        $endRow   = (int) preg_replace('/[A-Z]/', '', $cellEnd);

        if ($startRow >= $fila_footer) {
            $merges_a_desplazar[] = [$startCol, $startRow, $endCol, $endRow];
        }
    }

    foreach ($sheet->getMergeCells() as $rangoMerge => $_) {
        $coords = Coordinate::splitRange($rangoMerge);
        list($cellStart, $_) = $coords[0];
        $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);
        if ($startRow >= $fila_footer) {
            try { $sheet->unmergeCells($rangoMerge); } catch (Exception $e) {}
        }
    }

    $altura_p = $sheet->getRowDimension(9)->getRowHeight();
    $altura_r = $sheet->getRowDimension(10)->getRowHeight();
    if ($altura_p < 0 || $altura_p === null) $altura_p = 15.0;
    if ($altura_r < 0 || $altura_r === null) $altura_r = 15.0;

    $sheet->insertNewRowBefore($fila_footer, $filas_extra);

    foreach ($merges_a_desplazar as list($minCol, $minRow, $maxCol, $maxRow)) {
        $rango = Coordinate::stringFromColumnIndex($minCol) . ($minRow + $filas_extra) . ':'
               . Coordinate::stringFromColumnIndex($maxCol) . ($maxRow + $filas_extra);
        try { $sheet->mergeCells($rango); } catch (Exception $e) {}
    }

    for ($i = 0; $i < $filas_extra; $i++) {
        $fila_destino = $fila_footer + $i;
        $fila_origen  = ($i % 2 === 0) ? 9 : 10;
        $altura_ref   = ($i % 2 === 0) ? $altura_p : $altura_r;

        $sheet->getRowDimension($fila_destino)->setRowHeight($altura_ref);

        for ($col = 1; $col <= 20; $col++) {
            $col_letter = Coordinate::stringFromColumnIndex($col);
            $sheet->duplicateStyle(
                $sheet->getStyle($col_letter . $fila_origen),
                $col_letter . $fila_destino
            );
        }

        $sheet->setCellValue('H' . $fila_destino, $fila_origen === 9 ? 'P' : 'R');
    }

    $num_equipos_extra = (int) ($filas_extra / 2);
    for ($k = 0; $k < $num_equipos_extra; $k++) {
        $fp = $fila_footer + ($k * 2);
        $fe = $fp + 1;
        try { $sheet->mergeCells("B{$fp}:B{$fe}"); } catch (Exception $e) {}
        try { $sheet->mergeCells("C{$fp}:C{$fe}"); } catch (Exception $e) {}
        try { $sheet->mergeCells("D{$fp}:D{$fe}"); } catch (Exception $e) {}
        try { $sheet->mergeCells("E{$fp}:G{$fe}"); } catch (Exception $e) {}
    }
}

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

function aplicarParcheZip($ruta_archivo, $drawings_originales, $fila_corte, $offset) {
    if (empty($drawings_originales)) return;

    $clave_drawing = 'xl/drawings/drawing1.xml';
    $clave_drawing_rels = 'xl/drawings/_rels/drawing1.xml.rels';

    if (!isset($drawings_originales[$clave_drawing])) return;

    $drawing_original = $drawings_originales[$clave_drawing];

    $anchors_shapes = extraerAnchorsConTag($drawing_original, 'sp');
    if (empty($anchors_shapes) && $offset === 0) {
        return;
    }

    if ($offset > 0) {
        $anchors_shapes = array_map(
            function ($a) use ($fila_corte, $offset) {
                return ajustarFilasDrawingXdr($a, $fila_corte, $offset);
            },
            $anchors_shapes
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($ruta_archivo) !== true) {
        throw new Exception('No se pudo abrir el archivo para parchear.');
    }
    $drawing_generado = $zip->getFromName($clave_drawing);
    $rels_generado = $zip->getFromName($clave_drawing_rels);
    $zip->close();

    if ($drawing_generado === false) {
        reinyectarDrawingsCompletos($ruta_archivo, $drawings_originales, $fila_corte, $offset);
        return;
    }

    $contenido_generado = '';
    if (preg_match('/<(?:xdr:)?wsDr[^>]*>(.*)<\/(?:xdr:)?wsDr>/s', $drawing_generado, $m)) {
        $contenido_generado = $m[1];
    }

    $contenido_generado_xdr = convertirAXdr($contenido_generado);

    $tiene_pics_generadas = (strpos($contenido_generado_xdr, '<xdr:pic>') !== false);

    $anchors_pics_originales_xml = '';
    if (!$tiene_pics_generadas) {
        $anchors_pics_originales = extraerAnchorsConTag($drawing_original, 'pic');
        if ($offset > 0) {
            $anchors_pics_originales = array_map(
                function ($a) use ($fila_corte, $offset) {
                    return ajustarFilasDrawingXdr($a, $fila_corte, $offset);
                },
                $anchors_pics_originales
            );
        }
        $anchors_pics_originales_xml = implode("\n", $anchors_pics_originales);
    }

    $drawing_fusionado = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
        . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
        . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . $contenido_generado_xdr
        . $anchors_pics_originales_xml
        . implode("\n", $anchors_shapes)
        . '</xdr:wsDr>';

    $rels_a_usar = $rels_generado;
    if (!$tiene_pics_generadas) {
        $rels_originales = $drawings_originales[$clave_drawing_rels] ?? null;
        $rels_a_usar = fusionarRels($rels_generado, $rels_originales);
    }

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
    for ($i = 0; $i < $zip_in->numFiles; $i++) {
        $nombre = $zip_in->getNameIndex($i);
        $nombres_existentes[] = $nombre;
        $contenido = $zip_in->getFromIndex($i);

        if ($nombre === $clave_drawing) {
            $contenido = $drawing_fusionado;
        } elseif ($nombre === $clave_drawing_rels && $rels_a_usar !== false && $rels_a_usar !== null) {
            $contenido = $rels_a_usar;
        }
        $zip_out->addFromString($nombre, $contenido);
    }

    if (!$tiene_pics_generadas) {
        foreach ($drawings_originales as $nombre => $contenido) {
            if (strpos($nombre, 'xl/media/') === 0 && !in_array($nombre, $nombres_existentes)) {
                $zip_out->addFromString($nombre, $contenido);
            }
        }
    }

    $zip_in->close();
    $zip_out->close();

    if (!@rename($ruta_temporal, $ruta_archivo)) {
        @copy($ruta_temporal, $ruta_archivo);
        @unlink($ruta_temporal);
    }
}

function reinyectarDrawingsCompletos($ruta_archivo, $drawings_originales, $fila_corte, $offset) {
    $ruta_temporal = $ruta_archivo . '.patch';

    $zip_in = new ZipArchive();
    if ($zip_in->open($ruta_archivo) !== true) return;

    $zip_out = new ZipArchive();
    if ($zip_out->open($ruta_temporal, ZipArchive::CREATE) !== true) {
        $zip_in->close();
        return;
    }

    $nombres_existentes = [];
    for ($i = 0; $i < $zip_in->numFiles; $i++) {
        $nombre = $zip_in->getNameIndex($i);
        $nombres_existentes[] = $nombre;
        $contenido = $zip_in->getFromIndex($i);

        if ($nombre === 'xl/drawings/drawing1.xml' && isset($drawings_originales[$nombre])) {
            $contenido = ajustarFilasDrawingXdr($drawings_originales[$nombre], $fila_corte, $offset);
        } elseif ($nombre === 'xl/drawings/_rels/drawing1.xml.rels' && isset($drawings_originales[$nombre])) {
            $contenido = $drawings_originales[$nombre];
        }
        $zip_out->addFromString($nombre, $contenido);
    }

    foreach ($drawings_originales as $nombre => $contenido) {
        if (!in_array($nombre, $nombres_existentes)) {
            if ($nombre === 'xl/drawings/drawing1.xml') {
                $contenido = ajustarFilasDrawingXdr($contenido, $fila_corte, $offset);
            }
            $zip_out->addFromString($nombre, $contenido);
        }
    }

    $zip_in->close();
    $zip_out->close();

    if (!@rename($ruta_temporal, $ruta_archivo)) {
        @copy($ruta_temporal, $ruta_archivo);
        @unlink($ruta_temporal);
    }
}

function extraerAnchorsConTag($xml, $tag) {
    $anchors = [];
    foreach (['twoCellAnchor', 'oneCellAnchor', 'absoluteAnchor'] as $anchor_tag) {
        $pat = '/<xdr:' . $anchor_tag . '(?:\s[^>]*)?>.*?<\/xdr:' . $anchor_tag . '>/s';
        if (preg_match_all($pat, $xml, $matches)) {
            foreach ($matches[0] as $bloque) {
                if (strpos($bloque, '<xdr:' . $tag . ' ') !== false 
                    || strpos($bloque, '<xdr:' . $tag . '>') !== false) {
                    $anchors[] = $bloque;
                }
            }
        }
    }
    return $anchors;
}

function ajustarFilasDrawingXdr($xml, $fila_corte, $offset) {
    if ($offset === 0) return $xml;

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

function convertirAXdr($xml) {
    $tags = [
        'oneCellAnchor', 'twoCellAnchor', 'absoluteAnchor',
        'from', 'to', 'ext',
        'pic', 'sp', 'cxnSp', 'grpSp', 'graphicFrame', 'contentPart',
        'nvPicPr', 'nvSpPr', 'nvCxnSpPr', 'nvGrpSpPr', 'nvGraphicFramePr',
        'cNvPr', 'cNvPicPr', 'cNvSpPr', 'cNvCxnSpPr', 'cNvGrpSpPr', 'cNvGraphicFramePr',
        'blipFill', 'spPr', 'txBody', 'style',
        'col', 'colOff', 'row', 'rowOff',
        'clientData',
    ];
    foreach ($tags as $tag) {
        $xml = preg_replace('/<' . $tag . '([\s\/>])/', '<xdr:' . $tag . '$1', $xml);
        $xml = preg_replace('/<\/' . $tag . '>/', '</xdr:' . $tag . '>', $xml);
    }
    return $xml;
}

function fusionarRels($rels_generado, $rels_original) {
    if (empty($rels_original)) return $rels_generado;
    if (empty($rels_generado)) return $rels_original;

    $rels_gen_list = [];
    if (preg_match_all('/<Relationship\s+[^>]+\/>/', $rels_generado, $m)) {
        $rels_gen_list = $m[0];
    }

    $rels_orig_list = [];
    if (preg_match_all('/<Relationship\s+[^>]+\/>/', $rels_original, $m)) {
        $rels_orig_list = $m[0];
    }

    $max_id = 0;
    foreach ($rels_gen_list as $rel) {
        if (preg_match('/Id="rId(\d+)"/', $rel, $mid)) {
            $max_id = max($max_id, (int)$mid[1]);
        }
    }

    $existing_targets = [];
    foreach ($rels_gen_list as $rel) {
        if (preg_match('/Target="([^"]+)"/', $rel, $mt)) {
            $existing_targets[] = basename($mt[1]);
        }
    }

    foreach ($rels_orig_list as $rel) {
        if (preg_match('/Target="([^"]+)"/', $rel, $mt)) {
            if (in_array(basename($mt[1]), $existing_targets)) {
                continue;
            }
        }
        $max_id++;
        $rel_renumerado = preg_replace('/Id="rId\d+"/', 'Id="rId' . $max_id . '"', $rel);
        $rels_gen_list[] = $rel_renumerado;
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
         . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
         . implode("", $rels_gen_list)
         . '</Relationships>';
}