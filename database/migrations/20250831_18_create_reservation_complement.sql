-- Création de la table reservation_complement
CREATE TABLE IF NOT EXISTS `reservation_complement` (
`id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
`reservation` bigint NOT NULL,
FOREIGN KEY (`reservation`) REFERENCES `reservation`(`id`),
`tarif` bigint NOT NULL,
FOREIGN KEY (`tarif`) REFERENCES `tarif`(`id`),
`tarif_access_code` CHAR(32) NULL,
`qty` INT NOT NULL,
`created_at` DATETIME NOT NULL,
`updated_at` DATETIME NULL
);
