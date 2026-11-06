CREATE DATABASE IF NOT EXISTS sigel
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE sigel;

CREATE TABLE roles(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    eliminado_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    dias_max_prestamo INT UNSIGNED NOT NULL,
    eliminado_en DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE equipos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    estado ENUM('disponible', 'mantenimiento', 'baja')
        NOT NULL DEFAULT 'disponible',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    eliminado_en DATETIME NULL,

    CONSTRAINT fk_equipos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

CREATE TABLE equipo_imagenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT UNSIGNED NOT NULL,
    nombre_archivo VARCHAR(100) NOT NULL UNIQUE,
    mime VARCHAR(50) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_imagenes_equipo
        FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

CREATE TABLE prestamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    operador_id INT UNSIGNED NOT NULL,
    aprobado_por INT UNSIGNED NULL,
    estado ENUM(
        'solicitado',
        'aprobado',
        'entregado',
        'devuelto',
        'cancelado'
    ) NOT NULL DEFAULT 'solicitado',
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prestamos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),

    CONSTRAINT fk_prestamos_operador
        FOREIGN KEY (operador_id) REFERENCES usuarios(id),

    CONSTRAINT fk_prestamos_aprobador
        FOREIGN KEY (aprobado_por) REFERENCES usuarios(id),

    INDEX idx_prestamos_intervalo (estado, fecha_inicio, fecha_fin)
) ENGINE=InnoDB;

CREATE TABLE prestamo_detalle (
    prestamo_id INT UNSIGNED NOT NULL,
    equipo_id INT UNSIGNED NOT NULL,
    fecha_devolucion DATETIME NULL,

    PRIMARY KEY (prestamo_id, equipo_id),

    CONSTRAINT fk_detalle_prestamo
        FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),

    CONSTRAINT fk_detalle_equipo
        FOREIGN KEY (equipo_id) REFERENCES equipos(id),

    INDEX idx_detalle_equipo (equipo_id)
) ENGINE=InnoDB;

CREATE TABLE auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    selector CHAR(18) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expira_en DATETIME NOT NULL,

    CONSTRAINT fk_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE bitacora (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    accion VARCHAR(50) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT UNSIGNED NOT NULL,
    valores_antes JSON NULL,
    valores_despues JSON NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bitacora_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
