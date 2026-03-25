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
     * Exécute la commande CLI.
     */
    public function execute(): int
    {
        echo "Début du processus d'anonymisation...\n";

        try {
            $result = $this->runAnonymization();
            $this->displayResults($result);
            return 0;
        } catch (Exception $e) {
            echo "ERREUR : " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Logique métier réutilisable.
     *
     * @return array{threshold_date: string, anonymized_reservations: int, anonymized_details: int}
     * @throws Exception
     */
    private function runAnonymization(): array
    {
        $retentionPeriod = $_ENV['PERSONAL_DATA_RETENTION_DAYS'] ?? 'P3Y';
        $anonymizer = new AnonymizeDataService($retentionPeriod);
        return $anonymizer->run();
    }

    /**
     * Affiche les résultats en CLI.
     */
    private function displayResults(array $result): void
    {
        echo "Anonymisation des données antérieures à : " . $result['threshold_date'] . "\n";
        echo "Nombre de réservations traitées : " . $result['anonymized_reservations'] . "\n";
        echo "Nombre de détails de réservations traitées : " . $result['anonymized_details'] . "\n";
        echo "Processus terminé avec succès.\n";
    }
}
