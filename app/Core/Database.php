<?php

namespace app\Core;

use PDO;
use PDOException;

/**
 * Gère la connexion à la base de données
 */
class Database
{
    /** @var PDO|null L'instance unique de la classe */
    private static ?PDO $instance = null;

    /**
     * Le constructeur est privé pour empêcher l'instanciation directe.
     */
    private function __construct() {}

    /**
     * Empêche le clonage de l'instance.
     */
    private function __clone() {}

    /**
     * Méthode statique qui crée l'instance unique de la classe
     * ou la retourne si elle existe déjà.
     *
     * @return PDO L'instance de PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Les constantes sont déjà chargées par un require dans les fichiers de config
            $dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';charset=' . $_ENV['DB_CHARSET'];
            $dbName = $_ENV['DB_NAME'];

            if (!empty($dbName)) {
                $dsn .= ';dbname=' . $dbName;
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lance des exceptions en cas d'erreur
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Récupère les résultats en tableau associatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Utilise les vraies requêtes préparées
            ];

            try {
                self::$instance = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
            } catch (PDOException $e) {
                // En développement, on peut afficher l'erreur. En production, il faudrait logger l'erreur et afficher un message générique.
                throw new PDOException("Erreur de connexion à la base de données : " . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }


    /**
     * Vérifie si l'application est installée en testant l'existence de la table 'migrations'.
     * @return bool
     */
    public static function isInstalled(): bool
    {
        try {
            $pdo = self::getInstance();
            // On essaie d'exécuter une requête simple sur une table qui doit exister après l'installation.
            // La table 'migrations' est un excellent candidat.
            $pdo->query("SELECT 1 FROM migrations LIMIT 1");
            return true;
        } catch (PDOException $e) {
            // Si la requête échoue parce que la table n'existe pas (SQLSTATE[42S02]),
            // alors l'application n'est pas installée.
            if ($e->getCode() === '42S02') {
                return false;
            }
            // Pour toute autre erreur (ex: problème de connexion), on laisse l'exception se propager
            // pour être gérée plus haut.
            throw $e;
        }
    }
}