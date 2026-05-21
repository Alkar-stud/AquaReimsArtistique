<?php

namespace app\Services\Mails;

use app\Models\Mail\MailTemplate;

class MailTemplateRenderer
{
    /**
     * Charge un template par code et remplace {placeholders}.
     *
     * @param MailTemplate $template
     * @param array $params
     * @return MailTemplate|null
     */
    public function render(MailTemplate $template, array $params = []): ?MailTemplate
    {
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