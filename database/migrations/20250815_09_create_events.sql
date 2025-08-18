-- Création de la table events
CREATE TABLE IF NOT EXISTS `events` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` varchar(255) NOT NULL,
    `lieu` bigint NOT NULL,
    FOREIGN KEY (`lieu`) REFERENCES `piscines`(`id`),
    `opening_doors_at` DATETIME NOT NULL,
    `event_start_at` DATETIME NOT NULL,
    `limitation_per_swimmer` INT NULL,
    `associate_event` bigint NULL,
    FOREIGN KEY (`associate_event`) REFERENCES `events`(`id`),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);