<?php
namespace app\Services;

interface ReservationStorageInterface
{
    public function saveReservation(array $reservation): string;
    public function findReservationById(string $id): ?array;
    public function updateReservation(string $id, array $fields): int;
    public function deleteReservation(string $id): int;
}
