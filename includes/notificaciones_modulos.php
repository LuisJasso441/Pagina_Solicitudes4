<?php
/**
 * Mapa: tipo de notificación → módulo legible
 * Portal de Solicitudes de Atención - Grupo Verden
 *
 * Fuente única de verdad para:
 *   - Agrupar notificaciones no leídas por módulo en el correo de recordatorio.
 *   - En el futuro, filtrar / etiquetar notificaciones en cualquier UI.
 *
 * Cómo agregar un tipo nuevo:
 *   1. Añadir la entrada 'tipo_nuevo' => 'clave_modulo' en $mapa.
 *   2. Si es un módulo nuevo, añadir la clave a $modulos con su etiqueta y URL.
 *   3. Ejecutar diagnosticar_tipos_sin_modulo() para verificar cobertura.
 *
 * IMPORTANTE: los tipos del módulo Vacaciones (actualmente suspendido) están
 * excluidos deliberadamente. Cuando el módulo se reactive, descomentar sus
 * entradas en $mapa y agregar 'vacaciones' a $modulos.
 */

/**
 * Devuelve el arreglo de módulos con su etiqueta legible, orden y URL destino.
 * La URL se usa como link en el correo para llevar al usuario directo al módulo.
 *
 * @return array<string, array{etiqueta:string, orden:int, url:string}>
 */
function obtener_modulos_notificaciones(): array
{
    return [
        'solicitudes_ti' => [
            'etiqueta' => 'Solicitudes de Atención TI',
            'orden'    => 1,
            'url'      => URL_BASE . 'solicitudes/dashboard.php',
        ],
        'osm' => [
            'etiqueta' => 'Órdenes de Servicio de Mantenimiento',
            'orden'    => 2,
            'url'      => URL_BASE . 'ordenes_servicio/dashboard.php',
        ],
        'mantenimiento_ti' => [
            'etiqueta' => 'Solicitudes de Mantenimiento TI',
            'orden'    => 3,
            'url'      => URL_BASE . 'dashboard/sistemas/ti_sistemas/dashboard_mantenimientos.php',
        ],
        'documentos_colaborativos' => [
            'etiqueta' => 'Documentos Colaborativos',
            'orden'    => 4,
            'url'      => URL_BASE . 'colaborativo/dashboard.php',
        ],
        'cqr' => [
            'etiqueta' => 'Cotizaciones (CQR)',
            'orden'    => 5,
            'url'      => URL_BASE . 'dashboard/cotizaciones_qr/dashboard.php',
        ],
        'sec' => [
            'etiqueta' => 'Salidas de Envases (SEC)',
            'orden'    => 6,
            'url'      => URL_BASE . 'dashboard/sec/dashboard.php',
        ],
        'ssc' => [
            'etiqueta' => 'Solicitudes Colaborativas (SSC)',
            'orden'    => 7,
            'url'      => URL_BASE . 'colaborativo/dashboard.php',
        ],
        'bitacora_aires' => [
            'etiqueta' => 'Bitácora de Aires Acondicionados',
            'orden'    => 8,
            'url'      => URL_BASE . 'dashboard/sistemas/bitacora_aires/dashboard.php',
        ],
        'inventario_sistemas' => [
            'etiqueta' => 'Inventario de Sistemas',
            'orden'    => 9,
            'url'      => URL_BASE . 'dashboard/sistemas/ti_sistemas/dashboard.php',
        ],
    ];
}

/**
 * Devuelve el arreglo tipo → clave de módulo.
 * Los tipos excluidos deliberadamente (Vacaciones suspendido) están comentados.
 *
 * @return array<string, string>
 */
