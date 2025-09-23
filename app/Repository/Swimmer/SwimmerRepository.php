<?php
namespace app\Repository\Swimmer;

use app\Models\Swimmer\Swimmer;
use app\Models\Swimmer\SwimmerGroup;
use app\Repository\AbstractRepository;

class SwimmerRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('swimmer');
    }

    /**
     * Retourne tous les nageurs ordonnés par nom
     * @return Swimmer[]
     */
    public function findAll(bool $withGroup = false): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY name";
        $rows = $this->query($sql);

        $groupRepo = $withGroup ? new SwimmerGroupRepository() : null;

        return array_map(function (array $r) use ($groupRepo) {
            $group = null;
            if ($groupRepo && !empty($r['group'])) {
                $group = $groupRepo->findById((int)$r['group']);
            }
            return $this->hydrate($r, $group);
        }, $rows);
    }

    /**
     * Retourne un nageur par son ID
     * @param int $id
     * @param bool $withGroup
     * @return Swimmer|null
     */
    public function findById(int $id, bool $withGroup = false): ?Swimmer
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $group = null;
        if ($withGroup && !empty($rows[0]['group'])) {
            $groupRepo = new SwimmerGroupRepository();
            $group = $groupRepo->findById((int)$rows[0]['group']);
        }
        return $this->hydrate($rows[0], $group);
    }

    /**
     * Retourne tous les nageurs d'un groupe
     * @return Swimmer[]
     */
    public function findByGroupId(int $groupId, bool $withGroup = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE `group` = :groupId ORDER BY name";
        $rows = $this->query($sql, ['groupId' => $groupId]);

        $group = null;
        if ($withGroup) {
            $groupRepo = new SwimmerGroupRepository();
            $group = $groupRepo->findById($groupId);
        }

        return array_map(fn(array $r) => $this->hydrate($r, $group), $rows);
    }

    /**
     * Ajoute un nageur
     * @param Swimmer $s
     * @return int
     */
    public function insert(Swimmer $s): int
    {
        $sql = "INSERT INTO $this->tableName (name, `group`, created_at)
                VALUES (:name, :group, :created_at)";
        $ok = $this->execute($sql, [
            'name' => $s->getName(),
            'group' => $s->getGroup(),
            'created_at' => $s->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un nageur
     * @param Swimmer $s
     * @return bool
     */
    public function update(Swimmer $s): bool
    {
        $sql = "UPDATE $this->tableName SET
                name = :name,
                `group` = :group,
                updated_at = NOW()
                WHERE id = :id";
        return $this->execute($sql, [
            'id' => $s->getId(),
            'name' => $s->getName(),
            'group' => $s->getGroup(),
        ]);
    }

    /**
     * Change le groupe d’un nageur (NULL pour détacher).
     * Valide optionnellement l’existence du groupe cible.
     * @param int $swimmerId
     * @param int|null $newGroupId
     * @param bool $ensureGroupExists
     * @return bool
     */
    public function changeGroup(int $swimmerId, ?int $newGroupId, bool $ensureGroupExists = true): bool
    {
        if ($ensureGroupExists && $newGroupId !== null) {
            $gRepo = new SwimmerGroupRepository();
            if (!$gRepo->findById($newGroupId)) {
                return false; // groupe inexistant
            }
        }

        $sql = "UPDATE $this->tableName SET `group` = :group, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $swimmerId, 'group' => $newGroupId]);
    }

    /**
     * Supprime tous les nageurs d'un groupe
     * @param int $swimmerGroupId
     * @return bool
     */
    public function deleteBySwimmerId(int $swimmerGroupId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE group = :group";
        return $this->execute($sql, ['group' => $swimmerGroupId]);
    }

    /**
     * Hydrate un nageur
     * @param array $data
     * @param SwimmerGroup|null $group
     * @return Swimmer
     */
    protected function hydrate(array $data, ?SwimmerGroup $group = null): Swimmer
    {
        $s = new Swimmer();
        $s->setId((int)$data['id'])
            ->setName($data['name'])
            ->setGroup(isset($data['group']) ? (int)$data['group'] : null)
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $s->setUpdatedAt($data['updated_at']); }
        if ($group) { $s->setGroupObject($group); }

        return $s;
    }
}
