<?php

namespace app\Commands;

use app\Services\Anonymize\AnonymizeDataService;
use app\Traits\HasPdoConnection;
use Exception;

class AnonymizeDataCommand
{
    use HasPdoConnection;

    public function __construct()
    {
        $this->initPdo();
    }

    /**
     * Exécute la commande.
     */
    public function execute(): int
    {
        echo "Début du processus d'anonymisation...\n";
        try {
            $retentionPeriod = $_ENV['PERSONAL_DATA_RETENTION_DAYS'] ?? 'P3Y'; // Valeur par défaut de 3 ans

            $anonymizer = new AnonymizeDataService($retentionPeriod);
            $result = $anonymizer->run();

            echo "Anonymisation des données antérieures à : " . $result['threshold_date'] . "\n";
            echo "Nombre de réservations traitées : " . $result['anonymized_reservations'] . "\n";
            echo "Nombre de détails de réservations traitées : " . $result['anonymized_details'] . "\n";
            echo "Processus terminé avec succès.\n";
            return 0;
        } catch (Exception $e) {
            echo "ERREUR : " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
