-- Modification de la table reservation_temp
ALTER TABLE
    `reservation_temp` ADD `is_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `swimmer_if_limitation`;
