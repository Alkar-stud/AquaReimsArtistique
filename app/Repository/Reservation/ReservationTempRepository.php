<?php
namespace app\Repository\Reservation;

use app\Core\Paginator;
use app\Models\Reservation\Reservation;
use app\Repository\AbstractRepository;
use app\Repository\Event\EventRepository;
use app\Models\Reservation\ReservationTemp;
use app\Repository\Swimmer\SwimmerRepository;
use DateTime;

class ReservationTempRepository extends AbstractRepository
{
    private ?EventRepository $eventRepository;

    public function __construct(?EventRepository $eventRepository = null)
    {
        parent::__construct('reservation_temp');
        $this->eventRepository = $eventRepository;
    }

    /**
     * Méthode lazy pour instancier le repository Event uniquement si nécessaire.
     * @return EventRepository
     */
    private function getEventRepository(): EventRepository
    {
        if ($this->eventRepository === null) {
            $this->eventRepository = new EventRepository();
        }
        return $this->eventRepository;
    }

    /**
     * Récupère une réservation temporaire par son identifiant.
     *
     * @param int $id Identifiant de la réservation temporaire.
     * @return ReservationTemp|null Instance mappée si trouvée, sinon null.
     */
    public function findById(int $id): ?ReservationTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
        if (empty($rows)) return null;

