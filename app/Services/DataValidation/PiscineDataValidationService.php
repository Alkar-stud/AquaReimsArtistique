<?php

namespace app\Services\DataValidation;

class PiscineDataValidationService
{
    private ?string $label = null;
    private ?string $address = null;
    private ?int $max_places = null;
    private ?bool $numbered_seats = null;

    public function checkData(array $data): ?string
    {
        // Normalisation
        $this->label = trim($data['label'] ?? '');
        $addressValue = trim($data['address'] ?? '');
        $this->address = $addressValue !== '' ? $addressValue : null;
        $this->max_places = isset($data['capacity']) && is_numeric($data['capacity']) ? (int)$data['capacity'] : 0;
        $this->numbered_seats = ($data['numberedSeats'] ?? 'non') === 'oui';

        // Validation
        if (empty($this->label)) {
            return "Le nom de la piscine est obligatoire.";
        }
        if (!preg_match('/^[A-Za-z0-9À-ÖØ-öø-ÿ\s\-]+$/u', $this->label)) {
            return "Le nom de la piscine ne doit contenir que des lettres, des chiffres et des espaces.";
        }
        if ($this->max_places < 0) {
            return "La capacité doit être un nombre positif.";
        }

        return null;
    }

    public function getLabel(): ?string { return $this->label; }
    public function getAddress(): ?string { return $this->address; }
    public function getMaxPlaces(): ?int { return $this->max_places; }
    public function getNumberedSeats(): ?bool { return $this->numbered_seats; }
}