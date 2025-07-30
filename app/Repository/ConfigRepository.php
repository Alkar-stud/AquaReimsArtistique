<?php

namespace app\Repository;

use app\Models\Config;

class ConfigRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('config');
    }

    public function findAllAsKeyValue(): array
    {
        $configs = [];
        foreach ($this->findAll() as $config) {
            $configs[$config->getConfigKey()] = $config->getConfigValue();
        }
        return $configs;
    }
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY config_key;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findById(int $id): ?Config
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    public function insert(Config $config): void
    {
        $sql = "INSERT INTO $this->tableName (libelle, config_key, config_value, config_type, created_at) 
            VALUES (:libelle, :config_key, :config_value, :config_type, :created_at)";
        $this->execute($sql, [
            'libelle' => $config->getLibelle(),
            'config_key' => $config->getConfigKey(),
            'config_value' => $config->getConfigValue(),
            'config_type' => $config->getConfigType(),
            'created_at' => $config->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
    public function update(Config $config): void
    {
        $sql = "UPDATE $this->tableName SET 
            libelle = :libelle, config_key = :config_key, config_value = :config_value, config_type = :config_type, updated_at = NOW()
            WHERE id = :id";
        $this->execute($sql, [
            'id' => $config->getId(),
            'libelle' => $config->getLibelle(),
            'config_key' => $config->getConfigKey(),
            'config_value' => $config->getConfigValue(),
            'config_type' => $config->getConfigType(),
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    public function hydrate(array $data): Config
    {
        $config = new Config();
        $value = $data['config_value'];
        $type = $data['config_type'];

        // Conversion du type
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
            ->setLibelle($data['libelle'])
            ->setConfigKey($data['config_key'])
            ->setConfigValue($value)
            ->setConfigType($type)
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $config;
    }

}