<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;

class ReservationSummaryBuilder
{
    public function buildDetailsRecap(Reservation $reservation): array
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
                $place = htmlspecialchars($detail->getPlaceNumber());
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

    public function buildComplementsRecap(Reservation $reservation): array
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

    public function buildFullRecap(Reservation $reservation): array
    {
        $details = $this->buildDetailsRecap($reservation);
        $complements = $this->buildComplementsRecap($reservation);

        return [
            'html' => $details['html'] . $complements['html'],
            'text' => $details['text'] . $complements['text'],
        ];
    }
}
