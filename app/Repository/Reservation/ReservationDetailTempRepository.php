<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Models\Reservation\ReservationDetailTemp;

class ReservationDetailTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_detail_temp');
    }

    /**
     * Récupère un détail de réservation temporaire par son identifiant.
     *
     * @param int $id Identifiant du détail.
     * @return ReservationDetailTemp|null Modèle si trouvé, sinon null.
     */
    public function findById(int $id): ?ReservationDetailTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
        if (empty($rows)) return null;
        return $this->mapRowToModel($rows[0]);
    }

    /**
     * Récupère tous les détails liés à une réservation temporaire.
     *
     * @param int $reservationTempId Identifiant de la réservation temporaire.
     * @return ReservationDetailTemp[] Tableau d'instances (vide si aucune).
     */
    public function findByReservationTemp(int $reservationTempId): array
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE reservation_temp = :rid", ['rid' => $reservationTempId]);
        return array_map(fn($r) => $this->mapRowToModel($r), $rows);
    }

    /**
     * Insère un détail de réservation temporaire en base.
     *
     * Les champs optionnels (par ex. justificatif, place_number) peuvent être null.
     * En cas de succès, l'identifiant auto-incrémenté est affecté au modèle.
     *
     * @param ReservationDetailTemp $d Modèle à insérer.
     * @return bool True si l'insertion a réussi, false sinon.
     */
    public function insert(ReservationDetailTemp $d): bool
    {
        $sql = "INSERT INTO {$this->tableName}
            (reservation_temp, name, firstname, tarif, tarif_access_code, justificatif_name, justificatif_original_name, place_number, created_at, updated_at)
            VALUES (:reservation_temp, :name, :firstname, :tarif, :tarif_access_code, :justificatif_name, :justificatif_original_name, :place_number, :created_at, :updated_at)";
        $params = [
            'reservation_temp' => $d->getReservationTemp(),
            'name' => $d->getName(),
            'firstname' => $d->getFirstName(),
            'tarif' => $d->getTarif(),
            'tarif_access_code' => $d->getTarifAccessCode(),
            'justificatif_name' => $d->getJustificatifName(),
            'justificatif_original_name' => $d->getJustificatifOriginalName(),
            'place_number' => $d->getPlaceNumber(),
            'created_at' => $d->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $d->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $ok = $this->execute($sql, $params);
        if ($ok) {
            $d->setId($this->getLastInsertId());
        }
        return $ok;
    }

    /**
     * Convertit une ligne SQL en instance de ReservationDetailTemp.
     *
     * Gère les conversions de types et les champs optionnels.
     *
     * @param array $row Ligne associatif depuis la base.
     * @return ReservationDetailTemp Modèle peuplé.
     */
    private function mapRowToModel(array $row): ReservationDetailTemp
    {
        $m = new ReservationDetailTemp();
        $m->setId((int)$row['id']);
        $m->setReservationTemp((int)$row['reservation_temp']);
        $m->setName($row['name']);
        $m->setFirstName($row['firstname']);
        $m->setTarif((int)$row['tarif']);
        $m->setTarifAccessCode($row['tarif_access_code']);
        $m->setJustificatifName($row['justificatif_name']);
        $m->setJustificatifOriginalName($row['justificatif_original_name']);
        $m->setPlaceNumber($row['place_number'] !== null ? (string)$row['place_number'] : null);
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }
}
