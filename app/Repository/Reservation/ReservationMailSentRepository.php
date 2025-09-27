<?php
namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationMailSent;
use app\Repository\AbstractRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationRepository as ResRepo;
use DateMalformedStringException;

class ReservationMailSentRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_mail_sent');
    }

    /**
     * Retourne tous les envois, triés du plus récent au plus ancien.
     *
     * @param bool $withReservation Hydrater la relation Reservation
     * @param bool $withTemplate Hydrater la relation template (si repo disponible)
     * @return ReservationMailSent[]
     * @throws DateMalformedStringException
     */
    public function findAll(bool $withReservation = false, bool $withTemplate = false): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY sent_at DESC";
        $rows = $this->query($sql);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTemplate);
    }

    /**
     * Trouve un envoi par ID.
     *
     * @param int $id
     * @param bool $withReservation
     * @param bool $withTemplate
     * @return ReservationMailSent|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id, bool $withReservation = false, bool $withTemplate = false): ?ReservationMailSent
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $m = $this->hydrate($rows[0]);
        return $this->hydrateRelations([$m], $withReservation, $withTemplate)[0];
    }

    /**
     * Retourne les envois pour une réservation.
     *
     * @param int $reservationId
     * @param bool $withReservation
     * @param bool $withTemplate
     * @return ReservationMailSent[]
     * @throws DateMalformedStringException
     */
    public function findByReservation(int $reservationId, bool $withReservation = false, bool $withTemplate = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY sent_at DESC";
        $rows = $this->query($sql, ['reservationId' => $reservationId]);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTemplate);
    }

    /**
     * Retourne les envois pour un template donné.
     *
     * @param int $templateId
     * @param bool $withReservation
     * @param bool $withTemplate
     * @return ReservationMailSent[]
     * @throws DateMalformedStringException
     */
    public function findByTemplate(int $templateId, bool $withReservation = false, bool $withTemplate = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE mail_template = :templateId ORDER BY sent_at DESC";
        $rows = $this->query($sql, ['templateId' => $templateId]);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTemplate);
    }

    /**
     * Vérifie si un mail pour un template a déjà été envoyé à une réservation.
     *
     * @param int $reservationId
     * @param int $templateId
     * @return bool
     */
    public function hasMailBeenSent(int $reservationId, int $templateId): bool
    {
        $sql = "SELECT COUNT(*) AS count
                FROM $this->tableName
                WHERE reservation = :reservationId AND mail_template = :templateId";
        $result = $this->query($sql, [
            'reservationId' => $reservationId,
            'templateId' => $templateId,
        ]);

        return (int)($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Compte le nombre d'envois pour une réservation et un template donné.
     *
     * @param int $reservationId
     * @param int $templateId
     * @return int
     */
    public function countSentMails(int $reservationId, int $templateId): int
    {
        $sql = "SELECT COUNT(*) AS count
                FROM $this->tableName
                WHERE reservation = :reservationId AND mail_template = :templateId";
        $result = $this->query($sql, [
            'reservationId' => $reservationId,
            'templateId' => $templateId,
        ]);

        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * Insère un nouvel envoi.
     *
     * @param ReservationMailSent $mailSent
     * @return int ID inséré (0 si échec)
     */
    public function insert(ReservationMailSent $mailSent): int
    {
        $sql = "INSERT INTO $this->tableName
                (reservation, mail_template, sent_at)
                VALUES (:reservation, :mail_template, :sent_at)";

        $ok = $this->execute($sql, [
            'reservation' => $mailSent->getReservation(),
            'mail_template' => $mailSent->getMailTemplate(),
            'sent_at' => $mailSent->getSentAt()->format('Y-m-d H:i:s'),
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Hydrate un objet depuis une ligne SQL (sans relations).
     *
     * @param array<string,mixed> $data
     * @return ReservationMailSent
     */
    protected function hydrate(array $data): ReservationMailSent
    {
        $m = new ReservationMailSent();
        $m->setId((int)$data['id'])
            ->setReservation((int)$data['reservation'])
            ->setMailTemplate((int)$data['mail_template'])
            ->setSentAt($data['sent_at']);

        return $m;
    }

    /**
     * Supprime tous les détails d'une réservation
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate les relations optionnelles en masse (évite N+1).
     *
     * @param ReservationMailSent[] $items
     * @param bool $withReservation
     * @param bool $withTemplate
     * @return ReservationMailSent[]
     * @throws DateMalformedStringException
     */
    private function hydrateRelations(array $items, bool $withReservation, bool $withTemplate): array
    {
        if (empty($items)) return [];

        // Collecte des IDs uniques
        $reservationIds = $withReservation
            ? array_values(array_unique(array_map(fn($i) => $i->getReservation(), $items)))
            : [];
        $templateIds = $withTemplate
            ? array_values(array_unique(array_map(fn($i) => $i->getMailTemplate(), $items)))
            : [];

        // Chargement des réservations
        $reservationsById = [];
        if ($withReservation && $reservationIds) {
            $resRepo = new ResRepo();
            foreach ($reservationIds as $rid) {
                $r = $resRepo->findById($rid, false, false, false);
                if ($r) $reservationsById[$rid] = $r;
            }
        }

        // Chargement des mail templates
        $templatesById = [];
        if ($withTemplate && $templateIds) {
            $tplRepo = new MailTemplateRepository();
            if (method_exists($tplRepo, 'findByIds')) {
                foreach ($tplRepo->findByIds($templateIds) as $t) {
                    $templatesById[$t->getId()] = $t;
                }
            } else {
                foreach ($templateIds as $tid) {
                    $t = $tplRepo->findById($tid);
                    if ($t) $templatesById[$tid] = $t;
                }
            }
        }

        // Attachement des relations
        foreach ($items as $i) {
            if ($withReservation && isset($reservationsById[$i->getReservation()])) {
                $i->setReservationObject($reservationsById[$i->getReservation()]);
            }
            if ($withTemplate && isset($templatesById[$i->getMailTemplate()])) {
                $i->setMailTemplateObject($templatesById[$i->getMailTemplate()]);
            }
        }

        return $items;
    }
}
