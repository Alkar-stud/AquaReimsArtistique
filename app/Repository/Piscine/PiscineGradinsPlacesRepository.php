<?php
// php
namespace app\Repository\Piscine;

use app\Models\Piscine\PiscineGradinsPlaces;
use app\Repository\AbstractRepository;

class PiscineGradinsPlacesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscine_gradins_places');
    }

    /**
     * Retourne toutes les places ordonnées par zone et numéro
     * @return PiscineGradinsPlaces[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY rankInZone , place_number";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve une place par son ID.
     * @param int $id
     * @param bool $withZone
     * @return PiscineGradinsPlaces|null
     */
    public function findById(int $id, bool $withZone = true): ?PiscineGradinsPlaces
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $zone = null;
        if ($withZone) {
            $zonesRepo = new PiscineGradinsZonesRepository();
            $zone = $zonesRepo->findById((int)$rows[0]['zone']);
        }
        return $this->hydrate($rows[0], $zone);
    }

    /**
     * Retourne les places d'une zone ordonnées par rang et numéro.
     * @param int $zoneId
     * @param bool|null $onlyOpen
     * @param bool $withZone
     * @return array
     */
    public function findByZone(int $zoneId, ?bool $onlyOpen = null, bool $withZone = true): array
    {
        $flags = [];
        if ($onlyOpen !== null) {
            $flags['is_open'] = $onlyOpen;
        }
        return $this->findByFlags($flags, $zoneId, $withZone);
    }

    /** Wrappers lisibles pour les statuts spéciaux. */
    public function findPmrPlaces(bool $withZone = false): array
    {
        return $this->findByFlags(['is_pmr' => true, 'is_open' => true], null, $withZone);
    }

    public function findVipPlaces(bool $withZone = false): array
    {
        return $this->findByFlags(['is_vip' => true, 'is_open' => true], null, $withZone);
    }

    public function findVolunteerPlaces(bool $withZone = false): array
    {
        return $this->findByFlags(['is_volunteer' => true, 'is_open' => true], null, $withZone);
    }

    /**
     * Retourne les places d'une zone ordonnées par rang et numéro.
     * @param array $flags
     * @param int|null $zoneId
     * @param bool $withZone
     * @return array
     */
    public function findByFlags(array $flags = [], ?int $zoneId = null, bool $withZone = false): array
    {
        $where = [];
        $params = [];

        if ($zoneId !== null) {
            $where[] = 'zone = :zoneId';
            $params['zoneId'] = $zoneId;
        }

        foreach (['is_open', 'is_pmr', 'is_vip', 'is_volunteer'] as $flag) {
            if (array_key_exists($flag, $flags)) {
                $where[] = "$flag = :$flag";
                $params[$flag] = $flags[$flag] ? 1 : 0;
            }
        }

        $sql = "SELECT * FROM $this->tableName"
            . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY rankInZone, place_number";

        $rows = $this->query($sql, $params);

        if (!$withZone) {
            return array_map([$this, 'hydrate'], $rows);
        }

        // Hydratation de la zone : une requête si zoneId est fourni, sinon une par ligne.
        $zoneObject = null;
        if ($zoneId !== null) {
            $zonesRepo = new PiscineGradinsZonesRepository();
            $zoneObject = $zonesRepo->findById($zoneId);
        }

        if ($zoneObject) {
            return array_map(fn(array $r) => $this->hydrate($r, $zoneObject), $rows);
        }

        return array_map(function (array $r) {
            $zonesRepo = new PiscineGradinsZonesRepository();
            $z = $zonesRepo->findById((int)$r['zone']);
            return $this->hydrate($r, $z);
        }, $rows);
    }

    /**
     * Trouve une place par sa zone, son rang et son numéro.
     * @param int $zoneId
     * @param string $rank
     * @param int $placeNumber
     * @param bool $withZone
     * @return PiscineGradinsPlaces|null
     */
    public function findByZoneRankAndNumber(int $zoneId, string $rank, int $placeNumber, bool $withZone = true): ?PiscineGradinsPlaces
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone = :zoneId AND rankInZone = :rank AND place_number = :placeNumber";
        $rows = $this->query($sql, ['zoneId' => $zoneId, 'rank' => $rank, 'placeNumber' => $placeNumber]);
        if (!$rows) return null;

        $zone = null;
        if ($withZone) {
            $zonesRepo = new PiscineGradinsZonesRepository();
            $zone = $zonesRepo->findById($zoneId);
        }
        return $this->hydrate($rows[0], $zone);
    }

    /**
     * Ajoute une place.
     * @return int ID inséré (0 si échec)
     */
    public function insert(PiscineGradinsPlaces $place): int
    {
        $sql = "INSERT INTO $this->tableName
            (zone, rankInZone, place_number, is_pmr, is_vip, is_volunteer, is_open, created_at)
            VALUES (:zone, :rankInZone, :place_number, :is_pmr, :is_vip, :is_volunteer, :is_open, :created_at)";

        $ok = $this->execute($sql, [
            'zone' => $place->getZone(),
            'rankInZone' => $place->getRankInZone(),
            'place_number' => $place->getPlaceNumber(),
            'is_pmr' => $place->isPmr() ? 1 : 0,
            'is_vip' => $place->isVip() ? 1 : 0,
            'is_volunteer' => $place->isVolunteer() ? 1 : 0,
            'is_open' => $place->isOpen() ? 1 : 0,
            'created_at' => $place->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une place
     * @param PiscineGradinsPlaces $place
     * @return bool
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
     * Hydrate une place depuis une ligne BDD.
     * @param array $data
     * @param $zoneObject
     * @return PiscineGradinsPlaces
     */
    protected function hydrate(array $data, $zoneObject = null): PiscineGradinsPlaces
    {
        $place = new PiscineGradinsPlaces();
        $place->setId((int)$data['id'])
            ->setZone((int)$data['zone'])
            ->setRankInZone($data['rankInZone'])
            ->setPlaceNumber($data['place_number'])
            ->setIsPmr((bool)$data['is_pmr'])
            ->setIsVip((bool)$data['is_vip'])
            ->setIsVolunteer((bool)$data['is_volunteer'])
            ->setIsOpen((bool)$data['is_open'])
            ->setCreatedAt($data['created_at']);

        if ($zoneObject) {
            $place->setZoneObject($zoneObject);
        }
        return $place;
    }
}
