<?php

namespace app\Models\Tarif;

use app\Models\AbstractModel;

class Tarif extends AbstractModel
{
    private string $name;
    private ?string $description = null;
    private ?int $seat_count = null;
    private ?int $min_age = null;
    private ?int $max_age = null;
    private ?int $max_tickets = null;
    private int $price;
    private bool $includes_program;
    private bool $requires_proof;
    private ?string $access_code = null;
    private bool $is_active;

    // --- GETTERS ---
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getSeatCount(): ?int { return $this->seat_count; }
    public function getMinAge(): ?int { return $this->min_age; }
    public function getMaxAge(): ?int { return $this->max_age; }
    public function getMaxTickets(): ?int { return $this->max_tickets; }
    public function getPrice(): int { return $this->price; }
    public function getIncludesProgram(): bool { return $this->includes_program; }
    public function getRequiresProof(): bool { return $this->requires_proof; }
    public function getAccessCode(): ?string { return $this->access_code; }
    public function getIsActive(): bool { return $this->is_active; }

    // --- SETTERS ---
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setSeatCount(?int $seat_count): self { $this->seat_count = $seat_count; return $this; }
    public function setMinAge(?int $min_age): self { $this->min_age = $min_age; return $this; }
    public function setMaxAge(?int $max_age): self { $this->max_age = $max_age; return $this; }
    public function setMaxTickets(?int $max_tickets): self { $this->max_tickets = $max_tickets; return $this; }
    public function setPrice(int $price): self { $this->price = $price; return $this; }
    public function setIncludesProgram(bool $includes_program): self { $this->includes_program = $includes_program; return $this; }
    public function setRequiresProof(bool $requires_proof): self { $this->requires_proof = $requires_proof; return $this; }
    public function setAccessCode(?string $access_code): self { $this->access_code = empty($access_code) ? null : $access_code; return $this; }
    public function setIsActive(bool $is_active): self { $this->is_active = $is_active; return $this; }

}
