<?php

namespace app\Services\Payment;

use app\DTO\HelloAssoCartDTO;
use app\Services\HelloAssoService;

/**
 * Service pour gérer la création d'intentions de paiement.
 * Il est agnostique du contexte (réservation, boutique, etc.).
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
     * @param int $totalAmount Le montant total en centimes.
     * @param array $payerInfo Tableau contenant ['firstName', 'lastName', 'email'].
     * @param string $itemName Le nom de l'article/panier pour le paiement.
     * @param array $metaData Tableau de métadonnées à associer au paiement (ex: ['reservationId' => '...']).
     * @param string $backUrl URL de retour en cas d'annulation.
     * @param string $errorUrl URL en cas d'erreur de paiement.
     * @param string $returnUrl URL de succès après paiement.
     *
     * @return array ['success' => bool, 'redirectUrl' => ?string, 'checkoutIntentId' => ?string, 'error' => ?string]
     */
    public function createPaymentIntent(
        int $totalAmount,
        array $payerInfo,
        string $itemName,
        array $metaData,
        string $backUrl,
        string $errorUrl,
        string $returnUrl
    ): array {
        if ($totalAmount <= 0) {
            return ['success' => false, 'error' => 'Le montant doit être positif.'];
        }

        $cartDTO = new HelloAssoCartDTO();
        $cartDTO->setTotalAmount($totalAmount);
        $cartDTO->setInitialAmount($totalAmount);
        $cartDTO->setItemName($itemName);
        $cartDTO->setPayer([
            'firstName' => $payerInfo['firstName'],
            'lastName'  => $payerInfo['lastName'],
            'email'     => $payerInfo['email'],
            'country'   => 'FRA'
        ]);
        $cartDTO->setMetaData($metaData);
        $cartDTO->setBackUrl($backUrl);
        $cartDTO->setErrorUrl($errorUrl);
        $cartDTO->setReturnUrl($returnUrl);

        try {
            $accessToken = $this->helloAssoService->GetToken();
            $checkout = $this->helloAssoService->PostCheckoutIntents($accessToken, $cartDTO);

            if (isset($checkout->redirectUrl) && isset($checkout->id)) {
                return ['success' => true, 'redirectUrl' => $checkout->redirectUrl, 'checkoutIntentId' => $checkout->id];
            }

            $errorMessage = $checkout->message ?? 'Réponse invalide de la plateforme de paiement.';
            return ['success' => false, 'error' => $errorMessage, 'details' => $checkout];

        } catch (\Exception $e) {
            error_log('Erreur lors de la création de l\'intention de paiement: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur de communication avec la plateforme de paiement.'];
        }
    }
}