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
            $dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=' . $_ENV['DB_CHARSET'];

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
}