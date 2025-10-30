<?php
/**
 * Contar notificaciones no leídas
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = conectarDB();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM notificaciones 
        WHERE usuario_destino = ? AND leida = 0
    ");
    
    $stmt->execute([$_SESSION['usuario_id']]);
    $resultado = $stmt->fetch();
    
    echo json_encode(['count' => (int)$resultado['total']]);
    
} catch (Exception $e) {
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>