<?php

namespace app\Services\Payment;

use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationProcessAndPersistService;


/**
 * Gère la logique de traitement des webhooks/notifications de paiement entrant.
 */
class PaymentWebhookService
{
    private ReservationProcessAndPersistService $reservationProcessService;
    private HelloAssoService $helloAssoService;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private ReservationRepository $reservationRepository;
    private PaymentRecordService $paymentRecordService;

    public function __construct(
        ReservationProcessAndPersistService $reservationProcessService,
        HelloAssoService $helloAssoService,
        ReservationPaymentRepository $reservationPaymentRepository,
        ReservationRepository $reservationRepository,
        PaymentRecordService $paymentRecordService,
    )
    {
        $this->reservationProcessService = $reservationProcessService;
        $this->helloAssoService = $helloAssoService;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->reservationRepository = $reservationRepository;
        $this->paymentRecordService = $paymentRecordService;
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
                $this->reservationProcessService->processAndPersistReservationComplement($orderData, $reservationIdSql, $context);
            } else {
                error_log("Webhook 'balance_payment' reçu sans primaryId (SQL) dans les metadata.");
            }
        }
    }

    /**
     * Pour gérer la vérification d'un paiement et on met à jour si besoin
     *
     * @param int $paymentId
     * @return array
     */
    public function handlePaymentState(int $paymentId): array
    {
        //On va chercher la réponse de HelloAsso
        $result = $this->helloAssoService->checkPaymentState($paymentId);

        $payer = $result->payer; // tableau pour le nom et le mail si besoin
        $state = $result->state; //string de l'état du paiement
        $refundOperations = $result->refundOperations; //Tableau d'index des opérations de remboursement de ce paiement [id, amount, amountTip, status, meta = [createdAt, updatedAt

        //On met à jour l'état en bdd le paiement initial
        $payment = $this->reservationPaymentRepository->findByPaymentId($paymentId);
        $payment->setStatusPayment($state);
        $this->reservationPaymentRepository->update($payment);

        //On récupère la réservation
        $reservation = $this->reservationRepository->findById($payment->getReservation());

        if ($state == 'Refunded') {
            //On insère le remboursement dans les paiements
            $refundOperations = $this->paymentRecordService->createRefundPaymentRecord($paymentId, $result);

            //On met à jour la réservation avec le nouveau montant
            $reservation->setTotalAmountPaid($reservation->getTotalAmountPaid() - $refundOperations->getAmountPaid());
            $this->reservationRepository->update($reservation);
        }
        //Et on retourne le résultat au front
        return ['payer' => $payer, 'state' => $state, 'refundOperations' => $refundOperations, 'reservation' => $reservation];
    }

    /**
     * Pour gérer le remboursement d'un paiement
     *
     * @param int $paymentId
     * @return array
     */
    public function handlePaymentRefund(int $paymentId): array
    {

        return ['success' => false];
    }

}