function obtener_mapa_tipos_a_modulo(): array
{
    return [
        // === Solicitudes de Atención TI ===
        'nueva_solicitud'             => 'solicitudes_ti',
        'cambio_estado'               => 'solicitudes_ti',

        // === Órdenes de Servicio de Mantenimiento (OSM) ===
        'nueva_orden_mantenimiento'   => 'osm',
        'orden_mantenimiento_nueva'   => 'osm', // legacy (pruebas iniciales)
        'orden_completada'            => 'osm',
        'orden_lista_validacion'      => 'osm',
        'orden_devuelta'              => 'osm',
        'firma_orden_usuario'         => 'osm',
        'orden_en_proceso'            => 'osm',
        'firma_orden_mantenimiento'   => 'osm',
        'orden_corrigiendose'         => 'osm',

        // === Solicitudes de Mantenimiento TI ===
        'mantenimiento_nuevo'         => 'mantenimiento_ti',
        'mantenimiento_firmado'       => 'mantenimiento_ti',
        'firma_mantenimiento'         => 'mantenimiento_ti',
        'mantenimiento_actualizado'   => 'mantenimiento_ti',
        'mantenimiento_finalizado'    => 'mantenimiento_ti',
        'mantenimiento_enviado'       => 'mantenimiento_ti',

        // === Documentos Colaborativos ===
        'documento_comentario'        => 'documentos_colaborativos',
        'documento_nuevo'             => 'documentos_colaborativos',
        'documento_completado'        => 'documentos_colaborativos',
        'documento_seguimiento'       => 'documentos_colaborativos',

        // === Cotizaciones (CQR) ===
        'cqr_comentario'              => 'cqr',
        'cqr_nueva'                   => 'cqr',
        'cqr_respuesta'               => 'cqr',

        // === Bitácora de Aires Acondicionados ===
        'bitacora_aires_prestamo'     => 'bitacora_aires',
        'bitacora_aires_devolucion'   => 'bitacora_aires',

        // === Salidas de Envases (SEC) ===
        'sec_creada'                  => 'sec',
        'sec_entrega_firmada'         => 'sec',
        'sec_recibe_firmada'          => 'sec',
        'sec_cerrada'                 => 'sec',

        // === Solicitudes Colaborativas (SSC) ===
        'ssc_marcado_visto'           => 'ssc',

        // === Inventario Sistemas ===
        'inventario_equipo'           => 'inventario_sistemas',

        // === Módulo Vacaciones (SUSPENDIDO - no incluir en correos) ===
        // 'vacaciones'                => 'vacaciones',
        // 'firma_usuario'             => 'vacaciones',
    ];
}

/**
 * Resuelve la clave de módulo para un tipo dado.
 * Retorna null si el tipo no está mapeado (típicamente porque está excluido
 * deliberadamente o porque es un tipo nuevo que aún no se agregó).
 *
 * @param string $tipo
 * @return string|null
 */
function resolver_modulo_por_tipo(string $tipo): ?string
{
    static $mapa = null;
    if ($mapa === null) {
        $mapa = obtener_mapa_tipos_a_modulo();
    }
    return $mapa[$tipo] ?? null;
}

/**
 * Diagnóstico: detecta tipos que existen en la BD pero no están en el mapa.
 * Útil para correr manualmente después de agregar módulos nuevos al sistema.
 *
 * @param PDO $pdo
 * @return array<int, array{tipo:string, total:int}> Tipos sin módulo (los excluidos deliberadamente también aparecen aquí)
 */
function diagnosticar_tipos_sin_modulo(PDO $pdo): array
{
    $mapa = obtener_mapa_tipos_a_modulo();
    $tipos_mapeados = array_keys($mapa);

    $sql = "SELECT tipo, COUNT(*) AS total FROM notificaciones GROUP BY tipo ORDER BY total DESC";
    $stmt = $pdo->query($sql);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sin_modulo = [];
    foreach ($todos as $fila) {
        if (!in_array($fila['tipo'], $tipos_mapeados, true)) {
            $sin_modulo[] = [
                'tipo'  => $fila['tipo'],
                'total' => (int) $fila['total'],
            ];
        }
    }
    return $sin_modulo;
}