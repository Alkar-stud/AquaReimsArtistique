-- Modification de la table mail_template pour ajouter la colonne requires_resume_attachment
ALTER TABLE `mail_template` ADD `requires_resume_attachment` INT NULL DEFAULT NULL AFTER `body_text`;
