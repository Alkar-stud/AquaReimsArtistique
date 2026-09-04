<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Payment\HelloAssoService;
use app\Services\Reservation\ReservationFinalSummaryService;
use Throwable;

class HelloAssoController extends AbstractController
{
    private ReservationFinalSummaryService $reservationFinalSummaryService;
    private HelloAssoService $helloAssoService;

    public function __construct(
        ReservationFinalSummaryService $reservationFinalSummaryService
    ) {
        parent::__construct(false);

        $this->reservationFinalSummaryService = $reservationFinalSummaryService;
        $this->helloAssoService = new HelloAssoService();
    }

    #[Route('/gestion/command-helloasso', name: 'app_gestion_command_helloasso')]
    public function commandHelloAsso(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(
            4,
            'command-helloasso'
        );

        $orderId = null;
        $orderData = null;
        $error = null;

        /*
         * La recherche de la commande se fait en GET.
         *
         * Exemple :
         * /gestion/command-helloasso?orderId=190741272
         */
        if (isset($_GET['orderId'])) {
            $orderIdValue = trim((string) $_GET['orderId']);

            if (
                $orderIdValue === '' ||
                !ctype_digit($orderIdValue) ||
                (int) $orderIdValue <= 0
            ) {
                $error = 'Le numéro de commande HelloAsso est invalide.';
            } else {
                $orderId = (int) $orderIdValue;

                try {
                    $order = $this->helloAssoService->GetOrder($orderId);

                    $orderData = $this->helloAssoService
                        ->GetUsefulOrderData($order);

                } catch (Throwable $e) {
                    if ($e->getCode() === 404) {
                        $error = 'Commande HelloAsso introuvable.';
                    } else {
                        /*
                         * On ne transmet volontairement pas le détail
                         * de l'exception au visiteur.
                         */
                        $error = 'Impossible de récupérer la commande HelloAsso.';
                    }
                }
            }
        }

        $this->render(
            '/gestion/command-helloasso',
            [
                'orderId' => $orderId,
                'orderData' => $orderData,
                'error' => $error,
            ],
            'Vérification d\'une commande HelloAsso'
        );
    }
}