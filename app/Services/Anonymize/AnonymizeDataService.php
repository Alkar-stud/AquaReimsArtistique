<?php

namespace app\Services\Anonymize;

use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use DateInterval;
use DateTime;
use Exception;

/**
 * Service d’anonymisation des données personnelles après une période de rétention.
 *
 * - S’appuie sur `reservation.anonymized_at` et `reservation_detail.anonymized_at` pour éviter les doublons.
 * - Utilise des stratégies par champ: `fixed:<valeur>`, `null`, `concatIdEmail:<domaine>` (pour `email`).
 * - La période de rétention est fournie au format ISO 8601 pour DateInterval (ex: 'P2Y' ou 'P6M').
 *
 * Exemple d’usage:
 * $service = new AnonymizeDataService('P2Y');
 * $result = $service->run();
 * // $result = ['threshold_date' => 'YYYY-mm-dd HH:ii:ss', 'anonymized_reservations' => n, 'anonymized_details' => m]
 */
class AnonymizeDataService
{
    private string $retentionPeriod;
    private ReservationRepository $reservationRepository;
    private ReservationDetailRepository $reservationDetailRepository;

    //tables où se situe quel type de données à anonymiser
    private array $tablesWhereAnonymize = [
        'reservation' => [
            // champ => stratégie
            'name' => 'fixed:Anonyme',
            'firstname' => 'fixed:Anonyme',
            'email' => 'concatIdEmail:@anonyme.local',
            'phone' => 'null'
        ],
        'reservation_detail' => [
            'name' => 'fixed:Anonyme',
            'firstname' => 'fixed:Anonyme',
            'justificatif_name' => 'null'
        ]
    ];

    public function __construct(string $retentionPeriod)
    {
        $this->retentionPeriod = $retentionPeriod;
        $this->reservationRepository = new ReservationRepository();
        $this->reservationDetailRepository = new ReservationDetailRepository();
    }

    /**
     * Anonymise les données personnelles anciennes.
     *
     * @return array Un résumé des opérations.
     * @throws Exception
     */
    public function run(): array
    {
        $thresholdDate = (new DateTime())->sub(new DateInterval($this->retentionPeriod));
        $threshold = $thresholdDate->format('Y-m-d H:i:s');

        // Démarre la transaction via l'un des repositories
        $this->reservationRepository->beginTransaction();

        try {
            $affectedReservations = 0;
            $affectedDetails = 0;

            foreach ($this->tablesWhereAnonymize as $table => $fieldStrategies) {
                switch ($table) {
                    case 'reservation':
                        $affectedReservations += $this->reservationRepository->anonymizeOlderThan($thresholdDate, $fieldStrategies);
                        break;

                    case 'reservation_detail':
                        $affectedDetails += $this->reservationDetailRepository->anonymizeOlderThan($thresholdDate, $fieldStrategies);
                        break;

                    // Ajoutez ici d'autres tables si nécessaires
                }
            }

            $this->reservationRepository->commit();

            return [
                'threshold_date' => $threshold,
                'anonymized_reservations' => $affectedReservations,
                'anonymized_details' => $affectedDetails,
            ];
        } catch (Exception $e) {
            $this->reservationRepository->rollBack();
            throw $e;
        }

    }
}