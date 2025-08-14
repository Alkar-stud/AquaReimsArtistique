<?php

namespace app\Services;

use app\Core\DatabaseMongoDB;
use Exception;
use MongoDB\Collection;

class MongoService
{
    private string $baseCollectionName;

    public function __construct(string $collectionName)
    {
        $this->baseCollectionName = $collectionName;
    }

    public function create(array $document, ?string $subType = null): string
    {
        $collection = $this->getCollection($subType);
        $result = $collection->insertOne($document);
        return (string)$result->getInsertedId();
    }

    public function find(array $filter = [], array $options = [], ?string $subType = null)
    {
        $collection = $this->getCollection($subType);
        return $collection->find($filter, $options); // Retourne le cursor directement
    }

    public function findOne(array $filter = [], array $options = [], ?string $subType = null): ?array
    {
        $collection = $this->getCollection($subType);
        $doc = $collection->findOne($filter, $options);
        return $doc ? $doc->getArrayCopy() : null;
    }

    public function update(array $filter, array $update, array $options = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        $result = $collection->updateMany($filter, ['$set' => $update], $options);
        return $result->getModifiedCount();
    }

    public function delete(array $filter, array $options = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        $result = $collection->deleteMany($filter, $options);
        return $result->getDeletedCount();
    }

    private function getCollection(?string $subType = null): Collection
    {
        $collectionName = $this->baseCollectionName;

        if ($subType) {
            $collectionName = $this->baseCollectionName . '_' . $subType;
        }

        return DatabaseMongoDB::getDatabase()->selectCollection($collectionName);
    }

    public function countDocuments(array $filter = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        return $collection->countDocuments($filter);
    }
}