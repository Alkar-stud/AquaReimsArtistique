<?php

namespace app\Models;

use DateTime;
use DateTimeInterface;

class Accueil
{
    private int $id;
    private bool $is_displayed = false;
    private DateTimeInterface $display_until;
    private ?string $content;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    public function getId(): int { return $this->id; }
    public function isDisplayed(): bool { return $this->is_displayed; }
    public function getDisplayUntil(): DateTimeInterface { return $this->display_until; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    public function setId(int $id): self { $this->id = $id; return $this; }
    public function setIsdisplayed(bool $is_displayed): self { $this->is_displayed = $is_displayed; return $this; }
    public function setDisplayUntil(string $display_until): self { $this->display_until = new DateTime($display_until); return $this; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function setCreatedAt(string $created_at): self { $this->created_at = new DateTime($created_at); return $this; }
    public function setUpdatedAt(?string $updated_at): self { $this->updated_at = $updated_at ? new DateTime($updated_at) : null; return $this; }
}