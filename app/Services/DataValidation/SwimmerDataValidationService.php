<?php

namespace app\Services\DataValidation;

use app\Repository\Swimmer\SwimmerGroupRepository;

class SwimmerDataValidationService
{
    private ?string $name = null;
    private ?int $group = null;


    public function checkData(array $data): ?string
    {
        // Normalisation
        $this->name = htmlspecialchars(trim($data['name'] ?? ''));
        $this->group = isset($data['group']) ? (int)$data['group'] : null;

        // Validation
        if (empty($this->name) && !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/u', $this->name)) {
            return "Le nom du nageur est obligatoire et ne doit contenir que des lettres et espaces.";
        }
        if (!is_numeric($this->group)) {
            return "L'identifiant du groupe doit être un nombre.";
        }

        $swimmerGroupRepository = new SwimmerGroupRepository();
        if (!$swimmerGroupRepository->findById((int)$this->group)) {
            return "Le groupe spécifié n'existe pas.";
        }

        return null;
    }

    public function getName(): ?string { return $this->name; }
    public function getGroup(): ?string { return $this->group; }

}
