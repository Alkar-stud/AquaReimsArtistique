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

-- Ajout de la config de base
INSERT IGNORE INTO `config` (`label`, `config_key`, `config_value`, `config_type`) VALUES
('Maintenance', 'MAINTENANCE', '0', 'bool', '2025-07-25 17:33:54', '2025-09-17 23:04:15'),
('Email du club Aqua Reims Artistique', 'EMAIL_CLUB', 'aquareimsartistique@gmail.com', 'email'),
('Email des galas Aqua Reims Artistique', 'EMAIL_GALA', 'gala@aquareimsartistique.fr', 'email'),
('Temps pour le timeout de session', 'TIMEOUT_SESSION', 'PT30M', 'string'),
('Temps pour le timeout de réservation de place', 'TIMEOUT_PLACE_RESERV', 'PT20M', 'string'),
('Durée max des logs en jours', 'MAX_LOGS_LIFE', '30', 'int'),
('Durée max des logs en nombre d\'entrée', 'MAX_LOGS_SIZE', '100000', 'int'),
('Dossier pour l\'upload des justificatifs', 'UPLOAD_PROOF_PATH', '/storage/proofs/', 'string'),
('Taille maxi des fichiers (Mo) justificatif pour preuve de tarif', 'MAX_UPLOAD_PROOF_SIZE', '4', 'int'),
('Token pour cron', 'ACCESS_TOKEN', '7QwEYAhcQ5Kmvadkb7vIZS1hKojUk3eTTSBc2E9ykbG5yKuL4C8Z5qpdMwPsWRBP', 'string'),
('nb caractère pour token', 'NB_CARACTERE_TOKEN', '32', 'int'),
('Signature des mails', 'SIGNATURE', 'Aqua Reims Artistique vous remercie et à bientôt...', 'string');
