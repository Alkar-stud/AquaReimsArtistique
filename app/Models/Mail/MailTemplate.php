<?php

namespace app\Models\Mail;

use app\Models\AbstractModel;

class MailTemplate extends AbstractModel
{
    private string $code;
    private string $subject;
    private ?string $body_html = null;
    private ?string $body_text = null;

    // --- GETTERS ---
    public function getCode(): string { return $this->code; }
    public function getSubject(): string { return $this->subject; }
    public function getBodyHtml(): ?string { return $this->body_html; }
    public function getBodyText(): ?string { return $this->body_text; }

    // --- SETTERS
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function setSubject(string $subject): self { $this->subject = $subject; return $this; }

    public function setBodyHtml(?string $body_html): self
    {
        $this->body_html = $body_html;
        return $this;
    }

    public function setBodyText(?string $body_text): self
    {
        $this->body_text = $body_text;
        return $this;
    }

}