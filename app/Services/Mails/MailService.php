<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Services\Log\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use app\Utils\MailSmtpDebugLogger;

class MailService
{
    private PHPMailer $mailer;
    private bool $logOnly;
    private bool $debug;
    private MailTemplateRepository $mailTemplateRepository;
    private ReservationMailSentRepository $reservationMailSentRepository;

    /**
     * Transport SMTP + log.
     * - logOnly si MAIL_MAILER='log' -> pas d'envoi, log uniquement.
     * - debug si MAIL_DEBUG='true' -> log en plus de l'envoi (et SMTPDebug).
     * @throws Exception
     */
    public function __construct(
        MailTemplateRepository $mailTemplateRepository = new MailTemplateRepository(),
        ReservationMailSentRepository $reservationMailSentRepository = new ReservationMailSentRepository(),
    )
    {
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->reservationMailSentRepository = $reservationMailSentRepository;
        $this->logOnly = (($_ENV['MAIL_MAILER'] ?? '') === 'log');
        $this->debug = (($_ENV['MAIL_DEBUG'] ?? 'false') === 'true');

        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = 'UTF-8';

        $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? null;
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique';
        if (!$fromEmail) {
            throw new Exception("MAIL_FROM_ADDRESS manquant.");
        }
        $this->mailer->setFrom($fromEmail, $fromName);

        if ($this->logOnly) {
            return; // Pas de config SMTP en mode log.
        }

        // Validation config SMTP
        foreach (['MAIL_HOST','MAIL_USERNAME','MAIL_PASSWORD','MAIL_ENCRYPTION','MAIL_PORT'] as $key) {
            if (empty($_ENV[$key])) {
                throw new Exception("Configuration SMTP manquante: $key.");
            }
        }

        // Config SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = (int)$_ENV['MAIL_PORT'];

        if ($this->debug) {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;

            $smtpLogger = new MailSmtpDebugLogger();
            $this->mailer->Debugoutput = function (string $str, int $level) use ($smtpLogger) {
                $smtpLogger->append($str, $level);
            };
        }
    }

    /**
     * Envoi d’un message déjà préparé.
     */
    public function sendMessage(string $recipientEmail, string $subject, ?string $htmlBody = null, ?string $textBody = null): bool
    {
        // Log en debug, ou en mode logOnly sans envoyer
        if ($this->debug || $this->logOnly) {
            $this->logMessage($recipientEmail, $subject, $htmlBody, $textBody);
        }
        if ($this->logOnly) {
            return true;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody ?? '';
            $this->mailer->AltBody = $textBody ?? '';
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo} - $e");
            return false;
        }
    }

    private function logMessage(string $to, string $subject, ?string $html, ?string $text): void
    {
        $logger = Logger::get();

        $context = [
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'log_only' => $this->logOnly,
            'debug' => $this->debug,
        ];

        if ($this->logOnly) {
            $logger->info('mail', 'log_only', $context);
        } else {
            $logger->debug('mail', 'prepared', $context);
        }
    }

    /**
     * Enregistre l'envoi d'un email pour une réservation.
     *
     * @param Reservation $reservation
     * @param string $templateMailCode
     * @return bool True si l'enregistrement a réussi, false sinon.
     */
    public function recordMailSent(Reservation $reservation, string $templateMailCode): bool
    {
        $template = $this->mailTemplateRepository->findByCode($templateMailCode);
        if (!$template) {
            Logger::get()->warning('mail', 'record_failure', [
                'message' => 'Mail template not found for recording.',
                'reservation_id' => $reservation->getId(),
                'template_code' => $templateMailCode
            ]);
            return false;
        }

        $mailSentRecord = new ReservationMailSent();
        $mailSentRecord->setReservation($reservation->getId())
            ->setMailTemplate($template->getId())
            ->setSentAt(date('Y-m-d H:i:s'));

        try {
            $id = $this->reservationMailSentRepository->insert($mailSentRecord);
            if ($id <= 0) {
                throw new \RuntimeException('Échec insertion mail sent record.');
            }
            return true;
        } catch (\Exception $e) {
            Logger::get()->error('mail', 'record_failure', [
                'message' => 'Failed to insert mail sent record.',
                'reservation_id' => $reservation->getId(),
                'template_code' => $templateMailCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Pour retourner les templates qu'il est possible d'envoyer manuellement
     *
     * @return array
     */
    public function emailsTemplatesToSendManually(): array
    {
        return $this->mailTemplateRepository->findByCodes([
            'paiement_confirme',
            'paiement_confirme_add',
            'summary',
            'final_summary',
            'paiement_relance_1',
        ]);
    }

    /**
     * Envoi d'un mail avec image inline (CID) et optionnellement un PDF en PJ.
     * - $imageData peut être un chemin de fichier PNG ou des données binaires (ou data URI).
     *
     * @param string $recipientEmail
     * @param string $subject
     * @param string|null $htmlBody
     * @param string|null $textBody
     * @param string|null $imageData
     * @param string|null $cid
     * @param string $filename
     * @param string|null $pdfPath
     * @param string $pdfName
     * @return bool
     */
    public function sendMessageWithInlineImage(
        string $recipientEmail,
        string $subject,
        ?string $htmlBody,
        ?string $textBody,
        ?string $imageData = null,
        ?string $cid = null,
        string $filename = 'image.png',
        ?string $pdfPath = null,
        string $pdfName = 'document.pdf'
    ): bool {
        if ($this->debug || $this->logOnly) {
            $this->logMessage($recipientEmail, $subject, $htmlBody, $textBody);
        }
        if ($this->logOnly) {
            return true;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody ?? '';
            $this->mailer->AltBody = $textBody ?? '';

            // Image inline via CID: chemin fichier -> addEmbeddedImage, sinon binaire/data URI -> addStringEmbeddedImage
            if ($imageData && $cid) {
                if (is_file($imageData)) {
                    // Chemin fichier
                    $this->mailer->addEmbeddedImage($imageData, $cid, '', 'base64', 'image/png', 'inline');
                } else {
                    // Binaire ou data URI
                    $binary = $imageData;
                    if (str_starts_with($imageData, 'data:image/')) {
                        $comma = strpos($imageData, ',');
                        $binary = $comma !== false ? base64_decode(substr($imageData, $comma + 1)) : '';
                    }
                    if ($binary !== '') {
                        $this->mailer->addStringEmbeddedImage($binary, $cid, '', 'base64', 'image/png', 'inline');
                    }
                }
            }

            // Pièce jointe PDF
            if ($pdfPath && is_file($pdfPath)) {
                $this->mailer->addAttachment($pdfPath, $pdfName);
            }

            $this->mailer->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo} - $e");
            return false;
        }
    }

}
