<?php
/**
 * Generador de PDF para Solicitudes de Servicio a Clientes (SSC)
 * Formato: FR-CC-12
 * 
 * Genera un PDF con el formato oficial de la empresa
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/colaborativo/documentos_colaborativos.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'login.php');
    exit;
}

// Obtener ID del documento
$documento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$documento_id) {
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/documentos_colaborativos.php?error=id_invalido');
    exit;
}

// Obtener documento
$documento = obtener_documento($documento_id);

if (!$documento) {
    header('Location: ' . URL_BASE . 'dashboard/colaborativo/documentos_colaborativos.php?error=not_found');
    exit;
}

// Cargar TCPDF
require_once __DIR__ . '/../vendor/autoload.php';

// ========================================
// FUNCIÓN: Auto-ajuste de texto en celda
// ========================================

/**
 * Imprime texto en una celda ajustando el tamaño de fuente automaticamente
 * si el texto es demasiado largo para caber en el ancho disponible.
 * 
 * @param TCPDF  $pdf        Instancia de TCPDF
 * @param float  $ancho      Ancho de la celda en mm
 * @param float  $alto       Alto de la celda en mm
 * @param string $texto      Texto a imprimir
 * @param int    $borde      Borde de la celda (default 1)
 * @param int    $ln         Salto de linea despues (default 0)
 * @param string $align      Alineacion: L, C, R (default 'L')
 * @param bool   $fill       Rellenar fondo (default true)
 * @param string $fontFamily Familia de fuente (default 'helvetica')
 * @param string $fontStyle  Estilo de fuente (default '')
 * @param float  $maxSize    Tamaño maximo de fuente (default 9)
 * @param float  $minSize    Tamaño minimo de fuente (default 6)
 */
function cellAutoFit($pdf, $ancho, $alto, $texto, $borde = 1, $ln = 0, $align = 'L', $fill = true, $fontFamily = 'helvetica', $fontStyle = '', $maxSize = 9, $minSize = 6) {
    $pdf->SetFont($fontFamily, $fontStyle, $maxSize);
    $textoAncho = $pdf->GetStringWidth($texto);
    
    // Reducir fuente si el texto no cabe (dejando 1mm de margen interno)
    $fontSize = $maxSize;
    while ($textoAncho > ($ancho - 1) && $fontSize > $minSize) {
        $fontSize -= 0.5;
        $pdf->SetFont($fontFamily, $fontStyle, $fontSize);
        $textoAncho = $pdf->GetStringWidth($texto);
    }
    
    $pdf->Cell($ancho, $alto, $texto, $borde, $ln, $align, $fill);
    
    // Restaurar tamaño original despues de imprimir
    $pdf->SetFont($fontFamily, $fontStyle, $maxSize);
}

// ========================================
// PREPARAR DATOS DEL DOCUMENTO
// ========================================

// Servicios disponibles (usar texto del sistema)
$servicios_nombres = [
    'tratamiento_agua' => 'Tratamiento de agua',
    'revision_productos' => 'Productos químicos y/o residuos',
    'calibracion_equipos' => 'Calibración y/o verificación de equipos',
    'otro' => 'Otro'
];

$servicio_seleccionado = $documento['servicio_solicitado'] ?? '';
$servicio_otro_texto = $documento['servicio_otro_especificar'] ?? '';

// Formatear fechas
$fecha_solicitud = !empty($documento['fecha_solicitud']) 
    ? date('d/m/Y', strtotime($documento['fecha_solicitud'])) 
    : '';

$fecha_recibido = !empty($documento['fecha_recibido']) 
    ? date('d/m/Y', strtotime($documento['fecha_recibido'])) 
    : '';

$hora_recibido = $documento['hora_recibido'] ?? '';

$fecha_entrega = !empty($documento['fecha_entrega']) 
    ? date('d/m/Y', strtotime($documento['fecha_entrega'])) 
    : '';

$hora_entrega = $documento['hora_entrega'] ?? '';

// Fecha y hora de respuesta combinada
$fecha_hora_respuesta = '';
if (!empty($fecha_entrega)) {
    $fecha_hora_respuesta = $fecha_entrega;
    if (!empty($hora_entrega)) {
        $fecha_hora_respuesta .= ' ' . $hora_entrega;
    }
}

// ========================================
// CONFIGURACIÓN DEL PDF
// ========================================

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator('VerdenCore - Sistema SSC');
$pdf->SetAuthor('Sistema de Solicitudes');
$pdf->SetTitle('Solicitud de Servicio a Clientes - ' . $documento['folio']);
$pdf->SetSubject('Solicitud de Servicio a Clientes');

// Eliminar header y footer por defecto
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Configurar márgenes
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

// Agregar página
$pdf->AddPage();

// ========================================
// COLORES Y ESTILOS
// ========================================

$colorVerdePrincipal = array(146, 208, 80);   // Verde para títulos
$colorVerdeClaro = array(198, 239, 206);      // Verde claro para secciones
$colorGris = array(217, 217, 217);            // Gris para etiquetas
$colorBlanco = array(255, 255, 255);
$colorNegro = array(0, 0, 0);

