<?php
// php
namespace app\Repository\Piscine;

use app\Models\Piscine\PiscineGradinsPlaces;
use app\Repository\AbstractRepository;

class PiscineGradinsPlacesRepository extends AbstractRepository
{
    // Colonnes autorisées
    private $allowed = [
        'id',
        'zone',
        'rank_in_zone',
        'place_number',
        'is_pmr',
        'is_vip',
        'is_volunteer',
        'is_open',
        'created_at',
        'updated_at'
    ];
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
        $sql = "SELECT * FROM $this->tableName ORDER BY rank_in_zone , place_number";
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
     * Recherche par plusieurs champs.
     * @param array $fields  Liste des noms de colonnes.
     * @param array $values  Liste des valeurs correspondantes.
     * @param bool $withZone Inclure l'objet zone.
     * @return PiscineGradinsPlaces[]
     */

    /**
     * Recherche par plusieurs champs (appel: findByFields(['zone' => 3, 'is_open' => 1])).
     * @param array $criteria Tableau associatif colonne => valeur.
     * @param bool $withZone Inclure l'objet zone.
     * @return PiscineGradinsPlaces[]
     */
    public function findByFields(array $criteria, bool $withZone = false): array
    {
        if (empty($criteria)) {
            return [];
        }

        $whereParts = [];
        $params = [];
        $i = 0;

        foreach ($criteria as $col => $value) {
            if (!in_array($col, $this->allowed, true)) {
                return []; // colonne non autorisée
            }
            $paramName = 'p' . $i++;
            $whereParts[] = "$col = :$paramName";
            $params[$paramName] = $value;
        }

        $whereSql = implode(' AND ', $whereParts);
        $sql = "SELECT * FROM {$this->tableName} WHERE $whereSql ORDER BY rank_in_zone, place_number";

        $rows = $this->query($sql, $params);
        if (!$rows) return [];

        $results = [];
        $zonesRepo = $withZone ? new PiscineGradinsZonesRepository() : null;

        foreach ($rows as $row) {
            $zoneObj = null;
            if ($withZone) {
                $zoneObj = $zonesRepo->findById((int)$row['zone']);
            }
            $results[] = $this->hydrate($row, $zoneObj);
        }
        return $results;
    }


    /**
     * Ajoute une place.
     * @return int ID inséré (0 si échec)
     */
    public function insert(PiscineGradinsPlaces $place): int
    {
        $sql = "INSERT INTO $this->tableName
            (zone, rank_in_zone, place_number, is_pmr, is_vip, is_volunteer, is_open, created_at)
            VALUES (:zone, :rank_in_zone, :place_number, :is_pmr, :is_vip, :is_volunteer, :is_open, :created_at)";

        $ok = $this->execute($sql, [
            'zone' => $place->getZone(),
            'rank_in_zone' => $place->getRankInZone(),
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
            rank_in_zone = :rank_in_zone,
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
            'rank_in_zone' => $place->getRankInZone(),
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
            ->setRankInZone($data['rank_in_zone'])
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
