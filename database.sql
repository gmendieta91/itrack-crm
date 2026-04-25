-- ============================================================
-- iTrack CRM · Base de datos COMPLETA v2
-- Para instalacion nueva: ejecutar TODO este archivo
-- Para migracion desde v1: ejecutar solo la seccion MIGRACION
-- ============================================================

CREATE DATABASE IF NOT EXISTS itrack_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE itrack_crm;

CREATE TABLE IF NOT EXISTS usuarios (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  apellido     VARCHAR(100) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  usuario      VARCHAR(60)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  rol          ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuarios (nombre,apellido,email,usuario,password,rol) VALUES
('Administrador','iTrack','admin@itrack.com.ar','admin',
 '$2y$10$6HFVkG1EYu68w9.f8HH2se21mxGwHDf2oWm15HgPsk/mDEwLR5R46','admin');

CREATE TABLE IF NOT EXISTS clientes (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(100) NOT NULL,
  apellido         VARCHAR(100) NOT NULL,
  empresa          VARCHAR(150) NOT NULL,
  telefono         VARCHAR(30)  NULL,
  email            VARCHAR(150) NULL,
  direccion        VARCHAR(200) NULL,
  localidad        VARCHAR(100) NULL,
  provincia        VARCHAR(100) NULL,
  estado           ENUM('prospecto','activo','cliente','perdido','inactivo') NOT NULL DEFAULT 'prospecto',
  tipo_servicio    VARCHAR(150) NULL,
  precio           VARCHAR(30)  NULL,
  cant_vehiculos   INT          NULL,
  logo_cliente     LONGTEXT     NULL,
  origen           ENUM('redes_sociales','referido','web','llamada_entrante','feria_evento','publicidad','otro') NULL,
  origen_detalle   VARCHAR(200) NULL,
  ultima_contacto  DATETIME     NULL,
  proxima_accion   VARCHAR(200) NULL,
  fecha_proxima    DATE         NULL,
  notas            TEXT         NULL,
  creado_por       INT          NOT NULL,
  asignado_a       INT          NULL,
  creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  FOREIGN KEY (asignado_a) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS propuestas (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id     INT          NOT NULL,
  usuario_id     INT          NOT NULL,
  tipo_servicio  VARCHAR(150) NOT NULL,
  precio         VARCHAR(30)  NOT NULL,
  cant_vehiculos INT          NULL,
  nombre_archivo VARCHAR(200) NOT NULL DEFAULT '',
  estado         ENUM('borrador','enviada','en_negociacion','ganada','perdida','pausada') NOT NULL DEFAULT 'borrador',
  notas          TEXT         NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS seguimientos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id      INT          NOT NULL,
  propuesta_id    INT          NULL,
  usuario_id      INT          NOT NULL,
  tipo            ENUM('llamada','reunion','email','whatsapp','nota','otro') NOT NULL DEFAULT 'nota',
  titulo          VARCHAR(200) NOT NULL,
  descripcion     TEXT         NULL,
  fecha_contacto  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  proxima_accion  VARCHAR(200) NULL,
  fecha_proxima   DATE         NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id)   REFERENCES clientes(id)  ON DELETE CASCADE,
  FOREIGN KEY (propuesta_id) REFERENCES propuestas(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS importaciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT NOT NULL,
  archivo     VARCHAR(200) NULL,
  total       INT NOT NULL DEFAULT 0,
  importados  INT NOT NULL DEFAULT 0,
  errores     INT NOT NULL DEFAULT 0,
  log         TEXT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  api_key     VARCHAR(64)  NOT NULL UNIQUE,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  creado_por  INT          NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_uso  DATETIME     NULL,
  FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
