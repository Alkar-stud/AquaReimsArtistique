<?php

namespace app\Repository\User;

use app\Models\User\User;
use app\Repository\AbstractRepository;

class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('user');
    }

    /**
     * Retourne tous les utilisateurs ordonnés par nom d'utilisateur.
     * @return User[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY username;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère un nouvel utilisateur.
     * @return int ID inséré (0 si échec)
     */
    public function insert(User $user): int
    {
        $sql = "INSERT INTO $this->tableName 
        (username, password, email, display_name, role, is_actif, created_at, password_reset_token, password_reset_expires_at)
        VALUES (:username, :password, :email, :display_name, :role, :is_actif, :created_at, :token, :expires_at)";
        $ok = $this->execute($sql, [
            'username' => $user->getUsername(),
            'password' => $user->getPassword(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'role' => $user->getRole()?->getId(),
            'is_actif' => $user->getIsActif(),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'token' => $user->getPasswordResetToken(),
            'expires_at' => $user->getPasswordResetExpiresAt()?->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Retourne un utilisateur par son nom d'utilisateur.
     * @param string $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User
    {
        $sql = "SELECT * FROM $this->tableName WHERE username = :username";
        $result = $this->query($sql, ['username' => $username]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne un utilisateur par son email.
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM $this->tableName WHERE email = :email";
        $result = $this->query($sql, ['email' => $email]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne un utilisateur par son ID.
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne un utilisateur par son token de réinitialisation de mot de passe.
     * @param string $token
     * @return User|null
     */
    public function findByValidResetToken(string $token): ?User
    {
        $sql = "SELECT * FROM $this->tableName 
                WHERE password_reset_token = :token 
                  AND password_reset_expires_at > NOW() 
                  AND is_actif = 1;";
        $result = $this->query($sql, ['token' => $token]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne les utilisateurs dont le rôle a un niveau strictement supérieur à $level.
     * Donc avec moins de droits
     * @param int $level
     * @return User[]
     */
    public function findAllWithRoleLevelLowerThan(int $level): array
    {
        $sql = "SELECT u.* FROM $this->tableName u
            INNER JOIN role r ON u.role = r.id
            WHERE r.level > :level
            ORDER BY r.level, u.username;";
        $results = $this->query($sql, ['level' => $level]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Mise à jour des données d'un utilisateur.
     * @param int $userId
     * @param string $newDisplayName
     * @param string $newEmail
     * @return bool
     */
    public function updateData(int $userId, string $newDisplayName, string $newEmail): bool
    {
        $sql = "UPDATE $this->tableName SET display_name = :display_name, email = :email WHERE id = :id;";
        return $this->execute($sql, ['display_name' => $newDisplayName, 'email' => $newEmail, 'id' => $userId]);
    }

    /**
     * Mise à jour du mot de passe d'un utilisateur.
     * @param int $userId
     * @param string $newHashedPassword
     * @return bool
     */
    public function updatePassword(int $userId, string $newHashedPassword): bool
    {
        $sql = "UPDATE $this->tableName SET password = :password WHERE id = :id;";
        return $this->execute($sql, ['password' => $newHashedPassword, 'id' => $userId]);
    }

    /**
     * Mise à jour du token de réinitialisation de mot de passe d'un utilisateur.
     * @param int $userId
     * @param string $token
     * @param string $expiresAt
     * @return bool
     */
    public function savePasswordResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = :token, password_reset_expires_at = :expires_at WHERE id = :id;";
        return $this->execute($sql, ['token' => $token, 'expires_at' => $expiresAt, 'id' => $userId]);
    }

    /**
     * Supprime le token de réinitialisation de mot de passe d'un utilisateur (une fois utilisé)
     * @param int $userId
     * @return bool
     */
    public function clearResetToken(int $userId): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id;";
        return $this->execute($sql, ['id' => $userId]);
    }

    /**
     * Hydrate un User depuis une ligne BDD.
     */
    protected function hydrate(array $data): User
    {
        $user = new User();
        $user->setId($data['id'])
            ->setUsername($data['username'])
            ->setPassword($data['password'])
            ->setEmail($data['email'])
            ->setDisplayName($data['display_name'])
            ->setIsActif($data['is_actif'])
            ->setPasswordResetToken($data['password_reset_token'])
            ->setPasswordResetExpiresAt($data['password_reset_expires_at'])
            ->setSessionId($data['session_id']);

        if (!empty($data['role'])) {
            $roleRepository = new RoleRepository();
            $role = $roleRepository->findById((int)$data['role']);
            $user->setRole($role);
        }

        return $user;
    }

    /**
     * Enregistre le session ID d'un utilisateur.
     * @param int $userId
     * @param string $sessionId
     * @return bool
     */
    public function addSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = :sessionId WHERE id = :id;";
        return $this->execute($sql, ['sessionId' => $sessionId, 'id' => $userId]);
    }

    /**
     * Supprime le session ID d'un utilisateur.
     * @param int $userId
     * @param string $sessionId
     * @return bool
     */
    public function removeSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = NULL WHERE id = :id OR session_id = :sessionId;";
        return $this->execute($sql, ['id' => $userId, 'sessionId' => $sessionId]);
    }

    /**
     * Met à jour les données d'un utilisateur.
     * @param User $user
     * @return bool
     */
    public function update(User $user): bool
    {
        $sql = "UPDATE $this->tableName SET 
        username = :username,
        email = :email,
        display_name = :display_name,
        role = :role,
        is_actif = :is_actif,
        session_id = :session_id,
        updated_at = :updated_at
        WHERE id = :id;";
        return $this->execute($sql, [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'role' => $user->getRole()?->getId(),
            'is_actif' => (int)$user->getIsActif(),
            'session_id' => $user->getSessionId(),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $user->getId()
        ]);
    }

    /**
     * Met à jour l'état d'un utilisateur.
     * @param int $id
     * @param bool $isActif
     * @return bool
     */
    public function suspendOnOff(int $id, bool $isActif): bool
    {
        $sql = "UPDATE $this->tableName SET is_actif = :is_actif WHERE id = :id;";
        return $this->execute($sql, ['id' => $id, 'is_actif' => (int)$isActif]);
    }

}
