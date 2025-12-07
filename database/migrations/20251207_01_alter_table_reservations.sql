-- Modification de la table reservation
ALTER TABLE
    `reservation` ADD `rgpd_consent` datetime NULL DEFAULT NULL AFTER `swimmer_if_limitation`;

-- Modification de la table reservation_temp
ALTER TABLE
    `reservation_temp` ADD `rgpd_consent` datetime NULL DEFAULT NULL AFTER `swimmer_if_limitation`;

