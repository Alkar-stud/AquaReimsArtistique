-- Création de la table events_tarifs
CREATE TABLE IF NOT EXISTS `events_tarifs`
(
    `event` bigint NOT NULL,
    FOREIGN KEY (`event`) REFERENCES `events`(`id`),
    `tarif` bigint NOT NULL,
    FOREIGN KEY (`tarif`) REFERENCES `tarifs`(`id`)
);
