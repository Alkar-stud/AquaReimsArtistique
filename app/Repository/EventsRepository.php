<?php

namespace app\Repository;

use app\Models\Events;
use DateMalformedStringException;

class EventsRepository extends AbstractRepository
{
    private PiscinesRepository $piscinesRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $inscriptionDatesRepository;

    public function __construct(
        ?PiscinesRepository $piscinesRepository = null,
        ?TarifsRepository $tarifsRepository = null,
        ?EventInscriptionDatesRepository $inscriptionDatesRepository = null
    ) {
        parent::__construct('events');
        $this->piscinesRepository = $piscinesRepository ?? new PiscinesRepository();
        $this->tarifsRepository = $tarifsRepository ?? new TarifsRepository();
        $this->inscriptionDatesRepository = $inscriptionDatesRepository ?? new EventInscriptionDatesRepository();
    }


        /**
     * Trouve tous les événements
     * @return Events[]
     * @throws DateMalformedStringException
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY `event_start_at` DESC, `libelle`;";
        $results = $this->query($sql);

        // Convertir chaque ligne en objet Events
        $events = [];
        foreach ($results as $result) {
            $event = $this->hydrate($result);

            // Charger les relations pour chaque événement
            $this->loadPiscine($event);
            $this->loadAssociatedEvent($event); // Appel sur l'objet hydraté
            $this->loadTarifs($event);
            $this->loadInscriptionDates($event);

            $events[] = $event;
        }

        return $events;
    }


    /**
     * Trouve un événement par son ID
     * @param int $id
     * @return Events|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Events
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        $event = $this->hydrate($result[0]);

        // Chargement des relations
        $this->loadPiscine($event);
        $this->loadAssociatedEvent($event);
        $this->loadTarifs($event);
        $this->loadInscriptionDates($event);

        return $event;
    }

    /**
     * Trouve tous les événements à venir
     * @return Events[]
     */
    public function findSortByDate($upComing = true): array
    {
        $sql = "SELECT * FROM $this->tableName 
                WHERE event_start_at " . ($upComing === true ? ">" : "<=") . " NOW()
                ORDER BY event_start_at ASC";
        $results = $this->query($sql);

        // Convertir chaque ligne en objet Events
        $events = [];
        foreach ($results as $result) {
            $event = $this->hydrate($result);

            // Charger les relations pour chaque événement
            $this->loadPiscine($event);
            $this->loadAssociatedEvent($event); // Appel sur l'objet hydraté
            $this->loadTarifs($event);
            $this->loadInscriptionDates($event);

            $events[] = $event;
        }

        return $events;
    }

