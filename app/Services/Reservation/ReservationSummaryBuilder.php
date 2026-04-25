<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Services\Payment\DonationService;

class ReservationSummaryBuilder
{
    private DonationService $donationService;
    public function __construct(
        DonationService $donationService
    ) {
        $this->donationService = $donationService;
    }


    private function buildDetailsRecap(Reservation $reservation): array
    {
        $html = '';
        $text = '';

        if (empty($reservation->getDetails())) {
            return ['html' => $html, 'text' => $text];
        }

        $html .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Participants</h4>';
        $text .= "Participants\n";
        $html .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';

        foreach ($reservation->getDetails() as $detail) {
            $tarif = $detail->getTarifObject();
            $fullName = htmlspecialchars($detail->getFirstName() . ' ' . $detail->getName());

            $html .= '<tr>';
            $html .= '<td style="border-bottom: 1px solid #ddd;">' . $fullName;
            $text .= $fullName;

            if ($tarif->getName()) {
                $tarifName = htmlspecialchars($tarif->getName());
                $html .= ' (Tarif: <em>' . $tarifName . '</em>)';
                $text .= ' (Tarif: ' . $tarifName . ')';
            }

            if ($detail->getPlaceNumber()) {
                $place = htmlspecialchars($detail->getPlaceObject()->getFullPlaceName());
                $html .= ' &mdash; Place: <em>' . $place . '</em>';
                $text .= ' - Place: ' . $place;
            }

            $price = number_format($tarif->getPrice() / 100, 2, ',', ' ') . ' €';
            $html .= '</td>';
            $html .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . $price . '</strong></td>';
            $text .= ' : ' . $price . "\n";
            $html .= '</tr>';
        }

        $html .= '</table>';

        return ['html' => $html, 'text' => $text];
    }

    private function buildComplementsRecap(Reservation $reservation): array
    {
        $html = '';
        $text = '';

        if (empty($reservation->getComplements())) {
            return ['html' => $html, 'text' => $text];
        }

        $html .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Compléments</h4>';
        $text .= "\nCompléments\n";
        $html .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';

        foreach ($reservation->getComplements() as $complement) {
            $tarif = $complement->getTarifObject();
            $name = htmlspecialchars($tarif->getName());
            $qty = $complement->getQty();

            $html .= '<tr>';
            $html .= '<td style="border-bottom: 1px solid #ddd;">' . $name . ' (x' . $qty . ')</td>';
            $text .= $name . ' (x' . $qty . ')';

            $price = number_format($tarif->getPrice() / 100, 2, ',', ' ') . ' €';
            $html .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . $price . '</strong></td>';
            $text .= ' : ' . $price . "\n";
            $html .= '</tr>';
        }

        $html .= '</table>';

        return ['html' => $html, 'text' => $text];
    }

    private function buildDonationRecap(Reservation $reservation): array
    {
        $html = '';
        $text = '';
        $donationCents = $this->donationService->totalAmountOfDonation($reservation->getPayments());

        if ($donationCents <= 0) {
            return ['html' => $html, 'text' => $text];
        }

        $donationFormatted = number_format($donationCents / 100, 2, ',', ' ') . ' €';

        $html .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Don</h4>';
        $html .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="border-bottom: 1px solid #ddd;">Don à l\'association</td>';
        $html .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . $donationFormatted . '</strong></td>';
        $html .= '</tr>';
        $html .= '</table>';

        $text .= "\nDon\n";
        $text .= "Don à l'association : " . $donationFormatted . "\n";

        return ['html' => $html, 'text' => $text];
    }

    public function buildFullRecap(Reservation $reservation): array
    {
        $details = $this->buildDetailsRecap($reservation);
        $complements = $this->buildComplementsRecap($reservation);
        $donation = $this->buildDonationRecap($reservation);

        return [
            'html' => $details['html'] . $complements['html'] . $donation['html'],
            'text' => $details['text'] . $complements['text'] . $donation['text'],
        ];
    }
}
