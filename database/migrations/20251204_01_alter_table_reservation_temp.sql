-- Modification de la table reservation_temp
ALTER TABLE
    `reservation_temp` ADD `total_amount` INT NULL DEFAULT NULL AFTER `swimmer_if_limitation`;

