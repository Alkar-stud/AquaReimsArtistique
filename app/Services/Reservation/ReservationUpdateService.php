<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationsComplements;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use DateMalformedStringException;

class ReservationUpdateService
{
    private ReservationsRepository $reservationsRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsComplementsRepository $reservationsComplementsRepository;
    private ReservationCartService $reservationCartService;
    private ReservationService $reservationService;

    public function __construct()
    {
        $this->reservationsRepository = new ReservationsRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->reservationsComplementsRepository = new ReservationsComplementsRepository();
        $this->reservationCartService = new ReservationCartService();
        $this->reservationService = new ReservationService();
    }

    public function updateContactField(int $reservationId, string $field, string $value): array
    {
        // Appliquer les règles de casse
        $normalizedValue = $this->reservationService->normalizeFieldValue($field, $value);
        $success = $this->reservationsRepository->updateSingleField($reservationId, $field, $normalizedValue);

        return $success
            ? ['success' => true, 'message' => 'Informations mises à jour.']
            : ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }

    public function updateDetailField(int $detailId, string $field, string $value): array
    {
        // Appliquer les règles de casse
        $normalizedValue = $this->reservationService->normalizeFieldValue($field, $value);
        $success = $this->reservationsDetailsRepository->updateSingleField($detailId, $field, $normalizedValue);

        return $success
            ? ['success' => true, 'message' => 'Participant mis à jour.']
            : ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }

    /**
     * @throws DateMalformedStringException
     */
    public function updateComplementQuantity(int $reservationId, int $complementId, string $action): array
    {
        $complement = $this->reservationsComplementsRepository->findById($complementId);
        if (!$complement) {
            return ['success' => false, 'message' => 'Complément non trouvé.'];
        }

        $qty = $complement->getQty();
        if ($action === 'plus') {
            $qty++;
        } else {
            $qty--;
        }

        if ($qty <= 0) {
            $success = $this->reservationsComplementsRepository->delete($complement->getId());
            $message = 'Complément supprimé.';
        } else {
            $success = $this->reservationsComplementsRepository->updateQuantity($complement->getId(), null, $qty);
            $message = 'Quantité mise à jour.';
        }

        if ($success) {
            $this->recalculateAndSaveTotal($reservationId);
            return ['success' => true, 'message' => $message, 'reload' => true];
        }

        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }

    /**
     * @throws DateMalformedStringException
     */
    public function addComplement(int $reservationId, int $tarifId): array
    {
        $existing = $this->reservationsComplementsRepository->findByReservationAndTarif($reservationId, $tarifId);
        if ($existing) {
            return $this->updateComplementQuantity($reservationId, $existing->getId(), 'plus');
        }

        $newComplement = new ReservationsComplements();
        $newComplement->setReservation($reservationId)->setTarif($tarifId)->setQty(1)->setCreatedAt(date('Y-m-d H:i:s'));
        $this->reservationsComplementsRepository->insert($newComplement);

        $this->recalculateAndSaveTotal($reservationId);

        return ['success' => true, 'message' => 'Article ajouté.', 'reload' => true];
    }

    /**
     * @throws DateMalformedStringException
     */
    private function recalculateAndSaveTotal(int $reservationId): void
    {
        $reservation = $this->reservationsRepository->findById($reservationId);
        $allReservationDetails = $this->reservationsDetailsRepository->findByReservation($reservationId);
        $allReservationComplements = $this->reservationsComplementsRepository->findByReservation($reservationId);

        $detailsAsArray = array_map(fn($d) => ['tarif_id' => $d->getTarif()], $allReservationDetails);
        $complementsAsArray = array_map(fn($c) => ['tarif_id' => $c->getTarif(), 'qty' => $c->getQty()], $allReservationComplements);

        $newTotalAmount = $this->reservationCartService->calculateTotalAmount(['reservation_detail' => $detailsAsArray, 'reservation_complement' => $complementsAsArray, 'event_id' => $reservation->getEvent()]);

        $this->reservationsRepository->updateSingleField($reservationId, 'total_amount', (string)$newTotalAmount);
    }
}