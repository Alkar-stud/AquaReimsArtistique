-- Création de la table accueil.
CREATE TABLE IF NOT EXISTS `accueil` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `events`(`id`),
    `is_displayed` tinyint(1) NOT NULL,
    `display_until` DATETIME NOT NULL,
    `content` LONGTEXT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    );