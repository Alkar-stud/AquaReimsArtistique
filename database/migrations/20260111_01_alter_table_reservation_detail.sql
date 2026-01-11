-- Ajout de l'ID en clé étrangère du user faisant l'action complements_given_at
ALTER TABLE `reservation_detail` ADD `entry_validate_by` BIGINT NULL DEFAULT NULL AFTER `entered_at`;

ALTER TABLE `reservation_detail` ADD CONSTRAINT `reservation_detail_ibfk_4` FOREIGN KEY (`entry_validate_by`) REFERENCES `user`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;