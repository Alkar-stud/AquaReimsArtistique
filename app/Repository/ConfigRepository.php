<?php

namespace app\Repository;

use PDO;

class ConfigRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('config');
    }

    /**
     * Récupère toutes les configurations de la base de données,
     * les type correctement et les retourne sous forme de tableau associatif [clé => valeur].
     *
     * @return array
     */
    public function findAllAsKeyValue(): array
    {
        // 1. On sélectionne toutes les colonnes dont on a besoin
        $sql = "SELECT config_key, config_value, config_type FROM {$this->tableName}";
        $stmt = $this->pdo->query($sql);

        // 2. On récupère les résultats sous forme de tableau associatif classique
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $configs = [];
        // 3. On parcourt les résultats pour construire notre tableau final et typer les valeurs
        foreach ($results as $row) {
            $key = $row['config_key'];
            $value = $row['config_value'];
            $type = $row['config_type'];

            // On convertit la valeur dans le bon type
            switch ($type) {
                case 'bool':
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'int':
                case 'integer':
                    $value = (int)$value;
                    break;
                // case 'string' et autres sont déjà corrects par défaut
            }

            $configs[$key] = $value;
        }

        return $configs;
    }
}