// Ancho de página utilizable (Letter: 216mm - márgenes)
$anchoTotal = 196;
$anchoMitad = $anchoTotal / 2;

// ========================================
// ENCABEZADO: LOGO | TÍTULO | CÓDIGO
// ========================================

$yInicio = 10;
$alturaEncabezado = 21; // Altura total del encabezado (divisible entre 3)

// Columna 1: Logo (espacio reservado)
$anchoLogo = 45;
$pdf->SetXY(10, $yInicio);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);
$pdf->Cell($anchoLogo, $alturaEncabezado, '', 1, 0, 'C', true);

// Intentar insertar logo si existe
$rutaLogo = __DIR__ . '/../assets/img/LOGO RESIMEX.jpg';
if (file_exists($rutaLogo)) {
    $pdf->Image($rutaLogo, 12, $yInicio + 2, 41, 0, '', '', '', true, 150);
} else {
    // Si no hay logo, mostrar texto placeholder
    $pdf->SetXY(10, $yInicio + 7);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($anchoLogo, 6, 'LOGO EMPRESA', 0, 0, 'C');
}

// Columna 2: Título central
$anchoTitulo = 95;
$pdf->SetXY(10 + $anchoLogo, $yInicio);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoTitulo, $alturaEncabezado, 'SOLICITUD DE SERVICIO A CLIENTES', 1, 0, 'C', true);

// Columna 3: Tabla de código/revisión
$anchoCodigo = 56; // 45 + 95 + 56 = 196
$anchoEtiquetaCodigo = 32;
$anchoValorCodigo = $anchoCodigo - $anchoEtiquetaCodigo;
$alturaCelda = 7; // 7 x 3 = 21
$xCodigo = 10 + $anchoLogo + $anchoTitulo;

// Fila 1: CÓDIGO
$pdf->SetXY($xCodigo, $yInicio);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiquetaCodigo, $alturaCelda, 'CÓDIGO:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValorCodigo, $alturaCelda, 'FR-CC-12', 1, 1, 'C', true);

// Fila 2: No. DE REVISIÓN
$pdf->SetXY($xCodigo, $yInicio + $alturaCelda);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiquetaCodigo, $alturaCelda, 'No. DE REVISIÓN:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValorCodigo, $alturaCelda, '02', 1, 1, 'C', true);

// Fila 3: FECHA DE REVISIÓN
$pdf->SetXY($xCodigo, $yInicio + ($alturaCelda * 2));
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiquetaCodigo, $alturaCelda, 'FECHA DE REVISIÓN:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValorCodigo, $alturaCelda, '26/01/2026', 1, 1, 'C', true);

// ========================================
// SECCIÓN: DATOS DEL SOLICITANTE
// ========================================

$yDatos = $yInicio + $alturaEncabezado + 3;
$alturaFila = 7;

// Definir anchos de columnas
$anchoEtiqueta1 = 38;  // Etiqueta columna izquierda
$anchoValor1 = 60;     // Valor columna izquierda
$anchoEtiqueta2 = 48;  // Etiqueta columna derecha (más larga)
$anchoValor2 = 50;     // Valor columna derecha
// Total: 38 + 60 + 48 + 50 = 196

// Fila 1: Nombre de solicitante | Nombre de quien recibe solicitud
$pdf->SetXY(10, $yDatos);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoEtiqueta1, $alturaFila, 'Nombre de solicitante:', 1, 0, 'L', true);
cellAutoFit($pdf, $anchoValor1, $alturaFila, $documento['solicitado_por'] ?? '');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta2, $alturaFila, 'Nombre de quien recibe solicitud:', 1, 0, 'L', true);
cellAutoFit($pdf, $anchoValor2, $alturaFila, $documento['recibe_solicitud'] ?? '', 1, 1);

// Fila 2: Fecha de solicitud | Fecha y hora de recibido
$yDatos += $alturaFila;
$pdf->SetXY(10, $yDatos);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta1, $alturaFila, 'Fecha de solicitud:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoValor1, $alturaFila, $fecha_solicitud, 1, 0, 'L', true);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta2, $alturaFila, 'Fecha y hora de recibido:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$fecha_hora_recibido = $fecha_recibido . (!empty($hora_recibido) ? ' ' . $hora_recibido : '');
$pdf->Cell($anchoValor2, $alturaFila, $fecha_hora_recibido, 1, 1, 'L', true);

// Fila 3: Área o proceso solicitante | (vacío)
$yDatos += $alturaFila;
$pdf->SetXY(10, $yDatos);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta1, $alturaFila, 'Área o proceso solicitante:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
// Capitalizar primera letra del departamento
$departamento_formateado = ucfirst(strtolower($documento['departamento_creador'] ?? ''));
$pdf->Cell($anchoValor1, $alturaFila, $departamento_formateado, 1, 0, 'L', true);

// Celda vacía (columna derecha)
$pdf->Cell($anchoEtiqueta2 + $anchoValor2, $alturaFila, '', 1, 1, 'L', true);

