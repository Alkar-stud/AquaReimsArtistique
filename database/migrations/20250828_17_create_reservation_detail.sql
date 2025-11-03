-- Création de la table reservation_detail
CREATE TABLE IF NOT EXISTS `reservation_detail` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservation`(`id`),
    `name` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(255) NOT NULL,
    `tarif` bigint NOT NULL,
    FOREIGN KEY (`tarif`) REFERENCES `tarif`(`id`),
    `tarif_access_code` CHAR(32) NULL,
    `justificatif_name` VARCHAR(255) NULL,
    `place_number` bigint NULL, -- Doit être NULLABLE pour l’annulation et les réservations sans placement
    FOREIGN KEY (`place_number`) REFERENCES `piscine_gradins_places`(`id`),
    `entered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,

    KEY `idx_resdet_reservation` (`reservation`),
    KEY `idx_resdet_place_number` (`place_number`),
    KEY `idx_resdet_tarif` (`tarif`)
);
