<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationComplement;
use app\Models\Reservation\ReservationComplementTemp;
use app\Models\Reservation\ReservationDetailTemp;
use app\Models\Reservation\ReservationTemp;
use app\Models\Tarif\Tarif;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Reservation\ReservationComplementTempRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Utils\DurationHelper;
use app\Utils\FilesHelper;
use JsonSerializable;

class ReservationSessionService
{
    private ?ReservationTempRepository $reservationTempRepository;
    private ?ReservationDetailTempRepository $reservationDetailTempRepository;
    private ?ReservationComplementTempRepository $reservationComplementTempRepository;
    private ?PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository;
    private ?FilesHelper $filesHelper;

    public function __construct(
        ?ReservationTempRepository $reservationTempRepository = null,
        ?ReservationDetailTempRepository $reservationDetailTempRepository = null,
        ?ReservationComplementTempRepository $reservationComplementTempRepository = null,
        ?PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository = null,
        ?FilesHelper $filesHelper = null
    )
    {
        $this->reservationTempRepository = $reservationTempRepository;
        $this->reservationDetailTempRepository = $reservationDetailTempRepository;
        $this->reservationComplementTempRepository = $reservationComplementTempRepository;
        $this->piscineGradinsPlacesRepository = $piscineGradinsPlacesRepository;
        $this->filesHelper = $filesHelper;
    }

    /**
     * Efface complètement la session de réservation en cours et initialise une nouvelle session vide.
     */
    public function clearReservationSession(): void
    {
        $_SESSION['reservation'] = $this->getDefaultReservationStructure();
        $this->reservationTempRepository->deleteBySession(session_id());
    }

    /**
     * Retourne la structure par défaut pour une session de réservation.
     *
     * @return array
     */
    public function getDefaultReservationStructure(): array
    {
        return [
            'event_id' => null,
            'event_session_id' => null,
            'reservation_temp_id' => null,
            'swimmer_id' => null,
            'limit_per_swimmer' => null,
            'access_code_used' => null,
            'booker' => [
                'name'    => $_SESSION['booker']['name'] ?? null,
                'firstname' => $_SESSION['booker']['firstname'] ?? null,
                'email'  => $_SESSION['booker']['email'] ?? null,
                'phone'  => $_SESSION['booker']['phone'] ?? null,
            ],
            'reservation_detail' => [],
            'reservation_complement' => [],
            'last_activity' => time(),
        ];
    }

    /**
     * Récupère les données de la session de réservation en cours.
     *
     * @return array{
     *   reservation: ReservationTemp|null,
     *   reservation_details: array<int,ReservationDetailTemp>|null,
     *   reservation_complements: array<int,ReservationComplementTemp>|null
     * }
     */
    public function getReservationTempSession(): array
    {
        $sessionId = session_id();

        // Avant de récupérer la session en cours, on nettoie toutes les réservations temporaires expirées
        $this->clearExpiredReservations();

        $session = ['reservation' => null, 'reservation_details' => null, 'reservation_complements' => null];
        //On va chercher les infos dans les tables _temp à l'aide de session_id()

        // On récupère la session en cours dans la table
        $reservationTemp = $this->getReservationTempRepository()->findBySessionId($sessionId);
        if (!$reservationTemp) {
            return $session;
        }
        $session['reservation'] = $reservationTemp;

        // On va ensuite chercher s'il y a des places assises réservées
        $reservationDetails = $this->getReservationDetailTempRepository()->findByFields(['reservation_temp' => $reservationTemp->getId()]);
        $session['reservation_details'] = $reservationDetails; // peut être []

        // On va ensuite chercher s'il y a des compléments réservés
        $reservationComplements = $this->getReservationComplementTempRepository()->findByReservationTemp($reservationTemp->getId());
        $session['reservation_complements'] = $reservationComplements; // peut être []

        return $session;
    }


    /**
     * Récupère les données de la session de réservation en cours.
     * @return array|null
     */
    public function getReservationSession(): ?array
    {
        return $_SESSION['reservation'] ?? null;
    }

