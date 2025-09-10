<?php

namespace app\Services;

use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Reservation\ReservationsRepository;
use DateMalformedStringException;

class NageuseService
{
    private GroupesNageusesRepository $groupesNageusesRepository;
    private NageusesRepository $nageusesRepository;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;

    public function __construct()
    {
        $this->groupesNageusesRepository = new GroupesNageusesRepository();
        $this->nageusesRepository = new NageusesRepository();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
    }

    public function getAllGroupesNageuses(): array
    {
        return $this->groupesNageusesRepository->findAll();
    }

    public function getNageusesByGroupe(): array
    {
        $nageuses = $this->nageusesRepository->findAll();
        $nageusesParGroupe = [];
        foreach ($nageuses as $nageuse) {
            $groupeId = $nageuse->getGroupe();
            if (!isset($nageusesParGroupe[$groupeId])) {
                $nageusesParGroupe[$groupeId] = [];
            }
            $nageusesParGroupe[$groupeId][] = [
                'id' => $nageuse->getId(),
                'nom' => $nageuse->getName()
            ];
        }
        return $nageusesParGroupe;
    }

    /**
     * Vérifie si la limite de spectateurs pour une nageuse spécifique est atteinte pour un événement.
     *
     * @param int $eventId L'ID de l'événement.
     * @param int $nageuseId L'ID de la nageuse.
     * @return array Contient 'limitReached' (bool), 'limit' (?int), et 'error' (?string).
     * @throws DateMalformedStringException
     */
    public function isSwimmerLimitReached(int $eventId, int $nageuseId): array
    {
        $nageuse = $this->nageusesRepository->findById($nageuseId);
        if (!$nageuse) {
            return ['limitReached' => true, 'limit' => null, 'error' => 'Nageuse invalide.'];
        }

        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            return ['limitReached' => true, 'limit' => null, 'error' => 'Événement non trouvé.'];
        }

        $limit = $event->getLimitationPerSwimmer();
        // S'il n'y a pas de limite pour cet événement, la limite n'est jamais atteinte.
        if ($limit === null) {
            return ['limitReached' => false, 'limit' => null, 'error' => null];
        }
        //S'il y a une limite, on la renseigne dans $_SESSION
        $_SESSION['reservation'][session_id()]['nageuse_id'] = $nageuseId;

        // Compte les réservations actives pour cette nageuse spécifique sur cet événement.
        $currentReservations = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

        return ['limitReached' => $currentReservations >= $limit, 'limit' => $limit, 'error' => null];
    }

    public function checkNageuseLimit(int $eventId, int $nageuseId): array
    {
        if (!$eventId || !$nageuseId) {
            return ['success' => false, 'limiteAtteinte' => true, 'error' => 'Paramètres manquants'];
        }

        $result = $this->isSwimmerLimitReached($eventId, $nageuseId);
        if ($result['error']) {
            return ['success' => false, 'limiteAtteinte' => true, 'error' => $result['error']];
        }

        return $result;
    }

    /**
     * Récupère le statut des réservations pour une nageuse sur un événement (limite, déjà réservées, restantes).
     *
     * @param int $eventId
     * @param int $nageuseId
     * @return array ['limit' => ?int, 'reserved' => int, 'remaining' => ?int]
     * @throws DateMalformedStringException
     */
    public function getSwimmerReservationStatus(int $eventId, int $nageuseId): array
    {
        $event = $this->eventsRepository->findById($eventId);
        if (!$event || $event->getLimitationPerSwimmer() === null) {
            return ['limit' => null, 'reserved' => 0, 'remaining' => null];
        }

        $limit = $event->getLimitationPerSwimmer();
        $reserved = $this->reservationsRepository->countReservationsForNageuse($eventId, $nageuseId);
        $remaining = max(0, $limit - $reserved);

        return [
            'limit' => $limit,
            'reserved' => $reserved,
            'remaining' => $remaining,
        ];
    }

}