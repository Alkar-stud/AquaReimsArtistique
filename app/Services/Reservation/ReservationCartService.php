<?php

namespace app\Services\Reservation;

use app\Repository\TarifsRepository;

class ReservationCartService
{
    private TarifsRepository $tarifsRepository;

    public function __construct()
    {
        $this->tarifsRepository = new TarifsRepository();
    }
    /**
     * Calcule le montant total de la réservation en se basant sur les quantités de chaque tarif.
     *
     * @param array $reservationData Les données complètes de la session de réservation.
     * @return int Le montant total en centimes
     */
    public function calculateTotalAmount(array $reservationData): int
    {
        $total = 0;
        $eventId = $reservationData['event_id'] ?? null;
        if (!$eventId) {
            return $total;
        }

        $allEventTarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifsById = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        // Calcul pour les tarifs avec places assises (packs inclus)
        $reservationDetails = $reservationData['reservation_detail'] ?? [];
        $seatedTarifQuantities = $this->getTarifQuantitiesFromDetails($reservationDetails, $allEventTarifs);

        foreach ($seatedTarifQuantities as $tarifId => $quantity) {
            if (isset($tarifsById[$tarifId])) {
                $total += $quantity * $tarifsById[$tarifId]->getPrice();
            }
        }

        // Calcul pour les compléments (tarifs sans place assise)
        $complements = $reservationData['reservation_complement'] ?? [];
        foreach ($complements as $complement) {
            $tarifId = $complement['tarif_id'];
            if (isset($tarifsById[$tarifId])) {
                $total += $complement['qty'] * $tarifsById[$tarifId]->getPrice();
            }
        }

        return $total;
    }

    /**
     * Calcule les quantités pour chaque tarif à partir des détails de la réservation.
     *
     * @param array $reservationDetails Les détails de la réservation (liste des participants).
     * @param array $allEventTarifs La liste complète des objets Tarif de l'événement.
     * @return array Un tableau associatif [tarif_id => quantity].
     */
    public function getTarifQuantitiesFromDetails(array $reservationDetails, array $allEventTarifs): array
    {
        if (empty($reservationDetails)) {
            return [];
        }
        // Compter le nombre de places pour chaque tarif_id
        $tarifIds = array_column($reservationDetails, 'tarif_id');
        $placesPerTarifId = array_count_values($tarifIds);

        // Convertir le nombre de places en nombre de "packs" (quantité de tarifs)
        $tarifQuantities = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifId = $tarif->getId();
            $nbPlacesInTarif = $tarif->getNbPlace() ?? 1;
            if (isset($placesPerTarifId[$tarifId])) {
                // On s'assure que la division est entière et logique.
                // Si on a 4 places pour un tarif de 4 places, ça fait 1 pack.
                // La division par zéro est évitée car getNbPlace() renvoie 1 par défaut.
                $tarifQuantities[$tarifId] = (int)($placesPerTarifId[$tarifId] / $nbPlacesInTarif);
            }
        }
        return $tarifQuantities;
    }

    /**
     * Compte le nombre de places assises (numérotées) dans un ensemble de détails de réservation.
     *
     * @param array $reservationDetails Les détails de la réservation (peut contenir des objets ou des tableaux).
     * @param array $tarifs La liste complète des tarifs de l'événement pour référence.
     * @return int Le nombre de places assises.
     */
    public function countSeatedPlaces(array $reservationDetails, array $tarifs): int
    {
        $nb = 0;
        // Création d'une carte pour une recherche rapide des tarifs par ID
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        foreach ($reservationDetails as $detail) {
            $tarifId = is_array($detail) ? ($detail['tarif_id'] ?? null) : $detail->getTarif();
            if ($tarifId !== null) {
                $tarif = $tarifsById[$tarifId] ?? null;
                // Une place est considérée comme "assise" si son tarif possède un nombre de places défini (getNbPlace n'est pas null).
                if ($tarif && $tarif->getNbPlace() !== null) {
                    $nb++;
                }
            }
        }
        return $nb;
    }


}