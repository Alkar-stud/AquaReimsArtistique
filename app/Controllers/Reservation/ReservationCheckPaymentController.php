<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\ReservationPayment;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Payment\PaymentWebhookService;
use app\Utils\HelloAssoDebugJson;

class ReservationCheckPaymentController extends AbstractController
{
    private PaymentWebhookService $paymentWebhookService;
    private ReservationRepository $reservationRepository;

    public function __construct(
        PaymentWebhookService $paymentWebhookService,
        ReservationRepository $reservationRepository,
    )
    {
        parent::__construct(true); // route publique
        $this->paymentWebhookService = $paymentWebhookService;
        $this->reservationRepository = $reservationRepository;
    }

    #[Route('/reservation/paymentCallback', name: 'app_reservation_paymentCallback', methods: ['POST'])]
    public function paymentCallback(): void
    {
        //Si ce n'est pas du json, on refuse
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($contentType && stripos($contentType, 'application/json') === false) {
            http_response_code(400);
            echo 'Ce n\'est pas du JSON';
            return;
        }

        // Récupération du payload
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



    /**
     * Pour vérifier si le callback a bien enregistré le paiement envoyé par HelloAsso
     * @return void
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
        $paymentsRepository = new ReservationPaymentRepository();
        $payment = $paymentsRepository->findByCheckoutId((int)$checkoutIntentId);

        // Renvoyer le statut au front
        if ($payment && in_array($payment->getStatusPayment(), ['Authorized', 'Processed'])) {
            $this->handleSuccessfulCheck($payment);
        } else {
            // Le paiement n'est pas encore trouvé ou n'a pas le bon statut, on indique au front de patienter.
            $this->json(['success' => false, 'status' => 'pending']);
        }
    }

    #[Route('/reservation/checkPaymentState', name: 'app_reservation_check_payment_state', methods: ['POST'])]
    public function checkPaymentState(): void
    {
        $paymentId = $_GET['id'] ?? 0;
        if (!$paymentId) {
            $this->json(['success' => false, 'error' => 'paymentId manquant']);
        }

        $result = $this->paymentWebhookService->handlePaymentState($paymentId);
        if (!$result['success']) {
            $this->json($result);
        }

        $this->json(['success' => true, 'state' => $result['state'], 'totalAmountPaid' => $result['reservation']->getTotalAmountPaid()]);
    }


    #[Route('/reservation/checkPaymentRefund', name: 'app_reservation_check_payment_refund', methods: ['POST'])]
    public function checkPaymentRefund(): void
    {
        $paymentId = $_GET['id'] ?? 0;
        if (!$paymentId) {
            $this->json(['success' => false, 'error' => 'paymentId manquant']);
        }

        //$this->paymentWebhookService->handlePaymentRefund($paymentId);


        $this->json(['success' => true]);
    }


    /**
     * Gère la réponse JSON pour une vérification de paiement réussie.
     * @param ReservationPayment $payment
     * @return void
     */
    private function handleSuccessfulCheck(ReservationPayment $payment): void
    {
        $reservation = $this->reservationRepository->findById($payment->getReservation());

        if ($reservation) {
            unset($_SESSION['reservation'][session_id()]);
            $this->json(['success' => true, 'token' => $reservation->getToken()]);
        } else {
            // Cas peu probable où le paiement existe, mais pas la réservation associée
            $this->json(['success' => false, 'error' => 'Paiement trouvé mais réservation introuvable.']);
        }
    }


}