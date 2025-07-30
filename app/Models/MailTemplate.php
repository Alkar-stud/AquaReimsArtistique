<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class MailTemplate
{
    private int $id;
    private string $code;
    private string $subject;
    private ?string $body_html = null;
    private ?string $body_text = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBodyHtml(): ?string
    {
        return $this->body_html;
    }

    public function getBodyText(): ?string
    {
        return $this->body_text;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    // --- SETTERS

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

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

    /**
     * @throws DateMalformedStringException
     */
    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }
}