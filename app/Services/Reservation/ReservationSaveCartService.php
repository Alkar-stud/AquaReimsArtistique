<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationComplement;
use app\Models\Reservation\ReservationDetailTemp;
use app\Models\Tarif\Tarif;
use app\Repository\Tarif\TarifRepository;

class ReservationSaveCartService
{
    private ReservationSessionService $reservationSessionService;
    private TarifRepository $tarifRepository;
    private ReservationPriceCalculator $priceCalculator;

    public function __construct(
        ReservationSessionService $reservationSessionService,
        TarifRepository $tarifRepository,
        ReservationPriceCalculator $priceCalculator,
    )
    {
        $this->reservationSessionService = $reservationSessionService;
        $this->tarifRepository = $tarifRepository;
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * @param ReservationDetailTemp[] $reservationDetails
     * @param array $tarifsById
     * @return array
     */
    public function prepareReservationDetailSummary(array $reservationDetails, array $tarifsById): array
    {
        $grouped = $this->reservationSessionService->prepareSessionReservationDetailToView($reservationDetails, $tarifsById);

        $summary = [];
        $subtotal = 0;

        foreach ($grouped as $tarifId => $group) {

            // On garde uniquement les valeurs qui sont des objets ReservationDetailTemp
            $participants = array_values(array_filter(
                $group,
                static fn($item) => $item instanceof ReservationDetailTemp
            ));
            $count = count($participants);

            $tarif = $tarifsById[$tarifId] ?? null;
            $seatCount = $tarif ? ($tarif->getSeatCount() ?? 0) : 0;
            $price = $tarif ? (int)$tarif->getPrice() : 0;

            $calc = $this->priceCalculator->computeDetailTotals($count, $seatCount, $price);
            $packs = $calc['packs'];
            $total = $calc['total'];

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
     * @param ReservationComplement[] $reservationComplement
     * @param array $tarifsById
     * @return array
     */
    public function prepareReservationComplementSummary(array $reservationComplement, array $tarifsById): array
    {
        $complements = $this->reservationSessionService->prepareReservationComplementToView($reservationComplement, $tarifsById);

        $subtotal = 0;
        foreach ($complements as $tid => &$group) {
            $qty = (int)($group['qty'] ?? 0);
            $price = (int)($group['price'] ?? 0);
            $group['total'] = $this->priceCalculator->computeComplementTotal($qty, $price);
            $group['codes'] = $group['codes'] ?? [];
            $subtotal += $group['total'];
        }
        unset($group);

        return [
            'complements' => $complements,
            'subtotal'    => $subtotal,
        ];
    }

    public function prepareReservationToSaveTemporarily($session): array
    {
        $tarifs = $this->tarifRepository->findByEventId($session['event_id']);
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        $detailReport     = $this->prepareReservationDetailSummary($session['reservation_detail'], $tarifsById);
        $complementReport = $this->prepareReservationComplementSummary($session['reservation_complement'] ?? [], $tarifsById);

        $this->reservationSessionService->setReservationSession('totals', [
            'details_subtotal'     => $detailReport['subtotal'],
            'complements_subtotal' => $complementReport['subtotal'],
            'total_amount'         => (int)$detailReport['subtotal'] + (int)$complementReport['subtotal'],
        ]);

        return [
            'event_id'               => $session['event_id'],
            'event_session_id'       => $session['event_session_id'] ?? null,
            'swimmer_id'             => $session['swimmer_id'] ?? null,
            'access_code_used'       => $session['access_code_used'] ?? null,
            'booker'                 => $session['booker'] ?? [],
            'reservation_detail'     => $detailReport['details'] ?? [],
            'reservation_complement' => $complementReport['complements'] ?? [],
            'totals' => [
                'details_subtotal'     => $detailReport['subtotal'],
                'complements_subtotal' => $complementReport['subtotal'],
                'total_amount'         => (int)$detailReport['subtotal'] + (int)$complementReport['subtotal'],
            ],
            'created_at' => date('c'),
        ];
    }
}
