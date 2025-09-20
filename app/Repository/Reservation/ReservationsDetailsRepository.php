<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationsDetails;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\AbstractRepository;
use app\Repository\TarifRepository;
use DateMalformedStringException;

class ReservationsDetailsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_details');
    }

    /**
     * Trouve tous les détails de réservations
     * @return ReservationsDetails[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un détail de réservation par son ID
     * @param int $id
     * @return ReservationsDetails|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?ReservationsDetails
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve tous les détails des places assises pour une réservation
     * @param int $reservationId
     * @return ReservationsDetails[]
     */
    public function findByReservation(int $reservationId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at";
        $results = $this->query($sql, ['reservationId' => $reservationId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve les détails par numéro de place
     * @param int $placeNumber
     * @return ReservationsDetails[]
     */
    public function findByPlaceNumber(int $placeNumber): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE place_number = :placeNumber ORDER BY created_at DESC";
        $results = $this->query($sql, ['placeNumber' => $placeNumber]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve tous les IDs de places déjà réservées pour une session d'un événement.
     * @param int $sessionId
     * @return array Un tableau plat d'IDs de places.
     */
    public function findReservedSeatsForSession(int $sessionId): array
    {
        $sql = "SELECT rd.place_number
                 FROM reservations_details rd
                 INNER JOIN reservations r ON rd.reservation = r.id
                 WHERE r.event_session = :sessionId
                   AND r.is_canceled = 0
                   AND rd.place_number IS NOT NULL";
        $results = $this->query($sql, ['sessionId' => $sessionId]);

        return array_column($results, 'place_number');
    }

    /**
     * Compte le nombre de détails (donc de places) pour une réservation
     * @param int $reservationId
     * @return int
     */
    public function countByReservation(int $reservationId): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE reservation = :reservationId";
        $result = $this->query($sql, ['reservationId' => $reservationId]);
        return (int)$result[0]['count'];
    }

    /**
     * Trouve tous les détails pour une liste d'IDs de réservation.
     * @param array $reservationIds
     * @return ReservationsDetails[]
     */
    public function findByReservations(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }
        // Crée une chaîne de placeholders (?, ?, ?) pour la clause IN
        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));

        $sql = "SELECT * FROM $this->tableName WHERE reservation IN ($placeholders) ORDER BY created_at";
        $results = $this->query($sql, $reservationIds);
        return $this->hydrateWithRelations($results);
    }

    /**
     * Insère un nouveau détail de réservation
     * @param ReservationsDetails $detail
     * @return int ID du détail inséré
     */
    public function insert(ReservationsDetails $detail): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, nom, prenom, tarif, tarif_access_code, justificatif_name, place_number, created_at)
            VALUES (:reservation, :nom, :prenom, :tarif, :tarif_access_code, :justificatif_name, :place_number, :created_at)";

        $this->execute($sql, [
            'reservation' => $detail->getReservation(),
            'nom' => $detail->getNom(),
            'prenom' => $detail->getPrenom(),
            'tarif' => $detail->getTarif(),
            'tarif_access_code' => $detail->getTarifAccessCode(),
            'justificatif_name' => $detail->getJustificatifName(),
            'place_number' => $detail->getPlaceObject()?->getId(),
            'created_at' => $detail->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour un détail de réservation
     * @param ReservationsDetails $detail
     * @return bool Succès de la mise à jour
     */
    public function update(ReservationsDetails $detail): bool
    {
        $sql = "UPDATE $this->tableName SET 
            reservation = :reservation,
            nom = :nom,
            prenom = :prenom,
            tarif = :tarif,
            tarif_access_code = :tarif_access_code,
            justificatif_name = :justificatif_name,
            place_number = :place_number,
            updated_at = NOW()
            WHERE id = :id";

        return $this->execute($sql, [
            'id' => $detail->getId(),
            'reservation' => $detail->getReservation(),
            'nom' => $detail->getNom(),
            'prenom' => $detail->getPrenom(),
            'tarif' => $detail->getTarif(),
            'tarif_access_code' => $detail->getTarifAccessCode(),
            'justificatif_name' => $detail->getJustificatifName(),
            'place_number' => $detail->getPlaceObject()?->getId()
        ]);
    }

    /**
     * Met à jour un seul champ d'un détail de réservation.
     * @param int $id L'ID du détail
     * @param string $field Le nom de la colonne à mettre à jour
     * @param string|null $value La nouvelle valeur
     * @return bool
     */
    public function updateSingleField(int $id, string $field, ?string $value): bool
    {
        // Liste blanche des champs autorisés pour la sécurité
        $allowedFields = ['nom', 'prenom'];
        if (!in_array($field, $allowedFields)) {
            return false;
        }

        // On ne peut pas utiliser de paramètre pour le nom de la colonne,
        // la liste blanche ci-dessus sert de protection.
        $sql = "UPDATE $this->tableName SET `$field` = :value WHERE id = :id";

        return $this->execute($sql, ['id' => $id, 'value' => $value]);
    }

    /**
     * Met les champs place_number à null lorsqu'il y a annulation
     *
     * @param int $reservationId
     * @return bool;
     */
    public function cancelByReservation(int $reservationId): bool
    {
        $sql = "UPDATE $this->tableName SET place_number = null WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Supprime un détail de réservation
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Supprime tous les détails d'une réservation
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate un objet ReservationsDetails à partir d'un tableau de données
     * @param array $data
     * @return ReservationsDetails
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): ReservationsDetails
    {
        $detail = new ReservationsDetails();
        $detail->setId($data['id'])
            ->setReservation($data['reservation'])
            ->setNom($data['nom'])
            ->setPrenom($data['prenom'])
            ->setTarif($data['tarif'])
            ->setTarifAccessCode($data['tarif_access_code'])
            ->setJustificatifName($data['justificatif_name'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        // Charger l'objet Place si un ID de place est défini
        if ($data['place_number']) { // La colonne contient l'ID de la place.
            $placeRepository = new PiscineGradinsPlacesRepository();
            $place = $placeRepository->findById((int)$data['place_number']);
            if ($place) {
                $detail->setPlaceNumber($place->getPlaceNumber()); // On stocke le numéro (string)
                $detail->setPlaceObject($place);
            }
        }
        return $detail;
    }

    private function hydrateWithRelations(array $detailsData): array
    {
        if (empty($detailsData)) {
            return [];
        }

        $details = array_map([$this, 'hydrate'], $detailsData);

        // Récupérer tous les IDs de tarifs nécessaires
        $tarifIds = array_values(array_unique(array_column($detailsData, 'tarif')));

        // Charger tous les objets tarifs en une seule requête
        $tarifsRepository = new TarifRepository();
        $tarifs = $tarifsRepository->findByIds($tarifIds); // Vous devrez peut-être créer cette méthode

        // Mapper les tarifs par leur ID pour un accès facile
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        // Attacher l'objet tarif à chaque complément
        foreach ($details as $detail) {
            $detail->setTarifObject($tarifsById[$detail->getTarif()] ?? null);
        }

        return $details;
    }
}