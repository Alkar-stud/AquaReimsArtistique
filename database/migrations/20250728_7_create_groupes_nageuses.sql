-- Création de la table nageuses_groupes
CREATE TABLE IF NOT EXISTS `nageuses_groupes` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` char(64) NOT NULL,
    `coach` varchar(255) NULL,
    `is_active` BOOLEAN NOT NULL,
    `order` int NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL
);

