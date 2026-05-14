<?php
/**
 * Generador de PDF - Solicitud de Vacaciones
 * dashboard/vacaciones/generar_pdf_solicitud_vacaciones.php
 * 
 * Formato: FR-GT-07
 * Recibe datos del formulario via POST y genera PDF descargable
 * Solo accesible por Admin de Área
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/vacaciones/vacaciones_funciones.php';

if (!sesion_activa()) { exit('No autenticado'); }
if (empty($_SESSION['es_admin_area'])) { exit('Sin permisos'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Método no permitido'); }

require_once __DIR__ . '/../../vendor/autoload.php';

// ========================================
// RECIBIR DATOS DEL FORMULARIO
// ========================================

$nombre          = trim($_POST['nombre'] ?? $_SESSION['nombre_completo']);
$no_nomina       = trim($_POST['no_nomina'] ?? $_SESSION['no_nomina'] ?? '');
$departamento    = trim($_POST['departamento'] ?? $_SESSION['departamento_nombre'] ?? '');
$puesto          = trim($_POST['puesto'] ?? $_SESSION['puesto'] ?? '');
$fecha_ingreso   = trim($_POST['fecha_ingreso'] ?? '');
$periodo_vac     = trim($_POST['periodo_vacacional'] ?? '');
$dias_corresp    = intval($_POST['dias_correspondientes'] ?? 0);
$dias_pendientes = intval($_POST['dias_pendientes'] ?? 0);
$dias_solicitados = intval($_POST['dias_solicitados'] ?? 0);
$saldo_dias      = intval($_POST['saldo_dias'] ?? 0);
$fecha_inicio    = trim($_POST['fecha_inicio'] ?? '');
$fecha_fin       = trim($_POST['fecha_fin'] ?? '');
$fecha_regreso   = trim($_POST['fecha_regreso'] ?? '');
$empresa         = trim($_POST['empresa'] ?? 'resimex');
$firma_empleado  = $_POST['firma_empleado'] ?? '';

// Formatear fechas
function fmtFecha($fecha) {
    if (empty($fecha)) return '';
    try {
        $dt = new DateTime($fecha);
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        return $dt->format('d') . ' ' . $meses[intval($dt->format('m')) - 1] . ' ' . $dt->format('Y');
    } catch (Exception $e) {
        return $fecha;
    }
}

function fmtFechaCorta($fecha) {
    if (empty($fecha)) return '';
    try { return (new DateTime($fecha))->format('d/m/Y'); }
    catch (Exception $e) { return $fecha; }
}

$fecha_solicitud_texto = obtener_fecha_actual_espanol();
$fecha_ingreso_fmt = fmtFecha($fecha_ingreso);
$fecha_inicio_fmt = fmtFechaCorta($fecha_inicio);
$fecha_fin_fmt = fmtFechaCorta($fecha_fin);
$fecha_regreso_fmt = fmtFechaCorta($fecha_regreso);

// ========================================
// CONFIGURACIÓN DEL PDF
// ========================================

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('VerdenCore - Sistema de Vacaciones');
$pdf->SetAuthor('Sistema Vacaciones');
$pdf->SetTitle('Solicitud de Vacaciones - ' . $nombre);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(false, 10);
$pdf->AddPage();

// Ancho útil
$anchoTotal = 186; // 216 - 15 - 15

// ========================================
// ENCABEZADO: Logo | Título | Código
// ========================================

$yInicio = 12;
$alturaEnc = 18;
$anchoLogo = 40;
$anchoTitulo = 90;
$anchoCodigo = $anchoTotal - $anchoLogo - $anchoTitulo;

$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.4);

// Logo
$empresaLower = strtolower($empresa);
$rutaLogo = __DIR__ . '/../../assets/img/logo_' . $empresaLower . '.png';
if (!file_exists($rutaLogo)) {
    $rutaLogo = __DIR__ . '/../../assets/img/LOGO RESIMEX.jpg';
}

$pdf->SetXY(15, $yInicio);
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell($anchoLogo, $alturaEnc, '', 1, 0, 'C', true);
if (file_exists($rutaLogo)) {
    $pdf->Image($rutaLogo, 17, $yInicio + 2, $anchoLogo - 4, 0, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell($anchoLogo, $alturaEnc, strtoupper($empresaLower), 0, 0, 'C');
}

// Título central
$pdf->SetXY(15 + $anchoLogo, $yInicio);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell($anchoTitulo, $alturaEnc, 'SOLICITUD DE VACACIONES', 1, 0, 'C', true);

// Código (3 filas)
$xCodigo = 15 + $anchoLogo + $anchoTitulo;
$altCodFila = $alturaEnc / 3;

$pdf->SetXY($xCodigo, $yInicio);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, 'CÓDIGO:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, 'FR-GT-07', 1, 0, 'C', true);

$pdf->SetXY($xCodigo, $yInicio + $altCodFila);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, 'NO. DE REVISIÓN:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, '00', 1, 0, 'C', true);

$pdf->SetXY($xCodigo, $yInicio + $altCodFila * 2);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, 'FECHA DE REVISIÓN:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($anchoCodigo / 2, $altCodFila, '02/08/2021', 1, 0, 'C', true);

// ========================================
// FECHA DE SOLICITUD
// ========================================

$yFecha = $yInicio + $alturaEnc + 4;
$pdf->SetXY(15, $yFecha);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($anchoTotal, 6, 'Fecha de Solicitud: ' . $fecha_solicitud_texto, 0, 1, 'R');

// ========================================
// DATOS DEL EMPLEADO
// ========================================

$yDatos = $yFecha + 8;
$anchoLabel = 40;
$anchoValor = $anchoTotal - $anchoLabel;
$altFila = 7;

// Línea separadora por cada campo
$campos = [
    ['label' => 'NOMBRE:', 'valor' => $nombre, 'label2' => 'NO. NÓMINA:', 'valor2' => $no_nomina],
    ['label' => 'DEPARTAMENTO:', 'valor' => $departamento, 'label2' => 'PUESTO:', 'valor2' => $puesto],
    ['label' => 'FECHA DE INGRESO:', 'valor' => $fecha_ingreso_fmt],
];

foreach ($campos as $i => $campo) {
    $y = $yDatos + ($i * ($altFila + 1));
    $pdf->SetXY(15, $y);
    $pdf->SetFont('helvetica', 'B', 9);
    
    if (isset($campo['label2'])) {
        // Dos campos en una fila
        $mitad = $anchoTotal / 2;
        $pdf->Cell(38, $altFila, $campo['label'], 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($mitad - 38, $altFila, $campo['valor'], 'B', 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(30, $altFila, $campo['label2'], 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($mitad - 30, $altFila, $campo['valor2'], 'B', 1, 'L');
    } else {
        // Campo completo
        $pdf->Cell(40, $altFila, $campo['label'], 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($anchoTotal - 40, $altFila, $campo['valor'], 'B', 1, 'L');
    }
}

// ========================================
// TABLA: PERIODO VACACIONAL
// ========================================

$yTabla = $yDatos + (count($campos) * ($altFila + 1)) + 5;
$anchoLabelTabla = $anchoTotal * 0.65;
$anchoValorTabla = $anchoTotal - $anchoLabelTabla;

$filas_tabla = [
    ['label' => 'PERIODO VACACIONAL CORRIENTE', 'valor' => $periodo_vac, 'bold_valor' => true],
    ['label' => 'DÍAS DE VACACIONES CORRESPONDIENTES AL PERIODO', 'valor' => $dias_corresp],
    ['label' => 'DÍAS PENDIENTES DE VACACIONES', 'valor' => $dias_pendientes],
    ['label' => 'DÍAS SOLICITADOS', 'valor' => $dias_solicitados, 'color' => true],
    ['label' => 'SALDO DE DÍAS PENDIENTES', 'valor' => $saldo_dias],
];

foreach ($filas_tabla as $idx => $fila) {
    $y = $yTabla + ($idx * 7);
    $pdf->SetXY(15, $y);
    
    // Label
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($anchoLabelTabla, 7, '  ' . $fila['label'], 1, 0, 'L', true);
    
    // Valor
    $valorStr = (string)$fila['valor'];
    if (!empty($fila['bold_valor'])) {
        $pdf->SetFont('helvetica', 'B', 9);
    } else {
        $pdf->SetFont('helvetica', '', 9);
    }
    if (!empty($fila['color'])) {
        $pdf->SetTextColor(0, 128, 0);
    }
    $pdf->Cell($anchoValorTabla, 7, $valorStr, 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
}

// Nota LFT
$yNota = $yTabla + (count($filas_tabla) * 7) + 2;
$pdf->SetXY(15, $yNota);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->MultiCell($anchoTotal, 4, 'Está solicitando de acuerdo con lo establecido por la Ley Federal de Trabajo en su Artículo 76 y 80 le sean otorgadas sus vacaciones correspondientes a los días:', 0, 'L');

// ========================================
// DÍAS DE VACACIONES (DEL / AL / REGRESA)
// ========================================

$yVac = $yNota + 10;
$pdf->SetXY(15, $yVac);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($anchoTotal, 7, 'DÍAS DE VACACIONES', 0, 1, 'L');

$yVac += 8;
$anchoLabelVac = 25;
$anchoValorVac = 40;

// DEL
$pdf->SetXY(30, $yVac);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($anchoLabelVac, 7, 'DEL:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoValorVac, 7, $fecha_inicio_fmt, 1, 1, 'C');

// AL
$pdf->SetXY(30, $yVac + 8);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($anchoLabelVac, 7, 'AL:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoValorVac, 7, $fecha_fin_fmt, 1, 1, 'C');

// REGRESA
$pdf->SetXY(30, $yVac + 16);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($anchoLabelVac, 7, 'REGRESA:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoValorVac, 7, $fecha_regreso_fmt, 1, 1, 'C');


// ========================================
// FIRMAS (3 columnas)
// ========================================

$yFirmas = $yVac + 34;
$anchoFirma = $anchoTotal / 3;
$alturaFirma = 30;

// Firma del solicitante
$pdf->SetXY(15, $yFirmas);
$pdf->Cell($anchoFirma, $alturaFirma, '', 0, 0, 'C');

// Insertar firma del empleado si existe
if (!empty($firma_empleado) && strpos($firma_empleado, 'data:image') === 0) {
    $firmaData = preg_replace('#^data:image/\w+;base64,#i', '', $firma_empleado);
    $firmaImagen = base64_decode($firmaData);
    if ($firmaImagen) {
        $tempFile = tempnam(sys_get_temp_dir(), 'firma_vac_') . '.png';
        file_put_contents($tempFile, $firmaImagen);
        $pdf->Image($tempFile, 20, $yFirmas + 2, $anchoFirma - 10, $alturaFirma - 8, 'PNG', '', '', true, 150, '', false, false, 0, 'CM');
        @unlink($tempFile);
    }
}

// Líneas de firma
$yLinea = $yFirmas + $alturaFirma;
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);

// Línea 1
$pdf->Line(15, $yLinea, 15 + $anchoFirma, $yLinea);
$pdf->SetXY(15, $yLinea + 1);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($anchoFirma, 4, 'NOMBRE Y FIRMA DEL SOLICITANTE', 0, 0, 'C');

// Línea 2
$pdf->Line(15 + $anchoFirma, $yLinea, 15 + $anchoFirma * 2, $yLinea);
$pdf->SetXY(15 + $anchoFirma, $yLinea + 1);
$pdf->Cell($anchoFirma, 4, 'NOMBRE Y FIRMA', 0, 0, 'C');
$pdf->SetXY(15 + $anchoFirma, $yLinea + 4);
$pdf->Cell($anchoFirma, 4, 'JEFE INMEDIATO', 0, 0, 'C');

// Línea 3
$pdf->Line(15 + $anchoFirma * 2, $yLinea, 15 + $anchoFirma * 3, $yLinea);
$pdf->SetXY(15 + $anchoFirma * 2, $yLinea + 1);
$pdf->Cell($anchoFirma, 4, 'NOMBRE Y FIRMA', 0, 0, 'C');
$pdf->SetXY(15 + $anchoFirma * 2, $yLinea + 4);
$pdf->Cell($anchoFirma, 4, 'GESTIÓN DE TALENTO HUMANO', 0, 0, 'C');

// ========================================
// GENERAR Y DESCARGAR PDF
// ========================================

$nombreArchivo = 'Solicitud_Vacaciones_' . preg_replace('/[^a-zA-Z0-9]/', '_', $nombre) . '_' . date('Ymd') . '.pdf';

if (ob_get_level()) { ob_end_clean(); }

$pdf->Output($nombreArchivo, 'D');
exit;
