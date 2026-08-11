<?php
/**
 * Exportar Reporte de Mantenimientos Preventivos (FR-TI-05)
 * dashboard/maestro/exportar_reporte_mantenimientos.php
 *
 * v1.6 - AUTO-TRIM de firmas:
 *        - Detecta automaticamente el bounding box del contenido
 *          (canal alpha o pixeles no-blancos)
 *        - Recorta los espacios vacios alrededor de la firma
 *        - Aplica padding 5% para no cortar el trazo
 *        - Resultado: firmas centradas, proporcionadas y profesionales
 *
 * v1.5 - Calcula tamano que cabe en la celda I respetando proporcion
 * v1.4 - Fix critico de regex de captura del drawing generado
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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
$fecha_desde = trim($_POST['fecha_desde'] ?? '');
$fecha_hasta = trim($_POST['fecha_hasta'] ?? '');
$tipo_filtro = trim($_POST['tipo_mantenimiento'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    establecer_alerta('error', 'Fecha "Desde" inválida.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    establecer_alerta('error', 'Fecha "Hasta" inválida.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if (strtotime($fecha_desde) > strtotime($fecha_hasta)) {
    establecer_alerta('error', 'La fecha "Desde" no puede ser posterior a "Hasta".');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}
if ($tipo_filtro && !in_array($tipo_filtro, ['fisico', 'logico'])) {
    establecer_alerta('error', 'Tipo de mantenimiento inválido.');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// LOCALIZAR PLANTILLA
// =====================================================================
$ruta_plantilla = __DIR__ . '/../../plantillas_reportes/Reporte_de_Mantenimientos_preventivos_de_TI.xlsx';

if (!file_exists($ruta_plantilla)) {
    establecer_alerta('error', 'Plantilla no encontrada: plantillas_reportes/Reporte_de_Mantenimientos_preventivos_de_TI.xlsx');
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// CONSULTAR DATOS
// =====================================================================
$pdo = conectarDB();

$where = ["sm.estado = 'cerrada'"];
$where[] = "DATE(sm.fecha_finalizacion) BETWEEN :desde AND :hasta";

$params = [
    ':desde' => $fecha_desde,
    ':hasta' => $fecha_hasta,
];

if ($tipo_filtro) {
    $where[] = "sm.tipo_mantenimiento = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT 
        sm.id,
        sm.folio,
        sm.tipo_mantenimiento,
        sm.fecha_atencion,
        sm.fecha_finalizacion,
        sm.firma_usuario,
        sm.descripcion_solucion,
        u.nombre_completo  AS usuario_nombre,
        d.nombre           AS depto_nombre,
        ie.hostname        AS equipo_hostname,
        ie.codigo_interno  AS equipo_codigo
    FROM solicitudes_mantenimiento_ti sm
    LEFT JOIN usuarios u           ON sm.usuario_id = u.id
    LEFT JOIN departamentos d      ON sm.departamento_id = d.id
    LEFT JOIN inventario_equipos ie ON sm.equipo_id = ie.id
    WHERE {$where_sql}
    ORDER BY sm.fecha_finalizacion ASC, sm.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_registros = count($registros);

// =====================================================================
// EXTRAER DRAWINGS Y MEDIA ORIGINALES
// =====================================================================
$drawings_originales = extraerDrawingsOriginales($ruta_plantilla);

// =====================================================================
// CARGAR PLANTILLA Y TRANSFORMAR
// =====================================================================
$archivos_temporales_firmas = [];

try {
    $spreadsheet = IOFactory::load($ruta_plantilla);

    $hoja = $spreadsheet->getSheetByName('Hoja1');
    if (!$hoja) {
        throw new Exception('No se encontró la hoja "Hoja1" en la plantilla.');
    }
    $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($hoja));
    $sheet = $spreadsheet->getActiveSheet();

    $FILA_INICIO_DATOS = 8;
    $FILAS_BASE        = 30;
    $FILA_FOOTER_FIN   = 38;

    $filas_extra = 0;
    if ($total_registros > $FILAS_BASE) {
        $filas_extra = $total_registros - $FILAS_BASE;

        $merges_a_desplazar = [];
        $merges_actuales = $sheet->getMergeCells();
        foreach ($merges_actuales as $rangoMerge => $_) {
            $coords = Coordinate::splitRange($rangoMerge);
            list($cellStart, $cellEnd) = $coords[0];
            $startCol = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellStart));
            $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);
            $endCol   = Coordinate::columnIndexFromString(preg_replace('/[0-9]/', '', $cellEnd));
            $endRow   = (int) preg_replace('/[A-Z]/', '', $cellEnd);

            if ($startRow >= $FILA_FOOTER_FIN) {
                $merges_a_desplazar[] = [$startCol, $startRow, $endCol, $endRow];
            }
        }

        foreach ($merges_actuales as $rangoMerge => $_) {
            $coords = Coordinate::splitRange($rangoMerge);
            list($cellStart, $_) = $coords[0];
            $startRow = (int) preg_replace('/[A-Z]/', '', $cellStart);
            if ($startRow >= $FILA_FOOTER_FIN) {
                try { $sheet->unmergeCells($rangoMerge); } catch (Exception $e) {}
            }
        }

        $altura_dato_modelo = $sheet->getRowDimension(37)->getRowHeight();
        if ($altura_dato_modelo < 0 || $altura_dato_modelo === null) $altura_dato_modelo = 19.5;

        $sheet->insertNewRowBefore($FILA_FOOTER_FIN, $filas_extra);

        foreach ($merges_a_desplazar as list($minCol, $minRow, $maxCol, $maxRow)) {
            $rango = Coordinate::stringFromColumnIndex($minCol) . ($minRow + $filas_extra) . ':'
                   . Coordinate::stringFromColumnIndex($maxCol) . ($maxRow + $filas_extra);
            try { $sheet->mergeCells($rango); } catch (Exception $e) {}
        }

        for ($i = 0; $i < $filas_extra; $i++) {
            $fila_destino = $FILA_FOOTER_FIN + $i;
            $sheet->getRowDimension($fila_destino)->setRowHeight($altura_dato_modelo);

            for ($col = 1; $col <= 12; $col++) {
                $col_letter = Coordinate::stringFromColumnIndex($col);
                $sheet->duplicateStyle(
                    $sheet->getStyle($col_letter . '37'),
                    $col_letter . $fila_destino
                );
            }
            $sheet->setCellValue('B' . $fila_destino, $FILAS_BASE + $i + 1);
        }
    }

    // =====================================================================
    // LLENAR DATOS
    // =====================================================================
    foreach ($registros as $i => $r) {
        $fila = $FILA_INICIO_DATOS + $i;

        $id_equipo = $r['equipo_hostname'] ?: $r['equipo_codigo'] ?: '—';

        if ($tipo_filtro) {
            $tipo_label = ($tipo_filtro === 'fisico') ? 'Físico' : 'Lógico';
        } else {
            $tipo_label = ($r['tipo_mantenimiento'] === 'fisico') ? 'Físico' : 'Lógico';
        }

        $tiempo_total = calcularTiempoTotal($r['fecha_atencion'], $r['fecha_finalizacion']);
        $hallazgos = $r['descripcion_solucion'] ?: '';

        $sheet->setCellValue('B' . $fila, $i + 1);
        $sheet->setCellValue('C' . $fila, $id_equipo);
        $sheet->setCellValue('D' . $fila, $r['usuario_nombre'] ?? '—');
        $sheet->setCellValue('E' . $fila, $r['depto_nombre'] ?? '—');
        $sheet->setCellValue('F' . $fila, $tipo_label);
        $sheet->setCellValue('G' . $fila, date('d/m/Y', strtotime($r['fecha_finalizacion'])));
        $sheet->setCellValue('H' . $fila, $tiempo_total);
        $sheet->setCellValue('J' . $fila, $hallazgos);

        $sheet->getRowDimension($fila)->setRowHeight(60);

        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'J'] as $col) {
            $sheet->getStyle($col . $fila)
                  ->getAlignment()
                  ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                  ->setVertical(Alignment::VERTICAL_CENTER)
                  ->setWrapText(true);
        }

        // ============================================================
        // INSERTAR FIRMA CON AUTO-TRIM Y CENTRADO
        // ============================================================
        if (!empty($r['firma_usuario'])) {
            $ruta_firma = guardarFirmaTemp($r['firma_usuario'], $r['id']);
            if ($ruta_firma) {
                $archivos_temporales_firmas[] = $ruta_firma;

                // *** v1.6: Auto-trim de la firma ***
                $ruta_firma_trim = recortarEspacioVacio($ruta_firma);
                if ($ruta_firma_trim !== $ruta_firma) {
                    // Si se creo una version recortada, usarla y agregar a temporales
                    $archivos_temporales_firmas[] = $ruta_firma_trim;
                }

                try {
                    $drawing = new Drawing();
                    $drawing->setName('Firma_' . $r['id']);
                    $drawing->setDescription('Firma del usuario');
                    $drawing->setPath($ruta_firma_trim);
                    $drawing->setCoordinates('I' . $fila);

                    // Calcular tamano que cabe en la celda I respetando proporcion
                    $tam = @getimagesize($ruta_firma_trim);
                    if ($tam !== false) {
                        $img_w = (int) $tam[0];
                        $img_h = (int) $tam[1];

                        $max_ancho = 150;
                        $max_alto  = 50;

                        $escala = min($max_ancho / $img_w, $max_alto / $img_h);
                        $nuevo_w = max(1, (int) round($img_w * $escala));
                        $nuevo_h = max(1, (int) round($img_h * $escala));

                        $drawing->setWidthAndHeight($nuevo_w, $nuevo_h);

                        // Centrar en la celda
                        $offset_x = max(2, (int) round((165 - $nuevo_w) / 2));
                        $offset_y = max(2, (int) round((80  - $nuevo_h) / 2));
                        $drawing->setOffsetX($offset_x);
                        $drawing->setOffsetY($offset_y);
                    } else {
                        $drawing->setHeight(40);
                        $drawing->setOffsetX(8);
                        $drawing->setOffsetY(20);
                    }

                    $drawing->setWorksheet($sheet);
                } catch (Exception $e) {
                    error_log("[Firma] Solicitud {$r['id']}: ERROR drawing - " . $e->getMessage());
                }
            }
        }
    }

    // =====================================================================
    // METADATA
    // =====================================================================
    $tipo_label_titulo = $tipo_filtro
        ? ' - ' . ($tipo_filtro === 'fisico' ? 'Físico' : 'Lógico')
        : '';

    $spreadsheet->getProperties()
                ->setCreator('VerdenCore - Mantenimientos TI')
                ->setLastModifiedBy($_SESSION['nombre_completo'] ?? 'Sistemas')
                ->setTitle('Reporte de Mantenimientos Preventivos' . $tipo_label_titulo)
                ->setSubject('Mantenimientos cerrados ' . $fecha_desde . ' a ' . $fecha_hasta)
                ->setDescription('Total registros: ' . $total_registros);

    $sheet->setSelectedCell('A1');

    // =====================================================================
    // GUARDAR Y APLICAR PARCHE ZIP
    // =====================================================================
    $archivo_temporal = tempnam(sys_get_temp_dir(), 'rmant_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($archivo_temporal);

    aplicarParcheZip($archivo_temporal, $drawings_originales, $FILA_FOOTER_FIN, $filas_extra);

    // =====================================================================
    // ENVIAR ARCHIVO
    // =====================================================================
    $tipo_archivo_label = $tipo_filtro ? '_' . ucfirst($tipo_filtro) : '';
    $nombre_archivo = "Reporte_Mantenimientos_TI{$tipo_archivo_label}_{$fecha_desde}_a_{$fecha_hasta}.xlsx";

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

    foreach ($archivos_temporales_firmas as $ruta) {
        @unlink($ruta);
    }
    exit;

} catch (Exception $e) {
    foreach ($archivos_temporales_firmas as $ruta) {
        @unlink($ruta);
    }
    error_log("Error generando Reporte: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    establecer_alerta('error', 'Error al generar el reporte: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/maestro/index.php');
    exit;
}

// =====================================================================
// HELPERS
// =====================================================================

function calcularTiempoTotal($atencion, $finalizacion) {
    if (empty($atencion) || empty($finalizacion)) return '—';

    try {
        $inicio = new DateTime($atencion);
        $fin    = new DateTime($finalizacion);
        $diff   = $inicio->diff($fin);
    } catch (Exception $e) {
        return '—';
    }

    if ($diff->days >= 1) {
        return $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
    }

    $partes = [];
    if ($diff->h > 0) $partes[] = $diff->h . ' h';
    if ($diff->i > 0) $partes[] = $diff->i . ' min';
    if (empty($partes)) $partes[] = '< 1 min';

    return implode(' ', $partes);
}

function guardarFirmaTemp($firma_base64, $id_solicitud) {
    if (empty($firma_base64)) return null;

    $firma_normalizada = preg_replace('/\s+/', '', trim($firma_base64));

    $datos_binarios = null;
    $extension = 'png';

    if (preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,(.+)$/i', $firma_normalizada, $m)) {
        $extension = (strtolower($m[1]) === 'jpeg') ? 'jpg' : strtolower($m[1]);
        $datos_binarios = base64_decode($m[2], true);
    } elseif (preg_match('/^data:image\/[^;]+;base64,/i', $firma_normalizada)) {
        $contenido = preg_replace('/^data:image\/[^;]+;base64,/i', '', $firma_normalizada);
        $datos_binarios = base64_decode($contenido, true);
    } elseif (preg_match('/^[A-Za-z0-9+\/]+=*$/', $firma_normalizada)) {
        $datos_binarios = base64_decode($firma_normalizada, true);
    } else {
        if (strpos($firma_normalizada, ',') !== false) {
            $partes = explode(',', $firma_normalizada, 2);
            $candidato = end($partes);
            $datos_binarios = base64_decode($candidato, true);
        }
    }

    if ($datos_binarios === false || strlen($datos_binarios) < 100) {
        return null;
    }

    $header_hex = strtoupper(bin2hex(substr($datos_binarios, 0, 8)));
    if (strpos($header_hex, '89504E47') === 0) {
        $extension = 'png';
    } elseif (strpos($header_hex, 'FFD8FF') === 0) {
        $extension = 'jpg';
    } elseif (strpos($header_hex, '47494638') === 0) {
        $extension = 'gif';
    }

    $ruta_base = tempnam(sys_get_temp_dir(), 'firma_');
    if ($ruta_base === false) return null;

    $ruta_final = $ruta_base . '.' . $extension;

    if (!@rename($ruta_base, $ruta_final)) {
        if (@copy($ruta_base, $ruta_final)) {
            @unlink($ruta_base);
        } else {
            $ruta_final = $ruta_base;
        }
    }

    if (file_put_contents($ruta_final, $datos_binarios) === false) {
        @unlink($ruta_final);
        return null;
    }

    if (@getimagesize($ruta_final) === false) {
        @unlink($ruta_final);
        return null;
    }

    return $ruta_final;
}

/**
 * AUTO-TRIM de firma: detecta el bounding box del contenido (alpha o
 * pixeles no-blancos) y recorta los espacios vacios alrededor.
 *
 * Si la imagen ya esta bien recortada (espacio vacio < 10%) no hace nada.
 * En caso contrario, crea una version recortada con padding 5% y la guarda
 * en una nueva ruta. Devuelve la ruta nueva o la original.
 *
 * Requiere extension GD (incluida por defecto en XAMPP).
 */
