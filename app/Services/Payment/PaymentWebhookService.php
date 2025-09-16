<?php

namespace app\Services\Payment;

use app\Services\Reservation\ReservationprocessAndPersistService;
use app\Utils\HelloAssoDebugJson;
use DateMalformedStringException;
use DateTime;

/**
 * Gère la logique de traitement des webhooks/notifications de paiement entrant.
 */
class PaymentWebhookService
{
    private ReservationprocessAndPersistService $reservationProcessService;
    private PaymentRecordService $paymentRecordService;

    public function __construct()
    {
        $this->reservationProcessService = new ReservationprocessAndPersistService();
        $this->paymentRecordService = new PaymentRecordService();
    }

    /**
     * Point d'entrée principal pour le traitement d'un webhook.
     *
     * @param object $payload Les données décodées du webhook.
     * @return void
     * @throws DateMalformedStringException
     */
    public function handleWebhook(object $payload): void
    {
        // Vérification de base de la notification
        if (
            !isset($payload->eventType) || $payload->eventType !== 'Order' ||
            !isset($payload->data->items[0]->state) || $payload->data->items[0]->state !== 'Processed'
        ) {
            error_log("Webhook HelloAsso ignoré : événement non pertinent ou paiement non traité.");
            return;
        }

        $context = $payload->metadata->context ?? 'new_reservation'; // Contexte par défaut
        $orderData = $payload->data;

        if ($context === 'new_reservation') {
            $reservationIdMongo = $payload->metadata->reservationId ?? null;
            if ($reservationIdMongo) {
                $this->reservationProcessService->processAndPersistReservation($orderData, $reservationIdMongo);
            } else {
                error_log("Webhook 'new_reservation' reçu sans reservationId (Mongo) dans les metadata.");
            }
        } elseif ($context === 'balance_payment') {
            $reservationIdSql = $payload->metadata->reservationId ?? null;
            if ($reservationIdSql) {
                $this->reservationProcessService->processAndPersistReservationComplement($orderData, $reservationIdSql, $context);
            } else {
                error_log("Webhook 'balance_payment' reçu sans reservationId (SQL) dans les metadata.");
            }
        }
    }

}