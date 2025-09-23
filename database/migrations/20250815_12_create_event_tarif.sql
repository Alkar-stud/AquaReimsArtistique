-- Création de la table event_tarif
DROP TABLE IF EXISTS `event_tarif`;
CREATE TABLE `event_tarif` (
    `event` BIGINT NOT NULL,
    `tarif` BIGINT NOT NULL,
    PRIMARY KEY (`event`, `tarif`),
    CONSTRAINT `fk_event_tarif_event`
        FOREIGN KEY (`event`) REFERENCES `event`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_event_tarif_tarif`
        FOREIGN KEY (`tarif`) REFERENCES `tarif`(`id`) ON DELETE CASCADE
);
