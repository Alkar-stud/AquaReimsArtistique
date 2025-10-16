<?php
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
     * @param bool $withEvent
     * @param bool $withEventSession
     * @param bool $withEventInscriptionDates
     * @param bool $withChildren
     * @return Reservation[]
     */
    public function findAll(
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withEventInscriptionDates = false,
        bool $withChildren = false
    ): array {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $rows = $this->query($sql);

        $list = array_map([$this, 'hydrate'], $rows);
        foreach ($list as $r) {
            $this->hydrateOptionalRelations($r, $withEvent, $withEventSession, $withEventInscriptionDates);
        }

        return $withChildren ? $this->hydrateRelations($list) : $list;
    }

    /**
     * Recherche et retourne une réservation par son ID.
     *
     * @param int  $id L'ID de la réservation.
     * @param bool $withEvent Si true, hydrate l'objet Event associé.
     * @param bool $withEventSession Si true, hydrate l'objet EventSession associé.
     * @param bool $withEventInscriptionDates Si true, hydrate les dates d'inscription de l'événement.
     * @param bool $withChildren Si true, hydrate les relations enfants (détails, compléments, paiements, mails envoyés).
     * @return Reservation|null La réservation trouvée, ou null si elle n'existe pas.
     */
    public function findById(
        int $id,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withEventInscriptionDates = false,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession, $withEventInscriptionDates);

        if ($withChildren) {
            return $this->hydrateRelations([$r])[0];
        }
        return $r;
    }

    /**
     * Retourne une réservation par un champ défini
     * @param string $field
     * @param string|int $fieldValue
     * @param bool $withEvent Si true, hydrate l'objet Event associé.
     * @param bool $withEventSession Si true, hydrate l'objet EventSession associé.
     * @param bool $withEventInscriptionDates
     * @param bool $withChildren Si true, hydrate les relations enfants (détails, compléments, paiements).
     * @return Reservation|null La réservation trouvée, ou null si elle n'existe pas.
     */
    public function findByField(
        string $field,
        string|int $fieldValue,
        bool $withEvent = false,
        bool $withEventSession = false,
        bool $withEventInscriptionDates = true,
        bool $withChildren = true
    ): ?Reservation {
        $sql = "SELECT * FROM $this->tableName WHERE " . $field . " = :" . $field . ";";
        $rows = $this->query($sql, [$field => $fieldValue]);
        if (!$rows) return null;

        $r = $this->hydrate($rows[0]);
        $this->hydrateOptionalRelations($r, $withEvent, $withEventSession, $withEventInscriptionDates);
        return $withChildren ? $this->hydrateRelations([$r])[0] : $r;
    }

    /**
     * Retourne toutes les réservations actives d'une session
     * @param int $sessionId
     * @param int|null $limit
     * @param int|null $offset
     * @param bool $withEvent
     * @param bool $withChildren
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
     * Recherche toutes les réservations par email et évènement,
     * puis hydrate chaque enregistrement exactement comme `findById`.
     *
     * Les options variadiques `$with` doivent suivre le même ordre que celles de `findById`
     * (par exemple : withEvent, withEventSession, withSwimmer, withDetails, withComplements, withPayments, ...).
     * @param string $email
     * @param int $eventId
     * @param bool ...$with
     * @return array
     */
    public function findByEmailAndEvent(string $email, int $eventId, bool ...$with): array
    {
        $sql = "SELECT id FROM $this->tableName WHERE email = :email AND event = :eventId";
        $rows = $this->query($sql, ['email' => $email, 'eventId' => $eventId]);

        $reservations = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            // Délègue à findById pour bénéficier des mêmes options d'hydratation
            $reservations[] = $this->findById($id, ...$with);
        }

        // Filtre les éventuels null renvoyés si un id n'existe plus
        return array_values(array_filter($reservations));
    }

    /**
     * Pour trouver une réservation par son token
     * @param string $token
     * @param bool $withEvent
     * @param bool $withEventSession
     * @param bool $withChildren
     * @return Reservation|null
     */

    /**
     * Ajoute une réservation
     * @return int ID inséré
     */
    public function insert(Reservation $reservation): int
    {
        $sql = "INSERT INTO $this->tableName
            (event, event_session, reservation_temp_id, name, firstname, email, phone, swimmer_if_limitation,
             total_amount, total_amount_paid, token, token_expire_at, comments, created_at)
            VALUES (:event, :event_session, :reservation_temp_id, :name, :firstname, :email, :phone, :swimmer_if_limitation,
             :total_amount, :total_amount_paid, :token, :token_expire_at, :comments, :created_at)";

        $ok = $this->execute($sql, [
            'event' => $reservation->getEvent(),
            'event_session' => $reservation->getEventSession(),
            'reservation_temp_id' => $reservation->getReservationTempId(),
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
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function update(Reservation $reservation): bool
    {
        $sql = "UPDATE $this->tableName SET
            event = :event,
            event_session = :event_session,
            reservation_temp_id = :reservation_temp_id,
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
     * Met à jour un champ unique
     * @param int $id
     * @param string $field
     * @param string|null $value
     * @return bool
     */
    public function updateSingleField(int $id, string $field, ?string $value): bool
    {
        $allowed = ['name', 'firstname', 'email', 'phone', 'total_amount', 'total_amount_paid', 'is_canceled'];
        if (!in_array($field, $allowed, true)) return false;

        if ($field === 'phone' && $value === '') $value = null;

        $sql = "UPDATE $this->tableName SET `$field` = :value, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'value' => $value]);
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

        $complementsRepository = new ReservationComplementRepository();
        $allComplements = $complementsRepository->findByReservationIds($reservationIds, false, true);
        $complementsByReservationId = [];
        foreach ($allComplements as $complement) {
            $complementsByReservationId[$complement->getReservation()][] = $complement;
        }

        $paymentsRepository = new ReservationPaymentRepository();
        $allPayments = $paymentsRepository->findByReservations($reservationIds);
        $paymentsByReservationId = [];
        foreach ($allPayments as $payment) {
            $paymentsByReservationId[$payment->getReservation()][] = $payment;
        }

        $mailSentRepository = new ReservationMailSentRepository();
        $allMailSent = $mailSentRepository->findByReservations($reservationIds);
        $mailSentByReservationId = [];
        foreach ($allMailSent as $mailSent) {
            $mailSentByReservationId[$mailSent->getReservation()][] = $mailSent;
        }

        foreach ($reservations as $reservation) {
            $reservation->setDetails($detailsByReservationId[$reservation->getId()] ?? []);
            $reservation->setComplements($complementsByReservationId[$reservation->getId()] ?? []);
            $reservation->setPayments($paymentsByReservationId[$reservation->getId()] ?? []);
            $reservation->setMailSent($mailSentByReservationId[$reservation->getId()] ?? []);
        }

        return $reservations;
    }

    /**
     * Vérifie si une session a au moins une réservation active.
     * C'est mieux que de compter toutes les réservations.
     * @param int $sessionId
     * @return bool
     */
    public function hasReservationsForSession(int $sessionId): bool
    {
        $sql = "SELECT 1 FROM $this->tableName WHERE event_session = :sessionId AND is_canceled = 0 LIMIT 1";
        $rows = $this->query($sql, ['sessionId' => $sessionId]);

        // Si la requête retourne au moins une ligne, cela signifie qu'il y a des réservations.
        return !empty($rows);
    }

    /**
     * On fait pareil pour vérifier s'il y a des réservations active pour un event
     * @param int $eventId
     * @return bool
     */
    public function hasReservations(int $eventId): bool
    {
        $sql = "SELECT 1 FROM $this->tableName WHERE event = :event AND is_canceled = 0 LIMIT 1;";
        $rows = $this->query($sql, ['event' => $eventId]);

        // Si la requête retourne au moins une ligne, cela signifie qu'il y a des réservations.
        return !empty($rows);
    }

    /**
     * Retourne un tableau des réservations annulées d'un event
     *
     * @param int $eventId
     * @return array
     */
    public function findCanceledByEvent(int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :event AND is_canceled = 1";
        $rows = $this->query($sql, ['event' => $eventId]);
        return array_map([$this, 'hydrate'], $rows);
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
     * @param bool $withEventInscriptionDates
     * @return void
     */
    private function hydrateOptionalRelations(
        Reservation $r,
        bool $withEvent,
        bool $withEventSession,
        bool $withEventInscriptionDates = false
    ): void
    {
        // Sessions
        if ($withEventSession) {
            $sessionRepo = new EventSessionRepository();
            $session = $sessionRepo->findById($r->getEventSession());
            if ($session) {
                $r->setEventSessionObject($session);
            }
        }

        // Événement (et éventuellement ses dates d'inscription)
        if ($withEvent || $withEventInscriptionDates) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById(
                $r->getEvent(),
                true,                  // withPiscine
                false,                 // withSessions
                $withEventInscriptionDates // withInscriptionDates
            );
            if ($event) {
                $r->setEventObject($event);
            }
        }
    }
}
