<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservations;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\MongoReservationStorage;
use app\Services\ReservationStorageInterface;
use DateMalformedStringException;
use Exception;

class ReservationprocessAndPersistService
{
    private ReservationStorageInterface $reservationStorage;
    private ReservationPersistenceService $persistenceService;
    private ReservationTokenService $reservationTokenService;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;

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
        $reservationsRepository = new ReservationsRepository();
        // Vérifie si cette réservation a déjà été persistée pour éviter les doublons
        $existingReservation = $reservationsRepository->findByMongoId($reservationIdMongo);
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

}