<?php
/**
 * Descargar archivo XLSX - Bitácora de Aires Acondicionados
 * Ubicación: dashboard/sistemas/bitacora_aires/descargar_bitacora_aires.php
 *
 * Replica el formato exacto de la plantilla original:
 *   - Logo en B2:E4 (espacio reservado, vacío)
 *   - Título "BITÁCORA DE REGISTRO AIRES ACONDICIONADOS" en F2:O4 (Arial 20 bold)
 *   - "Nombre del encargado: (NOMBRE)" en B6:O6
 *   - Cabeceras fila 7 con fill #89DBC6
 *   - Datos desde fila 8 con merges B:C / D:I / N:O
 *   - Firmas insertadas como imágenes (Drawing) en columnas L y N
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/sistemas/bitacora_aires/bitacora_aires_funciones.php';

// Cargar Composer autoload (PhpSpreadsheet)
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// =====================================================================
// SEGURIDAD
// =====================================================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Solo el departamento de Sistemas puede descargar el archivo
if (!ba_es_sistemas()) {
    establecer_alerta('error', 'No tienes permisos para descargar este archivo.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

// =====================================================================
// OBTENER REGISTROS (Sistemas ve TODOS)
// =====================================================================
$registros = ba_obtener_registros($usuario_id);

// =====================================================================
// HELPER: convertir base64 data URL a archivo temporal PNG
// =====================================================================
function ba_firma_a_temp($firma_base64) {
    if (empty($firma_base64)) return null;

    if (preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,(.+)$/i', $firma_base64, $m)) {
        $extension = (strtolower($m[1]) === 'jpeg') ? 'jpg' : strtolower($m[1]);
        $datos     = base64_decode($m[2], true);
    } else {
        return null;
    }

    if ($datos === false || strlen($datos) < 80) return null;

    $tmp = tempnam(sys_get_temp_dir(), 'ba_firma_');
    if ($tmp === false) return null;

    $rutaFinal = $tmp . '.' . $extension;
    @rename($tmp, $rutaFinal);

    if (file_put_contents($rutaFinal, $datos) === false) {
        @unlink($rutaFinal);
        return null;
    }

    if (@getimagesize($rutaFinal) === false) {
        @unlink($rutaFinal);
        return null;
    }

    return $rutaFinal;
}

// Archivos temporales a limpiar al final
$archivos_temporales = [];

// =====================================================================
// CONSTRUIR HOJA
// =====================================================================
try {
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Hoja1');

    // -----------------------------------------------------------------
    // Anchos de columna (replicando plantilla original)
    // -----------------------------------------------------------------
    $sheet->getColumnDimension('A')->setWidth(1.71);
    $sheet->getColumnDimension('B')->setWidth(4.43);
    $sheet->getColumnDimension('C')->setWidth(5.71);
    $sheet->getColumnDimension('D')->setWidth(13.43);
    $sheet->getColumnDimension('E')->setWidth(13.00);
    $sheet->getColumnDimension('F')->setWidth(8.43);
    $sheet->getColumnDimension('G')->setWidth(8.57);
    $sheet->getColumnDimension('H')->setWidth(7.71);
    $sheet->getColumnDimension('I')->setWidth(3.86);
    $sheet->getColumnDimension('J')->setWidth(17.71);
    $sheet->getColumnDimension('K')->setWidth(11.00);
    $sheet->getColumnDimension('L')->setWidth(13.00);
    $sheet->getColumnDimension('M')->setWidth(11.14);
    $sheet->getColumnDimension('N')->setWidth(9.14);
    $sheet->getColumnDimension('O')->setWidth(7.43);
    $sheet->getColumnDimension('P')->setWidth(1.71);

    // -----------------------------------------------------------------
    // Alturas de fila
    // -----------------------------------------------------------------
    $sheet->getRowDimension(1)->setRowHeight(6.0);
    $sheet->getRowDimension(2)->setRowHeight(15.0);
    $sheet->getRowDimension(3)->setRowHeight(15.0);
    $sheet->getRowDimension(4)->setRowHeight(21.0);
    $sheet->getRowDimension(5)->setRowHeight(15.75);
    $sheet->getRowDimension(6)->setRowHeight(27.0);
    $sheet->getRowDimension(7)->setRowHeight(35.25);

    // -----------------------------------------------------------------
    // ENCABEZADO: Logo (B2:E4) + Título (F2:O4)
    // -----------------------------------------------------------------
    $sheet->mergeCells('B2:E4');
    $sheet->mergeCells('F2:O4');

    // Caja del logo - con borde
    $sheet->getStyle('B2:E4')->applyFromArray([
        'borders' => [
            'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
        ]
    ]);

    // Insertar logo si existe (mismo patrón que el dashboard).
    // Para usar un logo específico de esta bitácora, sube el archivo a:
    //   assets/img/logo_bitacora_aires.png
    // Si no existe, se usa el logo general del sistema (assets/img/Logo.png).
    $ba_logo_fs = __DIR__ . '/../../../assets/img/logo_bitacora_aires.png';
    if (!file_exists($ba_logo_fs)) {
        $ba_logo_fs = __DIR__ . '/../../../assets/img/Logo.png';
    }
    if (file_exists($ba_logo_fs) && @getimagesize($ba_logo_fs) !== false) {
        try {
            $logoDwg = new Drawing();
            $logoDwg->setName('Logo');
            $logoDwg->setDescription('Logo de la empresa');
            $logoDwg->setPath($ba_logo_fs);
            $logoDwg->setCoordinates('B2');

            // Ajustar tamaño dentro del bloque B2:E4 (aprox 195x70 px)
            $tamLogo = @getimagesize($ba_logo_fs);
            if ($tamLogo !== false) {
                $iw = (int)$tamLogo[0];
                $ih = (int)$tamLogo[1];
                $maxW = 180;
                $maxH = 62;
                $escala = min($maxW / $iw, $maxH / $ih);
                $nuevoW = max(1, (int)round($iw * $escala));
                $nuevoH = max(1, (int)round($ih * $escala));
                $logoDwg->setWidthAndHeight($nuevoW, $nuevoH);
                // Centrar dentro de B2:E4
                $logoDwg->setOffsetX(max(2, (int)round((195 - $nuevoW) / 2)));
                $logoDwg->setOffsetY(max(2, (int)round((70 - $nuevoH) / 2)));
            }

            $logoDwg->setWorksheet($sheet);
        } catch (Exception $e) {
            error_log("[Bitácora Aires - Excel] Error insertando logo: " . $e->getMessage());
        }
    }

    // Título
    $sheet->setCellValue('F2', 'BITÁCORA DE REGISTRO AIRES ACONDICIONADOS');
    $sheet->getStyle('F2:O4')->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 20, 'bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders' => [
            'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
        ]
    ]);

    // -----------------------------------------------------------------
    // NOMBRE DEL ENCARGADO (B6:O6)
    // -----------------------------------------------------------------
    $sheet->mergeCells('B6:O6');
    $sheet->setCellValue('B6', 'Nombre del encargado: Christopher Hernández Hernández');
    $sheet->getStyle('B6:O6')->applyFromArray([
        'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => false],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'indent'     => 1,
        ],
        'borders' => [
            'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
        ]
    ]);

    // -----------------------------------------------------------------
    // CABECERAS DE TABLA (fila 7)
    // -----------------------------------------------------------------
    $sheet->mergeCells('B7:C7'); // Fecha
    $sheet->mergeCells('D7:I7'); // Nombre Completo
    $sheet->mergeCells('N7:O7'); // Firma (entrega)

    $sheet->setCellValue('B7', 'Fecha');
    $sheet->setCellValue('D7', 'Nombre Completo');
    $sheet->setCellValue('J7', 'Área - Lugar');
    $sheet->setCellValue('K7', 'Hora prestamo');
    $sheet->setCellValue('L7', 'Firma');
    $sheet->setCellValue('M7', 'Hora entrega');
    $sheet->setCellValue('N7', 'Firma');

    $estiloHeader = [
        'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => false],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '89DBC6'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
        ]
    ];
    $sheet->getStyle('B7:O7')->applyFromArray($estiloHeader);

    // -----------------------------------------------------------------
    // DATOS - desde la fila 8
    // -----------------------------------------------------------------
    $filaInicio = 8;
    $minFilas   = 28; // Mínimo de filas en blanco para que se vea como plantilla
    $totalFilas = max($minFilas, count($registros));

    $estiloDatoBase = [
        'font' => ['name' => 'Calibri', 'size' => 10],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
        ]
    ];

    for ($i = 0; $i < $totalFilas; $i++) {
        $r = $filaInicio + $i;
        $sheet->getRowDimension($r)->setRowHeight(28);

        // Merges por fila (B:C, D:I, N:O)
        $sheet->mergeCells("B{$r}:C{$r}");
        $sheet->mergeCells("D{$r}:I{$r}");
        $sheet->mergeCells("N{$r}:O{$r}");

        // Aplicar estilo base a toda la fila B-O
        $sheet->getStyle("B{$r}:O{$r}")->applyFromArray($estiloDatoBase);

        if ($i < count($registros)) {
            $reg       = $registros[$i];
            $fechaFmt  = date('d/m/Y', strtotime($reg['fecha_prestamo']));
            $horaP     = $reg['hora_prestamo'] ? substr($reg['hora_prestamo'], 0, 5) : '';
            $horaE     = $reg['hora_entrega']  ? substr($reg['hora_entrega'], 0, 5)  : '';

            $sheet->setCellValue("B{$r}", $fechaFmt);
            $sheet->setCellValue("D{$r}", $reg['nombre_completo']);
            $sheet->getStyle("D{$r}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'indent'     => 1,
                    'wrapText'   => true,
                ]
            ]);
            $sheet->setCellValue("J{$r}", $reg['departamento_nombre']);
            $sheet->setCellValue("K{$r}", $horaP);
            $sheet->setCellValue("M{$r}", $horaE);

            // ----- Firma préstamo (columna L) -----
            if (!empty($reg['firma_prestamo'])) {
                $rutaFirma = ba_firma_a_temp($reg['firma_prestamo']);
                if ($rutaFirma) {
                    $archivos_temporales[] = $rutaFirma;
                    try {
                        $dwg = new Drawing();
                        $dwg->setName('FirmaPrestamo_' . $reg['id']);
                        $dwg->setDescription('Firma préstamo');
                        $dwg->setPath($rutaFirma);
                        $dwg->setCoordinates("L{$r}");

                        // Ajustar tamaño respetando proporción
                        $tam = @getimagesize($rutaFirma);
                        if ($tam !== false) {
                            $img_w = (int)$tam[0];
                            $img_h = (int)$tam[1];
                            $max_w = 80;
                            $max_h = 26;
                            $escala = min($max_w / $img_w, $max_h / $img_h);
                            $nuevoW = max(1, (int)round($img_w * $escala));
                            $nuevoH = max(1, (int)round($img_h * $escala));
                            $dwg->setWidthAndHeight($nuevoW, $nuevoH);
                            $dwg->setOffsetX(max(2, (int)round((90 - $nuevoW) / 2)));
                            $dwg->setOffsetY(max(2, (int)round((36 - $nuevoH) / 2)));
                        } else {
                            $dwg->setHeight(24);
                            $dwg->setOffsetX(6);
                            $dwg->setOffsetY(4);
                        }

                        $dwg->setWorksheet($sheet);
                    } catch (Exception $e) {
                        error_log("[Bitácora Aires - Excel] firma préstamo {$reg['id']}: " . $e->getMessage());
                    }
                }
            }

            // ----- Firma entrega (columna N, mergeada N:O) -----
            if (!empty($reg['firma_entrega'])) {
                $rutaFirma = ba_firma_a_temp($reg['firma_entrega']);
                if ($rutaFirma) {
                    $archivos_temporales[] = $rutaFirma;
                    try {
                        $dwg = new Drawing();
                        $dwg->setName('FirmaEntrega_' . $reg['id']);
                        $dwg->setDescription('Firma entrega');
                        $dwg->setPath($rutaFirma);
                        $dwg->setCoordinates("N{$r}");

                        $tam = @getimagesize($rutaFirma);
                        if ($tam !== false) {
                            $img_w = (int)$tam[0];
                            $img_h = (int)$tam[1];
                            $max_w = 110;
                            $max_h = 26;
                            $escala = min($max_w / $img_w, $max_h / $img_h);
                            $nuevoW = max(1, (int)round($img_w * $escala));
                            $nuevoH = max(1, (int)round($img_h * $escala));
                            $dwg->setWidthAndHeight($nuevoW, $nuevoH);
                            $dwg->setOffsetX(max(2, (int)round((130 - $nuevoW) / 2)));
                            $dwg->setOffsetY(max(2, (int)round((36 - $nuevoH) / 2)));
                        } else {
                            $dwg->setHeight(24);
                            $dwg->setOffsetX(8);
                            $dwg->setOffsetY(4);
                        }

                        $dwg->setWorksheet($sheet);
                    } catch (Exception $e) {
                        error_log("[Bitácora Aires - Excel] firma entrega {$reg['id']}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // Configuración de página
    // -----------------------------------------------------------------
    $sheet->getPageSetup()
          ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
          ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER)
          ->setFitToWidth(1)
          ->setFitToHeight(0);

    $sheet->getPageMargins()->setTop(0.4)->setRight(0.4)->setLeft(0.4)->setBottom(0.4);

    $sheet->setSelectedCell('A1');

    // Metadata
    $spreadsheet->getProperties()
        ->setCreator($_SESSION['nombre_completo'] ?? 'Sistemas')
        ->setLastModifiedBy($_SESSION['nombre_completo'] ?? 'Sistemas')
        ->setTitle('Bitácora de Registro Aires Acondicionados')
        ->setSubject('Registro de préstamos de controles de aire acondicionado')
        ->setDescription('Generado automáticamente desde Verden Core');

    // -----------------------------------------------------------------
    // DESCARGAR
    // -----------------------------------------------------------------
    $filename = 'Bitacora_Aires_Acondicionados_' . date('Y-m-d_His') . '.xlsx';

    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    // Limpiar temporales
    foreach ($archivos_temporales as $tmp) {
        @unlink($tmp);
    }

    exit;

} catch (Exception $e) {
    error_log("[Bitácora Aires - Excel] Error general: " . $e->getMessage());
    foreach ($archivos_temporales as $tmp) {
        @unlink($tmp);
    }
    establecer_alerta('error', 'Error al generar el archivo Excel: ' . $e->getMessage());
    header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
    exit;
}