<?php

namespace app\Services\Auth;

use app\Models\User\User;
use app\Repository\User\UserRepository;

readonly class UserSessionService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    /**
     * Connecte un utilisateur en initialisant sa session.
     */
    public function login(User $user): void
    {
        // Régénération de l'ID de session pour prévenir la fixation de session
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'displayname' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'role' => [
                'id' => $user->getRole()->getId(),
                'label' => $user->getRole()->getLabel(),
                'level' => $user->getRole()->getLevel()
            ],
            'LAST_ACTIVITY' => time(),
            'LAST_REGENERATION' => time()
        ];

        // Enregistre le nouvel ID de session en BDD
        $this->userRepository->addSessionId($user->getId(), session_id());
    }

    /**
     * Déconnecte l'utilisateur et détruit la session.
     */
    public function logout(): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }

        $userId = $this->getUserId();
        $sessionId = session_id();

        $_SESSION = [];
        session_destroy();

        // On supprime l'ID de session en BDD après avoir détruit la session
        // pour s'assurer que toutes les infos sont bien récupérées avant.
        if ($userId && $sessionId) {
            $this->userRepository->removeSessionId($userId, $sessionId);
        }
    }

    /**
     * Vérifie si un utilisateur est actuellement connecté.
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /**
     * Récupère l'ID de l'utilisateur connecté.
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }
}