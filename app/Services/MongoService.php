<?php

namespace app\Services;

use app\Core\DatabaseMongoDB;
use MongoDB\Collection;

class MongoService
{
    private Collection $collection;

    public function __construct(string $collectionName)
    {
        $this->collection = DatabaseMongoDB::getDatabase()->selectCollection($collectionName);
    }

    public function create(array $document): string
    {
        $result = $this->collection->insertOne($document);
        return (string)$result->getInsertedId();
    }

    public function find(array $filter = [], array $options = []): array
    {
        return $this->collection->find($filter, $options)->toArray();
    }

    public function findOne(array $filter = [], array $options = []): ?array
    {
        $doc = $this->collection->findOne($filter, $options);
        return $doc ? $doc->getArrayCopy() : null;
    }

    public function update(array $filter, array $update, array $options = []): int
    {
        $result = $this->collection->updateMany($filter, ['$set' => $update], $options);
        return $result->getModifiedCount();
    }

    public function delete(array $filter, array $options = []): int
    {
        $result = $this->collection->deleteMany($filter, $options);
        return $result->getDeletedCount();
    }
}