<?php
/**
 * Procesador: Crear Nueva Orden de Servicio
 * Usuario crea Apartado 1
 * 
 * NOTIFICACIÓN: Al crear orden → Mantenimiento
 */

session_start();

// CRÍTICO: Limpiar cualquier output buffer
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ordenes_servicio/ordenes_servicio_funciones.php';

// Limpiar output buffer y establecer header JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!sesion_activa()) {
    echo json_encode([
        'success' => false,
        'error' => 'Sesión no válida'
    ]);
    exit;
}

// Verificar que NO sea departamento de Mantenimiento
$departamento_codigo = strtolower($_SESSION['departamento_codigo'] ?? $_SESSION['departamento'] ?? '');
if ($departamento_codigo === 'mantenimiento') {
    echo json_encode([
        'success' => false,
        'error' => 'El departamento de Mantenimiento no puede crear órdenes'
    ]);
    exit;
}

// Detectar si es usuario de Logística (folio autogenerado LOG-###)
$es_logistica = in_array($departamento_codigo, ['logistica', 'almacen_residuos']);

// ========================================
// VALIDACIÓN DE PERMISOS OSM - CREADOR
// ========================================
try {
    $pdo_permisos = conectarDB();
    $stmt_permisos = $pdo_permisos->prepare("
        SELECT creador FROM permisos_osm WHERE user_id = :user_id
    ");
    $stmt_permisos->execute([':user_id' => $_SESSION['usuario_id']]);
    $permisos_osm = $stmt_permisos->fetch(PDO::FETCH_ASSOC);
    
    // Si no tiene registro de permisos o no tiene permiso de creador
    if (!$permisos_osm || $permisos_osm['creador'] != 1) {
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para crear Órdenes de Servicio. Contacte al administrador.'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Error verificando permisos OSM: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar permisos'
    ]);
    exit;
}
// ========================================

// Obtener datos JSON
$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos'
    ]);
    exit;
}

// Validar campos obligatorios
// Para Logística, 'folio' NO viene del cliente (se autogenera en backend)
$campos_requeridos = $es_logistica
    ? ['empresa', 'unidad_equipo', 'prioridad', 'descripcion_falla']
    : ['empresa', 'folio', 'unidad_equipo', 'prioridad', 'descripcion_falla'];
foreach ($campos_requeridos as $campo) {
    if (empty($datos[$campo])) {
        echo json_encode([
            'success' => false,
            'error' => "El campo {$campo} es obligatorio"
        ]);
        exit;
    }
}

// VALIDACIÓN DEL FOLIO
// Logística: se autogenera en backend dentro de la transacción (se asigna en $folio más abajo)
// Otros roles: se valida el folio capturado por el usuario
if ($es_logistica) {
    $folio = null; // Se asignará con generar_folio_logistica_osm() dentro de la transacción
} else {
    $folio = trim($datos['folio']);

    // Verificar que el folio no esté vacío DESPUÉS del trim
    if (empty($folio)) {
        echo json_encode([
            'success' => false,
            'error' => 'El folio no puede estar vacío. Por favor, genera un folio válido.'
        ]);
        exit;
    }

    // Validar formato del folio (no debe contener caracteres especiales peligrosos)
    if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $folio)) {
        echo json_encode([
            'success' => false,
            'error' => 'El folio solo puede contener letras, números, guiones, guiones bajos y barras'
        ]);
        exit;
    }
}

$pdo = conectarDB();
$max_intentos = $es_logistica ? 5 : 1; // Solo Logística reintenta ante race conditions
$orden_id = null;

