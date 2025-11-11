-- Création de la table event
CREATE TABLE IF NOT EXISTS `event` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(255) NOT NULL,
    `place` bigint NOT NULL,
    FOREIGN KEY (`place`) REFERENCES `piscine`(`id`),
    `limitation_per_swimmer` INT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
