<?php

namespace app\Services;

use app\Repository\MailTemplateRepository;
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
        // 1. Valider que toutes les variables d'environnement nécessaires existent.
        $requiredKeys = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_PORT', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredKeys as $key) {
            if (empty($_ENV[$key])) {
                // On lance une exception claire si une variable manque.
                throw new Exception("Configuration MailService manquante : la variable d'environnement {$key} n'est pas définie.");
            }
        }

        $this->mailer = new PHPMailer(true);
        $this->templateRepository = new MailTemplateRepository();

        // 2. Utiliser les variables maintenant qu'on sait qu'elles existent.
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = (int)$_ENV['MAIL_PORT'];
        $this->mailer->CharSet = 'UTF-8';

        if (isset($_ENV['MAIL_MAILER']) && $_ENV['MAIL_MAILER'] === 'log') {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique';
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $fromName);
    }

    /**
     * Envoie un email de réinitialisation de mot de passe.
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $templateCode
     * @param array $params
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function send(string $recipientEmail, string $templateCode, array $params = []): bool
    {
        // Récupérer le template depuis la BDD
        $template = $this->templateRepository->findByCode($templateCode);

        if (!$template) {
            error_log("MailService Error: Template '{$templateCode}' not found.");
            return false;
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

        // Envoyer l'email
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $bodyHtml;
            $this->mailer->AltBody = $bodyText;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Envoie un email de réinitialisation de mot de passe.
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $username Le nom de l'utilisateur pour la personnalisation.
     * @param string $resetLink Le lien de réinitialisation à inclure.
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $username, string $resetLink): bool
    {
        // On appelle la méthode générique avec les bons paramètres
        return $this->send($recipientEmail, 'password_reset', [
            'username' => $username,
            'link' => $resetLink
        ]);
    }

    /**
     * Envoie un email suite changement du mot de passe
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $username Le nom de l'utilisateur pour la personnalisation.
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendPasswordModifiedEmail(string $recipientEmail, string $username): bool
    {
        // On appelle la méthode générique avec les bons paramètres
        return $this->send($recipientEmail, 'password_modified', [
            'username' => $username,
            'email_club' => EMAIL_CLUB
        ]);
    }
}