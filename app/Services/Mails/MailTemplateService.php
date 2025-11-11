<?php

namespace app\Services\Mails;

use app\Models\Mail\MailTemplate;
use app\Repository\Mail\MailTemplateRepository;
use DateMalformedStringException;

readonly class MailTemplateService
{
    public function __construct(
        private MailTemplateRepository $templateRepository = new MailTemplateRepository()
    ) {}

    /**
     * Charge un template par code et remplace {placeholders}.
     */
    public function render(string $templateCode, array $params = []): ?MailTemplate
    {
        $template = $this->templateRepository->findByCode($templateCode);
        if (!$template) {
            error_log("MailTemplateService: template '$templateCode' introuvable.");
            return null;
        }

        $subject = $template->getSubject();
        $bodyHtml = $template->getBodyHtml();
        $bodyText = $template->getBodyText();

        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, (string)$value, $subject);
            if ($bodyHtml !== null) {
                $bodyHtml = str_replace($placeholder, (string)$value, $bodyHtml);
            }
            if ($bodyText !== null) {
                $bodyText = str_replace($placeholder, (string)$value, $bodyText);
            }
        }

        $template->setSubject($subject);
        $template->setBodyHtml($bodyHtml);
        $template->setBodyText($bodyText);

        return $template;
    }
}
