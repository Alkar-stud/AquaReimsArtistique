<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Models\Reservation\ReservationComplementTemp;
use app\Repository\Tarif\TarifRepository;

class ReservationComplementTempRepository extends AbstractRepository
{
    private array $fieldsAllowed = [
        'reservation_temp',
        'tarif',
        'tarif_access_code',
        'qty',
    ];

    public function __construct()
    {
        parent::__construct('reservation_complement_temp');
    }

    /**
     * Récupère un complément de réservation temporaire par son identifiant.
     *
     * @param int $id Identifiant du complément.
     * @return ReservationComplementTemp|null Modèle si trouvé, sinon null.
     */
    public function findById(int $id): ?ReservationComplementTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
        if (empty($rows)) return null;
        return $this->mapRowToModel($rows[0]);
    }

    /**
     * Récupère tous les compléments associés à une réservation temporaire.
     *
     * @param int $reservationTempId Identifiant de la réservation temporaire.
     * @return ReservationComplementTemp[] Tableau d'instances (vide si aucune).
     */
    public function findByReservationTemp(int $reservationTempId): array
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE reservation_temp = :rid", ['rid' => $reservationTempId]);
        return array_map(fn($r) => $this->mapRowToModel($r), $rows);
    }

    /**
     * Cherche par plusieurs champs/valeurs
     * @param array $criteria
     * @return array
     */
    public function findByFields(array $criteria): array
    {
        if (empty($criteria)) {
            return [];
        }

        $params = [];
        $clauses = [];
        $i = 0;

        foreach ($criteria as $field => $val) {
            if (!in_array($field, $this->fieldsAllowed, true)) {
                continue;
            }

            if ($val === null) {
                $clauses[] = "`$field` IS NULL";
            } else {
                $p = "p{$i}";
                $clauses[] = "`$field` = :$p";
                $params[$p] = $val;
                $i++;
            }
        }

        if (empty($clauses)) {
            return [];
        }

        $sql = "SELECT * FROM `{$this->tableName}` WHERE " . implode(' AND ', $clauses);
        $rows = $this->query($sql, $params);

        $details = array_map(fn($r) => $this->hydrate($r), $rows);
        $this->hydrateRelations($details);

        return $details;
    }

    /**
     * Insère un complément de réservation temporaire en base.
     *
     * En cas de succès, l'identifiant auto-incrémenté est affecté au modèle.
     *
     * @param ReservationComplementTemp $c Modèle à insérer.
     * @return bool True si l'insertion a réussi, false sinon.
     */
    public function insert(ReservationComplementTemp $c): bool
    {
        $sql = "INSERT INTO {$this->tableName}
            (reservation_temp, tarif, tarif_access_code, qty, created_at, updated_at)
            VALUES (:reservation_temp, :tarif, :tarif_access_code, :qty, :created_at, :updated_at)";
        $params = [
            'reservation_temp' => $c->getReservationTemp(),
            'tarif' => $c->getTarif(),
            'tarif_access_code' => $c->getTarifAccessCode(),
            'qty' => $c->getQty(),
            'created_at' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $c->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $ok = $this->execute($sql, $params);
        if ($ok) {
            $c->setId($this->getLastInsertId());
        }
        return $ok;
    }


    public function update(ReservationComplementTemp $reservationComplementTemp): bool
    {
        $params = [
            'reservation_temp' => $reservationComplementTemp->getReservationTemp(),
            'tarif' => $reservationComplementTemp->getTarif(),
            'tarif_access_code' => $reservationComplementTemp->getTarifAccessCode(),
            'qty' => $reservationComplementTemp->getQty(),
            'updated_at' => $reservationComplementTemp->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        return $this->updateById($reservationComplementTemp->getId(), $params);
    }

    /**
     * Convertit une ligne SQL en instance de ReservationComplementTemp.
     *
     * @param array $row Ligne associatif depuis la base.
     * @return ReservationComplementTemp Modèle peuplé.
     */
    private function mapRowToModel(array $row): ReservationComplementTemp
    {
        $m = new ReservationComplementTemp();
        $m->setId((int)$row['id']);
        $m->setReservationTemp((int)$row['reservation_temp']);
        $m->setTarif((int)$row['tarif']);
        $m->setTarifAccessCode($row['tarif_access_code']);
        $m->setQty((int)$row['qty']);
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }

    /**
     * Convertit une ligne SQL en instance de ReservationComplementTemp.
     *
     * Gère les conversions de types et les champs optionnels.
     *
     * @param array $row Ligne associatif depuis la base.
     * @return ReservationComplementTemp Modèle peuplé.
     */
    protected function hydrate(array $row): ReservationComplementTemp
    {
        $m = new ReservationComplementTemp();
        $m->setId((int)$row['id']);
        $m->setReservationTemp((int)$row['reservation_temp']);
        $m->setTarif((int)$row['tarif']);
        $m->setTarifAccessCode($row['tarif_access_code']);
        $m->setQty($row['qty'] !== null ? (int)$row['qty'] : 0);
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }

    /**
     * Hydrate les relations (ici, l'objet Tarif) pour une liste de détails.
     *
     * @param ReservationComplementTemp[] $complements
     * @return void
     */
    private function hydrateRelations(array $complements): void
    {
        if (empty($complements)) {
            return;
        }

        $tarifIds = array_unique(array_map(fn($d) => $d->getTarif(), $complements));
        if (empty($tarifIds)) {
            return;
        }

        $tarifRepository = new TarifRepository();
        $tarifs = $tarifRepository->findByIds($tarifIds);
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        foreach ($complements as $complement) {
            $complement->setTarifObject($tarifsById[$complement->getTarif()] ?? null);
        }

    }
}
