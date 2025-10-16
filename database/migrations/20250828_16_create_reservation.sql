-- Création de la table reservation
CREATE TABLE IF NOT EXISTS `reservation` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `event`(`id`),
    `event_session` bigint NOT NULL,
    FOREIGN KEY (`event_session`) REFERENCES `event_session`(`id`),
    `reservation_temp_id` varchar(24) NOT NULL, -- Car c'est cet ID temporaire qui est envoyé aux metadata du partenaire de paiement
    `name` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` CHAR(15) NULL,
    `swimmer_if_limitation` bigint NULL,
    FOREIGN KEY (`swimmer_if_limitation`) REFERENCES `swimmer`(`id`),
    `total_amount` INT NOT NULL,
    `total_amount_paid` INT NOT NULL,
    `token` CHAR(64) NOT NULL,
    `token_expire_at` DATETIME NOT NULL,
    `is_canceled` TINYINT(1) NOT NULL DEFAULT '0',
    `is_checked` TINYINT(1) NOT NULL DEFAULT '0',
    `comments` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);
