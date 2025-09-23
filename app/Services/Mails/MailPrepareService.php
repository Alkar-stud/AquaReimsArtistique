<?php

namespace app\Services\Mails;

readonly class MailPrepareService
{
    public function __construct(
        private MailService         $mailService = new MailService(),
        private MailTemplateService $templateService = new MailTemplateService()
    ) {}

    /**
     * Envoie un email de réinitialisation de mot de passe.
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
}
