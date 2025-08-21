<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationsDetails;
use app\Repository\AbstractRepository;
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
     * Trouve tous les détails pour une réservation
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
     * Compte le nombre de détails pour une réservation
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
     * Compte le nombre de places déjà réservées par nageuse et événement
     * @param int $eventId
     * @param int $nageuseId
     * @return int
     */
    public function countPlacesForNageuseAndEvent(int $eventId, int $nageuseId): int
    {
        $sql = "SELECT COUNT(rd.id) as count
            FROM reservations_details rd
            INNER JOIN reservations r ON rd.reservation = r.id
            WHERE r.event = :eventId
              AND r.nageuse_si_limitation = :nageuseId
              AND r.is_canceled = 0";
        $result = $this->query($sql, [
            'eventId' => $eventId,
            'nageuseId' => $nageuseId
        ]);
        return (int)($result[0]['count'] ?? 0);
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
            'place_number' => $detail->getPlaceNumber(),
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
            'place_number' => $detail->getPlaceNumber()
        ]);
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
            ->setPlaceNumber($data['place_number'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $detail;
    }
}