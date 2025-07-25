-- Création de la table user
CREATE TABLE `user` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` varchar(255) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `email` varchar(128) NOT NULL UNIQUE,
    `display_name` varchar(128) DEFAULT NULL,
    `roles` bigint DEFAULT NULL,
    FOREIGN KEY (`roles`) REFERENCES `roles`(`id`),
    `password_reset_token` varchar(255) DEFAULT NULL,
    `password_reset_expires_at` datetime DEFAULT NULL,
    `session_id` varchar(255)DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (`email`)
);

-- Ajout du super admin
INSERT INTO `user` (`id`, `username`, `password`, `email`, `roles`) VALUES
(1, 'Aradmin', '$2y$13$jisw5Ip67lSQiiPNbZ.1wOCLT5RqqQ/99RmZxwc1brlhaapJOTzom', 'example@email.com', 1);
