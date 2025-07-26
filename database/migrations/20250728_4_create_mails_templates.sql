-- Ajout des mails_templates
CREATE TABLE IF NOT EXISTS `mails_templates` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` char(64)  NOT NULL UNIQUE,
    `subject` varchar(255) NOT NULL,
    `body_html` longtext,
    `body_text` longtext,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);


-- Ajout des mails_templates
INSERT INTO `mails_templates` (`code`, `subject`, `body_html`, `body_text`) VALUES
    ( 'password_reset', 'Réinitialisation de votre mot de passe', '<p>Bonjour {username},</p>\r\n<p>Vous avez demandé une réinitialisation de votre mot de passe. Veuillez cliquer sur le lien ci-dessous pour continuer :</p>\r\n<p><a href=\"{link}\">Réinitialiser mon mot de passe</a></p>\r\n<p>Si vous n\'êtes pas à l\'origine de cette demande, veuillez ignorer cet email.</p>\r\n<p>Ce lien expirera dans une heure.</p>', 'Bonjour {username},\r\nVous avez demandé une réinitialisation de votre mot de passe. Veuillez copier-coller le lien suivant dans votre navigateur pour continuer :\r\n{link}\r\n\r\nSi vous n\'êtes pas à l\'origine de cette demande, veuillez ignorer cet email.\r\n\r\nCe lien expirera dans une heure.'),
    ('password_modified', 'Votre mot de passe a été modifié', '<p>Bonjour {username},</p>\r\n<p>Votre mot de passe a été modifié.</p>\r\n<p>Si vous n\'êtes pas à l\'origine de cette modification, veuillez aller vous connecter en cliquant sur mot de passe oublié à l\'aide de cette adresse mail.</p>\r\n<p>Si cela est impossible ou ne fonctionne pas, veuillez nous contacter rapidement à {email_club}</p>', 'Bonjour {username},\r\nVotre mot de passe a été modifié.\r\nSi vous n\'êtes pas à l\'origine de cette modification, veuillez aller vous connecter en cliquant sur mot de passe oublié à l\'aide de cette adresse mail.\r\nSi cela est impossible ou ne fonctionne pas, veuillez nous contacter rapidement à {email_club}.');