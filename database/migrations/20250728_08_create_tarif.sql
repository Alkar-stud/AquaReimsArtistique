-- Création de la table tarif
CREATE TABLE IF NOT EXISTS `tarif` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NULL,
    `seat_count` INT NULL,
    `min_age` INT NULL,
    `max_age` INT NULL,
    `max_tickets` INT NULL,
    `price` INT NOT NULL,
    `includes_program` BOOLEAN NOT NULL,
    `requires_proof` BOOLEAN  NOT NULL,
    `access_code` CHAR(32) NULL,
    `is_active` BOOLEAN NOT NULL ,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

