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
     * Trouve les réservations par email
     * @param string $email
     * @return Reservations[]
     */
    public function findByEmail(string $email): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE email = :email ORDER BY created_at DESC";
        $results = $this->query($sql, ['email' => $email]);
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
            (event, nom, prenom, email, phone, nageuse_si_limitation, total_amount, total_amount_paid, 
             token, token_expire_at, comments, created_at)
            VALUES (:event, :nom, :prenom, :email, :phone, :nageuse_si_limitation, :total_amount, :total_amount_paid,
             :token, :token_expire_at, :comments, :created_at)";

        $this->execute($sql, [
            'event' => $reservation->getEvent(),
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
     * Annule une réservation
     * @param int $id
     * @return bool
     */
    public function cancel(int $id): bool
    {
        $sql = "UPDATE $this->tableName SET is_canceled = 1, updated_at = NOW() WHERE id = :id";
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
     * Compte le nombre de réservations actives pour un événement
     * @param int $eventId
     * @return int
     */
    public function countActiveReservationsForEvent(int $eventId): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE event = :eventId AND is_canceled = 0";
        $result = $this->query($sql, ['eventId' => $eventId]);
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
            ->setEvent($data['event'])
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