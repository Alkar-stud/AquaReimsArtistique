-- Création de la table logs pour les niveaux warning et plus
CREATE TABLE logs (
                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          ts DATETIME(3) NOT NULL,         -- horodatage lisible
                          tsu BIGINT UNSIGNED NOT NULL,    -- ms epoch pour tri rapide
                          level VARCHAR(16) NOT NULL,
                          level_int SMALLINT NOT NULL,
                          channel VARCHAR(128) NOT NULL,
                          message TEXT,
                          context JSON,
                          user_id INT NULL,
                          ip VARCHAR(45) NULL,
                          uri VARCHAR(2048) NULL,
                          method VARCHAR(10) NULL,
                          duration_ms FLOAT NULL,
                          request_id VARCHAR(64) NULL,
                          INDEX idx_tsu (tsu),
                          INDEX idx_level (level_int),
                          INDEX idx_channel (channel),
                          INDEX idx_user (user_id),
                          INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;