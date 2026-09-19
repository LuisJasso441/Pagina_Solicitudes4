<?php
// Configuración de base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'solicitudes_ti');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Conexión a la base de datos.
 *
 * Singleton por request: la primera llamada crea la conexión PDO,
 * las siguientes reutilizan la misma instancia dentro del mismo request.
 *
 * Esto es crítico para operaciones transaccionales que involucran
 * múltiples funciones (por ejemplo, crear_sec() -> registrar_movimiento()):
 * todas las llamadas comparten la misma transacción y evitan lock contention
 * entre conexiones distintas al mismo InnoDB row.
 *
 * PHP-FPM cierra la conexión al final del request, así que el singleton
 * no persiste entre requests.
 */
function conectarDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET time_zone = '-06:00'");
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    return $pdo;
}