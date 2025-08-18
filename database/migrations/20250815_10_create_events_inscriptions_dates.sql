-- Création de la table events_inscriptions_dates.
CREATE TABLE IF NOT EXISTS `events_inscriptions_dates` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `events`(`id`),
    `libelle` char(64) NOT NULL,
    `start_registration_at` DATETIME NOT NULL,
    `close_registration_at` DATETIME NOT NULL,
    `access_code` char(24) NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);