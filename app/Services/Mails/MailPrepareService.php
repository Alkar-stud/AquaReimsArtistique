<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Utils\BuildLink;

readonly class MailPrepareService
{
    public function __construct(
        private MailService         $mailService = new MailService(),
        private MailTemplateService $templateService = new MailTemplateService()
    ) {}

    /**
     * Envoie un email de réinitialisation de mot de passe.
     * @param string $recipientEmail
     * @param string $username
     * @param string $resetLink
     * @return bool
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $username, string $resetLink): bool
    {
        $tpl = $this->templateService->render('password_reset', [
            'username' => $username,
            'link' => $resetLink,
        ]);
        if (!$tpl) return false;

        return $this->mailService->sendMessage(
            $recipientEmail,
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText()
        );
    }

    /**
     * Envoie un email suite au changement du mot de passe.
     * @param string $recipientEmail
     * @param string $username
     * @return bool
     */
    public function sendPasswordModifiedEmail(string $recipientEmail, string $username): bool
    {
        $tpl = $this->templateService->render('password_modified', [
            'username' => $username,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ]);
        if (!$tpl) return false;

        return $this->mailService->sendMessage(
            $recipientEmail,
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText()
        );
    }


    /**
     * Pour envoyer un email de confirmation de réservation.
     *
     * @param Reservation $reservation
     * @param string $mailTemplate
     * @return bool
     */
    public function sendReservationConfirmationEmail(Reservation $reservation, string $mailTemplate = 'paiement_confirme'): bool
    {
        $recapHtml = '';
        $recapText = '';
        if (!empty($reservation->getDetails())) {
            $recapHtml .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Participants</h4>';
            $recapText .= 'Participants
            ';
            $recapHtml .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
            foreach ($reservation->getDetails() as $key=>$detail) {
                $recapHtml .= '<tr>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd;">';
                $recapHtml .= htmlspecialchars($detail->getFirstName() . ' ' . $detail->getName());
                $recapText .= htmlspecialchars($detail->getFirstName() . ' ' . $detail->getName());
                if ($reservation->getDetails()[$key]->getTarifObject()->getName()) {
                    $recapHtml .= ' (Tarif: <em>' . htmlspecialchars($reservation->getDetails()[$key]->getTarifObject()->getName()) . '</em>)';
                    $recapText .= ' (Tarif: ' . htmlspecialchars($reservation->getDetails()[$key]->getTarifObject()->getName()) . ')';
                }
                if ($detail->getPlaceNumber()) {
                    $recapHtml .= ' &mdash; Place: <em>' . htmlspecialchars($detail->getPlaceNumber()) . '</em>';
                    $recapText .= ' - Place: ' . htmlspecialchars($detail->getPlaceNumber());
                }
                $recapHtml .= '</td>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($reservation->getDetails()[$key]->getTarifObject()->getPrice() / 100, 2, ',', ' ') . ' €</strong></td>';
                $recapText .= ' : ' . number_format($reservation->getDetails()[$key]->getTarifObject()->getPrice() / 100, 2, ',', ' ') . ' €
                ';
                $recapHtml .= '</tr>';
            }
            $recapHtml .= '</table>';
        }

        if (!empty($reservation->getComplements())) {
            $recapHtml .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Compléments</h4>';
            $recapText .= '
    Compléments
            ';
            $recapHtml .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
            foreach ($reservation->getComplements() as $key=>$complement) {
                $recapHtml .= '<tr>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd;">';
                //Nom du complément
                $recapHtml .= htmlspecialchars($reservation->getComplements()[$key]->getTarifObject()->getName());
                $recapText .= htmlspecialchars($reservation->getComplements()[$key]->getTarifObject()->getName());
                //Quantité
                $recapHtml .= ' (x' . $complement->getQty() . ')';
                $recapText .= ' (x' . $complement->getQty() . ')';

                $recapHtml .= '</td>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($reservation->getComplements()[$key]->getTarifObject()->getPrice() / 100, 2, ',', ' ') . ' €</strong></td>';
                $recapText .= ' : ' . number_format($reservation->getComplements()[$key]->getTarifObject()->getPrice() / 100, 2, ',', ' ') . ' €
                ';
                $recapHtml .= '</tr>';
            }
            $recapHtml .= '</table>';
        }

        // Logique pour le total à payer
        $totalAmount = $reservation->getTotalAmount();
        $totalAmountPaid = $reservation->getTotalAmountPaid();

        if ($totalAmountPaid >= $totalAmount) {
            $totalAPayerLabel = 'Total payé :';
            $totalAPayerColor = 'green';
            $montantAAfficher = $totalAmountPaid;
        } elseif ($totalAmountPaid > 0) {
            $totalAPayerLabel = 'Reste à payer :';
            $totalAPayerColor = 'orange';
            $montantAAfficher = $totalAmount - $totalAmountPaid;
        } else {
            $totalAPayerLabel = 'À payer :';
            $totalAPayerColor = 'red';
            $montantAAfficher = $totalAmount;
        }
        $totalAPayerHtml = "<strong style=\"color: $totalAPayerColor;\">$totalAPayerLabel</strong>";
        $totalAPayerText = $totalAPayerLabel;

        $buildLink = new BuildLink();
        $tpl = $this->templateService->render($mailTemplate, [
            'name' => $reservation->getFirstName(),
            'token' => $reservation->getToken(),
            'IDreservation' => 'ARA-' . str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT),
            'EventName' => $reservation->getEventObject()->getName(),
            'DateEvent' => $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y \à H\hi'),
            'DoorsOpen' => $reservation->getEventSessionObject()->getOpeningDoorsAt()->format('d/m/Y \à \p\a\r\t\i\r \d\e H\hi'),
            'Piscine' => $reservation->getEventObject()->getPiscine()->getLabel() . ' (' . $reservation->getEventObject()->getPiscine()->getAddress() . ' )',
            'ReservationNameFirstname' => $reservation->getName() . ' ' . $reservation->getFirstName(),
            'ReservationEmail' => $reservation->getEmail(),
            'ReservationPhone' => !empty($reservation->getPhone()) ? $reservation->getPhone() : '-',
            'ReservationNbTotalPlace' => count($reservation->getDetails()),
            'AffichRecapDetailPlaces' => $recapHtml,
            'AffichRecapDetailPlacesText' => $recapText,
            'UrlModifData' => $buildLink->buildResetLink('/modifData', $reservation->getToken()),
            'TotalAPayer' => $totalAPayerHtml,
            'TotalAPayerText' => $totalAPayerText,
            'ReservationMontantTotal' => number_format($montantAAfficher / 100, 2, ',', ' ') . ' €',
            'SIGNATURE' => SIGNATURE,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ]);
        if (!$tpl) return false;

        return $this->mailService->sendMessage(
            $reservation->getEmail(),
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText()
        );

    }


    /**
     * Pour envoyer un email de confirmation d'annulation de réservation.
     *
     * @param Reservation $reservation
     * @param string $templateEmail
     * @return bool
     */
    public function sendCancelReservationConfirmationEmail(Reservation $reservation, string $templateEmail): bool
    {

        $tpl = $this->templateService->render($templateEmail, [
            'IDreservation' => str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT),
            'SIGNATURE' => SIGNATURE,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ]);
        if (!$tpl) return false;

        return $this->mailService->sendMessage(
            $reservation->getEmail(),
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText()
        );

    }


}
