-- Création de la table reservation_payment
CREATE TABLE IF NOT EXISTS `reservation_payment` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `reservation` bigint NOT NULL,
    FOREIGN KEY (`reservation`) REFERENCES `reservation`(`id`),
    `type` CHAR(5) NOT NULL,
    `amount_paid` INT NOT NULL,
    `part_of_donation` INT NULL,
    `checkout_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `payment_id` INT NOT NULL,
    `status_payment` CHAR(32) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);
