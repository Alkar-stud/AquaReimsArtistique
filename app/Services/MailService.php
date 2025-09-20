<?php

namespace app\Services;

use app\Models\MailTemplate;
use app\Models\Reservation\Reservations;
use app\Repository\Event\EventsRepository;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\TarifRepository;
use DateMalformedStringException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    private PHPMailer $mailer;
    private MailTemplateRepository $templateRepository;

    /**
     * Configure PHPMailer en utilisant un tableau de configuration validé.
     * @throws Exception Si une clé de configuration requise est manquante.
     */
    public function __construct()
    {
        // Valider que toutes les variables d'environnement nécessaires existent.
        $requiredKeys = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_PORT', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredKeys as $key) {
            if (empty($_ENV[$key])) {
                // On lance une exception claire si une variable manque.
                throw new Exception("Configuration MailService manquante : la variable d'environnement $key n'est pas définie.");
            }
        }

        $this->mailer = new PHPMailer(true);
        $this->templateRepository = new MailTemplateRepository();

        // Utiliser les variables maintenant qu'on sait qu'elles existent.
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = (int)$_ENV['MAIL_PORT'];
        $this->mailer->CharSet = 'UTF-8';

        if ( (isset($_ENV['MAIL_MAILER']) && $_ENV['MAIL_MAILER'] === 'log') || isset($_ENV['MAIL_DEBUG']) && $_ENV['MAIL_DEBUG'] === 'true') {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique';
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $fromName);
    }

    /**
     * Envoie un email
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $templateCode
     * @param array $params
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function send(string $recipientEmail, string $templateCode, array $params = []): bool
    {
        // Récupérer le template rempli depuis la BDD
        $template = $this->retrieveTemplateAndFillIt($templateCode, $params);
        if (!$template) {
            return false;
        }

        // Construire et envoyer l'email
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template->getSubject();
            $this->mailer->Body    = $template->getBodyHtml();
            $this->mailer->AltBody = $template->getBodyText();
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo} - $e");
            return false;
        }
    }

    /**
     * Pour récupérer le template et le préparer pour l'envoi.
     *
     * @param string $templateCode
     * @param array $params
     * @return MailTemplate|null
     * @throws DateMalformedStringException
     */
    private function retrieveTemplateAndFillIt(string $templateCode, array $params = []): ?MailTemplate
    {
        // Récupérer le template depuis la BDD
        $template = $this->templateRepository->findByCode($templateCode);

        if (!$template) {
            error_log("MailService Error: Template '$templateCode' not found.");
            return null;
        }

        $subject = $template->getSubject();
        $bodyHtml = $template->getBodyHtml();
        $bodyText = $template->getBodyText();

        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);

            if ($bodyHtml) {
                $bodyHtml = str_replace($placeholder, $value, $bodyHtml);
            }
            if ($bodyText) {
                $bodyText = str_replace($placeholder, $value, $bodyText);
            }
        }

        $template->setSubject($subject);
        $template->setBodyHtml($bodyHtml);
        $template->setBodyText($bodyText);

        return $template;
    }

}