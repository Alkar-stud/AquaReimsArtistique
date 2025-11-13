<?php

namespace app\Services\Mongo;

use app\Core\DatabaseMongoDB;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;

class MongoService
{
    private string $baseCollectionName;

    public function __construct(string $collectionName)
    {
        $this->baseCollectionName = $collectionName;
    }

    /**
     * @param array $document
     * @param string|null $subType
     * @return string
     */
    public function create(array $document, ?string $subType = null): string
    {
        $collection = $this->getCollection($subType);
        $result = $collection->insertOne($document);
        return (string)$result->getInsertedId();
    }

    /**
     * @param array $filter
     * @param array $options
     * @param string|null $subType
     * @return CursorInterface
     */
    public function find(array $filter = [], array $options = [], ?string $subType = null): CursorInterface
    {
        $collection = $this->getCollection($subType);
        return $collection->find($filter, $options); // Retourne le cursor directement
    }

    /**
     * @param array $filter
     * @param array $options
     * @param string|null $subType
     * @return array|null
     */
    public function findOne(array $filter = [], array $options = [], ?string $subType = null): ?array
    {
        $collection = $this->getCollection($subType);
        $doc = $collection->findOne($filter, $options);
        return $doc?->getArrayCopy();
    }

    /**
     * @param array $filter
     * @param array $update
     * @param string|null $subType
     * @return int
     */
    public function update(array $filter, array $update, ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        $result = $collection->updateOne($filter, $update);
        return $result->getModifiedCount();
    }

    /**
     * @param array $filter
     * @param array $options
     * @param string|null $subType
     * @return int
     */
    public function delete(array $filter, array $options = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        $result = $collection->deleteMany($filter, $options);
        return $result->getDeletedCount();
    }

    /**
     * @param array $filter
     * @param array $options
     * @param string|null $subType
     * @return int
     */
    public function deleteOne(array $filter, array $options = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        $result = $collection->deleteOne($filter, $options);
        return $result->getDeletedCount();
    }

    /**
     * @param string|null $subType
     * @return Collection
     */
    private function getCollection(?string $subType = null): Collection
    {
        $collectionName = $this->baseCollectionName;

        if ($subType) {
            $collectionName = $this->baseCollectionName . '_' . $subType;
        }

        return DatabaseMongoDB::getDatabase()->selectCollection($collectionName);
    }

    /**
     * @param array $filter
     * @param string|null $subType
     * @return int
     */
    public function countDocuments(array $filter = [], ?string $subType = null): int
    {
        $collection = $this->getCollection($subType);
        return $collection->countDocuments($filter);
    }
}