    /**
     * Met à jour une valeur spécifique dans la session de réservation.
     * @param string|array $key
     * @param mixed $value
     */
    public function setReservationSession(string|array $key, mixed $value): void
    {
        if (is_array($key)) {
            $ref = &$_SESSION['reservation'];
            foreach ($key as $k) {
                $ref = &$ref[$k];
            }
            // Sérialise récursivement la valeur pour s'assurer qu'aucun objet n'est stocké en session.
            $ref = $this->recursiveSerialize($value);
        } else {
            $_SESSION['reservation'][$key] = $this->recursiveSerialize($value);
        }
        // Met à jour le timestamp à chaque modification
        $_SESSION['reservation']['last_activity'] = time();
    }


    /**
     * Parcourt récursivement une valeur (tableau ou objet) et convertit tous les objets
     * implémentant JsonSerializable en leur représentation de tableau.
     *
     * @param mixed $data
     * @return mixed
     */
    private function recursiveSerialize(mixed $data): mixed
    {
        if ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (is_array($data)) {
            return array_map([$this, 'recursiveSerialize'], $data);
        }

        return $data;
    }


    /**
     * TTL de session de réservation en secondes, dérivé de TIMEOUT_SESSION (ISO 8601, ex PT20M).
     * Fallback à 1800s si non défini ou invalide.
     *
     * @return int
     */
    public function getReservationTimeoutDuration(): int
    {
        $isoDuration = defined('TIMEOUT_PLACE_RESERV') ? TIMEOUT_PLACE_RESERV : 'PT30M';
        $seconds = DurationHelper::iso8601ToSeconds($isoDuration);

        return (is_int($seconds) && $seconds > 0) ? $seconds : 1800;
    }


    /**
     * Vérifie si la session de réservation est expirée
     *
     * @param array $session
     * @return bool
     */
    public function isReservationSessionExpired(array $session): bool
    {
        $last = (int)($session['last_activity'] ?? 0);
        if ($last <= 0) {
            return true;
        }
        $ttl = $this->getReservationTimeoutDuration();
        return (time() - $last) > $ttl;
    }

    /**
     * Supprime toutes les réservations temporaires expirées de la base de données.
     * Le timeout est défini par la constante TIMEOUT_PLACE_RESERV.
     */
    private function clearExpiredReservations(): void
    {
        $timeoutSeconds = $this->getReservationTimeoutDuration();
        $expiredReservations = $this->getReservationTempRepository()->findExpired($timeoutSeconds);

        if (empty($expiredReservations)) {
            return;
        }

        $reservationIds = array_map(fn($r) => $r->getId(), $expiredReservations);

        // Récupérer les détails associés pour trouver les fichiers à supprimer
        $detailsToDelete = $this->getReservationDetailTempRepository()->findByFields(['reservation_temp' => $reservationIds]);
        $detailIdsToDelete = array_map(fn($d) => $d->getId(), $detailsToDelete);

        if (!empty($detailIdsToDelete)) {
            $proofFilesToDelete = $this->getReservationDetailTempRepository()->findJustificatifNamesByIds($detailIdsToDelete);
            foreach ($proofFilesToDelete as $fileName) {
                $this->getFilesHelper()->deleteFile(UPLOAD_PROOF_PATH . 'temp/', $fileName);
            }
        }

        // Supprimer les enregistrements de la base de données
        $this->getReservationTempRepository()->deleteByIds($reservationIds);
    }

