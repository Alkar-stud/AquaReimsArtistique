<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventsRepository;
use app\Services\EventsService;
use app\Services\NageuseService;
use app\Services\ReservationService;
use app\Services\ReservationSessionService;

class ReservationAjaxController extends AbstractController
{
    private NageuseService $nageuseService;
    private ReservationSessionService $reservationSessionService;
    private ReservationService $reservationService;
    private EventsRepository $eventsRepository;
    private EventsService $eventsService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationSessionService = new ReservationSessionService();
        $this->nageuseService = new NageuseService();
        $this->reservationService = new ReservationService();
        $this->eventsRepository = new EventsRepository();
        $this->eventsService = new EventsService();
    }


    //======================================================================
    // ETAPE 1 : Sélection Événement / Séance / Nageuse
    //======================================================================
    #[Route('/reservation/check-nageuse-limit', name: 'check_nageuse_limit', methods: ['POST'])]
    public function checkNageuseLimit(): void
    {
        $this->checkCsrfOrExit('check-nageuse-limit', false);

        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = (int)($input['event_id'] ?? 0);
        $nageuseId = (int)($input['nageuse_id'] ?? 0);

        //On récupère la limite, et si elle est atteinte ou non et à combien on en est.
        $nageuseLimitReached = $this->nageuseService->checkNageuseLimit($eventId, $nageuseId);
        if ($nageuseLimitReached['limit'] !== null) {
            // On enregistre la limite en session pour les étapes suivantes
            $this->reservationSessionService->setReservationSession('limitPerSwimmer', $nageuseLimitReached['limit']);
        }

        $this->json(['success' => true, 'limiteAtteinte' => $nageuseLimitReached['limitReached']]);
    }

    #[Route('/reservation/validate-access-code', name: 'validate_access_code', methods: ['POST'])]
    public function validateAccessCode(): void
    {
        $this->checkCsrfOrExit('validate_access_code', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        if (!$eventId || !$code) {
            $this->json(['success' => false, 'error' => 'Veuillez fournir un événement et un code.']);
            return;
        }

        $result = $this->eventsService->validateAccessCode($eventId, $code);

        if ($result['success']) {
            $this->reservationSessionService->setReservationSession('access_code_used', ['event_id' => $eventId, 'code' => $code]);
        }

        $this->json($result);
    }

    /*
     * Pour valider et enregistrer en $_SESSION les valeurs de l'étape 1
     *
     */
    #[Route('/reservation/etape1', name: 'etape1', methods: ['POST'])]
    public function etape1(): void
    {
        $this->checkCsrfOrExit('reservation_etape1', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $eventId = (int)($input['event_id'] ?? 0);
        $sessionId = (int)($input['event_session_id'] ?? 0);
        $nageuseId = isset($input['nageuse_id']) ? (int)$input['nageuse_id'] : null;

        //On vérifie ce qui a été saisi
        $validationResult = $this->reservationService->verifyReservationPrerequisites($eventId, $sessionId, $nageuseId);

        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        $this->reservationSessionService->setReservationSession('event_id', $eventId);
        $this->reservationSessionService->setReservationSession('event_session_id', $sessionId);

        $this->json(['success' => true]);
    }

    //======================================================================
    // ETAPE 2 : Informations Personnelles
    //======================================================================


}