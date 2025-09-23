-- Création de la table config
CREATE TABLE IF NOT EXISTS `config` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `label` varchar(255) NOT NULL,
    `config_key` char(32) NOT NULL UNIQUE,
    `config_value` varchar(255) NOT NULL,
    `config_type` char(8) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (`config_key`)
);
