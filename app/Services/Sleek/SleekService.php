<?php
namespace app\Services\Sleek;

use app\Core\DatabaseSleekDB;
use RuntimeException;

/**
 * Wrapper léger autour d'un Store SleekDB pour opérations communes.
 */
class SleekService
{
    private object $store;

    public function __construct(string $collectionName = 'reservation')
    {
        $this->store = DatabaseSleekDB::getStore($collectionName);
    }

    public function create(array $document): string
    {
        // SleekDB retourne un id (string ou int selon config)
        $result = $this->store->insert($document);
        // Selon la version, insert peut retourner l'id ou le document ; normaliser en string
        if (is_array($result) && isset($result['_id'])) {
            return (string)$result['_id'];
        }
        if (is_string($result) || is_int($result)) {
            return (string)$result;
        }
        // fallback : tenter de récupérer 'id' si présent
        if (is_array($result) && isset($result['id'])) {
            return (string)$result['id'];
        }
        throw new RuntimeException('Impossible de récupérer l\'id après insertion SleekDB.');
    }

    /**
     * @return array<int,array> tableau de documents
     */
    public function find(array $filter = [], array $options = []): array
    {
        return iterator_to_array($this->store->fetch($filter, $options));
    }

    public function findOne(array $filter = []): ?array
    {
        $doc = $this->store->findOne($filter);
        return $doc === null ? null : (array)$doc;
    }

    public function update(array $filter, array $data): int
    {
        // SleekDB update peut retourner un int (count) ou tableau; normaliser en int
        $res = $this->store->update($filter, $data);
        if (is_int($res)) {
            return $res;
        }
        if (is_array($res) && isset($res['updated'])) {
            return (int)$res['updated'];
        }
        return 0;
    }

    public function delete(array $filter): int
    {
        $res = $this->store->delete($filter);
        if (is_int($res)) {
            return $res;
        }
        if (is_array($res) && isset($res['deleted'])) {
            return (int)$res['deleted'];
        }
        return 0;
    }

    public function countDocuments(array $filter = []): int
    {
        return (int)$this->store->count($filter);
    }
}
