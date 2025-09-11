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
use DateInterval;
use DateTime;

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
            // Gérer le cas où l'événement n'est pas trouvé, ou la session est incohérente
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

        // Le service prépare toutes les données nécessaires pour la vue.
        $viewData = $this->reservationService->getStep5ViewModel($reservation);
        if ($viewData === null) {
            // Gérer le cas où l'événement n'est pas trouvé, ou la session est incohérente
            header('Location: /reservation?erreur=session_invalide');
            exit;
        }

        // Le service a déjà tout préparé, il ne reste qu'à rendre la vue.
        $this->render('reservation/etape5', array_merge($viewData, [
            'csrf_token' => $this->getCsrfToken()
        ]), 'Réservations');
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
