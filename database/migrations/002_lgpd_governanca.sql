-- LGPD, Marco Civil e governança: aceite dos termos, registros de acesso, auditoria e encerramento de rádio.

ALTER TABLE users ADD COLUMN terms_version VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL;
ALTER TABLE stations ADD COLUMN closing_at DATETIME NULL;

CREATE TABLE access_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email_hash CHAR(64) NULL,
  event VARCHAR(30) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_access_log_time ON access_log (created_at);
CREATE INDEX idx_access_log_user ON access_log (user_id, created_at);

CREATE TABLE audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NULL,
  actor_id INT UNSIGNED NULL,
  actor_role VARCHAR(20) NULL,
  action VARCHAR(40) NOT NULL,
  target VARCHAR(120) NULL,
  detail VARCHAR(255) NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_audit_station ON audit_log (station_id, id);
CREATE INDEX idx_audit_time ON audit_log (created_at);
