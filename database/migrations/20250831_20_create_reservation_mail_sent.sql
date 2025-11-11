-- Création de la table reservation_mail_sent
CREATE TABLE IF NOT EXISTS `reservation_mail_sent`
(
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservation`(`id`),
    `mail_template` bigint NOT NULL,
    FOREIGN KEY (`mail_template`) REFERENCES `mail_template`(`id`),
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
