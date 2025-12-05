<?php
/**
 * Server-Sent Events (SSE) para notificaciones en tiempo real
 * VERSIÓN CORREGIDA - Conexión fresca en cada iteración
 */

session_start();

// Configuración para mantener conexión SSE
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
session_write_close();

// Configurar headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
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
    
    if (ob_get_level()) ob_flush();
    flush();
}

// Función para obtener conexión PDO FRESCA (sin cache)
function obtener_conexion_fresca() {
    $host = 'localhost';
    $dbname = 'solicitudes_ti';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("❌ [SSE] Error conexión BD: " . $e->getMessage());
        return null;
    }
}

// Enviar conexión exitosa
enviar_evento_sse('connected', [
    'message' => 'Conectado al servidor de notificaciones',
    'usuario_id' => $usuario_id
]);

// Obtener el último ID al momento de conectar
$ultima_notificacion_id = 0;

$pdo = obtener_conexion_fresca();
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) as ultimo_id FROM notificaciones WHERE usuario_destino = ?");
        $stmt->execute([$usuario_id]);
        $result = $stmt->fetch();
        $ultima_notificacion_id = (int)$result['ultimo_id'];
        error_log("✅ [SSE] Usuario $usuario_id conectado. Último ID: $ultima_notificacion_id");
    } catch (Exception $e) {
        error_log("❌ [SSE] Error al obtener último ID: " . $e->getMessage());
    }
    $pdo = null; // Cerrar conexión
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
        // CRÍTICO: Obtener conexión FRESCA en cada iteración
        $pdo = obtener_conexion_fresca();
        
        if ($pdo) {
            // Buscar notificaciones nuevas
            $stmt = $pdo->prepare("
                SELECT id, tipo, titulo, mensaje, fecha_creacion, datos_json 
                FROM notificaciones 
                WHERE usuario_destino = ? 
                AND id > ? 
                AND leida = 0
                ORDER BY id ASC
                LIMIT 10
            ");
            
            $stmt->execute([$usuario_id, $ultima_notificacion_id]);
            $notificaciones = $stmt->fetchAll();
            
            // Enviar cada notificación nueva
            foreach ($notificaciones as $notif) {
                $datos = [
                    'id' => (int)$notif['id'],
                    'tipo' => $notif['tipo'],
                    'titulo' => $notif['titulo'],
                    'mensaje' => $notif['mensaje'],
                    'fecha' => $notif['fecha_creacion'],
                    'datos' => json_decode($notif['datos_json'], true)
                ];
                
                enviar_evento_sse('notificacion', $datos);
                
                // Actualizar último ID procesado
                $ultima_notificacion_id = (int)$notif['id'];
                
                error_log("📬 [SSE] Notificación #{$notif['id']} enviada a usuario $usuario_id: {$notif['tipo']}");
            }
            
            // CRÍTICO: Cerrar conexión después de cada uso
            $pdo = null;
        }
        
    } catch (Exception $e) {
        error_log("❌ [SSE] Error en stream: " . $e->getMessage());
    }
    
    // Heartbeat cada 10 segundos
    $contador_heartbeat++;
    if ($contador_heartbeat >= 10) {
        enviar_evento_sse('heartbeat', [
            'timestamp' => time(),
            'ultimo_id' => $ultima_notificacion_id
        ]);
        $contador_heartbeat = 0;
    }
    
    // Esperar 1 segundo antes de la siguiente verificación
    sleep(1);
    
    // Forzar flush
    if (ob_get_level()) ob_flush();
    flush();
}