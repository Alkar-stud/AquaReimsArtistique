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

INSERT INTO `piscines` (`libelle`, `adresse`, `max_places`, `numbered_seats`) VALUES
    ('Thiolettes', '77 Avenue de l\'Europe 51100 Reims', 149, 0),
    ('UCPA Reims', '5 Boulevard Jules César 51100 Reims', 560, 1);