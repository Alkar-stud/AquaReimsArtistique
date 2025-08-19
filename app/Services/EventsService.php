<?php

namespace app\Services;

use app\Models\Event\EventInscriptionDates;
use app\Models\Event\Events;
use app\Repository\Event\EventInscriptionDatesRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Piscine\PiscinesRepository;
use app\Repository\TarifsRepository;
use DateMalformedStringException;

class EventsService
{
    private EventsRepository $eventsRepository;
    private PiscinesRepository $piscinesRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $inscriptionDatesRepository;

    /**
     * Constructeur du service avec injection des repositories nécessaires
     */
    public function __construct(
        ?EventsRepository $eventsRepository = null,
        ?PiscinesRepository $piscinesRepository = null,
        ?TarifsRepository $tarifsRepository = null,
        ?EventInscriptionDatesRepository $inscriptionDatesRepository = null
    ) {
        $this->eventsRepository = $eventsRepository ?? new EventsRepository();
        $this->piscinesRepository = $piscinesRepository ?? new PiscinesRepository();
        $this->tarifsRepository = $tarifsRepository ?? new TarifsRepository();
        $this->inscriptionDatesRepository = $inscriptionDatesRepository ?? new EventInscriptionDatesRepository();
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
    public function createEvent(array $data): int
    {
        // Hydrater un nouvel objet Events à partir des données
        $event = $this->hydrateEvent($data);

        // Ajouter les tarifs si présents
        if (!empty($data['tarifs']) && is_array($data['tarifs'])) {
            foreach ($data['tarifs'] as $tarifId) {
                $tarif = $this->tarifsRepository->findById((int)$tarifId);
                if ($tarif) {
                    $event->addTarif($tarif);
                }
            }
        }

        // Persister l'événement
        $eventId = $this->eventsRepository->insert($event);

        // Traiter les dates d'inscription si présentes
        if (!empty($data['inscription_dates']) && is_array($data['inscription_dates'])) {
            foreach ($data['inscription_dates'] as $dateData) {
                if (!empty($dateData['libelle']) && !empty($dateData['start_at']) && !empty($dateData['close_at'])) {
                    $inscriptionDate = new EventInscriptionDates();
                    $inscriptionDate->setEvent($eventId)
                        ->setLibelle($dateData['libelle'])
                        ->setStartRegistrationAt($dateData['start_at'])
                        ->setCloseRegistrationAt($dateData['close_at'])
                        ->setAccessCode($dateData['access_code'] ?? null);

                    $this->inscriptionDatesRepository->insert($inscriptionDate);
                }
            }
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
    public function updateEvent(Events $event, array $tarifs = [], array $inscriptionDates = []): bool
    {
        // Mettre à jour l'événement
        $result = $this->eventsRepository->update($event);

        // Gérer les tarifs
        $this->tarifsRepository->deleteEventTarifs($event->getId());
        foreach ($tarifs as $tarifId) {
            $tarif = $this->tarifsRepository->findById((int)$tarifId);
            if ($tarif) {
                $event->addTarif($tarif);
                $this->tarifsRepository->addEventTarif($event->getId(), (int)$tarifId);
            }
        }

        // Gérer les dates d'inscription
        $this->inscriptionDatesRepository->deleteByEventId($event->getId());
        foreach ($inscriptionDates as $inscriptionDate) {
            $inscriptionDate->setEvent($event->getId());
            $this->inscriptionDatesRepository->insert($inscriptionDate);
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
            ->setOpeningDoorsAt($data['opening_doors_at'] ?? date('Y-m-d H:i:s'))
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

        // Charger l'événement associé
        if ($event->getAssociateEvent()) {
            $associatedEvent = $this->eventsRepository->findById($event->getAssociateEvent());
            if ($associatedEvent) {
                $event->setAssociatedEvent($associatedEvent);
            }
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