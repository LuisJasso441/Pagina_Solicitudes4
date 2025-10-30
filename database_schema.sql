-- ============================================
-- BASE DE DATOS: solicitudes_ti
-- Sistema de Gestión de Solicitudes de TI
-- ============================================

DROP DATABASE IF EXISTS solicitudes_ti;
CREATE DATABASE solicitudes_ti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE solicitudes_ti;

-- ============================================
-- TABLA: usuarios
-- ============================================
CREATE TABLE usuarios (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre_completo VARCHAR(150) NOT NULL,
  usuario VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  departamento VARCHAR(100) NOT NULL,
  email VARCHAR(100) DEFAULT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  es_colaborativo TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY usuario (usuario),
  KEY idx_departamento (departamento),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USUARIOS DE PRUEBA (password: admin123)
-- ============================================
INSERT INTO usuarios (nombre_completo, usuario, password, departamento, es_colaborativo, activo) VALUES
('Juan Pérez', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sistemas', 0, 1),
('María González', 'sistemas', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sistemas', 0, 1),
('Roberto Sánchez', 'roberto', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Laboratorio', 1, 1),
('Carmen López', 'carmen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ventas', 1, 1),
('Pedro Martínez', 'pedro', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Normatividad', 1, 1),
('Ana Torres', 'ana', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Recursos Humanos', 0, 1),
('Luis Ramírez', 'luis', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Contabilidad', 0, 1),
('Sofia Hernández', 'sofia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Almacén de refacciones', 0, 1);

-- ============================================
-- TABLA: solicitudes_atencion
-- ============================================
CREATE TABLE solicitudes_atencion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  folio VARCHAR(50) NOT NULL,
  usuario_id INT(11) NOT NULL,
  departamento VARCHAR(100) NOT NULL,
  tipo_soporte ENUM('Apoyo','Problema') NOT NULL DEFAULT 'Problema',
  tipo_apoyo VARCHAR(255) DEFAULT NULL,
  tipo_problema VARCHAR(255) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  prioridad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  estado ENUM('pendiente','en_proceso','finalizada','cancelada') NOT NULL DEFAULT 'pendiente',
  atendido_por INT(11) DEFAULT NULL,
  fecha_atencion DATETIME DEFAULT NULL,
  comentarios_ti TEXT DEFAULT NULL,
  fecha_actualizacion DATETIME DEFAULT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_folio (folio),
  KEY idx_usuario_id (usuario_id),
  KEY idx_estado (estado),
  KEY idx_prioridad (prioridad),
  KEY idx_atendido_por (atendido_por),
  KEY idx_fecha_creacion (fecha_creacion),
  CONSTRAINT fk_solicitud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_solicitud_tecnico FOREIGN KEY (atendido_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: historial_estados
-- ============================================
CREATE TABLE historial_estados (
  id INT(11) NOT NULL AUTO_INCREMENT,
  solicitud_id INT(11) NOT NULL,
  estado_anterior VARCHAR(50) DEFAULT NULL,
  estado_nuevo VARCHAR(50) NOT NULL,
  comentario TEXT DEFAULT NULL,
  usuario_id INT(11) NOT NULL,
  fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_solicitud_id (solicitud_id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_fecha_cambio (fecha_cambio),
  CONSTRAINT fk_historial_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_atencion (id) ON DELETE CASCADE,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: archivos_adjuntos
-- ============================================
CREATE TABLE archivos_adjuntos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  solicitud_id INT(11) NOT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta_archivo VARCHAR(500) NOT NULL,
  tipo_mime VARCHAR(100) NOT NULL,
  tamanio INT(11) NOT NULL,
  fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_solicitud_id (solicitud_id),
  CONSTRAINT fk_archivo_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_atencion (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: notificaciones
-- ============================================
CREATE TABLE notificaciones (
  id INT(11) NOT NULL AUTO_INCREMENT,
  tipo VARCHAR(50) NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  mensaje TEXT NOT NULL,
  usuario_destino INT(11) NOT NULL,
  datos_json TEXT DEFAULT NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_leida DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_usuario_destino (usuario_destino),
  KEY idx_leida (leida),
  KEY idx_fecha_creacion (fecha_creacion),
  KEY idx_tipo (tipo),
  KEY idx_usuario_leida (usuario_destino, leida),
  CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_destino) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: documentos_colaborativos
-- ============================================
CREATE TABLE documentos_colaborativos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  categoria VARCHAR(50) DEFAULT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta_archivo VARCHAR(500) NOT NULL,
  tipo_mime VARCHAR(100) NOT NULL,
  tamanio INT(11) NOT NULL,
  subido_por INT(11) NOT NULL,
  fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  descargas INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_subido_por (subido_por),
  KEY idx_categoria (categoria),
  KEY idx_fecha_subida (fecha_subida),
  CONSTRAINT fk_doc_usuario FOREIGN KEY (subido_por) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: descargas_log
-- ============================================
CREATE TABLE descargas_log (
  id INT(11) NOT NULL AUTO_INCREMENT,
  documento_id INT(11) NOT NULL,
  usuario_id INT(11) NOT NULL,
  fecha_descarga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documento_id (documento_id),
  KEY idx_usuario_id (usuario_id),
  CONSTRAINT fk_descarga_documento FOREIGN KEY (documento_id) REFERENCES documentos_colaborativos (id) ON DELETE CASCADE,
  CONSTRAINT fk_descarga_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: mantenimientos_equipos_computo
-- ============================================
CREATE TABLE mantenimientos_equipos_computo (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) NOT NULL,
  tipo_mantenimiento ENUM('Preventivo','Correctivo') NOT NULL,
  equipo VARCHAR(100) NOT NULL,
  numero_serie VARCHAR(100) DEFAULT NULL,
  ubicacion VARCHAR(200) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('pendiente','en_proceso','finalizado') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_atencion DATETIME DEFAULT NULL,
  atendido_por INT(11) DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_estado (estado),
  KEY idx_atendido_por (atendido_por),
  CONSTRAINT fk_mant_equipo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_mant_equipo_tecnico FOREIGN KEY (atendido_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: mantenimientos_impresoras
-- ============================================
CREATE TABLE mantenimientos_impresoras (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) NOT NULL,
  tipo_mantenimiento ENUM('Preventivo','Correctivo') NOT NULL,
  marca_modelo VARCHAR(150) NOT NULL,
  numero_serie VARCHAR(100) DEFAULT NULL,
  ubicacion VARCHAR(200) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('pendiente','en_proceso','finalizado') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_atencion DATETIME DEFAULT NULL,
  atendido_por INT(11) DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_estado (estado),
  CONSTRAINT fk_mant_impresora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: mantenimientos_telefonos
-- ============================================
CREATE TABLE mantenimientos_telefonos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) NOT NULL,
  tipo_mantenimiento ENUM('Preventivo','Correctivo') NOT NULL,
  numero_extension VARCHAR(50) NOT NULL,
  ubicacion VARCHAR(200) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('pendiente','en_proceso','finalizado') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_atencion DATETIME DEFAULT NULL,
  atendido_por INT(11) DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_estado (estado),
  CONSTRAINT fk_mant_telefono_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: mantenimientos_camaras
-- ============================================
CREATE TABLE mantenimientos_camaras (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) NOT NULL,
  tipo_mantenimiento ENUM('Preventivo','Correctivo') NOT NULL,
  ubicacion VARCHAR(200) NOT NULL,
  numero_serie VARCHAR(100) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('pendiente','en_proceso','finalizado') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_atencion DATETIME DEFAULT NULL,
  atendido_por INT(11) DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_usuario_id (usuario_id),
  KEY idx_estado (estado),
  CONSTRAINT fk_mant_camara_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VISTAS
-- ============================================
CREATE VIEW v_solicitudes_completas AS
SELECT 
    s.*,
    u.nombre_completo as solicitante_nombre,
    u.email as solicitante_email,
    t.nombre_completo as tecnico_nombre
FROM solicitudes_atencion s
INNER JOIN usuarios u ON s.usuario_id = u.id
LEFT JOIN usuarios t ON s.atendido_por = t.id;

CREATE VIEW v_estadisticas_departamento AS
SELECT 
    departamento,
    COUNT(*) as total_solicitudes,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
    SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas
FROM solicitudes_atencion
GROUP BY departamento;

-- ============================================
-- FIN - Base de datos creada correctamente
-- ============================================
SELECT 'Base de datos creada exitosamente' as Resultado;