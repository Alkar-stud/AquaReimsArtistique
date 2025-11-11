<?php
// php
namespace app\Repository\Piscine;

use app\Models\Piscine\Piscine;
use app\Models\Piscine\PiscineGradinsZones;
use app\Repository\AbstractRepository;

class PiscineGradinsZonesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscine_gradins_zones');
    }

    /**
     * Retourne toutes les zones ordonnées par nom.
     * @return PiscineGradinsZones[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY zone_name";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve une zone par son ID.
     * @param int $id
     * @param bool $withPiscine
     * @return PiscineGradinsZones|null
     */
    public function findById(int $id, bool $withPiscine = false): ?PiscineGradinsZones
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $piscine = null;
        if ($withPiscine) {
            $piscineRepo = new PiscineRepository();
            $piscine = $piscineRepo->findById((int)$rows[0]['piscine']);
        }
        return $this->hydrate($rows[0], $piscine);
    }

    /**
     * Retourne les zones d'une piscine ordonnées par nom
     * @return PiscineGradinsZones[]
     */
    public function findByPiscine(int $piscineId, bool $withPiscine = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE piscine = :piscineId ORDER BY zone_name";
        $rows = $this->query($sql, ['piscineId' => $piscineId]);

        $piscine = null;
        if ($withPiscine) {
            $piscineRepo = new PiscineRepository();
            $piscine = $piscineRepo->findById($piscineId);
        }

        return array_map(function(array $r) use ($piscine) {
            return $this->hydrate($r, $piscine);
        }, $rows);
    }

    /**
     * Trouve une zone par son nom et son ID de piscine.
     * @param string $zoneName
     * @param int $piscineId
     * @param bool $withPiscine
     * @return PiscineGradinsZones|null
     */
    public function findByNameAndPiscine(string $zoneName, int $piscineId, bool $withPiscine = false): ?PiscineGradinsZones
    {
        $sql = "SELECT * FROM $this->tableName WHERE zone_name = :zoneName AND piscine = :piscineId";
        $rows = $this->query($sql, ['zoneName' => $zoneName, 'piscineId' => $piscineId]);
        if (!$rows) return null;

        $piscine = null;
        if ($withPiscine) {
            $piscineRepo = new PiscineRepository();
            $piscine = $piscineRepo->findById($piscineId);
        }
        return $this->hydrate($rows[0], $piscine);
    }

    /**
     * Ajoute une zone
     * @return int ID inséré (0 si échec)
     */
    public function insert(PiscineGradinsZones $zone): int
    {
        $sql = "INSERT INTO $this->tableName
            (piscine, zone_name, nb_seats_vertically, nb_seats_horizontally, is_open, is_stairs_after, comments, created_at)
            VALUES (:piscine, :zone_name, :nb_seats_vertically, :nb_seats_horizontally, :is_open, :is_stairs_after, :comments, :created_at)";

        $ok = $this->execute($sql, [
            'piscine' => $zone->getPiscine(),
            'zone_name' => $zone->getZoneName(),
            'nb_seats_vertically' => $zone->getNbSeatsVertically(),
            'nb_seats_horizontally' => $zone->getNbSeatsHorizontally(),
            'is_open' => $zone->isOpen() ? 1 : 0,
            'is_stairs_after' => $zone->isStairsAfter() ? 1 : 0,
            'comments' => $zone->getComments(),
            'created_at' => $zone->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une zone
     * @param PiscineGradinsZones $zone
     * @return bool
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
            comments = :comments,
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
     * Patch générique de colonnes (mise à jour partielle, ajoute toujours updated_at).
     * Les booléens sont convertis en 0/1.
     */
    public function updateFields(int $id, array $fields): bool
    {
        if (!$fields) return false;

        $sets = [];
        $params = ['id' => $id];

        foreach ($fields as $col => $val) {
            $sets[] = "$col = :$col";
            $params[$col] = is_bool($val) ? ($val ? 1 : 0) : $val;
        }
        $sets[] = "updated_at = NOW()";

        $sql = "UPDATE $this->tableName SET " . implode(', ', $sets) . " WHERE id = :id";
        return $this->execute($sql, $params);
    }

    /**
     * Wrapper explicite pour la bascule d'ouverture.
     */
    public function updateOpenStatus(int $id, bool $isOpen): bool
    {
        return $this->updateFields($id, ['is_open' => $isOpen]);
    }

    /**
     * Hydrate une zone depuis une ligne BDD.
     * @param array<string,mixed> $data
     */
    protected function hydrate(array $data, ?Piscine $piscine = null): PiscineGradinsZones
    {
        $zone = new PiscineGradinsZones();
        $zone->setId((int)$data['id'])
            ->setPiscine((int)$data['piscine'])
            ->setZoneName($data['zone_name'])
            ->setNbSeatsVertically((int)$data['nb_seats_vertically'])
            ->setNbSeatsHorizontally((int)$data['nb_seats_horizontally'])
            ->setIsOpen((bool)$data['is_open'])
            ->setIsStairsAfter((bool)$data['is_stairs_after'])
            ->setComments($data['comments'] ?? null)
            ->setCreatedAt($data['created_at']);

        if ($piscine) {
            $zone->setPiscineObject($piscine);
        }
        return $zone;
    }
}
