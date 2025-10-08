<?php
namespace app\Services\Reservation;

use app\Services\Mongo\MongoReservationStorage;
use app\Services\Sleek\SleekReservationStorage;
use app\Utils\NsqlIdGenerator;

/**
 * Orchestrateur d'écriture parallèle MongoDB + SleekDB avec un nsql_id commun.
 */
class DualReservationWriter
{
    public function __construct(
        private MongoReservationStorage $mongo,
        private SleekReservationStorage $sleek
    ) {}

    /**
     * Insère le même document dans les deux bases avec un nsql_id commun.
     * @return array{nsql_id:string, mongo_id:string, sleek_id:string}
     */
    public function save(array $reservation): array
    {
        $reservation['nsql_id'] = $reservation['nsql_id'] ?? NsqlIdGenerator::new();

        $mongoId = $this->mongo->saveReservation($reservation);
        $sleekId = $this->sleek->saveReservation($reservation);

        return [
            'nsql_id'  => $reservation['nsql_id'],
            'mongo_id' => $mongoId,
            'sleek_id' => $sleekId,
        ];
    }
}
