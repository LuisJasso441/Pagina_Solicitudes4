<?php
/**
 * Descargar Vale de EPP en PDF
 * VERSIÓN 2.2 - Marco envolvente al contenido (no a la hoja)
 * Ubicación: dashboard/inventario_epp/descargar_vale_pdf.php
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventario_epp/vales_epp_funciones.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$permisos = verificar_permisos_epp($_SESSION['usuario_id']);
if (!$permisos['tiene_acceso']) {
    die('Sin acceso.');
}

$vale_id = (int) ($_GET['id'] ?? 0);
if (!$vale_id) die('Vale no encontrado.');

$vale = obtener_vale_epp($vale_id);
if (!$vale) die('Vale no encontrado.');

// =====================================================
// CREAR PDF
// =====================================================

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('VerdenCore');
$pdf->SetAuthor('Grupo Verden');
$pdf->SetTitle('Vale ' . $vale['folio']);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// =====================================================
// PASO 1: Escribir todo el contenido y medir la altura
// =====================================================

$margen = 18;
$ancho = 180;
$y_inicio = 18;

$pdf->SetXY($margen, $y_inicio + 5);

// --- ENCABEZADO ---
$logo_path = __DIR__ . '/../../assets/img/LOGO RESIMEX.jpg';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, $margen + 3, $y_inicio + 5, 32, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColor(44, 62, 80);
$pdf->SetXY(55, $y_inicio + 5);
$pdf->Cell(0, 9, 'VALE DE SOLICITUD DE ENTREGA', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetX(55);
$pdf->Cell(0, 8, 'DE EQUIPO DE PROTECCIÓN PERSONAL', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// Línea separadora bajo título
$pdf->Ln(3);
$pdf->SetDrawColor(44, 62, 80);
$pdf->SetLineWidth(0.4);
$pdf->Line($margen + 2, $pdf->GetY(), $margen + $ancho - 2, $pdf->GetY());
$pdf->Ln(4);

// --- FOLIO Y ESTADO ---
$pdf->SetX($margen);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(240, 243, 247);
$pdf->Cell(90, 8, '  Folio: ' . $vale['folio'], 1, 0, 'L', true);

if ($vale['estado'] === 'Entregado') {
    $pdf->SetFillColor(212, 237, 218);
    $pdf->SetTextColor(21, 87, 36);
} elseif ($vale['estado'] === 'Pendiente') {
    $pdf->SetFillColor(255, 243, 205);
    $pdf->SetTextColor(133, 100, 4);
} elseif ($vale['estado'] === 'Cancelado') {
    $pdf->SetFillColor(248, 215, 218);
    $pdf->SetTextColor(114, 28, 36);
}
$pdf->Cell(90, 8, 'Estado: ' . $vale['estado'] . '  ', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// --- DATOS DEL TRABAJADOR ---
$pdf->Ln(4);
$pdf->SetX($margen);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($ancho, 7, '  DATOS DEL TRABAJADOR', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetX($margen);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(248, 249, 250);
$pdf->Cell(30, 7, ' Nombre:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($ancho - 30, 7, ' ' . $vale['nombre_empleado'], 1, 1, 'L');

$pdf->SetX($margen);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(30, 7, ' Área:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($ancho - 30, 7, ' ' . $vale['area'], 1, 1, 'L');

// --- TABLA DE ARTÍCULOS ---
$pdf->Ln(5);
$pdf->SetX($margen);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);

$pdf->Cell(12, 7, '#', 1, 0, 'C', true);
$pdf->Cell(18, 7, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(78, 7, 'Descripción', 1, 0, 'C', true);
$pdf->Cell(24, 7, 'Talla', 1, 0, 'C', true);
$pdf->Cell(24, 7, 'Motivo', 1, 0, 'C', true);
$pdf->Cell(24, 7, 'Fecha', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

$fill = false;
foreach ($vale['lineas'] as $i => $linea) {
    $pdf->SetX($margen);
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 247 : 255, $fill ? 250 : 255);
    
    $motivo_text = $linea['motivo'];
    if ($linea['motivo'] === 'Otro' && $linea['motivo_otro']) {
        $motivo_text .= ': ' . $linea['motivo_otro'];
    }
    
    $pdf->Cell(12, 7, ($i + 1), 1, 0, 'C', true);
    $pdf->Cell(18, 7, $linea['cantidad'], 1, 0, 'C', true);
    $pdf->Cell(78, 7, $linea['descripcion'], 1, 0, 'L', true);
    $pdf->Cell(24, 7, $linea['talla'] ?: '-', 1, 0, 'C', true);
    $pdf->Cell(24, 7, $motivo_text, 1, 0, 'C', true);
    $pdf->Cell(24, 7, date('d/m/Y', strtotime($vale['fecha_creacion'])), 1, 1, 'C', true);
    
    $fill = !$fill;
}

// Leyenda
$pdf->Ln(2);
$pdf->SetX($margen);
$pdf->SetFont('helvetica', 'I', 7.5);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'MOTIVO:   a) Nuevo      b) Cambio      c) Reemplazo      d) Otro', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);

// --- OBSERVACIONES ---
if ($vale['observaciones']) {
    $pdf->Ln(2);
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($ancho, 6, 'Observaciones:', 0, 1, 'L');
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell($ancho, 5, $vale['observaciones'], 1, 'L');
}

// --- ENTREGA CONFIRMADA ---
if ($vale['estado'] === 'Entregado') {
    $pdf->Ln(3);
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(40, 167, 69);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($ancho, 7, '  ENTREGA CONFIRMADA', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Cell(35, 7, ' Entregado por:', 1, 0, 'L', true);
    $pdf->Cell(65, 7, ' ' . $vale['entregado_por_nombre'], 1, 0, 'L');
    $pdf->Cell(25, 7, ' Fecha:', 1, 0, 'L', true);
    $pdf->Cell($ancho - 125, 7, ' ' . date('d/m/Y H:i', strtotime($vale['fecha_entrega'])), 1, 1, 'L');
    
    if ($vale['observaciones_entrega']) {
        $pdf->SetX($margen);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($ancho, 5, 'Obs. entrega: ' . $vale['observaciones_entrega'], 1, 'L');
    }
}

// --- CANCELADO ---
if ($vale['estado'] === 'Cancelado') {
    $pdf->Ln(3);
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 53, 69);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($ancho, 7, '  VALE CANCELADO', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetX($margen);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(35, 7, ' Cancelado por:', 1, 0, 'L');
    $pdf->Cell(65, 7, ' ' . ($vale['cancelado_por_nombre'] ?? ''), 1, 0, 'L');
    $pdf->Cell(25, 7, ' Fecha:', 1, 0, 'L');
    $pdf->Cell($ancho - 125, 7, ' ' . ($vale['fecha_cancelacion'] ? date('d/m/Y H:i', strtotime($vale['fecha_cancelacion'])) : ''), 1, 1, 'L');
    
    if ($vale['motivo_cancelacion']) {
        $pdf->SetX($margen);
        $pdf->MultiCell($ancho, 5, 'Motivo: ' . $vale['motivo_cancelacion'], 1, 'L');
    }
}

// Pie dentro del marco
$pdf->Ln(4);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(130, 130, 130);
$pdf->Cell(0, 4, 'Generado desde VerdenCore el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre_completo'], 0, 1, 'C');

// =====================================================
// PASO 2: Dibujar el marco alrededor del contenido
// =====================================================

$y_fin = $pdf->GetY() + 5;

// Marco exterior (línea gruesa)
$pdf->SetDrawColor(44, 62, 80);
$pdf->SetLineWidth(1.0);
$pdf->Rect($margen - 4, $y_inicio, $ancho + 8, $y_fin - $y_inicio);

// Marco interior (línea delgada, separada 2mm)
$pdf->SetLineWidth(0.3);
$pdf->Rect($margen - 2, $y_inicio + 2, $ancho + 4, $y_fin - $y_inicio - 4);

// Restaurar
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.2);

// =====================================================
// SALIDA
// =====================================================

$nombre_archivo = 'Vale_' . $vale['folio'] . '.pdf';
$pdf->Output($nombre_archivo, 'D');
exit;