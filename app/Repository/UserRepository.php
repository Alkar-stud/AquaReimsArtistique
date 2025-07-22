<?php

namespace app\Repository;

class UserRepository extends AbstractRepository
{
    public function __construct()
    {
        // On passe le nom de la table au constructeur parent
        parent::__construct('users');
    }

    /**
     * Exemple de méthode spécifique à ce repository.
     * Trouve un utilisateur par son email.
     *
     * @param string $email
     * @return array|false
     */
    public function findByEmail(string $email): array|false
    {
        // On utilise la méthode "query" héritée, mais on ne veut qu'un seul résultat
        $results = $this->query("SELECT * FROM {$this->tableName} WHERE email = :email", ['email' => $email]);
        // On retourne le premier résultat, ou false s'il n'y en a pas.
        return $results[0] ?? false;
    }

    // On peut ajouter ici toutes les méthodes spécifiques aux utilisateurs
    // par exemple : createUser, updateUserPassword, etc.
}