for ($intento = 1; $intento <= $max_intentos; $intento++) {
    try {
        $pdo->beginTransaction();

        // Logística: generar el folio autoincrement DENTRO de la transacción con FOR UPDATE
        if ($es_logistica) {
            $folio = generar_folio_logistica_osm($pdo);
        } else {
            // Otros roles: verificar que el folio manual no exista
            $stmt = $pdo->prepare("SELECT id, folio FROM ordenes_servicio_mantenimiento WHERE folio = :folio");
            $stmt->execute([':folio' => $folio]);
            $existe = $stmt->fetch();

            if ($existe) {
                error_log("⚠️ FOLIO DUPLICADO - Folio: '$folio', ID existente: {$existe['id']}, Nuevo intento por: {$_SESSION['nombre_completo']}");
                throw new Exception("El folio '{$folio}' ya existe. Por favor, use otro folio o regenere uno nuevo.");
            }
        }

        // Preparar datos del Apartado 1
        $apartado1_data = [
            'empresa' => $datos['empresa'],
            'folio' => $folio,
            'area_solicitante' => $datos['area_solicitante'] ?? $_SESSION['departamento_nombre'],
            'fecha_entrada' => $datos['fecha_entrada'] ?? date('Y-m-d'),
            'hora_entrada' => $datos['hora_entrada'] ?? date('H:i:s'),
            'unidad_equipo' => trim($datos['unidad_equipo']),
            'nombre_solicitante' => $datos['nombre_solicitante'] ?? $_SESSION['nombre_completo'],
            'prioridad' => $datos['prioridad'],
            'descripcion_falla' => trim($datos['descripcion_falla']),
            'evidencia_archivos' => $datos['evidencia_archivos'] ?? []
        ];

        // Insertar orden
        $stmt = $pdo->prepare("
            INSERT INTO ordenes_servicio_mantenimiento (
                folio, usuario_id, usuario_nombre, departamento, empresa,
                estado, apartado1_data, fecha_creacion
            ) VALUES (
                :folio, :usuario_id, :usuario_nombre, :departamento, :empresa,
                'pendiente_mantenimiento', :apartado1_data, NOW()
            )
        ");

        $stmt->execute([
            ':folio' => $folio,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':usuario_nombre' => $_SESSION['nombre_completo'],
            ':departamento' => $_SESSION['departamento_nombre'],
            ':empresa' => $datos['empresa'],
            ':apartado1_data' => json_encode($apartado1_data, JSON_UNESCAPED_UNICODE)
        ]);

        $orden_id = $pdo->lastInsertId();

        // ========================================
        // NOTIFICACIÓN: Nueva orden → Mantenimiento
        // ========================================
        $usuarios_mantenimiento = obtener_usuarios_mantenimiento();

        foreach ($usuarios_mantenimiento as $usuario_mant_id) {
            $stmt_notif = $pdo->prepare("
                INSERT INTO notificaciones 
                (tipo, titulo, mensaje, usuario_destino, datos_json, leida, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");

            $datos_json = json_encode([
                'orden_id' => $orden_id,
                'folio' => $folio,
                'url' => URL_BASE . 'dashboard/ordenes_servicio/ver_orden_servicio.php?id=' . $orden_id
            ]);

            $stmt_notif->execute([
                'nueva_orden_mantenimiento',
                '🔧 Nueva Orden de Servicio',
                "{$_SESSION['nombre_completo']} ha creado la orden $folio",
                $usuario_mant_id,
                $datos_json
            ]);
        }

        $pdo->commit();

        error_log("✅ Nueva orden creada - ID: {$orden_id}, Folio: {$folio}, Usuario: {$_SESSION['nombre_completo']}, Depto: {$_SESSION['departamento_nombre']}" . ($es_logistica ? " (intento {$intento})" : ""));

        echo json_encode([
            'success' => true,
            'orden_id' => $orden_id,
            'folio' => $folio,
            'folio_generado' => $folio, // El JS del modal lo lee para mostrar el folio real (Logística)
            'message' => 'Orden creada exitosamente'
        ]);
        exit; // Éxito, salir del script

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Choque de UNIQUE KEY en Logística → reintentar hasta max_intentos veces con jitter
        if ($es_logistica && $e->getCode() === '23000' && $intento < $max_intentos) {
            error_log("⚠️ Choque UNIQUE en folio LOG- (intento {$intento}/{$max_intentos}) - reintentando...");
            usleep(random_int(50000, 150000)); // 50-150ms jitter
            continue;
        }

        error_log("❌ Error PDO al crear orden: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar la orden: ' . $e->getMessage()
        ]);
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("❌ Error al crear orden: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Si llegamos aquí (solo posible en Logística) es porque agotamos todos los intentos
error_log("❌ Se agotaron los {$max_intentos} intentos de generación de folio LOG-");
echo json_encode([
    'success' => false,
    'error' => 'No se pudo asignar un folio único después de varios intentos. Intente nuevamente.'
]);