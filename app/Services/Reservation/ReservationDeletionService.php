<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use Throwable;

class ReservationDeletionService
{
    private ReservationRepository $reservationRepository;
    private ReservationDetailRepository $reservationDetailRepository;
    private ReservationComplementRepository $reservationComplementRepository;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private ReservationMailSentRepository $reservationMailSentRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationDetailRepository $reservationDetailRepository,
        ReservationComplementRepository $reservationComplementRepository,
        ReservationPaymentRepository $reservationPaymentRepository,
        ReservationMailSentRepository $reservationMailSentRepository,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->reservationComplementRepository = $reservationComplementRepository;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->reservationMailSentRepository = $reservationMailSentRepository;
    }

    /**
     * Pour supprimer une réservation et ses détails.
     * @param int $reservationId
     * @return void
     * @throws Throwable En cas d'échec de la suppression.
     */
    public function deleteReservation(int $reservationId): void
    {
        $this->reservationRepository->beginTransaction();
        try {
            // On supprime d'abord les "enfants".
            $this->reservationPaymentRepository->deleteByReservation($reservationId);
            $this->reservationComplementRepository->deleteByReservation($reservationId);
            $this->reservationDetailRepository->deleteByReservation($reservationId);
            $this->reservationMailSentRepository->deleteByReservation($reservationId);

            // Puis, on supprime la réservation elle-même
            $this->reservationRepository->delete($reservationId);

            $this->reservationRepository->commit();
        } catch (Throwable $e) {
            $this->reservationRepository->rollBack();
            throw $e;
        }

    }

}