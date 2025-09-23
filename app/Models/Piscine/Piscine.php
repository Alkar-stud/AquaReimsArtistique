<?php

namespace app\Models\Piscine;

use app\Models\AbstractModel;

class Piscine extends AbstractModel
{
    private string $libelle;
    private string $adresse;
    private int $max_places;
    private bool $numbered_seats;

    // --- GETTERS ---
    public function getLibelle(): string { return $this->libelle; }
    public function getAdresse(): string { return $this->adresse; }
    public function getMaxPlaces(): int { return $this->max_places; }
    public function getNumberedSeats(): bool { return $this->numbered_seats; }

    // --- SETTERS ---
    public function setLibelle(string $libelle): self { $this->libelle = $libelle; return $this; }
    public function setAdresse(string $adresse): self { $this->adresse = $adresse; return $this; }
    public function setMaxPlaces(int $max_places): self { $this->max_places = $max_places; return $this; }
    public function setNumberedSeats($numbered_seats): self { $this->numbered_seats = (bool)$numbered_seats; return $this; }

}
