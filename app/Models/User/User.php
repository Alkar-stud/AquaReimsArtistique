<?php

namespace app\Models\User;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class User extends AbstractModel
{
    private string $username;
    private string $password;
    private string $email;
    private ?string $display_name = null;
    private ?Role $role = null;
    private ?bool $isActif = true; //actif par défaut
    private ?string $password_reset_token = null;
    private ?DateTimeInterface $password_reset_expires_at = null;
    private ?string $session_id = null;

    // --- GETTERS ---
    public function getUsername(): string { return $this->username; }
    public function getPassword(): string { return $this->password; }
    public function getEmail(): string { return $this->email; }
    //S'il n'y a pas de nom d'affichage renseigné, on retourne le nom d'utilisateur
    public function getDisplayName(): string { return $this->display_name ?: $this->getUsername(); }
    public function getRole(): ?Role { return $this->role; }
    public function getIsActif(): bool { return $this->isActif; }
    public function getPasswordResetToken(): ?string { return $this->password_reset_token; }
    public function getPasswordResetExpiresAt(): ?DateTimeInterface { return $this->password_reset_expires_at; }
    public function getSessionId(): ?string { return $this->session_id; }

    // --- SETTERS
    public function setUsername(string $username): self { $this->username = $username; return $this; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    /**
     * Si display_name est vide ou null, il retourne username pour que display_name, même non renseigné, ne soit pas vide
     * @param string|null $display_name
     * @return $this
     */
    public function setDisplayName(?string $display_name): self
    {
        if ($display_name !== null) {
            $display_name = trim($display_name);
        }
        $this->display_name = ($display_name === '' ? null : $display_name);
        return $this;
    }

    public function setRole(?Role $role): self { $this->role = $role; return $this; }
    public function setIsActif(bool $isActif): self { $this->isActif = $isActif; return $this; }
    public function setPasswordResetToken(?string $password_reset_token): self { $this->password_reset_token = $password_reset_token; return $this; }
    public function setPasswordResetExpiresAt(?string $password_reset_expires_at): self
    {
        $this->password_reset_expires_at = $password_reset_expires_at ? new DateTime($password_reset_expires_at) : null;
        return $this;
    }
    public function setSessionId(?string $session_id): self { $this->session_id = $session_id; return $this; }
    
}
