-- Création de la table reservation_temp
CREATE TABLE IF NOT EXISTS `reservation_temp` (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `event` bigint NOT NULL,
        FOREIGN KEY (`event`) REFERENCES `event`(`id`),
        `event_session` bigint NOT NULL,
        FOREIGN KEY (`event_session`) REFERENCES `event_session`(`id`),
        `access_code` CHAR(24) NULL,
        `session_id` varchar(128) NOT NULL,
        `name` VARCHAR(255) NULL,
        `firstname` VARCHAR(255) NULL,
        `email` VARCHAR(255) NULL,
        `phone` CHAR(15) NULL,
        `swimmer_if_limitation` bigint NULL,
        FOREIGN KEY (`swimmer_if_limitation`) REFERENCES `swimmer`(`id`),
        `created_at` DATETIME NOT NULL, -- Servira aussi pour le timeout
        `updated_at` DATETIME NULL
);
