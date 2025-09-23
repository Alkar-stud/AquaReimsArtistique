-- Création de la table event_session
CREATE TABLE IF NOT EXISTS `event_session` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event_id` bigint NOT NULL,
    FOREIGN KEY (`event_id`) REFERENCES `event`(`id`) ON DELETE CASCADE,
    `session_name` varchar(255) NULL, -- "Séance du matin", "Séance du soir", etc.
    `opening_doors_at` DATETIME NOT NULL,
    `event_start_at` DATETIME NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
