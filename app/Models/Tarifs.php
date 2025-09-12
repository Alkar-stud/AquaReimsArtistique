<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class Tarifs
{
    private int $id;
    private string $libelle;
    private ?string $description = null;
    private ?int $nb_place = null;
    private ?int $age_min = null;
    private ?int $age_max = null;
    private ?int $max_tickets = null;
    private int $price;
    private bool $is_program_show_include;
    private bool $is_proof_required;
    private ?string $access_code = null;
    private bool $is_active;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---
    public function getId(): int { return $this->id; }
    public function getLibelle(): string { return $this->libelle; }
    public function getDescription(): ?string { return $this->description; }
    public function getNbPlace(): ?int { return $this->nb_place; }
    public function getAgeMin(): ?int { return $this->age_min; }
    public function getAgeMax(): ?int { return $this->age_max; }
    public function getMaxTickets(): ?int { return $this->max_tickets; }
    public function getPrice(): int { return $this->price; }
    public function getIsProgramShowInclude(): bool { return $this->is_program_show_include; }
    public function getIsProofRequired(): bool { return $this->is_proof_required; }
    public function getAccessCode(): ?string { return $this->access_code; }
    public function getIsActive(): bool { return $this->is_active; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    // --- SETTERS ---
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setNbPlace(?int $nb_place): self
    {
        $this->nb_place = $nb_place;
        return $this;
    }

    public function setAgeMin(?int $age_min): self
    {
        $this->age_min = $age_min;
        return $this;
    }

    public function setAgeMax(?int $age_max): self
    {
        $this->age_max = $age_max;
        return $this;
    }

    public function setMaxTickets(?int $max_tickets): self
    {
        $this->max_tickets = $max_tickets;
        return $this;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function setIsProgramShowInclude(bool $is_program_show_include): self
    {
        $this->is_program_show_include = $is_program_show_include;
        return $this;
    }

    public function setIsProofRequired(bool $is_proof_required): self
    {
        $this->is_proof_required = $is_proof_required;
        return $this;
    }

    public function setAccessCode(?string $access_code): self
    {
        // Si la chaîne est vide, on la transforme en null
        $this->access_code = empty($access_code) ? null : $access_code;
        return $this;
    }

    public function setIsActive(bool $is_active): self
    {
        $this->is_active = $is_active;
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