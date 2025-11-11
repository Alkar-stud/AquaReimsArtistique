-- Création de la table user
CREATE TABLE IF NOT EXISTS `user` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` varchar(255) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `email` varchar(128) NOT NULL UNIQUE,
    `display_name` varchar(128) DEFAULT NULL,
    `role` bigint DEFAULT NULL,
    FOREIGN KEY (`role`) REFERENCES `role`(`id`),
    `is_actif` tinyint(1) NOT NULL DEFAULT '1',
    `password_reset_token` varchar(255) DEFAULT NULL,
    `password_reset_expires_at` datetime DEFAULT NULL,
    `session_id` varchar(255)DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (`email`)
);
