-- Création de la table reservation_place_temp
CREATE TABLE IF NOT EXISTS `reservation_place_temp` (
    `session` VARCHAR(255) NOT NULL,
    `event_session_id` bigint NOT NULL ,
    FOREIGN KEY (`event_session_id`) REFERENCES `event_session`(`id`),
    `place_id` bigint NOT NULL ,
    FOREIGN KEY (`place_id`) REFERENCES `piscine_gradins_places`(`id`),
    `index` int(10) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `expire_at` DATETIME NOT NULL
);

/*
 index correspond à l'index des places dans $_SESSION
 */
