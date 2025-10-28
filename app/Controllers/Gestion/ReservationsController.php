<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pagination\PaginationService;
use app\Services\Payment\PaymentWebhookService;
use app\Services\Reservation\ReservationDeletionService;
use app\Services\Reservation\ReservationUpdateService;
use Exception;
use Throwable;

class ReservationsController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private ReservationRepository $reservationRepository;
    private PaginationService $paginationService;
    private ReservationUpdateService $reservationUpdateService;
    private ReservationDeletionService $reservationDeletionService;
    private PaymentWebhookService $paymentWebhookService;
    private ReservationPaymentRepository $reservationPaymentRepository;

    function __construct(
        EventQueryService $eventQueryService,
        ReservationRepository $reservationRepository,
        PaginationService $paginationService,
        ReservationUpdateService $reservationUpdateService,
        ReservationDeletionService $reservationDeletionService,
        PaymentWebhookService $paymentWebhookService,
        ReservationPaymentRepository $reservationPaymentRepository,
    )
    {
        parent::__construct(false);
        $this->eventQueryService = $eventQueryService;
        $this->reservationRepository = $reservationRepository;
        $this->paginationService = $paginationService;
        $this->reservationUpdateService = $reservationUpdateService;
        $this->reservationDeletionService = $reservationDeletionService;
        $this->paymentWebhookService = $paymentWebhookService;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
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

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $reservationId = $data['reservationId'] ?? null;
        if (!$reservationId) {
            $this->json(['success' => false, 'message' => 'ID de réservation manquant.']);
        }
        $reservation = $this->reservationRepository->findById((int)$reservationId, false, false, false, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.']);
        }

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

    #[Route('/gestion/reservations/toggle-status', name: 'app_gestion_reservations_toggle_status', methods: ['POST'])]
    public function toggleStatus(): void
    {
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


    #[Route('/gestion/reservations/delete/{id}', name: 'app_gestion_reservations_delete', methods: ['DELETE'])]
    public function delete(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->whatCanDoCurrentUser();
        if (!str_contains($userPermissions, 'D')) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits de suppression.'], 403);
        }

        $reservation = $this->reservationRepository->findById($id);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
            return;
        }
        try {
            $this->reservationDeletionService->deleteReservation($id);
            $this->flashMessageService->setFlashMessage('success', "La réservation a été supprimée avec succès.");

            $this->json(['success' => true]);
        } catch (Exception $e) {
            // Log de l'erreur pour le débogage
            error_log("Erreur lors de la suppression de la réservation ID $id : " . $e->getMessage());
            // Message d'erreur générique pour l'utilisateur
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation.'], 500);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation.'], 500);
        }


    }


    #[Route('/gestion/reservations/refresh-payment', name: 'app_gestion_reservations_refresh_payment', methods: ['POST'])]
    public function requestRefresh(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->whatCanDoCurrentUser();
        if (!str_contains($userPermissions, 'R')) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits nécessaires.'], 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['paymentId'])) { // ==> c'est notre paymentID, pas celui de HelloAsso
            $this->json(['success' => false, 'message' => 'Données invalides.']);
        }

        //On va chercher le paiementID de HelloAsso concerné
        $payment = $this->reservationPaymentRepository->findById($data['paymentId']);
        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Paiement non trouvé.'], 404);
            return;
        }

        $result = $this->paymentWebhookService->handlePaymentState($payment->getPaymentId());
        $this->json($result);
    }


    #[Route('/gestion/reservations/refund', name: 'app_gestion_reservations_refund', methods: ['POST'])]
    public function requestRefund(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->whatCanDoCurrentUser();
        if (!str_contains($userPermissions, 'U')) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits de modification.'], 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['paymentId'])) {
            $this->json(['success' => false, 'message' => 'Données invalides.']);
        }

        //On récupère le checkoutID


        // Logique à implémenter :
        // 2. Récupérer le paymentId depuis le corps de la requête.
        // 3. Appeler un service qui communiquera avec l'API HelloAsso pour initier le remboursement.
        // 4. Mettre à jour le statut du paiement localement.
        // 5. Renvoyer une réponse JSON { success: true/false, message: '...' }.
    }

}