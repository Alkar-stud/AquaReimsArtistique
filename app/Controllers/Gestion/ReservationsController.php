<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Enums\LogType;
use app\Models\Reservation\ReservationPayment;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Services\Event\EventPiscineService;
use app\Services\Event\EventQueryService;
use app\Services\Log\Logger;
use app\Services\Mails\MailService;
use app\Services\Pagination\PaginationService;
use app\Services\Payment\HelloAssoService;
use app\Services\Payment\PaymentWebhookService;
use app\Services\Pdf\PdfGenerationService;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationDeletionService;
use app\Services\Reservation\ReservationTokenService;
use app\Services\Reservation\ReservationUpdateService;
use app\Utils\DataHelper;
use app\Utils\DurationHelper;
use Exception;
use InvalidArgumentException;
use Throwable;

class ReservationsController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private ReservationRepository $reservationRepository;
    private ReservationTempRepository $reservationTempRepository;
    private PaginationService $paginationService;
    private ReservationUpdateService $reservationUpdateService;
    private ReservationDeletionService $reservationDeletionService;
    private PaymentWebhookService $paymentWebhookService;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private HelloAssoService $helloAssoService;
    private ReservationTokenService $reservationTokenService;
    private ReservationQueryService $reservationQueryService;
    private MailService $mailService;
    private DataHelper $dataHelper;
    private EventPiscineService $EventPiscineService;

    function __construct(
        EventQueryService $eventQueryService,
        ReservationRepository $reservationRepository,
        ReservationTempRepository $reservationTempRepository,
        PaginationService $paginationService,
        ReservationUpdateService $reservationUpdateService,
        ReservationDeletionService $reservationDeletionService,
        PaymentWebhookService $paymentWebhookService,
        ReservationPaymentRepository $reservationPaymentRepository,
        HelloAssoService $helloAssoService,
        ReservationTokenService $reservationTokenService,
        ReservationQueryService $reservationQueryService,
        MailService $mailService,
        DataHelper $dataHelper,
        EventPiscineService $EventPiscineService,
    )
    {
        parent::__construct(false);
        $this->eventQueryService = $eventQueryService;
        $this->reservationRepository = $reservationRepository;
        $this->reservationTempRepository = $reservationTempRepository;
        $this->paginationService = $paginationService;
        $this->reservationUpdateService = $reservationUpdateService;
        $this->reservationDeletionService = $reservationDeletionService;
        $this->paymentWebhookService = $paymentWebhookService;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->helloAssoService = $helloAssoService;
        $this->reservationTokenService = $reservationTokenService;
        $this->reservationQueryService = $reservationQueryService;
        $this->mailService = $mailService;
        $this->dataHelper = $dataHelper;
        $this->EventPiscineService = $EventPiscineService;
        $this->checkIfCurrentUserIsAllowedToManagedThis(3);
    }

    #[Route('/gestion/reservations', name: 'app_gestion_reservations')]
    public function index(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->authorizationService->getPermissionsFor($this->currentUser);
        $isReadOnly = !$this->authorizationService->hasPermission($this->currentUser, 'U');

        $tab = $_GET['tab'] ?? null;
        $sessionId = (int)($_GET['s'] ?? 0);
        $isCancel = isset($_GET['cancel']) && $_GET['cancel'];
        $isChecked = isset($_GET['check']) ? (bool)$_GET['check'] : null;
        $searchQuery = $_GET['q'] ?? '';
        $paginationConfig = $this->paginationService->createFromRequest($_GET);

        if ($tab == 'extract') {
            //On envoie tous les galas
            $events = $this->eventQueryService->getAllEventsWithRelations();
        } elseif ($tab == 'incoming') {
            //On envoie les réservations en cours : dans reservation_temp
            $events = $this->eventQueryService->getAllEventsWithRelations(true);
        } elseif ($tab == 'past') {
            //On envoie les galas passés
            $events = $this->eventQueryService->getAllEventsWithRelations(false);
        } else {
            //On envoie les galas à venir
            $events = $this->eventQueryService->getAllEventsWithRelations(true);
        }

        $paginator = null;
        if ($tab == 'incoming') {
            $repo = $this->reservationTempRepository;
        } else {
            $repo = $this->reservationRepository;
        }

        //Si on a une session, on cherche pour la session
        if ($sessionId > 0) {
            $paginator = $repo->findBySessionPaginated(
                $sessionId,
                $paginationConfig->getCurrentPage(),
                $paginationConfig->getItemsPerPage(),
                $isCancel,
                $isChecked
            );
        }
        //Si on a une recherche via le champ chercher
        if (!empty($searchQuery)) {
            $paginator = $this->reservationQueryService->searchReservationsWithParam(
                $searchQuery,
                $paginationConfig->getCurrentPage(),
                $paginationConfig->getItemsPerPage(),
                $repo
            );
        }

        //On récupère les mailTemplates envoyables manuellement :
        $emailsTemplatesToSendManually = $this->mailService->emailsTemplatesToSendManually();
        // Filtrer RecapFinal avant de passer au template
        $pdfTypesFiltered = array_filter(PdfGenerationService::PDF_TYPES, fn($key) => $key !== 'RecapFinal', ARRAY_FILTER_USE_KEY);

        // Données pour le timeout de la session utilisateur
        $durationInSeconds = DurationHelper::iso8601ToSeconds(TIMEOUT_PLACE_RESERV);

        //On récupère les piscines par event pour afficher le plan d'occupation
        $piscinesPerEvent = $this->EventPiscineService->getPiscinesPerEvent($events);

        $this->render('/gestion/reservations', [
            'events' => $events,
            'selectedSessionId' => $sessionId,
            'piscinesPerEvent' => $piscinesPerEvent,
            'tab' => $tab,
            'emailsTemplatesToSendManually' => $emailsTemplatesToSendManually,
            'reservations' => $paginator ? $paginator->getItems() : [],
            'currentPage' => $paginator ? $paginator->getCurrentPage() : 1,
            'totalPages' => $paginator ? $paginator->getTotalPages() : 0,
            'itemsPerPage' => $paginationConfig->getItemsPerPage(),
            'userPermissions' => $userPermissions,
            'isReadOnly' => $isReadOnly,
            'pdfTypes' => $pdfTypesFiltered, // On passe la liste des types de PDF à la vue
            'isCancel' => $isCancel,                    //Pour les boutons de filtre
            'isChecked' => $isChecked,                  //Pour les boutons de filtre
            'searchQuery' => $searchQuery,              //Pour afficher la recherche en cours
            'timeout_session_reservation' => $durationInSeconds,
        ], "Gestion des réservations");
    }

    #[Route('/gestion/reservations/details/{id}', name: 'app_gestion_reservation_details', methods: ['GET'])]
    public function getReservationDetails(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('R');

        $reservation = $this->reservationRepository->findById($id, true, true, false, true);

        if (!$reservation) {
            $this->json(['error' => 'Réservation non trouvée'], 404);
        }

        $this->json($reservation->toArray());
    }

    #[Route('/gestion/reservations-temp/details/{id}', name: 'app_gestion_reservation_temp_details', methods: ['GET'])]
    public function getReservationTempDetails(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('R');

        $reservation = $this->reservationTempRepository->findById($id);

        if (!$reservation) {
            $this->json(['error' => 'Réservation temporaire non trouvée'], 404);
        }

        $this->json($reservation->toArray());
    }

    #[Route('/gestion/reservations-temp/delete/{id}', name: 'app_gestion_reservation_temp_delete', methods: ['DELETE'])]
    public function deleteTempReservation(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('D');

        $reservation = $this->reservationTempRepository->findById($id);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation temporaire non trouvée.'], 404);
        }

        // Ajout de la vérification du statut de verrouillage
        if ($reservation->isLocked()) {
            $this->json(['success' => false, 'message' => 'Impossible de supprimer une réservation temporaire qui est verrouillée.'], 403);
        }

        try {
            // Utilise la méthode existante deleteByIds du repository
            $this->reservationTempRepository->deleteByIds([$id]);
            //On log l'event
            Logger::get()->event(
                'reservation.temp.deleted',
                [
                    'reservation_temp_id' => $id,
                    'user_id' => $this->currentUser?->getId() ?? null,
                ]);
            $this->flashMessageService->setFlashMessage('success', "La réservation temporaire a été supprimée avec succès.");

            $this->json(['success' => true]);
        } catch (Exception $e) {
            // Log de l'erreur pour le débogage
            error_log("Erreur lors de la suppression de la réservation temporaire ID $id : " . $e->getMessage());
            Logger::get()->event(
                'reservation.temp.delete.failed',
                [
                    'reservation_temp_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            // Message d'erreur générique pour l'utilisateur
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation temporaire.' . $e], 500);
        } catch (Throwable $e) {
            Logger::get()->event(
                'reservation.temp.delete.failed',
                [
                    'reservation_temp_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation temporaire.' . $e], 500);
        }
    }

    #[Route('/gestion/reservations-temp/toggle-lock', name: 'app_gestion_reservation_temp_toggle_lock', methods: ['POST'])]
    public function toggleTempLock(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['id', 'isLocked']);

        try {
            $this->reservationTempRepository->updateSingleField((int)$data['id'], 'is_locked', (bool)$data['isLocked']);

            // Log de l'événement
            $eventCode = (bool)$data['isLocked'] ? 'reservation.temp.locked' : 'reservation.temp.unlocked';
            Logger::get()->event(
                $eventCode,
                [
                    'reservation_temp_id' => (int)$data['id'],
                    'is_locked' => (bool)$data['isLocked'],
                ]);

            // On génère et renvoie un nouveau token pour maintenir la session sécurisée
            $newCsrfToken = $this->csrfService->getToken($this->getCsrfContext());

            parent::json(['success' => true, 'csrfToken' => $newCsrfToken]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du verrouillage : " . $e->getMessage());
            Logger::get()->event(
                'reservation.temp.lock.failed',
                [
                    'reservation_temp_id' => (int)$data['id'],
                    'is_locked' => (bool)$data['isLocked'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    #[Route('/gestion/reservations/update', name: 'app_gestion_reservations_update', methods: ['POST'])]
    public function update(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['reservationId']);

        $reservation = $this->reservationRepository->findById((int)$data['reservationId'], true, true, false, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.']);
        }

        try {
            $return = $this->reservationUpdateService->handleUpdateReservationFields(
                $reservation,
                $data['typeField'] ?? '',
                $data['id'] ?? null,
                $data['tarifId'] ?? null,
                $data['field'] ?? null,
                $data['value'] ?? null,
                $data['action'] ?? null
            );

            if ($return['success'] ?? false) {
                Logger::get()->event(
                    'reservation.updated',
                    [
                        'reservation_id' => $reservation->getId(),
                        'type_field' => $data['typeField'] ?? '',
                        'field' => $data['field'] ?? '',
                    ]);
            } else {
                Logger::get()->event(
                    'reservation.update.failed',
                    [
                        'reservation_id' => $reservation->getId(),
                        'type_field' => $data['typeField'] ?? '',
                        'error' => $return['message'] ?? 'Unknown error',
                    ]);
            }

            $this->json($return);
        } catch (Exception $e) {
            Logger::get()->event(
                'reservation.update.failed',
                [
                    'reservation_id' => $reservation->getId(),
                    'type_field' => $data['typeField'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    #[Route('/gestion/reservations/update-seat', name: 'app_gestion_reservations_update_seat', methods: ['POST'])]
    public function updateSeat(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['detailId', 'newPlaceId']);

        $detailId = (int)($data['detailId'] ?? 0);
        // newPlaceId peut être null si on libère la place
        $newPlaceId = isset($data['newPlaceId']) ? (int)$data['newPlaceId'] : null;

        try {
            $success = $this->reservationUpdateService->updateSeatForDetail($detailId, $newPlaceId);
            if ($success) {
                Logger::get()->event(
                    'reservation.seat.updated',
                    [
                        'detail_id' => $detailId,
                        'new_place_id' => $newPlaceId,
                    ]);
                $this->json(['success' => true, 'message' => 'La place a été mise à jour avec succès.']);
            } else {
                Logger::get()->event(
                    'reservation.seat.update.failed',
                    [
                        'detail_id' => $detailId,
                        'new_place_id' => $newPlaceId,
                    ]);
                $this->json(['success' => false, 'message' => 'La mise à jour de la place a échoué.'], 500);
            }
        } catch (InvalidArgumentException $e) {
            Logger::get()->event(
                'reservation.seat.update.failed',
                [
                    'detail_id' => $detailId,
                    'new_place_id' => $newPlaceId,
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }


    #[Route('/gestion/reservations/toggle-status', name: 'app_gestion_reservations_toggle_status', methods: ['POST'])]
    public function toggleStatus(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['id', 'status']);

        try {
            $this->reservationRepository->updateSingleField((int)$data['id'], 'is_checked', (bool)$data['status']);
            Logger::get()->event(
                'reservation.checked.toggled',
                [
                    'reservation_id' => (int)$data['id'],
                    'is_checked' => (bool)$data['status'],
                ]);
            $this->flashMessageService->setFlashMessage('success', "Le statut a été mis à jour avec succès.");
            // On génère et renvoie un nouveau token pour maintenir la session sécurisée
            $newCsrfToken = $this->csrfService->getToken($this->getCsrfContext());

            parent::json(['success' => true, 'csrfToken' => $newCsrfToken]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            Logger::get()->event(
                'reservation.checked.toggle.failed',
                [
                    'reservation_id' => (int)$data['id'],
                    'is_checked' => (bool)$data['status'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }


    #[Route('/gestion/reservations/delete/{id}', name: 'app_gestion_reservations_delete', methods: ['DELETE'])]
    public function delete(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('D');

        $reservation = $this->reservationRepository->findById($id);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
        }
        try {
            $this->reservationDeletionService->deleteReservation($id);
            Logger::get()->event(
                'reservation.deleted',
                [
                    'reservation_id' => $id,
                ]);
            $this->flashMessageService->setFlashMessage('success', "La réservation a été supprimée avec succès.");

            $this->json(['success' => true]);
        } catch (Exception $e) {
            // Log de l'erreur pour le débogage
            error_log("Erreur lors de la suppression de la réservation ID $id : " . $e->getMessage());
            Logger::get()->event(
                'reservation.delete.failed',
                [
                    'reservation_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            // Message d'erreur générique pour l'utilisateur
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation.' . $e], 500);
        } catch (Throwable $e) {
            Logger::get()->event(
                'reservation.delete.failed',
                [
                    'reservation_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Une erreur serveur est survenue lors de la suppression de la réservation.' . $e], 500);
        }


    }

    #[Route('/gestion/reservations/refresh-payment', name: 'app_gestion_reservations_refresh_payment', methods: ['POST'])]
    public function requestRefresh(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('R');

        $data = $this->dataHelper->getAndCheckPostData(['paymentId']);

        //On va chercher le paiementID de HelloAsso concerné
        $payment = $this->reservationPaymentRepository->findById($data['paymentId']);
        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Paiement non trouvé.'], 404);
        }

        if ($payment->getType() == 'man' || $payment->getCheckoutId() === 0) {
            Logger::get()->event(
                'reservation.payment.refresh.failed',
                [
                    'payment_id' => $data['paymentId'],
                    'reason' => 'Payment already up to date or manual payment',
                ]);
            $this->json(['success' => false, 'message' => 'Paiement déjà à jour.'], 404);
        }

        try {
            $result = $this->paymentWebhookService->handlePaymentState($payment->getPaymentId());
            Logger::get()->event(
                'reservation.payment.refreshed',
                [
                    'payment_id' => $data['paymentId'],
                ]);
            $this->json($result);
        } catch (Exception $e) {
            Logger::get()->event(
                'reservation.payment.refresh.failed',
                [
                    'payment_id' => $data['paymentId'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur lors de l\'actualisation du paiement.'], 500);
        }
    }


    #[Route('/gestion/reservations/refund', name: 'app_gestion_reservations_refund', methods: ['POST'])]
    public function requestRefund(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['paymentId']);

        //On va chercher le paiementID de HelloAsso concerné
        $payment = $this->reservationPaymentRepository->findById($data['paymentId']);
        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Paiement non trouvé.'], 404);
        }

        try {
            //Si le paiement était en type 'man' on gère différemment, car pas passé par HelloAsso.
            if ($payment->getType() != 'man') {
                $this->helloAssoService->refundPayment($payment->getPaymentId(), 'remboursement sur demande)');
                $result = $this->paymentWebhookService->handlePaymentState($payment->getPaymentId());
            } else {
                $result = $this->paymentWebhookService->processRefundManuelPayment($payment);
            }

            Logger::get()->event(
                'reservation.payment.refunded',
                [
                    'payment_id' => $data['paymentId'],
                    'payment_type' => $payment->getType(),
                ]);

            $this->json(['success' => true, 'result' => $result]);
        } catch (Exception $e) {
            Logger::get()->event(
                'reservation.payment.refund.failed',
                [
                    'payment_id' => $data['paymentId'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
        }
    }

    #[Route('/gestion/reservations/paid', name: 'app_gestion_reservations_paid', methods: ['POST'])]
    public function requestMarkAsPaid(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        //On récupère les données
        $data = $this->dataHelper->getAndCheckPostData(['reservationId']);

        $reservation = $this->reservationRepository->findById($data['reservationId']);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée']);
        }

        $amount = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();

        //On créée un objet Payment
        $newPayment = new ReservationPayment();
        $newPayment->setReservation($reservation->getid())
            ->setType('man')
            ->setCheckoutId(0)
            ->setOrderId(0)
            ->setPaymentId(0)
            ->setAmountPaid($amount)
            ->setStatusPayment('Processed');

        //On met à jour l'objet Reservation
        $reservation->setTotalAmountPaid($reservation->getTotalAmount());

        //On insère une ligne dans la table de paiement en type 'man'
        $this->reservationPaymentRepository->insert($newPayment);
        //On met à jour la réservation : amount paid = total amount
        $this->reservationRepository->update($reservation);

        Logger::get()->event(
            'reservation.manual_payment.marked',
            [
                'reservation_id' => $reservation->getId(),
                'amount' => $amount,
                'method' => 'manual',
            ]);

        $this->json(['success' => true, 'reservation' => $reservation]);
    }


    #[Route('/gestion/reservations/reinit-token', name: 'app_gestion_reservations_reinit', methods: ['POST'])]
    public function reinitToken(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        //On récupère les données
        $data = $this->dataHelper->getAndCheckPostData(['reservationId']);

        $reservation = $this->reservationRepository->findById($data['reservationId'], true, true, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée']);
        }

        try {
            $newReservation = $this->reservationTokenService->updateToken(
                $reservation,
                isset($data['token']),
                $data['new_expire_at'] ?? false,
                $data['sendEmail'] ?? false
            );

            Logger::get()->event(
                'reservation.token.reinitialized',
                [
                    'reservation_id' => $data['reservationId'],
                    'send_email' => $data['sendEmail'] ?? false,
                ]);

            $this->json(['success' => true, 'reservation' => $newReservation->toArray()]);
        } catch (Exception $e) {
            Logger::get()->event(
                'reservation.token.reinit.failed',
                [
                    'reservation_id' => $data['reservationId'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur lors de la réinitialisation du token.'], 500);
        }
    }

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     */
    #[Route('/gestion/reservations/send-mail', name: 'app_gestion_reservations_send_email', methods: ['POST'])]
    public function sendEmail(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->dataHelper->getAndCheckPostData(['reservationId', 'templateCode']);
        //on récupère la réservation pour l'envoyer au mail
        $reservation = $this->reservationRepository->findById($data['reservationId'], true, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
        }

        try {
            //On envoie le mail
            $returnEmailSent = $this->mailService->send($data['templateCode'],
                [
                    'reservation' => $reservation,
                ],
                $reservation->getEmail(),
                'reservation.' . $data['templateCode']
            );

            if (!$returnEmailSent) {
                Logger::get()->event(
                    'reservation.email.send.failed',
                    [
                        'reservation_id' => $data['reservationId'],
                        'template_code' => $data['templateCode'],
                        'reason' => 'Mail not sent',
                    ]);
                $this->json(['success' => false, 'message' => 'Mail non envoyé']);
            } else {
                Logger::get()->event(
                    'reservation.email.sent',
                    [
                        'reservation_id' => $data['reservationId'],
                        'template_code' => $data['templateCode'],
                        'recipient' => $reservation->getEmail(),
                    ]);
                $this->json(['success' => true, 'message' => 'Mail envoyé', 'reservation' => $reservation->toArray()]);
            }
        } catch (Exception $e) {
            Logger::get()->event(
                'reservation.email.send.failed',
                [
                    'reservation_id' => $data['reservationId'],
                    'template_code' => $data['templateCode'],
                    'error' => $e->getMessage(),
                ]);
            $this->json(['success' => false, 'message' => 'Erreur serveur lors de l\'envoi du mail.'], 500);
        }
    }

}