<?php
/**
 * Configuración de Departamentos
 * Define todos los departamentos de la empresa y sus características
 * NOTA: Se eliminó el sistema de puestos
 */

// ====================================
// LISTA DE DEPARTAMENTOS
// ====================================

$departamentos = [
    'almacen_refacciones' => [
        'nombre' => 'Almacén de refacciones',
        'codigo' => 'ALM_REF',
        'icono' => 'bi-box-seam',
        'color' => '#6366f1'
    ],
    'almacen_residuos' => [
        'nombre' => 'Almacén de residuos',
        'codigo' => 'ALM_RES',
        'icono' => 'bi-recycle',
        'color' => '#10b981'
    ],
    'atencion_clientes' => [
        'nombre' => 'Atención a clientes',
        'codigo' => 'ATC',
        'icono' => 'bi-headset',
        'color' => '#f59e0b'
    ],
    'calidad' => [
        'nombre' => 'Calidad',
        'codigo' => 'CAL',
        'icono' => 'bi-award',
        'color' => '#8b5cf6'
    ],
    'construccion' => [
        'nombre' => 'Construcción',
        'codigo' => 'CON',
        'icono' => 'bi-tools',
        'color' => '#ef4444'
    ],
    'contabilidad' => [
        'nombre' => 'Contabilidad',
        'codigo' => 'CTB',
        'icono' => 'bi-calculator',
        'color' => '#06b6d4'
    ],
    'gestion_talento' => [
        'nombre' => 'Gestión de talento humano',
        'codigo' => 'GTH',
        'icono' => 'bi-people',
        'color' => '#ec4899'
    ],
    'laboratorio' => [
        'nombre' => 'Laboratorio',
        'codigo' => 'LAB',
        'icono' => 'bi-prescription2',
        'color' => '#3b82f6',
        'colaborativo' => true
    ],
    'logistica' => [
        'nombre' => 'Logística',
        'codigo' => 'LOG',
        'icono' => 'bi-truck',
        'color' => '#14b8a6'
    ],
    'mantenimiento' => [
        'nombre' => 'Mantenimiento',
        'codigo' => 'MAN',
        'icono' => 'bi-wrench',
        'color' => '#f97316'
    ],
    'normatividad' => [
        'nombre' => 'Normatividad',
        'codigo' => 'NOR',
        'icono' => 'bi-file-earmark-text',
        'color' => '#6366f1',
        'colaborativo' => true
    ],
    'ptar' => [
        'nombre' => 'PTAR',
        'codigo' => 'PTAR',
        'icono' => 'bi-droplet',
        'color' => '#0ea5e9'
    ],
    'seguridad' => [
        'nombre' => 'Seguridad',
        'codigo' => 'SEG',
        'icono' => 'bi-shield-check',
        'color' => '#dc2626'
    ],
    'sistemas' => [
        'nombre' => 'Sistemas',
        'codigo' => 'SIS',
        'icono' => 'bi-laptop',
        'color' => '#4f46e5',
        'es_ti' => true
    ],
    'tesoreria' => [
        'nombre' => 'Tesorería',
        'codigo' => 'TES',
        'icono' => 'bi-cash-stack',
        'color' => '#059669'
    ],
    'ventas' => [
        'nombre' => 'Ventas',
        'codigo' => 'VEN',
        'icono' => 'bi-cart',
        'color' => '#d946ef',
        'colaborativo' => true
    ]
];

// ====================================
// DEPARTAMENTOS COLABORATIVOS
// ====================================

// Departamentos que comparten base de datos colaborativa
$departamentos_colaborativos = ['normatividad', 'ventas', 'laboratorio'];

// ====================================
// FUNCIONES AUXILIARES
// ====================================

/**
 * Obtener nombre completo del departamento
 */
function obtener_nombre_departamento($codigo) {
    global $departamentos;
    return isset($departamentos[$codigo]) ? $departamentos[$codigo]['nombre'] : 'Desconocido';
}

/**
 * Verificar si un departamento es colaborativo
 */
function es_departamento_colaborativo($codigo) {
    global $departamentos_colaborativos;
    return in_array($codigo, $departamentos_colaborativos);
}

/**
 * Verificar si un departamento es TI/Sistemas
 */
function es_departamento_ti($codigo) {
    global $departamentos;
    return isset($departamentos[$codigo]['es_ti']) && $departamentos[$codigo]['es_ti'] === true;
}

/**
 * Obtener color del departamento
 */
function obtener_color_departamento($codigo) {
    global $departamentos;
    return isset($departamentos[$codigo]) ? $departamentos[$codigo]['color'] : '#6b7280';
}

/**
 * Obtener icono del departamento
 */
function obtener_icono_departamento($codigo) {
    global $departamentos;
    return isset($departamentos[$codigo]) ? $departamentos[$codigo]['icono'] : 'bi-building';
}

?>