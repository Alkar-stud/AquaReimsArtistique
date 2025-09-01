<?php
namespace app\Services;

use app\Repository\Reservation\ReservationsRepository;

class MysqlReservationStorage implements ReservationStorageInterface
{
    private ReservationsRepository $repo;
    public function __construct() {
        $this->repo = new ReservationsRepository();
    }
    public function saveReservation(array $reservation): string {
        // À adapter selon la méthode de ton repo
        return $this->repo->save($reservation);
    }
    public function findReservationById(string $id): ?array {
        return $this->repo->findById($id);
    }
}
