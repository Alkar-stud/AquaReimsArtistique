<?php

namespace app\Controllers\Ajax;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\ReservationDetailItemDTO;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Event\EventQueryService;
use app\Services\Swimmer\SwimmerQueryService;
use app\Services\Tarif\TarifService;

class ReservationAjaxController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private SwimmerQueryService $swimmerQueryService;
    private ReservationDataValidationService $reservationDataValidationService;
    private ReservationQueryService $reservationQueryService;
    private TarifService $tarifService;
    //Pour définir les steps existants
    private array $existingStep = [1, 2, 3, 4, 5, 6];

    public function __construct(
        EventQueryService $eventQueryService,
        SwimmerQueryService $swimmerQueryService,
        ReservationSessionService $reservationSessionService,
        ReservationDataValidationService $reservationDataValidationService,
        ReservationQueryService $reservationQueryService,
        TarifService $tarifService,
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->eventQueryService = $eventQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->reservationQueryService = $reservationQueryService;
        $this->tarifService = $tarifService;
    }


    /**
     * Pour valider et enregistrer en $_SESSION les valeurs des différentes étapes
     * @param int $step
     * @return void
     *
     */
    #[Route('/reservation/valid/{step}', name: 'etape1', methods: ['POST'])]
    public function validStep(int $step): void
    {
        //On vérifie
        if (!in_array($step, $this->existingStep)) {
            $this->json(['success' => false, 400, 'error' => 'Cette étape n\'existe pas']);
        }

        // On redirige si session de réservation est expirée
        $session = $this->redirectIfReservationSessionIsExpired();

        //on vérifie les données des étapes précédentes
        for ($i = 1; $i <= $step; $i++) {
            $return = $this->reservationDataValidationService->checkPreviousStep($i, $session);
            if (!$return['success']) {
                $this->json($return, 400);
            }
        }

        //On récupère les données step4, il y a potentiellement des fichiers
        if ($step == 4) {
            $input = json_decode($_POST['participants'] ?? '[]', true);
            $files = $_FILES;

        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = [];
            }
            $files = null;
        }

        $result = $this->reservationDataValidationService->validateAndPersistDataPerStep($step, $input, $files);

        if (!$result['success']) {
            $this->json($result, 400);
        }

        //selon si place de la piscine sont numérotées au pas
        $this->json(['success' => true, 'numerated_seat' => $result['data']['numerated_seat']]);
    }


    //======================================================================
    // ETAPE 1 : Sélection Événement / Séance / Nageur
    //======================================================================

    /**
     * Pour vérifier si la limite est atteinte
     * Renvoi en JSON limitPerSwimmer pour le JS et limiteAtteinte true ou false
     *
     * @return void
     */
    #[Route('/reservation/check-swimmer-limit', name: 'check_swimmer_limit', methods: ['POST'])]
    public function checkSwimmerLimit(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = (int)($input['event_id'] ?? 0);
        $swimmerId = (int)($input['swimmer_id'] ?? 0);

        //On récupère la limite, et si elle est atteinte ou non et à combien on en est.
        $swimmerLimitReached = $this->swimmerQueryService->checkSwimmerLimit($eventId, $swimmerId);

        // On enregistre la limite en session pour les étapes suivantes, même si elle est null
        $this->reservationSessionService->setReservationSession('limit_per_swimmer', $swimmerLimitReached['limit']);

        $this->json([
            'success' => true,
            'limiteAtteinte' => $swimmerLimitReached['limitReached'],
            'limitPerSwimmer' => $swimmerLimitReached['limit']
        ]);
    }

    /**
     * @return void
     */
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
            $this->reservationSessionService->setReservationSession('access_code_used', $code);
        }

        $this->json($result);
    }

    //======================================================================
    // ETAPE 2 : Informations Personnelles
    //======================================================================

    /**
     * Pour vérifier si un email a déjà été utilisé pour une réservation
     */
    #[Route('/reservation/check-duplicate-email', name: 'check-duplicate-email', methods: ['POST'])]
    public function checkDuplicateEmailInReservation(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        $result = $this->reservationQueryService->checkExistingReservationWithSameEmail($eventId, $email);

        $this->json($result);
    }

    /**
     * Pour renvoyer le mail de confirmation d'une commande
     */
    #[Route('/reservation/resend-confirmation', name: 'resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        $result = $this->reservationQueryService->resendConfirmationEmails($eventId, $email);

        $this->json($result);
    }

    /**
     * Redirige vers session expirée si timeout session réservation est expiré
     *
     * @return array|null
     */
    private function redirectIfReservationSessionIsExpired(): ?array
    {
        $session = $this->reservationSessionService->getReservationSession();
        if (!empty($session) && $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->json([
                'success' => false,
                'error' => 'Votre session a expiré. Merci de recommencer.',
                'redirect' => '/reservation?session_expiree=1'
            ], 440);
        }
        // Met à jour le timestamp à chaque vérification
        $_SESSION['reservation']['last_activity'] = time();
        $this->reservationSessionService->setReservationSession('last_activity', $_SESSION['reservation']['last_activity']);

        return $session;
    }

    //======================================================================
    // ETAPE 3 : Choix des places
    //======================================================================

    //Pour valider les tarifs avec code
    /**
     * @return void
     */
    #[Route('/reservation/validate-special-code', name: 'validate_special_code', methods: ['POST'])]
    public function validateSpecialCode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        //On va chercher le tarif correspondant s'il y en a un
        $result = $this->tarifService->validateSpecialCode($eventId, $code);
        if (!$result['success']) {
            $this->json($result, 200, 'reservation');
        }

        //On construit le DTO pour persister le code dans $_SESSION
        // On récupère les détails actuels pour y ajouter le nouveau
        $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_detail'] ?? [];
        $dto = ReservationDetailItemDTO::fromArrayWithSpecialPrice($result['tarif']['id'], [], $code);
        $currentDetails[] = $dto->jsonSerialize();
        $this->reservationSessionService->setReservationSession('reservation_detail', $currentDetails);

        $this->json([
            'success'            => true,
            'tarif'              => $result['tarif'],
            'reservation_detail' => $currentDetails
        ], 200, 'reservation');
    }

    /**
     * @return void
     */
    #[Route('/reservation/remove-special-tarif', name: 'remove_special_tarif', methods: ['POST'])]
    public function removeSpecialTarif(): void
    {
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

        $this->json(['success' => true], 200, 'reservation');
    }

}