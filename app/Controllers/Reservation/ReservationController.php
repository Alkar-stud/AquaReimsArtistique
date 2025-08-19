<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\ReservationsRepository;
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
        $nageusesParGroupe = [];
        foreach ($groupes as $groupe) {
            $nageuses = $nageusesRepository->findByGroupId($groupe->getId());
            $nageusesParGroupe[$groupe->getId()] = [];
            foreach ($nageuses as $nageuse) {
                $nageusesParGroupe[$groupe->getId()][] = [
                    'id' => $nageuse->getId(),
                    'nom' => $nageuse->getName()
                ];
            }
        }

        $this->render('reservation/home', [
            'events' => $events,
            'groupes' => $groupes,
            'nageusesParGroupe' => $nageusesParGroupe,
            'csrf_token' => $this->getCsrfToken()
        ], 'Réservations');
    }

    #[Route('/reservation/check-nageuse-limit', name: 'check_nageuse_limit', methods: ['GET'])]
    public function checkNageuseLimit(): void
    {
        $eventId = (int)($_GET['event_id'] ?? 0);
        $nageuseId = (int)($_GET['nageuse_id'] ?? 0);

        if (!$eventId || !$nageuseId) {
            $this->json(['limiteAtteinte' => true, 'error' => 'Paramètres manquants']);
            return;
        }

        $event = $this->eventsRepository->findById($eventId);
        if (!$event || $event->getLimitationPerSwimmer() === null) {
            $this->json(['limiteAtteinte' => false]);
            return;
        }

        $limite = $event->getLimitationPerSwimmer();

        $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

        $this->json(['limiteAtteinte' => $count >= $limite]);
    }

    #[Route('/reservation/etape1', methods: ['POST'])]
    public function etape1(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = (int)($input['event_id'] ?? 0);
        $sessionId = (int)($input['session_id'] ?? 0);
        $nageuseId = isset($input['nageuse_id']) ? (int)$input['nageuse_id'] : null;

        // Contrôles...

        $_SESSION['reservation'][session_id()]['event_id'] = $eventId;
        $_SESSION['reservation'][session_id()]['session_id'] = $sessionId;
        $_SESSION['reservation'][session_id()]['nageuse_id'] = $nageuseId;

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape2', methods: ['GET'])]
    public function etape2(): void
    {
        $this->render('reservation/etape2', [

        ], 'Réservations');
    }


}