-- Ajout du marqueur d'anonymisation et index sur reservation_detail
ALTER TABLE `reservation_detail`
    ADD COLUMN `anonymized_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX `idx_reservation_detail_anonymized` (`created_at`, `anonymized_at`);
