<?php

namespace app\Services\DataValidation;

class ConfigDataValidationService
{
    private ?string $label = null;
    private ?string $config_key = null;
    private ?string $config_value = null;
    private ?string $config_type = null;

    public function checkData(array $data): ?string
    {
        // Normalisation
        $this->label = htmlspecialchars(trim($data['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->config_key = strtoupper(trim($data['config_key'] ?? ''));
        $this->config_value = htmlspecialchars(trim($data['config_value'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type = trim($data['config_type'] ?? '');
        $this->config_type = $type === '' ? null : htmlspecialchars($type, ENT_QUOTES, 'UTF-8');

        // Validation
        if (empty($this->label)) {
            return "Le libellé est obligatoire pour expliquer l'utilité de cette variable de configuration.";
        }
        if (empty($this->config_key)) {
            return "La clé de configuration est obligatoire.";
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $this->config_key)) {
            return "La clé ne doit contenir que des lettres majuscules, chiffres ou '_', sans espaces ni caractères spéciaux.";
        }
        if (empty($this->config_value)) {
            return "La valeur de configuration est obligatoire.";
        }

        // Vérification du type si précisé
        if ($this->config_type !== null) {
            switch ($this->config_type) {
                case 'int':
                case 'integer':
                    if (filter_var($this->config_value, FILTER_VALIDATE_INT) === false) {
                        return "La valeur doit être un entier valide.";
                    }
                    break;
                case 'float':
                    if (filter_var($this->config_value, FILTER_VALIDATE_FLOAT) === false) {
                        return "La valeur doit être un nombre décimal valide.";
                    }
                    break;
                case 'bool':
                case 'boolean':
                    if (!in_array(strtolower($this->config_value), ['0', '1', 'true', 'false'], true)) {
                        return "La valeur doit être un booléen (0, 1, true, false).";
                    }
                    break;
                case 'email':
                    if (filter_var($this->config_value, FILTER_VALIDATE_EMAIL) === false) {
                        return "La valeur doit être une adresse email valide.";
                    }
                    break;
                case 'date':
                    if (strtotime($this->config_value) === false) {
                        return "La valeur doit être une date valide.";
                    }
                    break;
                case 'datetime':
                    if (strtotime($this->config_value) === false) {
                        return "La valeur doit être une date+heure valide.";
                    }
                    break;
                case 'url':
                    if (filter_var($this->config_value, FILTER_VALIDATE_URL) === false) {
                        return "La valeur doit être une URL valide.";
                    }
                    break;
                // Pour string et autres, pas de vérification stricte
            }
        }

        return null;
    }


    public function getLabel(): ?string { return $this->label; }
    public function getConfigKey(): ?string { return $this->config_key; }
    public function getConfigValue(): ?string { return $this->config_value; }
    public function getConfigType(): ?string { return $this->config_type; }
}