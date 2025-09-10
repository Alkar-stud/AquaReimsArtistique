<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\TarifsRepository;
use app\Repository\Event\EventsRepository;
use app\Services\EventsService;
use app\Services\ReservationService;
use app\Services\ReservationSessionService;
use app\Services\NageuseService;
use app\Utils\CsrfHelper;
use app\Utils\ReservationContextHelper;

class ReservationController extends AbstractController
{
    private ReservationSessionService $reservationSessionService;
    private EventsRepository $eventsRepository;
    private TarifsRepository $tarifsRepository;
    private PiscineGradinsZonesRepository $zonesRepository;
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsPlacesTempRepository $tempRepo;
    private NageuseService $nageuseService;
    private EventsService $eventsService;
    private ReservationService $reservationService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationSessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->zonesRepository = new PiscineGradinsZonesRepository();
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();
        $this->nageuseService = new NageuseService();
        $this->eventsService = new EventsService();
        $this->reservationService = new ReservationService();
    }

    // Page d'accueil du processus de réservation
    #[Route('/reservation', name: 'app_reservation')]
    public function index(): void
    {
        // On commence une nouvelle session de réservation, on nettoie les anciennes données de $_SESSION.
        $this->reservationSessionService->clearReservationSession();
        // On récupère toutes les données nécessaires pour l'affichage des événements
        $eventsData = $this->eventsService->getUpcomingEventsWithStatus();
        //On récupère tous les groupes de nageuses
        $groupes = $this->nageuseService->getAllGroupesNageuses();
        //On récupère toutes les nageuses triées par groupes
        $nageusesParGroupe = $this->nageuseService->getNageusesByGroupe();

        $this->render('reservation/etape1', [
            'events' => $eventsData['events'],
            'periodesOuvertes' => array_map(fn($s) => $s['openPeriod'], $eventsData['statuses']),
            'nextPublicOuvertures' => array_map(fn($s) => $s['nextPublicOpening'], $eventsData['statuses']),
            'groupes' => $groupes,
            'nageusesParGroupe' => $nageusesParGroupe,
            'csrf_token' => CsrfHelper::getToken()
        ], 'Réservations');
    }


    #[Route('/reservation/etape2Display', name: 'etape2Display')]
    public function etape2Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();

        // Valide le contexte de base lié à l'événement choisi (prérequis pour l'étape 2)
        if (!$this->reservationService->validateCoreContext($reservation)['success']) {
        // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape2', array_merge($context, [
            'csrf_token' => $this->getCsrfToken()
        ]), 'Réservations');
    }

    #[Route('/reservation/etape3Display', name: 'etape3Display', methods: ['GET'])]
    public function etape3Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();

        // Revalide le contexte de base lié à l'événement choisi (prérequis pour l'étape 2)
        if (!$this->reservationService->validateCoreContext($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }
        // Valide le contexte du payeur (prérequis pour l'étape 3)
        if (!$this->reservationService->validatePayerContext($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        // Préparer toutes les données pour la vue via le service
        $viewData = $this->reservationService->getReservationViewModel($reservation);
        if ($viewData === null) { // Gère le cas où l'événement n'est pas trouvé
            // Gérer le cas où l'événement n'est pas trouvé ou la session est incohérente
            header('Location: /reservation?erreur=session_invalide');
            exit;
        }

        // Le service prépare toutes les données nécessaires pour la vue.
        $this->render('reservation/etape3', array_merge($viewData, [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation,
        ]), 'Réservations');
    }

    #[Route('/reservation/etape4Display', name: 'etape4Display', methods: ['GET'])]
    public function etape4Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();
        // Valide le contexte des détails (prérequis pour l'étape 4)
        if (!$this->reservationService->validateDetailsContextStep4($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $context = $this->reservationService->getReservationViewModel($reservation);
        $this->render('reservation/etape4', array_merge($context, [
            'numberedSeats' => $context['event']->getPiscine()->getNumberedSeats(),
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation
        ]), 'Réservations');
    }

    #[Route('/reservation/etape5Display', name: 'etape5Display', methods: ['GET'])]
    public function etape5Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();
        // Valide le contexte des détails (prérequis pour l'étape 5, comme pour la 4)
        if (!$this->reservationService->validateDetailsContextStep4($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $sessionId = session_id();
        //Suppression des réservations en cours dont le timeout est expiré
        $this->tempRepo->deleteExpired((new \DateTime())->format('Y-m-d H:i:s'));
        // Récupérer les réservations temporaires de toutes les sessions restantes
        $tempAllSeats = $this->tempRepo->findAll();
        // Remet à null seat_id et seat_name pour chaque participant directement dans $_SESSION
        if (isset($_SESSION['reservation'][$sessionId]['reservation_detail']) && is_array($_SESSION['reservation'][$sessionId]['reservation_detail'])) {
            foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as &$detail) {
                $detail['seat_id'] = null;
                $detail['seat_name'] = null;
            }
            unset($detail);
        }

        // Filtrage des places concernées par la session en cours
        // Récupérer les places déjà réservées de manière définitive pour cette session
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession(
            $reservation['event_id'],
            $reservation['event_session_id']
        );

        // Construire un tableau place_id → session pour le JS de la vue
        $placesSessions = [];
        foreach ($tempAllSeats as $t) {
            $placesSessions[$t->getPlaceId()] = $t->getSession();
            //Si getSession() correspond à la session courante, on met à jour $_SESSION seat_id et seat_name
            if ($t->getSession() === $sessionId) {
                $index = $t->getIndex();
                $placeId = $t->getPlaceId();
                $place = $this->placesRepository->findById($placeId);
                $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_id'] = $placeId;
                $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_name'] = $place ? $place->getFullPlaceName() : $placeId;
            }
        }

        //Envoyer l'événement
        $event = $this->eventsRepository->findById($reservation['event_id']);
        $numberedSeats = $event->getPiscine()->getNumberedSeats();

        //Envoyer les tarifs
        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $nbPlacesAssises = 0;
        foreach ($reservation['reservation_detail'] ?? [] as $detail) {
            foreach ($tarifs as $tarif) {
                if ($tarif->getId() == $detail['tarif_id'] && $tarif->getNbPlace() !== null) {
                    $nbPlacesAssises++;
                }
            }
        }

        //Pour envoyer le nom des places au lieu de seulement leur ID
        $piscineId = $event->getPiscine()->getId();
        $zones = $this->zonesRepository->findOpenZonesByPiscine($piscineId);

        $zonesWithPlaces = [];
        foreach ($zones as $zone) {
            $zonesWithPlaces[] = [
                'zone' => $zone,
                'places' => $this->placesRepository->findByZone($zone->getId())
            ];
        }

        //Pour afficher le contexte récapitulatif
        $context = $this->reservationService->getReservationViewModel($reservation);
        $this->render('reservation/etape5', array_merge($context, [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation,
            'numberedSeats' => $numberedSeats,
            'nbPlacesAssises' => $nbPlacesAssises,
            'zonesWithPlaces' => $zonesWithPlaces,
            'placesReservees' => $placesReservees,
            'placesSessions' => $placesSessions
        ]), 'Réservations');
    }

    #[Route('/reservation/etape5', name: 'etape5', methods: ['POST'])]
    public function etape5(): void
    {
        $this->checkCsrfOrExit('reservation_etape5');

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $event = $this->eventsRepository->findById($reservation['event_id']);
        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $seats = $input['seats'] ?? [];

        $nbPlacesAssises = $this->countPlacesAssises($reservation['reservation_detail'] ?? [], $tarifs);

        if (count($seats) !== $nbPlacesAssises) {
            $this->json(['success' => false, 'error' => 'Nombre de places sélectionnées incorrect.']);
            return;
        }

        // Vérifier que chaque place est bien réservée temporairement pour cette session
        $tempSeats = $this->tempRepo->findAllSeatsBySession($sessionId) ?? [];
        $tempSeatIds = array_map(fn($t) => $t->getPlaceId(), $tempSeats);

        foreach ($seats as $seatId) {
            if (!in_array($seatId, $tempSeatIds)) {
                $this->json(['success' => false, 'error' => "La place $seatId n'est pas réservée pour cette session."]);
                return;
            }
        }

        // Mise à jour des détails de réservation
        foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as $i => &$detail) {
            $seatId = $seats[$i] ?? null;
            $detail['seat_id'] = $seatId;
            if ($seatId) {
                $place = $this->placesRepository->findById($seatId);
                $detail['seat_name'] = $place ? $place->getFullPlaceName() : $seatId;
            } else {
                $detail['seat_name'] = null;
            }
        }
        unset($detail);

        $_SESSION['reservation'][$sessionId]['selected_seats'] = $seats;

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape5AddSeat', name: 'etape5AddSeat', methods: ['POST'])]
    public function etape5AddSeat(): void
    {
        $this->checkCsrfOrExit('reservation_etape5');

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $seatId = (int)($input['seat_id'] ?? 0);
        $index = (int)($input['index'] ?? -1);
        if ($seatId <= 0 || $index < 0) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $nbPlacesAssises = $this->countPlacesAssises($reservation['reservation_detail'] ?? [], $tarifs);
        if ($index >= $nbPlacesAssises) {
            $this->json(['success' => false, 'error' => 'Index de participant invalide.']);
            return;
        }

        // Vérifier que la place existe et est ouverte
        $place = $this->placesRepository->findById($seatId);
        if (!$place || !$place->isOpen()) {
            $this->json(['success' => false, 'error' => "Place invalide ou fermée."]);
            return;
        }

        // Vérifier qu'elle n'est pas déjà prise (temporaire ou définitive)
        $tempSeats = $this->tempRepo->findAll();
        foreach ($tempSeats as $t) {
            if ($t->getPlaceId() == $seatId && $t->getSession() !== $sessionId) {
                $this->json(['success' => false, 'error' => "Place déjà en cours de réservation."]);
                return;
            }
        }

        // Insérer la réservation temporaire
        $now = new \DateTime();
        $timeout = (clone $now)->add(new \DateInterval(TIMEOUT_PLACE_RESERV));
        if (!$this->tempRepo->insertTempReservation($sessionId, $seatId, $index, $now, $timeout)) {
            return;
        }
        //Mise à jour de $_SESSION avec la place du participant à l'index donné
        $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_id'] = $seatId;
        $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_name'] = $place ? $place->getFullPlaceName() : $seatId;


        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);
    }

    #[Route('/reservation/etape5RemoveSeat', name: 'etape5RemoveSeat', methods: ['POST'])]
    public function etape5RemoveSeat(): void
    {
        $this->checkCsrfOrExit('reservation_etape5');

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $seatId = (int)($input['seat_id'] ?? 0);
        if ($seatId <= 0) {
            $this->json(['success' => false, 'error' => 'Paramètre manquant.']);
            return;
        }

        // Supprimer la réservation temporaire pour cette session et cette place
        $this->tempRepo->deleteBySessionAndPlace($sessionId, $seatId);

        //Mise à jour de $_SESSION avec la place du participant à retirer à l'index donné
        if (isset($_SESSION['reservation'][$sessionId]['reservation_detail'])) {
            foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as &$detail) {
                if (($detail['seat_id'] ?? null) == $seatId) {
                    $detail['seat_id'] = null;
                    $detail['seat_name'] = null;
                }
            }
            unset($detail);
        }

        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);
    }

    /**
     * Compte le nombre de places assises attendues.
     */
    private function countPlacesAssises(array $reservationDetails, array $tarifs): int
    {
        $nb = 0;
        // Création d'une carte pour une recherche rapide des tarifs par ID
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        foreach ($reservationDetails as $detail) {
            $tarifId = null;
            if (is_object($detail) && $detail instanceof \app\Models\Reservation\ReservationsDetails) {
                $tarifId = $detail->getTarif();
            } elseif (is_array($detail) && isset($detail['tarif_id'])) {
                $tarifId = $detail['tarif_id'];
            }
            if ($tarifId !== null) {
                $tarif = $tarifsById[$tarifId] ?? null;
                if ($tarif && $tarif->getNbPlace() !== null) {
                    $nb++;
                }
            }
        }
        return $nb;
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

    #[Route('/reservation/etape6Display', name: 'etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();
        //Ne sert plus à cette étape
        unset($_SESSION['reservation'][session_id()]['selected_seats']);

        // Valide le contexte des détails (prérequis pour l'étape 6)
        if (!$this->reservationService->validateDetailsContextStep4($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        //Filtre les item sans places assises
        $context = $this->reservationService->getReservationViewModel($reservation);
        $tarifsSansPlaces = array_filter($context['tarifs'], fn($t) => $t->getNbPlace() === null);

        //Récupération des tarifs sans places assises éventuellement déjà saisis pour pré remplissage
        $reservationComplement = [];
        if (!empty($reservation['reservation_complement'])) {
            foreach ($reservation['reservation_complement'] as $item) {
                $reservationComplement[$item['tarif_id']] = $item['qty'];
            }
        }
        $this->render('reservation/etape6', array_merge($context, [
            'tarifsSansPlaces' => $tarifsSansPlaces,
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation,
            'reservationComplement' => $reservationComplement
        ]), 'Réservations');
    }

    #[Route('/reservation/etape6', name: 'etape6', methods: ['POST'])]
    public function etape6(): void
    {
        $this->checkCsrfOrExit('reservation_etape6');

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $tarifsSansPlaces = array_filter($tarifs, fn($t) => $t->getNbPlace() === null);
        $tarifIdsSansPlaces = array_map(fn($t) => $t->getId(), $tarifsSansPlaces);

        $tarifsInput = $input['tarifs'] ?? [];
        $reservationComplement = [];
        foreach ($tarifsInput as $t) {
            $id = (int)($t['id'] ?? 0);
            $qty = (int)($t['qty'] ?? 0);
            if ($qty > 0 && in_array($id, $tarifIdsSansPlaces, true)) {
                $reservationComplement[] = ['tarif_id' => $id, 'qty' => $qty];
            }
        }

        // Enregistrement dans reservation_complement (même si vide)
        $_SESSION['reservation'][$sessionId]['reservation_complement'] = $reservationComplement;

        $this->json(['success' => true]);
    }

}