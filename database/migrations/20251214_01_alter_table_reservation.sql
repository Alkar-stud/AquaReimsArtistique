-- Ajout du marqueur d'anonymisation et index sur reservation
ALTER TABLE `reservation`
    ADD COLUMN `anonymized_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX `idx_reservation_created_anonymized` (`created_at`, `anonymized_at`);
