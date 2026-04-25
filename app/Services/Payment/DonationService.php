<?php

namespace app\Services\Payment;

use app\Models\Reservation\ReservationPayment;

class DonationService
{

    /**
     * @param ReservationPayment[] $payments
     * @return int
     */
    public function totalAmountOfDonation(array $payments): int
    {
        $donationCents = 0;
        foreach ($payments as $payment) {
            $part = $payment->getPartOfDonation();
            if ($part !== null) {
                $donationCents += $part;
            }
        }
        return $donationCents;
    }

    /**
     * @param ReservationPayment[] $payments
     */
    public function checkIfHasDonation(array $payments): bool
    {
        foreach ($payments as $payment) {
            if (($payment->getPartOfDonation() ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }




}