        $reservation = $this->hydrate($rows[0]);
        $this->hydrateRelations([$reservation]);
        return $reservation;
    }

    /**
     * Récupère la première réservation temporaire correspondant à un identifiant de session.
     *
     * @param string $sessionId Identifiant de session (session_id).
     * @return ReservationTemp|null Instance mappée si trouvée, sinon null.
     */
    public function findBySessionId(string $sessionId): ?ReservationTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE session_id = :session_id LIMIT 1", ['session_id' => $sessionId]);
        if (empty($rows)) return null;

        $reservation = $this->hydrate($rows[0]);
        $this->hydrateRelations([$reservation]);
        return $reservation;
    }

    /**
     * Retourne toutes les réservations actives d'une session
     * @param int $sessionId
     * @param int|null $limit
     * @param int|null $offset
     * @param bool $withEvent
     * @param bool $withChildren
     * @param string|null $sortOrder
     * @return Reservation[]
     */
    /**
     * Retourne toutes les réservations temporaires d'une session, éventuellement paginées.
     *
     * @param int $sessionId
     * @param int|null $limit
     * @param int|null $offset
     * @param bool $withEvent
     * @param string|null $sortOrder
     * @return ReservationTemp[]
     */
    public function findBySession(
        int $sessionId,
        ?int $limit = null,
        ?int $offset = null,
        bool $withEvent = false,
        ?string $sortOrder = null,
    ): array {
        $sql = "SELECT * FROM {$this->tableName} WHERE event_session = :sessionId";

        match ($sortOrder) {
            'IDreservation'   => $sql .= " ORDER BY id",
            'NomReservation'  => $sql .= " ORDER BY name, firstname",
            default           => $sql .= " ORDER BY created_at",
        };

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $rows = $this->query($sql, ['sessionId' => $sessionId]);
        if (empty($rows)) {
            return [];
        }

        $list = array_map([$this, 'hydrate'], $rows);

        if ($withEvent) {
            $this->hydrateRelations($list);
        }

        return $list;
    }

    /**
     * Version paginée, même style que ReservationRepository::findBySessionPaginated
     *
     * @param int $sessionId
     * @param int $currentPage
     * @param int $itemsPerPage
     * @return Paginator
     */
    public function findBySessionPaginated(int $sessionId, int $currentPage, int $itemsPerPage): Paginator
    {
        if ($sessionId <= 0) {
            return new Paginator([], 0, $itemsPerPage, $currentPage);
        }

        $totalItems = $this->countBySession($sessionId);

        $offset = ($currentPage - 1) * $itemsPerPage;

        // On hydrate systématiquement l'Event pour rester cohérent avec l'affichage
        $items = $this->findBySession($sessionId, $itemsPerPage, $offset, true);

        return new Paginator($items, $totalItems, $itemsPerPage, $currentPage);
    }


    /**
     * Compte le nombre total de réservations pour une session donnée.
     *
     * @param int $sessionId
     * @return int
     */
    public function countBySession(int $sessionId): int
    {
        if ($sessionId <= 0) {
            return 0;
        }
        $sql = "SELECT COUNT(id) as total FROM $this->tableName WHERE event_session = :sessionId";

        $result = $this->query($sql, ['sessionId' => $sessionId]);

        return $result[0]['total'] ?? 0;
    }


    /**
     * Compte les résultats d’une recherche par texte.
     */
    public function countBySearch(string $searchQuery): int
    {
        $params = [];
        $where = $this->buildSearchWhere($searchQuery, $params);

        $sql = "SELECT COUNT(id) AS total FROM $this->tableName WHERE $where";
        $result = $this->query($sql, $params);

        return (int)($result[0]['total'] ?? 0);
    }

    /** Helper privé pour construire le WHERE et les paramètres de la recherche texte
     *
     * @param string $searchQuery
     * @param array $params
     * @return string
     */
    private function buildSearchWhere(string $searchQuery, array &$params): string
    {
        $clauses = [];

        $q = '%' . trim($searchQuery) . '%';
        $params['q_id'] = $q;
        $params['q_name'] = $q;
        $params['q_firstname'] = $q;
        $params['q_email'] = $q;

        // CAST(id AS CHAR) pour permettre le LIKE sur l'id numérique
        $clauses[] = '(CAST(id AS CHAR) LIKE :q_id OR name LIKE :q_name OR firstname LIKE :q_firstname OR email LIKE :q_email)';

        return implode(' AND ', $clauses);
    }

    /**
     * Version paginée, même style que findBySessionPaginated.
     */
    public function findBySearchPaginated(
        string $searchQuery,
        int $currentPage,
        int $itemsPerPage,
        ?bool $isCanceled = null,
        ?bool $isChecked = null
    ): Paginator {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return new Paginator([], 0, $itemsPerPage, $currentPage);
        }

        $totalItems = $this->countBySearch($searchQuery);
        $offset = ($currentPage - 1) * $itemsPerPage;
        $items = $this->findBySearch($searchQuery, $itemsPerPage, $offset, false, true);

        return new Paginator($items, $totalItems, $itemsPerPage, $currentPage);
    }


    /**
     * Recherche par texte (LIKE) sur id, name, firstname, email, avec filtres facultatifs.
     * Retourne une liste (non paginée).
     */
    public function findBySearch(
        string $searchQuery,
        ?int $limit = null,
        ?int $offset = null,
        bool $withEvent = false,
        bool $withChildren = true,
        ?string $sortOrder = null
    ): array {
        $params = [];
        $where = $this->buildSearchWhere($searchQuery, $params);

        $sql = "SELECT * FROM $this->tableName WHERE $where";
        match ($sortOrder) {
            'IDreservation' => $sql .= " ORDER BY id",
            'NomReservation' => $sql .= " ORDER BY name, firstname",
            default => $sql .= " ORDER BY created_at DESC",
        };

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $rows = $this->query($sql, $params);
        if (empty($rows)) return [];

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateRelations($r, $withEvent, true, false, true);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Insère une nouvelle réservation temporaire en base.
     *
     * Le modèle doit contenir les valeurs nécessaires. Les dates sont formatées
     * avant insertion. En cas de succès, l'identifiant auto-incrémenté est affecté
     * au modèle via setId.
     *
     * @param ReservationTemp $m Modèle à insérer.
     * @return bool True si l'insertion a réussi, false sinon.
     */
    public function insert(ReservationTemp $m): bool
    {
        $sql = "INSERT INTO {$this->tableName}
            (event, event_session, session_id, name, firstname, email, phone, swimmer_if_limitation, access_code, created_at)
            VALUES (:event, :event_session, :session_id, :name, :firstname, :email, :phone, :swimmer_if_limitation, :access_code, :created_at)";
        $params = [
            'event' => $m->getEvent(),
            'event_session' => $m->getEventSession(),
            'session_id' => $m->getSessionId(),
            'name' => $m->getName(),
            'firstname' => $m->getFirstName(),
            'email' => $m->getEmail(),
            'phone' => $m->getPhone(),
            'swimmer_if_limitation' => $m->getSwimmerId(),
            'access_code' => $m->getAccessCode(),
            'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
        $ok = $this->execute($sql, $params);
        if ($ok) {
            $m->setId($this->getLastInsertId());
        }
        return $ok;
    }


    /**
     * Update l'objet
     *
     * @param ReservationTemp $reservationTemp
     * @return bool
     */
    public function update(ReservationTemp $reservationTemp): bool
    {
        $params = [
            'event' => $reservationTemp->getEvent(),
            'event_session' => $reservationTemp->getEventSession(),
            'session_id' => $reservationTemp->getSessionId(),
            'name' => $reservationTemp->getName(),
            'firstname' => $reservationTemp->getFirstName(),
            'email' => $reservationTemp->getEmail(),
            'phone' => $reservationTemp->getPhone(),
            'swimmer_if_limitation' => $reservationTemp->getSwimmerId(),
            'access_code' => $reservationTemp->getAccessCode(),
            'is_locked' => $reservationTemp->isLocked() === true ? 1 : 0,
        ];
        return $this->updateById($reservationTemp->getId(), $params);
    }

    /**
     * Pour supprimer tous les éléments par session_id
     *
     * @param string $sessionId
     * @return bool
     */
    public function deleteBySession(string $sessionId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE session_id = :session_id";
        return $this->execute($sql, ['session_id' => $sessionId]);
    }

    /**
     * Trouve les réservations temporaires expirées.
     *
     * @param int $timeoutSeconds Le timeout en secondes.
     * @return ReservationTemp[]
     */
    public function findExpired(int $timeoutSeconds): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE created_at < NOW() - INTERVAL :seconds SECOND AND is_locked = 0";
        $rows = $this->query($sql, ['seconds' => $timeoutSeconds]);

        if (empty($rows)) {
            return [];
        }

        $reservations = array_map(fn($row) => $this->hydrate($row), $rows);
        $this->hydrateRelations($reservations);
        return $reservations;
    }

    /**
     * Supprime une liste de réservations temporaires par leurs IDs.
     *
     * @param int[] $ids
     * @return bool
     */
    public function deleteByIds(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM {$this->tableName} WHERE id IN ($placeholders)";

        return $this->execute($sql, $ids);
    }

    /**
     * Convertit une ligne de résultat SQL en instance de ReservationTemp.
     *
     * Gère les conversions de types pour les champs numériques et les dates.
     *
     * @param array $row Ligne associative provenant de la base de données.
     * @return ReservationTemp Modèle peuplé à partir de la ligne fournie.
     */
    protected function hydrate(array $row): ReservationTemp
    {
        $m = new ReservationTemp();
        $m->setId((int)$row['id']);
        $m->setEvent((int)$row['event']);
        $m->setEventSession((int)$row['event_session']);
        $m->setSessionId($row['session_id']);
        $m->setName($row['name']);
        $m->setFirstName($row['firstname']);
        $m->setEmail($row['email']);
        $m->setPhone($row['phone']);
        $m->setSwimmerId($row['swimmer_if_limitation'] !== null ? (int)$row['swimmer_if_limitation'] : null);
        $m->setAccessCode($row['access_code']);
        $m->setIsLocked($row['is_locked']);
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }

    /**
     * Hydrate les relations pour une ou plusieurs réservations temporaires.
     *
     * @param array|ReservationTemp $reservations
     * @return array|ReservationTemp
     */
    public function hydrateRelations(array|ReservationTemp $reservations): array|ReservationTemp
    {
        $single = $reservations instanceof ReservationTemp;
        $list = $single ? [$reservations] : $reservations;

        if (empty($list)) {
            return $single ? $reservations : [];
        }

        // Récupération des ids
        $reservationIds = array_map(fn(ReservationTemp $r) => $r->getId(), $list);

        // Détails
        $detailsRepo = new ReservationDetailTempRepository();
        $allDetails = $detailsRepo->findByReservations($reservationIds, true, true, true);
        $detailsByReservationId = [];
        foreach ($allDetails as $detail) {
            // Si un élément inattendu est null, on l'ignore
            if ($detail === null) {
                continue;
            }

            $reservationTempId = $detail->getReservationTemp();
            if ($reservationTempId === null) {
                continue;
            }

            $detailsByReservationId[$reservationTempId][] = $detail;
        }

        // Compléments
        $complementsRepo = new ReservationComplementTempRepository();
        $allComplements = $complementsRepo->findByReservationIds($reservationIds, false, true);
        $complementsByReservationId = [];
        foreach ($allComplements as $complement) {
            $complementsByReservationId[$complement->getReservationTemp()][] = $complement;
        }

        // Hydratation du Swimmer
        $swimmerRepo = new SwimmerRepository();
        foreach ($list as $reservation) {
            $reservation->setDetails($detailsByReservationId[$reservation->getId()] ?? []);
            $reservation->setComplements($complementsByReservationId[$reservation->getId()] ?? []);

            $swimmerId = $reservation->getSwimmerId();
            if ($swimmerId !== null) {
                $reservation->setSwimmer($swimmerRepo->findById((int)$swimmerId));
            } else {
                $reservation->setSwimmer(null);
            }
        }

        return $single ? $list[0] : $list;
    }
}
