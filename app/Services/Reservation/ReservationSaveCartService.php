<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationComplement;
use app\Models\Reservation\ReservationComplementTemp;
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
     * @param ReservationComplementTemp[] $reservationComplements
     * @param array $tarifsById
     * @return array
     */
    public function prepareReservationComplementSummary(array $reservationComplements, array $tarifsById): array
    {
        $complements = [];
        $subtotal    = 0;

        foreach ($reservationComplements as $row) {
            if (!$row instanceof ReservationComplementTemp) {
                continue;
            }

            $tarifId = $row->getTarif() ?? 0;
            if ($tarifId <= 0 || !isset($tarifsById[$tarifId])) {
                continue;
            }

            /** @var Tarif $tarif */
            $tarif = $row->getTarifObject();

            $complements[$tarifId] = [
                'tarif_name'  => $tarif->getName(),
                'description' => $tarif->getDescription(),
                'price'       => $tarif->getPrice(),
                'qty'         => $row->getQty(),
                'code'       => $row->getTarifAccessCode(),
                'total'       => $row->getQty() * $tarif->getPrice(),
            ];

        }

        // Calcul des totaux + normalisation des codes pour la vue
        foreach ($complements as $tarifId => &$group) {
            $qty   = (int)($group['qty']   ?? 0);
            $price = (int)($group['price'] ?? 0);

            $group['total'] = $this->priceCalculator->computeComplementTotal($qty, $price);

            $subtotal += $group['total'];
        }
        unset($group);

        return [
            'complements' => $complements,
            'subtotal'    => $subtotal,
        ];
    }


    /**
     * @param $session
     * @return array
     */
    public function prepareReservationToSaveTemporarily($session): array
    {
        $tarifs = $this->tarifRepository->findByEventId($session['reservation']->getEvent());
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        $detailReport     = $this->prepareReservationDetailSummary($session['reservation_details'], $tarifsById);
        $complementReport = $this->prepareReservationComplementSummary($session['reservation_complements'] ?? [], $tarifsById);

        $this->reservationSessionService->setReservationSession('totals', [
            'details_subtotal'     => $detailReport['subtotal'],
            'complements_subtotal' => $complementReport['subtotal'],
            'total_amount'         => (int)$detailReport['subtotal'] + (int)$complementReport['subtotal'],
        ]);

        return [
            'event_id'               => $session['reservation']->getEvent(),
            'event_session_id'       => $session['reservation']->getEventSession() ?? null,
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
