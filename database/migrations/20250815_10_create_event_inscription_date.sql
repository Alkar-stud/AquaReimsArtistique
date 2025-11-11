-- Création de la table event_inscription_date.
CREATE TABLE IF NOT EXISTS `event_inscription_date` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `event`(`id`),
    `name` char(64) NOT NULL,
    `start_registration_at` DATETIME NOT NULL,
    `close_registration_at` DATETIME NOT NULL,
    `access_code` char(24) NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
