<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Repository\Mail\MailTemplateRepository;
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
    private MailHistoryService $mailHistoryService;
    private MailPrepareService $mailPrepareService;

    /**
     * Transport SMTP + log.
     * - logOnly si MAIL_MAILER='log' -> pas d'envoi, log uniquement.
     * - debug si MAIL_DEBUG='true' -> log en plus de l'envoi (et SMTPDebug).
     * @throws Exception
     */
    public function __construct(
        MailHistoryService $mailHistoryService,
        MailPrepareService $mailPrepareService,
        MailTemplateRepository $mailTemplateRepository = new MailTemplateRepository(),
    )
    {
        $this->mailHistoryService = $mailHistoryService;
        $this->mailPrepareService = $mailPrepareService;
        $this->mailTemplateRepository = $mailTemplateRepository;

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
        $this->configSMTP();
    }


    /**
     * Pour recevoir toutes les demandes d'envoi de mail.
     * Se charge de récupérer le template, de demander à le remplir et à envoyer
     *
     * @param string $templateCode
     * @param array $contextData
     * @param string $recipientEmail
     * @param string $codeEventLog
     * @return bool
     * @throws Exception
     */
    public function send(string $templateCode, array $contextData, string $recipientEmail, string $codeEventLog = 'unexpected.send_attempt'): bool
    {
        // Création du mail
        $this->mailer = $this->createMailer();

        // On prépare le mail
        $email = $this->mailPrepareService->prepareEmail($this->mailer, $templateCode, $contextData, $recipientEmail);

        if (!$email) {
            return false;
        }

        // On envoi le mail
        try {
            $email->send();
            //echo 'envoi du mail commenté !';
            //On trace le mail dans la BDD selon s'il s'agit pour une réservation ou pas
            if (isset($contextData['reservation'])) {
                $this->mailHistoryService->recordMailSentForReservation(
                    $contextData['reservation'],
                    $templateCode
                );
            }

            //On trace le mail général
            $this->mailHistoryService->logMailSent([
                'templateCode' => $templateCode,
                'recipient' => $recipientEmail,
            ],
                $codeEventLog,
                true
            );

            return true;
        } catch (Exception $e) {
            //On trace l'erreur
            $this->mailHistoryService->logMailSent([
                'templateCode' => $templateCode,
                'recipient' => $recipientEmail,
                'erreur' => "{$this->mailer->ErrorInfo} - $e"
            ],
                $codeEventLog,
                false
            );
            error_log("Mailer Error: {$this->mailer->ErrorInfo} - $e");
            return false;
        }
    }

    /**
     * Création du mail
     *
     * @return PHPMailer
     * @throws Exception
     */
    private function createMailer(): PHPMailer
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = 'UTF-8';
        $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? null;
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique';
        if (!$fromEmail) {
            throw new Exception("MAIL_FROM_ADDRESS manquant.");
        }
        try {
            $this->mailer->setFrom($fromEmail, $fromName);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la configuration de l'adresse d'expédition: " . $e->getMessage());
        }

        $this->resetMailer();

        $this->configSMTP();

        return $this->mailer;
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
        return $this->mailHistoryService->recordMailSentForReservation($reservation, $templateMailCode);

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
     * Reset du mailer pour éviter les problèmes de connexion SMTP entre envois, et réinitialisation des destinataires, pièces jointes, etc.
     *
     * @return void
     */
    private function resetMailer(): void
    {
        $this->mailer->clearAddresses();
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->clearReplyTos();

    }

    /**
     * Configuration du SMTP
     *
     * @return void
     */
    private function configSMTP(): void
    {
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

}
