<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\HelloAssoCartDTO;
use app\Models\Reservation\Reservations;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\HelloAssoService;
use app\Services\Payment\PaymentService;
use app\Services\Payment\PaymentWebhookService;
use app\Services\Reservation\ReservationCartService;
use app\Repository\TarifRepository;
use app\Services\Reservation\ReservationService;
use app\Services\Reservation\ReservationUpdateService;
use app\Services\Reservation\ReservationTokenService;
use DateMalformedStringException;

class ReservationModifDataController extends AbstractController
{
    private ReservationsRepository $reservationsRepository;
    private EventsRepository $eventsRepository;
    private EventSessionRepository $eventSessionRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsComplementsRepository $reservationsComplementsRepository;
    private TarifRepository $tarifsRepository;
    private ReservationCartService $reservationCartService;
    private ReservationTokenService $reservationTokenService;
    private PaymentService $paymentService;
    private HelloAssoService $helloAssoService;
    private ReservationService $reservationService;
    private ReservationUpdateService $reservationUpdateService;


    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationsRepository = new ReservationsRepository();
        $this->eventsRepository = new EventsRepository();
        $this->eventSessionRepository = new EventSessionRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->reservationsComplementsRepository = new ReservationsComplementsRepository();
        $this->tarifsRepository = new TarifRepository();
        $this->reservationCartService = new ReservationCartService();
        $this->reservationTokenService = new ReservationTokenService();
        $this->reservationService = new ReservationService();
        $this->paymentService = new PaymentService();
        $this->helloAssoService = new HelloAssoService();
        $this->reservationUpdateService = new ReservationUpdateService();
    }

    /**
     * Pour afficher le contenu de la réservation
     *
     * @throws DateMalformedStringException
     */
    #[Route('/modifData', name: 'app_reservation_modif_data')]
    public function modifData(): void
    {
        // On récupère et valide le token depuis l'URL
        $token = $_GET['token'] ?? null;
        if (!$token || !ctype_alnum($token)) {
            http_response_code(404);
            // Idéalement, afficher une page 404 générique
            echo "Page non trouvée.";
            exit;
        }

        // On récupère la réservation par son token
        $reservation = $this->reservationsRepository->findByToken($token);
        if (!$reservation) {
            http_response_code(404);
            echo "Réservation non trouvée.";
            exit;
        }

        //On récupère l'événement et la session associés à la réservation
        $event = $this->eventsRepository->findById($reservation->getEvent());
        $session = $this->eventSessionRepository->findById($reservation->getEventSession());

        // On récupère les détails et compléments de la réservation
        $reservationDetails = $this->reservationsDetailsRepository->findByReservation($reservation->getId());
        $reservationComplements = $this->reservationsComplementsRepository->findByReservation($reservation->getId());

        // On récupère tous les tarifs pour pouvoir afficher les libellés
        $tarifs = $this->tarifsRepository->findByEventId($event->getId());
        $tarifsByIdObj = [];
        foreach ($tarifs as $t) {
            $tarifsByIdObj[$t->getId()] = $t;
        }

        // Le service attend un tableau de données, pas un tableau d'objets.
        // On convertit les objets ReservationDetails en tableaux.
        $detailsAsArray = array_map(function ($detail) {
            return ['tarif_id' => $detail->getTarif()];
        }, $reservationDetails);
        // Calculer les quantités correctes de tarifs (packs)
        $tarifQuantities = $this->reservationCartService->getTarifQuantitiesFromDetails($detailsAsArray, $tarifs);

        // Préparer la liste des compléments disponibles à l'achat
        $userComplementTarifIds = array_map(fn($c) => $c->getTarif(), $reservationComplements);
        $allComplementTarifs = $this->tarifsRepository->findByEventId($event->getId());

        // Filtrer les compléments disponibles
        $availableComplements = array_filter($allComplementTarifs, function($tarif) use ($userComplementTarifIds) {
            return !in_array($tarif->getId(), $userComplementTarifIds);
        });

        // Garder uniquement ceux dont getNbPlace() n'est pas NULL
        $availableComplements = array_filter($availableComplements, function($tarif) {
            return $tarif->getNbPlace() === null;
        });

        $this->render('reservation/modif_data', [
            'reservation' => $reservation,
            'session' => $session,
            'event' => $event,
            'reservationDetails' => $reservationDetails,
            'reservationComplements' => $reservationComplements,
            'availableComplements' => $availableComplements,
            'tarifQuantities' => $tarifQuantities,
            'tarifsByIdObj' => $tarifsByIdObj,
            'reservationUuid' => $reservation->getUuid(),
        ], 'Récapitulatif de la réservation');
    }

    /**
     * Pour récupérer les infos pour mettre à jour un champ
     *
     * @throws DateMalformedStringException
     */
    #[Route('/modifData/update', name: 'app_reservation_update', methods: ['POST'])]
    public function update(): void
    {
        //On récupère toutes les données susceptibles d'être envoyées
        $data = json_decode(file_get_contents('php://input'), true);
        $typeField = $data['typeField'];
        $token = $data['token'] ?? null;
        $fieldId = $data['id'] ?? null;
        $tarifId = $data['tarifId'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $action = $data['action'] ?? null;

        //Si pas de token
        if (!$token || !ctype_alnum($token)) {
            $this->json(['success' => false, 'message' => 'Modification non autorisée.']);
            return;
        }
        //On récupère la réservation
        $reservation = $this->reservationsRepository->findByToken($token);

        // On vérifie que le token est existant dans la table et toujours valide
        if (!$this->reservationTokenService->checkReservationToken($reservation, $token)) {
            $this->json(['success' => false, 'message' => 'La modification n\'est plus autorisée.']);
        }

        if ($typeField == 'contact') {
            //Pour les infos de contact de la réservation
            $return = $this->reservationUpdateService->updateContactField($reservation->getId(), $field, $value);

        } elseif ($typeField == 'detail') {
            //Pour les infos des participants
            $return = $this->reservationUpdateService->updateDetailField((int)$fieldId, $field, $value);

        } elseif ($typeField == 'complement') {
            //Pour les infos des compléments
            if ($fieldId) { // Mise à jour d'un complément existant
                $return = $this->reservationUpdateService->updateComplementQuantity($reservation->getId(), (int)$fieldId, $action);
            } elseif ($tarifId) { // Ajout d'un nouveau complément
                $return = $this->reservationUpdateService->addComplement($reservation->getId(), (int)$tarifId);
            } else {
                $return = ['success' => false, 'message' => 'Action sur complément non valide.'];
            }
        } elseif ($typeField == 'cancel') {
            // La logique d'annulation est plus complexe, on la laisse ici pour l'instant
            // ou on la déplace dans le service si elle devient réutilisable.
            $return = $this->cancel($reservation->getId(), $reservation->getToken());
        } else {
            $return = ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }

        $this->json($return);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function updateComplements(Reservations $reservation, ?string $tarif_access_code, int $fieldId, ?string $action): array
    {
        $complement = $this->reservationsComplementsRepository->findById($fieldId);

        $qty = $complement->getQty();
        if ($action == 'plus') { $qty ++; }
        else { $qty--; }
        //Si $qty <= 0 on supprime la ligne
        if ($qty <= 0) {
            $success = $this->reservationsComplementsRepository->delete($complement->getId());
            $message = 'Complément supprimé.';
        } else {
            $success = $this->reservationsComplementsRepository->updateQuantity($complement->getId(), $tarif_access_code, $qty);
            $message = 'Quantité mise à jour.';
        }

        if ($success) {
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }

    }

    public function cancel(int $reservationId, string $token): array
    {
        $return = $this->reservationsRepository->cancelByToken($token);
        if ($return) {
            $return = $this->reservationsDetailsRepository->cancelByReservation($reservationId);
            if ($return) {
                return ['success' => true, 'message' => 'Réservation annulée.'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'annulation.'];
            }
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'annulation.'];
        }
    }

    /**
     * Crée une intention de paiement pour le solde restant d'une réservation.
     * @throws DateMalformedStringException
     */
    #[Route('/modifData/createPayment', name: 'app_reservation_create_payment_balance', methods: ['POST'])]
    public function createPaymentForBalance(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? null;
        $amountToPay = (int)($data['amountToPay'] ?? 0); // Montant total à régler en centimes
        $containsDonation = (bool)($data['containsDonation'] ?? false);

        if (!$token || !ctype_alnum($token)) {
            $this->json(['success' => false, 'message' => 'Token invalide.']);
            return;
        }

        $reservation = $this->reservationsRepository->findByToken($token);
        if (!$this->reservationTokenService->checkReservationToken($reservation, $token)) {
            $this->json(['success' => false, 'message' => 'La modification n\'est plus autorisée.']);
            return;
        }

        if ($amountToPay <= 0) {
            $this->json(['success' => false, 'message' => 'Le montant à payer doit être positif.']);
            return;
        }

        // Préparation des données pour le PaymentService
        $event = $this->eventsRepository->findById($reservation->getEvent());
        $itemName = 'Solde réservation ' . $reservation->getId() . ' - ' . $event->getLibelle();
        $payerInfo = [
            'firstName' => $reservation->getPrenom(),
            'lastName'  => $reservation->getNom(),
            'email'     => $reservation->getEmail()
        ];

        // Construction des URLs de retour avec le token
        $baseUrl = "https://" . $_SERVER['HTTP_HOST'];
        $returnParams = '?token=' . $token;

        // Création et hydratation du DTO
        $cartDTO = new HelloAssoCartDTO();
        $cartDTO->setTotalAmount($amountToPay);
        $cartDTO->setItemName($itemName);
        $cartDTO->setPayer($payerInfo);
        $cartDTO->setMetaData(['reservationId' => $reservation->getId(), 'context' => 'balance_payment']);
        $cartDTO->setBackUrl($baseUrl . '/modifData' . $returnParams);
        $cartDTO->setErrorUrl($baseUrl . '/modifData' . $returnParams . '&status=error');
        $cartDTO->setReturnUrl($baseUrl . '/modifData' . $returnParams . '&status=success');
        $cartDTO->setContainsDonation($containsDonation);

        // Appel du PaymentService
        $paymentResult = $this->paymentService->createPaymentIntent($cartDTO);

        if ($paymentResult['success']) {
            // On pourrait stocker le checkoutIntentId sur la réservation si nécessaire
            // $this->reservationsRepository->updateSingleField($reservation->getId(), 'checkout_intent_id', $paymentResult['checkoutIntentId']);
            $this->json(['success' => true, 'redirectUrl' => $paymentResult['redirectUrl']]);
        } else {
            error_log("Erreur de création de paiement (solde) pour reservationId {$reservation->getId()}: " . ($paymentResult['error'] ?? 'Inconnue'));
            $this->json(['success' => false, 'message' => 'Erreur lors de la communication avec le service de paiement.', 'details' => $paymentResult['details'] ?? null]);
        }
    }

    /**
     * Force la vérification d'un paiement de solde directement auprès de HelloAsso.
     * @throws DateMalformedStringException
     */
    #[Route('/modifData/forceCheckPayment', name: 'app_reservation_force_check_payment_balance', methods: ['POST'])]
    public function forceCheckPaymentForBalance(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $checkoutIntentId = $data['checkoutIntentId'] ?? null;

        if (!$checkoutIntentId) {
            $this->json(['success' => false, 'message' => 'ID de transaction manquant.']);
            return;
        }

        // Étape 1 : Vérifier si le webhook n'a pas déjà traité ce paiement entre-temps
        $payment = (new ReservationPaymentsRepository())->findByCheckoutId($checkoutIntentId);
        if ($payment) {
            $this->json(['success' => true]); // Le paiement est déjà là, tout va bien.
            return;
        }

        // Étape 2 : Interroger directement HelloAsso
        $accessToken = $this->helloAssoService->getToken();
        $paymentInfo = $this->helloAssoService->checkPayment($accessToken, $checkoutIntentId);

        // Étape 3 : Valider la réponse et traiter le paiement
        if (
            isset($paymentInfo->order, $paymentInfo->metadata->context) &&
            $paymentInfo->metadata->context === 'balance_payment' &&
            $paymentInfo->order->items[0]->state === 'Processed'
        ) {
            // La logique est la même que dans le webhook, on pourrait la factoriser,
            // mais pour l'instant, on la duplique pour la clarté.
            $reservationId = $paymentInfo->metadata->reservationId;
            $orderData = $paymentInfo->order;
            $orderData->checkoutIntentId = $paymentInfo->id; // Important : l'ID est à la racine

            // On simule l'action du webhook
            $webhookService = new PaymentWebhookService();
            $webhookService->handleWebhook($paymentInfo, json_encode($paymentInfo));

            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'message' => 'Le paiement n\'est pas encore confirmé par la plateforme.']);
        }
    }


}