-- Création de la table swimmer_group
CREATE TABLE IF NOT EXISTS `swimmer_group` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` char(64) NOT NULL,
    `coach` varchar(255) NULL,
    `is_active` BOOLEAN NOT NULL,
    `order` int NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

