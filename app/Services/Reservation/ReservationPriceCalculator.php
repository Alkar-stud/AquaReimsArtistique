<?php

namespace app\Services\Reservation;

class ReservationPriceCalculator
{
    /**
     * Calcule le nombre de packs et le total (en centimes) pour des détails de réservation.
     * - count: nombre de participants
     * - seatCount: nombre de places par pack (0 => 1 participant = 1 pack).
     * - price: prix unitaire du pack (en centimes)
     *
     * @param int $count
     * @param int $seatCount
     * @param int $price
     * @return array{packs:int,total:int}
     */
    public function computeDetailTotals(int $count, int $seatCount, int $price): array
    {
        $packs = ($seatCount > 0) ? intdiv($count, max(1, $seatCount)) : $count;
        $total = $packs * max(0, $price);

        return ['packs' => $packs, 'total' => $total];
    }

    /**
     * Calcule le total (en centimes) pour des compléments.
     * @param int $qty
     * @param int $price
     * @return int
     */
    public function computeComplementTotal(int $qty, int $price): int
    {
        return max(0, $qty) * max(0, $price);
    }
}
