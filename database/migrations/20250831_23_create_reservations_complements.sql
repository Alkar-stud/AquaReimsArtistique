-- Création de la table reservations_complements
CREATE TABLE IF NOT EXISTS `reservations_complements` (
`id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
`reservation` bigint NOT NULL,
FOREIGN KEY (`reservation`) REFERENCES `reservations`(`id`),
`tarif` bigint NOT NULL,
FOREIGN KEY (`tarif`) REFERENCES `tarifs`(`id`),
`tarif_access_code` CHAR(32) NULL,
`qty` INT NOT NULL,
`created_at` DATETIME NOT NULL,
`updated_at` DATETIME NULL
);