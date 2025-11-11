-- Création de la table event_presentations.
CREATE TABLE IF NOT EXISTS `event_presentations` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `event`(`id`),
    `is_displayed` tinyint(1) NOT NULL,
    `display_until` DATETIME NOT NULL,
    `content` LONGTEXT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    );
