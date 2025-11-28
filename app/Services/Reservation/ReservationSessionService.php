<?php

namespace app\Services\Reservation;

use app\Models\Tarif\Tarif;
use app\Repository\Reservation\ReservationComplementTempRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Utils\DurationHelper;
use JsonSerializable;

class ReservationSessionService
{
    private ?ReservationTempRepository $reservationTempRepository = null;
    private ?ReservationDetailTempRepository $reservationDetailTempRepository = null;
    private ?ReservationComplementTempRepository $reservationComplementTempRepository = null;

    public function __construct(
        ?ReservationTempRepository $reservationTempRepository = null,
        ?ReservationDetailTempRepository $reservationDetailTempRepository = null,
        ?ReservationComplementTempRepository $reservationComplementTempRepository = null
    )
    {
        $this->reservationTempRepository = $reservationTempRepository;
        $this->reservationDetailTempRepository = $reservationDetailTempRepository;
        $this->reservationComplementTempRepository = $reservationComplementTempRepository;
    }

    /**
     * Efface complètement la session de réservation en cours et initialise une nouvelle session vide.
     */
    public function clearReservationSession(): void
    {
        $_SESSION['reservation'] = $this->getDefaultReservationStructure();
        $this->reservationTempRepository->deleteBySession(session_id());
    }

    /**
     * Retourne la structure par défaut pour une session de réservation.
     *
     * @return array
     */
    public function getDefaultReservationStructure(): array
    {
        return [
            'event_id' => null,
            'event_session_id' => null,
            'reservation_temp_id' => null,
            'swimmer_id' => null,
            'limit_per_swimmer' => null,
            'access_code_used' => null,
            'booker' => [
                'name'    => $_SESSION['booker']['name'] ?? null,
                'firstname' => $_SESSION['booker']['firstname'] ?? null,
                'email'  => $_SESSION['booker']['email'] ?? null,
                'phone'  => $_SESSION['booker']['phone'] ?? null,
            ],
            'reservation_detail' => [],
            'reservation_complement' => [],
            'last_activity' => time(),
        ];
    }

    /** Récupère les données de la session de réservation en cours.
     *
     * @return array
     */
    public function getReservationTempSession(): array
    {
        $sessionId = session_id();

        // Avant de récupérer la session en cours, on nettoie toutes les réservations temporaires expirées
        $this->clearExpiredReservations();
        // ou celle en cours si step == 1 pour qu'il n'y ait qu'une seule fois session_id dans la table.

        $session = ['reservation' => null, 'reservation_details' => null, 'reservation_complements' => null];
        //On va chercher les infos dans les tables _temp à l'aide de session_id()

        // On récupère la session en cours dans la table
        $reservationTemp = $this->getReservationTempRepository()->findBySessionId($sessionId);
        if (!$reservationTemp) {
            return $session;
        }
        $session['reservation'] = $reservationTemp;

        // On va ensuite chercher s'il y a des places assises réservées
        $reservationDetails = $this->getReservationDetailTempRepository()->findByReservationTemp($reservationTemp->getId());
        $session['reservation_details'] = $reservationDetails; // peut être []

        // On va ensuite chercher s'il y a des compléments réservés
        $reservationComplements = $this->getReservationComplementTempRepository()->findByReservationTemp($reservationTemp->getId());
        $session['reservation_complements'] = $reservationComplements; // peut être []

        return $session;
    }


    /**
     * Récupère les données de la session de réservation en cours.
     * @return array|null
     */
    public function getReservationSession(): ?array
    {
        return $_SESSION['reservation'] ?? null;
    }

    /**
     * Met à jour une valeur spécifique dans la session de réservation.
     * @param string|array $key
     * @param mixed $value
     */
    public function setReservationSession(string|array $key, mixed $value): void
    {
        if (is_array($key)) {
            $ref = &$_SESSION['reservation'];
            foreach ($key as $k) {
                $ref = &$ref[$k];
            }
            // Sérialise récursivement la valeur pour s'assurer qu'aucun objet n'est stocké en session.
            $ref = $this->recursiveSerialize($value);
        } else {
            $_SESSION['reservation'][$key] = $this->recursiveSerialize($value);
        }
        // Met à jour le timestamp à chaque modification
        $_SESSION['reservation']['last_activity'] = time();
    }


    /**
     * Parcourt récursivement une valeur (tableau ou objet) et convertit tous les objets
     * implémentant JsonSerializable en leur représentation de tableau.
     *
     * @param mixed $data
     * @return mixed
     */
    private function recursiveSerialize(mixed $data): mixed
    {
        if ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (is_array($data)) {
            return array_map([$this, 'recursiveSerialize'], $data);
        }

        return $data;
    }


    /**
     * TTL de session de réservation en secondes, dérivé de TIMEOUT_SESSION (ISO 8601, ex PT20M).
     * Fallback à 1800s si non défini ou invalide.
     *
     * @return int
     */
    public function getReservationTimeoutDuration(): int
    {
        $isoDuration = defined('TIMEOUT_PLACE_RESERV') ? TIMEOUT_PLACE_RESERV : 'PT30M';
        $seconds = DurationHelper::iso8601ToSeconds($isoDuration);

        return (is_int($seconds) && $seconds > 0) ? $seconds : 1800;
    }


    /**
     * Vérifie si la session de réservation est expirée
     *
     * @param array $session
     * @return bool
     */
    public function isReservationSessionExpired(array $session): bool
    {
        $last = (int)($session['last_activity'] ?? 0);
        if ($last <= 0) {
            return true;
        }
        $ttl = $this->getReservationTimeoutDuration();
        return (time() - $last) > $ttl;
    }

