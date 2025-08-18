-- Création de la table reservations_mails_sent
CREATE TABLE IF NOT EXISTS `reservations_mails_sent`
(
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservations`(`id`),
    `mail_template` bigint NOT NULL,
    FOREIGN KEY (`mail_template`) REFERENCES `mails_templates`(`id`),
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);