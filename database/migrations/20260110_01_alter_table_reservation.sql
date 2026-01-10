-- Ajout de l'ID en clé étrangère du user faisant l'action complements_given_at
ALTER TABLE `reservation` ADD `complements_given_by` BIGINT NULL DEFAULT NULL AFTER `complements_given_at`;

ALTER TABLE `reservation` ADD CONSTRAINT `reservation_ibfk_4` FOREIGN KEY (`complements_given_by`) REFERENCES `user`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;