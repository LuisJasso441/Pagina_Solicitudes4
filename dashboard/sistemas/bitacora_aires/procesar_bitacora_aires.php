<?php
/**
 * Procesador de acciones - Bitácora de Aires Acondicionados
 * Ubicación: dashboard/sistemas/bitacora_aires/procesar_bitacora_aires.php
 *
 * Acciones soportadas:
 *   - pedir    : registra un nuevo préstamo (usuario logueado)
 *   - devolver : cierra un préstamo existente (solo el dueño)
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/sistemas/bitacora_aires/bitacora_aires_funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    establecer_alerta('error', 'Método no permitido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$usuario_id      = (int)$_SESSION['usuario_id'];
$nombre_completo = $_SESSION['nombre_completo'] ?? '';

// =====================================================================
// HELPER: validar payload de firma (data:image/png;base64,...)
// =====================================================================
function ba_validar_firma($firma) {
    if (empty($firma) || !is_string($firma)) return false;
    if (strpos($firma, 'data:image/') !== 0) return false;
    if (strpos($firma, ';base64,') === false) return false;
    // Tamaño mínimo razonable para considerar que hay algo dibujado
    if (strlen($firma) < 200) return false;
    return true;
}

// =====================================================================
// ACCIÓN: PEDIR CONTROL
// =====================================================================
if ($accion === 'pedir') {

    $firma_prestamo = $_POST['firma_prestamo'] ?? '';

    if (!ba_validar_firma($firma_prestamo)) {
        establecer_alerta('error', 'La firma es requerida y debe ser válida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
        exit;
    }

    // Obtener departamento limpio del usuario (solo nombre, sin oficina/sala)
    $info_depto = ba_obtener_departamento_usuario($usuario_id);
    if (!$info_depto || empty($info_depto['nombre'])) {
        establecer_alerta('error', 'No se pudo determinar tu departamento. Contacta a Sistemas.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
        exit;
    }

    $resultado = ba_registrar_prestamo([
        'usuario_id'          => $usuario_id,
        'nombre_completo'     => $nombre_completo,
        'departamento_id'     => (int)$info_depto['id'],
        'departamento_nombre' => $info_depto['nombre'],
        'firma_prestamo'      => $firma_prestamo,
    ]);

    if ($resultado['success']) {
        ba_notificar_sistemas_nuevo_prestamo(
            $resultado['id'],
            $nombre_completo,
            $info_depto['nombre']
        );
        establecer_alerta('success', 'Préstamo registrado. Sistemas ha sido notificado.');
    } else {
        establecer_alerta('error', $resultado['message']);
    }

    header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
    exit;
}

// =====================================================================
// ACCIÓN: DEVOLVER CONTROL
// =====================================================================
if ($accion === 'devolver') {

    $registro_id   = (int)($_POST['registro_id'] ?? 0);
    $firma_entrega = $_POST['firma_entrega'] ?? '';

    if ($registro_id <= 0) {
        establecer_alerta('error', 'Registro no válido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
        exit;
    }

    if (!ba_validar_firma($firma_entrega)) {
        establecer_alerta('error', 'La firma de devolución es requerida y debe ser válida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
        exit;
    }

    $resultado = ba_registrar_devolucion($registro_id, $usuario_id, $firma_entrega);

    if ($resultado['success']) {
        // Notificar a Sistemas que el control fue devuelto
        $reg = ba_obtener_registro($registro_id);
        if ($reg) {
            ba_notificar_sistemas_devolucion(
                $registro_id,
                $reg['nombre_completo'],
                $reg['departamento_nombre']
            );
        }
        establecer_alerta('success', $resultado['message']);
    } else {
        establecer_alerta('error', $resultado['message']);
    }

    header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
    exit;
}

// =====================================================================
// Acción no reconocida
// =====================================================================
establecer_alerta('error', 'Acción no reconocida.');
header('Location: ' . URL_BASE . 'dashboard/sistemas/bitacora_aires/bitacora_aires.php');
exit;