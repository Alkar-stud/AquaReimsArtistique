<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;

class ReservationDeletionService
{
    private ReservationRepository $reservationRepository;
    private ReservationDetailRepository $reservationDetailRepository;
    private ReservationComplementRepository $reservationComplementRepository;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private ReservationMailSentRepository $reservationMailSentRepository;

    public function __construct()
    {
        $this->reservationRepository = new ReservationRepository();
        $this->reservationDetailRepository = new ReservationDetailRepository();
        $this->reservationComplementRepository = new ReservationComplementRepository();
        $this->reservationPaymentRepository = new ReservationPaymentRepository();
        $this->reservationMailSentRepository = new ReservationMailSentRepository();
    }

    public function deleteReservation(int $reservationId): void
    {
        //On supprime d'abord les "enfants".
        $this->reservationPaymentRepository->deleteByReservation($reservationId);
        $this->reservationComplementRepository->deleteByReservation($reservationId);
        $this->reservationDetailRepository->deleteByReservation($reservationId);
        $this->reservationMailSentRepository->deleteByReservation($reservationId);

        //Puis, on supprime la réservation elle-même
        $this->reservationRepository->delete($reservationId);

    }

}