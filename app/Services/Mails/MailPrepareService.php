<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Repository\Mail\MailTemplateRepository;
use app\Services\Pdf\PdfGenerationService;
use app\Services\Reservation\PaymentStatusCalculator;
use app\Services\Reservation\ReservationSummaryBuilder;
use app\Utils\BuildLink;
use app\Utils\QRCode;
use app\Utils\StringHelper;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

readonly class MailPrepareService
{
    private MailTemplateKeyExtractor $mailTemplateKeyExtractor;
    private MailTemplateRepository $mailTemplateRepository;
    private MailTemplateRenderer $mailTemplateRenderer;
    private ContextDataResolver $contextDataResolver;
    private PdfGenerationService $pdfGenerationService;

    public function __construct(
        MailTemplateRepository $mailTemplateRepository,
        MailTemplateKeyExtractor $mailTemplateKeyExtractor,
        MailTemplateRenderer $mailTemplateRenderer,
        ContextDataResolver $contextDataResolver,
        PdfGenerationService $pdfGenerationService,
        private ReservationSummaryBuilder $summaryBuilder,
        private PaymentStatusCalculator $paymentCalculator
    )
    {
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mailTemplateKeyExtractor = $mailTemplateKeyExtractor;
        $this->mailTemplateRenderer = $mailTemplateRenderer;
        $this->contextDataResolver = $contextDataResolver;
        $this->pdfGenerationService = $pdfGenerationService;
    }

    /**
     * Pour préparer un email à partir d'un template donné
     *
     * @param PHPMailer $mailer
     * @param string $templateCode
     * @param array $params
     * @param string $recipientEmail
     * @return array|false
     * @throws Exception
     */
    public function prepareEmail(PHPMailer $mailer, string $templateCode, array $params, string $recipientEmail): PHPMailer|false
    {
        // Récupérer le template
        $template = $this->mailTemplateRepository->findByCode($templateCode);
        if (!$template) {
            return false;
        }
        //Récupérer les clés nécessaires :
        $keys = $this->mailTemplateKeyExtractor->extract($template);

        // Résoudre les données du contexte
        $replacements = $this->contextDataResolver->resolve($params, $keys, $template->getRequiresResumeAttachment());

        // On attache les éventuelles PJ
        if ($template->getRequiresResumeAttachment()) {
            //On récupère le tableau de paramètres+valeur
            $paramsToPdf = $this->buildReservationEmailParams($params['reservation'], true);
            // Génération du PDF RecapFinal
            $pdf = $this->pdfGenerationService->generateUnitPdf('RecapFinal', $params['reservation']->getId(), $paramsToPdf);
            $pdfName = 'recap_' . $params['reservation']->getId() . '_' . uniqid() . '.pdf';
            $pdfPath = sys_get_temp_dir() . '/' . $pdfName;
            file_put_contents($pdfPath, $pdf->Output('S'));
            $mailer->addAttachment($pdfPath, $pdfName);
        }

       // Remplir le template
        $templateFilled =  $this->mailTemplateRenderer->render($template, $replacements);

        // On insère les éventuelles images inline dans le $templateFilled
        if (isset($replacements['qrcodeEntranceInMail']) && isset($replacements['qrcodeEntrance'])) {
            //$this->insertInlineImage($templateFilled, $replacements);
            if (is_file($replacements['qrcodeEntrance'])) {
                $mailer->addEmbeddedImage($replacements['qrcodeEntrance'], 'qrcode_entrance', 'qrcode_entrance.png');
            } else {
                error_log('MailPrepareService: image QR code non trouvée ou non accessible pour le mail, envoi sans image inline.');
            }
        }

        //On remplit le mail avec les données
        $mailer->addAddress($recipientEmail);
        $mailer->isHTML(true);
        $mailer->Subject = $templateFilled->getSubject();
        $mailer->Body = $templateFilled->getBodyHtml() ?? '';
        $mailer->AltBody = $templateFilled->getBodyText() ?? '';

        return $mailer;
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
            'SIGNATURE' => defined('SIGNATURE') ? SIGNATURE : '',
            'email_club' => defined('EMAIL_CLUB') ? EMAIL_CLUB : '',
            'email_gala' => defined('EMAIL_GALA') ? EMAIL_GALA : '',
        ];
    }
}
