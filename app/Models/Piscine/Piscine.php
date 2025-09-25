<?php

namespace app\Models\Piscine;

use app\Models\AbstractModel;

class Piscine extends AbstractModel
{
    private string $libelle;
    private ?string $address = null;
    private int $max_places;
    private bool $numbered_seats;

    // --- GETTERS ---
    public function getLabel(): string { return $this->libelle; }
    public function getAddress(): ?string { return $this->address; }
    public function getMaxPlaces(): int { return $this->max_places; }
    public function getNumberedSeats(): bool { return $this->numbered_seats; }

    // --- SETTERS ---
    public function setLabel(string $label): self { $this->libelle = $label; return $this; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function setMaxPlaces(int $max_places): self { $this->max_places = $max_places; return $this; }
    public function setNumberedSeats($numbered_seats): self { $this->numbered_seats = (bool)$numbered_seats; return $this; }

}
