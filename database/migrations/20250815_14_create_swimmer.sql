-- Création de la table swimmer
CREATE TABLE IF NOT EXISTS `swimmer` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` char(64) NOT NULL,
    `group` bigint NULL,
    FOREIGN KEY (`group`) REFERENCES `swimmer_group`(`id`),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

