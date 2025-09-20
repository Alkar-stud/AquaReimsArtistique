<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventsRepository;
use app\Repository\TarifRepository;
use app\Services\EventsService;
use app\Services\NageuseService;
use app\Services\Reservation\ReservationService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Reservation\ReservationViewModelService;
use app\Utils\CsrfHelper;
use app\Utils\ReservationContextHelper;
use DateMalformedStringException;
use Exception;
use Random\RandomException;

class ReservationController extends AbstractController
{
    private ReservationSessionService $reservationSessionService;
    private EventsRepository $eventsRepository;
    private TarifRepository $tarifsRepository;
    private NageuseService $nageuseService;
    private EventsService $eventsService;
    private ReservationService $reservationService;
    private ReservationViewModelService $reservationViewModelService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationSessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->tarifsRepository = new TarifRepository();
        $this->nageuseService = new NageuseService();
        $this->eventsService = new EventsService();
        $this->reservationService = new ReservationService();
        $this->reservationViewModelService = new ReservationViewModelService();
    }

    // Page d'accueil du processus de réservation

    /**
     * @throws RandomException
     * @throws Exception
     */
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


    /**
     * @throws DateMalformedStringException
     */
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

    /**
     * @throws DateMalformedStringException
     */
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
        $viewData = $this->reservationViewModelService->getReservationViewModel($reservation);
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

    /**
     * @throws DateMalformedStringException
     */
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

        $context = $this->reservationViewModelService->getReservationViewModel($reservation);
        $this->render('reservation/etape4', array_merge($context, [
            'numberedSeats' => $context['event']->getPiscine()->getNumberedSeats(),
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation
        ]), 'Réservations');
    }

    /**
     * @throws DateMalformedStringException
     */
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
        $viewData = $this->reservationViewModelService->getStep5ViewModel($reservation);
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

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/etape6Display', name: 'etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        $reservation = $this->reservationSessionService->getReservationSession();
        //Ne sert plus à cette étape
        unset($_SESSION['reservation'][session_id()]['selected_seats']);

        // Valide le contexte des détails (prérequis pour les étapes 5 et 6)
        if (!$this->reservationService->validateDetailsContextStep4($reservation)['success']) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        //Filtre les item sans places assises
        $context = $this->reservationViewModelService->getReservationViewModel($reservation);
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

}
