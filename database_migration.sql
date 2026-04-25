-- ============================================================
-- iTrack CRM · MIGRACION v1 -> v2
-- Ejecutar SOLO si ya tenes la BD de la version anterior
-- ============================================================
USE itrack_crm;

ALTER TABLE clientes
  ADD COLUMN IF NOT EXISTS estado          ENUM('prospecto','activo','cliente','perdido','inactivo') NOT NULL DEFAULT 'prospecto' AFTER provincia,
  ADD COLUMN IF NOT EXISTS tipo_servicio   VARCHAR(150) NULL AFTER estado,
  ADD COLUMN IF NOT EXISTS precio          VARCHAR(30)  NULL AFTER tipo_servicio,
  ADD COLUMN IF NOT EXISTS cant_vehiculos  INT          NULL AFTER precio,
  ADD COLUMN IF NOT EXISTS logo_cliente    LONGTEXT     NULL AFTER cant_vehiculos,
  ADD COLUMN IF NOT EXISTS origen          ENUM('redes_sociales','referido','web','llamada_entrante','feria_evento','publicidad','otro') NULL AFTER logo_cliente,
  ADD COLUMN IF NOT EXISTS origen_detalle  VARCHAR(200) NULL AFTER origen,
  ADD COLUMN IF NOT EXISTS ultima_contacto DATETIME     NULL AFTER origen_detalle,
  ADD COLUMN IF NOT EXISTS proxima_accion  VARCHAR(200) NULL AFTER ultima_contacto,
  ADD COLUMN IF NOT EXISTS fecha_proxima   DATE         NULL AFTER proxima_accion,
  ADD COLUMN IF NOT EXISTS asignado_a      INT          NULL AFTER creado_por;

ALTER TABLE propuestas
  ADD COLUMN IF NOT EXISTS cant_vehiculos INT NULL AFTER precio,
  MODIFY COLUMN estado ENUM('borrador','enviada','en_negociacion','ganada','perdida','pausada') NOT NULL DEFAULT 'borrador';

CREATE TABLE IF NOT EXISTS importaciones (
  id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, archivo VARCHAR(200) NULL,
  total INT NOT NULL DEFAULT 0, importados INT NOT NULL DEFAULT 0, errores INT NOT NULL DEFAULT 0,
  log TEXT NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL,
  api_key VARCHAR(64) NOT NULL UNIQUE, activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT NOT NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_uso DATETIME NULL, FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
