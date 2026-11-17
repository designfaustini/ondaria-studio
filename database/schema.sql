-- Ondaria Studio — esquema MySQL 8 / MariaDB 10.6+ (utf8mb4)
-- Também é convertido automaticamente para SQLite em desenvolvimento (bin/console.php migrate).

CREATE TABLE plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  storage_mb INT UNSIGNED NOT NULL DEFAULT 1024,
  max_users INT UNSIGNED NOT NULL DEFAULT 2,
  max_aux_players INT UNSIGNED NOT NULL DEFAULT 1,
  streaming TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
  settings LONGTEXT NULL,
  active_event_set_id INT UNSIGNED NULL,
  public_token VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_station_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'operator',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE folders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'normal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_folder_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
  CONSTRAINT fk_folder_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_folders_station ON folders (station_id, parent_id);

CREATE TABLE media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  folder_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(255) NULL,
  artist VARCHAR(255) NULL,
  overlap_s DECIMAL(6,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_media_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
  CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_media_station_folder ON media (station_id, folder_id);

CREATE TABLE playlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'list',
  items LONGTEXT NULL,
  rotation_pos INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_playlist_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_playlists_station ON playlists (station_id, kind);

CREATE TABLE event_sets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eventset_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  event_set_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  start_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  periodicity VARCHAR(10) NOT NULL DEFAULT 'daily',
  hours_mask INT UNSIGNED NOT NULL DEFAULT 0,
  weekdays_mask INT UNSIGNED NOT NULL DEFAULT 127,
  item LONGTEXT NOT NULL,
  wait_mode VARCHAR(10) NOT NULL DEFAULT 'delayed',
  priority VARCHAR(5) NOT NULL DEFAULT 'low',
  max_wait_s INT UNSIGNED NOT NULL DEFAULT 300,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_set FOREIGN KEY (event_set_id) REFERENCES event_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_events_set ON events (station_id, event_set_id);

CREATE TABLE jingle_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  page_no INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  buttons LONGTEXT NULL,
  UNIQUE (station_id, page_no),
  CONSTRAINT fk_jingle_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sweepers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  media_id INT UNSIGNED NOT NULL,
  item LONGTEXT NOT NULL,
  offset_s DECIMAL(8,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sweeper_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
  CONSTRAINT fk_sweeper_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_sweepers_media ON sweepers (station_id, media_id);

CREATE TABLE play_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  played_at DATETIME NOT NULL,
  source VARCHAR(20) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  media_id INT UNSIGNED NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'ok',
  detail VARCHAR(255) NULL,
  CONSTRAINT fk_log_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_log_station_time ON play_log (station_id, played_at);

CREATE TABLE station_state (
  station_id INT UNSIGNED NOT NULL PRIMARY KEY,
  now_playing LONGTEXT NULL,
  np_updated_at DATETIME NULL,
  leader_token VARCHAR(64) NULL,
  leader_seen_at DATETIME NULL,
  random_state LONGTEXT NULL,
  weather LONGTEXT NULL,
  weather_at DATETIME NULL,
  CONSTRAINT fk_state_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  k VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_login_attempts ON login_attempts (k, attempted_at);

INSERT INTO plans (slug, name, price_cents, storage_mb, max_users, max_aux_players, streaming) VALUES
  ('gratis', 'Grátis', 0, 500, 1, 1, 0),
  ('pro', 'Pro', 4990, 10240, 5, 4, 1),
  ('rede', 'Rede', 14990, 51200, 20, 4, 1);
