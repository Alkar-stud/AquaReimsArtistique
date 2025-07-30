-- Ajout de la table roles
CREATE TABLE `roles` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `libelle` char(64) NOT NULL,
    `level` tinyint NOT NULL UNIQUE,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);


-- Ajout des roles
INSERT INTO `roles` (`libelle`, `level`) VALUES
    ( 'Super administrateur', 0),
    ( 'Administrateur', 1),
    ( 'Bureau', 2),
    ( 'CoDir', 3),
    ( 'Bénévole', 4);