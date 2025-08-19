-- Création de la table piscine_gradins_zones
CREATE TABLE IF NOT EXISTS `piscine_gradins_zones`
(
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `piscine` bigint NOT NULL,
    FOREIGN KEY (`piscine`) REFERENCES `piscines`(`id`),
    `zone_name` CHAR(8) NOT NULL,
    `nb_seats_vertically` INT NOT NULL,
    `nb_seats_horizontally` INT NOT NULL,
    `is_open` TINYINT(1) NOT NULL DEFAULT '1',
    `is_stairs_after` TINYINT(1) NOT NULL DEFAULT '1',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);