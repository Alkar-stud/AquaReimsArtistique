<?php

namespace app\Repository\User;

use app\Models\User\User;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('user');
    }


    /*
     * Insère un nouvel utilisateur dans la base de données.
     */
    public function insert(User $user): bool
    {
        $sql = "INSERT INTO $this->tableName 
        (username, password, email, display_name, role, is_actif, created_at, password_reset_token, password_reset_expires_at)
        VALUES (:username, :password, :email, :display_name, :role, :is_actif, :created_at, :token, :expires_at)";
        return $this->execute($sql, [
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
     * Trouve un utilisateur par son token de réinitialisation valide si actif.
     * @param string $token
     * @return User|false
     * @throws DateMalformedStringException
     */
    public function findByValidResetToken(string $token): User|false
    {
        $sql = "SELECT * FROM $this->tableName WHERE password_reset_token = :token AND password_reset_expires_at > NOW() AND is_actif = 1;";
        $result = $this->query($sql, ['token' => $token]);
        return $result ? $this->hydrate($result[0]) : false;
    }


    /*
     * Récupère tous les utilisateurs de la table qui ont un role plus bas.
     */
    public function findAllByLevel(): array
    {
        $currentUser = $_SESSION['user'] ?? null;
        $level = 5;
        if ($currentUser && isset($currentUser['role']['level'])) {
            $level = $currentUser['role']['level'];
        }
        $sql = "SELECT u.* FROM {$this->tableName} u
            INNER JOIN role r ON u.role = r.id
            WHERE r.level > :level
            ORDER BY r.level, u.username;";
        $results = $this->query($sql, ['level' => $level]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Met à jour les champs displayname et email d'un utilisateur, sauf si celui-ci est d'un level 0 (superadmin modifiable qu'en BDD à la main)
     */
    public function updateData(int $userId, string $newDisplayName, string $newEmail): bool
    {
        $sql = "UPDATE $this->tableName SET display_name = :display_name, email = :email WHERE id = :id;";
        return $this->execute($sql, ['display_name' => $newDisplayName, 'email' => $newEmail, 'id' => $userId]);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     */
    public function updatePassword(int $userId, string $newHashedPassword): bool
    {
        $sql = "UPDATE $this->tableName SET password = :password WHERE id = :id;";
        return $this->execute($sql, ['password' => $newHashedPassword, 'id' => $userId]);
    }

    /**
     * Sauvegarde le token de réinitialisation et sa date d'expiration pour un utilisateur.
     */
    public function savePasswordResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = :token, password_reset_expires_at = :expires_at WHERE id = :id;";
        return $this->execute($sql, ['token' => $token, 'expires_at' => $expiresAt, 'id' => $userId]);
    }

    /**
     * Invalide le token de réinitialisation pour un utilisateur en le mettant à NULL.
     */
    public function clearResetToken(int $userId): bool
    {
        $sql = "UPDATE $this->tableName SET password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id;";
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
            ->setIsActif($data['is_actif'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at'])
            ->setPasswordResetToken($data['password_reset_token'])
            ->setPasswordResetExpiresAt($data['password_reset_expires_at'])
            ->setSessionId($data['session_id']);

        // Hydratation de la relation avec le rôle
        if (!empty($data['role'])) {
            $roleRepository = new RoleRepository();
            $role = $roleRepository->findById($data['role']);
            $user->setRole($role);
        }

        return $user;
    }

    /**
     * Ajouter/mettre à jour l'identifiant de session enregistré en BDD pour login
     */
    public function addSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = :sessionId WHERE id = :id;";
        return $this->execute($sql, ['sessionId' => $sessionId, 'id' => $userId]);
    }


    /**
     * Supprimer l'identifiant de session enregistré en BDD pour logout
     */
    public function removeSessionId(int $userId, string $sessionId): bool
    {
        $sql = "UPDATE $this->tableName SET session_id = NULL WHERE id = :id OR session_id = :sessionId;";
        return $this->execute($sql, ['id' => $userId, 'sessionId' => $sessionId]);
    }

    // Met à jour les informations principales d'un utilisateur
    public function update(User $user): bool
    {
        $sql = "UPDATE $this->tableName SET 
        username = :username,
        email = :email,
        display_name = :display_name,
        role = :role,
        is_actif = :is_actif,
        updated_at = :updated_at
        WHERE id = :id;";
        return $this->execute($sql, [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'role' => $user->getRole()?->getId(),
            'is_actif' => (int)$user->getIsActif(), //forcer passage en int pour éviter erreur SQL General error: 1366 Incorrect integer value: '' for column 'is_actif' lors de la désactivation
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $user->getId()
        ]);
    }

    // Suspend ou réactive un utilisateur par son id
    public function suspendOnOff(int $id, bool $isActif): bool
    {
        $sql = "UPDATE $this->tableName SET is_actif = :is_actif WHERE id = :id;";
        return $this->execute($sql, ['id' => $id, 'is_actif' => (int)$isActif]);
    }

    // Supprime un utilisateur par son id
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id;";
        return $this->execute($sql, ['id' => $id]);
    }

}
