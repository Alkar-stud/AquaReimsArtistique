<?php

namespace app\Services\Swimmer;

use app\Models\Swimmer\SwimmerGroup;
use app\Repository\Event\EventRepository;
use app\Repository\Swimmer\SwimmerGroupRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\Reservation\ReservationQueryService;

class SwimmerQueryService
{
    private SwimmerGroupRepository $swimmerGroupRepository;
    private SwimmerRepository $swimmerRepository;
    private EventRepository $eventRepository;
    private ReservationQueryService $reservationQueryService;


    public function __construct(
        SwimmerGroupRepository $swimmerGroupRepository,
        SwimmerRepository $swimmerRepository,
        EventRepository $eventRepository,
        ReservationQueryService $reservationQueryService,
    )
    {
        $this->swimmerGroupRepository = $swimmerGroupRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->eventRepository = $eventRepository;
        $this->reservationQueryService = $reservationQueryService;
    }

    /**
     * Récupère uniquement les groupes actifs qui ont au moins un nageur.
     *
     * @param array $groupIdsWithSwimmers Les IDs des groupes qui ont des nageurs.
     * @return SwimmerGroup[]
     */
    public function getActiveGroupsWithSwimmers(array $groupIdsWithSwimmers): array
    {
        if (empty($groupIdsWithSwimmers)) {
            return [];
        }
        // On ne récupère que les groupes actifs et qui sont dans la liste fournie.
        return $this->swimmerGroupRepository->findActiveByIds($groupIdsWithSwimmers);
    }

    /**
     * Récupère tous les nageurs et les organise par ID de groupe.
     *
     * @return array<int, array> Un tableau associatif avec les ID de groupe comme clés.
     */
    public function getSwimmerByGroup(): array
    {
        $swimmers = $this->swimmerRepository->findAll();
        $swimmerPerGroup = [];
        foreach ($swimmers as $swimmer) {
            $groupId = $swimmer->getGroup();
            // On ignore les nageurs qui n'ont pas de groupe assigné.
            if ($groupId !== null) {
                $swimmerPerGroup[$groupId][] = [
                    'id' => $swimmer->getId(),
                    'name' => $swimmer->getName()
                ];
            }
        }
        return $swimmerPerGroup;
    }

    /**
     * Vérifie si la limite de spectateurs pour un nageur spécifique est atteinte pour un événement.
     * @param int $eventId
     * @param int $swimmerId
     * @return array
     */
    public function checkSwimmerLimit(int $eventId, int $swimmerId): array
    {
        if (!$eventId || !$swimmerId) {
            return ['success' => false, 'limiteAtteinte' => true, 'error' => 'Paramètres manquants'];
        }

        $result = $this->isSwimmerLimitReached($eventId, $swimmerId);
        if ($result['error']) {
            return ['success' => false, 'limiteAtteinte' => true, 'error' => $result['error']];
        }

        return $result;
    }

    /**
     * Calcul si la limite de spectateurs pour un nageur spécifique est atteinte pour un événement.
     *
     * @param int $eventId L'ID de l'événement.
     * @param int $swimmerId L'ID du nageur.
     * @return array Contient 'limitReached' (bool), 'limit' (?int), et 'error' (?string).
     */
    public function isSwimmerLimitReached(int $eventId, int $swimmerId): array
    {
        $swimmer = $this->swimmerRepository->findById($swimmerId);
        if (!$swimmer) {
            return ['limitReached' => true, 'limit' => null, 'error' => 'Nageur invalide.'];
        }

        $event = $this->eventRepository->findById($eventId);
        if (!$event) {
            return ['limitReached' => true, 'limit' => null, 'error' => 'Événement non trouvé.'];
        }

        $limit = $event->getLimitationPerSwimmer();
        // S'il n'y a pas de limite pour cet événement, la limite n'est jamais atteinte.
        if ($limit === null) {
            return ['limitReached' => false, 'limit' => null, 'error' => null];
        }
        //S'il y a une limite, on la renseigne dans $_SESSION
        $_SESSION['reservation'][session_id()]['swimmer_id'] = $swimmerId;

        // Compte les réservations actives pour ce nageur spécifique sur cet événement.
        $currentReservations = $this->reservationQueryService->countActiveReservationsForThisEventAndThisSwimmer($eventId, $swimmerId);
;
        //Et on retourne
        return ['limitReached' => $currentReservations >= $limit, 'limit' => $limit, 'error' => null];
    }

}