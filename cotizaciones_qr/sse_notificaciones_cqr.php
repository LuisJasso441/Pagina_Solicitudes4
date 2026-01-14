<?php
/**
 * Server-Sent Events para notificaciones del módulo CQR
 * Ubicación: cotizaciones_qr/sse_notificaciones_cqr.php
 */

// Configuración de SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Tiempo máximo de ejecución
set_time_limit(0);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo "event: error\n";
    echo "data: {\"error\": \"No autorizado\"}\n\n";
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Función para enviar evento SSE
function enviar_evento($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Obtener timestamp de última verificación
$ultima_verificacion = $_GET['desde'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

// Mantener conexión abierta
$intentos = 0;
$max_intentos = 30; // 30 segundos máximo

while ($intentos < $max_intentos) {
    // Nueva conexión en cada iteración para evitar cache
    $pdo = conectarDB();
    
    // Buscar notificaciones nuevas de CQR
    $stmt = $pdo->prepare("
        SELECT * FROM notificaciones 
        WHERE usuario_destino = :usuario_id 
        AND tipo LIKE 'cotizacion_%'
        AND leida = 0
        AND fecha_creacion > :desde
        ORDER BY fecha_creacion DESC
        LIMIT 10
    ");
    
    $stmt->execute([
        ':usuario_id' => $usuario_id,
        ':desde' => $ultima_verificacion
    ]);
    
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($notificaciones)) {
        foreach ($notificaciones as $notif) {
            enviar_evento('notificacion_cqr', [
                'id' => $notif['id'],
                'tipo' => $notif['tipo'],
                'titulo' => $notif['titulo'],
                'mensaje' => $notif['mensaje'],
                'datos' => json_decode($notif['datos_json'], true),
                'fecha' => $notif['fecha_creacion']
            ]);
        }
        
        // Actualizar timestamp
        $ultima_verificacion = date('Y-m-d H:i:s');
    }
    
    // Enviar heartbeat
    enviar_evento('heartbeat', ['time' => time()]);
    
    // Cerrar conexión PDO
    $pdo = null;
    
    // Esperar 1 segundo
    sleep(1);
    $intentos++;
    
    // Verificar si la conexión sigue activa
    if (connection_aborted()) {
        break;
    }
}

// Indicar que se debe reconectar
echo "event: reconnect\n";
echo "data: {\"reconnect\": true}\n\n";