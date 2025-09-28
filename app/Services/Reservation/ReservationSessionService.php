<?php

namespace app\Services\Reservation;

use JsonSerializable;

class ReservationSessionService
{
    private string $sessionId;

    public function __construct()
    {
        $this->sessionId = session_id();
        if (!isset($_SESSION['reservation'][$this->sessionId])) {
            $_SESSION['reservation'][$this->sessionId] = [];
        }
    }

    /**
     * Efface complètement la session de réservation en cours et initialise une nouvelle session vide.
     */
    public function clearReservationSession(): void
    {
        unset($_SESSION['reservation'][$this->sessionId]);
        $sessionId = session_id();
        $_SESSION['reservation'][$sessionId] = $this->getDefaultReservationStructure();
    }

    /**
     * Retourne la structure par défaut pour une session de réservation.
     *
     * @return array
     */
    private function getDefaultReservationStructure(): array
    {
        return [
            'event_id' => null,
            'event_session_id' => null,
            'swimmer_id' => null,
            'limitPerSwimmer' => null,
            'access_code_used' => null,
            'user' => null,
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
        return $_SESSION['reservation'][$this->sessionId] ?? null;
    }

    /**
     * Met à jour une valeur spécifique dans la session de réservation.
     * @param string $key
     * @param mixed $value
     */
    public function setReservationSession(string $key, mixed $value): void
    {
        // Sérialise récursivement la valeur pour s'assurer qu'aucun objet n'est stocké en session.
        $_SESSION['reservation'][$this->sessionId][$key] = $this->recursiveSerialize($value);
        // Met à jour le timestamp à chaque modification
        $_SESSION['reservation'][$this->sessionId]['last_activity'] = time();
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

}