<?php

namespace app\Repository\Tarif;

use app\Models\Tarif\Tarif;
use app\Repository\AbstractRepository;

class TarifRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('tarif');
    }

    /**
     * @return Tarif[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY name";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @param int $id
     * @return Tarif|null
     */
    public function findById(int $id): ?Tarif
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        return $rows ? $this->hydrate($rows[0]) : null;
    }

    /**
     * @return Tarif[]
     */
    public function findAllActive(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE is_active = 1 ORDER BY name";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @param int[] $ids
     * @return Tarif[]
     */
    public function findByIds(array $ids): array
    {
        if (!$ids) return [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ph = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
        $params = [];
        foreach ($ids as $k => $v) { $params["id$k"] = $v; }

        $sql = "SELECT * FROM $this->tableName WHERE id IN ($ph)";
        $rows = $this->query($sql, $params);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Récupère tous les tarifs associés à un événement spécifique
     *
     * @param int $eventId ID de l'événement
     * @return Tarif[] Tableau d'objets Tarif
     */
    public function findByEventId(int $eventId): array
    {
        $sql = "SELECT t.* FROM $this->tableName t
            INNER JOIN event_tarif et ON t.id = et.tarif
            WHERE et.event = :event_id
            ORDER BY t.seat_count DESC, t.name";
        $results = $this->query($sql, ['event_id' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Retourne tous les tarifs pour une liste d'événements, groupés par event_id
     * @param int[] $eventIds
     * @return array<int, Tarif[]>
     */
    public function findByEventIds(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

        $sql = "SELECT t.*, et.event as event_id
                 FROM tarif t
                 INNER JOIN event_tarif et ON et.tarif = t.id
                 WHERE et.event IN ($placeholders)";

        $rows = $this->query($sql, $eventIds);
        $tarifs = array_map([$this, 'hydrate'], $rows);

        $result = [];
        foreach ($tarifs as $index => $tarif) {
            // L'event_id a été ajouté par la jointure
            $eventId = (int)$rows[$index]['event_id'];
            if (!isset($result[$eventId])) {
                $result[$eventId] = [];
            }
            $result[$eventId][] = $tarif;
        }
        return $result;
    }

    /**
     * Vérifie si une liste d'IDs de tarifs contient au moins un tarif avec des places.
     * @param int[] $tarifIds
     * @return bool
     */
    public function hasSeatedTarif(array $tarifIds): bool
    {
        if (empty($tarifIds)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($tarifIds), '?'));

        $sql = "SELECT COUNT(*) as count FROM $this->tableName 
                 WHERE id IN ($placeholders) AND seat_count IS NOT NULL AND seat_count > 0";

        $result = $this->query($sql, $tarifIds);
        return isset($result[0]['count']) && $result[0]['count'] > 0;
    }

    /**
     * Retourne les tarifs en fonction de la présence de places assises ou non
     * @param bool $hasSeats true pour les tarifs avec places, false pour les autres.
     * @return Tarif[]
     */
    public function findBySeatType(bool $hasSeats, int $eventId = 0): array
    {
        if ($hasSeats) {
            $sql = "SELECT * FROM $this->tableName WHERE seat_count IS NOT NULL AND seat_count > 0 ORDER BY name";
        } else {
            $sql = "SELECT * FROM $this->tableName WHERE seat_count IS NULL OR seat_count = 0 ORDER BY name";
        }
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve si le tarif est utilisé dans au moins un event
     * @param int $id
     * @return bool
     */
    public function isUsed(int $id): bool
    {
        $sql = "SELECT COUNT(*) FROM event_tarif WHERE tarif = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result[0]['COUNT(*)'] > 0;
    }

    /**
     * @return int ID inséré (0 si échec)
     */
    public function insert(Tarif $tarif): int
    {
        $sql = "INSERT INTO {$this->tableName}
            (name, description, seat_count, min_age, max_age, max_tickets, price, includes_program, requires_proof, access_code, is_active, created_at)
            VALUES
            (:name, :description, :seat_count, :min_age, :max_age, :max_tickets, :price, :includes_program, :requires_proof, :access_code, :is_active, :created_at)";
        $ok = $this->execute($sql, [
            'name' => $tarif->getName(),
            'description' => $tarif->getDescription(),
            'seat_count' => $tarif->getSeatCount(),
            'min_age' => $tarif->getMinAge(),
            'max_age' => $tarif->getMaxAge(),
            'max_tickets' => $tarif->getMaxTickets(),
            'price' => $tarif->getPrice(),
            'includes_program' => $tarif->getIncludesProgram() ? 1 : 0,
            'requires_proof' => $tarif->getRequiresProof() ? 1 : 0,
            'access_code' => $tarif->getAccessCode(),
            'is_active' => $tarif->getIsActive() ? 1 : 0,
            'created_at' => $tarif->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour
     * @param Tarif $tarif
     * @return bool
     */
    public function update(Tarif $tarif): bool
    {
        $sql = "UPDATE $this->tableName SET
            name = :name,
            description = :description,
            seat_count = :seat_count,
            min_age = :min_age,
            max_age = :max_age,
            max_tickets = :max_tickets,
            price = :price,
            includes_program = :includes_program,
            requires_proof = :requires_proof,
            access_code = :access_code,
            is_active = :is_active,
            updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $tarif->getId(),
            'name' => $tarif->getName(),
            'description' => $tarif->getDescription(),
            'seat_count' => $tarif->getSeatCount(),
            'min_age' => $tarif->getMinAge(),
            'max_age' => $tarif->getMaxAge(),
            'max_tickets' => $tarif->getMaxTickets(),
            'price' => $tarif->getPrice(),
            'includes_program' => $tarif->getIncludesProgram() ? 1 : 0,
            'requires_proof' => $tarif->getRequiresProof() ? 1 : 0,
            'access_code' => $tarif->getAccessCode(),
            'is_active' => $tarif->getIsActive() ? 1 : 0,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function hydrate(array $data): Tarif
    {
        $t = new Tarif();
        $t->setId((int)$data['id'])
            ->setName($data['name'])
            ->setDescription($data['description'] ?? null)
            ->setSeatCount(isset($data['seat_count']) ? (int)$data['seat_count'] : null)
            ->setMinAge(isset($data['min_age']) ? (int)$data['min_age'] : null)
            ->setMaxAge(isset($data['max_age']) ? (int)$data['max_age'] : null)
            ->setMaxTickets(isset($data['max_tickets']) ? (int)$data['max_tickets'] : null)
            ->setPrice((int)$data['price'])
            ->setIncludesProgram((bool)$data['includes_program'])
            ->setRequiresProof((bool)$data['requires_proof'])
            ->setAccessCode($data['access_code'] ?? null)
            ->setIsActive((bool)$data['is_active']);

        return $t;
    }
}
