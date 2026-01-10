<?php
namespace app\Services\DataValidation;

class UserDataValidationService
{
    private ?string $username = null;
    private ?string $displayName = null;
    private ?string $email = null;
    private int $roleId = 0;
    private bool $isActive = true;

    public function checkData(array $data): ?string
    {
        // Normalisation
        $usernameRaw = trim($data['username'] ?? '');
        $this->username = htmlspecialchars($usernameRaw, ENT_QUOTES, 'UTF-8');
        $this->displayName = htmlspecialchars(trim($data['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $emailRaw = trim($data['email'] ?? '');
        $this->email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ?: null;
        $this->roleId = (int)($data['role'] ?? 0);

        // Checkbox "Actif" (toujours présent grâce au hidden input)
        $this->isActive = filter_var($data['is_active'] ?? '0', FILTER_VALIDATE_BOOLEAN);

        // Validation du nom d'utilisateur :
        // - accepte les lettres Unicode (y compris accentuées) \p{L}, les chiffres \p{N}, "_" et "-"
        // - interdit les espaces et autres caractères
        // - longueur entre 3 et 50 (vérifiée avec mb_strlen)
        $len = mb_strlen($usernameRaw, 'UTF-8');
        if (empty($usernameRaw) || !preg_match('/^[\p{L}\p{N}_-]+$/u', $usernameRaw) || $len < 3 || $len > 50) {
            return "Le nom d'utilisateur doit contenir entre 3 et 50 caractères sans espaces : seuls les lettres (y compris accentuées), chiffres, underscore et tiret sont autorisés.";
        }
        if (!$this->email) {
            return "L'adresse email saisie est invalide.";
        }
        if (!empty($this->displayName) && (strlen($this->displayName) < 3 || strlen($this->displayName) > 100)) {
            return "Le nom affiché doit contenir entre 3 et 100 caractères.";
        }
        if ($this->roleId <= 0) {
            return "Le rôle sélectionné est invalide.";
        }

        return null;
    }

    public function getUsername(): ?string { return $this->username; }
    public function getDisplayName(): ?string { return $this->displayName; }
    public function getEmail(): ?string { return $this->email; }
    public function getRoleId(): int { return $this->roleId; }
    public function getIsActive(): bool { return $this->isActive; }
}
