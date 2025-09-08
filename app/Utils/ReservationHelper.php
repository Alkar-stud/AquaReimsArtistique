<?php
namespace app\Utils;

use DateTimeInterface;

class ReservationHelper
{
    /**
     * Calcule le total de la réservation (en euros).
     * @param array $reservation Données de la réservation (détail et compléments)
     * @param array $tarifs Liste des objets Tarif (doivent avoir getId() et getPrice())
     * @return int Total en centimes
     */
    public static function calculerTotal(array $reservation, array $tarifs): int
    {
        $tarifsById = [];
        foreach ($tarifs as $t) {
            $tarifsById[$t->getId()] = $t;
        }
        $total = 0;
        // Tarifs avec places assises (1 par participant)
        foreach ($reservation['reservation_detail'] ?? [] as $detail) {
            $tarif = $tarifsById[$detail['tarif_id']] ?? null;
            $total += $tarif ? $tarif->getPrice() : 0;
        }
        // Tarifs complémentaires (quantité variable)
        foreach ($reservation['reservation_complement'] ?? [] as $item) {
            $tarif = $tarifsById[$item['tarif_id']] ?? null;
            $qty = (int)($item['qty'] ?? 0);
            $total += ($tarif ? $tarif->getPrice() : 0) * $qty;
        }
        return (int)round($total * 100);
    }

    /*
     * Pour générer un token avec date de validité au jour de l'event
     */
    public static function genereReservationToken(int $nbCaractereToken, DateTimeInterface $dateEvent, ?DateTimeInterface $dateFinInscriptionsEvent = null): array
    {
        if ($dateFinInscriptionsEvent === null) {
            $dateFinInscriptionsEvent = $dateEvent;
        }
        //On génère le token et la date de validité en fonction de la date de l'event
        $token = bin2hex(random_bytes($nbCaractereToken));

        // La date de validité est formatée directement depuis l'objet DateTimeInterface,
        // et la ligne précédente qui était écrasée a été supprimée.
        $token_valid = $dateFinInscriptionsEvent->format('Y-m-d H:i:s');

        return [$token, $token_valid];
    }

}