// Fila 4: Generador/Cliente | Folio
$yDatos += $alturaFila;
$pdf->SetXY(10, $yDatos);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta1, $alturaFila, 'Generador/Cliente:', 1, 0, 'L', true);
cellAutoFit($pdf, $anchoValor1, $alturaFila, $documento['nombre_cliente'] ?? '');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($anchoEtiqueta2, $alturaFila, 'Folio:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoValor2, $alturaFila, $documento['folio'] ?? '', 1, 1, 'L', true);

// ========================================
// SECCIÓN: DETALLES DEL SERVICIO SOLICITADO
// ========================================

$yDetalles = $yDatos + $alturaFila + 2;

// Título de sección
$pdf->SetXY(10, $yDetalles);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor($colorVerdePrincipal[0], $colorVerdePrincipal[1], $colorVerdePrincipal[2]);
$pdf->Cell($anchoTotal, 7, 'DETALLES DEL SERVICIO SOLICITADO', 1, 1, 'C', true);

// Fila 1: Servicio solicitado con checkboxes
$yServicio = $yDetalles + 7;
$pdf->SetXY(10, $yServicio);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell(28, $alturaFila, 'Servicio solicitado:', 1, 0, 'L', true);

// Opción 1: Tratamiento de agua
$pdf->SetFont('zapfdingbats', '', 9);
$checkTratamiento = ($servicio_seleccionado == 'tratamiento_agua') ? '4' : 'o';
$pdf->Cell(4, $alturaFila, $checkTratamiento, 0, 0, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(30, $alturaFila, ' Tratamiento de agua', 0, 0, 'L');

// Opción 2: Productos químicos y/o residuos
$pdf->SetFont('zapfdingbats', '', 9);
$checkProductos = ($servicio_seleccionado == 'revision_productos') ? '4' : 'o';
$pdf->Cell(4, $alturaFila, $checkProductos, 0, 0, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(42, $alturaFila, ' Productos químicos y/o residuos', 0, 0, 'L');

// Opción 3: Calibración y/o verificación de equipos
$pdf->SetFont('zapfdingbats', '', 9);
$checkCalibracion = ($servicio_seleccionado == 'calibracion_equipos') ? '4' : 'o';
$pdf->Cell(4, $alturaFila, $checkCalibracion, 0, 0, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(52, $alturaFila, ' Calibración y/o verificación de equipos', 0, 0, 'L');

// Opción 4: Otro
$pdf->SetFont('zapfdingbats', '', 9);
$checkOtro = ($servicio_seleccionado == 'otro') ? '4' : 'o';
$pdf->Cell(4, $alturaFila, $checkOtro, 0, 0, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(28, $alturaFila, ' Otro', 1, 1, 'L');

// Fila 2: Otro. Especifique (campo separado)
$yOtro = $yServicio + $alturaFila;
$pdf->SetXY(10, $yOtro);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, $alturaFila, 'Otro. Especifique:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$textoOtroEspecificar = ($servicio_seleccionado == 'otro' && !empty($servicio_otro_texto)) ? $servicio_otro_texto : '';
$pdf->Cell($anchoTotal - 28, $alturaFila, $textoOtroEspecificar, 1, 1, 'L', true);

// ========================================
// DESCRIPCIÓN DEL SERVICIO
// ========================================

$yDescripcion = $yOtro + $alturaFila;
$pdf->SetXY(10, $yDescripcion);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoTotal, 6, 'Descripción del servicio:', 1, 1, 'L', true);

// Área de texto para descripción
$alturaDescripcion = 35;
$pdf->SetXY(10, $yDescripcion + 6);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($anchoTotal, $alturaDescripcion, $documento['descripcion_servicio'] ?? '', 1, 'L', true);

// ========================================
// RESUMEN DE RESULTADOS
// ========================================

$yResumen = $yDescripcion + 6 + $alturaDescripcion;
$pdf->SetXY(10, $yResumen);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoTotal, 6, 'Resumen de resultados:', 1, 1, 'L', true);

// Área de texto para resumen
$alturaResumen = 40;
$pdf->SetXY(10, $yResumen + 6);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($anchoTotal, $alturaResumen, $documento['resumen_resultados'] ?? '', 1, 'L', true);

// ========================================
// FECHA Y HORA DE RESPUESTA
// ========================================

$yRespuesta = $yResumen + 6 + $alturaResumen + 3;
$pdf->SetXY(10, $yRespuesta);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell(45, $alturaFila, 'Fecha y hora de respuesta:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($anchoTotal - 45, $alturaFila, $fecha_hora_respuesta, 1, 1, 'L', true);

// ========================================
// GENERAR Y DESCARGAR PDF
// ========================================

// Limpiar el folio para nombre de archivo seguro
$folioLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documento['folio']);
$nombreArchivo = $folioLimpio . '.pdf';

// Limpiar buffer de salida
if (ob_get_level()) {
    ob_end_clean();
}

// Generar PDF y descargar
$pdf->Output($nombreArchivo, 'D');

exit;