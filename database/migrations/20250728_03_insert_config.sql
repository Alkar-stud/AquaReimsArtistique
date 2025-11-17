-- Ajout de la config de base
INSERT IGNORE INTO `config` (`label`, `config_key`, `config_value`, `config_type`) VALUES
('Maintenance', 'MAINTENANCE', '0', 'bool'),
('Email du club Aqua Reims Artistique', 'EMAIL_CLUB', 'email@club.fr', 'email'),
('Email des galas Aqua Reims Artistique', 'EMAIL_GALA', 'gala@club.fr', 'email'),
('Temps pour le timeout de session', 'TIMEOUT_SESSION', 'PT30M', 'string'),
('Temps pour le timeout de réservation de place', 'TIMEOUT_PLACE_RESERV', 'PT20M', 'string'),
('Durée max des log en jours', 'MAX_LOGS_LIFE', '30', 'int'),
('Durée max des log en nombre d\'entrée', 'MAX_LOGS_SIZE', '100000', 'int'),
('Dossier pour l\'upload des justificatifs', 'UPLOAD_PROOF_PATH', '/storage/proofs/', 'string'),
('Taille maxi des fichiers (Mo) justificatif pour preuve de tarif', 'MAX_UPLOAD_PROOF_SIZE', '4', 'int'),
('Token pour cron', 'CRON_TOKEN', 'ToeknAGenerer', 'string'),
('nb caractère pour token', 'NB_CARACTERE_TOKEN', '32', 'int'),
('Signature des mails', 'SIGNATURE', 'Aqua Reims Artistique vous remercie et à bientôt...', 'string'),
('% max de la commande pour la jauge de don', 'DONATION_SLIDER_MAX_PERCENTAGE', '50', 'int');
