<?php
/**
 * Procesar Eliminar Mantenimiento Histórico
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_eliminar_historico.php
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_ti = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_ti) {
    establecer_alerta('error', 'No tiene permisos para realizar esta acción.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    establecer_alerta('error', 'Registro inválido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$pdo = conectarDB();

$stmt = $pdo->prepare("SELECT folio FROM solicitudes_mantenimiento_ti WHERE id = ? AND es_historico = 1");
$stmt->execute([$id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    establecer_alerta('error', 'Registro histórico no encontrado o ya fue eliminado.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$folio = $registro['folio'];

try {
    $stmt = $pdo->prepare("DELETE FROM solicitudes_mantenimiento_ti WHERE id = ? AND es_historico = 1");
    $stmt->execute([$id]);

    establecer_alerta('success', "Registro histórico {$folio} eliminado correctamente.");
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;

} catch (PDOException $e) {
    error_log("Error eliminando histórico ID {$id}: " . $e->getMessage());
    $msg = 'Error al eliminar el registro.';
    if (strpos($e->getMessage(), 'foreign key') !== false 
     || strpos($e->getMessage(), 'FOREIGN KEY') !== false
     || strpos($e->getMessage(), '1451') !== false) {
        $msg .= ' Tiene registros relacionados (evidencias, comentarios o historial). Solicita asistencia para eliminarlos primero.';
    }
    establecer_alerta('error', $msg);
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento_historico.php?id=' . $id);
    exit;
}