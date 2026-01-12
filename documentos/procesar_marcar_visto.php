<?php
/**
 * Procesar: Marcar documento SSC como visto
 * Solo Laboratorio puede ejecutar esta acción
 * Envía notificación al departamento creador (Normatividad/Ventas)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/verificar_sesion.php';
require_once __DIR__ . '/../includes/notificaciones.php';

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Verificar que sea de Laboratorio
$departamento = $_SESSION['departamento'] ?? '';
if (strtolower($departamento) !== 'laboratorio') {
    echo json_encode(['success' => false, 'message' => 'Solo Laboratorio puede marcar como visto']);
    exit;
}

// Obtener datos
$documento_id = isset($_POST['documento_id']) ? intval($_POST['documento_id']) : 0;
$marcado_visto = isset($_POST['marcado_visto']) ? intval($_POST['marcado_visto']) : 0;

if (!$documento_id) {
    echo json_encode(['success' => false, 'message' => 'ID de documento no válido']);
    exit;
}

// Validar que marcado_visto sea 0 o 1
if (!in_array($marcado_visto, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Valor no válido']);
    exit;
}

try {
    $pdo = conectarDB();
    
    // Obtener información del documento
    $stmt = $pdo->prepare("
        SELECT id, folio, usuario_creador_id, departamento_creador, nombre_cliente
        FROM documentos_colaborativos 
        WHERE id = ?
    ");
    $stmt->execute([$documento_id]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$documento) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    $nombre_usuario = $_SESSION['nombre_completo'];
    
    // Actualizar el campo marcado_visto
    if ($marcado_visto == 1) {
        // Marcar como visto
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos 
            SET marcado_visto = 1,
                fecha_marcado_visto = NOW(),
                usuario_marcado_visto = ?
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id, $documento_id]);
        
        // ⭐ ENVIAR NOTIFICACIÓN al creador del documento
        $titulo = '👁️ Solicitud vista por Laboratorio';
        $mensaje = "Tu solicitud {$documento['folio']} ({$documento['nombre_cliente']}) ha sido vista por {$nombre_usuario}";
        
        crear_notificacion(
            'ssc_marcado_visto',
            $titulo,
            $mensaje,
            $documento['usuario_creador_id'],
            [
                'documento_id' => $documento_id,
                'folio' => $documento['folio'],
                'visto_por' => $nombre_usuario,
                'url' => URL_BASE . 'dashboard/colaborativo/ver_documento.php?id=' . $documento_id
            ]
        );
        
        // Registrar en log
        error_log("SSC {$documento['folio']} marcado como visto por {$nombre_usuario} (ID: {$usuario_id})");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Documento marcado como visto. Se notificó a ' . $documento['departamento_creador'],
            'marcado_visto' => 1,
            'fecha' => date('d/m/Y H:i'),
            'usuario' => $nombre_usuario
        ]);
        
    } else {
        // Desmarcar (quitar visto)
        $stmt = $pdo->prepare("
            UPDATE documentos_colaborativos 
            SET marcado_visto = 0,
                fecha_marcado_visto = NULL,
                usuario_marcado_visto = NULL
            WHERE id = ?
        ");
        $stmt->execute([$documento_id]);
        
        // Registrar en log
        error_log("SSC {$documento['folio']} desmarcado como visto por {$nombre_usuario} (ID: {$usuario_id})");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Documento desmarcado',
            'marcado_visto' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error al marcar documento como visto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}