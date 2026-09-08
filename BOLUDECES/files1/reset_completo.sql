-- ============================================================
-- RESET COMPLETO: borra la base y la vuelve a crear con estructura + datos de prueba
-- ============================================================
DROP DATABASE IF EXISTS spelling_bee;

-- ============================================================
-- SISTEMA DE COMPETENCIAS SPELLING BEE
-- Base de datos MySQL - Compatible con XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS spelling_bee
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE spelling_bee;

-- ============================================================
-- 1. COLORES (catálogo de colores predefinidos, paleta clara
--    con un toque de transparencia, para que las instituciones
--    elijan de una lista prolija en vez de cargar hex libre)
-- ============================================================
CREATE TABLE colores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  hex VARCHAR(9) NOT NULL   -- formato #RRGGBBAA (con canal alpha de transparencia)
) ENGINE=InnoDB;

INSERT INTO colores (nombre, hex) VALUES
('Azul Cielo',    '#4FA3E3D9'),
('Celeste',       '#7FD8E0D9'),
('Verde Menta',   '#6FCF97D9'),
('Verde Agua',    '#56C9B8D9'),
('Amarillo Suave','#F5D76ED9'),
('Naranja Suave', '#F2A65AD9'),
('Coral',         '#F2807DD9'),
('Rosa',          '#F49AC2D9'),
('Violeta',       '#B79CEDD9'),
('Lila',          '#C9B6E4D9'),
('Gris Claro',    '#B0BEC5D9'),
('Turquesa',      '#4FD1C5D9');

-- ============================================================
-- 2. INSTITUCIONES
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
  color_primario_id INT NULL,
  color_secundario_id INT NULL,
  color_terciario_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (color_primario_id) REFERENCES colores(id),
  FOREIGN KEY (color_secundario_id) REFERENCES colores(id),
  FOREIGN KEY (color_terciario_id) REFERENCES colores(id)
) ENGINE=InnoDB;

-- ============================================================
-- 3. USUARIOS (login unificado para todos los roles)
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
-- 4. PROFESOR_INSTITUCION (relación N:M)
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
-- 5. ALUMNOS
-- ============================================================
CREATE TABLE alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,                  -- login del alumno (opcional: puede existir sin cuenta)
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  dni VARCHAR(20) NOT NULL UNIQUE,
  anio TINYINT NOT NULL,                -- 1 a 6
  email VARCHAR(150) NOT NULL,
  institucion_id INT NOT NULL,
  profesor_id INT NOT NULL,             -- profesor asesor (referencia a usuarios.id con rol profesor)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (institucion_id) REFERENCES instituciones(id),
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. NIVELES (catálogo fijo: L1, L2, L3)
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
-- 7. COMPETENCIAS
-- ============================================================
CREATE TABLE competencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  fecha DATE NOT NULL,
  tipo ENUM('completa','simple') NOT NULL DEFAULT 'completa', -- simple = creada por alumno (1 round, 1 level)
  tipo_alcance ENUM('institucional','interescolar') NOT NULL DEFAULT 'institucional', -- institucional = solo alumnos de la escuela del profesor | interescolar = de cualquier institución
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
-- 8. COMPETENCIA_LEVELS (N:M - qué niveles participan)
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
-- 9. COMPETENCIA_ROUNDS (configuración de cada round)
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
-- 10. COMPETENCIA_INVITACIONES (para interescolares)
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
-- 11. INSCRIPCIONES (alumno <-> competencia)
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
  presente TINYINT(1) DEFAULT 0,       -- se marca el día del evento (control de asistencia)
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
-- 12. PALABRAS (banco público)
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
-- 13. RESULTADOS (performance por round)
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
-- 14. PRACTICA_RESULTADOS (historial de práctica individual)
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
-- 15. COMPETENCIA_ACCESOS (código + contraseña para espectadores/jurados)
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
-- 16. AUDITORIA (log de cambios importantes)
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


