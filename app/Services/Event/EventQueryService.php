<?php
namespace app\Services\Event;

use app\Models\Event\Event;
use app\Models\Piscine\Piscine;
use app\Models\Tarif\Tarif;
use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Piscine\PiscineRepository;
use app\Repository\Tarif\TarifRepository;

class EventQueryService
{
    private EventRepository $eventRepository;
    private PiscineRepository $piscineRepository;
    private TarifRepository $tarifRepository;
    private EventInscriptionDateRepository $inscriptionDateRepository;
    private EventSessionRepository $eventSessionRepository;

    public function __construct(
        EventRepository $eventRepository,
        PiscineRepository $piscineRepository,
        TarifRepository $tarifRepository,
        EventInscriptionDateRepository $inscriptionDateRepository,
        EventSessionRepository $eventSessionRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->piscineRepository = $piscineRepository;
        $this->tarifRepository = $tarifRepository;
        $this->inscriptionDateRepository = $inscriptionDateRepository;
        $this->eventSessionRepository = $eventSessionRepository;
    }

    /**
     * Récupère tous les événements avec leurs relations chargées
     *
     * @return Event[]
     */
    public function getAllEventsWithRelations($isUpComing = null): array
    {
        // Récupérer tous les événements de base
        $events = $this->eventRepository->findAllSortByDate();
        if (empty($events)) {
            return [];
        }
        // Créer une map des événements par ID pour un accès facile
        $eventsById = [];
        foreach ($events as $event) {
            $eventsById[$event->getId()] = $event;
        }
        $eventIds = array_keys($eventsById);

        // Récupérer toutes les relations en une seule fois (Eager Loading manuel)
        $piscineIds = array_map(fn(Event $e) => $e->getPlace(), $events);
        $piscines = $this->piscineRepository->findByIds(array_unique($piscineIds));
        $piscinesById = [];
        foreach ($piscines as $piscine) {
            $piscinesById[$piscine->getId()] = $piscine;
        }

        $tarifsByEventId = $this->tarifRepository->findByEventIds($eventIds);
        $sessionsByEventId = $this->eventSessionRepository->findByEventIds($eventIds);

        $inscriptionDatesByEventId = $this->inscriptionDateRepository->findByEventIds($eventIds);

        // Attacher les relations aux objets Event correspondants
        foreach ($events as $event) {
            $eventId = $event->getId();

            // Attacher la piscine
            if (isset($piscinesById[$event->getPlace()])) {
                $event->setPiscine($piscinesById[$event->getPlace()]);
            }

            // Attacher les tarifs
            if (isset($tarifsByEventId[$eventId])) {
                $event->setTarifs($tarifsByEventId[$eventId]);
            }

            // Attacher les sessions
            if (isset($sessionsByEventId[$eventId])) {
                $event->setSessions($sessionsByEventId[$eventId]);
            }

            // Attacher les dates d'inscription
            if (isset($inscriptionDatesByEventId[$eventId])) {
                $event->setInscriptionDates($inscriptionDatesByEventId[$eventId]);
            }
        }

        return $events;
    }

    /**
     * Récupère toutes les piscines disponibles.
     *
     * @return Piscine[]
     */
    public function getAllPiscines(): array
    {
        return $this->piscineRepository->findAll();
    }

    /**
     * Récupère tous les tarifs actifs disponibles.
     *
     * @return Tarif[]
     */
    public function getAllActiveTarifs(): array
    {
        return $this->tarifRepository->findAllActive();
    }
}