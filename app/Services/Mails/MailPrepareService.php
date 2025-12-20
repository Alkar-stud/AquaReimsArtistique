<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Services\Reservation\PaymentStatusCalculator;
use app\Services\Reservation\ReservationSummaryBuilder;
use app\Utils\BuildLink;
use app\Utils\QRCode;
use app\Utils\StringHelper;

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
     * @param string|null $pdfPath
     * @return bool
     */
    public function sendReservationConfirmationEmail(Reservation $reservation, string $mailTemplate = 'paiement_confirme', ?string $pdfPath = null): bool
    {
        $params = $this->buildReservationEmailParams($reservation, $mailTemplate == 'final_summary');
        $tpl = $this->templateService->render($mailTemplate, $params);
        if (!$tpl) {
            return false;
        }

        // Nom du PDF pour la PJ
        $pdfName = 'Recapitulatif_' . StringHelper::generateReservationNumber($reservation->getId()) . '.pdf';

        // Préparer le chemin du QR code inline si besoin
        $inlineImagePath = $params['qrcodeEntrance'] ?? null;
        $tempFileToRemove = null;

        // Si on reçoit du binaire, écrire dans un fichier temporaire
        if (is_string($inlineImagePath) && $inlineImagePath !== '' && !is_file($inlineImagePath)) {
            // On suppose que c'est un binaire ; écrire en fichier temporaire
            $tmp = sys_get_temp_dir() . '/qrcode_' . uniqid() . '.png';
            if (false === @file_put_contents($tmp, $inlineImagePath)) {
                error_log(sprintf('Impossible d\'écrire le binaire QR code pour reservation id=%d', $reservation->getId()));
                // fallback : envoyer sans image inline
                return $this->sendTemplatedEmail($reservation->getEmail(), $mailTemplate, $params);
            }
            $inlineImagePath = $tmp;
            $tempFileToRemove = $tmp;
        }

        // Si pas de fichier valide, fallback sur envoi sans image
        if (!is_string($inlineImagePath) || !is_file($inlineImagePath)) {
            error_log(sprintf('QR code manquant ou non accessible pour la réservation id=%d, envoi sans image.', $reservation->getId()));
            return $this->sendTemplatedEmail($reservation->getEmail(), $mailTemplate, $params);
        }

        try {
            return $this->mailService->sendMessageWithInlineImage(
                $reservation->getEmail(),
                $tpl->getSubject(),
                $tpl->getBodyHtml(),
                $tpl->getBodyText(),
                $inlineImagePath,        // chemin du fichier PNG pour l'image inline
                'qrcode_entrance',       // CID
                'qrcode_entrance.png',   // Nom du fichier
                $pdfPath,                // Chemin du PDF
                $pdfName                 // Nom du PDF
            );
        } catch (\Throwable $e) {
            error_log(sprintf('Erreur envoi mail pour reservation id=%d : %s', $reservation->getId(), $e->getMessage()));
            return false;
        } finally {
            if ($tempFileToRemove && is_file($tempFileToRemove)) {
                @unlink($tempFileToRemove);
            }
        }
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
     * @param bool $needQrCode
     * @return array
     */
    public function buildReservationEmailParams(Reservation $reservation, bool $needQrCode = false): array
    {
        $recap = $this->summaryBuilder->buildFullRecap($reservation);
        $payment = $this->paymentCalculator->calculate($reservation);
        $buildLink = new BuildLink();
        $qrCodeUrlModif = $buildLink->buildResetLink('/modifData', $reservation->getToken());
        $qrCodeUrlEntrance = $buildLink->buildResetLink('/entrance', $reservation->getToken());

        if ($needQrCode) {
            // Génération du QR code pour modification (fichier temporaire pour PDF)
            $qrcodeModifPath = QRCode::generate($qrCodeUrlModif, 250, 10);

            // Génération du QR code d'entrée : on retourne un chemin de fichier (pour l'inline)
            $qrcodeEntrancePath = QRCode::generate($qrCodeUrlEntrance, 250, 10);

            //Génération pour le QRCode dans le mail
            $qrcodeEntranceInMail = '<img src="cid:qrcode_entrance" alt="QR Code d\'entrée" style="max-width: 250px; height: auto; display: block; margin: 20px auto;" />Montrez ce QR code pour faciliter votre entrée';

        } else {
            $qrcodeModifPath = null;
            $qrcodeEntrancePath = null;
            $qrcodeEntranceInMail = '';
        }

        return [
            'name' => $reservation->getFirstName(),
            'token' => $reservation->getToken(),
            'qrcodeModif' => $qrcodeModifPath,
            'qrcodeEntrance' => $qrcodeEntrancePath, // chemin du PNG
            'qrcodeEntranceInMail' => $qrcodeEntranceInMail,
            'IDreservation' => StringHelper::generateReservationNumber($reservation->getId()),
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
            'email_gala' => defined('EMAIL_GALA') ? EMAIL_GALA : '',
        ];
    }

    /**
     * @param Reservation $reservation
     * @return array
     */
    private function buildCancelReservationParams(Reservation $reservation): array
    {
        return [
            'IDreservation' => StringHelper::generateReservationNumber($reservation->getId()),
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