    /**
     * Insère un nouvel événement
     * @param Events $event
     * @return int ID de l'événement inséré
     */
    public function insert(Events $event): int
    {
        $sql = "INSERT INTO $this->tableName 
            (libelle, lieu, opening_doors_at, event_start_at, associate_event, limitation_per_swimmer, created_at)
            VALUES (:libelle, :lieu, :opening_doors_at, :event_start_at, :associate_event, :limitation_per_swimmer, :created_at)";

        $this->execute($sql, [
            'libelle' => $event->getLibelle(),
            'lieu' => $event->getLieu(),
            'opening_doors_at' => $event->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $event->getEventStartAt()->format('Y-m-d H:i:s'),
            'associate_event' => $event->getAssociateEvent(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer(),
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        $eventId = $this->getLastInsertId();
        $event->setId($eventId);

        // Gestion de la réciprocité
        if ($event->getAssociateEvent()) {
            $this->manageReciprocityAssociation($eventId, $event->getAssociateEvent());
        }

        // Sauvegarde des relations
        $this->saveTarifs($event);

        return $eventId;
    }

    /**
     * Met à jour un événement et gère la désassociation réciproque
     * @param Events $event L'événement à mettre à jour
     * @return bool Succès de la mise à jour
     * @throws DateMalformedStringException
     */
    public function update(Events $event): bool
    {
        // Récupérer l'état actuel de l'événement pour vérifier si l'association a changé
        $currentEvent = $this->findById($event->getId());
        $oldAssociateEventId = $currentEvent ? $currentEvent->getAssociateEvent() : null;
        $newAssociateEventId = $event->getAssociateEvent();

        // Mise à jour de l'événement
        $sql = "UPDATE $this->tableName SET 
        libelle = :libelle,
        lieu = :lieu,
        opening_doors_at = :opening_doors_at,
        event_start_at = :event_start_at,
        associate_event = :associate_event,
        limitation_per_swimmer = :limitation_per_swimmer,
        updated_at = NOW()
        WHERE id = :id";

        $result = $this->execute($sql, [
            'id' => $event->getId(),
            'libelle' => $event->getLibelle(),
            'lieu' => $event->getLieu(),
            'opening_doors_at' => $event->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $event->getEventStartAt()->format('Y-m-d H:i:s'),
            'associate_event' => $event->getAssociateEvent(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer()
        ]);

        // Gérer la désassociation réciproque
        if ($oldAssociateEventId !== $newAssociateEventId) {
            // Si l'ancien événement associé existe, le désassocier de cet événement
            if ($oldAssociateEventId) {
                $this->removeEventAssociation($oldAssociateEventId, $event->getId());
            }

            // Si un nouvel événement est associé, établir l'association réciproque
            if ($newAssociateEventId) {
                $this->ensureEventAssociation($newAssociateEventId, $event->getId());
            }
        }

        // Mise à jour des relations
        $this->saveTarifs($event);

        return $result;
    }

    /**
     * Supprime un événement par son ID
     * @param int $id
     * @return bool
     */
    /**
     * Supprime un événement et gère la désassociation réciproque
     * @param int $id ID de l'événement à supprimer
     * @return bool Succès de la suppression
     */
    public function delete(int $id): bool
    {
        // Récupérer l'événement à supprimer
        $event = $this->findById($id);
        if (!$event) {
            return false; // L'événement n'existe pas
        }

        // Vérifier si l'événement a un événement associé
        $associatedEventId = $event->getAssociateEvent();
        if ($associatedEventId) {
            // Supprimer l'association réciproque
            $sql = "UPDATE $this->tableName SET associate_event = NULL, updated_at = NOW() WHERE id = :id";
            $this->execute($sql, ['id' => $associatedEventId]);
        }

        // Suppression des liens avec les tarifs
        $sql = "DELETE FROM events_tarifs WHERE event = :id";
        $this->execute($sql, ['id' => $id]);

        // Suppression des liens avec les dates d'inscriptions
        $sql = "DELETE FROM events_inscriptions_dates WHERE event = :id";
        $this->execute($sql, ['id' => $id]);

        // Supprimer l'événement
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Charge la piscine associée à l'événement
     * @param Events $event
     */
    private function loadPiscine(Events $event): void
    {
        $piscine = $this->piscinesRepository->findById($event->getLieu());
        if ($piscine) {
            $event->setPiscine($piscine);
        }
    }

    /**
     * Charge l'événement associé pour un événement unique
     * @param Events $event
     * @return void
     * @throws DateMalformedStringException
     */
    private function loadAssociatedEvent(Events $event): void
    {
        if (!$event->getAssociateEvent()) {
            return;
        }

        // Charger l'événement associé sans récursion
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $event->getAssociateEvent()]);

        if (!empty($result)) {
            // Hydratation basique sans charger les relations pour éviter la récursion
            $associatedEvent = $this->hydrate($result[0], true);
            $event->setAssociatedEvent($associatedEvent);
        }
    }

    /**
     * Charge les tarifs associés à l'événement
     * @param Events $event
     */
    private function loadTarifs(Events $event): void
    {
        $sql = "SELECT t.*, 
            CASE WHEN t.nb_place IS NULL THEN 0 ELSE 1 END AS has_places
            FROM tarifs t
            JOIN events_tarifs et ON t.id = et.tarif
            WHERE et.event = :event_id
            ORDER BY has_places DESC, t.libelle";

        $results = $this->query($sql, ['event_id' => $event->getId()]);

        // Variable pour suivre le changement de catégorie
        $previousHasPlaces = null;

        if ($results) {
            foreach ($results as $data) {
                // Si nous passons de tarifs avec places à tarifs sans places, insérer un tarif séparateur
                if ($previousHasPlaces !== null && $previousHasPlaces == 1 && $data['has_places'] == 0) {
                    // Créer un "tarif séparateur"
                    $separator = new \app\Models\Tarifs();
                    $separator->setId(-1)  // ID spécial pour identifier le séparateur
                    ->setLibelle('__SEPARATOR__');
                    $event->addTarif($separator);
                }

                $tarif = $this->tarifsRepository->hydrate($data);
                $event->addTarif($tarif);

                // Mémoriser l'état "has_places" pour la prochaine itération
                $previousHasPlaces = $data['has_places'];
            }
        }
    }

    /**
     * Charge les dates d'inscription associées à l'événement
     * @param Events $event
     */
    private function loadInscriptionDates(Events $event): void
    {
        $inscriptionDates = $this->inscriptionDatesRepository->findByEventId($event->getId());
        foreach ($inscriptionDates as $date) {
            $date->setEventObject($event);
            $event->addInscriptionDate($date);
        }
    }

    /**
     * Sauvegarde les tarifs associés à l'événement
     * @param Events $event
     */
    private function saveTarifs(Events $event): void
    {
        // Supprimer les associations existantes
        $sql = "DELETE FROM events_tarifs WHERE event = :event_id";
        $this->execute($sql, ['event_id' => $event->getId()]);

        // Ajouter les nouvelles associations
        if (!empty($event->getTarifs())) {
            $values = [];
            $params = [];

            foreach ($event->getTarifs() as $i => $tarif) {
                $values[] = "(:event_id_$i, :tarif_id_$i)";
                $params["event_id_$i"] = $event->getId();
                $params["tarif_id_$i"] = $tarif->getId();
            }

            $sql = "INSERT INTO events_tarifs (event, tarif) VALUES " . implode(', ', $values);
            $this->execute($sql, $params);
        }
    }

    /**
     * Hydrate un objet Events à partir d'un tableau de données
     * @param array $data
     * @return Events
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data, $isBasicHydrate = false): Events
    {
        $event = new Events();
        $event->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setLieu($data['lieu'])
            ->setOpeningDoorsAt($data['opening_doors_at'])
            ->setEventStartAt($data['event_start_at'])
            ->setLimitationPerSwimmer($data['limitation_per_swimmer'])
            ->setAssociateEvent($data['associate_event'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        //on hydrate sans recharger les relations
        if ($isBasicHydrate === false) {
            // Charger les tarifs associés
            $this->loadTarifs($event);

            // Charger les dates d'inscription
            $this->loadInscriptionDates($event);
        }

        return $event;
    }

    /**
     * Gère l'association réciproque entre événements
     * @param int $eventId ID de l'événement principal
     * @param int|null $associateEventId ID de l'événement associé
     */
    private function manageReciprocityAssociation(int $eventId, ?int $associateEventId): void
    {
        // Récupérer l'ancienne association pour cet événement
        $sql = "SELECT associate_event FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $eventId]);
        $oldAssociateId = $result[0]['associate_event'] ?? null;

        // Si l'ancien événement associé existe et n'est plus le même, supprimer la réciprocité
        if ($oldAssociateId && $oldAssociateId != $associateEventId) {
            // Vérifier que l'événement associé pointe bien vers celui-ci avant de le modifier
            $checkSql = "SELECT associate_event FROM $this->tableName WHERE id = :id";
            $checkResult = $this->query($checkSql, ['id' => $oldAssociateId]);
            if (!empty($checkResult) && $checkResult[0]['associate_event'] == $eventId) {
                $sql = "UPDATE $this->tableName SET associate_event = NULL, updated_at = NOW() 
                    WHERE id = :id";
                $this->execute($sql, ['id' => $oldAssociateId]);
            }
        }

        // Si un nouvel événement est associé, créer la réciprocité (seulement si nécessaire).
        if ($associateEventId) {
            // Vérifier d'abord si l'événement associé pointe déjà vers cet événement
            $checkSql = "SELECT associate_event FROM $this->tableName WHERE id = :id";
            $checkResult = $this->query($checkSql, ['id' => $associateEventId]);

            // Ne mettre à jour que si l'association n'existe pas déjà
            if (empty($checkResult) || $checkResult[0]['associate_event'] != $eventId) {
                $sql = "UPDATE $this->tableName SET associate_event = :event_id, updated_at = NOW()
                    WHERE id = :id";
                $this->execute($sql, ['id' => $associateEventId, 'event_id' => $eventId]);
            }
        }
    }


    /**
     * Supprime l'association d'un événement vers un autre
     * @param int $eventId ID de l'événement à modifier
     * @param int $associationToRemove ID de l'association à supprimer
     * @return bool
     */
    private function removeEventAssociation(int $eventId, int $associationToRemove): bool
    {
        $sql = "UPDATE $this->tableName SET associate_event = NULL, updated_at = NOW()
            WHERE id = :id AND associate_event = :associate_event";

        return $this->execute($sql, [
            'id' => $eventId,
            'associate_event' => $associationToRemove
        ]);
    }

    /**
     * S'assure qu'un événement est associé à un autre
     * @param int $eventId ID de l'événement à modifier
     * @param int $associationToAdd ID de l'association à ajouter
     * @return bool
     */
    private function ensureEventAssociation(int $eventId, int $associationToAdd): bool
    {
        $sql = "UPDATE $this->tableName SET associate_event = :associate_event, updated_at = NOW()
            WHERE id = :id";

        return $this->execute($sql, [
            'id' => $eventId,
            'associate_event' => $associationToAdd
        ]);
    }

}
