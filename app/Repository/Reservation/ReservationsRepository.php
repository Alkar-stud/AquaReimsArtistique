<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\Reservations;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class ReservationsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations');
    }

    /**
     * Trouve toutes les réservations
     * @return Reservations[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une réservation par son ID
     * @param int $id
     * @return Reservations|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Reservations
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve une réservation par son ID MongoDB
     * @param string $mongoId
     * @return Reservations|null
     * @throws DateMalformedStringException
     */
    public function findByMongoId(string $mongoId): ?Reservations
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation_mongo_id = :mongoId";
        $result = $this->query($sql, ['mongoId' => $mongoId]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve une réservation par son UUID
     * @param string $uuid
     * @return Reservations|null
     * @throws DateMalformedStringException
     */
    public function findByUuid(string $uuid): ?Reservations
    {
        $sql = "SELECT * FROM $this->tableName WHERE uuid = :uuid";
        $result = $this->query($sql, ['uuid' => $uuid]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve une réservation par son token
     * @param string $token
     * @return Reservations|null
     * @throws DateMalformedStringException
     */
    public function findByToken(string $token): ?Reservations
    {
        $sql = "SELECT * FROM $this->tableName WHERE token = :token";
        $result = $this->query($sql, ['token' => $token]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve toutes les réservations pour un événement donné
     * @param int $eventId
     * @return Reservations[]
     */
    public function findByEvent(int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :eventId ORDER BY created_at DESC";
        $results = $this->query($sql, ['eventId' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve toutes les réservations actives (non annulées) pour un événement
     * @param int $eventId
     * @return Reservations[]
     */
    public function findActiveByEvent(int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :eventId AND is_canceled = 0 ORDER BY created_at DESC";
        $results = $this->query($sql, ['eventId' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve les réservations d'un event donné par email
     */
    public function findByEmailAndEvent(string $email, int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE email = :email AND event = :eventId";
        $results = $this->query($sql, [
            'email' => $email,
            'eventId' => $eventId
        ]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère une nouvelle réservation
     * @param Reservations $reservation
     * @return int ID de la réservation insérée
     */
    public function insert(Reservations $reservation): int
    {
        $sql = "INSERT INTO $this->tableName 
             (event, event_session, reservation_mongo_id, uuid, nom, prenom, email, phone, nageuse_si_limitation, total_amount, total_amount_paid,  
             token, token_expire_at, comments, created_at)
              VALUES (:event, :event_session, :reservation_mongo_id, :uuid, :nom, :prenom, :email, :phone, :nageuse_si_limitation, :total_amount, :total_amount_paid,
             :token, :token_expire_at, :comments, :created_at)";

        $this->execute($sql, [
            'event' => $reservation->getEvent(),
            'event_session' => $reservation->getEventSession(),
            'reservation_mongo_id' => $reservation->getReservationMongoId(),
            'uuid' => $reservation->getUuid(),
            'nom' => $reservation->getNom(),
            'prenom' => $reservation->getPrenom(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
            'nageuse_si_limitation' => $reservation->getNageuseId(),
            'total_amount' => $reservation->getTotalAmount(),
            'total_amount_paid' => $reservation->getTotalAmountPaid(),
            'token' => $reservation->getToken(),
            'token_expire_at' => $reservation->getTokenExpireAt()->format('Y-m-d H:i:s'),
            'comments' => $reservation->getComments(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour une réservation
     * @param Reservations $reservation
     * @return bool Succès de la mise à jour
     */
    public function update(Reservations $reservation): bool
    {
        $sql = "UPDATE $this->tableName SET 
        event = :event,
        event_session = :event_session,
        reservation_mongo_id = :reservation_mongo_id,
        uuid = :uuid,
        nom = :nom,
        prenom = :prenom,
        email = :email,
        phone = :phone,
        nageuse_si_limitation = :nageuse_si_limitation,
        total_amount = :total_amount,
        total_amount_paid = :total_amount_paid,
        token = :token,
        token_expire_at = :token_expire_at,
        comments = :comments,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $reservation->getId(),
            'event' => $reservation->getEvent(),
            'event_session' => $reservation->getEventSession(),
            'reservation_mongo_id' => $reservation->getReservationMongoId(),
            'uuid' => $reservation->getUuid(),
            'nom' => $reservation->getNom(),
            'prenom' => $reservation->getPrenom(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
            'nageuse_si_limitation' => $reservation->getNageuseId(),
            'total_amount' => $reservation->getTotalAmount(),
            'total_amount_paid' => $reservation->getTotalAmountPaid(),
            'token' => $reservation->getToken(),
            'token_expire_at' => $reservation->getTokenExpireAt()->format('Y-m-d H:i:s'),
            'comments' => $reservation->getComments()
        ]);
    }

    /**
     * Met à jour un seul champ d'une réservation.
     * @param int $id L'ID de la réservation
     * @param string $field Le nom de la colonne à mettre à jour
     * @param string|null $value La nouvelle valeur
     * @return bool
     */
    public function updateSingleField(int $id, string $field, ?string $value): bool
    {
        // Liste blanche des champs autorisés pour la sécurité
        $allowedFields = ['nom', 'prenom', 'email', 'phone', 'total_amount', 'total_amount_paid'];
        if (!in_array($field, $allowedFields)) {
            return false;
        }
        // Si le champ est 'phone' et que la valeur est une chaîne vide, on la transforme en null
        if ($field === 'phone' && $value === '') {
            $value = null;
        }

        // On ne peut pas utiliser de paramètre pour le nom de la colonne,
        // la liste blanche ci-dessus sert de protection.
        $sql = "UPDATE $this->tableName SET `$field` = :value WHERE id = :id";

        return $this->execute($sql, ['id' => $id, 'value' => $value]);
    }

    /**
     * Annule une réservation
     * @param int $id
     * @param bool $is_canceled
     * @return bool
     */
    public function cancelById(int $id, bool $is_canceled = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_canceled = :is_canceled, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, [
            'id' => $id,
            'is_canceled' => $is_canceled ? 1 : 0
        ]);
    }

    /**
     * Annule une réservation par le token
     * @param string $token
     * @param bool $is_canceled
     * @return bool
     */
    public function cancelByToken(string $token, bool $is_canceled = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_canceled = :is_canceled, updated_at = NOW() WHERE token = :token";
        return $this->execute($sql, [
            'token' => $token,
            'is_canceled' => $is_canceled ? 1 : 0
        ]);
    }


    /**
     * Marque une réservation comme vérifiée / ou non
     * @param int $id
     * @param bool $is_checked
     * @return bool
     */
    public function check(int $id, bool $is_checked = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_checked = ($is_checked == false ? 0 : 1), updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Supprime une réservation
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Compte le nombre de réservations par nageuse pour un événement (excluant les tarifs avec code d'accès).
     * @param int $eventId
     * @param int $nageuseId
     * @return int
     */
    public function countReservationsForNageuse(int $eventId, int $nageuseId): int
    {
        $sql = "SELECT COUNT(*) as count
            FROM reservations_details rd
            INNER JOIN reservations r ON rd.reservation = r.id
            INNER JOIN tarifs t ON rd.tarif = t.id
            WHERE r.event = :eventId AND r.nageuse_si_limitation = :nageuseId AND r.is_canceled = 0 AND t.access_code IS NULL";
        $result = $this->query($sql, ['eventId' => $eventId, 'nageuseId' => $nageuseId]);
        return (int)$result[0]['count'];
    }

    /**
     * Compte le nombre de réservations actives pour un événement
     * @param int $eventId L'ID de l'événement
     * @param int|null $nageuseId Si fourni, compte uniquement les réservations pour cette nageuse.
     * @return int
     */
    public function countActiveReservationsForEvent(int $eventId, ?int $nageuseId = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE event = :eventId AND is_canceled = 0";
        $params = ['eventId' => $eventId];

        if ($nageuseId !== null) {
            $sql .= " AND nageuse_si_limitation = :nageuseId";
            $params['nageuseId'] = $nageuseId;
        }

        $result = $this->query($sql, $params);
        return (int)$result[0]['count'];
    }

    /**
     * Hydrate un objet Reservations à partir d'un tableau de données
     * @param array $data
     * @return Reservations
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): Reservations
    {
        $reservation = new Reservations();
        $reservation->setId($data['id'])
            ->setUuid($data['uuid'])
            ->setEvent($data['event'])
            ->setEventSession($data['event_session'])
            ->setReservationMongoId($data['reservation_mongo_id'])
            ->setNom($data['nom'])
            ->setPrenom($data['prenom'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'])
            ->setNageuseId($data['nageuse_si_limitation'])
            ->setTotalAmount($data['total_amount'])
            ->setTotalAmountPaid($data['total_amount_paid'])
            ->setToken($data['token'])
            ->setTokenExpireAt($data['token_expire_at'])
            ->setIsCanceled((bool)$data['is_canceled'])
            ->setComments($data['comments'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $reservation;
    }
}