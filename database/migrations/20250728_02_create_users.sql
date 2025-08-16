-- Création de la table users
CREATE TABLE IF NOT EXISTS `users` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` varchar(255) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `email` varchar(128) NOT NULL UNIQUE,
    `display_name` varchar(128) DEFAULT NULL,
    `roles` bigint DEFAULT NULL,
    FOREIGN KEY (`roles`) REFERENCES `roles`(`id`),
    `is_actif` tinyint(1) NOT NULL DEFAULT '1',
    `password_reset_token` varchar(255) DEFAULT NULL,
    `password_reset_expires_at` datetime DEFAULT NULL,
    `session_id` varchar(255)DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (`email`)
);

-- Ajout du super admin
INSERT IGNORE INTO `user` (`id`, `username`, `password`, `email`, `roles`) VALUES
(1, 'Aradmin', 'MdpBidonAChangerAlInstall', 'example@email.com', 1);
