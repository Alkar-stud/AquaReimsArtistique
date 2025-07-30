<?php

namespace app\Repository;

use app\Models\User;
use DateMalformedStringException;

class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('user');
    }

    /**
     * Trouve un utilisateur par son nom d'utilisateur.
     *
     * @param string $username
     * @return User|false
     * @throws DateMalformedStringException
     */
    public function findByUsername(string $username): User|false
    {
        $sql = "SELECT * FROM $this->tableName WHERE username = :username";
        $result = $this->query($sql, ['username' => $username]);
        return $result ? $this->hydrate($result[0]) : false;
    }

    /**
     * Trouve un utilisateur par son email.
     *
     * @param string $email
     * @return User|false
     * @throws DateMalformedStringException
     */
    public function findByEmail(string $email): User|false
    {
        $sql = "SELECT * FROM $this->tableName WHERE email = :email";
        $result = $this->query($sql, ['email' => $email]);
        return $result ? $this->hydrate($result[0]) : false;
    }

    /**
     * Trouve un utilisateur par son id.
     *
     * @param int $id
     * @return User|false
     * @throws DateMalformedStringException
     */
    public function findById(int $id): User|false
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : false;
    }

    /**
     * Trouve un utilisateur par son token de réinitialisation valide.
     * @param string $token
     * @return User|false
     * @throws DateMalformedStringException
     */
    public function findByValidResetToken(string $token): User|false
    {
        $sql = "SELECT * FROM $this->tableName WHERE password_reset_token = :token AND password_reset_expires_at > NOW()";
        $result = $this->query($sql, ['token' => $token]);
        return $result ? $this->hydrate($result[0]) : false;
    }

    /**
     * Met à jour les champs displayname et email d'un utilisateur, sauf si celui-ci est d'un level 0 (superadmin modifiable qu'en BDD à la main)
     */
    public function updateData(int $userId, string $newDisplayName, string $newEmail): bool
    {
        $sql = "UPDATE $this->tableName SET display_name = :display_name, email = :email WHERE id = :id";
        return $this->execute($sql, ['display_name' => $newDisplayName, 'email' => $newEmail, 'id' => $userId]);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     */
    public function updatePassword(int $userId, string $newHashedPassword): bool
    {
        $sql = "UPDATE $this->tableName SET password = :password WHERE id = :id";
        return $this->execute($sql, ['password' => $newHashedPassword, 'id' => $userId]);
    }

    /**
     * Sauvegarde le token de réinitialisation et sa date d'expiration pour un utilisateur.
     */
    public function savePasswordResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = :token, password_reset_expires_at = :expires_at WHERE id = :id";
        return $this->execute($sql, ['token' => $token, 'expires_at' => $expiresAt, 'id' => $userId]);
    }

    /**
     * Invalide le token de réinitialisation pour un utilisateur en le mettant à NULL.
     */
    public function clearResetToken(int $userId): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id";
        return $this->execute($sql, ['id' => $userId]);
    }

    /**
     * Crée et remplit un objet User à partir d'un tableau de données (BDD).
     * @throws DateMalformedStringException
     */
    private function hydrate(array $data): User
    {
        $user = new User();
        $user->setId($data['id'])
            ->setUsername($data['username'])
            ->setPassword($data['password'])
            ->setEmail($data['email'])
            ->setDisplayName($data['display_name'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at'])
            ->setPasswordResetToken($data['password_reset_token'])
            ->setPasswordResetExpiresAt($data['password_reset_expires_at'])
            ->setSessionId($data['session_id']);

        // Hydratation de la relation avec le rôle
        if (!empty($data['roles'])) {
            $roleRepository = new RoleRepository();
            $role = $roleRepository->findById($data['roles']);
            $user->setRole($role);
        }

        return $user;
    }

    /**
     * Ajouter l'identifiant de session enregistré en BDD pour login
     */
    public function addSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = :sessionId WHERE id = :id";
        return $this->execute($sql, ['sessionId' => $sessionId, 'id' => $userId]);
    }

    /**
     * Supprimer l'identifiant de session enregistré en BDD pour logout
     */
    public function removeSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = NULL WHERE id = :id OR session_id = :sessionId";
        return $this->execute($sql, ['id' => $userId, 'sessionId' => $sessionId]);
    }

}