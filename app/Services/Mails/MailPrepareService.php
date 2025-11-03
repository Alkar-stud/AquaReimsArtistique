<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Services\Reservation\PaymentStatusCalculator;
use app\Services\Reservation\ReservationSummaryBuilder;
use app\Utils\BuildLink;
use app\Utils\QRCode;

readonly class MailPrepareService
{
    public function __construct(
        private MailService $mailService = new MailService(),
        private MailTemplateService $templateService = new MailTemplateService(),
        private ReservationSummaryBuilder $summaryBuilder = new ReservationSummaryBuilder(),
        private PaymentStatusCalculator $paymentCalculator = new PaymentStatusCalculator()
    ) {}

    /**
     * @param string $recipientEmail
     * @param string $username
     * @param string $resetLink
     * @return bool
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $username, string $resetLink): bool
    {
        $params = $this->buildPasswordResetParams($username, $resetLink);
        return $this->sendTemplatedEmail($recipientEmail, 'password_reset', $params);
    }

    /**
     * @param string $recipientEmail
     * @param string $username
     * @return bool
     */
    public function sendPasswordModifiedEmail(string $recipientEmail, string $username): bool
    {
        $params = $this->buildPasswordModifiedParams($username);
        return $this->sendTemplatedEmail($recipientEmail, 'password_modified', $params);
    }

    /**
     * @param Reservation $reservation
     * @param string $mailTemplate
     * @return bool
     */
    public function sendReservationConfirmationEmail(Reservation $reservation, string $mailTemplate = 'paiement_confirme', ?string $pdfPath = null): bool
    {
        $params = $this->buildReservationEmailParams($reservation);
        $tpl = $this->templateService->render($mailTemplate, $params);
        if (!$tpl) {
            return false;
        }

        // Nom du PDF pour la PJ
        $pdfName = 'Recapitulatif_ARA-' . str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) . '.pdf';

        // Attachement inline du QR code d'entrée
        return $this->mailService->sendMessageWithInlineImage(
            $reservation->getEmail(),
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText(),
            $params['qrcodeEntrance'], // Données binaires
            'qrcode_entrance',         // CID
            'qrcode_entrance.png',      // Nom du fichier
            $pdfPath,                  // Chemin du PDF
            $pdfName                   // Nom du PDF
        );
        //return $this->sendTemplatedEmail($reservation->getEmail(), $mailTemplate, $params);
    }

    /**
     * @param Reservation $reservation
     * @param string $templateEmail
     * @return bool
     */
    public function sendCancelReservationConfirmationEmail(Reservation $reservation, string $templateEmail): bool
    {
        $params = $this->buildCancelReservationParams($reservation);
        return $this->sendTemplatedEmail($reservation->getEmail(), $templateEmail, $params);
    }

    // Méthodes privées pour construire les paramètres

    /**
     * @param string $username
     * @param string $resetLink
     * @return string[]
     */
    private function buildPasswordResetParams(string $username, string $resetLink): array
    {
        return [
            'username' => $username,
            'link' => $resetLink,
        ];
    }

    /**
     * @param string $username
     * @return array
     */
    private function buildPasswordModifiedParams(string $username): array
    {
        return [
            'username' => $username,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ];
    }

    /**
     * @param Reservation $reservation
     * @return array
     */
    public function buildReservationEmailParams(Reservation $reservation): array
    {
        $recap = $this->summaryBuilder->buildFullRecap($reservation);
        $payment = $this->paymentCalculator->calculate($reservation);
        // Générer le QR code
        $buildLink = new BuildLink();
        $qrCodeUrlModif = $buildLink->buildResetLink('/modifData', $reservation->getToken());
        $qrCodeUrlEntrance = $buildLink->buildResetLink('/entrance', $reservation->getToken());

        // Génération du QR code pour modification (fichier temporaire pour PDF)
        $qrcodeModifPath = QRCode::generate($qrCodeUrlModif, 250, 10);

        // Générer le QR code d'entrée et créer directement la balise HTML
        $qrcodeEntranceBinary = QRCode::generateBinary($qrCodeUrlEntrance, 250, 10);

        return [
            'name' => $reservation->getFirstName(),
            'token' => $reservation->getToken(),
            'qrcodeModif' => $qrcodeModifPath,
            'qrcodeEntrance' => $qrcodeEntranceBinary,
            'qrcodeEntranceInMail' => '<img src="cid:qrcode_entrance" alt="QR Code d\'entrée" style="max-width: 250px; height: auto; display: block; margin: 20px auto;" />Montrez ce QR code pour faciliter votre entrée',
            'IDreservation' => 'ARA-' . str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT),
            'EventName' => $reservation->getEventObject()->getName(),
            'DateEvent' => $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y \à H\hi'),
            'DoorsOpen' => $reservation->getEventSessionObject()->getOpeningDoorsAt()->format('d/m/Y \à \p\a\r\t\i\r \d\e H\hi'),
            'Piscine' => $reservation->getEventObject()->getPiscine()->getLabel() . ' (' . $reservation->getEventObject()->getPiscine()->getAddress() . ' )',
            'ReservationNameFirstname' => $reservation->getName() . ' ' . $reservation->getFirstName(),
            'ReservationEmail' => $reservation->getEmail(),
            'ReservationPhone' => !empty($reservation->getPhone()) ? $reservation->getPhone() : '-',
            'ReservationNbTotalPlace' => count($reservation->getDetails()),
            'AffichRecapDetailPlaces' => $recap['html'],
            'AffichRecapDetailPlacesText' => $recap['text'],
            'UrlModifData' => $buildLink->buildResetLink('/modifData', $reservation->getToken()),
            'TotalAPayer' => $payment['labelHtml'],
            'TotalAPayerText' => $payment['label'],
            'ReservationMontantTotal' => number_format($payment['amount'] / 100, 2, ',', ' ') . ' €',
            'SIGNATURE' => SIGNATURE,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ];
    }

    /**
     * @param Reservation $reservation
     * @return array
     */
    private function buildCancelReservationParams(Reservation $reservation): array
    {
        return [
            'IDreservation' => str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT),
            'SIGNATURE' => SIGNATURE,
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
        ];
    }

    /**
     * @param string $recipientEmail
     * @param string $templateCode
     * @param array $params
     * @return bool
     */
    private function sendTemplatedEmail(string $recipientEmail, string $templateCode, array $params): bool
    {
        $tpl = $this->templateService->render($templateCode, $params);
        if (!$tpl) {
            return false;
        }

        return $this->mailService->sendMessage(
            $recipientEmail,
            $tpl->getSubject(),
            $tpl->getBodyHtml(),
            $tpl->getBodyText()
        );
    }
}
