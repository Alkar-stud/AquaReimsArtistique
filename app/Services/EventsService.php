<?php

namespace app\Services;

use app\Models\Event\EventInscriptionDates;
use app\Models\Event\Events;
use app\Repository\Event\EventInscriptionDatesRepository;
use app\Repository\Event\EventsRepository;
use app\Models\Event\EventSession;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Piscine\PiscinesRepository;
use app\Repository\TarifsRepository;
use DateMalformedStringException;

class EventsService
{
    private EventsRepository $eventsRepository;
    private PiscinesRepository $piscinesRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $inscriptionDatesRepository;
    private EventSessionRepository $eventSessionRepository;


    /**
     * Constructeur du service avec injection des repositories nécessaires
     */
    public function __construct(
        ?EventsRepository $eventsRepository = null,
        ?PiscinesRepository $piscinesRepository = null,
        ?TarifsRepository $tarifsRepository = null,
        ?EventInscriptionDatesRepository $inscriptionDatesRepository = null,
        ?EventSessionRepository $eventSessionRepository = null
    ) {
        $this->eventsRepository = $eventsRepository ?? new EventsRepository();
        $this->piscinesRepository = $piscinesRepository ?? new PiscinesRepository();
        $this->tarifsRepository = $tarifsRepository ?? new TarifsRepository();
        $this->inscriptionDatesRepository = $inscriptionDatesRepository ?? new EventInscriptionDatesRepository();
        $this->eventSessionRepository = $eventSessionRepository ?? new EventSessionRepository();
    }

    /**
     * Récupère tous les événements avec leurs relations chargées
     *
     * @return Events[]
     * @throws DateMalformedStringException
     */
    public function getAllEvents(): array
    {
        $events = $this->eventsRepository->findAll();

        // Charger les relations pour chaque événement
        foreach ($events as $event) {
            $this->loadRelations($event);
        }

        return $events;
    }

    /**
     * Récupère les événements à venir ou passés avec leurs relations chargées
     *
     * @param bool $upComing True pour les événements à venir, False pour les événements passés
     * @return Events[]
     * @throws DateMalformedStringException
     */
    public function getUpcomingEvents(bool $upComing = true): array
    {
        $events = $this->eventsRepository->findSortByDate($upComing);

        // Charger les relations pour chaque événement
        foreach ($events as $event) {
            $this->loadRelations($event);
        }

        return $events;
    }

    /**
     * Récupère un événement par son ID avec ses relations chargées
     *
     * @param int $id
     * @return Events|null
     * @throws DateMalformedStringException
     */
    public function getEventById(int $id): ?Events
    {
        $event = $this->eventsRepository->findById($id);

        if ($event) {
            $this->loadRelations($event);
        }

        return $event;
    }

    /**
     * Crée un nouvel événement
     *
     * @param array $data Données de l'événement
     * @return int ID de l'événement créé
     * @throws DateMalformedStringException
     */
    public function createEvent(Events $event, array $tarifs = [], array $inscriptionDates = [], array $sessions = []): int
    {
        $eventId = $this->eventsRepository->insert($event);

        // Tarifs
        foreach ($tarifs as $tarifId) {
            $this->tarifsRepository->addEventTarif($eventId, (int)$tarifId);
        }

        // Dates d'inscription
        foreach ($inscriptionDates as $inscriptionDate) {
            $inscriptionDate->setEvent($eventId);
            $this->inscriptionDatesRepository->insert($inscriptionDate);
        }

        // Sessions
        foreach ($sessions as $sessionData) {
            $session = new EventSession();
            $session->setEventId($eventId)
                ->setSessionName($sessionData['session_name'] ?? null)
                ->setOpeningDoorsAt($sessionData['opening_doors_at'])
                ->setEventStartAt($sessionData['event_start_at'])
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->eventSessionRepository->insert($session);
        }

        return $eventId;
    }

