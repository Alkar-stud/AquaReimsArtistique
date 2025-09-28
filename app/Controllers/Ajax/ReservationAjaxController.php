<?php

namespace app\Controllers\Ajax;

use app\Attributes\Route;
use app\Controllers\AbstractController;

use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationAjaxController extends AbstractController
{

    private ReservationSessionService $reservationSessionService;
    private EventQueryService $eventQueryService;
    private SwimmerQueryService $swimmerQueryService;

    public function __construct(
        ReservationSessionService $reservationSessionService,
        EventQueryService $eventQueryService,
        SwimmerQueryService $swimmerQueryService,
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->reservationSessionService = $reservationSessionService;
        $this->eventQueryService = $eventQueryService;
        $this->swimmerQueryService = $swimmerQueryService;
    }

    //======================================================================
    // ETAPE 1 : Sélection Événement / Séance / Nageur
    //======================================================================

    #[Route('/reservation/check-swimmer-limit', name: 'check_swimmer_limit', methods: ['POST'])]
    public function checkSwimmerLimit(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = (int)($input['event_id'] ?? 0);
        $swimmerId = (int)($input['swimmer_id'] ?? 0);

        //On récupère la limite, et si elle est atteinte ou non et à combien on en est.
        $swimmerLimitReached = $this->swimmerQueryService->checkSwimmerLimit($eventId, $swimmerId);
        // On enregistre la limite en session pour les étapes suivantes, même si elle est null
        $this->reservationSessionService->setReservationSession('limitPerSwimmer', $swimmerLimitReached['limit']);

        $this->json(['success' => true, 'limiteAtteinte' => $swimmerLimitReached['limitReached']]);
    }

    #[Route('/reservation/validate-access-code', name: 'validate_access_code', methods: ['POST'])]
    public function validateAccessCode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        if (!$eventId || !$code) {
            $this->json(['success' => false, 'error' => 'Veuillez fournir un événement et un code.']);
            return;
        }

        $result = $this->eventQueryService->validateAccessCode($eventId, $code);

        if ($result['success']) {
            //$this->reservationSessionService->setReservationSession('access_code_used', new ReservationAccessCodeDTO($eventId, $code));
        }

        $this->json($result);
    }

    /**
     * Pour valider et enregistrer en $_SESSION les valeurs de l'étape 1
     *
     */
    #[Route('/reservation/etape1', name: 'etape1', methods: ['POST'])]
    public function etape1(): void
    {


    }


}