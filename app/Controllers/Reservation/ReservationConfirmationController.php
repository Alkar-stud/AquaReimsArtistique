<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\HelloAssoCartDTO;
use app\Models\Reservation\ReservationPayments;
use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\TarifsRepository;
use app\Services\HelloAssoService;
use app\Services\MongoReservationStorage;
use app\Services\Payment\PaymentService;
use app\Services\Payment\PaymentWebhookService;
use app\Services\Reservation\ReservationCartService;
use app\Services\Reservation\ReservationPersistenceService;
use app\Services\Reservation\ReservationprocessAndPersistService;
use app\Services\Reservation\ReservationTokenService;
use app\Services\ReservationStorageInterface;
use app\Utils\CsrfHelper;
use DateMalformedStringException;
use Exception;
use MongoDB\BSON\UTCDateTime;

class ReservationConfirmationController extends AbstractController
{
    private HelloAssoService $helloAssoService;
    private HelloAssoCartDTO $helloAssoCart;
    private ReservationTokenService $reservationTokenService;
    private ReservationStorageInterface $reservationStorage;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;
    private ReservationPersistenceService $persistenceService;
    private ReservationCartService $reservationCartService;
    private ReservationprocessAndPersistService $reservationprocessAndPersistService;
    private PaymentService $paymentService;
    private PaymentWebhookService $paymentWebhookService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->helloAssoService = new HelloAssoService;
        $this->helloAssoCart = new HelloAssoCartDTO;
        $this->reservationTokenService = new ReservationTokenService;
        // Pour MongoDB, à changer si autre BDD
        $this->reservationStorage = new MongoReservationStorage('ReservationTemp');
        $this->reservationsPlacesTempRepository = new ReservationsPlacesTempRepository();
        $this->persistenceService = new ReservationPersistenceService(
            $this->reservationStorage,
            $this->reservationTokenService,
            $this->reservationsPlacesTempRepository
        );
        $this->reservationCartService = new ReservationCartService();
        $this->reservationprocessAndPersistService = new ReservationprocessAndPersistService();
        $this->paymentService = new PaymentService();
        $this->paymentWebhookService = new PaymentWebhookService();
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/confirmation', name: 'app_reservation_confirmation')]
    public function index(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? [];
        $eventsRepository = new EventsRepository();
        $tarifsRepository = new TarifsRepository();
        $nageusesRepository = new NageusesRepository();

        $event = null;
        if ($reservation && !empty($reservation['event_id'])) {
            $event = $eventsRepository->findById($reservation['event_id']);
        }
        if (
            !$reservation
            || empty($reservation['event_id'])
            || ($event && $event->getLimitationPerSwimmer() !== null && empty($reservation['nageuse_id']))
        ) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $event = null;
        $sessionObj = null;
        $nageuse = null;
        $tarifsById = [];

        $event = $eventsRepository->findById($reservation['event_id']);
        if ($event && !empty($reservation['event_session_id'])) {
            foreach ($event->getSessions() as $s) {
                if ($s->getId() == $reservation['event_session_id']) {
                    $sessionObj = $s;
                    break;
                }
            }
        }
        $tarifs = $tarifsRepository->findByEventId($reservation['event_id']);
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif->getLibelle();
        }
        if (!empty($reservation['nageuse_id'])) {
            $nageuse = $nageusesRepository->findById($reservation['nageuse_id']);
        }

        // Calculer le total et les quantités correctes pour l'affichage
        $totalAmount = $this->reservationCartService->calculateTotalAmount($reservation);
        $allEventTarifs = $tarifsRepository->findByEventId($reservation['event_id']);
        $tarifQuantities = $this->reservationCartService->getTarifQuantitiesFromDetails($reservation['reservation_detail'] ?? [], $allEventTarifs);

