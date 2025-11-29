<?php

namespace app\Controllers\Ajax;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\ReservationComplementItemDTO;
use app\DTO\ReservationDetailItemDTO;
use app\Repository\Tarif\TarifRepository;
use app\Services\FlashMessageService;
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
    #[Route('/reservation/valid/{step}', name: 'etapes', methods: ['POST'])]
    public function validStep(int $step): void
    {
        //On vérifie
        if (!in_array($step, $this->existingStep)) {
            $this->json(['success' => false, 400, 'error' => 'Cette étape n\'existe pas']);
        }

        //On récupère la session dans $_SESSION
        //$session = $this->reservationSessionService->getReservationSession();
        //On récupère la réservation en cours.
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée : si on ne trouve rien ET qu'on a dépassé l'étape1, c'est que c'est expiré.
        if (!$session['reservation'] && $step > 1) {
            $flashMessageService = new FlashMessageService();
            $flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->json([
                'success'  => false,
                'redirect' => '/reservation?session_expiree=ra'
            ], 200);
        }

        //Pour la troisième étape, on vérifie si la capacité globale n'est pas dépassée
        if ($step == 3) {
            $totalCapacityLimit = $this->reservationQueryService->checkTotalCapacityLimit($session['reservation']->getEvent(), $session['reservation']->getEventSession());
            if ($totalCapacityLimit['limitReached']) {
                $this->json([
                    'success' => false,
                    'error' => 'La capacité maximale de la piscine est atteinte.',
                    'limit' => $totalCapacityLimit['limit']
                ]);
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

        $result = $this->reservationDataValidationService->validateDataPerStep($session['reservation'], $step, $input, $files);
if ($step >= 5) {
    print_r($input);
    print_r($result);
    die;
}

//        $result = $this->reservationDataValidationService->validateAndPersistDataPerStep($step, $input, $files);

        if (!$result['success']) {
            $this->json($result, 200);
        }

        //selon si place de la piscine sont numérotées au pas
        $this->json([
            'success' => true,
            'numerated_seat' => $result['data']['numerated_seat'],
            'limit' => $totalCapacityLimit['limit'] ?? [],
        ]);
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

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->json([
                'success'  => false,
                'error'    => 'Votre session a expiré. Merci de recommencer votre réservation.',
                'redirect' => '/reservation?session_expiree=ra'
            ], 419);
        }

        // Rafraîchir l'activité
        $_SESSION['reservation']['last_activity'] = time();

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
        $withSeat = !array_key_exists('with_seat', $input) || $input['with_seat'];

        //On va chercher le tarif correspondant s'il y en a un
        $result = $this->tarifService->validateSpecialCode($eventId, $code, $withSeat);
        if (!$result['success']) {
            $this->json($result, 200, 'reservation');
        }

        //On construit le DTO pour persister le code dans $_SESSION
        // On récupère les détails actuels pour y ajouter le nouveau (reservation_detail ou reservation_complement selon si place assises dans le tarif).
        if ($result['tarif']['seat_count'] === null) {
            $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_complement'] ?? [];
            $dto = ReservationComplementItemDTO::fromArrayWithSpecialPrice($result['tarif']['id'], [], $code);
            $currentDetails[] = $dto->jsonSerialize();
            $this->reservationSessionService->setReservationSession('reservation_complement', $currentDetails);
        } else {
            $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_detail'] ?? [];
            $dto = ReservationDetailItemDTO::fromArrayWithSpecialPrice($result['tarif']['id'], [], $code);
            $currentDetails[] = $dto->jsonSerialize();
            $this->reservationSessionService->setReservationSession('reservation_detail', $currentDetails);
        }

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

        //On va chercher le tarif correspondant s'il y en a un
        $tarifRepository = new TarifRepository();
        $result = $tarifRepository->findById($tarifId);

        //Selon s'il y a des places assises ou non, on est dans reservation_detail ou reservation_complement
        if ($result->getSeatCount() === null) {
            // Récupérer les détails actuels de la session
            $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_complement'] ?? [];
            // Utiliser le service pour retirer le tarif
            $newDetails = $this->tarifService->removeTarifFromDetails($currentDetails, $tarifId);
            // Mettre à jour la session
            $this->reservationSessionService->setReservationSession('reservation_complement', $newDetails);
        } else {
            // Récupérer les détails actuels de la session
            $currentDetails = $this->reservationSessionService->getReservationSession()['reservation_detail'] ?? [];
            // Utiliser le service pour retirer le tarif
            $newDetails = $this->tarifService->removeTarifFromDetails($currentDetails, $tarifId);
            // Mettre à jour la session
            $this->reservationSessionService->setReservationSession('reservation_detail', $newDetails);
        }

        $this->json(['success' => true], 200, 'reservation');
    }

    //======================================================================
    // ETAPE 5 : Choix des places numérotées
    //======================================================================
    #[Route('/reservation/seat-states/{eventSessionId}', name: 'seat_states', methods: ['GET'])]
    public function seatStates(int $eventSessionId): void
    {
        $seatStates = [21 => "occupied", 41 => "in_cart_other", 61 => "in_cart_session", 81 => "vip", 101 => "benevole"]; // Exemple: la place avec l'ID 41 est occupée
        //On récupère toutes les places réservées et payées


        /* Doit renvoyer quelque chose du style :
        {
          "success": true,
          "seatStates": {
            "123": "occupied",
            "124": "occupied",
            "250": "in_cart_other",
            "251": "in_cart_session",
            "310": "vip",
            "311": "benevole"
          }
        }
        */



        $this->json(
            [
                'success' => true,
                'seatStates' => $seatStates
            ]
        );
    }

    #[Route('/reservation/etape5AddSeat', name: 'etape5_add_seat', methods: ['POST'])]
    public function etape5AddSeat(): void
    {

    }

    #[Route('/reservation/etape5RemoveSeat', name: 'etape5_remove_seat', methods: ['POST'])]
    public function etape5RemoveSeat(): void
    {

    }

}