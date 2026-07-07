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
        'color' => '#795548'
    ],
    'almacen_residuos' => [
        'nombre' => 'Almacén de residuos',
        'codigo' => 'ALM_RES',
        'icono' => 'bi-recycle',
        'color' => '#558B2F'
    ],
    'atencion_clientes' => [
        'nombre' => 'Atención a clientes',
        'codigo' => 'ATC',
        'icono' => 'bi-headset',
        'color' => '#1E88E5'
    ],
    'calidad' => [
        'nombre' => 'Calidad',
        'codigo' => 'CAL',
        'icono' => 'bi-award',
        'color' => '#00897B'
    ],
    'contabilidad' => [
        'nombre' => 'Contabilidad',
        'codigo' => 'CTB',
        'icono' => 'bi-calculator',
        'color' => '#2E7D32'
    ],
    'gestion_talento' => [
        'nombre' => 'Gestión de talento humano',
        'codigo' => 'GTH',
        'icono' => 'bi-people',
        'color' => '#C2185B'
    ],
    'laboratorio' => [
        'nombre' => 'Laboratorio',
        'codigo' => 'LAB',
        'icono' => 'bi-prescription2',
        'color' => '#1565C0',
        'colaborativo' => true
    ],
    'logistica' => [
        'nombre' => 'Logística',
        'codigo' => 'LOG',
        'icono' => 'bi-truck',
        'color' => '#EF6C00'
    ],
    'mantenimiento' => [
        'nombre' => 'Mantenimiento',
        'codigo' => 'MAN',
        'icono' => 'bi-wrench',
        'color' => '#616161'
    ],
    'normatividad' => [
        'nombre' => 'Normatividad',
        'codigo' => 'NOR',
        'icono' => 'bi-file-earmark-text',
        'color' => '#3949AB',
        'colaborativo' => true
    ],
    'ptar' => [
        'nombre' => 'PTAR',
        'codigo' => 'PTAR',
        'icono' => 'bi-droplet',
        'color' => '#00796B',
        'colaborativo' => true
    ],
    'seguridad' => [
        'nombre' => 'Seguridad',
        'codigo' => 'SEG',
        'icono' => 'bi-shield-check',
        'color' => '#C62828'
    ],
    'sistemas' => [
        'nombre' => 'Sistemas',
        'codigo' => 'SIS',
        'icono' => 'bi-laptop',
        'color' => '#283593',
        'es_ti' => true
    ],
    'tesoreria' => [
        'nombre' => 'Tesorería',
        'codigo' => 'TES',
        'icono' => 'bi-cash-stack',
        'color' => '#B28704'
    ],
    'ventas' => [
        'nombre' => 'Ventas',
        'codigo' => 'VEN',
        'icono' => 'bi-cart',
        'color' => '#D84315',
        'colaborativo' => true
    ],
    'direccion' => [
        'nombre' => 'Dirección',
        'codigo' => 'DIR',
        'icono' => 'bi-building',
        'color' => '#6A1B9A',
        'colaborativo' => true
    ]
];

// ====================================
// DEPARTAMENTOS COLABORATIVOS
// ====================================

// Departamentos que comparten base de datos colaborativa
$departamentos_colaborativos = ['normatividad', 'ventas', 'laboratorio', 'direccion', 'dirección', 'ptar'];

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