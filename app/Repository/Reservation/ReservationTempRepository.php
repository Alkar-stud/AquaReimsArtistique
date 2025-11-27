<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Models\Reservation\ReservationTemp;
use DateTime;

class ReservationTempRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_temp');
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
        return $this->mapRowToModel($rows[0]);
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
        return $this->mapRowToModel($rows[0]);
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
     * Convertit une ligne de résultat SQL en instance de ReservationTemp.
     *
     * Gère les conversions de types pour les champs numériques et les dates.
     *
     * @param array $row Ligne associatif provenant de la base de données.
     * @return ReservationTemp Modèle peuplé à partir de la ligne fournie.
     */
    private function mapRowToModel(array $row): ReservationTemp
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
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }
}
