<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Models\Reservation\ReservationComplementTemp;

class ReservationComplementTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_complement_temp');
    }

    public function findById(int $id): ?ReservationComplementTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
        if (empty($rows)) return null;
        return $this->mapRowToModel($rows[0]);
    }

    /** @return ReservationComplementTemp[] */
    public function findByReservationTemp(int $reservationTempId): array
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE reservation_temp = :rid", ['rid' => $reservationTempId]);
        return array_map(fn($r) => $this->mapRowToModel($r), $rows);
    }

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
}
