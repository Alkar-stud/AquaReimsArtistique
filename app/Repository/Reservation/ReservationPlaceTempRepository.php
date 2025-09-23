<?php
namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationPlaceTemp;
use app\Repository\AbstractRepository;
use DateTime;

class ReservationPlaceTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_place_temp');
    }

    /**
     * Trouve par ID de place et sessionId
     * @param int $place_id
     * @param int $event_session_id
     * @return ReservationPlaceTemp|null
     */
    public function findByPlaceAndSession(int $place_id, int $event_session_id): ?ReservationPlaceTemp
    {
        $sql = "SELECT * FROM $this->tableName WHERE place_id = :place_id AND event_session_id = :event_session_id";
        $result = $this->query($sql, ['place_id' => $place_id, 'event_session_id' => $event_session_id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne toutes les places par sessionId
     * @param string $session_id
     * @return array
     */
    public function findAllSeatsBySession(string $session_id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE session = :session ORDER BY `index`";
        $results = $this->query($sql, ['session' => $session_id]);
        return array_map([$this, 'hydrate'], $results ?: []);
    }

    /**
     * Retourne toutes les places par session d'événement
     * @param int $event_session_id
     * @return array
     */
    public function findByEventSession(int $event_session_id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event_session_id = :event_session_id ORDER BY session, `index`";
        $results = $this->query($sql, ['event_session_id' => $event_session_id]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère une nouvelle réservation temporaire
     * @param ReservationPlaceTemp $reservation
     * @return bool
     */
    public function insert(ReservationPlaceTemp $reservation): bool
    {
        $sql = "INSERT INTO $this->tableName (session, event_session_id, place_id, `index`, created_at, expire_at)
                VALUES (:session, :event_session_id, :place_id, :index, :created_at, :expire_at)";
        return $this->execute($sql, [
            'session' => $reservation->getSession(),
            'event_session_id' => $reservation->getEventSessionId(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'expire_at' => $reservation->getExpireAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Insère une nouvelle réservation temporaire
     * @param string $sessionId
     * @param int $eventSessionId
     * @param int $seatId
     * @param int $index
     * @param DateTime $expireAt
     * @return bool
     */
    public function insertTempReservation(string $sessionId, int $eventSessionId, int $seatId, int $index, DateTime $expireAt): bool
    {
        $reservationTemp = new ReservationPlaceTemp();
        $reservationTemp->setSession($sessionId)
            ->setEventSessionId($eventSessionId)
            ->setPlaceId($seatId)
            ->setIndex($index)
            ->setExpireAt($expireAt->format('Y-m-d H:i:s'));
        return $this->insert($reservationTemp);
    }

    /**
     * Met à jour une réservation temporaire
     * @param ReservationPlaceTemp $reservation
     * @return bool
     */
    public function update(ReservationPlaceTemp $reservation): bool
    {
        $sql = "UPDATE $this->tableName SET
                    expire_at = :expire_at
                WHERE session = :session
                  AND event_session_id = :event_session_id
                  AND place_id = :place_id
                  AND `index` = :index";
        return $this->execute($sql, [
            'expire_at' => $reservation->getExpireAt()->format('Y-m-d H:i:s'),
            'session' => $reservation->getSession(),
            'event_session_id' => $reservation->getEventSessionId(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
        ]);
    }

    /**
     * Met à jour le timeout d'une réservation temporaire
     * @param string $sessionId
     * @param int $placeId
     * @param DateTime $newExpireAt
     * @return bool
     */
    public function updateTimeoutForSessionAndPlace(string $sessionId, int $placeId, DateTime $newExpireAt): bool
    {
        $sql = "UPDATE $this->tableName 
                SET expire_at = :expire_at 
                WHERE session = :session_id AND place_id = :place_id";
        return $this->execute($sql, [
            'expire_at' => $newExpireAt->format('Y-m-d H:i:s'),
            'session_id' => $sessionId,
            'place_id' => $placeId
        ]);
    }

    /**
     * Supprime les réservations expirées
     * @param string $now
     * @return void
     */
    public function deleteExpired(string $now): void
    {
        $sql = "DELETE FROM $this->tableName WHERE expire_at < :now";
        $this->execute($sql, ['now' => $now]);
    }

    /**
     * Supprime toutes les réservations d'une session
     * @param string $session
     * @return void
     */
    public function deleteBySession(string $session): void
    {
        $sql = "DELETE FROM $this->tableName WHERE session = :session";
        $this->execute($sql, ['session' => $session]);
    }

    /**
     * Supprime une réservation temporaire par place
     * @param string $session
     * @param int $seatId
     * @param int $eventSessionId
     * @return void
     */
    public function deleteBySessionAndPlace(string $session, int $seatId, int $eventSessionId): void
    {
        $sql = "DELETE FROM $this->tableName 
                WHERE session = :session AND `place_id` = :seatId AND event_session_id = :eventSessionId";
        $this->execute($sql, ['session' => $session, 'seatId' => $seatId, 'eventSessionId' => $eventSessionId]);
    }

    /**
     * Hydrate une réservation temporaire
     * @param array $data
     * @return ReservationPlaceTemp
     */
    protected function hydrate(array $data): ReservationPlaceTemp
    {
        $reservation = new ReservationPlaceTemp();
        $reservation->setSession($data['session'])
            ->setEventSessionId((int)$data['event_session_id'])
            ->setPlaceId((int)$data['place_id'])
            ->setIndex((int)$data['index'])
            ->setExpireAt($data['expire_at'])
            ->setCreatedAt($data['created_at']);
        return $reservation;
    }
}