        $this->render('reservation/confirmation', [
            'reservation' => $reservation,
            'csrf_token' => $this->getCsrfToken(),
            'event' => $event,
            'session' => $sessionObj,
            'nageuse' => $nageuse,
            'tarifs' => $allEventTarifs, // On passe tous les tarifs
            'tarifsById' => $tarifsById,
            'totalAmount' => $totalAmount,
            'tarifQuantities' => $tarifQuantities
        ], 'Réservations - Confirmation');
    }

    #[Route('/reservation/saveCart', name: 'app_reservation_saveCart', methods: ['POST'])]
    public function saveCart(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!CsrfHelper::validateToken($input['csrf_token'] ?? '', 'reservation_saveCart')) return;

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $this->json(['success' => true]);
    }


    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/payment', name: 'app_reservation_payment')]
    public function payment(): void
    {
        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $now = time();
        $intentTimestamp = $reservation['paymentIntentTimestamp'] ?? 0;
        // L'URL est valide pendant 15 minutes, on la régénère au bout de 10 minutes par sécurité.
        $isIntentValid = ($now - $intentTimestamp) < 600; // 10 minutes = 600 seconds

        // Si un checkoutIntentId et une URL de redirection existent déjà en session ET sont valides, on les réutilise.
        if (
            !empty($reservation['checkoutIntentId']) &&
            !empty($reservation['redirectUrl']) &&
            $isIntentValid
        ) {
            $redirectUrl = $reservation['redirectUrl'];
        } else {
            // Sinon, on en crée un nouveau auprès de HelloAsso
            $total = $this->reservationCartService->calculateTotalAmount($reservation);

            // Préparation des données pour le PaymentService
            $event = (new EventsRepository())->findById($reservation['event_id']);
            $itemName = 'Réservation gala ' . $event->getLibelle();
            $payerInfo = [
                'firstName' => $reservation['user']['prenom'],
                'lastName'  => $reservation['user']['nom'],
                'email'     => $reservation['user']['email']
            ];

            // Sauvegarde de la réservation temporaire pour obtenir un ID stable
            $reservation['total'] = $total;
            $reservation['php_session_id'] = $sessionId;
            $mongoId = $this->reservationStorage->saveOrUpdateReservation($reservation);
            $_SESSION['reservation'][$sessionId]['reservationId'] = $mongoId;

            // Définition des URLs de callback
            $protocol = "https://";
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . $host;

            // Création et hydratation du DTO
            $cartDTO = new HelloAssoCartDTO();
            $cartDTO->setTotalAmount($total);
            $cartDTO->setItemName($itemName);
            $cartDTO->setPayer($payerInfo);
            $cartDTO->setMetaData(['reservationId' => $mongoId, 'context' => 'new_reservation']);
            $cartDTO->setBackUrl($baseUrl . '/reservation/confirmation');
            $cartDTO->setErrorUrl($baseUrl . '/reservation/erreur');
            $cartDTO->setReturnUrl($baseUrl . '/reservation/merci');
            // La notion de don n'est pas gérée dans le tunnel initial, donc false par défaut.
            $cartDTO->setContainsDonation(false);

            // Appel du PaymentService
            $paymentResult = $this->paymentService->createPaymentIntent($cartDTO);

            if ($paymentResult['success']) {
                $redirectUrl = $paymentResult['redirectUrl'];
                $checkoutIntentId = $paymentResult['checkoutIntentId'];

                // Sauvegarde des informations de paiement dans la session et la BDD temporaire
                $_SESSION['reservation'][$sessionId]['checkoutIntentId'] = $checkoutIntentId;
                $_SESSION['reservation'][$sessionId]['redirectUrl'] = $redirectUrl;
                $_SESSION['reservation'][$sessionId]['paymentIntentTimestamp'] = $now;
                $this->reservationStorage->updateReservation($mongoId, ['checkoutIntentId' => $checkoutIntentId]);
            } else {
                // Gérer l'erreur de création de paiement
                error_log("Erreur de création de paiement HelloAsso: " . ($paymentResult['error'] ?? 'Inconnue'));
                // Pour le débogage, on affiche l'erreur complète au lieu de rediriger.
                echo "<h1>Erreur lors de la création du paiement</h1>";
                echo "<p>Le service de paiement a retourné une erreur. Voici les détails :</p>";
                echo "<pre>";
                print_r($paymentResult);
                echo "</pre>";
                //header('Location: /reservation/erreur?msg=creation_paiement_impossible');
                exit;
            }
        }

        $this->render('reservation/payment', [
            'reservation' => $_SESSION['reservation'][$sessionId],
            'redirectUrl' => $redirectUrl,
        ], 'Paiement réservation');
    }

    #[Route('/reservation/merci', name: 'app_reservation_merci')]
    public function merci(): void
    {
        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;

        $this->render('reservation/merci', [
            'reservation' => $reservation
        ], 'Vérification du paiement');
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/checkPayment', name: 'app_reservation_checkPayment', methods: ['POST'])]
    public function checkPayment(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $checkoutIntentId = $input['checkoutIntentId'] ?? null;
        if (!$checkoutIntentId) {
            $this->json(['success' => false, 'error' => 'checkoutId manquant']);
            return;
        }
        // Vérifier dans la BDD si un paiement avec cet ID a été enregistré (par le webhook)
        $paymentsRepository = new ReservationPaymentsRepository();
        $payment = $paymentsRepository->findByCheckoutId((int)$checkoutIntentId);

        // Renvoyer le statut au front
        if ($payment && in_array($payment->getStatusPayment(), ['Authorized', 'Processed'])) {
            $this->handleSuccessfulCheck($payment);
        } else {
            // Le paiement n'est pas encore trouvé ou n'a pas le bon statut, on indique au front de patienter.
            $this->json(['success' => false, 'status' => 'pending']);
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/forceCheckPayment', name: 'app_reservation_forceCheckPayment', methods: ['POST'])]
    public function forceCheckPayment(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $checkoutIntentId = $input['checkoutIntentId'] ?? null;
        if (!$checkoutIntentId) {
            $this->json(['success' => false, 'error' => 'checkoutId manquant']);
            return;
        }

        // Vérification si le paiement a déjà été enregistré
        $paymentsRepository = new ReservationPaymentsRepository();
        $payment = $paymentsRepository->findByCheckoutId((int) $checkoutIntentId);
        if ($payment) {
            $this->handleSuccessfulCheck($payment);
            return;
        }

        // Directly check with HelloAsso
        $accessToken = $this->helloAssoService->getToken();
        $result = $this->helloAssoService->checkPayment($accessToken, $checkoutIntentId);

        // Validate the response from HelloAsso
        if (
            !isset($result->order->items[0]->state) ||
            $result->order->items[0]->state !== 'Processed' ||
            !isset($result->metadata->reservationId)
        ) {
            $this->json(['success' => false, 'error' => 'Le paiement n\'est pas confirmé par HelloAsso.', 'details' => $result]);
            return;
        }

        $reservationIdMongo = $result->metadata->reservationId;
        $orderData = $result->order;
        $orderData->checkoutIntentId = $result->id;

        $persistedReservation = $this->reservationprocessAndPersistService->processAndPersistReservation($orderData, $reservationIdMongo);

        if ($persistedReservation) {
            unset($_SESSION['reservation'][session_id()]);
            $this->json(['success' => true, 'reservationUuid' => $persistedReservation->getUuid()]);
        } else {
            $this->json(['success' => false, 'error' => 'Erreur serveur lors de la sauvegarde de la réservation.']);
        }
    }

    #[Route('/reservation/clear-payment-intent', name: 'app_reservation_clear_payment_intent', methods: ['POST'])]
    public function clearPaymentIntent(): void
    {
        $sessionId = session_id();
        if (isset($_SESSION['reservation'][$sessionId])) {
            unset($_SESSION['reservation'][$sessionId]['checkoutIntentId']);
            unset($_SESSION['reservation'][$sessionId]['redirectUrl']);
            unset($_SESSION['reservation'][$sessionId]['paymentIntentTimestamp']);
        }
        $this->json(['success' => true]);
    }

    /**
     * Gère la réponse JSON pour une vérification de paiement réussie.
     * @param ReservationPayments $payment
     * @throws DateMalformedStringException
     */
    private function handleSuccessfulCheck(ReservationPayments $payment): void
    {
        $reservationsRepository = new ReservationsRepository();
        $reservation = $reservationsRepository->findById($payment->getReservation());

        if ($reservation) {
            unset($_SESSION['reservation'][session_id()]);
            $this->json(['success' => true, 'reservationUuid' => $reservation->getUuid()]);
        } else {
            // Cas peu probable où le paiement existe, mais pas la réservation associée
            $this->json(['success' => false, 'error' => 'Paiement trouvé mais réservation introuvable.']);
        }
    }

    /**
     * @throws Exception
     */
    #[Route('/reservation/finalize-free', name: 'app_reservation_finalize_free', methods: ['POST'])]
    public function finalizeFreeReservation(): void
    {
        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        // Sécurité : on re-calcule le total pour s'assurer qu'il est bien de 0.
        $total = $this->reservationCartService->calculateTotalAmount($reservation);
        if ($total > 0) {
            $this->json(['success' => false, 'error' => 'Cette réservation n\'est pas gratuite.']);
            return;
        }

        // Sauvegarde en BDD temporaire si ce n'est pas déjà fait
        $reservationId = $reservation['reservationId'] ?? null;
        if (!$reservationId) {
            $reservation['createdAt'] = new UTCDateTime(time() * 1000);
            $reservationId = $this->reservationStorage->saveReservation($reservation);
            $_SESSION['reservation'][$sessionId]['reservationId'] = $reservationId;
        }

        // Utilisation du service de persistance pour les réservations gratuites
        $persistedReservation = $this->persistenceService->persistFreeReservation($reservation);

        if ($persistedReservation) {
            // Nettoyer la session de réservation
            unset($_SESSION['reservation'][$sessionId]);
            $this->json(['success' => true, 'reservationUuid' => $persistedReservation->getUuid()]);
        } else {
            $this->json(['success' => false, 'error' => 'Erreur serveur lors de la sauvegarde de la réservation.']);
        }
    }


    /**
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/success', name: 'app_reservation_success')]
    public function success(): void
    {
        $uuid = $_GET['uuid'] ?? null;
        if (!$uuid) {
            // Pas d'ID, on ne peut rien afficher.
            header('Location: /');
            exit;
        }

        $reservationsRepository = new ReservationsRepository();
        $reservation = $reservationsRepository->findByUuid($uuid);

        if (!$reservation) {
            header('Location: /');
            exit;
        }

        // Récupération des données associées pour l'affichage du récapitulatif
        $eventsRepository = new EventsRepository();
        $tarifsRepository = new TarifsRepository();
        $nageusesRepository = new NageusesRepository();
        $detailsRepository = new ReservationsDetailsRepository();
        $complementsRepository = new ReservationsComplementsRepository();

        $event = $eventsRepository->findById($reservation->getEvent());
        $sessionObj = null;
        if ($event) {
            foreach ($event->getSessions() as $s) {
                if ($s->getId() == $reservation->getEventSession()) {
                    $sessionObj = $s;
                    break;
                }
            }
        }

        $this->render('reservation/success', [
            'reservation' => $reservation,
            'reservationDetails' => $detailsRepository->findByReservation($reservation->getId()),
            'reservationComplements' => $complementsRepository->findByReservation($reservation->getId()),
            'event' => $event,
            'session' => $sessionObj,
            'nageuse' => $reservation->getNageuseId() ? $nageusesRepository->findById($reservation->getNageuseId()) : null,
            'tarifs' => $tarifsRepository->findByEventId($reservation->getEvent()),
            'reservationNumber' => $reservation->getId()
        ], 'Réservation confirmée');
    }


}