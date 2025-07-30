-- Création de la table config
CREATE TABLE IF NOT EXISTS `config` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` varchar(255) NOT NULL,
    `config_key` char(32) NOT NULL UNIQUE,
    `config_value` varchar(255) NOT NULL,
    `config_type` char(8) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (`config_key`)
);

-- Ajout de la config de base
INSERT INTO `config` (`libelle`, `config_key`, `config_value`, `config_type`) VALUES
    ('Maintenance', 'MAINTENANCE', '0', 'bool'),
    ('Aqua Reims Artistique', 'EMAIL_CLUB', 'aquareimsartistique@gmail.com', 'email'),
    ('Aqua Reims Artistique gala', 'EMAIL_GALA', 'gala@aquareimsartistique.fr', 'email'),
    ('Temps pour le timeout de session', 'SESSION_TIMEOUT', 'PT40M', 'string');