<?php

namespace app\Services\DataValidation;

use app\Services\Tarif\TarifService;

class TarifDataValidationService
{
    private ?TarifService $tarifService = null;

    private ?string $name = null;
    private ?string $description = null;
    private ?int $seat_count = null;
    private ?int $min_age = null;
    private ?int $max_age = null;
    private ?int $max_tickets = null;
    private ?int $price = null;
    private ?bool $includes_program = null;
    private ?bool $requires_proof = null;
    private ?string $access_code = null;
    private ?bool $is_active = null;

    /**
     * Injection optionnelle pour permettre le contrôle d'unicité.
     */
    public function setTarifService(TarifService $tarifService): self
    {
        $this->tarifService = $tarifService;
        return $this;
    }

    /**
     * Valide et normalise les données. Si $excludeId est fourni, il est exclu de la vérification d'unicité.
     */
    public function checkData(array $data, ?int $excludeId = null): ?string
    {
        // Conserver le code brut pour la vérification (évite htmlspecialchars dans la comparaison DB)
        $rawAccessCode = isset($data['access_code']) ? trim((string)$data['access_code']) : '';

        // Normalisation
        $nameValue = trim($data['name'] ?? '');
        $this->name = !empty($nameValue) ? htmlspecialchars(mb_convert_case($nameValue, MB_CASE_TITLE, "UTF-8")) : '';

        $descriptionValue = trim($data['description'] ?? '');
        if (!empty($descriptionValue)) {
            $firstChar = mb_substr($descriptionValue, 0, 1, 'UTF-8');
            $this->description = htmlspecialchars(mb_strtoupper($firstChar, 'UTF-8') . mb_substr($descriptionValue, 1, null, 'UTF-8'));
        } else {
            $this->description = null;
        }
        $this->seat_count = !empty($data['seat_count']) && is_numeric($data['seat_count']) ? (int)$data['seat_count'] : null;
        $this->min_age = !empty($data['min_age']) && is_numeric($data['min_age']) ? (int)$data['min_age'] : null;
        $this->max_age = !empty($data['max_age']) && is_numeric($data['max_age']) ? (int)$data['max_age'] : null;
        $this->max_tickets = !empty($data['max_tickets']) && is_numeric($data['max_tickets']) ? (int)$data['max_tickets'] : null;
        $this->price = !empty($data['price']) && is_numeric($data['price']) ? (int)($data['price'] * 100) : 0;
        $this->includes_program = isset($data['includes_program']);
        $this->requires_proof = isset($data['requires_proof']);
        $this->access_code = !empty($rawAccessCode) ? htmlspecialchars($rawAccessCode) : null;
        $this->is_active = isset($data['is_active']);

        // Validation de base
        if (empty($this->name)) {
            return "Le nom du tarif est obligatoire.";
        }
        if ($this->price < 0) {
            return "Le prix doit être un nombre supérieur ou égal à 0.";
        }
        if ($this->min_age !== null && $this->min_age < 0) {
            return "L'âge minimum doit être un nombre positif.";
        }
        if ($this->max_age !== null && $this->max_age < 0) {
            return "L'âge maximum doit être un nombre positif.";
        }
        if ($this->min_age !== null && $this->max_age !== null && $this->min_age > $this->max_age) {
            return "L'âge minimum ne peut pas être supérieur à l'âge maximum.";
        }
        if ($this->seat_count !== null && $this->seat_count < 0) {
            return "Le nombre de sièges doit être un nombre positif.";
        }
        if ($this->max_tickets !== null && $this->max_tickets < 0) {
            return "Le nombre maximum de tickets doit être un nombre positif.";
        }

        // Contrôle d'unicité du code par type (avec/sans places), insensible à la casse et aux espaces.
        if ($this->tarifService && $rawAccessCode !== '') {
            $hasSeats = ($this->seat_count !== null && $this->seat_count > 0);
            $duplicate = $this->tarifService->isAccessCodeDuplicateForSeatType($rawAccessCode, $hasSeats, $excludeId);
            if ($duplicate) {
                return "Ce code est déjà utilisé pour un tarif " . ($hasSeats ? "avec places" : "sans places") . ".";
            }
        }

        return null;
    }

    // --- GETTERS ---
    public function getName(): ?string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getSeatCount(): ?int { return $this->seat_count; }
    public function getMinAge(): ?int { return $this->min_age; }
    public function getMaxAge(): ?int { return $this->max_age; }
    public function getMaxTickets(): ?int { return $this->max_tickets; }
    public function getPrice(): ?int { return $this->price; }
    public function getIncludesProgram(): ?bool { return $this->includes_program; }
    public function getRequiresProof(): ?bool { return $this->requires_proof; }
    public function getAccessCode(): ?string { return $this->access_code; }
    public function getIsActive(): ?bool { return $this->is_active; }
}