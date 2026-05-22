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
            $replacement = $this->valueToString($value);

            $subject = str_replace($placeholder, $replacement, $subject);
            if ($bodyHtml !== null) {
                $bodyHtml = str_replace($placeholder, $replacement, $bodyHtml);
            }
            if ($bodyText !== null) {
                $bodyText = str_replace($placeholder, $replacement, $bodyText);
            }
        }

        $template->setSubject($subject);
        $template->setBodyHtml($bodyHtml);
        $template->setBodyText($bodyText);

        return $template;
    }

    private function valueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value)) {
            // Si l'objet possède __toString, l'utiliser ; sinon, sérialiser proprement en JSON
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            return $encoded === false ? '' : $encoded;
        }

        if (is_array($value)) {
            // Aplatir récursivement en chaîne lisible
            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->valueToString($item);
            }
            return implode(', ', $parts);
        }

        // Valeur inconnue : fallback
        return '';
    }
}