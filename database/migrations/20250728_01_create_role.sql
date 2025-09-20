-- Ajout de la table roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `label` char(64) NOT NULL,
    `level` tinyint NOT NULL UNIQUE,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);


-- Ajout des roles
INSERT IGNORE INTO `roles` (`label`, `level`) VALUES
    ( 'Super administrateur', 0),
    ( 'Administrateur', 1),
    ( 'Bureau', 2),
    ( 'CoDir', 3),
    ( 'Bénévole', 4);