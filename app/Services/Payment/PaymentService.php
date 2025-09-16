<?php

namespace app\Services\Payment;

use app\DTO\HelloAssoCartDTO;
use app\Services\HelloAssoService;
use Exception;

/**
 * Service pour gérer la création d'intentions de paiement.
 */
class PaymentService
{
    private HelloAssoService $helloAssoService;

    public function __construct()
    {
        $this->helloAssoService = new HelloAssoService();
    }

    /**
     * Crée une intention de paiement auprès du fournisseur (HelloAsso).
     *
     * @param HelloAssoCartDTO $cartDTO L'objet contenant toutes les informations du panier.
     *
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

            $errorMessage = $checkout->message ?? 'Réponse invalide de la plateforme de paiement.';
            return ['success' => false, 'error' => $errorMessage, 'details' => $checkout];

        } catch (Exception $e) {
            error_log('Erreur lors de la création de l\'intention de paiement: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur de communication avec la plateforme de paiement.'];
        }
    }
}