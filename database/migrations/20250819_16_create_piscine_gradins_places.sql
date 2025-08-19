-- Création de la table piscine_gradins_places
CREATE TABLE IF NOT EXISTS `piscine_gradins_places`
(
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `zone` bigint NOT NULL,
    FOREIGN KEY (`zone`) REFERENCES `piscine_gradins_zones`(`id`),
    `rankInZone` CHAR(8) NOT NULL,      -- Rangée dans la zone
    `place_number` INT NOT NULL,        -- Numéro du siège
    `is_pmr` TINYINT(1) NOT NULL DEFAULT '0',
    `is_vip` TINYINT(1) NOT NULL DEFAULT '0',
    `is_volunteer` TINYINT(1) NOT NULL DEFAULT '0',
    `is_open` TINYINT(1) NOT NULL DEFAULT '1',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);