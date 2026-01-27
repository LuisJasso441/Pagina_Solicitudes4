<?php
/**
 * Procesar creación de solicitud (AJAX)
 * Devuelve respuesta en formato JSON
 * 
 * ACTUALIZADO: Manejo de archivos adjuntos en Imagenes_Tickets
 * ACTUALIZADO: Guarda el nombre_solicitante editable
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión expirada. Por favor, inicia sesión nuevamente.'
    ]);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

try {
    // Validar campos obligatorios
    $errores = [];
    
    if (empty($_POST['tipo_soporte'])) $errores[] = "El tipo de soporte es obligatorio";
    if (empty($_POST['descripcion'])) $errores[] = "La descripción es obligatoria";
    if (empty($_POST['prioridad'])) $errores[] = "La prioridad es obligatoria";
    
    // Validar campos condicionales
    if ($_POST['tipo_soporte'] == 'Apoyo' && empty($_POST['tipo_apoyo'])) {
        $errores[] = "Debe seleccionar el tipo de apoyo";
    }
    if ($_POST['tipo_soporte'] == 'Problema' && empty($_POST['tipo_problema'])) {
        $errores[] = "Debe seleccionar el tipo de problema";
    }
    
    if (!empty($errores)) {
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $errores)
        ]);
        exit();
    }
    
    // Generar folio único
    $folio = generar_folio('SOL');
    
    // Preparar datos
    $usuario_id = $_SESSION['usuario_id'];
    $departamento = $_SESSION['departamento'];
    $nombre_solicitante = isset($_POST['nombre_completo']) ? limpiar_dato($_POST['nombre_completo']) : $_SESSION['nombre_completo'];
    $tipo_soporte = limpiar_dato($_POST['tipo_soporte']);
    $tipo_apoyo = isset($_POST['tipo_apoyo']) ? limpiar_dato($_POST['tipo_apoyo']) : null;
    $tipo_problema = isset($_POST['tipo_problema']) ? limpiar_dato($_POST['tipo_problema']) : null;
    $descripcion = limpiar_dato($_POST['descripcion']);
    $prioridad = limpiar_dato($_POST['prioridad']);
    $estado = ESTADO_PENDIENTE;
    
    // Conectar a BD
    $pdo = conectarDB();
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Insertar solicitud (INCLUYE nombre_solicitante)
    $stmt = $pdo->prepare("
        INSERT INTO solicitudes_atencion (
            folio, usuario_id, departamento, nombre_solicitante, tipo_soporte, tipo_apoyo, tipo_problema,
            descripcion, prioridad, estado, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $folio, $usuario_id, $departamento, $nombre_solicitante, $tipo_soporte, $tipo_apoyo, $tipo_problema,
        $descripcion, $prioridad, $estado
    ]);
    
    // Obtener el ID de la solicitud insertada
    $solicitud_id = $pdo->lastInsertId();
    
    // ====================================
    // PROCESAR ARCHIVOS ADJUNTOS
    // ====================================
    $archivos_subidos = [];
    
    if (isset($_FILES['evidencia_error']) && !empty($_FILES['evidencia_error']['name'][0])) {
        
        // Crear carpeta Imagenes_Tickets si no existe
        $carpeta_destino = __DIR__ . '/../Imagenes_Tickets';
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0755, true);
        }
        
        // Crear subcarpeta por folio para mejor organización
        $carpeta_folio = $carpeta_destino . '/' . $folio;
        if (!is_dir($carpeta_folio)) {
            mkdir($carpeta_folio, 0755, true);
        }
        
        // Tipos MIME permitidos
        $tipos_permitidos = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        // Tamaño máximo (10MB)
        $max_size = 10 * 1024 * 1024;
        
        // Procesar cada archivo (máximo 5)
        $total_archivos = min(count($_FILES['evidencia_error']['name']), 5);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            $nombre_original = $_FILES['evidencia_error']['name'][$i];
            $tipo_mime = $_FILES['evidencia_error']['type'][$i];
            $tamanio = $_FILES['evidencia_error']['size'][$i];
            $tmp_name = $_FILES['evidencia_error']['tmp_name'][$i];
            $error = $_FILES['evidencia_error']['error'][$i];
            
            // Saltar si no hay archivo
            if (empty($nombre_original) || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Validar error de subida
            if ($error !== UPLOAD_ERR_OK) {
                continue; // Ignorar archivos con error
            }
            
            // Validar tipo MIME
            if (!in_array($tipo_mime, $tipos_permitidos)) {
                continue; // Ignorar tipos no permitidos
            }
            
            // Validar tamaño
            if ($tamanio > $max_size) {
                continue; // Ignorar archivos muy grandes
            }
            
            // Generar nombre único para el archivo
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nombre_guardado = 'ticket_' . $solicitud_id . '_' . uniqid() . '.' . strtolower($extension);
            $ruta_completa = $carpeta_folio . '/' . $nombre_guardado;
            $ruta_relativa = 'Imagenes_Tickets/' . $folio . '/' . $nombre_guardado;
            
            // Mover archivo
            if (move_uploaded_file($tmp_name, $ruta_completa)) {
                
                // Guardar en tabla archivos_adjuntos
                $stmt_archivo = $pdo->prepare("
                    INSERT INTO archivos_adjuntos 
                    (solicitud_id, nombre_archivo, ruta_archivo, tipo_mime, tamanio, fecha_subida)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt_archivo->execute([
                    $solicitud_id,
                    $nombre_original,
                    $ruta_relativa,
                    $tipo_mime,
                    $tamanio
                ]);
                
                $archivos_subidos[] = [
                    'nombre' => $nombre_original,
                    'ruta' => $ruta_relativa,
                    'tipo' => $tipo_mime,
                    'tamanio' => $tamanio
                ];
            }
        }
    }
    
    // ====================================
    // REGISTRAR EN HISTORIAL DE ESTADOS
    // ====================================
    $stmt_historial = $pdo->prepare("
        INSERT INTO historial_estados 
        (solicitud_id, estado_anterior, estado_nuevo, comentario, usuario_id, fecha_cambio)
        VALUES (?, NULL, ?, 'Solicitud creada', ?, NOW())
    ");
    $stmt_historial->execute([$solicitud_id, $estado, $usuario_id]);
    
    // Confirmar transacción
    $pdo->commit();
    
    // ====================================
    // ENVIAR NOTIFICACIÓN A TI
    // ====================================
    require_once __DIR__ . '/../includes/notificaciones.php';
    
    notificar_nueva_solicitud(
        $folio,
        $nombre_solicitante,
        $_SESSION['departamento_nombre'],
        $prioridad
    );
    
    // Construir mensaje de respuesta
    $mensaje = "Solicitud creada exitosamente con folio: <strong>{$folio}</strong>";
    if (count($archivos_subidos) > 0) {
        $mensaje .= "<br><small>" . count($archivos_subidos) . " archivo(s) adjuntado(s)</small>";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'folio' => $folio,
        'archivos' => $archivos_subidos
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error al crear solicitud: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la solicitud: ' . $e->getMessage()
    ]);
}