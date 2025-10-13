<?php

namespace app\Services\Payment;

use app\DTO\HelloAssoCartDTO;
use app\Repository\Event\EventRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationDataPersist;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Reservation\ReservationTempWriter;
use app\Utils\BuildLink;
use Exception;

class PaymentService
{
    private HelloAssoCartDTO $helloAssoCartDTO;
    private EventRepository $eventRepository;
    private HelloAssoService $helloAssoService;
    private ReservationTempWriter $reservationWriter;
    private ReservationSessionService $reservationSessionService;
    private ReservationDataPersist $reservationDataPersist;
    private ReservationRepository $reservationRepository;

    public function __construct(
        HelloAssoCartDTO          $helloAssoCartDTO,
        EventRepository           $eventRepository,
        HelloAssoService          $helloAssoService,
        ReservationTempWriter     $reservationWriter,
        ReservationSessionService $reservationSessionService,
        ReservationDataPersist    $reservationDataPersist,
        ReservationRepository     $reservationRepository,
    )
    {
        $this->helloAssoCartDTO = $helloAssoCartDTO;
        $this->eventRepository = $eventRepository;
        $this->helloAssoService = $helloAssoService;
        $this->reservationWriter = $reservationWriter;
        $this->reservationSessionService = $reservationSessionService;
        $this->reservationDataPersist = $reservationDataPersist;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * On organise le paiement, préparation, demande de token, puis de checkoutId
     *
     * @param array $reservation
     * @param array $session
     * @return array
     */
    public function handlePayment(array $reservation, array $session): array
    {
        //Si le montant total est égal à 0, on redirige directement pour l'enregistrement définitif
        if($reservation['totals']['total_amount'] == 0) {
            if ($_SERVER['REQUEST_URI'] == '/reservation/payment') { $context = 'new_reservation'; }
            elseif ($_SERVER['REQUEST_URI'] == '/modifData') { $context = 'balance_payment'; }
            else { $context = 'other'; }
            $this->reservationDataPersist->persistConfirmReservation((object)$reservation, $reservation, $context, true);
            $finalReservation = $this->reservationRepository->findByField('reservation_temp_id', $session['primary_id']);


            return ['success' => true, 'token' => $finalReservation->getToken()];
        }

        //On prépare le panier pour HelloAsso avec le DTO
        $checkoutCart = $this->prepareCheckOutData($reservation);

        $now = time();
        $intentTimestamp = $session['paymentIntentTimestamp'] ?? 0;
        // L'URL est valide pendant 15 minutes, on la régénère au bout de 10 minutes par sécurité.
        $isIntentValid = ($now - $intentTimestamp) < 600; // 10 minutes = 600 seconds

        // Si un checkoutIntentId et une URL de redirection existent déjà en session ET sont valides, on les réutilise.
        if (
            !empty($session['checkoutIntentId']) &&
            !empty($session['redirectUrl']) &&
            $isIntentValid
        ) {
            $result = ['success' => true, 'redirectUrl' => $session['redirectUrl'], 'checkoutIntentId' => $session['checkoutIntentId']];
        } else {
            //On demande un checkoutId avec l'url à donner à la vue
            // On retourne le résultat (succès ou échec) au contrôleur.
            $result = $this->createPaymentIntent($checkoutCart);
        }

        //On sauvegarde le checkoutIntentId
        if ($result['success']) {
            $this->reservationWriter->updateReservationByPrimaryId($reservation['primary_id'], ['checkout_intent_id' => $result['checkoutIntentId']]);
            //Puis, on ajoute à la session pour les retrouver après
            $this->reservationSessionService->setReservationSession('checkoutIntentId', $result['checkoutIntentId']);
            $this->reservationSessionService->setReservationSession('redirectUrl', $result['redirectUrl']);
            $this->reservationSessionService->setReservationSession('paymentIntentTimestamp', time());
        }

        return $result;
    }

    /**
     * Reçoit $reservation (ce qu'il y a comme sauvegarde temporaire en noSQL)
     *
     * @param array $reservation
     * @return HelloAssoCartDTO
     */
    public function prepareCheckOutData(array $reservation): HelloAssoCartDTO
    {
        // On récupère le nom de l'événement pour un affichage plus clair sur la page de paiement
        $event = $this->eventRepository->findById($reservation['event_id']);
        $eventName = $event ? $event->getName() : 'Événement';

        // On récupère l'URL de base de l'application
        $buildLink = new BuildLink();
        $baseUrl = $buildLink->buildBasicLink();

        // On remplit le DTO avec les informations de la réservation
        $this->helloAssoCartDTO->setTotalAmount((int)$reservation['totals']['total_amount']);
        $this->helloAssoCartDTO->setInitialAmount((int)$reservation['totals']['total_amount']); // Identique pour un paiement unique
        $this->helloAssoCartDTO->setItemName("Réservation pour {$eventName}");

        // URLs de redirection pour le processus de paiement
        $this->helloAssoCartDTO->setBackUrl($baseUrl . '/reservation/confirmation'); // URL pour revenir au panier
        $this->helloAssoCartDTO->setErrorUrl($baseUrl . '/reservation/error-payment'); // URL en cas d'échec
        $this->helloAssoCartDTO->setReturnUrl($baseUrl . '/reservation/success-payment'); // URL après un paiement réussi

        // Informations sur l'acheteur
        $this->helloAssoCartDTO->setPayer([
            'firstName' => $reservation['booker']['firstname'],
            'lastName'  => $reservation['booker']['name'],
            'email'     => $reservation['booker']['email'],
            'country'   => 'FRA'
        ]);

        // Champ très important pour la réconciliation : on stocke notre ID interne.
        // HelloAsso nous le renverra lors de la confirmation du paiement.
        $this->helloAssoCartDTO->setMetaData([
            'primary_id' => $reservation['primary_id'],
            'context'    => 'new_reservation'
        ]);

        return $this->helloAssoCartDTO;
    }


    /**
     * Crée une intention de paiement auprès du fournisseur (HelloAsso).
     *
     * @param HelloAssoCartDTO $cartDTO L'objet contenant toutes les informations du panier.
     * @return array ['success' => bool, 'redirectUrl' => ?string, 'checkoutIntentId' => ?string, 'error' => ?string]
     */
    public function createPaymentIntent(HelloAssoCartDTO $cartDTO): array
    {
        if ($cartDTO->getTotalAmount() <= 0) {
            return ['success' => false, 'error' => 'Le montant doit être positif.'];
        }

        // L'initialAmount doit être égal au totalAmount pour un paiement unique.
        // On s'assure que c'est bien le cas.
        if ($cartDTO->getInitialAmount() !== $cartDTO->getTotalAmount()) {
            $cartDTO->setInitialAmount($cartDTO->getTotalAmount());
        }

        try {
            $accessToken = $this->helloAssoService->GetToken();
            $checkout = $this->helloAssoService->PostCheckoutIntents($accessToken, $cartDTO);

            if (isset($checkout->redirectUrl) && isset($checkout->id)) {
                return ['success' => true, 'redirectUrl' => $checkout->redirectUrl, 'checkoutIntentId' => $checkout->id];
            }

            // On cherche un message d'erreur plus précis dans la réponse
            $specificError = $checkout->errors[0]->message ?? null;
            // On utilise le message générique comme solution de repli
            $genericError = $checkout->message ?? 'Réponse invalide de la plateforme de paiement.';

            return ['success' => false, 'error' => $specificError ?? $genericError, 'details' => $checkout];

        } catch (Exception $e) {
            error_log('Erreur lors de la création de l\'intention de paiement: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur de communication avec la plateforme de paiement.'];
        }
    }

}