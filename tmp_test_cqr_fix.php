<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/cotizaciones_qr/cqr_correos.php';

echo "=== TEST 2: helper _cqr_destinatario_recibio_correo_reciente ===" . PHP_EOL;
$bloq = _cqr_destinatario_recibio_correo_reciente('normatividad.test@verden.local', 'cqr_nueva_cotizacion', 5);
echo "Retorno: " . var_export($bloq, true) . " (false = LIBRE, true = BLOQUEADO)" . PHP_EOL . PHP_EOL;

echo "=== TEST 5: cqr_enviar_correo_nueva_cotizacion (llamada real) ===" . PHP_EOL;
$cotizacion = [
    'id'                   => 16,
    'folio'                => 'CQR-19082026-0016',
    'departamento_creador' => 'ventas',
    'nombre_cliente'       => 'JTEKT MACHINE SYSTEMS MEXICO, S.A. DE C.V.',
    'tipo_cliente'         => 'nuevo',
];
$r = cqr_enviar_correo_nueva_cotizacion($cotizacion);
echo "Retorno: " . var_export($r, true) . PHP_EOL;
