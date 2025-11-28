-- Création de la table reservation_complement_temp
CREATE TABLE IF NOT EXISTS `reservation_complement_temp` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation_temp` bigint NOT NULL,
    FOREIGN KEY (`reservation_temp`) REFERENCES `reservation_temp`(`id`) ON DELETE CASCADE,
    `tarif` bigint NOT NULL,
    FOREIGN KEY (`tarif`) REFERENCES `tarif`(`id`),
    `tarif_access_code` CHAR(32) NULL,
    `qty` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);
