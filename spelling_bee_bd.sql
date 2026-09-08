-- ============================================================
-- SISTEMA DE COMPETENCIAS SPELLING BEE
-- Base de datos MySQL - Compatible con XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS spelling_bee
  CHARACTER SET utf8mb4
COLLATE utf8mb4_spanish_ci;

USE spelling_bee;

-- ============================================================
-- 1. INSTITUCIONES
-- ============================================================
CREATE TABLE instituciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  direccion VARCHAR(200),
  ciudad VARCHAR(100),
  provincia VARCHAR(100),
  telefono VARCHAR(30),
  logo_url VARCHAR(255),
  color_primario VARCHAR(7) DEFAULT '#000000',
  color_secundario VARCHAR(7) DEFAULT '#FFFFFF',
  color_terciario VARCHAR(7) DEFAULT '#CCCCCC',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. USUARIOS (login unificado para todos los roles)
-- ============================================================
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','directivo','profesor','alumno') NOT NULL,
  telefono VARCHAR(30),
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. PROFESOR_INSTITUCION (relación N:M)
-- Un profesor puede trabajar en varias instituciones
-- ============================================================
CREATE TABLE profesor_institucion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  institucion_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (institucion_id) REFERENCES instituciones(id) ON DELETE CASCADE,
  UNIQUE KEY uq_profesor_institucion (usuario_id, institucion_id)
) ENGINE=InnoDB;

-- ============================================================
-- 4. ALUMNOS
-- ============================================================
CREATE TABLE alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,              -- login del alumno
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  dni VARCHAR(20) NOT NULL UNIQUE,
  anio TINYINT NOT NULL,                -- 1 a 6
  email VARCHAR(150) NOT NULL,
  institucion_id INT NOT NULL,
  profesor_id INT NOT NULL,             -- profesor asesor (referencia a usuarios.id con rol profesor)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. NIVELES (catálogo fijo: L1, L2, L3)
-- ============================================================
CREATE TABLE niveles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(5) NOT NULL UNIQUE,     -- L1, L2, L3
  nombre VARCHAR(50) NOT NULL,           -- "1er y 2do año", etc.
  anio_desde TINYINT NOT NULL,
  anio_hasta TINYINT NOT NULL
) ENGINE=InnoDB;

INSERT INTO niveles (codigo, nombre, anio_desde, anio_hasta) VALUES
('L1', '1er y 2do año', 1, 2),
('L2', '3er y 4to año', 3, 4),
('L3', '5to y 6to año', 5, 6);

-- ============================================================
-- 6. COMPETENCIAS
-- ============================================================
CREATE TABLE competencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  fecha DATE NOT NULL,
  tipo ENUM('completa','simple') NOT NULL DEFAULT 'completa', -- simple = creada por alumno (1 round, 1 level)
  formato ENUM('deletreo','deletreo_oracion') NOT NULL DEFAULT 'deletreo',
  usa_audio TINYINT(1) DEFAULT 0,
  tipo_desempate ENUM('tiempo_acumulado','ultimo_round','round_extra','menor_penalizacion') DEFAULT 'tiempo_acumulado',
  estado ENUM('borrador','en_curso','finalizada') DEFAULT 'borrador',
  es_publica TINYINT(1) DEFAULT 1,
  institucion_organizadora_id INT NULL,   -- para tema de colores en vista pública
  creado_por_usuario_id INT NOT NULL,     -- profesor o alumno que la creó
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (institucion_organizadora_id) REFERENCES instituciones(id),
  FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMPETENCIA_LEVELS (N:M - qué niveles participan)
-- ============================================================
CREATE TABLE competencia_levels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competencia_id INT NOT NULL,
  nivel_id INT NOT NULL,
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
  FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  UNIQUE KEY uq_competencia_nivel (competencia_id, nivel_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. COMPETENCIA_ROUNDS (configuración de cada round)
-- ============================================================
CREATE TABLE competencia_rounds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competencia_id INT NOT NULL,
  nivel_id INT NOT NULL,              -- cada nivel puede tener su propia config de rounds
  numero_round TINYINT NOT NULL,      -- 1, 2, 3...
  cantidad_pasan INT NULL,            -- cuántos avanzan al siguiente round
  cantidad_eliminados INT NULL,       -- alternativa: cuántos quedan eliminados
  estado ENUM('pendiente','en_curso','finalizado') DEFAULT 'pendiente',
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
  FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  UNIQUE KEY uq_competencia_nivel_round (competencia_id, nivel_id, numero_round)
) ENGINE=InnoDB;

