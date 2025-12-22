<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Repository\Tarif\TarifRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Models\Reservation\ReservationDetailTemp;

class ReservationDetailTempRepository extends AbstractRepository
{
    private array $fieldsAllowed = [
        'reservation_temp',
        'name',
        'firstname',
        'tarif',
        'tarif_access_code',
        'justificatif_name',
        'justificatif_original_name',
        'place_number',
    ];

    private ?TarifRepository $tarifRepository;
    private ?PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository;

    public function __construct(?TarifRepository $tarifRepository = null, ?PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository = null)
    {
        parent::__construct('reservation_detail_temp');
        $this->tarifRepository = $tarifRepository;
        $this->piscineGradinsPlacesRepository = $piscineGradinsPlacesRepository;
    }

    /**
     * Méthode lazy pour instancier le repository Tarif uniquement si nécessaire.
     *
     * @return TarifRepository
     */
    private function getTarifRepository(): TarifRepository
    {
        if ($this->tarifRepository === null) {
            $this->tarifRepository = new TarifRepository();
        }
        return $this->tarifRepository;
    }

    /**
     * Méthode lazy pour instancier le repository des places uniquement si nécessaire.
     *
     * @return PiscineGradinsPlacesRepository
     */
    private function getPiscineGradinsPlacesRepository(): PiscineGradinsPlacesRepository
    {
        if ($this->piscineGradinsPlacesRepository === null) {
            $this->piscineGradinsPlacesRepository = new PiscineGradinsPlacesRepository();
        }
        return $this->piscineGradinsPlacesRepository;
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

        $detail = $this->hydrate($rows[0]);
        $this->hydrateRelations([$detail]);
        return $detail;
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
     * Récupère les places en cours de réservation pour une session d'événement,
     * avec l'ID de session temporaire associé.
     *
     * @param int $eventSessionId L'ID de la session de l'événement.
     * @return array
     */
    public function findSeatStatesForSession(int $eventSessionId): array
    {
        $sql = "SELECT
                    rdt.place_number,
                    rt.session_id
                FROM reservation_detail_temp rdt
                JOIN reservation_temp rt ON rdt.reservation_temp = rt.id
                WHERE rt.event_session = :eventSessionId
                  AND rdt.place_number IS NOT NULL";

        $results = $this->query($sql, ['eventSessionId' => $eventSessionId]);

        // Retourne un tableau associatif [place_number => session_id]
        return array_column($results, 'session_id', 'place_number');
    }

    /**
     * Récupère les noms des fichiers justificatifs pour une liste d'IDs de détails.
     *
     * @param int[] $detailIds
     * @return string[]
     */
    public function findJustificatifNamesByIds(array $detailIds): array
    {
        if (empty($detailIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($detailIds), '?'));
        $sql = "SELECT justificatif_name FROM {$this->tableName} WHERE id IN ($placeholders) AND justificatif_name IS NOT NULL";

        $results = $this->query($sql, $detailIds);

        return array_column($results, 'justificatif_name');
    }

    /**
     * Détails pour une liste d'IDs de réservation.
     * @param int[] $reservationIds
     * @param bool $withTarif
     * @param bool $withChildren
     * @param bool $withSwimmer
     * @return array
     */
    public function findByReservations(array $reservationIds, bool $withTarif = false, bool $withChildren = false, bool $withSwimmer = false): array
    {
        if (empty($reservationIds)) return [];

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = "SELECT * FROM {$this->tableName} WHERE reservation_temp IN ($placeholders) ORDER BY id";

        $rows = $this->query($sql, $reservationIds);
        if (empty($rows)) return [];

        // Hydrater les entités
        $list = array_map([$this, 'hydrate'], $rows);

        // Hydrater les relations — la méthode modifie $list en place et ne retourne rien
        $this->hydrateRelations($list);

        // Retourner la liste hydratée
        return $list;
    }


    /**
     * Compte le nombre de détails pour une réservation
     * @param int $reservationTempId
     * @return int
     */
    public function countByReservation(int $reservationTempId): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE reservation_temp = :reservationTempId";
        $result = $this->query($sql, ['reservationTempId' => $reservationTempId]);
        return (int)($result[0]['count'] ?? 0);
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
     * Update l'objet
     * @param ReservationDetailTemp $reservationDetailTemp
     * @return bool
     */
    public function update(ReservationDetailTemp $reservationDetailTemp): bool
    {
        $params = [
            'reservation_temp' => $reservationDetailTemp->getReservationTemp(),
            'name' => $reservationDetailTemp->getName(),
            'firstname' => $reservationDetailTemp->getFirstName(),
            'tarif' => $reservationDetailTemp->getTarif(),
            'tarif_access_code' => $reservationDetailTemp->getTarifAccessCode(),
            'justificatif_name' => $reservationDetailTemp->getJustificatifName(),
            'justificatif_original_name' => $reservationDetailTemp->getJustificatifOriginalName(),
            'place_number' => $reservationDetailTemp->getPlaceNumber(),
            'updated_at' => $reservationDetailTemp->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        return $this->updateById($reservationDetailTemp->getId(), $params);
    }

    /**
     * Convertit une ligne SQL en instance de ReservationDetailTemp.
     *
     * Gère les conversions de types et les champs optionnels.
     *
     * @param array $row Ligne associatif depuis la base.
     * @return ReservationDetailTemp Modèle peuplé.
     */
    protected function hydrate(array $row): ReservationDetailTemp
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

    /**
     * Hydrate les relations (ici, l'objet Tarif) pour une liste de détails.
     *
     * @param ReservationDetailTemp[] $details
     * @return void
     */
    private function hydrateRelations(array $details): void
    {
        if (empty($details)) {
            return;
        }

        $tarifIds = array_unique(array_map(fn($d) => $d->getTarif(), $details));
        if (empty($tarifIds)) {
            return;
        }

        $tarifs = $this->getTarifRepository()->findByIds($tarifIds);
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        foreach ($details as $detail) {
            $detail->setTarifObject($tarifsById[$detail->getTarif()] ?? null);
        }

        // Hydratation de l'objet Place
        $placeIds = array_values(array_unique(array_filter(array_map(fn($d) => $d->getPlaceNumber(), $details))));
        if (empty($placeIds)) {
            return;
        }

        $places = $this->getPiscineGradinsPlacesRepository()->findByIds($placeIds);
        $placesById = [];
        foreach ($places as $place) {
            $placesById[$place->getId()] = $place;
        }
        foreach ($details as $detail) {
            $detail->setPlaceObject($placesById[$detail->getPlaceNumber()] ?? null);
        }
    }
}
