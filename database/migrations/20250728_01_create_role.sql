-- Ajout de la table role
CREATE TABLE IF NOT EXISTS `role` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `label` char(64) NOT NULL,
    `level` tinyint NOT NULL UNIQUE,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
