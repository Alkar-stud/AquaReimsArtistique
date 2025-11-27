-- Création de la table reservation_temp
CREATE TABLE IF NOT EXISTS `reservation_temp` (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `event` bigint NOT NULL,
        FOREIGN KEY (`event`) REFERENCES `event`(`id`),
        `event_session` bigint NOT NULL,
        FOREIGN KEY (`event_session`) REFERENCES `event_session`(`id`),
        `session_id` varchar(24) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `firstname` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` CHAR(15) NULL,
        `swimmer_if_limitation` bigint NULL,
        FOREIGN KEY (`swimmer_if_limitation`) REFERENCES `swimmer`(`id`),
        `created_at` DATETIME NOT NULL, -- Servira aussi pour le timeout
        `updated_at` DATETIME NULL
);
