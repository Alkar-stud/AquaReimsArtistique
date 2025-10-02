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
     * Retourne un tableau contenant les informations suivantes :
     * - 'success' (bool) : Indique si l'opération a réussi.
     * - 'limiteAtteinte' (bool) : Vrai si la limite de nageurs est atteinte.
     * - 'limitPerSwimmer' (int|null) : Limite maximale de réservations par nageur, ou null si non définie.
     * - 'currentReservations' (int), existant seulement si 'limit' est null
     *
     * @param int $eventId
     * @param int $swimmerId
     * @return array{
     *     success: bool,
     *     limiteAtteinte: bool,
     *     limitPerSwimmer: int|null
     * }
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
     * @return array Contient 'currentReservations' (int), 'limitReached' (bool), 'limit' (?int), et 'error' (?string).
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

        // Compte les réservations actives pour ce nageur spécifique sur cet événement.
        $currentReservations = $this->reservationQueryService->countActiveReservationsForThisEventAndThisSwimmer($eventId, $swimmerId);

        //Et on retourne
        return [
            'currentReservations' => $currentReservations,
            'limitReached' => $currentReservations >= $limit,
            'limit' => $limit, 'error' => null
        ];
    }

    /**
     * Retourne un tableau contenant les informations suivantes :
     * (bool)limitReached, (?int)limit, (?int)currentReservations: nombre de réservations existantes
     *
     * @return array
     */
    public function getStateOfLimitPerSwimmer(): array
    {
        $eventId   = (int)($session['event_id'] ?? 0);
        $swimmerId = (int)($session['swimmer_id'] ?? 0);
        // Ne teste la limite que si un nageur est effectivement sélectionné
        $swimmerLimitReached = ['limitReached' => false, 'limit' => null, 'currentReservations' => null];
        if ($swimmerId > 0) {
            $swimmerLimitReached = $this->checkSwimmerLimit($eventId, $swimmerId);
        }

        return $swimmerLimitReached;

    }

}