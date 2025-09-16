<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservations;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\MongoReservationStorage;
use app\Services\Payment\PaymentRecordService;
use app\Services\ReservationStorageInterface;
use DateMalformedStringException;
use Exception;

class ReservationprocessAndPersistService
{
    private ReservationStorageInterface $reservationStorage;
    private ReservationPersistenceService $persistenceService;
    private ReservationTokenService $reservationTokenService;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;
    private ReservationsRepository $reservationsRepository;
    private ReservationPaymentsRepository $reservationPaymentsRepository;

    public function __construct()
    {
        $this->reservationStorage = new MongoReservationStorage('ReservationTemp');
        $this->reservationTokenService = new ReservationTokenService();
        $this->reservationsPlacesTempRepository = new ReservationsPlacesTempRepository();

        $this->persistenceService = new ReservationPersistenceService(
            $this->reservationStorage,
            $this->reservationTokenService,
            $this->reservationsPlacesTempRepository
        );
        $this->reservationsRepository = new ReservationsRepository();
        $this->reservationPaymentsRepository = new ReservationPaymentsRepository();
    }

    /**
     * Logique centrale pour traiter et persister une réservation après un paiement réussi.
     * @param object $paymentData Données de la commande/paiement.
     * @param string $reservationIdMongo ID de la réservation temporaire.
     * @return Reservations|null
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function processAndPersistReservation(object $paymentData, string $reservationIdMongo): ?Reservations
    {
        // Vérifie si cette réservation a déjà été persistée pour éviter les doublons
        $existingReservation = $this->reservationsRepository->findByMongoId($reservationIdMongo);
        if ($existingReservation) {
            error_log("Tentative de double traitement pour la réservation MongoDB ID: " . $reservationIdMongo);
            return $existingReservation; // C'est déjà fait, on retourne l'objet existant.
        }

        $tempReservation = $this->reservationStorage->findReservationById($reservationIdMongo);
        if (!$tempReservation) {
            error_log("Impossible de trouver la réservation temporaire pour l'ID: " . $reservationIdMongo);
            return null;
        }

        // Utilise le service pour persister la réservation
        return $this->persistenceService->persistPaidReservation($paymentData, $tempReservation);
    }

    /**
     * Logique centrale pour traiter et persister un ajout à une réservation existante après un paiement réussi.
     * @param object $paymentData Données du paiement.
     * @param string $reservationId ID de la réservation SQL.
     * @param string $context
     * @return bool
     * @throws DateMalformedStringException
     */
    public function processAndPersistReservationComplement(object $paymentData, string $reservationId, string $context): bool
    {
        //On vérifie si la réservation existe
        $reservation = $this->reservationsRepository->findById($reservationId);
        if (!$reservation) {
            return false;
        }

        //On récupère l'ID HelloAsso du paiement
        $orderId = $paymentData->id;
        //On vérifie si ce paiement est déjà enregistré
        $reservationPayment = $this->reservationPaymentsRepository->findByOrderId($orderId);
        //S'il existe déjà dans la table, on retourne false
        if ($reservationPayment) {
            return false;
        }

        //Si le montant ne correspond pas à la différence manquante dans la réservation, on retourne false
        if ($paymentData->amount->total != $reservation->getTotalAmount() - $reservation->getTotalAmountPaid()) {
            return false;
        }

        //On enregistre le paiement et on met à jour la réservation
        (new PaymentRecordService())->createPaymentRecord($reservation->getId(), $paymentData, $context);

        //On met à jour $reservation
        $reservation = $this->reservationsRepository->findById($reservationId);

        // Envoyer l'email de confirmation et enregistrer l'envoi
        $this->persistenceService->sendAndRecordConfirmationEmail($reservation);

        return true;
    }

}