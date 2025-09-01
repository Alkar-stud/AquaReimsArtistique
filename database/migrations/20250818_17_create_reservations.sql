-- Création de la table reservations
CREATE TABLE IF NOT EXISTS `reservations` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(255) DEFAULT NULL UNIQUE,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `events`(`id`),
    `event_session` bigint NOT NULL,
    FOREIGN KEY (`event_session`) REFERENCES `event_sessions`(`id`),
    `reservation_mongo_id` varchar(24) NOT NULL,
    `nom` VARCHAR(255) NOT NULL,
    `prenom` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` CHAR(15) NOT NULL,
    `nageuse_si_limitation` bigint NULL,
    FOREIGN KEY (`nageuse_si_limitation`) REFERENCES `nageuses`(`id`),
    `total_amount` INT NOT NULL,
    `total_amount_paid` INT NOT NULL,
    `token` CHAR(64) NOT NULL,
    `token_expire_at` DATETIME NOT NULL,
    `is_canceled` TINYINT(1) NOT NULL DEFAULT '0',
    `comments` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);