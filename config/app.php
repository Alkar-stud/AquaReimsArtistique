<?php

use app\Repository\ConfigRepository;
use app\Core\Database;

/**
 * Ce fichier charge la configuration dynamique depuis la base de données
 * et la définit sous forme de constantes globales.
 */
try {
    Database::getInstance();
    // Récupérer les configurations
    $configRepo = new ConfigRepository();
    $appConfigs = $configRepo->findAllAsKeyValue();

    // Définir chaque configuration comme une constante
    foreach ($appConfigs as $key => $value) {
        // On vérifie si la constante n'est pas déjà définie pour éviter les erreurs
        if (!defined($key)) {
            define($key, $value);
        }
    }
} catch (PDOException $e) {
    echo "Erreur de connexion BDD : " . $e->getMessage();
}