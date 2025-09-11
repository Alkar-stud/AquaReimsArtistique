<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\ReservationAccessCodeDTO;
use app\DTO\ReservationDetailItemDTO;
use app\Repository\Event\EventsRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\TarifsRepository;
use app\Services\EventsService;
use app\Services\NageuseService;
use app\Services\ReservationService;
use app\Services\ReservationSessionService;
use app\Services\TarifService;
use app\Utils\ReservationContextHelper;
use DateInterval;
use DateTime;
use Exception;

class ReservationAjaxController extends AbstractController
{
    private NageuseService $nageuseService;
    private ReservationSessionService $reservationSessionService;
    private TarifsRepository $tarifsRepository;
    private ReservationService $reservationService;
    private EventsService $eventsService;
    private TarifService $tarifService;
    private EventsRepository $eventsRepository;
    private PiscineGradinsZonesRepository $zonesRepository;
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationsPlacesTempRepository $tempRepo;
    private ReservationsDetailsRepository $reservationsDetailsRepository;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationSessionService = new ReservationSessionService();
        $this->nageuseService = new NageuseService();
        $this->tarifsRepository = new TarifsRepository();
        $this->reservationService = new ReservationService();
        $this->eventsService = new EventsService();
        $this->tarifService = new TarifService();
        $this->eventsRepository = new EventsRepository;
        $this->zonesRepository = new PiscineGradinsZonesRepository();
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
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
        $validationResult = $this->reservationService->validateDataPerStep(1, $input);

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
    public function checkDuplicateEmailInReservation(): void
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

        //On vérifie ce qui a été saisi et ce qu'il y a dans $_SESSION
        $validationResult = $this->reservationService->validateDataPerStep(2, $input);

        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        // Enregistrement en session
        // $validationResult['data'] contient une instance de ReservationUserDTO
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
        $this->checkCsrfOrExit('etape3', false);
        $input = json_decode(file_get_contents('php://input'), true);

