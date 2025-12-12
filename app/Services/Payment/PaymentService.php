<?php

namespace app\Services\Payment;

use app\DTO\HelloAssoCartDTO;
use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationDataPersist;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Reservation\ReservationTempWriter;
use app\Utils\BuildLink;
use Exception;

class PaymentService
{
    private HelloAssoCartDTO $helloAssoCartDTO;
    private HelloAssoService $helloAssoService;
    private ReservationTempWriter $reservationWriter;
    private ReservationSessionService $reservationSessionService;
    private ReservationDataPersist $reservationDataPersist;
    private ReservationRepository $reservationRepository;
    private BuildLink $buildLink;

    public function __construct(
        HelloAssoCartDTO          $helloAssoCartDTO,
        HelloAssoService          $helloAssoService,
        ReservationTempWriter     $reservationWriter,
        ReservationSessionService $reservationSessionService,
        ReservationDataPersist    $reservationDataPersist,
        ReservationRepository     $reservationRepository,
        BuildLink                 $buildLink,
    )
    {
        $this->helloAssoCartDTO = $helloAssoCartDTO;
        $this->helloAssoService = $helloAssoService;
        $this->reservationWriter = $reservationWriter;
        $this->reservationSessionService = $reservationSessionService;
        $this->reservationDataPersist = $reservationDataPersist;
        $this->reservationRepository = $reservationRepository;
        $this->buildLink = $buildLink;
    }

    /**
     * On organise le paiement, préparation, demande de token, puis de checkoutId
     *
     * @param array $reservationTemp
     * @param array $session
     * @return array
     */
    public function handlePayment(array $reservationTemp, array $session): array
    {
        //Si le montant total est égal à 0, on redirige directement pour l'enregistrement définitif
        if($session['totals']['total_amount'] == 0) {
            if ($_SERVER['REQUEST_URI'] == '/reservation/payment') { $context = 'new_reservation'; }
            elseif ($_SERVER['REQUEST_URI'] == '/modifData') { $context = 'balance_payment'; }
            else { $context = 'other'; }
            $this->reservationDataPersist->persistConfirmReservation(null, $reservationTemp['reservation'], $context, true);
            $finalReservation = $this->reservationRepository->findByField('reservation_temp_id', $reservationTemp['reservation']->getId());

            return ['success' => true, 'token' => $finalReservation->getToken()];
        }
        $reservationTemp['totals'] = $session['totals'];
        
        //On prépare le panier pour HelloAsso avec le DTO
        $checkoutCart = $this->prepareCheckOutData($reservationTemp);

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
            $result = [
                'success' => true,
                'redirectUrl' => $session['redirectUrl'],
                'checkoutIntentId' => $session['checkoutIntentId']
            ];
        } else {
            //On demande un checkoutId avec l'url à donner à la vue
            // On retourne le résultat (succès ou échec) au contrôleur.
            $result = $this->createPaymentIntent($checkoutCart);
        }

        //On sauvegarde le checkoutIntentId
        if ($result['success']) {
            $this->reservationWriter->updateReservationByPrimaryId($reservationTemp['reservation']->getId(), ['checkout_intent_id' => $result['checkoutIntentId']]);
            //Puis, on ajoute à la session pour les retrouver après
            $this->reservationSessionService->setReservationSession('checkoutIntentId', $result['checkoutIntentId']);
            $this->reservationSessionService->setReservationSession('redirectUrl', $result['redirectUrl']);
            $this->reservationSessionService->setReservationSession('paymentIntentTimestamp', time());
        }

        return $result;
    }

    /**
     * Reçoit $reservation (ce qu'il y a comme sauvegarde temporaire en BDD)
     *
     * @param array $reservation
     * @return HelloAssoCartDTO
     */
    public function prepareCheckOutData(array $reservation): HelloAssoCartDTO
    {
        // On récupère le nom de l'événement pour un affichage plus clair sur la page de paiement
        $event = $reservation['reservation']->getEventObject();
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
            'firstName' => $reservation['reservation']->getFirstName(),
            'lastName'  => $reservation['reservation']->getName(),
            'email'     => $reservation['reservation']->getEmail(),
            'country'   => 'FRA'
        ]);

        // Champ très important pour la réconciliation : on stocke notre ID interne.
        // HelloAsso nous le renverra lors de la confirmation du paiement.
        $this->helloAssoCartDTO->setMetaData([
            'primary_id' => $reservation['reservation']->getId(),
            'context'    => 'new_reservation'
        ]);

        return $this->helloAssoCartDTO;
    }

    /**
     * Prépare le DTO HelloAsso pour un paiement de solde sur une réservation existante.
     *
     * @param Reservation $reservation L'objet Reservation persistant.
     * @param int $amountToPay Le montant total à payer en centimes (incluant le don).
     * @param bool $containsDonation Indique si le montant inclut un don.
     * @return HelloAssoCartDTO
     */
    public function prepareCheckOutDataForBalance(Reservation $reservation, int $amountToPay, bool $containsDonation): HelloAssoCartDTO
    {

        $token = $reservation->getToken();
        $baseUrl = $this->buildLink->buildBasicLink('/modifData?token=' . $token);

        // URLs de redirection spécifiques à la page de modification
        $successUrl = $baseUrl . '&status=success';
        $errorUrl = $baseUrl . '&status=error';
        $returnUrl = $baseUrl . '&status=return';

        $itemName = "Règlement du solde de la réservation #" . $reservation->getId();
        if ($containsDonation) {
            $itemName .= " (incluant un don)";
        }

        // On crée une nouvelle instance du DTO pour ne pas interférer avec le flux de réservation initial
        $cartDTO = new HelloAssoCartDTO();
        $cartDTO->setTotalAmount($amountToPay);
        $cartDTO->setInitialAmount($amountToPay);
        $cartDTO->setItemName($itemName);
        $cartDTO->setBackUrl($returnUrl);
        $cartDTO->setErrorUrl($errorUrl);
        $cartDTO->setReturnUrl($successUrl);
        $cartDTO->setContainsDonation($containsDonation);

        $cartDTO->setPayer([
            'firstName' => $reservation->getFirstName(),
            'lastName'  => $reservation->getName(),
            'email'     => $reservation->getEmail(),
            'country'   => 'FRA'
        ]);

        // Métadonnées cruciales pour le webhook
        $cartDTO->setMetaData([
            'context'   => 'balance_payment',
            'primaryId' => $reservation->getId() // ID SQL de la réservation
        ]);

        return $cartDTO;
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