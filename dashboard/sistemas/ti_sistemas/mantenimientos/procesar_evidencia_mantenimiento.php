<?php
/**
 * Procesar evidencias de Mantenimiento
 * dashboard/sistemas/ti_sistemas/mantenimientos/procesar_evidencia_mantenimiento.php
 *
 * Acciones (solo Sistemas, solo si la solicitud no esta cerrada/cancelada):
 *   - subir_evidencia
 *   - eliminar_evidencia
 */

session_start();
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . URL_BASE . 'auth/InicioSesion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$departamento = strtolower(trim($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? ''));
$es_sistemas = in_array($departamento, ['ti', 'sistemas', 'ti_sistemas']);

if (!$es_sistemas) {
    establecer_alerta('error', 'No tiene permisos para gestionar evidencias.');
    header('Location: ' . URL_BASE . 'index.php');
    exit;
}

$pdo = conectarDB();

$accion       = $_POST['accion'] ?? '';
$solicitud_id = intval($_POST['solicitud_id'] ?? 0);

if (!$solicitud_id) {
    establecer_alerta('error', 'ID de solicitud no valido.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

// Validar que la solicitud existe y esta en estado editable
$stmt = $pdo->prepare("SELECT id, folio, estado FROM solicitudes_mantenimiento_ti WHERE id = ?");
$stmt->execute([$solicitud_id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    establecer_alerta('error', 'Solicitud no encontrada.');
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/mantenimientos.php');
    exit;
}

$estados_editables = ['pendiente', 'en_proceso', 'finalizada'];
if (!in_array($solicitud['estado'], $estados_editables)) {
    establecer_alerta('error', "No se pueden modificar evidencias en una solicitud {$solicitud['estado']}.");
    header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
    exit;
}

switch ($accion) {
    case 'subir_evidencia':
        subirEvidencia($pdo, $solicitud);
        break;
    case 'eliminar_evidencia':
        eliminarEvidencia($pdo, $solicitud);
        break;
    default:
        establecer_alerta('error', 'Accion no valida.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
}

// =====================================================================
// SUBIR EVIDENCIA
// =====================================================================
function subirEvidencia($pdo, $solicitud) {
    $solicitud_id = $solicitud['id'];
    $folio        = $solicitud['folio'];

    if (empty($_FILES['evidencias']['name'][0])) {
        establecer_alerta('warning', 'No se selecciono ningun archivo.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    // Verificar limite global (max 5 archivos por solicitud)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evidencias_mantenimiento_ti WHERE solicitud_id = ?");
    $stmt->execute([$solicitud_id]);
    $existentes = intval($stmt->fetchColumn());

    $total_nuevos = count($_FILES['evidencias']['name']);
    if ($existentes + $total_nuevos > 5) {
        $disponibles = max(0, 5 - $existentes);
        establecer_alerta('error', "Solo puedes subir {$disponibles} archivo(s) mas (limite: 5 por solicitud).");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    $extensiones_validas = ['jpg', 'jpeg', 'png', 'pdf'];
    $tamanio_max = 10 * 1024 * 1024; // 10 MB

    $archivos_validos = [];
    $errores = [];

    for ($i = 0; $i < $total_nuevos; $i++) {
        if ($_FILES['evidencias']['error'][$i] !== UPLOAD_ERR_OK) {
            $errores[] = "Error al subir archivo: " . $_FILES['evidencias']['name'][$i];
            continue;
        }
        $ext = strtolower(pathinfo($_FILES['evidencias']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $extensiones_validas)) {
            $errores[] = "Formato no permitido: " . $_FILES['evidencias']['name'][$i];
            continue;
        }
        if ($_FILES['evidencias']['size'][$i] > $tamanio_max) {
            $errores[] = "Archivo demasiado grande: " . $_FILES['evidencias']['name'][$i];
            continue;
        }
        $archivos_validos[] = [
            'tmp_name'  => $_FILES['evidencias']['tmp_name'][$i],
            'name'      => $_FILES['evidencias']['name'][$i],
            'size'      => $_FILES['evidencias']['size'][$i],
            'type'      => $_FILES['evidencias']['type'][$i],
            'extension' => $ext,
        ];
    }

    if (!empty($errores)) {
        establecer_alerta('error', implode(' | ', $errores));
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    if (empty($archivos_validos)) {
        establecer_alerta('warning', 'No hay archivos validos para subir.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $base_dir = __DIR__ . '/../../../../Imagenes_Mantenimientos';
        if (!is_dir($base_dir)) {
            @mkdir($base_dir, 0775, true);
        }
        $folio_dir = $base_dir . '/' . $folio;
        if (!is_dir($folio_dir)) {
            @mkdir($folio_dir, 0775, true);
        }

        $sql_evid = "INSERT INTO evidencias_mantenimiento_ti
                     (solicitud_id, nombre_archivo, nombre_original, ruta_archivo, tipo_mime, tamanio, subido_por)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_evid = $pdo->prepare($sql_evid);

        $subidos = 0;
        foreach ($archivos_validos as $arch) {
            $nombre_seguro = $solicitud_id . '_' . uniqid() . '.' . $arch['extension'];
            $ruta_completa = $folio_dir . '/' . $nombre_seguro;
            $ruta_relativa = 'Imagenes_Mantenimientos/' . $folio . '/' . $nombre_seguro;

            if (move_uploaded_file($arch['tmp_name'], $ruta_completa)) {
                $stmt_evid->execute([
                    $solicitud_id,
                    $nombre_seguro,
                    $arch['name'],
                    $ruta_relativa,
                    $arch['type'],
                    $arch['size'],
                    $_SESSION['usuario_id'],
                ]);
                $subidos++;
            }
        }

        // Historial
        if ($subidos > 0) {
            $msg_hist = "Se agregaron {$subidos} evidencia(s) a la solicitud.";
            $sql_hist = "INSERT INTO historial_mantenimientos_ti
                         (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                         VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql_hist);
            $stmt->execute([$solicitud_id, $solicitud['estado'], $solicitud['estado'], $msg_hist, $_SESSION['usuario_id']]);
        }

        $pdo->commit();

        establecer_alerta('success', "{$subidos} archivo(s) subido(s) correctamente.");
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al subir evidencia: " . $e->getMessage());
        establecer_alerta('error', 'Error al subir las evidencias.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }
}

// =====================================================================
// ELIMINAR EVIDENCIA
// =====================================================================
function eliminarEvidencia($pdo, $solicitud) {
    $solicitud_id  = $solicitud['id'];
    $evidencia_id  = intval($_POST['evidencia_id'] ?? 0);

    if (!$evidencia_id) {
        establecer_alerta('error', 'ID de evidencia no valido.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM evidencias_mantenimiento_ti WHERE id = ? AND solicitud_id = ?");
        $stmt->execute([$evidencia_id, $solicitud_id]);
        $evid = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evid) {
            establecer_alerta('error', 'Evidencia no encontrada.');
            header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
            exit;
        }

        $pdo->beginTransaction();

        // Borrar archivo fisico
        $ruta_completa = __DIR__ . '/../../../../' . $evid['ruta_archivo'];
        if (file_exists($ruta_completa)) {
            @unlink($ruta_completa);
        }

        // Borrar registro
        $stmt = $pdo->prepare("DELETE FROM evidencias_mantenimiento_ti WHERE id = ?");
        $stmt->execute([$evidencia_id]);

        // Historial
        $msg_hist = "Se elimino la evidencia: " . $evid['nombre_original'];
        $sql_hist = "INSERT INTO historial_mantenimientos_ti
                     (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql_hist);
        $stmt->execute([$solicitud_id, $solicitud['estado'], $solicitud['estado'], $msg_hist, $_SESSION['usuario_id']]);

        $pdo->commit();

        establecer_alerta('success', 'Evidencia eliminada correctamente.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al eliminar evidencia: " . $e->getMessage());
        establecer_alerta('error', 'Error al eliminar la evidencia.');
        header('Location: ' . URL_BASE . 'dashboard/sistemas/ti_sistemas/mantenimientos/ver_mantenimiento.php?id=' . $solicitud_id);
        exit;
    }
}