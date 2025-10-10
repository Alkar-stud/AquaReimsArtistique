<?php

namespace app\Services\Payment;

use app\Services\Reservation\ReservationprocessAndPersistService;


/**
 * Gère la logique de traitement des webhooks/notifications de paiement entrant.
 */
class PaymentWebhookService
{
    private ReservationprocessAndPersistService $reservationProcessService;

    public function __construct(
        ReservationprocessAndPersistService $reservationProcessService,
    )
    {
        $this->reservationProcessService = $reservationProcessService;
    }

    /**
     * Point d'entrée principal pour le traitement d'un webhook.
     *
     * @param object $payload Les données décodées du webhook.
     * @return void
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
            $primaryTempId = $payload->metadata->primary_id ?? null;
            if ($primaryTempId) {
                $this->reservationProcessService->processAndPersistReservation($orderData, $primaryTempId, $context);
            } else {
                error_log("Webhook 'new_reservation' reçu sans primaryId (NoSQL) dans les metadata.");
            }
        } elseif ($context === 'balance_payment') {
            $reservationIdSql = $payload->metadata->primaryId ?? null;
            if ($reservationIdSql) {
                //$this->reservationProcessService->processAndPersistReservationComplement($orderData, $reservationIdSql, $context);
            } else {
                error_log("Webhook 'balance_payment' reçu sans primaryId (SQL) dans les metadata.");
            }
        }
    }

}