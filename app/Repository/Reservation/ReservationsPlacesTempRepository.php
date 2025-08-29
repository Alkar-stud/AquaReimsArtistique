<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationsPlacesTemp;
use app\Repository\AbstractRepository;

class ReservationsPlacesTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_places_temp');
    }

    public function findByPlace(int $place_id): ?ReservationsPlacesTemp
    {
        $sql = "SELECT * FROM $this->tableName WHERE place_id = :place_id";
        $result = $this->query($sql, ['place_id' => $place_id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    public function findAllSeatsBySession(string $session_id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE session = :session";
        $results = $this->query($sql, ['session' => $session_id]);
        return array_map([$this, 'hydrate'], $results ?: []);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY session, `index`";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function insert(ReservationsPlacesTemp $reservation): int
    {
        $sql = "INSERT INTO $this->tableName (session, place_id, `index`, created_at, timeout)
                VALUES (:session, :place_id, :index, :created_at, :timeout)";
        $this->execute($sql, [
            'session' => $reservation->getSession(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            'timeout' => $reservation->getTimeout()->format('Y-m-d H:i:s')
        ]);
        return $this->getLastInsertId();
    }

    public function insertTempReservation(string $sessionId, int $seatId, int $index, \DateTime $now, \DateTime $timeout): bool
    {
        $reservationTemp = new \app\Models\Reservation\ReservationsPlacesTemp();
        $reservationTemp->setSession($sessionId)
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
                `index` = :index,
                created_at = :created_at,
                timeout = :timeout
                WHERE id = :id";
        return $this->execute($sql, [
            'id' => $reservation->getId(),
            'session' => $reservation->getSession(),
            'place_id' => $reservation->getPlaceId(),
            'index' => $reservation->getIndex(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            'timeout' => $reservation->getTimeout()->format('Y-m-d H:i:s')
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

    public function deleteBySessionAndPlace(string $session, int $seatId): void
    {
        $sql = "DELETE FROM $this->tableName WHERE session = :session AND `place_id` = :seatId";
        $this->execute($sql, ['session' => $session, 'seatId' => $seatId]);
    }

    protected function hydrate(array $data): ReservationsPlacesTemp
    {
        $reservation = new ReservationsPlacesTemp();
        $reservation->setSession($data['session'])
            ->setPlaceId($data['place_id'])
            ->setIndex($data['index'])
            ->setCreatedAt($data['created_at'])
            ->setTimeout($data['timeout']);
        return $reservation;
    }
}