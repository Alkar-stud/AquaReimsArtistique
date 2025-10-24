<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pagination\PaginationService;
use app\Services\Reservation\ReservationUpdateService;
use Exception;

class ReservationsController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private ReservationRepository $reservationRepository;
    private PaginationService $paginationService;
    private ReservationUpdateService $reservationUpdateService;

    function __construct(
        EventQueryService $eventQueryService,
        ReservationRepository $reservationRepository,
        PaginationService $paginationService,
        ReservationUpdateService $reservationUpdateService,
    )
    {
        parent::__construct(false);
        $this->eventQueryService = $eventQueryService;
        $this->reservationRepository = $reservationRepository;
        $this->paginationService = $paginationService;
        $this->reservationUpdateService = $reservationUpdateService;
    }

    #[Route('/gestion/reservations', name: 'app_gestion_reservations')]
    public function index(): void
    {
        //On vérifie si le CurrentUser a le droit de lire, de modifier ou rien du tout
        $userPermissions = $this->whatCanDoCurrentUser();
        $isReadOnly = !str_contains($userPermissions, 'U');

        $tab = $_GET['tab'] ?? null;
        $sessionId = (int)($_GET['s'] ?? 0);
        $isCancel = isset($_GET['cancel']) && $_GET['cancel'];
        $isChecked = isset($_GET['check']) ? (bool)$_GET['check'] : null;
        $paginationConfig = $this->paginationService->createFromRequest($_GET);

        if ($tab == 'extract') {
            $events = null;
        } elseif ($tab == 'past') {
            //On envoie les galas passés
            $events = $this->eventQueryService->getAllEventsWithRelations(false);
        } else {
            //On envoie les galas à venir
            $events = $this->eventQueryService->getAllEventsWithRelations(true);
        }

        $paginator = null;
        if ($sessionId > 0) {
            $paginator = $this->reservationRepository->findBySessionPaginated(
                $sessionId,
                $paginationConfig->getCurrentPage(),
                $paginationConfig->getItemsPerPage(),
                $isCancel,
                $isChecked
            );
        }

        $this->render('/gestion/reservations', [
            'events' => $events,
            'selectedSessionId' => $sessionId,
            'tab' => $tab,
            'reservations' => $paginator ? $paginator->getItems() : [],
            'currentPage' => $paginator ? $paginator->getCurrentPage() : 1,
            'totalPages' => $paginator ? $paginator->getTotalPages() : 0,
            'itemsPerPage' => $paginationConfig->getItemsPerPage(),
            'userPermissions' => $userPermissions,
            'isReadOnly' => $isReadOnly,
            'isCancel' => $isCancel,                    //Pour les boutons de filtre
            'isChecked' => $isChecked,                  //Pour les boutons de filtre
        ], "Gestion des réservations");
    }

    #[Route('/gestion/reservations/details/{id}', name: 'app_gestion_reservation_details', methods: ['GET'])]
    public function getReservationDetails(int $id): void
    {
        // On vérifie que l'utilisateur a au moins le droit de lecture
        $userPermissions = $this->whatCanDoCurrentUser();
        if (!str_contains($userPermissions, 'R')) {
            $this->json(['error' => 'Accès non autorisé'], 403);
            return;
        }

        $reservation = $this->reservationRepository->findById($id, true, true, false, true);

        if (!$reservation) {
            $this->json(['error' => 'Réservation non trouvée'], 404);
            return;
        }

        $this->json($reservation->toArray());
    }

    #[Route('/gestion/reservations/update', name: 'app_gestion_reservations_update', methods: ['POST'])]
    public function update(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->whatCanDoCurrentUser();
        if (!str_contains($userPermissions, 'U')) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits de modification.'], 403);
            return;
        }

        // Le contrôleur est responsable de lire la requête
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Il valide l'autorisation (via l'ID de réservation)
        $reservationId = $data['reservationId'] ?? null;
        if (!$reservationId) {
            $this->json(['success' => false, 'message' => 'ID de réservation manquant.']);
        }
        $reservation = $this->reservationRepository->findById((int)$reservationId, false, false, false, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.']);
        }

        // 4. Il délègue l'action métier au service avec des paramètres clairs
        $return = $this->reservationUpdateService->handleUpdateReservationFields(
            $reservation,
            $data['typeField'] ?? '',
            $data['id'] ?? null,
            $data['tarifId'] ?? null,
            $data['field'] ?? null,
            $data['value'] ?? null,
            $data['action'] ?? null
        );

        $this->json($return);

    }

    #[Route('/gestion/reservation/toggle-status', name: 'app_gestion_reservation_toggle_status', methods: ['POST'])]
    public function toggleStatus(): void
    {
        // On s'attend à recevoir du JSON
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'], $data['status'])) {
            $this->json(['success' => false, 'message' => 'Données invalides.']);
            return;
        }

        try {
            $this->reservationRepository->updateSingleField((int)$data['id'], 'is_checked', (bool)$data['status']);
            $this->flashMessageService->setFlashMessage('success', "Le statut a été mis à jour avec succès.");
            // On génère et renvoie un nouveau token pour maintenir la session sécurisée
            $newCsrfToken = $this->csrfService->getToken($this->getCsrfContext());

            parent::json(['success' => true, 'csrfToken' => $newCsrfToken]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

}