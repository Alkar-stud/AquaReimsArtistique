<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;
use DateMalformedStringException;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    private ReservationSessionService $sessionService;
    private EventsRepository $eventsRepository;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->sessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
    }

    // Page d'accueil du processus de réservation
    public function index(): void
    {
        // Réinitialiser toute session de réservation existante
        $this->sessionService->clearSession();

        // Charger tous les événements à venir
        $events = $this->eventsRepository->findUpcoming();

echo '<pre>';
print_r($events);
echo '</pre>';
        // Afficher la première étape
        $this->render('reservation/home', [
            'events' => $events,
            'csrf_token' => $this->getCsrfToken()
        ], 'Réservations');
    }

    // Traitement du formulaire de l'étape 1

    /**
     * @throws DateMalformedStringException
     */
    public function processStep1()
    {
        // Créer une nouvelle session de réservation
        $session = $this->sessionService->createSession();

        // Validation CSRF
        if (!$this->validateCsrf($_POST['csrf_token'])) {
            $this->render('error', ['message' => 'Token CSRF invalide'], 'Erreur');
            return;
        }

        // Récupération et validation des données
        $eventId = (int) $_POST['event_id'];
        $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
        $nageuseId = isset($_POST['nageuse_id']) ? (int)$_POST['nageuse_id'] : null;

        // Vérification de l'événement
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            $this->render('error', ['message' => 'Événement introuvable'], 'Erreur');
            return;
        }

        // Vérification de la limitation par nageuse si nécessaire
        if ($event->getLimitationPerSwimmer() && (!$groupId || !$nageuseId)) {
            $this->render('error', ['message' => 'Vous devez sélectionner une nageuse'], 'Erreur');
            return;
        }

        // Enregistrer les données dans la session
        $session->setData('event_id', $eventId);
        $session->setData('group_id', $groupId);
        $session->setData('nageuse_id', $nageuseId);
        $session->setData('event', $event);

        // Passer à l'étape suivante
        $session->setStep(2);
        $this->sessionService->updateSession($session);

        // Rediriger vers l'étape 2
        header('Location: /reservation/contact');
        exit;
    }

    // Points d'API pour la partie dynamique
    public function getGroupsApi()
    {
        header('Content-Type: application/json');
        $eventId = (int) $_GET['event_id'];

        $groupesRepository = new GroupesNageusesRepository();
        $groups = $groupesRepository->findAll();

        echo json_encode($groups);
    }

    public function getNageusesApi()
    {
        header('Content-Type: application/json');
        $groupId = (int) $_GET['group_id'];

        $nageusesRepository = new NageusesRepository();
        $nageuses = $nageusesRepository->findByGroupId($groupId);

        echo json_encode($nageuses);
    }
}