-- Création de la table reservations_places_temp
CREATE TABLE IF NOT EXISTS `reservations_places_temp` (
    `session` VARCHAR(255) NOT NULL,
    `event_session_id` bigint NOT NULL ,
    FOREIGN KEY (`event_session_id`) REFERENCES `event_sessions`(`id`),
    `place_id` bigint NOT NULL ,
    FOREIGN KEY (`place_id`) REFERENCES `piscine_gradins_places`(`id`),
    `index` int(10) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `timeout` DATETIME NOT NULL
);

/*
 index correspond à l'index des places dans $_SESSION
 */