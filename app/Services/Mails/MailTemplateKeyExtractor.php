<?php

namespace app\Services\Mails;

use app\Models\Mail\MailTemplate;

class MailTemplateKeyExtractor {
    public function extract(MailTemplate $template): array {
        $fullContent =
            $template->getSubject() . ' ' .
            ($template->getBodyHtml() ?? '') . ' ' .
            ($template->getBodyText() ?? '');

        preg_match_all('/\{(\w+)\}/', $fullContent, $matches);
        return array_unique($matches[1] ?? []);
    }
}