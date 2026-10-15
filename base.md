
-- cecsap.asignacion_grado definition

CREATE TABLE `asignacion_grado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_maestro` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_asig_maestro` (`id_maestro`),
  KEY `fk_asig_grado` (`id_grado`),
  KEY `fk_asig_estado` (`id_estado`),
  CONSTRAINT `fk_asig_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id`),
  CONSTRAINT `fk_asig_grado` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asig_maestro` FOREIGN KEY (`id_maestro`) REFERENCES `maestros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cecsap.asistencias definition

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `horario_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_registro` time DEFAULT NULL,
  `estado_id` int(11) NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_asistencias_estados` (`estado_id`),
  KEY `fk_asistencias_horarios` (`horario_id`),
  KEY `idx_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_asistencias_estados` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `fk_asistencias_estudiantes` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asistencias_horarios` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cecsap.estados definition

CREATE TABLE `estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(50) NOT NULL,
  `cod` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- cecsap.estudiantes definition

CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `grado_id` int(11) NOT NULL,
  `card_uid` varchar(50) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT 'default.png',
  `estado_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_estudiantes_grados` (`grado_id`),
  KEY `fk_estudiantes_estados` (`estado_id`),
  CONSTRAINT `fk_estudiantes_estados` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `fk_estudiantes_grados` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cecsap.grados definition

CREATE TABLE `grados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `seccion` varchar(10) NOT NULL DEFAULT 'A',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grado_seccion` (`nombre`,`seccion`),
  KEY `fk_grados_estados` (`estado_id`),
  CONSTRAINT `fk_grados_estados` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cecsap.horarios definition

CREATE TABLE `horarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_grado` int(11) NOT NULL,
  `dia` int(11) NOT NULL,
  `h_entrada` time NOT NULL,
  `h_salida` time NOT NULL,
  `estado_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_horarios_grado` (`id_grado`),
  KEY `fk_horarios_estado` (`estado_id`),
  CONSTRAINT `fk_horarios_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `fk_horarios_grado` FOREIGN KEY (`id_grado`) REFERENCES `grados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cecsap.maestros definition

CREATE TABLE `maestros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado_id` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_maestros_estados` (`estado_id`),
  CONSTRAINT `fk_maestros_estados` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;