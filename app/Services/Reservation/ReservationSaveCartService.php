<?php

namespace app\Services\Reservation;

use app\Models\Tarif\Tarif;
use app\Repository\Tarif\TarifRepository;

class ReservationSaveCartService
{
    private ReservationSessionService $reservationSessionService;
    private TarifRepository $tarifRepository;

    public function __construct(
        ReservationSessionService $reservationSessionService,
        TarifRepository $tarifRepository,
    )
    {
        $this->reservationSessionService = $reservationSessionService;
        $this->tarifRepository = $tarifRepository;
    }

    /**
     * Pour calculer les totaux pour une réservation (en cours ou déjà faite)
     *
     * @param array $reservationDetails
     * @param array $tarifsById
     * @return array{details: array, subtotal: int}
     */
    public function prepareReservationDetailSummary(array $reservationDetails, array $tarifsById): array
    {
        // Réutilise l'existant pour grouper les lignes par tarif_id
        $grouped = $this->reservationSessionService->prepareReservationDetailToView($reservationDetails, $tarifsById);

        $summary = [];
        $subtotal = 0;

        foreach ($grouped as $tarifId => $group) {
            $participants = array_values(array_filter($group, 'is_array'));
            $count = count($participants);

            $tarif = $tarifsById[$tarifId] ?? null;
            $seatCount = $tarif ? ($tarif->getSeatCount() ?? 0) : 0;
            $price = $tarif ? (int)$tarif->getPrice() : 0;

            // évite division par zéro
            $packs = ($seatCount > 0) ? intdiv($count, max(1, $seatCount)) : $count;
            $total = $packs * $price;

            $subtotal += $total;

            $summary[$tarifId] = [
                'tarif_name'  => $group['tarif_name'] ?? '',
                'description' => $group['description'] ?? '',
                'price'       => $price,
                'seatCount'   => $seatCount,
                'participants'=> $participants,
                'count'       => $count,
                'packs'       => $packs,
                'total'       => $total,
            ];
        }

        return ['details' => $summary, 'subtotal' => $subtotal];
    }

    /**
     * Prépare `reservation_complement` pour la vue (groupe) et renvoie aussi le sous-total de ces compléments.
     *
     * @param array $reservationComplement
     * @param array<int,Tarif> $tarifsById
     * @return array{complements: array, subtotal: int}
     */
    public function prepareReservationComplementSummary(array $reservationComplement, array $tarifsById): array
    {
        // Réutilise l'existant pour construire les groupes
        $complements = $this->reservationSessionService->prepareReservationComplementToView($reservationComplement, $tarifsById);

        $subtotal = 0;
        foreach ($complements as $tid => &$group) {
            $qty = (int)($group['qty'] ?? 0);
            $price = (int)($group['price'] ?? 0);
            $group['total'] = $qty * $price;
            // s'assurer que 'codes' existe comme tableau (déjà fait dans prepareReservationComplementToView)
            $group['codes'] = $group['codes'] ?? [];
            $subtotal += $group['total'];
        }
        unset($group);

        return [
            'complements' => $complements,
            'subtotal'    => $subtotal,
        ];
    }


    /**
     * Prépare $reservation pour sauvegarde dans NoSQL
     *
     * @param $session
     * @return array
     */
    public function prepareReservationToSaveInNoSQL($session): array
    {
        // 1. Récupérer les tarifs de l'événement
        $tarifs = $this->tarifRepository->findByEventId($session['event_id']);

        // 2. Les indexer par leur ID, ce qui est crucial pour les méthodes de calcul
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        $detailReport       = $this->prepareReservationDetailSummary($session['reservation_detail'], $tarifsById);
        $complementReport   = $this->prepareReservationComplementSummary($session['reservation_complement'] ?? [], $tarifsById);

        return $reservation = [
            'event_id'              => $session['event_id'],
            'event_session_id'      => $session['event_session_id'] ?? null,
            'swimmer_id'            => $session['swimmer_id'] ?? null,
            'access_code_used'      => $session['access_code_used'] ?? null,
            'booker'                => $session['booker'] ?? [],
            'reservation_detail'    => $session['reservation_detail'] ?? [],
            'reservation_complement'=> $session['reservation_complement'] ?? [],
            'totals' => [
                'details_subtotal'     => $detailReport['subtotal'],
                'complements_subtotal' => $complementReport['subtotal'],
                'grand_total'          => (int)$detailReport['subtotal'] + (int)$complementReport['subtotal'],
            ],
            'created_at' => date('c'),
        ];

    }

}