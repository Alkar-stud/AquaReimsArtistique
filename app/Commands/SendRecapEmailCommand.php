<?php

namespace app\Commands;

use app\Services\Reservation\ReservationFinalSummaryService;
use app\Traits\HasPdoConnection;
use Exception;

class SendRecapEmailCommand
{
    use HasPdoConnection;

    private ReservationFinalSummaryService $reservationFinalSummaryService;

    public function __construct()
    {
        $this->initPdo();
        $this->reservationFinalSummaryService = new ReservationFinalSummaryService();
    }

    /**
     * Exécute la commande.
     *
     * @param int $limit Nombre maximum d'emails à envoyer
     */
    public function execute(int $limit = 100): int
    {
        echo "Début de l'envoi des emails récapitulatifs...\n";
        echo "Limite d'envoi : $limit\n";

        try {
            $result = $this->reservationFinalSummaryService->sendFinalEmail($limit, true);

            echo "Nombre d'emails envoyés : " . ($result['sent'] ?? 0) . "\n";
            echo "Nombre d'erreurs : " . ($result['errors'] ?? 0) . "\n";
            echo "Processus terminé avec succès.\n";
            return 0;
        } catch (Exception $e) {
            echo "ERREUR : " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
