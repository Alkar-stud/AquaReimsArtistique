<?php

namespace app\Services\Payment;

use app\Models\Reservation\ReservationPayment;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;

/**
 * Service dédié à la création d'enregistrements de paiement.
 */
class PaymentRecordService
{
    private ReservationPaymentRepository $paymentRepository;
    private ReservationRepository $reservationRepository;


    public function __construct(
        ReservationPaymentRepository $paymentRepository,
        ReservationRepository $reservationRepository,
    )
    {
        $this->paymentRepository = $paymentRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Crée et persiste un enregistrement de paiement et met à jour le total payé de la réservation.
     *
     * @param int $reservationId L'ID de la réservation (SQL).
     * @param object $orderData Les données de la commande issues du webhook HelloAsso.
     * @param string|null $context
     * @return ReservationPayment|null
     */
    public function createPaymentRecord(int $reservationId, object $orderData, ?string $context): ?ReservationPayment
    {
        $checkoutIdAsInt = $orderData->id ?? null;
        if (!$checkoutIdAsInt) {
            return null;
        }

        // Sécurité : ne pas enregistrer deux fois le même paiement
        if ($this->paymentRepository->findByCheckoutId($checkoutIdAsInt)) {
            return null;
        }

        if ($context === 'new_reservation') { $typePayment = 'new'; }
        else if ($context == 'balance_payment') { $typePayment = 'add'; }
        else if ($context == 'refund') { $typePayment = 'ref'; }
        else { $typePayment = 'other'; }

        $payment = new ReservationPayment();
        $payment->setReservation($reservationId)
            ->setType($typePayment)
            ->setCheckoutId($orderData->checkoutIntentId)
            ->setOrderId($orderData->id)
            ->setPaymentId($orderData->payments[0]->id)
            ->setAmountPaid((int)$orderData->amount->total)
            ->setStatusPayment($orderData->payments[0]->state);

        // Mettre à jour le total payé sur la réservation
        // Pour un nouveau paiement, le montant est déjà défini lors de la création de la réservation.
        // On ne met à jour (en ajoutant) que pour les paiements complémentaires.
        //On met $reservation totalAmountPaid à la bonne valeur, car $payment->getAmountPaid() peut être supérieur en cas de don.
        //Dans ce cas, on enregistre la différence ailleurs.
        if ($typePayment === 'add') {
            $reservation = $this->reservationRepository->findById($reservationId);
            if ($reservation->getTotalAmount() < ($reservation->getTotalAmountPaid() + $payment->getAmountPaid()))
            {
                $amountToPaid = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();
                $partOfDonation = $payment->getAmountPaid() - $amountToPaid;
                $newTotalPaid = $reservation->getTotalAmount();

                $payment->setPartOfDonation($partOfDonation);
            } else {
                $newTotalPaid = $reservation->getTotalAmountPaid() + $payment->getAmountPaid();
            }

            $reservation->setTotalAmountPaid($newTotalPaid);
            //Ajout donc on doit vérifier de nouveau la commande
            $reservation->setIsChecked(false);

            $this->reservationRepository->update($reservation);

            //$this->reservationRepository->updateSingleField($reservationId, 'total_amount_paid', $newTotalPaid);
        }

        $id = $this->paymentRepository->insert($payment);
        if ($id <= 0) {
            throw new \RuntimeException('Échec insertion payment.');
        }

        return $payment;
    }

    /**
     * @param int $paymentId
     * @param object $orderData
     * @return ReservationPayment|null
     */
    public function createRefundPaymentRecord(int $paymentId, object $orderData): ?ReservationPayment
    {
        $payment = $this->paymentRepository->findByPaymentId($paymentId);
        if (!$payment) {
            return null;
        }

        $refundOperations = new ReservationPayment();
        $refundOperations->setReservation($payment->getReservation())
            ->setType('ref')
            ->setCheckoutId($payment->getCheckoutId())
            ->setOrderId($orderData->order->id)
            ->setPaymentId($payment->getPaymentId())
            ->setAmountPaid((int)$orderData->amount)
            ->setStatusPayment($orderData->refundOperations[0]->status);

        $id = $this->paymentRepository->insert($refundOperations);
        if ($id <= 0) {
            throw new \RuntimeException('Échec insertion payment.');
        }

        return $refundOperations;
    }


}