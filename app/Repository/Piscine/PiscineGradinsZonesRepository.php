<?php

namespace app\Repository\Piscine;

use app\Models\Piscine\PiscineGradinsZones;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class PiscineGradinsZonesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscine_gradins_zones');
    }

    /**
     * Trouve toutes les zones de gradins
     * @return PiscineGradinsZones[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY zone_name ASC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une zone de gradins par son ID
     * @param int $id
     * @return PiscineGradinsZones|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?PiscineGradinsZones
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve toutes les zones de gradins pour une piscine
     * @param int $piscineId
     * @return PiscineGradinsZones[]
     */
    public function findByPiscine(int $piscineId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE piscine = :piscineId ORDER BY zone_name ASC";
        $results = $this->query($sql, ['piscineId' => $piscineId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve toutes les zones ouvertes pour une piscine
     * @param int $piscineId
     * @return PiscineGradinsZones[]
     */
    public function findOpenZonesByPiscine(int $piscineId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE piscine = :piscineId AND is_open = 1 ORDER BY zone_name ASC";
        $results = $this->query($sql, ['piscineId' => $piscineId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une zone par son nom et l'ID de la piscine
     * @param string $zoneName
     * @param int $piscineId
     * @return PiscineGradinsZones|null
     * @throws DateMalformedStringException
     */
    public function findByNameAndPiscine(string $zoneName, int $piscineId): ?PiscineGradinsZones
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone_name = :zoneName AND piscine = :piscineId";
        $result = $this->query($sql, ['zoneName' => $zoneName, 'piscineId' => $piscineId]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Insère une nouvelle zone de gradins
     * @param PiscineGradinsZones $zone
     * @return int ID de la zone insérée
     */
    public function insert(PiscineGradinsZones $zone): int
    {
        $sql = "INSERT INTO $this->tableName
            (piscine, zone_name, nb_seats_vertically, nb_seats_horizontally, is_open, is_stairs_after, created_at)
            VALUES (:piscine, :zone_name, :nb_seats_vertically, :nb_seats_horizontally, :is_open, :is_stairs_after, :created_at)";

        $this->execute($sql, [
            'piscine' => $zone->getPiscine(),
            'zone_name' => $zone->getZoneName(),
            'nb_seats_vertically' => $zone->getNbSeatsVertically(),
            'nb_seats_horizontally' => $zone->getNbSeatsHorizontally(),
            'is_open' => $zone->isOpen() ? 1 : 0,
            'is_stairs_after' => $zone->isStairsAfter() ? 1 : 0,
            'comments' => $zone->getComments(),
            'created_at' => $zone->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour une zone de gradins
     * @param PiscineGradinsZones $zone
     * @return bool Succès de la mise à jour
     */
    public function update(PiscineGradinsZones $zone): bool
    {
        $sql = "UPDATE $this->tableName SET
        piscine = :piscine,
        zone_name = :zone_name,
        nb_seats_vertically = :nb_seats_vertically,
        nb_seats_horizontally = :nb_seats_horizontally,
        is_open = :is_open,
        is_stairs_after = :is_stairs_after,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $zone->getId(),
            'piscine' => $zone->getPiscine(),
            'zone_name' => $zone->getZoneName(),
            'nb_seats_vertically' => $zone->getNbSeatsVertically(),
            'nb_seats_horizontally' => $zone->getNbSeatsHorizontally(),
            'is_open' => $zone->isOpen() ? 1 : 0,
            'is_stairs_after' => $zone->isStairsAfter() ? 1 : 0,
            'comments' => $zone->getComments()
        ]);
    }

    /**
     * Met à jour le statut d'ouverture d'une zone
     * @param int $id
     * @param bool $isOpen
     * @return bool
     */
    public function updateOpenStatus(int $id, bool $isOpen): bool
    {
        $sql = "UPDATE $this->tableName SET is_open = :is_open, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'is_open' => $isOpen ? 1 : 0]);
    }

    /**
     * Hydrate un objet PiscineGradinsZones à partir d'un tableau de données
     * @param array $data
     * @return PiscineGradinsZones
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): PiscineGradinsZones
    {
        $zone = new PiscineGradinsZones();
        $zone->setId($data['id'])
            ->setPiscine($data['piscine'])
            ->setZoneName($data['zone_name'])
            ->setNbSeatsVertically($data['nb_seats_vertically'])
            ->setNbSeatsHorizontally($data['nb_seats_horizontally'])
            ->setIsOpen((bool)$data['is_open'])
            ->setComments($data['comments'])
            ->setIsStairsAfter((bool)$data['is_stairs_after'])
            ->setCreatedAt($data['created_at']);

        if (isset($data['updated_at'])) {
            $zone->setUpdatedAt($data['updated_at']);
        }

        return $zone;
    }
}