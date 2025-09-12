<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\ReservationPayments;
use app\Models\Reservation\Reservations;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\TarifsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Services\HelloAssoService;
use app\DTO\HelloAssoCartDTO;
use app\Services\ReservationService;
use app\Utils\CsrfHelper;
use app\Utils\ReservationHelper;
use app\Services\ReservationStorageInterface;
use app\Services\ReservationPersistenceService;
use app\Services\MongoReservationStorage;
use DateMalformedStringException;
use MongoDB\BSON\UTCDateTime;

// à changer si la sauvegarde temporaire ne se fait plus dans MongoDB


class ReservationConfirmationController extends AbstractController
{
    private HelloAssoService $helloAssoService;
    private HelloAssoCartDTO $helloAssoCart;
    private ReservationHelper $reservationHelper;
    private ReservationStorageInterface $reservationStorage;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;
    private ReservationPersistenceService $persistenceService;
    private ReservationService $reservationService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->helloAssoService = new HelloAssoService;
        $this->helloAssoCart = new HelloAssoCartDTO;
        $this->reservationHelper = new ReservationHelper;
        // Pour MongoDB, à changer si autre BDD
        $this->reservationStorage = new MongoReservationStorage('ReservationTemp');
        $this->reservationsPlacesTempRepository = new ReservationsPlacesTempRepository();
        $this->persistenceService = new ReservationPersistenceService(
            $this->reservationStorage,
            $this->reservationHelper,
            $this->reservationsPlacesTempRepository
        );
        $this->reservationService = new ReservationService();
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
        $totalAmount = $this->reservationService->calculateTotalAmount($reservation);
        $allEventTarifs = $tarifsRepository->findByEventId($reservation['event_id']);
        $tarifQuantities = $this->reservationService->getTarifQuantitiesFromDetails($reservation['reservation_detail'] ?? [], $allEventTarifs);

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
            $event = (new EventsRepository())->findById($reservation['event_id']);
            $total = $this->reservationService->calculateTotalAmount($reservation);
            $_SESSION['reservation'][$sessionId]['total'] = $total;
            $reservation['total'] = $total;
            $reservation['php_session_id'] = $sessionId; // Pour retrouver la session dans reservations_places_temp car suppression en callback

            // S'assure qu'une réservation temporaire existe et la met à jour, ou la crée si besoin.
            $reservationId = $reservation['reservationId'] ?? null;
            if ($reservationId) {
                $reservation['updatedAt'] = new UTCDateTime(time() * 1000);
                $this->reservationStorage->updateReservation($reservationId, $reservation);
            } else {
                $reservation['createdAt'] = new UTCDateTime(time() * 1000);
                $reservationId = $this->reservationStorage->saveReservation($reservation);
                $_SESSION['reservation'][$sessionId]['reservationId'] = $reservationId;
            }

            $TabData = $this->helloAssoCart;
            $TabData->setTotalAmount($total);
            $TabData->setInitialAmount($total);
            $TabData->setPayer([
                'firstName' => $reservation['user']['prenom'],
                'lastName' => $reservation['user']['nom'],
                'email' => $reservation['user']['email'],
                'country' => 'FRA'
            ]);
            $TabData->setItemName('Réservation gala ' . $event->getLibelle());

            $TabData->setMetaData(['reservationId' => $reservationId]);
            $protocol = "https://";
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . $host;

            $TabData->setBackUrl($baseUrl . '/reservation/confirmation');
            $TabData->setErrorUrl($baseUrl . '/reservation/erreur');
            $TabData->setReturnUrl($baseUrl . '/reservation/merci');

            $accessToken = $this->helloAssoService->getToken();
            $checkout = $this->helloAssoService->PostCheckoutIntents($accessToken, $TabData);
            $redirectUrl = $checkout->redirectUrl;
            $checkoutIntentId = $checkout->id;

            // Ajout dans la session
            $_SESSION['reservation'][$sessionId]['checkoutIntentId'] = $checkoutIntentId;
            $_SESSION['reservation'][$sessionId]['redirectUrl'] = $redirectUrl;
            $_SESSION['reservation'][$sessionId]['paymentIntentTimestamp'] = $now;

            // Mise à jour dans MongoDB avec l'ID du checkout
            $this->reservationStorage->updateReservation(
                $reservationId,
                ['checkoutIntentId' => $checkoutIntentId]
            );
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
        // 2. Vérifier dans la BDD si un paiement avec cet ID a été enregistré (par le webhook)
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
    #[Route('/reservation/paymentCallback', name: 'app_reservation_paymentCallback', methods: ['POST'])]
    public function paymentCallback(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw);

        // Vérification de base de la notification
        if (
            !$payload || !isset($payload->eventType) || $payload->eventType !== 'Order' ||
            !isset($payload->data->items[0]->state) || $payload->data->items[0]->state !== 'Processed'
        ) {
            // Ce n'est pas une notification de paiement réussi, on l'ignore.
            http_response_code(200); // On répond 200 pour que HelloAsso ne réessaie pas.
            echo 'Notification ignored';
            return;
        }

        // Si APP_DEBUG est à true, on enregistre le payload pour le débogage
        if (isset($_ENV['HELLOASSO_DEBUG']) && $_ENV['HELLOASSO_DEBUG'] === 'true') {
            $dir = __DIR__ . '/../../../storage/app/private/';
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }
            $filename = $dir . 'webhook_callback_' . date('Ymd_His') . '_' . uniqid() . '.json';
            // On essaie de formater le JSON pour la lisibilité, sinon on sauvegarde le texte brut.
            $decodedPayload = json_decode($raw);
            $contentToSave = json_last_error() === JSON_ERROR_NONE ? json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $raw;
            file_put_contents($filename, $contentToSave);
        }

        // Récupérer l'ID de la réservation temporaire depuis les métadonnées
        $reservationIdMongo = $payload->metadata->reservationId ?? null;
        if (!$reservationIdMongo) {
            error_log("Webhook HelloAsso reçu sans reservationId dans les metadata.");
            http_response_code(200);
            echo 'Missing metadata';
            return;
        }

        // Traiter et persister la réservation
        $this->processAndPersistReservation($payload->data, $reservationIdMongo);

        http_response_code(200);
        echo 'OK';
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

        $persistedReservation = $this->processAndPersistReservation($orderData, $reservationIdMongo);

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
     * Logique centrale pour traiter et persister une réservation après un paiement réussi.
     * @param object $paymentData Données de la commande/paiement.
     * @param string $reservationIdMongo ID de la réservation temporaire.
     * @return Reservations|null
     * @throws DateMalformedStringException
     */
    private function processAndPersistReservation(object $paymentData, string $reservationIdMongo): ?Reservations
    {
        $reservationsRepository = new ReservationsRepository();
        // Vérifie si cette réservation a déjà été persistée pour éviter les doublons
        $existingReservation = $reservationsRepository->findByMongoId($reservationIdMongo);
        if ($existingReservation) {
            error_log("Tentative de double traitement pour la réservation MongoDB ID: " . $reservationIdMongo);
            return $existingReservation; // C'est déjà fait, on retourne l'objet existant.
        }

        $tempReservation = $this->reservationStorage->findReservationById($reservationIdMongo);
        if (!$tempReservation) {
            error_log("Impossible de trouver la réservation temporaire pour l'ID: " . $reservationIdMongo);
            return null;
        }

        // Utilise le service pour persister la réservation
        return $this->persistenceService->persistPaidReservation($paymentData, $tempReservation);
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