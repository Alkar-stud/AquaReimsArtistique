-- Création de la table reservation_detail_temp
CREATE TABLE IF NOT EXISTS `reservation_detail_temp` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation_temp` bigint NOT NULL,
    FOREIGN KEY (`reservation_temp`) REFERENCES `reservation_temp`(`id`) ON DELETE CASCADE,
    `name` VARCHAR(255) NULL,
    `firstname` VARCHAR(255) NULL,
    `tarif` bigint NOT NULL,
    FOREIGN KEY (`tarif`) REFERENCES `tarif`(`id`),
    `tarif_access_code` CHAR(32) NULL,
    `justificatif_name` VARCHAR(255) NULL,
    `justificatif_original_name` VARCHAR(255) NULL,
    `place_number` bigint NULL,
    FOREIGN KEY (`place_number`) REFERENCES `piscine_gradins_places`(`id`),
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL,

    KEY `idx_resdet_reservation_temp` (`reservation_temp`),
    KEY `idx_resdet_place_number` (`place_number`),
    KEY `idx_resdet_tarif` (`tarif`)
);
