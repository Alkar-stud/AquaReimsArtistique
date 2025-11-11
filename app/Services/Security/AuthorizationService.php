<?php

namespace app\Services\Security;

use app\Models\User\User;

final class AuthorizationService
{
    private array $securityConfig;

    public function __construct()
    {
        // Charge une seule fois la config de sécurité
        $this->securityConfig = require __DIR__ . '/../../../config/security.php';
    }

    /**
     * Retourne les permissions de l'utilisateur pour la fonctionnalité donnée.
     * Par défaut, utilise la section `reservations_access_level`.
     */
    public function getPermissionsFor(User $user, string $featureKey = 'reservations_access_level'): string
    {
        $levels = $this->securityConfig[$featureKey] ?? [];
        $roleLevel = $user->getRole()->getLevel();

        // Retourne une chaîne de permissions (ex: R, CRU, CRUD) ou chaîne vide si non défini.
        return (string)($levels[$roleLevel] ?? '');
    }

    /**
     * Indique si l'utilisateur possède toutes les permissions requises.
     * La comparaison est insensible à la casse (r/w/d == R/W/D).
     */
    public function hasPermission(User $user, string $required, string $featureKey = 'reservations_access_level'): bool
    {
        $have = strtoupper($this->getPermissionsFor($user, $featureKey));
        $need = strtoupper($required);

        // Autorise si toutes les lettres requises sont présentes dans $have
        foreach (str_split($need) as $letter) {
            if ($letter === '') {
                continue;
            }
            if (!str_contains($have, $letter)) {
                return false;
            }
        }
        return true;
    }
}