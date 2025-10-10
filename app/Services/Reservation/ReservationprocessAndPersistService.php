<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;

class ReservationprocessAndPersistService
{
    private ReservationRepository $reservationRepository;
    private ReservationTempWriter $reservationTempWriter;
    private ReservationDataPersist $reservationDataPersist;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationTempWriter $reservationTempWriter,
        ReservationDataPersist $reservationDataPersist,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->reservationTempWriter = $reservationTempWriter;
        $this->reservationDataPersist = $reservationDataPersist;
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
        $existingReservation = $this->reservationRepository->findByTempId($primaryTempId);
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
        return $this->reservationDataPersist->persistPaidReservation($paymentData, $tempReservation, $context);
    }

}