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

// CRÍTICO: Cerrar la sesión inmediatamente
// Esto permite que otras peticiones no queden bloqueadas
session_write_close();

// Configurar headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Deshabilitar output buffering completamente
while (ob_get_level()) {
    ob_end_clean();
}

// Función para enviar evento SSE
function enviar_evento_sse($evento, $datos) {
    echo "event: $evento\n";
    echo "data: " . json_encode($datos, JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Forzar envío inmediato
    if (ob_get_level()) ob_flush();
    flush();
}

// Enviar conexión exitosa
enviar_evento_sse('connected', ['message' => 'Conectado al servidor de notificaciones']);

// Obtener el último ID al momento de conectar
// Esto evita mostrar notificaciones viejas
$ultima_notificacion_id = 0;

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    // Obtener el ID más reciente al momento de conectar
    // Solo mostraremos notificaciones NUEVAS desde este momento
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(id), 0) as ultimo_id
        FROM notificaciones 
        WHERE usuario_destino = ?
    ");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();
    $ultima_notificacion_id = $result['ultimo_id'];
    
    error_log("✅ [SSE] Usuario $usuario_id conectado. Último ID: $ultima_notificacion_id");
    
} catch (Exception $e) {
    error_log("❌ [SSE] Error al inicializar: " . $e->getMessage());
}

// Loop infinito para mantener conexión
$contador_heartbeat = 0;

while (true) {
    // Verificar si la conexión sigue activa
    if (connection_aborted()) {
        error_log("🔌 [SSE] Usuario $usuario_id desconectado");
        break;
    }
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
        
        // Solo buscar notificaciones con ID mayor al último procesado
        // Sin filtro de tiempo para no perder notificaciones
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_destino = ? 
            AND id > ? 
            AND leida = 0
            ORDER BY id ASC
            LIMIT 10
        ");
        
        $stmt->execute([$usuario_id, $ultima_notificacion_id]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            
            error_log("📬 [SSE] Notificación #{$notif['id']} enviada a usuario $usuario_id: {$notif['tipo']}");
        }
        
    } catch (Exception $e) {
        error_log("❌ [SSE] Error en stream: " . $e->getMessage());
    }
    
    // Heartbeat cada 15 segundos
    $contador_heartbeat++;
    if ($contador_heartbeat >= 15) {
        enviar_evento_sse('heartbeat', ['timestamp' => time()]);
        $contador_heartbeat = 0;
    }
    
    // Esperar 1 segundo antes de la siguiente verificación
    sleep(1);
    
    // Forzar flush después de cada iteración
    if (ob_get_level()) ob_flush();
    flush();
}
?>