    /**
     * Calcule les quantités de chaque "pack" de tarifs à partir des détails de réservation de places.
     *
     * @param ReservationDetailTemp[]|null $reservationDetails
     * @param Tarif[] $allEventTarifs
     * @return array
     */
    public function getTarifQuantitiesFromDetails(?array $reservationDetails, array $allEventTarifs): array
    {
        if (empty($reservationDetails)) {
            return [];
        }

        // Compter le nombre de places réservées pour chaque tarif_id
        $tarifIds = [];
        foreach ($reservationDetails as $detail) {
            $tarifIds[] = $detail->getTarif();
        }
        $placesPerTarifId = array_count_values($tarifIds);

        // Convertir le nombre de places en nombre de "packs" (quantité de tarifs)
        $tarifQuantities = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifId = $tarif->getId();
            $nbPlacesInTarif = $tarif->getSeatCount();

            // On ne traite que les tarifs qui ont des places, qui sont dans la réservation,
            // et qui n'ont PAS de code d'accès (car les tarifs spéciaux sont gérés séparément dans la vue).
            if ($tarif->getAccessCode() === null && isset($placesPerTarifId[$tarifId])) {
                // Pour un pack de 4 places, si on a 8 réservations, ça fait 2 packs.
                // On évite la division par zéro ou par null.
                if ($nbPlacesInTarif > 0) {
                    $tarifQuantities[$tarifId] = (int)($placesPerTarifId[$tarifId] / $nbPlacesInTarif);
                }
            }
        }
        return $tarifQuantities;
    }

    /**
     * Calcule les quantités pour chaque tarif de type "complément".
     *
     * @param ReservationComplementTemp[]|null $reservationComplements
     * @return array Un tableau [tarif_id => quantite]
     */
    public function getComplementQuantities(?array $reservationComplements): array
    {
        if (empty($reservationComplements)) {
            return [];
        }

        $quantities = [];
        foreach ($reservationComplements as $complement) {
            $quantities[$complement->getTarif()] = $complement->getQty();
        }
        return $quantities;
    }

    /**
     * Pour préparer reservation_detail de $_SESSION en tableau prêt, en regroupant les packs et indexé par tarif_id
     *
     * @param ReservationDetailTemp[] $reservationDetails
     * @param array $tarifsById
     * @return array
     */
    public function prepareSessionReservationDetailToView(array $reservationDetails, array $tarifsById): array
    {
        $details = [];

        foreach ($reservationDetails as $detail) {
            $tarifId = $detail->getTarif() ?? 0;
            if ($tarifId <= 0 || !isset($tarifsById[$tarifId])) {
                // Incohérence d'entrée : on ignore la ligne invalide pour ne pas générer d'erreur en vue
                continue;
            }

            $tarif = $tarifsById[$tarifId];

            if (!isset($details[$tarifId])) {
                $details[$tarifId] = [
                    'tarif_name'  => $tarif->getName(),
                    'description' => $tarif->getDescription(),
                    'price'       => $tarif->getPrice(),
                ];
            }

            // On empile les participants sous index numériques (attendu par le résumé)
            $details[$tarifId][] = $detail;
        }

        return $details;
    }

    /**
     * Méthode lazy pour instancier le repository ReservationTemp uniquement si nécessaire
     * @return ReservationTempRepository
     */
    private function getReservationTempRepository(): ReservationTempRepository
    {
        if ($this->reservationTempRepository === null) {
            $this->reservationTempRepository = new ReservationTempRepository();
        }
        return $this->reservationTempRepository;
    }

    /**
     * Méthode lazy pour instancier le repository uniquement si nécessaire
     * @return ReservationDetailTempRepository
     */
    private function getReservationDetailTempRepository(): ReservationDetailTempRepository
    {
        if ($this->reservationDetailTempRepository === null) {
            $this->reservationDetailTempRepository = new ReservationDetailTempRepository();
        }
        return $this->reservationDetailTempRepository;
    }

    /**
     * Méthode lazy pour instancier le repository uniquement si nécessaire
     * @return ReservationComplementTempRepository
     */
    private function getReservationComplementTempRepository(): ReservationComplementTempRepository
    {
        if ($this->reservationComplementTempRepository === null) {
            $this->reservationComplementTempRepository = new ReservationComplementTempRepository();
        }
        return $this->reservationComplementTempRepository;
    }

    /**
     * Méthode lazy pour instancier le repository uniquement si nécessaire
     * @return PiscineGradinsPlacesRepository
     */
    private function getPiscineGradinsPlacesRepository(): PiscineGradinsPlacesRepository
    {
        if ($this->piscineGradinsPlacesRepository === null) {
            $this->piscineGradinsPlacesRepository = new PiscineGradinsPlacesRepository();
        }
        return $this->piscineGradinsPlacesRepository;
    }

    /**
     * Méthode lazy pour instancier le helper de fichiers uniquement si nécessaire.
     * @return FilesHelper
     */
    private function getFilesHelper(): FilesHelper
    {
        if ($this->filesHelper === null) {
            $this->filesHelper = new FilesHelper();
        }
        return $this->filesHelper;
    }

}