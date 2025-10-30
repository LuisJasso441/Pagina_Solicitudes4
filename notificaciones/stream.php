<?php
/**
 * Server-Sent Events (SSE) para notificaciones en tiempo real
 */

session_start();

// Configuración para mantener conexión SSE más tiempo
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 1);

require_once __DIR__ . '/../config/config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    exit('No autorizado');
}

$usuario_id = $_SESSION['usuario_id'];

// ============================================
// CRÍTICO: Cerrar la sesión inmediatamente
// Esto permite que otras peticiones no queden bloqueadas
// ============================================
session_write_close();

// Configurar headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Deshabilitar output buffering
if (ob_get_level()) ob_end_clean();

// Función para enviar evento SSE
function enviar_evento_sse($evento, $datos) {
    echo "event: $evento\n";
    echo "data: " . json_encode($datos) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// Enviar conexión exitosa
enviar_evento_sse('connected', ['message' => 'Conectado al servidor de notificaciones']);

// Obtener ID de la última notificación al conectar
$ultima_notificacion_id = 0;

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    // Al conectar, obtener el ID de la última notificación
    $stmt = $pdo->prepare("
        SELECT MAX(id) as ultimo_id
        FROM notificaciones 
        WHERE usuario_destino = ?
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $ultima_notificacion_id = $result['ultimo_id'] ?? 0;
    
} catch (Exception $e) {
    error_log("Error al obtener última notificación: " . $e->getMessage());
}

// Loop infinito para mantener conexión
$contador_heartbeat = 0;

while (true) {
    // Verificar si la conexión sigue activa
    if (connection_aborted()) {
        break;
    }
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        // Solo buscar notificaciones más recientes que el último ID
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_destino = ? 
            AND id > ? 
            AND leida = 0
            ORDER BY id ASC
        ");
        
        $stmt->execute([$usuario_id, $ultima_notificacion_id]);
        $notificaciones = $stmt->fetchAll();
        
        // Enviar cada notificación nueva
        foreach ($notificaciones as $notif) {
            $datos = [
                'id' => $notif['id'],
                'tipo' => $notif['tipo'],
                'titulo' => $notif['titulo'],
                'mensaje' => $notif['mensaje'],
                'fecha' => $notif['fecha_creacion'],
                'datos' => json_decode($notif['datos_json'], true)
            ];
            
            enviar_evento_sse('notificacion', $datos);
            
            // Actualizar último ID procesado
            $ultima_notificacion_id = $notif['id'];
        }
        
    } catch (Exception $e) {
        error_log("Error en SSE stream: " . $e->getMessage());
    }
    
    // Enviar heartbeat cada 30 segundos
    $contador_heartbeat++;
    if ($contador_heartbeat >= 10) {
        enviar_evento_sse('heartbeat', ['timestamp' => time()]);
        $contador_heartbeat = 0;
    }
    
    // Esperar 3 segundos antes de la siguiente verificación
    sleep(3);
}
?>