    /**
     * Supprime toutes les réservations temporaires expirées de la base de données.
     * Le timeout est défini par la constante TIMEOUT_PLACE_RESERV.
     */
    private function clearExpiredReservations(): void
    {
        $timeoutSeconds = $this->getReservationTimeoutDuration();
        $this->getReservationTempRepository()->deleteByTimeout($timeoutSeconds);
    }

    /**
     * Supprime à la première étape les entrées liées à session_id pour éviter des doublons
     *
     * @param string $sessionId
     */
    private function clearForNewStart(string $sessionId): void
    {
        $this->getReservationTempRepository()->deleteBySession($sessionId);
    }

    /**
     * @param array $reservationDetails
     * @param $allEventTarifs
     * @return array
     */
    public function arraySessionForFormStep3(array $reservationDetails, $allEventTarifs): array
    {
        //on parcourt le tableau pour retourner un autre tableau avec index=tarif_id et valeur=quantité de ce tarif
        if (empty($reservationDetails)) {
            return [];
        }
        // Compter le nombre de places pour chaque tarif_id
        $tarifIds = array_column($reservationDetails, 'tarif_id');
        $placesPerTarifId = array_count_values($tarifIds);

        // Convertir le nombre de places en nombre de "packs" (quantité de tarifs)
        $tarifQuantities = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifId = $tarif->getId();
            $nbPlacesInTarif = $tarif->getSeatCount() ?? 1;
            if (isset($placesPerTarifId[$tarifId])) {
                // On s'assure que la division est entière et logique.
                // Si on a 4 places pour un tarif de 4 places, ça fait 1 pack.
                // La division par zéro est évitée car getNbPlace() renvoie 1 par défaut.
                $tarifQuantities[$tarifId] = (int)($placesPerTarifId[$tarifId] / $nbPlacesInTarif);
            }
        }
        return $tarifQuantities;
    }

    /**
     * Pour préparer reservation_detail de $_SESSION en tableau prêt, en regroupant les packs et indexé par tarif_id
     *
     * @param array $reservationDetails
     * @param array $tarifsById
     * @return array
     */
    public function prepareSessionReservationDetailToView(array $reservationDetails, array $tarifsById): array
    {
        $details = [];

        foreach ($reservationDetails as $detail) {
            $tarifId = (int)($detail['tarif_id'] ?? 0);
            if ($tarifId <= 0 || !isset($tarifsById[$tarifId])) {
                // Incohérence d'entrée : on ignore la ligne invalide pour ne pas générer d'erreur en vue
                continue;
            }

            $tarif = $tarifsById[$tarifId];

            if (!isset($details[$tarifId])) {
                $details[$tarifId] = [
                    'tarif_name'  => $tarif->getName(),
                    'description' => $tarif->getDescription(),
                    'price'       => $tarif->getPrice(),
                ];
            }

            // On empile les participants sous index numériques (attendu par le résumé)
            $details[$tarifId][] = $detail;
        }

        return $details;
    }


    /**
     * Prépare `reservation_complement` pour la vue:
     * - groupe par tarif_id
     * - somme les qty
     * - liste les codes distincts non vides
     * - ajoute les infos du tarif (name, description, price)
     *
     * @param array $reservationComplement
     * @param array<int,Tarif> $tarifsById
     * @return array
     */
    public function prepareReservationComplementToView(array $reservationComplement, array $tarifsById): array
    {
        if (empty($reservationComplement)) {
            return [];
        }

        $complements = [];
        foreach ($reservationComplement as $row) {
            $tarifId = (int)($row['tarif_id'] ?? 0);
            if ($tarifId <= 0 || !isset($tarifsById[$tarifId])) {
                continue;
            }

            $qty  = max(0, (int)($row['qty'] ?? 0));
            $code = trim((string)($row['tarif_access_code'] ?? ''));

            if (!isset($complements[$tarifId])) {
                $tarif = $tarifsById[$tarifId];
                $complements[$tarifId] = [
                    'tarif_name'  => $tarif->getName(),
                    'description' => $tarif->getDescription(),
                    'price'       => $tarif->getPrice(),
                    'qty'         => 0,
                    'codes'       => [],
                ];
            }

            $complements[$tarifId]['qty'] += $qty;
            if ($code !== '') {
                $complements[$tarifId]['codes'][$code] = true;
            }
        }

        // Normalise les codes en liste simple
        foreach ($complements as &$group) {
            $group['codes'] = array_keys($group['codes']);
        }
        unset($group);

        return $complements;
    }

    /**
     * Méthode lazy pour instancier le repository ReservationTemp uniquement si nécessaire
     * @return ReservationTempRepository
     */
    private function getReservationTempRepository(): ReservationTempRepository
    {
        if ($this->reservationTempRepository === null) {
            $this->reservationTempRepository = new ReservationTempRepository();
        }
        return $this->reservationTempRepository;
    }

    /**
     * Méthode lazy pour instancier le repository uniquement si nécessaire
     * @return ReservationDetailTempRepository
     */
    private function getReservationDetailTempRepository(): ReservationDetailTempRepository
    {
        if ($this->reservationDetailTempRepository === null) {
            $this->reservationDetailTempRepository = new ReservationDetailTempRepository();
        }
        return $this->reservationDetailTempRepository;
    }

    /**
     * Méthode lazy pour instancier le repository uniquement si nécessaire
     * @return ReservationComplementTempRepository
     */
    private function getReservationComplementTempRepository(): ReservationComplementTempRepository
    {
        if ($this->reservationComplementTempRepository === null) {
            $this->reservationComplementTempRepository = new ReservationComplementTempRepository();
        }
        return $this->reservationComplementTempRepository;
    }


}