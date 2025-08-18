<?php

namespace app\Repository;

use app\Models\ReservationMailsSent;
use DateMalformedStringException;

class ReservationMailsSentRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_mails_sent');
    }

    /**
     * Trouve tous les mails envoyés
     * @return ReservationMailsSent[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY sent_at DESC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un mail envoyé par son ID
     * @param int $id
     * @return ReservationMailsSent|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?ReservationMailsSent
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve tous les mails envoyés pour une réservation
     * @param int $reservationId
     * @return ReservationMailsSent[]
     */
    public function findByReservation(int $reservationId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY sent_at DESC";
        $results = $this->query($sql, ['reservationId' => $reservationId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Vérifie si un mail avec un template spécifique a été envoyé pour une réservation
     * @param int $reservationId
     * @param int $templateId
     * @return bool
     */
    public function hasMailBeenSent(int $reservationId, int $templateId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName 
                WHERE reservation = :reservationId AND mail_template = :templateId";
        $result = $this->query($sql, [
            'reservationId' => $reservationId,
            'templateId' => $templateId
        ]);

        return (int)$result[0]['count'] > 0;
    }

    /**
     * Insère un nouvel enregistrement de mail envoyé
     * @param ReservationMailsSent $mailSent
     * @return int ID de l'enregistrement inséré
     */
    public function insert(ReservationMailsSent $mailSent): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, mail_template, sent_at)
            VALUES (:reservation, :mail_template, :sent_at)";

        $this->execute($sql, [
            'reservation' => $mailSent->getReservation(),
            'mail_template' => $mailSent->getMailTemplate(),
            'sent_at' => $mailSent->getSentAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Hydrate un objet ReservationMailsSent à partir d'un tableau de données
     * @param array $data
     * @return ReservationMailsSent
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): ReservationMailsSent
    {
        $mailSent = new ReservationMailsSent();
        $mailSent->setId($data['id'])
            ->setReservation($data['reservation'])
            ->setMailTemplate($data['mail_template'])
            ->setSentAt($data['sent_at']);

        return $mailSent;
    }
}