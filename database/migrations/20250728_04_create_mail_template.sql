-- Ajout des mail_template
CREATE TABLE IF NOT EXISTS `mail_template` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` char(64)  NOT NULL UNIQUE,
    `subject` varchar(255) NOT NULL,
    `body_html` longtext,
    `body_text` longtext,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);
