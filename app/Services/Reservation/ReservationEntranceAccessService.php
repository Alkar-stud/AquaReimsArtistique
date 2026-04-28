<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use DateTime;
use DateTimeInterface;

class ReservationEntranceAccessService
{

    /**
     * Donne la date et l'heure de début de l'événement à partir d'une réservation.
     *
     * @param Reservation $reservation
     * @return DateTime|null L'objet DateTime de début de l'événement, ou null s'il est introuvable.
     */
    public function getEventStartDateTime(Reservation $reservation): ?DateTime
    {
        $eventSession = $reservation->getEventSessionObject();
        if (!$eventSession) {
            return null;
        }

        return DateTime::createFromInterface($eventSession->getOpeningDoorsAt());
    }

    /**
     * Calcul du moment où les modifications sont disponibles.
     *
     * @param DateTime $eventStart L'heure de début de l'événement.
     * @return DateTime L'objet DateTime représentant la période pendant laquelle les modifications sont disponibles.
     */
    public function getModificationAvailableAt(DateTime $eventStart): DateTime
    {
        return (clone $eventStart)->modify('-2 hours');
    }

    /**
     * Vérification si la modification est autorisée en fonction de l'heure actuelle et du temps disponible.
     *
     * @param DateTime $now
     * @param DateTime $availableAt L'objet DateTime lorsque des modifications sont disponibles.
     * @return bool True Si la modification est autorisée, false sinon.
     */
    public function isModificationAllowed(DateTime $now, DateTime $availableAt): bool
    {
        return $now >= $availableAt;
    }

    /**
     * Construit un tableau de réponses d'accès refusé.
     *
     * @param DateTime $availableAt L'objet DateTime lorsque des modifications sont disponibles.
     * @return array Un tableau associatif contenant des informations de refus d'accès.
     */
    public function buildAccessDeniedResponse(DateTime $availableAt): array
    {
        return [
            'allowed' => false,
            'message' => 'Les modifications ne sont pas encore autorisées. Accessible 2h avant l\'ouverture des portes.',
            'availableAt' => $availableAt->format('d/m/Y à H:i'),
        ];
    }

}