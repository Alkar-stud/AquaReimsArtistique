-- complète de la config de base
INSERT IGNORE INTO `config` (`libelle`, `config_key`, `config_value`, `config_type`) VALUES
    ('Dossier pour l\'upload des justificatifs', 'UPLOAD_PROOF_PATH', '/storage/proofs/', 'string'),
    ('Taille maxi des fichiers (Mo) justificatif pour preuve de tarif', 'MAX_UPLOAD_PROOF_SIZE', '4', 'int'),
    ('Token pour cron', 'ACCESS_TOKEN', 'TokenAGenerer4deux3quatrechiffresOuPlusAvecToutPleinDeLettres', 'string'),
    ('nb caractère pour token', 'NB_CARACTERE_TOKEN', '32', 'int'),
    ('Signature des mails', 'SIGNATURE', 'Aqua Reims Artistique vous remercie et à bientôt...', 'string');