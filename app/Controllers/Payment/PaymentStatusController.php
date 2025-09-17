<?php

namespace app\Controllers\Payment;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Payment\PaymentWebhookService;
use app\Utils\HelloAssoDebugJson;
use DateMalformedStringException;

class PaymentStatusController extends AbstractController
{
    private PaymentWebhookService $paymentWebhookService;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->paymentWebhookService = new PaymentWebhookService();
    }

    /**
     * Route pour recevoir la notification de paiement de la part de HelloAsso.
     * Cette URL est déclarée dans l'interface API de HelloAsso
     *
     * @throws DateMalformedStringException
     */
    #[Route('/reservation/paymentCallback', name: 'app_reservation_paymentCallback', methods: ['POST'])]
    public function paymentCallback(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw);

        if (!$payload) {
            http_response_code(400); // Bad Request
            echo 'Invalid payload';
            return;
        }

        // Si HELLOASSO_DEBUG est à true, on enregistre le payload pour le débogage
        if (isset($_ENV['HELLOASSO_DEBUG']) && $_ENV['HELLOASSO_DEBUG'] === 'true') {
            $debugLogger = new HelloAssoDebugJson();
            $debugLogger->save($payload, $raw, 'webhook_callback');
        }

        // Déléguer le traitement au service dédié
        $this->paymentWebhookService->handleWebhook($payload);

        // Toujours répondre 200 à HelloAsso pour qu'il ne renvoie pas la notification.
        http_response_code(200);
        echo 'OK';
    }



}