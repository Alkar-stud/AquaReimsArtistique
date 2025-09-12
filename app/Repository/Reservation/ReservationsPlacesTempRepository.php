<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationsPlacesTemp;
use app\Repository\AbstractRepository;
use DateTime;

class ReservationsPlacesTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_places_temp');
    }

     public function findByPlaceAndSession(int $place_id, int $event_session_id): ?ReservationsPlacesTemp
     {
         $sql = "SELECT * FROM $this->tableName WHERE place_id = :place_id AND event_session_id = :event_session_id";
         $result = $this->query($sql, ['place_id' => $place_id, 'event_session_id' => $event_session_id]);
         return $result ? $this->hydrate($result[0]) : null;
     }

    public function findAllSeatsBySession(string $session_id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE session = :session";
        $results = $this->query($sql, ['session' => $session_id]);
        return array_map([$this, 'hydrate'], $results ?: []);
    }

    /**
     * Trouve toutes les réservations temporaires pour une session d'événement spécifique.
     */
    public function findByEventSession(int $event_session_id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event_session_id = :event_session_id ORDER BY session, `index`";
        $results = $this->query($sql, ['event_session_id' => $event_session_id]);
        return array_map([$this, 'hydrate'], $results);
    }

    public function insert(ReservationsPlacesTemp $reservation): int
    {
        $sql = "INSERT INTO $this->tableName (session, event_session_id, place_id, `index`, created_at, timeout)
                VALUES (:session, :event_session_id, :place_id, :index, :created_at, :timeout)";
        $this->execute($sql, [
            'session' => $reservation->getSession(),
            'event_session_id' => $reservation->getEventSessionId(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            'timeout' => $reservation->getTimeout()->format('Y-m-d H:i:s')
        ]);
        return $this->getLastInsertId();
    }

    public function insertTempReservation(string $sessionId, int $eventSessionId, int $seatId, int $index, \DateTime $now, \DateTime $timeout): bool
    {
        $reservationTemp = new \app\Models\Reservation\ReservationsPlacesTemp();
        $reservationTemp->setSession($sessionId)
            ->setEventSessionId($eventSessionId)
            ->setPlaceId($seatId)
            ->setIndex($index)
            ->setCreatedAt($now->format('Y-m-d H:i:s'))
            ->setTimeout($timeout->format('Y-m-d H:i:s'));
        $this->insert($reservationTemp);
        return true;
    }

    public function update(ReservationsPlacesTemp $reservation): bool
    {
        $sql = "UPDATE $this->tableName SET
                session = :session,
                place_id = :place_id,
                event_session_id = :event_session_id,
                index = :index,
                created_at = :created_at,
                timeout = :timeout
                WHERE id = :id";
        return $this->execute($sql, [
            'session' => $reservation->getSession(),
            'event_session_id' => $reservation->getEventSessionId(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            'timeout' => $reservation->getTimeout()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Met à jour le timeout pour une place spécifique d'une session.
     *
     * @param string $sessionId L'ID de la session PHP.
     * @param int $placeId L'ID de la place.
     * @param DateTime $newTimeout Le nouveau timestamp d'expiration.
     * @return bool
     */
    public function updateTimeoutForSessionAndPlace(string $sessionId, int $placeId, DateTime $newTimeout): bool
    {
        $sql = "UPDATE $this->tableName 
                SET timeout = :timeout 
                WHERE session = :session_id AND place_id = :place_id";

        return $this->execute($sql, [
            'timeout' => $newTimeout->format('Y-m-d H:i:s'),
            'session_id' => $sessionId,
            'place_id' => $placeId
        ]);
    }


    public function deleteExpired(string $now): void
    {
        $sql = "DELETE FROM $this->tableName WHERE timeout < :now";
        $this->execute($sql, ['now' => $now]);
    }

    public function deleteBySession(string $session): void
    {
        $sql = "DELETE FROM $this->tableName WHERE session = :session";
        $this->execute($sql, ['session' => $session]);
    }

     public function deleteBySessionAndPlace(string $session, int $seatId, int $eventSessionId): void
     {
         $sql = "DELETE FROM $this->tableName WHERE session = :session AND `place_id` = :seatId AND event_session_id = :eventSessionId";
         $this->execute($sql, ['session' => $session, 'seatId' => $seatId, 'eventSessionId' => $eventSessionId]);
     }

    protected function hydrate(array $data): ReservationsPlacesTemp
    {
        $reservation = new ReservationsPlacesTemp();
        $reservation->setSession($data['session'])
            ->setEventSessionId($data['event_session_id'])
            ->setPlaceId($data['place_id'])
            ->setIndex($data['index'])
            ->setCreatedAt($data['created_at'])
            ->setTimeout($data['timeout']);
        return $reservation;
    }
}