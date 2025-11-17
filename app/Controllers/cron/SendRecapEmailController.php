<?php

namespace app\Controllers\cron;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Reservation\ReservationFinalSummaryService;
use Exception;

class SendRecapEmailController extends AbstractController
{
    public function __construct(
        private readonly ReservationFinalSummaryService $reservationFinalSummaryService
    ) {
        parent::__construct(true); // On déclare en route publique, car la tâche cron n'aura pas besoin d'être authentifiée. La route est protégée par un token.
    }

    /**
     * GET /reservations/send-final-recap?token=...&limit=100
     * - token: sécurité basique (présent dans la constante CRON_TOKEN de la table config)
     * - limit: nombre max d'envois (défaut 100)
     *
     */
    #[Route('/reservations/send-final-recap', name: 'app_reservations_send-final-recap', methods: ['GET'])]
    public function sendFinalRecapEmail(): void
    {
        $cronToken = (string)($_GET['token'] ?? '');
        if ($cronToken !== CRON_TOKEN) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Token invalide.'], 403);
        }

        $limit = (int)($_GET['limit'] ?? 100);
        $limit = $limit > 0 ? $limit : 100;

        try {
            $result = $this->reservationFinalSummaryService->sendFinalEmail($limit, false);
            $this->json(['success' => true, 'result' => $result]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}