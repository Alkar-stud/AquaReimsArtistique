-- Création de la table tarifs
CREATE TABLE IF NOT EXISTS `tarifs` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NULL,
    `nb_place` INT NULL,
    `age_min` INT NULL,
    `age_max` INT NULL,
    `max_tickets` INT NULL,
    `price` INT NOT NULL,
    `is_program_show_include` BOOLEAN NOT NULL,
    `is_proof_required` BOOLEAN  NOT NULL,
    `access_code` CHAR(32) NULL,
    `is_active` BOOLEAN NOT NULL ,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