-- ============================================================
-- SEED DATA: 10 instituciones, 10 profesores, 40 alumnos
-- Contraseña para TODOS los usuarios (profesores y alumnos): 1234
-- Hash bcrypt generado para '1234' (válido con password_verify de PHP)
-- ============================================================
USE spelling_bee;

-- 1) INSTITUCIONES
-- Los colores se asignan por ID desde el catálogo 'colores' (12 colores, id 1-12)
SET @color_base = (SELECT MIN(id) FROM colores);

INSERT INTO instituciones (nombre, email, direccion, ciudad, provincia, telefono, logo_url, color_primario_id, color_secundario_id, color_terciario_id) VALUES
('Escuela Técnica Carmen Molina de Llano', 'contactoescuelatecnicac@spellingbeee.com', 'Rioja 687', 'Corrientes', 'Corrientes', '379-4500111', NULL, @color_base + 0, @color_base + 1, @color_base + 2),
('Escuela Portuaria', 'contactoescuelaportuari@spellingbeee.com', 'Av. Costanera Gral. San Martín 480', 'Corrientes', 'Corrientes', '379-4500222', NULL, @color_base + 3, @color_base + 4, @color_base + 5),
('Instituto Hipólito Yrigoyen', 'contactoinstitutohipoli@spellingbeee.com', 'San Juan 1120', 'Corrientes', 'Corrientes', '379-4500333', NULL, @color_base + 6, @color_base + 7, @color_base + 8),
('Escuela de Comercio', 'contactoescueladecomerc@spellingbeee.com', 'Salta 900', 'Corrientes', 'Corrientes', '379-4500444', NULL, @color_base + 9, @color_base + 10, @color_base + 11),
('Escuela Nacional', 'contactoescuelanacional@spellingbeee.com', 'Junín 745', 'Corrientes', 'Corrientes', '379-4500555', NULL, @color_base + 0, @color_base + 1, @color_base + 2),
('Instituto Ilia', 'contactoinstitutoilia@spellingbeee.com', 'Mendoza 1330', 'Corrientes', 'Corrientes', '379-4500666', NULL, @color_base + 3, @color_base + 4, @color_base + 5),
('Escuela Juana Manso', 'contactoescuelajuanaman@spellingbeee.com', 'Bolívar 610', 'Corrientes', 'Corrientes', '379-4500777', NULL, @color_base + 6, @color_base + 7, @color_base + 8),
('Escuela Normal José Manuel Estrada', 'contactoescuelanormaljo@spellingbeee.com', 'Pellegrini 980', 'Corrientes', 'Corrientes', '379-4500888', NULL, @color_base + 9, @color_base + 10, @color_base + 11),
('Colegio San José', 'contactocolegiosanjose@spellingbeee.com', 'La Rioja 1560', 'Corrientes', 'Corrientes', '379-4500999', NULL, @color_base + 0, @color_base + 1, @color_base + 2),
('Instituto San Fernando', 'contactoinstitutosanfer@spellingbeee.com', 'Catamarca 720', 'Corrientes', 'Corrientes', '379-4501110', NULL, @color_base + 3, @color_base + 4, @color_base + 5);

