<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\ReservationAccessCodeDTO;
use app\DTO\ReservationDetailItemDTO;
use app\Services\EventsService;
use app\Services\NageuseService;
use app\Services\ReservationService;
use app\Services\ReservationSessionService;
use app\Services\TarifService;
use Exception;

class ReservationAjaxController extends AbstractController
{
    private NageuseService $nageuseService;
    private ReservationSessionService $reservationSessionService;
    private ReservationService $reservationService;
    private EventsService $eventsService;
    private TarifService $tarifService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationSessionService = new ReservationSessionService();
        $this->nageuseService = new NageuseService();
        $this->reservationService = new ReservationService();
        $this->eventsService = new EventsService();
        $this->tarifService = new TarifService();
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
        // On enregistre la limite en session pour les étapes suivantes, même si elle est null
        $this->reservationSessionService->setReservationSession('limitPerSwimmer', $nageuseLimitReached['limit']);

        $this->json(['success' => true, 'limiteAtteinte' => $nageuseLimitReached['limitReached']]);
    }


    /**
     * @return void
     * @throws Exception
     */
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
            $this->reservationSessionService->setReservationSession('access_code_used', new ReservationAccessCodeDTO($eventId, $code));
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
        $validationResult = $this->reservationService->verifyPrerequisitesStep1($eventId, $sessionId, $nageuseId);

        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        // On enregistre les valeurs qui dépendent des choix de l'utilisateur
        $this->reservationSessionService->setReservationSession('nageuse_id', $nageuseId);
        $this->reservationSessionService->setReservationSession('event_id', $eventId);
        $this->reservationSessionService->setReservationSession('event_session_id', $sessionId);

        $this->json(['success' => true]);
    }

    //======================================================================
    // ETAPE 2 : Informations Personnelles
    //======================================================================

    /*
     * Pour vérifier si un email a déjà été utilisé pour une réservation
     */
    #[Route('/reservation/check-duplicate-email', name: 'check-duplicate-email', methods: ['POST'])]
    public function checkDuplicateEMailInReservation(): void
    {
        $this->checkCsrfOrExit('check_email', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        if (empty($email) || empty($eventId)) {
            $this->json(['exists' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        $result = $this->reservationService->checkExistingReservations($eventId, $email);

        if ($result['exists']) {
            // Ajoute le token CSRF à la réponse si des réservations existent
            $result['csrf_token'] = $this->getCsrfToken();
        }
        $this->json($result);
    }

    #[Route('/reservation/resend-confirmation', name: 'resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(): void
    {
        $this->checkCsrfOrExit('resend_confirmation', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        $result = $this->reservationService->resendConfirmationEmails($eventId, $email);
        $this->json($result);
    }

    #[Route('/reservation/etape2', name: 'etape2', methods: ['POST'])]
    public function etape2(): void
    {
        $this->checkCsrfOrExit('etape2', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email = trim($input['email'] ?? '');
        $telephone = trim($input['telephone'] ?? '');

        // Validation des informations du payer
        $validationResult = $this->reservationService->validatePayerInformation($nom, $prenom, $email, $telephone);
        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        // Enregistrement en session
        $this->reservationSessionService->setReservationSession('user', $validationResult['data']);

        $this->json(['success' => true]);
    }


    //======================================================================
    // ETAPE 3 : Choix des places
    //======================================================================

    //Pour valider les tarifs avec code
    #[Route('/reservation/validate-special-code', name: 'validate_special_code', methods: ['POST'])]
    public function validateSpecialCode(): void
    {
        $this->checkCsrfOrExit('validate_special_code', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        $result = $this->tarifService->validateSpecialCode($eventId, $code);

        if ($result['success']) {
            // On récupère les détails actuels pour y ajouter le nouveau
            $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_detail'] ?? [];
            $currentDetails[] = new ReservationDetailItemDTO(tarif_id: $result['tarif']['id'], access_code: $code);
            $this->reservationSessionService->setReservationSession('reservation_detail', $currentDetails);
        }

        $this->json($result);
    }

    #[Route('/reservation/remove-special-tarif', name: 'remove_special_tarif', methods: ['POST'])]
    public function removeSpecialTarif(): void
    {
        $this->checkCsrfOrExit('remove_special_tarif', false);
        $input = json_decode(file_get_contents('php://input'), true);

        $tarifId = (int)($input['tarif_id'] ?? 0);
        if (!$tarifId) {
            $this->json(['success' => false, 'error' => 'Paramètre manquant']);
            return;
        }

        // Récupérer les détails actuels de la session
        $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_detail'] ?? [];
        // Utiliser le service pour retirer le tarif
        $newDetails = $this->tarifService->removeTarifFromDetails($currentDetails, $tarifId);
        // Mettre à jour la session
        $this->reservationSessionService->setReservationSession('reservation_detail', $newDetails);

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape3', name: 'etape3', methods: ['POST'])]
    public function etape3(): void
    {
        $this->checkCsrfOrExit('etape3');
        $input = json_decode(file_get_contents('php://input'), true);
        $reservationData = $this->reservationSessionService->getReservationSession();

        // Le service gère toute la logique de validation et de construction des détails
        $result = $this->reservationService->processAndValidateStep3Submission($input, $reservationData);

        if ($result['success']) {
            // Si c'est un succès, on met à jour la session avec les nouvelles données
            $this->reservationSessionService->setReservationSession('reservation_detail', $result['data']);
            $this->json(['success' => true]);
        } else {
            // Sinon, on renvoie l'erreur fournie par le service
            $this->json($result);
        }
    }


}