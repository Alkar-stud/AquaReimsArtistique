<?php

namespace app\Repository\Piscine;

use app\Models\Piscine\PiscineGradinsPlaces;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class PiscineGradinsPlacesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscine_gradins_places');
    }

    /**
     * Trouve toutes les places de gradins
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY rankInZone ASC, place_number ASC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une place de gradins par son ID
     * @param int $id
     * @return PiscineGradinsPlaces|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?PiscineGradinsPlaces
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        // Hydrate la zone associée
        $zoneId = $result[0]['zone'];
        $zonesRepo = new \app\Repository\Piscine\PiscineGradinsZonesRepository();
        $zone = $zonesRepo->findById($zoneId);

        return $this->hydrate($result[0], $zone);
    }

    /**
     * Trouve toutes les places pour une zone
     * @param int $zoneId
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findByZone(int $zoneId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone = :zoneId ORDER BY rankInZone ASC, place_number ASC";
        $results = $this->query($sql, ['zoneId' => $zoneId]);
        // Récupère la zone une seule fois
        $zonesRepo = new \app\Repository\Piscine\PiscineGradinsZonesRepository();
        $zone = $zonesRepo->findById($zoneId);
        return array_map(function($data) use ($zone) {
            return $this->hydrate($data, $zone);
        }, $results);
    }

    /**
     * Trouve toutes les places ouvertes pour une zone
     * @param int $zoneId
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findOpenPlacesByZone(int $zoneId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone = :zoneId AND is_open = 1 ORDER BY rankInZone ASC, place_number ASC";
        $results = $this->query($sql, ['zoneId' => $zoneId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve toutes les places PMR disponibles
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findPmrPlaces(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE is_pmr = 1 AND is_open = 1";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve toutes les places VIP disponibles
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findVipPlaces(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE is_vip = 1 AND is_open = 1";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve toutes les places pour volontaires disponibles
     * @return PiscineGradinsPlaces[]
     * @throws DateMalformedStringException
     */
    public function findVolunteerPlaces(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE is_volunteer = 1 AND is_open = 1";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une place spécifique par sa zone, son rang et son numéro
     * @param int $zoneId
     * @param string $rank
     * @param int $placeNumber
     * @return PiscineGradinsPlaces|null
     * @throws DateMalformedStringException
     */
    public function findByZoneRankAndNumber(int $zoneId, string $rank, int $placeNumber): ?PiscineGradinsPlaces
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone = :zoneId AND rankInZone = :rank AND place_number = :placeNumber";
        $result = $this->query($sql, [
            'zoneId' => $zoneId,
            'rank' => $rank,
            'placeNumber' => $placeNumber
        ]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Insère une nouvelle place de gradins
     * @param PiscineGradinsPlaces $place
     * @return int ID de la place insérée
     */
    public function insert(PiscineGradinsPlaces $place): int
    {
        $sql = "INSERT INTO $this->tableName
            (zone, rankInZone, place_number, is_pmr, is_vip, is_volunteer, is_open, created_at)
            VALUES (:zone, :rankInZone, :place_number, :is_pmr, :is_vip, :is_volunteer, :is_open, :created_at)";

        $this->execute($sql, [
            'zone' => $place->getZone(),
            'rankInZone' => $place->getRankInZone(),
            'place_number' => $place->getPlaceNumber(),
            'is_pmr' => $place->isPmr() ? 1 : 0,
            'is_vip' => $place->isVip() ? 1 : 0,
            'is_volunteer' => $place->isVolunteer() ? 1 : 0,
            'is_open' => $place->isOpen() ? 1 : 0,
            'created_at' => $place->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour une place de gradins
     * @param PiscineGradinsPlaces $place
     * @return bool Succès de la mise à jour
     */
    public function update(PiscineGradinsPlaces $place): bool
    {
        $sql = "UPDATE $this->tableName SET
        zone = :zone,
        rankInZone = :rankInZone,
        place_number = :place_number,
        is_pmr = :is_pmr,
        is_vip = :is_vip,
        is_volunteer = :is_volunteer,
        is_open = :is_open,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $place->getId(),
            'zone' => $place->getZone(),
            'rankInZone' => $place->getRankInZone(),
            'place_number' => $place->getPlaceNumber(),
            'is_pmr' => $place->isPmr() ? 1 : 0,
            'is_vip' => $place->isVip() ? 1 : 0,
            'is_volunteer' => $place->isVolunteer() ? 1 : 0,
            'is_open' => $place->isOpen() ? 1 : 0
        ]);
    }

    /**
     * Met à jour le statut d'ouverture d'une place
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
     * Met à jour les attributs spéciaux d'une place (PMR, VIP, volontaire)
     * @param int $id
     * @param bool $isPmr
     * @param bool $isVip
     * @param bool $isVolunteer
     * @return bool
     */
    public function updateSpecialAttributes(int $id, bool $isPmr, bool $isVip, bool $isVolunteer): bool
    {
        $sql = "UPDATE $this->tableName SET 
                is_pmr = :is_pmr, 
                is_vip = :is_vip, 
                is_volunteer = :is_volunteer,
                updated_at = NOW() 
                WHERE id = :id";

        return $this->execute($sql, [
            'id' => $id,
            'is_pmr' => $isPmr ? 1 : 0,
            'is_vip' => $isVip ? 1 : 0,
            'is_volunteer' => $isVolunteer ? 1 : 0
        ]);
    }

    /**
     * Hydrate un objet PiscineGradinsPlaces à partir d'un tableau de données
     * @param array $data
     * @return PiscineGradinsPlaces
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data, $zoneObject = null): PiscineGradinsPlaces
    {
        $place = new PiscineGradinsPlaces();
        $place->setId($data['id'])
            ->setZone($data['zone'])
            ->setRankInZone($data['rankInZone'])
            ->setPlaceNumber($data['place_number'])
            ->setIsPmr((bool)$data['is_pmr'])
            ->setIsVip((bool)$data['is_vip'])
            ->setIsVolunteer((bool)$data['is_volunteer'])
            ->setIsOpen((bool)$data['is_open'])
            ->setCreatedAt($data['created_at']);

        if (isset($data['updated_at'])) {
            $place->setUpdatedAt($data['updated_at']);
        }

        // Hydratation automatique de la zone associée
        if ($zoneObject) {
            $place->setZoneObject($zoneObject);
        }

        return $place;
    }
}