<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Payment\PaymentRecordService;

class ReservationProcessAndPersistService
{
    private ReservationRepository $reservationRepository;
    private ReservationTempWriter $reservationTempWriter;
    private ReservationDataPersist $reservationDataPersist;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private PaymentRecordService $paymentRecordService;
    private MailPrepareService $mailPrepareService;
    private MailService $mailService;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationTempWriter $reservationTempWriter,
        ReservationDataPersist $reservationDataPersist,
        ReservationPaymentRepository $reservationPaymentRepository,
        PaymentRecordService $paymentRecordService,
        MailPrepareService $mailPrepareService,
        MailService $mailService,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->reservationTempWriter = $reservationTempWriter;
        $this->reservationDataPersist = $reservationDataPersist;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->paymentRecordService = $paymentRecordService;
        $this->mailPrepareService = $mailPrepareService;
        $this->mailService = $mailService;
    }

    /**
     * Logique centrale pour traiter et persister une réservation après un paiement réussi.
     *
     * @param object $paymentData Données de la commande/paiement.
     * @param string $primaryTempId ID de la réservation temporaire.
     * @param string $context
     * @return Reservation|null
     */
    public function processAndPersistReservation(object $paymentData, string $primaryTempId, string $context): ?Reservation
    {
        // Vérifie si cette réservation a déjà été persistée pour éviter les doublons
        $existingReservation = $this->reservationRepository->findByField('reservation_temp_id', $primaryTempId);
        if ($existingReservation) {
            error_log("Tentative de double traitement pour la réservation temporaire ID: " . $primaryTempId);
            return $existingReservation;
        }

        $tempReservation = $this->reservationTempWriter->findReservationById($primaryTempId);
        if (!$tempReservation) {
            error_log("Impossible de trouver la réservation temporaire pour l'ID: " . $primaryTempId);
            return null;
        }

        // Utilise le service pour persister la réservation
        return $this->reservationDataPersist->persistConfirmReservation($paymentData, $tempReservation, $context);
    }

    /**
     * Logique centrale pour traiter et persister un ajout à une réservation existante après un paiement réussi.
     *
     * @param object $paymentData Données du paiement.
     * @param string $reservationId ID de la réservation SQL.
     * @param string $context
     * @return bool
     */
    public function processAndPersistReservationComplement(object $paymentData, string $reservationId, string $context): bool
    {
        //On vérifie si la réservation existe
        $reservation = $this->reservationRepository->findById($reservationId);
        if (!$reservation) {
            return false;
        }

        //On récupère l'ID HelloAsso du paiement
        $orderId = $paymentData->id;
        //On vérifie si ce paiement est déjà enregistré
        $reservationPayment = $this->reservationPaymentRepository->findByOrderId($orderId);
        //S'il existe déjà dans la table, on retourne false
        if ($reservationPayment) {
            return false;
        }

        //Si le montant ne correspond pas à la différence manquante dans la réservation, on retourne false.
        //Il peut être supérieur s'il y a un don, il faudra simplement enregistrer ce qu'il manque dans réservation et ailleurs le montant du don.
        if ($paymentData->amount->total < $reservation->getTotalAmount() - $reservation->getTotalAmountPaid()) {
            return false;
        }

        //On enregistre le paiement et on met à jour la réservation (en remettant isChecked à false pour faire remonter la commande)
        $this->paymentRecordService->createPaymentRecord($reservation->getId(), $paymentData, $context);

        //On met à jour $reservation
        $reservation = $this->reservationRepository->findById($reservationId, true, true);

        // Envoyer l'email de confirmation d'annulation
        if (!$this->mailPrepareService->sendReservationConfirmationEmail($reservation)) {
            throw new \RuntimeException('Échec de l\'envoi de l\'email de confirmation.');
        }

        // Enregistrer l'envoi de l'email
        if (!$this->mailService->recordMailSent($reservation, 'paiement_confirme_add')) {
            throw new \RuntimeException('Échec de l\'enregistrement de l\'envoi de l\'email de confirmation.');
        }

        return true;
    }

}