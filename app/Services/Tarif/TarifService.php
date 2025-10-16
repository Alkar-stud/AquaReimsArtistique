<?php

namespace app\Services\Tarif;

use app\Models\Tarif\Tarif;
use app\Repository\Tarif\TarifRepository;

class TarifService
{
    private TarifRepository $tarifRepository;


    public function __construct(
        TarifRepository $tarifRepository,
    )
    {
        $this->tarifRepository = $tarifRepository;
    }

    /**
     * Trouve le premier tarif spécial (avec un code d'accès) dans les détails d'une réservation.
     *
     * @param array $reservationDetails Les détails de la réservation (généralement depuis la session).
     * @param array $availableTarifs La liste de tous les tarifs disponibles pour l'événement.
     * @return array|null Un tableau avec les données du tarif spécial si trouvé, sinon null.
     */
    public function findSpecialTarifInDetails(array $reservationDetails, array $availableTarifs): ?array
    {
        // Crée une map des tarifs par ID pour une recherche efficace.
        $tarifsById = [];
        foreach ($availableTarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        foreach ($reservationDetails as $detail) {
            $tarifId = $detail['tarif_id'] ?? null;
            $tarif = $tarifId ? ($tarifsById[$tarifId] ?? null) : null;

            // Si on trouve un tarif qui a un code d'accès, on le retourne.
            if ($tarif && $tarif->getAccessCode()) {
                return [
                    'id' => $tarif->getId(),
                    'libelle' => $tarif->getLibelle(),
                    'description' => $tarif->getDescription(),
                    'nb_place' => $tarif->getNbPlace(),
                    'price' => $tarif->getPrice(),
                    'code' => $detail['access_code'] ?? ''
                ];
            }
        }

        return null;
    }


    /**
     * Valide un code d'accès pour un tarif spécial et retourne les informations du tarif.
     *
     * @param int $eventId
     * @param string $code
     * @param bool $withSeat
     * @return array ['success' => bool, 'error' => ?string, 'tarif' => ?array]
     */
    public function validateSpecialCode(int $eventId, string $code, bool $withSeat = true): array
    {
        if (!$eventId || empty($code)) {
            return ['success' => false, 'error' => 'Paramètres manquants.'];
        }

        $tarifs = $this->tarifRepository->findByEventId($eventId);
        $needle = mb_strtolower($code);
                $matches = array_values(array_filter($tarifs, function (Tarif $t) use ($needle, $withSeat) {
                        $tCode = $t->getAccessCode();
                        if (!$tCode || mb_strtolower(trim($tCode)) !== $needle) {
                                return false;
            }
            $hasSeats = ($t->getSeatCount() !== null && $t->getSeatCount() > 0);
            // withSeat=false => seulement seat_count null (pas de places), sinon seulement avec places
            return $withSeat ? $hasSeats : !$hasSeats;
        }));

                if (empty($matches)) {
                    return ['success' => false, 'error' => 'Code invalide pour ce type de tarif.'];
        }

        $t = $matches[0];
        return [
            'success' => true,
            'tarif' => [
                    'id' => $t->getId(),
                    'name' => $t->getName(),
                    'description' => $t->getDescription(),
                    'seat_count' => $t->getSeatCount(),
                    'price' => $t->getPrice(),
                ],
        ];

    }

    /**
     * Supprime tous les détails d'un tarif spécifique d'une liste de détails de réservation.
     *
     * @param array $reservationDetails Les détails actuels de la réservation.
     * @param int $tarifId L'ID du tarif à supprimer.
     * @return array Les détails de la réservation mis à jour.
     */
    public function removeTarifFromDetails(array $reservationDetails, int $tarifId): array
    {
        if (empty($reservationDetails) || !$tarifId) {
            return $reservationDetails;
        }

        $filteredDetails = array_filter($reservationDetails, fn($detail) => ($detail['tarif_id'] ?? null) != $tarifId);

        // Ré-indexer le tableau pour éviter les clés discontinues en JSON
        return array_values($filteredDetails);
    }

    /**
     * Construit le "pré-remplissage" s'il existe déjà un tarif avec code en session.
     *
     * @param array $allTarifsForThisEvent
     * @param array $session
     * @param string $dto Le texte du DTO attendu (detail ou complement)
     * @return array|null
     */
    public function getAllTarifAndPrepareViewWithSpecialCode(array $allTarifsForThisEvent, array $session, string $dto): ?array
    {
        $specialTarifSession = null;
        $details = $session[$dto] ?? [];

        if (is_array($details) && !empty($details)) {
            foreach ($details as $d) {
                $code = is_object($d) ? ($d->tarif_access_code ?? null) : ($d['tarif_access_code'] ?? null);
                $tarifId = (int)(is_object($d) ? ($d->tarif_id ?? 0) : ($d['tarif_id'] ?? 0));
                if (!$code || $tarifId <= 0) {
                    continue;
                }
                foreach ($allTarifsForThisEvent as $tarif) {
                    if ($tarif->getId() === $tarifId) {
                        $specialTarifSession = [
                            'id'         => $tarif->getId(),
                            'name'       => $tarif->getName(),
                            'description'=> $tarif->getDescription(),
                            'seat_count' => $tarif->getSeatCount(),
                            'price'      => $tarif->getPrice(),
                            'code'       => $code,
                        ];
                        break 2;
                    }
                }
            }
        }

        return $specialTarifSession ?: null;
    }

    /**
     * Retourne un tableau d'objet Tarif indexé par leur ID à partir de la session[reservation][reservation_detail]
     *
     * @param array $listTarifsEventsSelected
     * @return array
     */
    public function getIndexedTarifFromEvent(array $listTarifsEventsSelected): array
    {
        // Extraire les ids de tarif depuis $listTarifsEventsSelected
        $ids = [];
        foreach ($listTarifsEventsSelected as $row) {
            $id = is_array($row)
                ? ($row['tarif_id'] ?? null)
                : ($row->tarif_id ?? null);

            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if (empty($ids)) {
            return [];
        }

        // Dé-dupliquer
        $ids = array_values(array_unique($ids));

        // Charger les tarifs correspondants
        $tarifs = $this->tarifRepository->findByIds($ids);

        // Indexer par id
        $indexed = [];
        foreach ($tarifs as $tarif) {
            $indexed[$tarif->getId()] = $tarif;
        }

        return $indexed;
    }

    /**
     * Vérifie si un code d'accès est déjà utilisé dans le même type (avec/sans places).
     * - Insensible à la casse et aux espaces.
     * - Inclut les tarifs inactifs.
     * - Exclut l'ID courant si fourni.
     * @param string|null $code
     * @param bool $hasSeats
     * @param int|null $excludeId
     * @return bool
     */
    public function isAccessCodeDuplicateForSeatType(?string $code, bool $hasSeats, ?int $excludeId = null): bool
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return false; // pas de code => pas de contrôle
        }

        $count = $this->tarifRepository->countByAccessCodeAndSeatType($normalized, $hasSeats, $excludeId);
        return $count > 0;
    }

    /**
     * Normalise un code (trim + lower). Retourne null si vide.
     * @param string|null $code
     * @return string|null
     */
    private function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $trimmed = trim($code);
        if ($trimmed === '') {
            return null;
        }
        return mb_strtolower($trimmed);
    }


}