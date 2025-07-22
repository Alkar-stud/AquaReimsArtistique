<?php

// On parcourt $_ENV pour construire le tableau $config spécifique à l'application.
foreach ($_ENV as $key => $value) {
    // On s'assure que la clé est bien au format attendu (lettres majuscules et underscores)
    if (preg_match('/^[A-Z0-9_]+$/', $key)) {
        // On convertit la clé en minuscules pour le tableau $config, comme dans votre version originale.
        $config[strtolower($key)] = $value;
    }
}
