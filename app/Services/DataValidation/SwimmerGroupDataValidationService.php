<?php

namespace app\Services\DataValidation;

class SwimmerGroupDataValidationService
{
    private ?string $name = null;
    private ?string $coach = null;
    private ?bool $is_active = null;
    private ?string $order = null;


    public function checkData(array $data): ?string
    {
        // Normalisation
        $nameValue = isset($data['name']) ? mb_convert_case((trim($data['name']) ?? ''), MB_CASE_TITLE, "UTF-8") : '';
        $this->name = $nameValue !== '' ? htmlspecialchars($nameValue) : null;
        $coachValue = isset($data['coach']) ? mb_convert_case((trim($data['coach']) ?? ''), MB_CASE_TITLE, "UTF-8") : '';
        $this->coach = $coachValue !== '' ? htmlspecialchars($coachValue) : null;
        $this->is_active = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : false;
        $this->order = isset($data['order']) && is_numeric($data['order']) ? (int)$data['order'] : 0;

        // Validation
        if (empty($this->name) &&  !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/u', $this->name)) {
            return "Le nom du groupe est obligatoire et ne doit contenir que des lettres et espaces.";
        }
        if ($this->coach !== null && !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/u', $this->coach)) {
            return "Le nom du coach ne doit contenir que des lettres et espaces.";
        }

        if (!is_bool($this->is_active)) {
            return "Le statut d'activité doit être un booléen.";
        }
        if ($this->order < 0) {
            return "L'ordre doit être un entier positif.";
        }

        return null;
    }

    public function getName(): ?string { return $this->name; }
    public function getCoach(): ?string { return $this->coach; }
    public function getIsActive(): ?bool { return $this->is_active; }
    public function getOrder(): ?string { return $this->order; }
}