function recortarEspacioVacio($ruta_origen) {
    if (!extension_loaded('gd')) {
        return $ruta_origen;
    }

    $info = @getimagesize($ruta_origen);
    if ($info === false) return $ruta_origen;

    $img = null;
    switch ($info[2]) {
        case IMAGETYPE_PNG:
            $img = @imagecreatefrompng($ruta_origen);
            break;
        case IMAGETYPE_JPEG:
            $img = @imagecreatefromjpeg($ruta_origen);
            break;
        case IMAGETYPE_GIF:
            $img = @imagecreatefromgif($ruta_origen);
            break;
    }
    if (!$img) return $ruta_origen;

    $w = imagesx($img);
    $h = imagesy($img);

    $tiene_alpha = ($info[2] === IMAGETYPE_PNG);

    // Detectar bounding box del contenido
    $top = $h;
    $bottom = -1;
    $left = $w;
    $right = -1;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($img, $x, $y);

            $tiene_contenido = false;
            if ($tiene_alpha) {
                // GD: 0 = opaco, 127 = transparente
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 120) {
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    if ($alpha < 100 || $r < 240 || $g < 240 || $b < 240) {
                        $tiene_contenido = true;
                    }
                }
            } else {
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r < 240 || $g < 240 || $b < 240) {
                    $tiene_contenido = true;
                }
            }

            if ($tiene_contenido) {
                if ($y < $top) $top = $y;
                if ($y > $bottom) $bottom = $y;
                if ($x < $left) $left = $x;
                if ($x > $right) $right = $x;
            }
        }
    }

    // Si no hay contenido, devolver original
    if ($bottom < 0 || $right < 0) {
        imagedestroy($img);
        return $ruta_origen;
    }

    $bbox_w = $right - $left + 1;
    $bbox_h = $bottom - $top + 1;

    // Si ya esta bien recortada, no hacer nada
    $espacio_horiz = 1 - ($bbox_w / $w);
    $espacio_vert  = 1 - ($bbox_h / $h);
    if ($espacio_horiz < 0.10 && $espacio_vert < 0.10) {
        imagedestroy($img);
        return $ruta_origen;
    }

    // Padding 5% (minimo 5 px) para no cortar el trazo
    $pad_x = max(5, (int) round($bbox_w * 0.05));
    $pad_y = max(5, (int) round($bbox_h * 0.05));

    $crop_left = max(0, $left - $pad_x);
    $crop_top  = max(0, $top  - $pad_y);
    $crop_w    = min($w - $crop_left, $bbox_w + 2 * $pad_x);
    $crop_h    = min($h - $crop_top,  $bbox_h + 2 * $pad_y);

    $cropped = imagecrop($img, [
        'x'      => $crop_left,
        'y'      => $crop_top,
        'width'  => $crop_w,
        'height' => $crop_h,
    ]);

    if ($cropped === false) {
        imagedestroy($img);
        return $ruta_origen;
    }
    imagedestroy($img);

    // Crear PNG final con fondo blanco (mejor para Excel)
    $w_new = imagesx($cropped);
    $h_new = imagesy($cropped);
    $final = imagecreatetruecolor($w_new, $h_new);
    $blanco = imagecolorallocate($final, 255, 255, 255);
    imagefill($final, 0, 0, $blanco);
    imagecopy($final, $cropped, 0, 0, 0, 0, $w_new, $h_new);

    imagedestroy($cropped);

    $nueva_ruta = $ruta_origen . '_trimmed.png';
    if (!@imagepng($final, $nueva_ruta)) {
        imagedestroy($final);
        return $ruta_origen;
    }
    imagedestroy($final);

    if (!file_exists($nueva_ruta) || filesize($nueva_ruta) < 100) {
        return $ruta_origen;
    }

    return $nueva_ruta;
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

    $tiene_logo_generado = (strpos($contenido_generado_xdr, '<xdr:pic>') !== false);

    $anchors_pics_originales_xml = '';
    if (!$tiene_logo_generado) {
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
    if (!$tiene_logo_generado) {
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

    if (!$tiene_logo_generado) {
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