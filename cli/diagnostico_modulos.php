<?php
/**
 * Diagnóstico rápido del mapeo tipo → módulo.
 * Uso desde el contenedor:
 *   docker compose exec web php /var/www/html/Pagina_Solicitudes4/cli/diagnostico_modulos.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notificaciones_modulos.php';

$sin = diagnosticar_tipos_sin_modulo(conectarDB());

if (empty($sin)) {
    echo "OK: todos los tipos están mapeados." . PHP_EOL;
} else {
    echo "Tipos SIN módulo (deben ser solo vacaciones/firma_usuario):" . PHP_EOL;
    foreach ($sin as $t) {
        echo "  - " . $t['tipo'] . " (" . $t['total'] . ")" . PHP_EOL;
    }
}

// Verificar constantes de recordatorio
echo PHP_EOL . "Constantes de recordatorio:" . PHP_EOL;
echo "  RECORDATORIO_DIAS_INACTIVIDAD = " . RECORDATORIO_DIAS_INACTIVIDAD . PHP_EOL;
echo "  RECORDATORIO_COOLDOWN_DIAS    = " . RECORDATORIO_COOLDOWN_DIAS . PHP_EOL;
echo "  RECORDATORIO_TIPO_CORREO      = " . RECORDATORIO_TIPO_CORREO . PHP_EOL;