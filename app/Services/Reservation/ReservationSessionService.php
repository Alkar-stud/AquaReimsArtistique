<?php

namespace app\Services\Reservation;

use app\Utils\DurationHelper;
use JsonSerializable;

class ReservationSessionService
{

    public function __construct(
    )
    {
    }

    /**
     * Efface complètement la session de réservation en cours et initialise une nouvelle session vide.
     */
    public function clearReservationSession(): void
    {
        $_SESSION['reservation'] = $this->getDefaultReservationStructure();
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
     */
    public function getReservationTimeoutDuration(): int
    {
        $iso = TIMEOUT_PLACE_RESERV ?? 'PT30M';
        return DurationHelper::iso8601ToSeconds($iso) ?? 1800;
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


}