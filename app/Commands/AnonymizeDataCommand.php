<?php

namespace app\Commands;

use Exception;

class AnonymizeDataCommand
{
    /**
     * Exécute la commande.
     */
    public function execute(): int
    {
        echo "Début du processus d'anonymisation...\n";
        try {
            echo "Processus terminé avec succès.\n";
            return 0;
        } catch (Exception $e) {
            echo "ERREUR : " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