-- 2) PROFESORES (usuarios con rol='profesor')
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, telefono, activo) VALUES
('María', 'González', 'mariagonzalez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600111', 1),
('Carlos', 'Fernández', 'carlosfernandez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600222', 1),
('Ana', 'Rodríguez', 'anarodriguez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600333', 1),
('Jorge', 'López', 'jorgelopez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600444', 1),
('Silvia', 'Martínez', 'silviamartinez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600555', 1),
('Roberto', 'Sosa', 'robertososa@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600666', 1),
('Patricia', 'Benítez', 'patriciabenitez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600777', 1),
('Daniel', 'Acuña', 'danielacuna@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600888', 1),
('Laura', 'Ojeda', 'lauraojeda@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4600999', 1),
('Miguel', 'Cardozo', 'miguelcardozo@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'profesor', '379-4601110', 1);

-- Relación profesor-institución (1 a 1 por ahora, se amplía después)
-- Se asume que los usuarios profesor se insertaron con IDs correlativos (ver @profesor_base)
SET @profesor_base = (SELECT MIN(id) FROM usuarios WHERE rol='profesor');
SET @institucion_base = (SELECT MIN(id) FROM instituciones);

INSERT INTO profesor_institucion (usuario_id, institucion_id) VALUES
(@profesor_base + 0, @institucion_base + 0),
(@profesor_base + 1, @institucion_base + 1),
(@profesor_base + 2, @institucion_base + 2),
(@profesor_base + 3, @institucion_base + 3),
(@profesor_base + 4, @institucion_base + 4),
(@profesor_base + 5, @institucion_base + 5),
(@profesor_base + 6, @institucion_base + 6),
(@profesor_base + 7, @institucion_base + 7),
(@profesor_base + 8, @institucion_base + 8),
(@profesor_base + 9, @institucion_base + 9);

-- 3) ALUMNOS: primero se crea el usuario (login), luego la ficha en 'alumnos'
-- Distribución: 13 alumnos L1, 13 alumnos L2, 14 alumnos L3 (~33% cada nivel)
-- Repartidos entre las 10 instituciones y su profesor asesor correspondiente

INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, telefono, activo) VALUES
('Sofía', 'Ramírez', 'sofiaramirez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Mateo', 'Pereyra', 'mateopereyra@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Valentina', 'Coronel', 'valentinacoronel@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Santiago', 'Duarte', 'santiagoduarte@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Isabella', 'Aguirre', 'isabellaaguirre@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Benjamín', 'Villalba', 'benjaminvillalba@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Emma', 'Gómez', 'emmagomez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Thiago', 'Ibáñez', 'thiagoibanez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Martina', 'Cáceres', 'martinacaceres@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Joaquín', 'Medina', 'joaquinmedina@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Catalina', 'Torres', 'catalinatorres@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Lucas', 'Romero', 'lucasromero@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Renata', 'Ayala', 'renataayala@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Bautista', 'Silva', 'bautistasilva@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Delfina', 'Ortiz', 'delfinaortiz@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Facundo', 'Molina', 'facundomolina@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Julieta', 'Paredes', 'julietaparedes@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Agustín', 'Ríos', 'agustinrios@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Mía', 'Escobar', 'miaescobar@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Tomás', 'Barrios', 'tomasbarrios@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Victoria', 'Leiva', 'victorialeiva@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Nicolás', 'Godoy', 'nicolasgodoy@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Pilar', 'Núñez', 'pilarnunez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Franco', 'Alarcón', 'francoalarcon@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Camila', 'Vera', 'camilavera@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Ignacio', 'Ferreyra', 'ignacioferreyra@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Antonella', 'Chávez', 'antonellachavez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Máximo', 'Guzmán', 'maximoguzman@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Abril', 'Maldonado', 'abrilmaldonado@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Federico', 'Quiroga', 'federicoquiroga@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Lola', 'Sánchez', 'lolasanchez@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Gael', 'Flores', 'gaelflores@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Alma', 'Bogado', 'almabogado@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Dante', 'Cabrera', 'dantecabrera@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Zoe', 'Insaurralde', 'zoeinsaurralde@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Bruno', 'Meza', 'brunomeza@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Olivia', 'Franco', 'oliviafranco@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Ciro', 'Bareiro', 'cirobareiro@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Luz', 'Portillo', 'luzportillo@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1),
('Elián', 'Zalazar', 'elianzalazar@spellingbeee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'alumno', NULL, 1);

SET @alumno_usuario_base = (SELECT MIN(id) FROM usuarios WHERE rol='alumno');

-- ============================================================
-- USUARIO ADMIN
-- Email: marcelo@spellingbee.com | Contraseña: 1234
-- ============================================================
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, telefono, activo) VALUES
('Marcelo', 'Admin', 'marcelo@spellingbee.com', '$2b$12$GtegZDWRg/6xeUeNLE4Bieylh/Mv7UDdhaYU8j0CvpleXtDZJgmfK', 'admin', NULL, 1);

INSERT INTO alumnos (usuario_id, nombre, apellido, dni, anio, email, institucion_id, profesor_id) VALUES
(@alumno_usuario_base + 0, 'Sofía', 'Ramírez', '41000000', 1, 'sofiaramirez@spellingbeee.com', @institucion_base + 0, @profesor_base + 0),
(@alumno_usuario_base + 1, 'Mateo', 'Pereyra', '41000137', 1, 'mateopereyra@spellingbeee.com', @institucion_base + 1, @profesor_base + 1),
(@alumno_usuario_base + 2, 'Valentina', 'Coronel', '41000274', 2, 'valentinacoronel@spellingbeee.com', @institucion_base + 2, @profesor_base + 2),
(@alumno_usuario_base + 3, 'Santiago', 'Duarte', '41000411', 1, 'santiagoduarte@spellingbeee.com', @institucion_base + 3, @profesor_base + 3),
(@alumno_usuario_base + 4, 'Isabella', 'Aguirre', '41000548', 1, 'isabellaaguirre@spellingbeee.com', @institucion_base + 4, @profesor_base + 4),
(@alumno_usuario_base + 5, 'Benjamín', 'Villalba', '41000685', 1, 'benjaminvillalba@spellingbeee.com', @institucion_base + 5, @profesor_base + 5),
(@alumno_usuario_base + 6, 'Emma', 'Gómez', '41000822', 1, 'emmagomez@spellingbeee.com', @institucion_base + 6, @profesor_base + 6),
(@alumno_usuario_base + 7, 'Thiago', 'Ibáñez', '41000959', 1, 'thiagoibanez@spellingbeee.com', @institucion_base + 7, @profesor_base + 7),
(@alumno_usuario_base + 8, 'Martina', 'Cáceres', '41001096', 2, 'martinacaceres@spellingbeee.com', @institucion_base + 8, @profesor_base + 8),
(@alumno_usuario_base + 9, 'Joaquín', 'Medina', '41001233', 1, 'joaquinmedina@spellingbeee.com', @institucion_base + 9, @profesor_base + 9),
(@alumno_usuario_base + 10, 'Catalina', 'Torres', '41001370', 1, 'catalinatorres@spellingbeee.com', @institucion_base + 0, @profesor_base + 0),
(@alumno_usuario_base + 11, 'Lucas', 'Romero', '41001507', 1, 'lucasromero@spellingbeee.com', @institucion_base + 1, @profesor_base + 1),
(@alumno_usuario_base + 12, 'Renata', 'Ayala', '41001644', 1, 'renataayala@spellingbeee.com', @institucion_base + 2, @profesor_base + 2),
(@alumno_usuario_base + 13, 'Bautista', 'Silva', '41001781', 3, 'bautistasilva@spellingbeee.com', @institucion_base + 3, @profesor_base + 3),
(@alumno_usuario_base + 14, 'Delfina', 'Ortiz', '41001918', 3, 'delfinaortiz@spellingbeee.com', @institucion_base + 4, @profesor_base + 4),
(@alumno_usuario_base + 15, 'Facundo', 'Molina', '41002055', 3, 'facundomolina@spellingbeee.com', @institucion_base + 5, @profesor_base + 5),
(@alumno_usuario_base + 16, 'Julieta', 'Paredes', '41002192', 4, 'julietaparedes@spellingbeee.com', @institucion_base + 6, @profesor_base + 6),
(@alumno_usuario_base + 17, 'Agustín', 'Ríos', '41002329', 3, 'agustinrios@spellingbeee.com', @institucion_base + 7, @profesor_base + 7),
(@alumno_usuario_base + 18, 'Mía', 'Escobar', '41002466', 4, 'miaescobar@spellingbeee.com', @institucion_base + 8, @profesor_base + 8),
(@alumno_usuario_base + 19, 'Tomás', 'Barrios', '41002603', 4, 'tomasbarrios@spellingbeee.com', @institucion_base + 9, @profesor_base + 9),
(@alumno_usuario_base + 20, 'Victoria', 'Leiva', '41002740', 3, 'victorialeiva@spellingbeee.com', @institucion_base + 0, @profesor_base + 0),
(@alumno_usuario_base + 21, 'Nicolás', 'Godoy', '41002877', 3, 'nicolasgodoy@spellingbeee.com', @institucion_base + 1, @profesor_base + 1),
(@alumno_usuario_base + 22, 'Pilar', 'Núñez', '41003014', 4, 'pilarnunez@spellingbeee.com', @institucion_base + 2, @profesor_base + 2),
(@alumno_usuario_base + 23, 'Franco', 'Alarcón', '41003151', 4, 'francoalarcon@spellingbeee.com', @institucion_base + 3, @profesor_base + 3),
(@alumno_usuario_base + 24, 'Camila', 'Vera', '41003288', 4, 'camilavera@spellingbeee.com', @institucion_base + 4, @profesor_base + 4),
(@alumno_usuario_base + 25, 'Ignacio', 'Ferreyra', '41003425', 3, 'ignacioferreyra@spellingbeee.com', @institucion_base + 5, @profesor_base + 5),
(@alumno_usuario_base + 26, 'Antonella', 'Chávez', '41003562', 5, 'antonellachavez@spellingbeee.com', @institucion_base + 6, @profesor_base + 6),
(@alumno_usuario_base + 27, 'Máximo', 'Guzmán', '41003699', 6, 'maximoguzman@spellingbeee.com', @institucion_base + 7, @profesor_base + 7),
(@alumno_usuario_base + 28, 'Abril', 'Maldonado', '41003836', 5, 'abrilmaldonado@spellingbeee.com', @institucion_base + 8, @profesor_base + 8),
(@alumno_usuario_base + 29, 'Federico', 'Quiroga', '41003973', 5, 'federicoquiroga@spellingbeee.com', @institucion_base + 9, @profesor_base + 9),
(@alumno_usuario_base + 30, 'Lola', 'Sánchez', '41004110', 6, 'lolasanchez@spellingbeee.com', @institucion_base + 0, @profesor_base + 0),
(@alumno_usuario_base + 31, 'Gael', 'Flores', '41004247', 5, 'gaelflores@spellingbeee.com', @institucion_base + 1, @profesor_base + 1),
(@alumno_usuario_base + 32, 'Alma', 'Bogado', '41004384', 6, 'almabogado@spellingbeee.com', @institucion_base + 2, @profesor_base + 2),
(@alumno_usuario_base + 33, 'Dante', 'Cabrera', '41004521', 6, 'dantecabrera@spellingbeee.com', @institucion_base + 3, @profesor_base + 3),
(@alumno_usuario_base + 34, 'Zoe', 'Insaurralde', '41004658', 6, 'zoeinsaurralde@spellingbeee.com', @institucion_base + 4, @profesor_base + 4),
(@alumno_usuario_base + 35, 'Bruno', 'Meza', '41004795', 5, 'brunomeza@spellingbeee.com', @institucion_base + 5, @profesor_base + 5),
(@alumno_usuario_base + 36, 'Olivia', 'Franco', '41004932', 6, 'oliviafranco@spellingbeee.com', @institucion_base + 6, @profesor_base + 6),
(@alumno_usuario_base + 37, 'Ciro', 'Bareiro', '41005069', 5, 'cirobareiro@spellingbeee.com', @institucion_base + 7, @profesor_base + 7),
(@alumno_usuario_base + 38, 'Luz', 'Portillo', '41005206', 6, 'luzportillo@spellingbeee.com', @institucion_base + 8, @profesor_base + 8),
(@alumno_usuario_base + 39, 'Elián', 'Zalazar', '41005343', 5, 'elianzalazar@spellingbeee.com', @institucion_base + 9, @profesor_base + 9);
