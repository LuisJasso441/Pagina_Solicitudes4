<?php
/**
 * Procesar Acceso de Equipos - Crear/Editar/Eliminar
 * dashboard/sistemas/ti_sistemas/procesar_acceso.php
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
if ($departamento !== 'sistemas') {
    establecer_alerta('error', 'No tiene permisos para realizar esta acci&oacute;n.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/acceso_equipos.php');
    exit;
}

$pdo = conectarDB();
$accion = $_POST['accion'] ?? '';
$redirect = URL_BASE . 'dashboard/sistemas/ti_sistemas/acceso_equipos.php';

switch ($accion) {
    case 'crear':
        $hostname = strtoupper(trim($_POST['hostname'] ?? ''));
        if (empty($hostname)) {
            establecer_alerta('error', 'El hostname es obligatorio.');
            header('Location: ' . $redirect);
            exit;
        }
        
        // Verificar duplicado
        $stmt = $pdo->prepare("SELECT id FROM acceso_equipos WHERE hostname = ?");
        $stmt->execute([$hostname]);
        if ($stmt->fetch()) {
            establecer_alerta('error', "Ya existe un registro de acceso para {$hostname}.");
            header('Location: ' . $redirect);
            exit;
        }
        
        try {
            $sql = "INSERT INTO acceso_equipos 
                    (hostname, departamento_id, direccion_ip, contrasena_equipo, 
                    usuario_asignado_nombre, estado, whoami, usuario_servidor, comentarios, registrado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $hostname,
                !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null,
                trim($_POST['direccion_ip'] ?? '') ?: null,
                trim($_POST['contrasena_equipo'] ?? '') ?: null,
                trim($_POST['usuario_asignado_nombre'] ?? '') ?: null,
                $_POST['estado'] ?? 'activo',
                trim($_POST['whoami'] ?? '') ?: null,
                trim($_POST['usuario_servidor'] ?? '') ?: null,
                trim($_POST['comentarios'] ?? '') ?: null,
                $_SESSION['usuario_id']
            ]);
            establecer_alerta('success', "Acceso para {$hostname} registrado exitosamente.");
        } catch (PDOException $e) {
            error_log("Error al crear acceso: " . $e->getMessage());
            establecer_alerta('error', 'Error al guardar el registro de acceso.');
        }
        break;
        
    case 'editar':
        $acceso_id = intval($_POST['acceso_id'] ?? 0);
        $hostname = strtoupper(trim($_POST['hostname'] ?? ''));
        
        if (!$acceso_id || empty($hostname)) {
            establecer_alerta('error', 'Datos inv&aacute;lidos.');
            header('Location: ' . $redirect);
            exit;
        }
        
        // Verificar duplicado (excluyendo el registro actual)
        $stmt = $pdo->prepare("SELECT id FROM acceso_equipos WHERE hostname = ? AND id != ?");
        $stmt->execute([$hostname, $acceso_id]);
        if ($stmt->fetch()) {
            establecer_alerta('error', "Ya existe otro registro de acceso para {$hostname}.");
            header('Location: ' . $redirect);
            exit;
        }
        
        try {
            $sql = "UPDATE acceso_equipos SET 
                    hostname = ?, departamento_id = ?, direccion_ip = ?, contrasena_equipo = ?,
                    usuario_asignado_nombre = ?, estado = ?, whoami = ?, usuario_servidor = ?, comentarios = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $hostname,
                !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null,
                trim($_POST['direccion_ip'] ?? '') ?: null,
                trim($_POST['contrasena_equipo'] ?? '') ?: null,
                trim($_POST['usuario_asignado_nombre'] ?? '') ?: null,
                $_POST['estado'] ?? 'activo',
                trim($_POST['whoami'] ?? '') ?: null,
                trim($_POST['usuario_servidor'] ?? '') ?: null,
                trim($_POST['comentarios'] ?? '') ?: null,
                $acceso_id
            ]);
            establecer_alerta('success', "Acceso para {$hostname} actualizado exitosamente.");
        } catch (PDOException $e) {
            error_log("Error al editar acceso: " . $e->getMessage());
            establecer_alerta('error', 'Error al actualizar el registro de acceso.');
        }
        break;
        
    case 'eliminar':
        $acceso_id = intval($_POST['acceso_id'] ?? 0);
        if (!$acceso_id) {
            establecer_alerta('error', 'ID no v&aacute;lido.');
            header('Location: ' . $redirect);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT hostname FROM acceso_equipos WHERE id = ?");
            $stmt->execute([$acceso_id]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM acceso_equipos WHERE id = ?");
            $stmt->execute([$acceso_id]);
            
            establecer_alerta('success', "Acceso para " . ($acc['hostname'] ?? '') . " eliminado.");
        } catch (PDOException $e) {
            error_log("Error al eliminar acceso: " . $e->getMessage());
            establecer_alerta('error', 'Error al eliminar el registro.');
        }
        break;
        
    default:
        establecer_alerta('error', 'Acci&oacute;n no v&aacute;lida.');
}

header('Location: ' . $redirect);
exit;