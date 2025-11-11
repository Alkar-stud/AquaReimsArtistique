<?php
namespace app\Repository\Swimmer;

use app\Models\Swimmer\SwimmerGroup;
use app\Repository\AbstractRepository;

class SwimmerGroupRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('swimmer_group');
    }

    /**
     * Retourne tous les groupes de nageurs
     * @return SwimmerGroup[]
     */
    public function findAll(bool $onlyIsActive = true, bool $withSwimmers = false): array
    {
        $sql = "SELECT * FROM $this->tableName"
            . ($onlyIsActive ? " WHERE is_active = 1" : "")
            . " ORDER BY `order`";
        $rows = $this->query($sql);

        $groups = array_map([$this, 'hydrate'], $rows);

        if ($withSwimmers && $groups) {
            $swimmerRepo = new SwimmerRepository();
            foreach ($groups as $g) {
                $g->setSwimmers($swimmerRepo->findByGroupId($g->getId(), false));
            }
        }

        return $groups;
    }

    /**
     * Retourne un groupe de nageurs par son ID
     * @param int $id
     * @param bool $withSwimmers
     * @return SwimmerGroup|null
     */
    public function findById(int $id, bool $withSwimmers = false): ?SwimmerGroup
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $g = $this->hydrate($rows[0]);

        if ($withSwimmers) {
            $swimmerRepo = new SwimmerRepository();
            $g->setSwimmers($swimmerRepo->findByGroupId($g->getId(), false));
        }

        return $g;
    }

    /**
     * Trouve les groupes actifs par leurs IDs.
     *
     * @param int[] $ids
     * @return SwimmerGroup[]
     */
    public function findActiveByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        // On s'assure que les IDs sont bien des entiers pour la sécurité
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->tableName} WHERE id IN ({$placeholders}) AND is_active = 1 ORDER BY `order` ASC";

        $rows = $this->query($sql, $ids);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Ajoute un groupe de nageurs
     * @param SwimmerGroup $g
     * @return int
     */
    public function insert(SwimmerGroup $g): int
    {
        $sql = "INSERT INTO $this->tableName
                (name, coach, is_active, `order`, created_at)
                VALUES (:name, :coach, :is_active, :order, :created_at)";
        $ok = $this->execute($sql, [
            'name' => $g->getName(),
            'coach' => $g->getCoach(),
            'is_active' => $g->getIsActive() ? 1 : 0,
            'order' => $g->getOrder(),
            'created_at' => $g->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un groupe de nageurs
     * @param SwimmerGroup $g
     * @return bool
     */
    public function update(SwimmerGroup $g): bool
    {
        $sql = "UPDATE $this->tableName SET
                name = :name,
                coach = :coach,
                is_active = :is_active,
                `order` = :order,
                updated_at = NOW()
                WHERE id = :id";
        return $this->execute($sql, [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'coach' => $g->getCoach(),
            'is_active' => $g->getIsActive() ? 1 : 0,
            'order' => $g->getOrder(),
        ]);
    }

    /**
     * Hydrate un groupe de nageurs
     * @param array $data
     * @return SwimmerGroup
     */
    private function hydrate(array $data): SwimmerGroup
    {
        $g = new SwimmerGroup();
        $g->setId((int)$data['id'])
            ->setName($data['name'])
            ->setCoach($data['coach'] ?? null)
            ->setIsActive((bool)$data['is_active'])
            ->setOrder((int)$data['order'])
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $g->setUpdatedAt($data['updated_at']); }

        return $g;
    }
}