-- ============================================================
-- 9. COMPETENCIA_INVITACIONES (para interescolares)
-- ============================================================
CREATE TABLE competencia_invitaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competencia_id INT NOT NULL,
  institucion_id INT NOT NULL,
  profesor_id INT NULL,               -- opcional: invitación a un profesor puntual
  token VARCHAR(100) NOT NULL UNIQUE, -- link de invitación
  estado ENUM('pendiente','aceptada','vencida') DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
  FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. INSCRIPCIONES (alumno <-> competencia)
-- ============================================================
CREATE TABLE inscripciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competencia_id INT NOT NULL,
  alumno_id INT NOT NULL,
  nivel_id INT NOT NULL,              -- heredado del año del alumno al momento de inscribir
  institucion_id INT NOT NULL,        -- heredado del alumno
  profesor_id INT NOT NULL,           -- heredado del alumno
  round_actual TINYINT DEFAULT 1,
  eliminado TINYINT(1) DEFAULT 0,
  posicion_final INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id),
  FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id),
  UNIQUE KEY uq_competencia_alumno (competencia_id, alumno_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. PALABRAS (banco público)
-- ============================================================
CREATE TABLE palabras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  palabra VARCHAR(100) NOT NULL,
  nivel_id INT NOT NULL,
  dificultad ENUM('principiante','normal','avanzado') NOT NULL DEFAULT 'normal',
  definicion VARCHAR(500) NULL,
  competencia_id INT NULL,            -- opcional: cargada específicamente para una competencia
  cargada_por_usuario_id INT NOT NULL,
  es_publica TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE SET NULL,
  FOREIGN KEY (cargada_por_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. RESULTADOS (performance por round)
-- ============================================================
CREATE TABLE resultados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inscripcion_id INT NOT NULL,
  competencia_round_id INT NOT NULL,
  palabra_id INT NOT NULL,
  tiempo_deletreo DECIMAL(6,2) NOT NULL,     -- en segundos
  acierto_deletreo TINYINT(1) NOT NULL,
  tiempo_oracion DECIMAL(6,2) NULL,          -- solo si formato = deletreo_oracion
  oracion_correcta TINYINT(1) NULL,
  penalizacion_segundos DECIMAL(6,2) DEFAULT 0,
  tiempo_total DECIMAL(6,2) NOT NULL,        -- deletreo + oracion + penalizacion
  audio_url VARCHAR(255) NULL,
  registrado_por_usuario_id INT NOT NULL,    -- jurado que cargó el resultado
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  FOREIGN KEY (competencia_round_id) REFERENCES competencia_rounds(id),
  FOREIGN KEY (palabra_id) REFERENCES palabras(id),
  FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 13. PRACTICA_RESULTADOS (historial de práctica individual)
-- ============================================================
CREATE TABLE practica_resultados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  nivel_id INT NOT NULL,
  dificultad ENUM('principiante','normal','avanzado') NOT NULL,
  palabra_id INT NOT NULL,
  tiempo_deletreo DECIMAL(6,2) NOT NULL,
  acierto TINYINT(1) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  FOREIGN KEY (nivel_id) REFERENCES niveles(id),
  FOREIGN KEY (palabra_id) REFERENCES palabras(id)
) ENGINE=InnoDB;

-- ============================================================
-- 14. COMPETENCIA_ACCESOS (código + contraseña para espectadores/jurados)
-- ============================================================
CREATE TABLE competencia_accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competencia_id INT NOT NULL,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol_acceso ENUM('espectador','jurado') NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 15. AUDITORIA (log de cambios importantes)
-- ============================================================
CREATE TABLE auditoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  accion VARCHAR(100) NOT NULL,        -- ej: 'crear_competencia', 'editar_resultado'
  tabla_afectada VARCHAR(50) NOT NULL,
  registro_id INT NULL,
  detalle TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ÍNDICES ADICIONALES RECOMENDADOS
-- ============================================================
CREATE INDEX idx_alumnos_institucion ON alumnos(institucion_id);
CREATE INDEX idx_alumnos_profesor ON alumnos(profesor_id);
CREATE INDEX idx_inscripciones_competencia ON inscripciones(competencia_id);
CREATE INDEX idx_resultados_inscripcion ON resultados(inscripcion_id);
CREATE INDEX idx_palabras_nivel_dificultad ON palabras(nivel_id, dificultad);
CREATE INDEX idx_competencias_estado ON competencias(estado);
