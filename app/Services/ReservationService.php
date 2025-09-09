<?php

namespace app\Services;

use app\Repository\Event\EventsRepository;
use DateMalformedStringException;

class ReservationService
{
    private EventsRepository $eventsRepository;
    private NageuseService $nageuseService;

    public function __construct()
    {
        $this->eventsRepository = new EventsRepository();
        $this->nageuseService = new NageuseService();
    }

    /**
     * Vérifie les prérequis d'une réservation (événement, séance, quota nageuse).
     *
     * @param int $eventId
     * @param int $sessionId
     * @param int|null $nageuseId
     * @return array ['success' => bool, 'error' => ?string]
     * @throws DateMalformedStringException
     */
    public function verifyReservationPrerequisites(int $eventId, int $sessionId, ?int $nageuseId): array
    {
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Événement invalide.'];
        }

        // Vérifie que la session sélectionnée appartient bien à l'événement
        $sessionIds = array_map(fn($s) => $s->getId(), $event->getSessions());
        if (!in_array($sessionId, $sessionIds, true)) {
            return ['success' => false, 'error' => 'Séance invalide.'];
        }

        // Si une limitation par nageuse est active, on la vérifie
        if ($event->getLimitationPerSwimmer() !== null) {
            if ($nageuseId === null) {
                return ['success' => false, 'error' => 'La sélection d\'une nageuse est obligatoire pour cet événement.'];
            }

            $limitCheck = $this->nageuseService->isSwimmerLimitReached($eventId, $nageuseId);
            if ($limitCheck['error']) {
                return ['success' => false, 'error' => $limitCheck['error']];
            }
            if ($limitCheck['limitReached']) {
                return ['success' => false, 'error' => 'Le quota de spectateurs pour cette nageuse est atteint.'];
            }
        }

        return ['success' => true, 'error' => null];
    }
}