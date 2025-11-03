<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Enums\LogType;
use app\Models\Reservation\ReservationPayment;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Log\Logger;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Mails\MailTemplateService;
use app\Services\Pagination\PaginationService;
use app\Services\Payment\HelloAssoService;
use app\Services\Payment\PaymentWebhookService;
use app\Services\Pdf\PdfGenerationService;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationDeletionService;
use app\Services\Reservation\ReservationTokenService;
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
    private HelloAssoService $helloAssoService;
    private ReservationTokenService $reservationTokenService;
    private PdfGenerationService $PdfGenerationService;
    private ReservationQueryService $reservationQueryService;
    private MailService $mailService;
    private MailPrepareService $mailPrepareService;

    function __construct(
        EventQueryService $eventQueryService,
        ReservationRepository $reservationRepository,
        PaginationService $paginationService,
        ReservationUpdateService $reservationUpdateService,
        ReservationDeletionService $reservationDeletionService,
        PaymentWebhookService $paymentWebhookService,
        ReservationPaymentRepository $reservationPaymentRepository,
        HelloAssoService $helloAssoService,
        ReservationTokenService $reservationTokenService,
        PdfGenerationService $PdfGenerationService,
        ReservationQueryService $reservationQueryService,
        MailService $mailService,
        MailPrepareService $mailPrepareService,
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
        $this->helloAssoService = $helloAssoService;
        $this->reservationTokenService = $reservationTokenService;
        $this->PdfGenerationService = $PdfGenerationService;
        $this->reservationQueryService = $reservationQueryService;
        $this->mailService = $mailService;
        $this->mailPrepareService = $mailPrepareService;
    }

    #[Route('/gestion/reservations', name: 'app_gestion_reservations')]
    public function index(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $userPermissions = $this->whatCanDoCurrentUser();
        $isReadOnly = !str_contains($userPermissions, 'U');

        $tab = $_GET['tab'] ?? null;
        $sessionId = (int)($_GET['s'] ?? 0);
        $isCancel = isset($_GET['cancel']) && $_GET['cancel'];
        $isChecked = isset($_GET['check']) ? (bool)$_GET['check'] : null;
        $searchQuery = $_GET['q'] ?? '';
        $paginationConfig = $this->paginationService->createFromRequest($_GET);

        if ($tab == 'extract') {
            //On envoie tous les galas
            $events = $this->eventQueryService->getAllEventsWithRelations();
        } elseif ($tab == 'past') {
            //On envoie les galas passés
            $events = $this->eventQueryService->getAllEventsWithRelations(false);
        } else {
            //On envoie les galas à venir
            $events = $this->eventQueryService->getAllEventsWithRelations(true);
        }

        $paginator = null;
        //Si on a une session, on cherche pour la session
        if ($sessionId > 0) {
            $paginator = $this->reservationRepository->findBySessionPaginated(
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
            );
        }

        //On récupère les mailTemplates envoyables manuellement :
        $emailsTemplatesToSendManually = $this->mailService->emailsTemplatesToSendManually();
        // Filtrer RecapFinal avant de passer au template
        $pdfTypesFiltered = array_filter(PdfGenerationService::PDF_TYPES, fn($key) => $key !== 'RecapFinal', ARRAY_FILTER_USE_KEY);

        $this->render('/gestion/reservations', [
            'events' => $events,
            'selectedSessionId' => $sessionId,
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
            return;
        }

        $this->json($reservation->toArray());
    }

    #[Route('/gestion/reservations/update', name: 'app_gestion_reservations_update', methods: ['POST'])]
    public function update(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->getAndCheckPostData(['reservationId']);

        $reservation = $this->reservationRepository->findById((int)$data['reservationId'], false, false, false, true);
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
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->getAndCheckPostData(['id', 'status']);

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
        $this->checkUserPermission('D');

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
        $this->checkUserPermission('R');

        $data = $this->getAndCheckPostData(['paymentId']);

        //On va chercher le paiementID de HelloAsso concerné
        $payment = $this->reservationPaymentRepository->findById($data['paymentId']);
        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Paiement non trouvé.'], 404);
        }

        if ($payment->getType() == 'man' || $payment->getCheckoutId() === 0) {
            $this->json(['success' => false, 'message' => 'Paiement déjà à jour.'], 404);
        }
        $result = $this->paymentWebhookService->handlePaymentState($payment->getPaymentId());

        $this->json($result);
    }


    #[Route('/gestion/reservations/refund', name: 'app_gestion_reservations_refund', methods: ['POST'])]
    public function requestRefund(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->getAndCheckPostData(['paymentId']);

        //On va chercher le paiementID de HelloAsso concerné
        $payment = $this->reservationPaymentRepository->findById($data['paymentId']);
        if (!$payment) {
            $this->json(['success' => false, 'message' => 'Paiement non trouvé.'], 404);
            return;
        }

        //Si le paiement était en type 'man' on gère différemment, car pas passé par HelloAsso.
        if ($payment->getType() != 'man') {
            $this->helloAssoService->refundPayment($payment->getPaymentId(), 'remboursement sur demande)');
            $result = $this->paymentWebhookService->handlePaymentState($payment->getPaymentId());
        } else {
            $result = $this->paymentWebhookService->processRefundManuelPayment($payment);
        }

        $this->json(['success' => true, 'result' => $result]);
    }

    #[Route('/gestion/reservations/paid', name: 'app_gestion_reservations_paid', methods: ['POST'])]
    public function requestMarkAsPaid(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        //On récupère les données
        $data = $this->getAndCheckPostData(['reservationId']);

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
        // Log action sensible sur le channel "application"
        Logger::get()->info(
            LogType::APPLICATION->value,
            'reservation_marked_as_paid',
            [
                'reservation_id' => $reservation->getId(),
                'amount' => $amount,
                'method' => 'manual',
                'user_id' => $this->currentUser?->getId() ?? null,
                'user_login' => $this->currentUser?->getUsername() ?? ''
            ]
        );

        $this->json(['success' => true, 'reservation' => $reservation]);
    }


    #[Route('/gestion/reservations/reinit-token', name: 'app_gestion_reservations_reinit', methods: ['POST'])]
    public function reinitToken(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        //On récupère les données
        $data = $this->getAndCheckPostData(['reservationId']);

        $reservation = $this->reservationRepository->findById($data['reservationId'], true, true, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée']);
        }

        $newReservation = $this->reservationTokenService->updateToken(
            $reservation,
            isset($data['token']),
            $data['new_expire_at'] ?? false,
            $data['sendEmail'] ?? false
        );

        $this->json(['success' => true, 'reservation' => $newReservation->toArray()]);
    }

    /**
     * Récupère les données envoyées en POST et vérifie si la/les clés recherchées sont présentes
     *
     * @param array $keyToCheck
     * @return array|null
     */
    private function getAndCheckPostData(array $keyToCheck = []): ?array
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        //On vérifie que c'est bien un tableau
        if (!is_array($data)) {
            $this->json(['success' => false, 'message' => 'Données invalides.']);
            return null;
        }

        //S'il n'y a rien à vérifier, on retourne les données
        if (empty($keyToCheck)) {
            return $data;
        }

        //On vérifie si la ou les clés recherchées sont contenues dans $data
        foreach ($keyToCheck as $key) {
            if (!is_string($key) || !array_key_exists($key, $data)) {
                $this->json(['success' => false, 'message' => 'Données manquantes.']);
                return null;
            }
        }

        return $data;
    }

    #[Route('/gestion/reservations/exports', name: 'app_gestion_reservations_exports', methods: ['GET'])]
    public function exports(): void
    {
        // Récupérer les paramètres depuis $_GET
        $sessionId = (int)($_GET['s'] ?? 0);
        $pdfType = $_GET['pdf'] ?? 'ListeParticipants';
        $sortOrder = $_GET['tri'] ?? 'IDreservation';

        try {
            // On construit le PDF en fonction de son type.
            $pdf = $this->PdfGenerationService->generate($pdfType, $sessionId, $sortOrder);

            // On envoie le PDF construit au navigateur.
            $pdf->Output('I', $this->PdfGenerationService->getFilenameForPdf($pdfType,$sessionId) . '.pdf');
            exit;
        } catch (Exception $e) {
            http_response_code(404);
            die("Erreur lors de la génération du PDF : " . $e->getMessage());
        }
    }

    #[Route('/gestion/reservations/send-mail', name: 'app_gestion_reservations_send_email', methods: ['POST'])]
    public function sendEmail(): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('U');

        $data = $this->getAndCheckPostData(['reservationId', 'templateCode']);
        //on récupère la réservation pour l'envoyer au mail
        $reservation = $this->reservationRepository->findById($data['reservationId'], true, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
        }

        //On essaie d'envoyer le mail demandé
        $returnEmailSent = $this->mailPrepareService->sendReservationConfirmationEmail($reservation, $data['templateCode']);
        //On enregistre l'envoi
        $this->mailService->recordMailSent($reservation, $data['templateCode']);

        if (!$returnEmailSent) {
            $this->json(['success' => false, 'message' => 'Mail non envoyé']);
        }

        $this->json(['success' => true, 'message' => 'Mail envoyé', 'reservation' => $reservation->toArray()]);
    }

}