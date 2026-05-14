<?php
/**
 * Generador de PDF para Órdenes de Servicio de Mantenimiento
 * Genera archivo .pdf basado en la plantilla FR-MT-01
 * Solo permite descargar órdenes con estado "completado"
 * 
 * CARACTERÍSTICAS:
 * - Usa TCPDF para generar el PDF
 * - Replica el diseño de la plantilla FR-MT-01
 * - Inserta imágenes de evidencia desde Imagenes_OSM
 * - Inserta firmas digitales del cierre
 * - Todo en una sola página
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ordenes_servicio/ordenes_servicio_funciones.php';

// Verificar sesión
if (!sesion_activa()) {
    header('Location: ' . URL_BASE . 'auth/login.php');
    exit;
}

// Obtener ID de la orden
$orden_id = $_GET['id'] ?? null;

if (!$orden_id) {
    $_SESSION['error'] = "ID de orden no especificado.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Obtener orden
$orden = obtener_orden_por_id($orden_id);

if (!$orden) {
    $_SESSION['error'] = "Orden no encontrada.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Verificar que la orden esté completada
if ($orden['estado'] !== 'completado') {
    $_SESSION['error'] = "Solo se pueden descargar órdenes completadas.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ver_orden_servicio.php?id=' . $orden_id);
    exit;
}

// Verificar permisos
$permisos = verificar_permiso_orden($orden_id, $_SESSION['usuario_id'], $_SESSION['departamento']);

if (!$permisos['puede_ver']) {
    $_SESSION['error'] = "No tienes permiso para ver esta orden.";
    header('Location: ' . URL_BASE . 'dashboard/ordenes_servicio/ordenes_servicio_mantenimiento.php');
    exit;
}

// Cargar autoloader de Composer (incluye TCPDF)
require_once __DIR__ . '/../../vendor/autoload.php';

// Extraer datos de los apartados
$apartado1 = $orden['apartado1'] ?? [];
$apartado2 = $orden['apartado2'] ?? [];
$apartado3 = $orden['apartado3'] ?? [];

// ========================================
// CONFIGURACIÓN DEL PDF
// ========================================

// Crear nueva instancia de TCPDF
$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator('VerdenCore - Sistema de Órdenes de Servicio');
$pdf->SetAuthor('Sistema OSM');
$pdf->SetTitle('Orden de Servicio - ' . ($apartado1['folio'] ?? $orden['folio']));
$pdf->SetSubject('Orden de Servicio para Mantenimiento');

// Eliminar header y footer por defecto
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Configurar márgenes (izq, arriba, der)
$pdf->SetMargins(10, 8, 10);
$pdf->SetAutoPageBreak(false, 10);

// Agregar página
$pdf->AddPage();

// ========================================
// COLORES Y ESTILOS
// ========================================

// Colores (RGB)
$colorVerdePrincipal = array(146, 208, 80);  // Verde para títulos principales
$colorVerdeSecundario = array(198, 239, 206); // Verde claro para subsecciones
$colorGris = array(217, 217, 217);            // Gris para etiquetas
$colorBlanco = array(255, 255, 255);
$colorNegro = array(0, 0, 0);

// Ancho de página utilizable
$anchoTotal = 196; // mm (216 - 10 - 10 márgenes)

// ========================================
// ENCABEZADO (Logo, Título, Código)
// ========================================

$yInicio = 10;
$alturaEncabezado = 18;

// Celda para Logo/Empresa (columna izquierda)
$pdf->SetXY(10, $yInicio);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);

// Logo o nombre de empresa
$empresa = $apartado1['empresa'] ?? 'RESIMEX';
$rutaLogo = __DIR__ . '/../../assets/img/logo_' . strtolower($empresa) . '.png';
if (!file_exists($rutaLogo)) {
    $rutaLogo = __DIR__ . '/../../assets/img/logo.png';
}

if (file_exists($rutaLogo)) {
    $pdf->Cell(35, $alturaEncabezado, '', 1, 0, 'C', true);
    $pdf->Image($rutaLogo, 12, $yInicio + 2, 30, 0, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(35, $alturaEncabezado, $empresa, 1, 0, 'C', true);
}

// Título central
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell(105, $alturaEncabezado, 'ORDEN DE SERVICIO PARA MANTENIMIENTO', 1, 0, 'C', true);

// Columna derecha (Código, Revisión, Fecha)
$xDerecha = 150;
$anchoDerecha = 56;
$alturaCeldaDerecha = $alturaEncabezado / 3;

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($xDerecha, $yInicio);
$pdf->Cell(28, $alturaCeldaDerecha, 'CÓDIGO:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(28, $alturaCeldaDerecha, 'FR-MT-01', 1, 1, 'C', true);

$pdf->SetXY($xDerecha, $yInicio + $alturaCeldaDerecha);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, $alturaCeldaDerecha, 'No. REVISIÓN:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(28, $alturaCeldaDerecha, '3', 1, 1, 'C', true);

$pdf->SetXY($xDerecha, $yInicio + ($alturaCeldaDerecha * 2));
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(28, $alturaCeldaDerecha, 'FECHA REV:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(28, $alturaCeldaDerecha, '15/10/2025', 1, 1, 'C', true);

// ========================================
// APARTADO 1: SOLICITANTE
// ========================================

$yApartado1 = $yInicio + $alturaEncabezado + 3;

// Título de sección
$pdf->SetXY(10, $yApartado1);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor($colorVerdePrincipal[0], $colorVerdePrincipal[1], $colorVerdePrincipal[2]);
$pdf->Cell($anchoTotal, 7, 'PARA SER LLENADO POR EL SOLICITANTE:', 1, 1, 'C', true);

// Fila 1: Empresa | Folio
$yFila = $yApartado1 + 7;
$anchoEtiqueta = 35;
$anchoValor = 63;

$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Empresa:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['empresa'] ?? 'RESIMEX', 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Folio:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['folio'] ?? $orden['folio'], 1, 1, 'C', true);

// Fila 2: Área solicitante | Fecha entrada
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Área solicitante:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['area_solicitante'] ?? '', 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Fecha entrada:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$fechaEntrada = !empty($apartado1['fecha_entrada']) ? date('d/m/Y', strtotime($apartado1['fecha_entrada'])) : '';
$pdf->Cell($anchoValor, 6, $fechaEntrada, 1, 1, 'C', true);

// Fila 3: Unidad/Equipo | Hora entrada
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Unidad / Equipo:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['unidad_equipo'] ?? '', 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Hora entrada:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['hora_entrada'] ?? '', 1, 1, 'C', true);

// Fila 4: Nombre solicitante | Prioridad
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Nombre solicitante:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['nombre_solicitante'] ?? '', 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Prioridad:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado1['prioridad'] ?? '', 1, 1, 'C', true);

// Descripción de la falla
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorVerdeSecundario[0], $colorVerdeSecundario[1], $colorVerdeSecundario[2]);
$pdf->Cell($anchoTotal, 6, 'DESCRIPCIÓN DE LA FALLA:', 1, 1, 'C', true);

$yFila += 6;
$alturaDescFalla = 18;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->MultiCell($anchoTotal, $alturaDescFalla, $apartado1['descripcion_falla'] ?? '', 1, 'L', true);

// Evidencia de la falla
$yFila += $alturaDescFalla;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorVerdeSecundario[0], $colorVerdeSecundario[1], $colorVerdeSecundario[2]);
$pdf->Cell($anchoTotal, 6, 'EVIDENCIA DE LA FALLA:', 1, 1, 'C', true);

// Área para imágenes de evidencia
$yFila += 6;
$alturaEvidencia = 35;
$pdf->SetXY(10, $yFila);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoTotal, $alturaEvidencia, '', 1, 1, 'C', true);

// Insertar imágenes de evidencia
$archivosAdjuntos = $apartado1['evidencia_archivos'] ?? $apartado1['archivos_adjuntos'] ?? [];
$rutaImagenes = __DIR__ . '/../../Imagenes_OSM/';
$imagenesInsertadas = 0;
$maxImagenes = 3;

// Dimensiones máximas para cada imagen (en mm)
$maxAltoImagen = 32;   // Alto máximo (dejando margen dentro del área)

if (!empty($archivosAdjuntos)) {
    // Contar imágenes válidas
    $imagenesValidas = [];
    foreach ($archivosAdjuntos as $archivo) {
        if (count($imagenesValidas) >= $maxImagenes) break;
        
        $nombreArchivo = $archivo['nombre_guardado'] ?? $archivo['nombre_archivo'] ?? '';
        if (empty($nombreArchivo)) continue;
        
        $rutaCompleta = $rutaImagenes . $nombreArchivo;
        if (!file_exists($rutaCompleta)) continue;
        
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) continue;
        
        $imagenesValidas[] = $rutaCompleta;
    }
    
    $totalImagenes = count($imagenesValidas);
    
    foreach ($imagenesValidas as $rutaCompleta) {
        try {
            // Obtener dimensiones reales de la imagen
            $infoImagen = @getimagesize($rutaCompleta);
            if ($infoImagen === false) continue;
            
            $anchoOriginal = $infoImagen[0];
            $altoOriginal = $infoImagen[1];
            $ratio = $anchoOriginal / $altoOriginal;
            
            // Calcular dimensiones manteniendo proporción
            if ($totalImagenes == 1) {
                // Una sola imagen: centrada y más grande
                $altoFinal = min($maxAltoImagen, $alturaEvidencia - 3);
                $anchoFinal = $altoFinal * $ratio;
                
                // Limitar ancho máximo
                if ($anchoFinal > $anchoTotal - 10) {
                    $anchoFinal = $anchoTotal - 10;
                    $altoFinal = $anchoFinal / $ratio;
                }
                
                // Centrar la imagen
                $xPos = 10 + ($anchoTotal - $anchoFinal) / 2;
                $yPos = $yFila + ($alturaEvidencia - $altoFinal) / 2;
            } else {
                // Múltiples imágenes: distribuir horizontalmente
                $anchoMaxPorImagen = ($anchoTotal - 10 - (($totalImagenes - 1) * 5)) / $totalImagenes;
                $altoFinal = min($maxAltoImagen, $alturaEvidencia - 3);
                $anchoFinal = $altoFinal * $ratio;
                
                // Ajustar si excede el ancho permitido
                if ($anchoFinal > $anchoMaxPorImagen) {
                    $anchoFinal = $anchoMaxPorImagen;
                    $altoFinal = $anchoFinal / $ratio;
                }
                
                // Calcular posición X
                $espacioUsado = $anchoFinal * $totalImagenes + 5 * ($totalImagenes - 1);
                $margenInicial = (($anchoTotal - $espacioUsado) / 2) + 10;
                $xPos = $margenInicial + ($imagenesInsertadas * ($anchoFinal + 5));
                $yPos = $yFila + ($alturaEvidencia - $altoFinal) / 2;
            }
            
            $pdf->Image($rutaCompleta, $xPos, $yPos, $anchoFinal, $altoFinal, '', '', '', true, 300);
            $imagenesInsertadas++;
            
        } catch (Exception $e) {
            error_log("Error insertando imagen: " . $e->getMessage());
        }
    }
}

if ($imagenesInsertadas === 0) {
    $pdf->SetXY(10, $yFila + 12);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell($anchoTotal, 6, '(Sin evidencias de imagen adjuntas)', 0, 1, 'C');
}

// ========================================
// APARTADO 2: MANTENIMIENTO
// ========================================
$yApartado2 = $yFila + $alturaEvidencia + 3;

// Título de sección
$pdf->SetXY(10, $yApartado2);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor($colorVerdePrincipal[0], $colorVerdePrincipal[1], $colorVerdePrincipal[2]);
$pdf->Cell($anchoTotal, 7, 'PARA SER LLENADO POR EL ÁREA DE MANTENIMIENTO:', 1, 1, 'C', true);

// Fila 1: Fecha atención | Hora inicio
$yFila = $yApartado2 + 7;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Fecha atención:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$fechaAtencion = !empty($apartado2['fecha_atencion']) ? date('d/m/Y', strtotime($apartado2['fecha_atencion'])) : '';
$pdf->Cell($anchoValor, 6, $fechaAtencion, 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Hora inicio:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado2['hora_inicio'] ?? '', 1, 1, 'C', true);

// Fila 2: Fecha término | Hora término
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Fecha término:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$fechaTermino = !empty($apartado2['fecha_termino']) ? date('d/m/Y', strtotime($apartado2['fecha_termino'])) : '';
$pdf->Cell($anchoValor, 6, $fechaTermino, 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoEtiqueta, 6, 'Hora término:', 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoValor, 6, $apartado2['hora_termino'] ?? '', 1, 1, 'C', true);

// Descripción de reparación
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorVerdeSecundario[0], $colorVerdeSecundario[1], $colorVerdeSecundario[2]);
$pdf->Cell($anchoTotal, 6, 'DESCRIPCIÓN DE REPARACIÓN DE FALLA:', 1, 1, 'C', true);

$yFila += 6;
$alturaDescRep = 18;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->MultiCell($anchoTotal, $alturaDescRep, $apartado2['descripcion_reparacion'] ?? '', 1, 'L', true);

// Personal asignado
$yFila += $alturaDescRep;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorVerdeSecundario[0], $colorVerdeSecundario[1], $colorVerdeSecundario[2]);
$pdf->Cell($anchoTotal, 6, 'PERSONAL ASIGNADO PARA REALIZAR EL SERVICIO:', 1, 1, 'C', true);

// Encabezados tabla personal
$yFila += 6;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoTotal / 2, 5, 'Nombre', 1, 0, 'C', true);
$pdf->Cell($anchoTotal / 2, 5, 'Firma', 1, 1, 'C', true);

// Filas de personal (máximo 3)
$personalAsignado = $apartado2['personal_asignado'] ?? [];
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);

for ($i = 0; $i < 3; $i++) {
    $yFila += 5;
    $pdf->SetXY(10, $yFila);
    $nombrePersonal = isset($personalAsignado[$i]['nombre']) ? $personalAsignado[$i]['nombre'] : '';
    $pdf->Cell($anchoTotal / 2, 5, $nombrePersonal, 1, 0, 'C', true);
    $pdf->Cell($anchoTotal / 2, 5, '', 1, 1, 'C', true);
}

// ========================================
// APARTADO 3: CIERRE
// ========================================

$yApartado3 = $yFila + 5 + 3;

// Título de sección
$pdf->SetXY(10, $yApartado3);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($colorVerdeSecundario[0], $colorVerdeSecundario[1], $colorVerdeSecundario[2]);
$pdf->Cell($anchoTotal, 6, 'CIERRE DE ORDEN DE SERVICIO:', 1, 1, 'C', true);

// Fila: Nombre solicitante | Firma solicitante
$yFila = $yApartado3 + 6;
$alturaFirma = 18;
$anchoNombreLabel = 45;
$anchoNombreValor = 53;
$anchoFirmaLabel = 45;
$anchoFirmaValor = 53;

$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoNombreLabel, $alturaFirma, "Nombre del solicitante:", 1, 0, 'C', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$nombreSolicitanteCierre = $apartado3['nombre_solicitante'] ?? $apartado1['nombre_solicitante'] ?? '';
$pdf->Cell($anchoNombreValor, $alturaFirma, $nombreSolicitanteCierre, 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->Cell($anchoFirmaLabel, $alturaFirma, "Firma del solicitante:", 1, 0, 'C', true);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoFirmaValor, $alturaFirma, '', 1, 1, 'C', true);

// Insertar firma del solicitante
if (!empty($apartado3['firma_solicitante'])) {
    $firmaTemp = insertarFirmaEnPDF($pdf, $apartado3['firma_solicitante'], 10 + $anchoNombreLabel + $anchoNombreValor + $anchoFirmaLabel + 5, $yFila + 2, $anchoFirmaValor - 10, $alturaFirma - 4);
}

// Fila: Nombre responsable | Firma responsable
$yFila += $alturaFirma;
$pdf->SetXY(10, $yFila);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->MultiCell($anchoNombreLabel, $alturaFirma, "Nombre responsable\nde mantenimiento:", 1, 'C', true, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoNombreValor, $alturaFirma, $apartado3['nombre_responsable_mantenimiento'] ?? '', 1, 0, 'C', true);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor($colorGris[0], $colorGris[1], $colorGris[2]);
$pdf->MultiCell($anchoFirmaLabel, $alturaFirma, "Firma responsable\nde mantenimiento:", 1, 'C', true, 0);
$pdf->SetFillColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
$pdf->Cell($anchoFirmaValor, $alturaFirma, '', 1, 1, 'C', true);

// Insertar firma del responsable de mantenimiento
if (!empty($apartado3['firma_responsable_mantenimiento'])) {
    $firmaTemp = insertarFirmaEnPDF($pdf, $apartado3['firma_responsable_mantenimiento'], 10 + $anchoNombreLabel + $anchoNombreValor + $anchoFirmaLabel + 5, $yFila + 2, $anchoFirmaValor - 10, $alturaFirma - 4);
}

// ========================================
// GENERAR Y DESCARGAR PDF
// ========================================

// Limpiar el folio para nombre de archivo seguro
$folioLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $orden['folio']);
$nombreArchivo = 'Orden_Servicio_' . $folioLimpio . '_' . date('Ymd_His') . '.pdf';

// Limpiar buffer de salida
if (ob_get_level()) {
    ob_end_clean();
}

// Generar PDF
$pdf->Output($nombreArchivo, 'D');

exit;

// ========================================
// FUNCIONES AUXILIARES
// ========================================

/**
 * Inserta una firma digital como imagen en el PDF
 */
function insertarFirmaEnPDF($pdf, $firmaData, $x, $y, $ancho, $alto) {
    try {
        // Limpiar datos base64
        if (strpos($firmaData, 'data:image') === 0) {
            $firmaData = preg_replace('#^data:image/\w+;base64,#i', '', $firmaData);
        }
        
        $firmaImagen = base64_decode($firmaData);
        
        if (!$firmaImagen) {
            return false;
        }
        
        // Crear archivo temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'firma_pdf_') . '.png';
        file_put_contents($tempFile, $firmaImagen);
        
        // Insertar imagen
        $pdf->Image($tempFile, $x, $y, $ancho, $alto, 'PNG', '', '', true, 150, '', false, false, 0, 'CM');
        
        // Eliminar archivo temporal
        @unlink($tempFile);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error insertando firma en PDF: " . $e->getMessage());
        return false;
    }
}