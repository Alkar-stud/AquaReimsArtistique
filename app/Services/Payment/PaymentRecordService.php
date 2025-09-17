<?php

namespace app\Services\Payment;

use app\Models\Reservation\ReservationPayments;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Reservation\ReservationsRepository;
use DateMalformedStringException;

/**
 * Service dédié à la création d'enregistrements de paiement.
 */
class PaymentRecordService
{
    private ReservationPaymentsRepository $paymentsRepository;
    private ReservationsRepository $reservationsRepository;


    public function __construct()
    {
        $this->paymentsRepository = new ReservationPaymentsRepository();
        $this->reservationsRepository = new ReservationsRepository();
    }

    /**
     * Crée et persiste un enregistrement de paiement et met à jour le total payé de la réservation.
     *
     * @param int $reservationId L'ID de la réservation (SQL).
     * @param object $orderData Les données de la commande issues du webhook HelloAsso.
     * @param string $metadata
     * @return ReservationPayments|null
     * @throws DateMalformedStringException
     */
    public function createPaymentRecord(int $reservationId, object $orderData, string $metadata = 'other'): ?ReservationPayments
    {
        $checkoutIdAsInt = $orderData->id ?? null;
        if (!$checkoutIdAsInt) {
            return null;
        }

        // Sécurité : ne pas enregistrer deux fois le même paiement
        if ($this->paymentsRepository->findByCheckoutId($checkoutIdAsInt)) {
            return null;
        }

        if ($metadata == 'new_reservation') {
            $typePayment = 'new';
        } else if ($metadata == 'balance_payment') {
            $typePayment = 'add';
        } else {
            $typePayment = 'other';
        }

        $payment = new ReservationPayments();
        $payment->setReservation($reservationId)
            ->setType($typePayment)
            ->setCheckoutId($orderData->checkoutIntentId)
            ->setOrderId($orderData->id)
            ->setPaymentId($orderData->payments[0]->id)
            ->setAmountPaid((int)$orderData->amount->total)
            ->setStatusPayment($orderData->payments[0]->state)
            ->setCreatedAt($orderData->date);

        // Mettre à jour le total payé sur la réservation
        // Pour un nouveau paiement, le montant est déjà défini lors de la création de la réservation.
        // On ne met à jour (en ajoutant) que pour les paiements complémentaires.
        //On met $reservation totalAmountPaid à la bonne valeur, car $payment->getAmountPaid() peut être supérieur en cas de don.
        //Dans ce cas, on enregistre la différence ailleurs.
        if ($typePayment === 'add') {
            $reservation = $this->reservationsRepository->findById($reservationId);
            if ($reservation->getTotalAmount() < ($reservation->getTotalAmountPaid() + $payment->getAmountPaid()))
            {
                $amountToPaid = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();
                $partOfDonation = $payment->getAmountPaid() - $amountToPaid;
                $newTotalPaid = $reservation->getTotalAmount();

                $payment->setPartOfDonation($partOfDonation);
            } else {
                $newTotalPaid = $reservation->getTotalAmountPaid() + $payment->getAmountPaid();
            }

            $this->reservationsRepository->updateSingleField($reservationId, 'total_amount_paid', $newTotalPaid);
        }

        $this->paymentsRepository->insert($payment);

        return $payment;
    }
}