    /**
     * Met à jour un événement existant
     *
     * @param Events $event Objet événement à mettre à jour
     * @param array $tarifs Tableau d'IDs de tarifs associés
     * @param array $inscriptionDates Tableau de dates d'inscription
     * @return bool Succès de la mise à jour
     */
    public function updateEvent(Events $event, array $tarifs = [], array $inscriptionDates = [], array $sessions = []): bool
    {
        $result = $this->eventsRepository->update($event);

        $eventId = $event->getId();

        // Tarifs
        $this->tarifsRepository->deleteEventTarifs($eventId);
        foreach ($tarifs as $tarifId) {
            $this->tarifsRepository->addEventTarif($eventId, (int)$tarifId);
        }

        // Dates d'inscription
        $this->inscriptionDatesRepository->deleteByEventId($eventId);
        foreach ($inscriptionDates as $inscriptionDate) {
            $inscriptionDate->setEvent($eventId);
            $this->inscriptionDatesRepository->insert($inscriptionDate);
        }

        // Sessions
        $this->eventSessionRepository->delete($eventId);
        foreach ($sessions as $sessionData) {
            $session = new EventSession();
            $session->setEventId($eventId)
                ->setSessionName($sessionData['session_name'] ?? null)
                ->setOpeningDoorsAt($sessionData['opening_doors_at'])
                ->setEventStartAt($sessionData['event_start_at'])
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->eventSessionRepository->insert($session);
        }

        return $result;
    }

    /**
     * Supprime un événement
     *
     * @param int $id ID de l'événement à supprimer
     * @return bool Succès de la suppression
     */
    public function deleteEvent(int $id): bool
    {
        // Supprimer d'abord les périodes d'inscription liées
        $this->inscriptionDatesRepository->deleteByEventId($id);

        // Supprimer ensuite les tarifs associés
        $this->tarifsRepository->deleteEventTarifs($id);

        // Supprimer ensuite les séances (sessions) liées
        $this->eventSessionRepository->delete($id);

        // Supprimer enfin l'événement
        return $this->eventsRepository->delete($id);
    }

    /**
     * Récupère toutes les piscines pour l'interface utilisateur
     *
     * @return array Liste des piscines
     */
    public function getAllPiscines(): array
    {
        return $this->piscinesRepository->findAll();
    }

    /**
     * Récupère tous les tarifs pour l'interface utilisateur
     *
     * @return array Liste des tarifs
     */
    public function getAllTarifs(): array
    {
        return $this->tarifsRepository->findAll('all', true);
    }

    /**
     * Hydrate un objet Events à partir d'un tableau de données
     *
     * @param array $data Données pour hydrater l'événement
     * @return Events L'événement hydraté
     * @throws DateMalformedStringException
     */
    private function hydrateEvent(array $data): Events
    {
        $event = new Events();

        if (isset($data['id'])) {
            $event->setId((int)$data['id']);
        }

        $event->setLibelle($data['libelle'] ?? '')
            ->setLieu((int)($data['lieu'] ?? 0))
            ->setEventStartAt($data['event_start_at'] ?? date('Y-m-d H:i:s'))
            ->setLimitationPerSwimmer(($data['limitation_per_swimmer'] === '' || $data['limitation_per_swimmer'] === '0')
                ? null
                : (int)($data['limitation_per_swimmer'] ?? null))
            ->setAssociateEvent(!empty($data['associate_event']) ? (int)$data['associate_event'] : null);

        return $event;
    }

    /**
     * Charge les relations d'un événement (piscine, événement associé, tarifs, dates d'inscription)
     *
     * @param Events $event L'événement à compléter
     * @return void
     * @throws DateMalformedStringException
     */
    private function loadRelations(Events $event): void
    {
        // Charger la piscine associée
        $piscine = $this->piscinesRepository->findById($event->getLieu());
        if ($piscine) {
            $event->setPiscine($piscine);
        }

        // Charger les tarifs
        $tarifs = $this->tarifsRepository->findByEventId($event->getId());
        foreach ($tarifs as $tarif) {
            $event->addTarif($tarif);
        }

        // Charger les dates d'inscription
        $inscriptionDates = $this->inscriptionDatesRepository->findByEventId($event->getId());
        foreach ($inscriptionDates as $date) {
            $date->setEventObject($event);
            $event->addInscriptionDate($date);
        }
    }
}