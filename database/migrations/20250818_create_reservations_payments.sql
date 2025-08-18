-- Création de la table reservations_payments
CREATE TABLE IF NOT EXISTS `reservations_payments` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservations`(`id`),
    `amount_paid` DECIMAL(10, 2) NOT NULL,
    `checkout_id` INT NOT NULL,
    `status_payment` CHAR(32) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);