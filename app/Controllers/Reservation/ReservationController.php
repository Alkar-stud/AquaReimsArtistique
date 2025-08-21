<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    private ReservationSessionService $sessionService;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->sessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository(); // Ajout
    }

    // Page d'accueil du processus de réservation
    public function index(): void
    {
        $this->sessionService->clearSession();
        $events = $this->eventsRepository->findUpcoming();

        // Récupérer tous les groupes
        $groupesRepository = new GroupesNageusesRepository();
        $groupes = $groupesRepository->findAll();

        // Récupérer les nageuses par groupe
        $nageusesRepository = new NageusesRepository();
        $nageuses = $nageusesRepository->findAll();
        $nageusesParGroupe = [];
        foreach ($nageuses as $nageuse) {
            $groupeId = $nageuse->getGroupe();
            if (!isset($nageusesParGroupe[$groupeId])) {
                $nageusesParGroupe[$groupeId] = [];
            }
            $nageusesParGroupe[$groupeId][] = [
                'id' => $nageuse->getId(),
                'nom' => $nageuse->getName()
            ];
        }

        $reservation = $_SESSION['reservation'][session_id()] ?? [];
        $selectedSession = $reservation['event_session_id'] ?? null;
        $selectedNageuse = $reservation['nageuse_id'] ?? null;
        $selectedGroupe = null;

        if ($selectedNageuse) {
            foreach ($nageuses as $nageuse) {
                if ($nageuse->getId() == $selectedNageuse) {
                    $selectedGroupe = $nageuse->getGroupe();
                    break;
                }
            }
        }

        $this->render('reservation/home', [
            'events' => $events,
            'groupes' => $groupes,
            'nageusesParGroupe' => $nageusesParGroupe,
            'csrf_token' => $this->getCsrfToken(),
            'selectedSession' => $selectedSession,
            'selectedNageuse' => $selectedNageuse,
            'selectedGroupe' => $selectedGroupe
        ], 'Réservations');
    }

    #[Route('/reservation/check-nageuse-limit', name: 'check_nageuse_limit', methods: ['GET'])]
    public function checkNageuseLimit(): void
    {
        $csrfToken = $_GET['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'check-nageuse-limit', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide : ' . $csrfToken]);
            return;
        }

        $eventId = (int)($_GET['event_id'] ?? 0);
        $nageuseId = (int)($_GET['nageuse_id'] ?? 0);

        if (!$eventId || !$nageuseId) {
            $this->json(['success' => true, 'limiteAtteinte' => true, 'error' => 'Paramètres manquants']);
            return;
        }

        $event = $this->eventsRepository->findById($eventId);
        //S'il n'y a pas de limite pour cet event.
        if (!$event || $event->getLimitationPerSwimmer() === null) {
            $this->json(['success' => true, 'limiteAtteinte' => false]);
            return;
        }

        $limite = $event->getLimitationPerSwimmer();

        $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

        $this->json(['success' => true, 'limiteAtteinte' => $count >= $limite]);
    }

    /*
     * Pour valider et enregistrer en $_SESSION les valeurs de l'étape 1
     *
     */
    #[Route('/reservation/etape1', methods: ['POST'])]
    public function etape1(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape1')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide : ' . $csrfToken]);
            return;
        }

        $eventId = (int)($input['event_id'] ?? 0);
        $sessionId = (int)($input['event_session_id'] ?? 0);
        $nageuseId = isset($input['nageuse_id']) ? (int)$input['nageuse_id'] : null;

        // Contrôles
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            $this->json(['success' => false, 'error' => 'Événement invalide.']);
            return;
        }

        $sessions = $event->getSessions();
        $sessionIds = array_map(fn($s) => $s->getId(), $sessions);
        if (!in_array($sessionId, $sessionIds, true)) {
            $this->json(['success' => false, 'error' => 'Séance invalide.']);
            return;
        }

        if ($event->getLimitationPerSwimmer() !== null) {
            $nageusesRepository = new \app\Repository\Nageuse\NageusesRepository();
            $nageuse = $nageusesRepository->findById($nageuseId);
            if (!$nageuse) {
                $this->json(['success' => false, 'error' => 'Nageuse invalide.']);
                return;
            }
            // Vérifier la limite de spectateurs
            $limite = $event->getLimitationPerSwimmer();
            $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

            if ($count >= $limite) {
                $this->json(['success' => false, 'error' => 'Le quota de spectateurs pour cette nageuse est atteint.']);
                return;
            }
        }

        $_SESSION['reservation'][session_id()]['event_id'] = $eventId;
        $_SESSION['reservation'][session_id()]['event_session_id'] = $sessionId;
        $_SESSION['reservation'][session_id()]['nageuse_id'] = $nageuseId;

        $this->json(['success' => true]);
    }

    #[Route('/reservation/check-email', methods: ['POST'])]
    public function checkEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'check_email', false)) {
            $this->json(['exists' => false, 'error' => 'Token CSRF invalide']);
            return;
        }
        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        // Recherche des réservations existantes
        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if ($reservations) {
            $result = [];
            foreach ($reservations as $r) {
                $result[] = [
                    'nb_places' => $r->getNbPlaces(),
                    'session_date' => $r->getSession()->getEventStartAt()->format('d/m/Y H:i')
                ];
            }
            $this->json(['exists' => true, 'reservations' => $result]);
        } else {
            $this->json(['exists' => false]);
        }
    }

    #[Route('/reservation/etape2Display', methods: ['GET'])]
    public function etape2Display(): void
    {
        $this->render('reservation/etape2', [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ], 'Réservations');
    }

    #[Route('/reservation/etape2', methods: ['POST'])]
    public function etape2Post(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape2')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }

        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email = trim($input['email'] ?? '');
        $telephone = trim($input['telephone'] ?? '');

        // Validation simple
        if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
            $this->json(['success' => false, 'error' => 'Tous les champs sont obligatoires.']);
            return;
        }
        if (strtolower($nom) === strtolower($prenom)) {
            $this->json(['success' => false, 'error' => 'Le nom et le prénom ne doivent pas être identiques.']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Adresse mail invalide.']);
            return;
        }
        if (!preg_match('/^0[1-9](\d{8})$/', str_replace(' ', '', $telephone))) {
            $this->json(['success' => false, 'error' => 'Numéro de téléphone invalide.']);
            return;
        }

        // Enregistrement en session
        $_SESSION['reservation'][session_id()]['user'] = [
            'nom' => strtoupper($nom),
            'prenom' => ucwords($prenom),
            'email' => $email,
            'telephone' => $telephone
        ];

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape3Display', methods: ['GET'])]
    public function etape3Display(): void
    {
        $this->render('reservation/etape3', [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ], 'Réservations');
    }

}