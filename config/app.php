<?php

use app\Repository\ConfigRepository;
use app\Core\Database;

/**
 * Ce fichier charge la configuration dynamique depuis la base de données
 * et la définit sous forme de constantes globales.
 */
try {
    Database::getInstance();
    $configRepo = new ConfigRepository();
    $configs = $configRepo->findAll(); // On récupère les objets pour accéder au type

    foreach ($configs as $conf) {
        $key = $conf->getConfigKey();
        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue; // sécurité
        }
        if (!defined($key)) {
            // La valeur est déjà typée par hydrate(), mais on peut renforcer:
            $value = $conf->getConfigValue();
            $type = $conf->getConfigType();

            switch ($type) {
                case 'bool':
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'int':
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;
                case 'email':
                case 'url':
                case 'string':
                default:
                    $value = (string)$value;
                    break;
            }

            define($key, $value);
        }
    }
} catch (PDOException $e) {
    echo "Erreur de connexion BDD : " . $e->getMessage();
}