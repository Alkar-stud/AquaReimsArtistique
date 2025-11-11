<?php

namespace app\Repository;

use app\Models\Config;

class ConfigRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('config');
    }

    /**
     * Retourne toutes les clés et valeurs de configuration.
     * @return array<string, mixed>
     */
    public function findAllAsKeyValue(): array
    {
        $configs = [];
        foreach ($this->findAll() as $config) {
            $configs[$config->getConfigKey()] = $config->getConfigValue();
        }
        return $configs;
    }

    /**
     * Retourne toutes les clés et valeurs de configuration.
     * @return Config[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY config_key;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * @param int $id
     * @return Config|null
     */
    public function findById(int $id): ?Config
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Insère une nouvelle configuration.
     * @return int ID inséré (0 si échec)
     */
    public function insert(Config $config): int
    {
        $sql = "INSERT INTO $this->tableName (label, config_key, config_value, config_type, created_at)
                VALUES (:label, :config_key, :config_value, :config_type, :created_at)";
        $ok = $this->execute($sql, [
            'label' => $config->getLabel(),
            'config_key' => $config->getConfigKey(),
            'config_value' => $config->getConfigValue(),
            'config_type' => $config->getConfigType(),
            'created_at' => $config->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une configuration existante.
     * @param Config $config
     * @return bool
     */
    public function update(Config $config): bool
    {
        $sql = "UPDATE $this->tableName SET
            label = :label, config_key = :config_key, config_value = :config_value, config_type = :config_type, updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $config->getId(),
            'label' => $config->getLabel(),
            'config_key' => $config->getConfigKey(),
            'config_value' => $config->getConfigValue(),
            'config_type' => $config->getConfigType(),
        ]);
    }

    /**
     * Hydrate une configuration depuis une ligne BDD.
     * @param array $data
     * @return Config
     */
    protected function hydrate(array $data): Config
    {
        $config = new Config();
        $value = $data['config_value'];
        $type = $data['config_type'];

        switch ($type) {
            case 'bool':
            case 'boolean':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'int':
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                $value = (float)$value;
                break;
            case 'string':
                $value = (string)$value;
                break;
            case 'email':
                $value = filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
                break;
            case 'date':
                $value = (strtotime($value) !== false) ? date('Y-m-d', strtotime($value)) : '';
                break;
            case 'datetime':
                $value = (strtotime($value) !== false) ? date('Y-m-d H:i:s', strtotime($value)) : '';
                break;
            case 'url':
                $value = filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
                break;
        }

        $config->setId($data['id'])
            ->setLabel($data['label'])
            ->setConfigKey($data['config_key'])
            ->setConfigValue($value)
            ->setConfigType($type);
        return $config;
    }
}
