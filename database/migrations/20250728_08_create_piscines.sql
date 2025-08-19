-- Création de la table piscines
CREATE TABLE IF NOT EXISTS `piscines` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` char(64) NOT NULL,
    `adresse` longtext NOT NULL,
    `max_places` int NOT NULL,
    `numbered_seats` tinyint(1) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

