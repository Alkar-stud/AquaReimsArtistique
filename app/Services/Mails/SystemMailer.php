<?php
namespace app\Services\Mails;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class SystemMailer
{
    private string $from;
    private string $fromName;

    public function __construct()
    {
        $this->from = $_ENV['ALERT_FROM'] ?? ($_ENV['MAIL_FROM_ADDRESS'] ?? '');
        $this->fromName = $_ENV['ALERT_FROM_NAME'] ?? ($_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique');
    }

    /**
     * Envoi simple (système) : n'utilise pas le MailHistoryService et n'émet pas d'événement.
     * Retourne true si envoi ok, false sinon.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            // Si configuration SMTP présente, l'utiliser
            if (!empty($_ENV['MAIL_HOST']) && !empty($_ENV['MAIL_USERNAME'])) {
                $mail->isSMTP();
                $mail->Host = $_ENV['MAIL_HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['MAIL_USERNAME'];
                $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
                $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
                $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);
            }

            if ($this->from !== '') {
                $mail->setFrom($this->from, $this->fromName);
            }

            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->isHTML(false);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('SystemMailer error: ' . $e->getMessage());
            return false;
        }
    }
}