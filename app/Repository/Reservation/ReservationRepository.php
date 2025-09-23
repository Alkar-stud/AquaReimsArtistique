<?php
// PHP
namespace app\Repository\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\AbstractRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;

class ReservationRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation');
    }

    /**
     * Retourne toutes les réservations (DESC par date de création)
     * @return Reservation[]
     */
    public function findAll(
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = false
    ): array {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $rows = $this->query($sql);

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Retourne une réservation par son ID
     */
    public function findById(
        int $id,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);

        if ($withChildren) {
            return $this->hydrateRelations([$r])[0];
        }
        return $r;
    }

    /**
     * Retourne une réservation par son tempId
     */
    public function findByTempId(
        string $tempId,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE reservation_temp_id = :reservation_temp_id";
        $rows = $this->query($sql, ['reservation_temp_id' => $tempId]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        return $withChildren ? $this->hydrateRelations([$r])[0] : $r;
    }

    /**
     * Retourne une réservation par son UUID
     */
    public function findByUuid(
        string $uuid,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE uuid = :uuid";
        $rows = $this->query($sql, ['uuid' => $uuid]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        return $withChildren ? $this->hydrateRelations([$r])[0] : $r;
    }

    /**
     * Retourne une réservation par son token
     */
    public function findByToken(
        string $token,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE token = :token";
        $rows = $this->query($sql, ['token' => $token]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        return $withChildren ? $this->hydrateRelations([$r])[0] : $r;
    }

    /**
     * Retourne toutes les réservations d'un événement
     * @return Reservation[]
     */
    public function findByEvent(
        int $eventId,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = false
    ): array {
        $sql = "SELECT * FROM $this->tableName WHERE event = :eventId ORDER BY created_at DESC";
        $rows = $this->query($sql, ['eventId' => $eventId]);

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Retourne toutes les réservations actives d'un événement
     * @return Reservation[]
     */
    public function findActiveByEvent(
        int $eventId,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withChildren = false
    ): array {
        $sql = "SELECT * FROM $this->tableName WHERE event = :eventId AND is_canceled = 0 ORDER BY created_at DESC";
        $rows = $this->query($sql, ['eventId' => $eventId]);

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateOptionalRelations($r, $withEvent, $withEventSession);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Retourne toutes les réservations actives d'une session
     * @return Reservation[]
     */
    public function findActiveBySession(
        int $sessionId,
        ?int $limit = null,
        ?int $offset = null,
        bool $withEvent = false,
        bool $withChildren = true
    ): array {
        $sql = "SELECT * FROM $this->tableName WHERE event_session = :sessionId AND is_canceled = 0 ORDER BY created_at";
        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }
        $rows = $this->query($sql, ['sessionId' => $sessionId]);
        if (empty($rows)) return [];

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateOptionalRelations($r, $withEvent, true);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Retourne les réservations d'un événement par email (doublons potentiels)
     * @return Reservation[]
     */
    public function findByEmailAndEvent(string $email, int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE email = :email AND event = :eventId";
        $rows = $this->query($sql, ['email' => $email, 'eventId' => $eventId]);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Ajoute une réservation
     * @return int ID inséré
     */
    public function insert(Reservation $reservation): int
    {
        $sql = "INSERT INTO $this->tableName
            (event, event_session, reservation_temp_id, uuid, name, firstname, email, phone, swimmer_if_limitation,
             total_amount, total_amount_paid, token, token_expire_at, comments, created_at)
            VALUES (:event, :event_session, :reservation_temp_id, :uuid, :name, :firstname, :email, :phone, :swimmer_if_limitation,
             :total_amount, :total_amount_paid, :token, :token_expire_at, :comments, :created_at)";

        $ok = $this->execute($sql, [
            'event' => $reservation->getEvent(),
            'event_session' => $reservation->getEventSession(),
            'reservation_temp_id' => $reservation->getReservationTempId(),
            'uuid' => $reservation->getUuid(),
            'name' => $reservation->getName(),
            'firstname' => $reservation->getFirstName(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
            'swimmer_if_limitation' => $reservation->getSwimmerId(),
            'total_amount' => $reservation->getTotalAmount(),
            'total_amount_paid' => $reservation->getTotalAmountPaid(),
            'token' => $reservation->getToken(),
            'token_expire_at' => $reservation->getTokenExpireAt()->format('Y-m-d H:i:s'),
            'comments' => $reservation->getComments(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une réservation
     */
    public function update(Reservation $reservation): bool
    {
        $sql = "UPDATE $this->tableName SET
            event = :event,
            event_session = :event_session,
            reservation_temp_id = :reservation_temp_id,
            uuid = :uuid,
            name = :name,
            firstname = :firstname,
            email = :email,
            phone = :phone,
            swimmer_if_limitation = :swimmer_if_limitation,
            total_amount = :total_amount,
            total_amount_paid = :total_amount_paid,
            token = :token,
            token_expire_at = :token_expire_at,
            comments = :comments,
            updated_at = NOW()
            WHERE id = :id";

        return $this->execute($sql, [
            'id' => $reservation->getId(),
            'event' => $reservation->getEvent(),
            'event_session' => $reservation->getEventSession(),
            'reservation_temp_id' => $reservation->getReservationTempId(),
            'uuid' => $reservation->getUuid(),
            'name' => $reservation->getName(),
            'firstname' => $reservation->getFirstName(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
            'swimmer_if_limitation' => $reservation->getSwimmerId(),
            'total_amount' => $reservation->getTotalAmount(),
            'total_amount_paid' => $reservation->getTotalAmountPaid(),
            'token' => $reservation->getToken(),
            'token_expire_at' => $reservation->getTokenExpireAt()->format('Y-m-d H:i:s'),
            'comments' => $reservation->getComments(),
        ]);
    }

    /**
     * Met à jour un champ simple
     * @param int $id
     * @param string $field
     * @param string|null $value
     * @return bool
     */
    public function updateSingleField(int $id, string $field, ?string $value): bool
    {
        $allowed = ['name', 'firstname', 'email', 'phone', 'total_amount', 'total_amount_paid'];
        if (!in_array($field, $allowed, true)) return false;

        if ($field === 'phone' && $value === '') $value = null;

        $sql = "UPDATE $this->tableName SET `$field` = :value, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'value' => $value]);
    }

    /**
     * Annule une réservation par son ID
     * @param int $id
     * @param bool $is_canceled
     * @return bool
     */
    public function cancelById(int $id, bool $is_canceled = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_canceled = :is_canceled, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'is_canceled' => $is_canceled ? 1 : 0]);
    }

    /**
     * Annule une réservation par son UUID
     * @param string $token
     * @param bool $is_canceled
     * @return bool
     */
    public function cancelByToken(string $token, bool $is_canceled = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_canceled = :is_canceled, updated_at = NOW() WHERE token = :token";
        return $this->execute($sql, ['token' => $token, 'is_canceled' => $is_canceled ? 1 : 0]);
    }

    /**
     * Marque une réservation comme vérifiée (ou non))
     * @param int $id
     * @param bool $is_checked
     * @return bool
     */
    public function check(int $id, bool $is_checked = true): bool
    {
        $sql = "UPDATE $this->tableName SET is_checked = :is_checked, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'is_checked' => $is_checked ? 1 : 0]);
    }

    /**
     * Compte les places réservées par event et nageur (hors tarifs avec code d'accès)
     * @param int $eventId
     * @param int $swimmerId
     * @return int
     */
    public function countReservationsForSwimmer(int $eventId, int $swimmerId): int
    {
        $sql = "SELECT COUNT(*) as count
            FROM reservation_detail rd
            INNER JOIN reservation r ON rd.reservation = r.id
            INNER JOIN tarif t ON rd.tarif = t.id
            WHERE r.event = :eventId
              AND r.swimmer_if_limitation = :swimmerId
              AND r.is_canceled = 0
              AND t.access_code IS NULL";
        $row = $this->query($sql, ['eventId' => $eventId, 'swimmerId' => $swimmerId])[0] ?? ['count' => 0];
        return (int)$row['count'];
    }

    /**
     * Compte le nombre de réservations actives par event (option nageur)
     * @param int $eventId
     * @param int|null $swimmerId
     * @return int
     */
    public function countActiveReservationsForEvent(int $eventId, ?int $swimmerId = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE event = :eventId AND is_canceled = 0";
        $params = ['eventId' => $eventId];
        if ($swimmerId !== null) {
            $sql .= " AND swimmer_if_limitation = :swimmerId";
            $params['swimmerId'] = $swimmerId;
        }
        $row = $this->query($sql, $params)[0] ?? ['count' => 0];
        return (int)$row['count'];
    }

    /**
     * Hydrate détails, compléments et paiements en masse
     * @param Reservation[] $reservations
     * @return Reservation[]
     */
    private function hydrateRelations(array $reservations): array
    {
        if (empty($reservations)) return [];

        $reservationIds = array_map(fn(Reservation $r) => $r->getId(), $reservations);

        $detailsRepository = new ReservationDetailRepository();
        $allDetails = $detailsRepository->findByReservations($reservationIds, false, true, true);
        $detailsByReservationId = [];
        foreach ($allDetails as $detail) {
            $detailsByReservationId[$detail->getReservation()][] = $detail;
        }

        $complementsRepository = new ReservationsComplementsRepository();
        $allComplements = $complementsRepository->findByReservations($reservationIds);
        $complementsByReservationId = [];
        foreach ($allComplements as $complement) {
            $complementsByReservationId[$complement->getReservation()][] = $complement;
        }

        $paymentsRepository = new ReservationPaymentsRepository();
        $allPayments = $paymentsRepository->findByReservations($reservationIds);
        $paymentsByReservationId = [];
        foreach ($allPayments as $payment) {
            $paymentsByReservationId[$payment->getReservation()][] = $payment;
        }

        foreach ($reservations as $reservation) {
            $reservation->setDetails($detailsByReservationId[$reservation->getId()] ?? []);
            $reservation->setComplements($complementsByReservationId[$reservation->getId()] ?? []);
            $reservation->setPayments($paymentsByReservationId[$reservation->getId()] ?? []);
        }

        return $reservations;
    }

    /**
     * Hydrate une réservation depuis une ligne SQL.
     * @param array $data
     * @return Reservation
     */
    protected function hydrate(array $data): Reservation
    {
        $r = new Reservation();

        $r->setId((int)$data['id'])
            ->setUuid($data['uuid'] ?? null)
            ->setEvent((int)$data['event'])
            ->setEventSession((int)$data['event_session'])
            ->setReservationTempId($data['reservation_temp_id'] ?? null)
            ->setName($data['name'])
            ->setFirstName($data['firstname'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'] ?? null)
            ->setSwimmerId(isset($data['swimmer_if_limitation']) ? (int)$data['swimmer_if_limitation'] : null)
            ->setTotalAmount((int)$data['total_amount'])
            ->setTotalAmountPaid((int)$data['total_amount_paid'])
            ->setToken($data['token'] ?? null)
            ->setTokenExpireAt($data['token_expire_at'])
            ->setIsCanceled(!empty($data['is_canceled']))
            ->setIsChecked(!empty($data['is_checked'] ?? 0))
            ->setComments($data['comments'] ?? null);

        return $r;
    }

    /**
     * Relations optionnelles
     * @param Reservation $r
     * @param bool $withEvent
     * @param bool $withEventSession
     * @return void
     */
    private function hydrateOptionalRelations(Reservation $r, bool $withEvent, bool $withEventSession): void
    {
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById($r->getEvent());
            if ($event) { $r->setEventObject($event); }
        }
        if ($withEventSession) {
            $sessionRepo = new EventSessionRepository();
            $session = $sessionRepo->findById($r->getEventSession());
            if ($session) { $r->setEventSessionObject($session); }
        }
    }
}
