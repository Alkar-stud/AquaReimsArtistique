<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\FlashMessageService;
use app\Services\Reservation\ReservationUpdateService;
use app\Utils\CsrfHelper;

class ReservationsController extends AbstractController
{
    private FlashMessageService $flashMessageService;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;
    private ReservationUpdateService $reservationUpdateService;

    function __construct()
    {
        parent::__construct(false);
        $this->flashMessageService = new FlashMessageService();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
        $this->reservationUpdateService = new ReservationUpdateService();

    }

    #[Route('/gestion/reservations', name: 'app_gestion_reservations')]
    public function index(?string $search = null): void
    {
        //Envoi juste la structure avec les onglets
        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/reservations', [
            'flash_message' => $flashMessage
        ], "Gestion des réservations");
    }

    #[Route('/gestion/reservations/upcoming', name: 'app_gestion_reservations_upcoming')]
    public function upcomingReservations(): void
    {
        // Récupérer tous les événements à venir pour la liste déroulante
        $events = $this->eventsRepository->findUpcoming();
        $reservations = [];
        $selectedSessionId = null;

        // Créer une liste "plate" de sessions pour le select
        $sessionsForSelect = [];
        foreach ($events as $event) {
            foreach ($event->getSessions() as $session) {
                $sessionsForSelect[] = (object)[
                    'id' => $session->getId(),
                    'label' => $event->getLibelle() . ' - ' . $session->getSessionName()
                ];
            }
        }

        // Déterminer la session et la page depuis l'URL
        $sessionId = isset($_GET['session']) ? (int)$_GET['session'] : null;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $itemsPerPage = 5; // Nombre de réservations par page

        // Lire le nombre d'éléments par page depuis l'URL, avec une valeur par défaut de 15
        $validItemsPerPage = [5, 15, 25, 50, 100];
        $itemsPerPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $validItemsPerPage) ? (int)$_GET['per_page'] : 15;


        // Si aucune session n'est dans l'URL, mais qu'il n'y a qu'une seule session possible, on la sélectionne.
        if ($sessionId === null && count($sessionsForSelect) === 1) {
            $sessionId = $sessionsForSelect[0]->id;
        }

        // Si une session est sélectionnée, récupérer ses réservations
        $pagination = null;
        if ($sessionId) {
            $selectedSessionId = $sessionId;

            $totalItems = $this->reservationsRepository->countActiveBySession($selectedSessionId);
            $totalPages = ceil($totalItems / $itemsPerPage);
            $offset = ($currentPage - 1) * $itemsPerPage;

            $reservations = $this->reservationsRepository->findActiveBySession($selectedSessionId, $itemsPerPage, $offset);


            $pagination = [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems
            ];
        }

        $this->render('/gestion/reservations/_upcoming_list', [
            'sessionsForSelect' => $sessionsForSelect,
            'reservations' => $reservations,
            'selectedSessionId' => $selectedSessionId,
            'pagination' => $pagination,
            'itemsPerPage' => $itemsPerPage
        ], '', true); // Le 'true' final indique un rendu partiel
    }

    #[Route('/gestion/reservations/past', name: 'app_gestion_reservations_past')]
    public function pastReservations(): void
    {
        // 1. Récupérer les réservations passées
        // $pastReservations = $this->reservationRepository->findPast();

        // 2. Rendre la vue partielle correspondante
        $this->render('/gestion/reservations/_past_list', [
            // 'reservations' => $pastReservations
        ], '', true);
    }


    #[Route('/gestion/reservations/details/{id}', name: 'app_gestion_reservations_details')]
    public function reservationDetails(int $id): void
    {
        // Le contexte (upcoming/past) nous dira si la vue est en lecture seule
        $context = $_GET['context'] ?? 'past'; // 'past' (lecture seule) par défaut pour la sécurité
        $isReadOnly = ($context !== 'upcoming');

        // Récupérer la réservation et ses détails
        $reservation = $this->reservationsRepository->findById($id);
        if (!$reservation) {
            echo '<div class="alert alert-danger">Réservation non trouvée.</div>';
            return;
        }

        $details = (new ReservationsDetailsRepository())->findByReservation($id);

        // Rendre un template partiel pour le contenu de la modale
        $this->render('/gestion/reservations/_modal_content', [
            'reservation' => $reservation,
            'details' => $details,
            'isReadOnly' => $isReadOnly,
            'csrf_token' => CsrfHelper::getToken('gestion_update_reservation'),
        ], '', true);
    }

    #[Route('/gestion/reservations/update', name: 'app_gestion_reservations_update', methods: ['POST'])]
    public function updateReservation(): void
    {
        $this->checkCsrfOrExit('gestion_update_reservation');

        $data = json_decode(file_get_contents('php://input'), true);
        $typeField = $data['typeField'] ?? null;
        $reservationId = $data['reservationId'] ?? null;
        $detailId = $data['detailId'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        if (!$typeField || !$reservationId) {
            $this->json(['success' => false, 'message' => 'Données de requête invalides.']);
            return;
        }

        $return = ['success' => false, 'message' => 'Type de champ non reconnu.'];

        if ($typeField === 'contact') {
            $return = $this->reservationUpdateService->updateContactField((int)$reservationId, $field, $value);
        } elseif ($typeField === 'detail') {
            if (!$detailId) {
                $this->json(['success' => false, 'message' => 'ID de participant manquant.']);
                return;
            }
            $return = $this->reservationUpdateService->updateDetailField((int)$detailId, $field, $value);
        }

        // Pour l'instant, on ne recharge la page qu'en cas de succès pour refléter les changements.
        $return['reload'] = $return['success'];

        $this->json($return);
    }
}