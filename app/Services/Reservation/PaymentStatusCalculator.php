<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;

class PaymentStatusCalculator
{
    public function calculate(Reservation $reservation): array
    {
        $totalAmount = $reservation->getTotalAmount();
        $totalAmountPaid = $reservation->getTotalAmountPaid();

        if ($totalAmountPaid >= $totalAmount) {
            return [
                'label' => 'Total payé :',
                'labelHtml' => '<strong style="color: green;">Total payé :</strong>',
                'amount' => $totalAmountPaid,
                'color' => 'green',
            ];
        }

        if ($totalAmountPaid > 0) {
            return [
                'label' => 'Reste à payer :',
                'labelHtml' => '<strong style="color: orange;">Reste à payer :</strong>',
                'amount' => $totalAmount - $totalAmountPaid,
                'color' => 'orange',
            ];
        }

        return [
            'label' => 'À payer :',
            'labelHtml' => '<strong style="color: red;">À payer :</strong>',
            'amount' => $totalAmount,
            'color' => 'red',
        ];
    }
}
