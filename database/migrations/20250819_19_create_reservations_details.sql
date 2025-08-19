-- Création de la table reservations_details
CREATE TABLE IF NOT EXISTS `reservations_details` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservations`(`id`),
    `nom` VARCHAR(255) NULL,            -- NULL si place non assise
    `prenom` VARCHAR(255) NULL,         -- NULL si place non assise
    `tarif` bigint NOT NULL,
    FOREIGN KEY (`tarif`) REFERENCES `tarifs`(`id`),
    `tarif_access_code` CHAR(32) NULL,
    `justificatif_name` VARCHAR(255) NULL,
    `place_number` bigint NULL,         -- NULL si place non assise
    FOREIGN KEY (`place_number`) REFERENCES `piscine_gradins_places`(`id`),
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);