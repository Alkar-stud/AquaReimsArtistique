<?php

namespace app\Services\Mails;

use app\Services\Log\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    private PHPMailer $mailer;
    private bool $logOnly;
    private bool $debug;

    /**
     * Transport SMTP + log.
     * - logOnly si MAIL_MAILER='log' -> pas d'envoi, log uniquement.
     * - debug si MAIL_DEBUG='true' -> log en plus de l'envoi (et SMTPDebug).
     * @throws Exception
     */
    public function __construct()
    {
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
}