        //On vérifie ce qui a été saisi et ce qu'il y a dans $_SESSION
        $validationResult = $this->reservationService->validateDataPerStep(3, $input);

        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        // Si c'est un succès, on met à jour la session avec les nouvelles données
        $this->reservationSessionService->setReservationSession('reservation_detail', $validationResult['data']);
        $this->json(['success' => true]);
    }



    //======================================================================
    // ETAPE 4 : Nom des participants
    //======================================================================

    #[Route('/reservation/etape4', name: 'etape4',methods: ['POST'])]
    public function etape4(): void
    {
        // La validation CSRF se fait sur $_POST car c'est une requête multipart/form-data
        $this->checkCsrfOrExit('reservation_etape4', false);

        //On vérifie ce qui a été saisi et ce qu'il y a dans $_SESSION
        $validationResult = $this->reservationService->validateDataPerStep(4, [$_POST, $_FILES]);

        if (!$validationResult['success']) {
            $this->json($validationResult);
            return;
        }

        // Si la validation réussit, on met à jour la session avec les DTOs propres
        $this->reservationSessionService->setReservationSession('reservation_detail', $validationResult['data']);

        // Récupérer l'événement pour la réponse, car on a besoin de renvoyer si la piscine a des places numérotées pour savoir si on saute l'étape suivante
        $reservation = $this->reservationSessionService->getReservationSession();
        $event = $this->eventsRepository->findById($reservation['event_id']);

        $this->json(['success' => true, 'numberedSeats' => $event->getPiscine()->getNumberedSeats()]);
    }


    //======================================================================
    // ETAPE 5 : Choix des places numérotées
    //======================================================================

    #[Route('/reservation/zone-plan/{zoneId}', name: 'reservation_zone_plan', methods: ['GET'])]
    public function getZonePlan(int $zoneId): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();
        // Valide le contexte des détails (prérequis pour l'étape 5)
        if (!$this->reservationService->validateDetailsContextStep4($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            http_response_code(403);
            echo "Session expirée ou invalide.";
            exit;
        }

        // Pour trouver les zones précédente et suivante, nous devons connaître la piscine
        $event = $this->eventsRepository->findById($reservation['event_id']);
        if (!$event) {
            http_response_code(404);
            echo "Événement non trouvé.";
            exit;
        }
        $piscineId = $event->getPiscine()->getId();

        // Récupérer toutes les zones ouvertes et triées pour cette piscine
        $openZones = $this->zonesRepository->findOpenZonesByPiscine($piscineId);
        $openZoneIds = array_map(fn($z) => $z->getId(), $openZones);

        // Trouver l'index de la zone actuelle pour déterminer la précédente et la suivante
        $currentIndex = array_search($zoneId, $openZoneIds);
        $prevZoneId = ($currentIndex !== false && $currentIndex > 0) ? $openZoneIds[$currentIndex - 1] : null;
        $nextZoneId = ($currentIndex !== false && $currentIndex < count($openZoneIds) - 1) ? $openZoneIds[$currentIndex + 1] : null;

        $zone = $this->zonesRepository->findById($zoneId);
        if (!$zone) {
            http_response_code(404);
            echo "Zone non trouvée.";
            exit;
        }

        //Suppression des réservations en cours dont le timeout est expiré
        $this->tempRepo->deleteExpired((new DateTime())->format('Y-m-d H:i:s'));
        // Récupérer les réservations temporaires de toutes les sessions restantes
        $tempAllSeats = $this->tempRepo->findByEventSession($reservation['event_session_id']);

        // Récupérer les places déjà réservées de manière définitive pour cette session
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession(
            $reservation['event_session_id']
        );

        // Construire un tableau place_id → session pour le JS de la vue
        $placesSessions = [];
        foreach ($tempAllSeats as $t) {
            $placesSessions[$t->getPlaceId()] = $t->getSession();
        }

        $this->render('reservation/_zone_plan', [
            'zone' => $zone,
            'places' => $this->placesRepository->findByZone($zone->getId()),
            'placesReservees' => $placesReservees,
            'placesSessions' => $placesSessions,
            'prevZoneId' => $prevZoneId,
            'nextZoneId' => $nextZoneId
        ], '', true);
    }

    #[Route('/reservation/etape5AddSeat', name: 'etape5_add_seat', methods: ['POST'])]
    public function etape5AddSeat(): void
    {
        $this->checkCsrfOrExit('reservation_etape5_add_seat', false);
        $input = json_decode(file_get_contents('php://input'), true);

        //on récupère les différents éléments
        $reservation = $this->reservationSessionService->getReservationSession() ?? [];
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        //On récupère les données
        $seatId = (int)($input['seat_id'] ?? 0);
        $index = (int)($input['index'] ?? -1);
        if ($seatId <= 0 || $index < 0) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        //On vérifie que l'index envoyé n'est pas supérieur au nombre de places attendues
        $nbPlacesAssisesAttendues = count($reservation['reservation_detail']);
        if ($index > $nbPlacesAssisesAttendues) {
            $this->json(['success' => false, 'error' => 'Index de participant invalide.']);
            return;
        }

        //On vérifie le statut de la place demandée
        $checkSeatStatus = $this->reservationService->seatStatus($seatId, $reservation['event_session_id']);

        // Si la place est libre, on peut continuer le processus d'ajout.
        // Sinon, on retourne directement le statut d'échec.
        if (!$checkSeatStatus['success']) {
            $this->json($checkSeatStatus);
            return;
        }

        //on ajoute à la session et en BDD
        // Insérer la réservation temporaire
        $now = new DateTime();
        $sessionId = session_id();
        $timeout = (clone $now)->add(new DateInterval(TIMEOUT_PLACE_RESERV));
        $this->tempRepo->insertTempReservation($sessionId, $reservation['event_session_id'], $seatId, $index, $now, $timeout);
        //Mise à jour de $_SESSION avec la place du participant à l'index donné
        $place = $checkSeatStatus['place'] ?? null;

        // On récupère les détails actuels, on les modifie, puis on les réenregistre via le service.
        $reservationDetails = $reservation['reservation_detail'];
        $reservationDetails[$index]['seat_id'] = $seatId;
        $reservationDetails[$index]['seat_name'] = $place ? $place->getFullPlaceName() : $seatId;
        $this->reservationSessionService->setReservationSession('reservation_detail', $reservationDetails);

        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);
    }

    #[Route('/reservation/etape5RemoveSeat', name: 'etape5_remove_seat', methods: ['POST'])]
    public function etape5RemoveSeat(): void
    {
        $this->checkCsrfOrExit('etape5_remove_seat', false);
        $input = json_decode(file_get_contents('php://input'), true);

        //on récupère les différents éléments
        $reservation = $this->reservationSessionService->getReservationSession() ?? [];
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        //On récupère les données
        $seatId = (int)($input['seat_id'] ?? 0);
        if ($seatId <= 0) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        $sessionId = session_id();
        // Supprimer la réservation temporaire pour cette session et cette place
        $this->tempRepo->deleteBySessionAndPlace($sessionId, $seatId, $reservation['event_session_id']);

        //Mise à jour de $_SESSION avec la place du participant à retirer à l'index donné
        $reservationDetails = $reservation['reservation_detail'] ?? [];
        foreach ($reservationDetails as &$detail) {
            if (($detail['seat_id'] ?? null) == $seatId) {
                $detail['seat_id'] = null;
                $detail['seat_name'] = null;
                break; // La place a été trouvée, on peut sortir de la boucle.
            }
        }
        unset($detail);
        $this->reservationSessionService->setReservationSession('reservation_detail', $reservationDetails);

        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);

    }

    #[Route('/reservation/etape5', name: 'etape5',methods: ['POST'])]
    public function etape5(): void
    {
        $this->checkCsrfOrExit('reservation_etape5', false);
        $input = json_decode(file_get_contents('php://input'), true);
        
        //On vérifie ce qui a été saisi et ce qu'il y a dans $_SESSION
        $validationResult = $this->reservationService->validateDataPerStep(5, $input);

        if (!$validationResult['success']) {
            $this->json($validationResult);
        }

        $this->json($validationResult);
    }


    /**
     * pour rafraichir le contexte avec fetch
     */
    #[Route('/reservation/display-details-fragment', name: 'display_details_fragment', methods: ['GET'])]
    public function displayDetailsFragment(): void
    {
        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/_display_details', $context, '', true);
    }
}
