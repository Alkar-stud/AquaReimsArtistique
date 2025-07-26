-- Création de la table nageuses
CREATE TABLE IF NOT EXISTS `nageuses` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` char(64) NOT NULL,
    `groupe` bigint NULL,
    FOREIGN KEY (`groupe`) REFERENCES `nageuses_groupes`(`id`),
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);

