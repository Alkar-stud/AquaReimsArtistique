<?php

namespace app\Services\Tarif;

use app\Repository\Event\EventTarifRepository;
use app\Repository\Tarif\TarifRepository;

class TarifService
{
    private TarifRepository $tarifsRepository;
    private EventTarifRepository $eventTarifRepository;

    public function __construct(
        TarifRepository $tarifsRepository,
        EventTarifRepository $eventTarifRepository,
    )
    {
        $this->tarifsRepository = $tarifsRepository;
        $this->eventTarifRepository = $eventTarifRepository;
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
     * @return array ['success' => bool, 'error' => ?string, 'tarif' => ?array]
     */
    public function validateSpecialCode(int $eventId, string $code): array
    {
        if (!$eventId || empty($code)) {
            return ['success' => false, 'error' => 'Paramètres manquants.'];
        }

        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        foreach ($tarifs as $tarif) {
            if ($tarif->getAccessCode() && strcasecmp($tarif->getAccessCode(), $code) === 0) {
                // Code trouvé et valide
                return [
                    'success' => true,
                    'tarif' => [
                        'id' => $tarif->getId(),
                        'name' => $tarif->getName(),
                        'description' => $tarif->getDescription(),
                        'seat_count' => $tarif->getSeatCount(),
                        'price' => $tarif->getPrice()
                    ]
                ];
            }
        }

        return ['success' => false, 'error' => 'Code invalide ou non reconnu.'];
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
     * @param array $allTarifsWithSeatForThisEvent
     * @param array $session
     * @return array|null
     */
    public function getAllTarifAndPrepareViewWithSpecialCode(array $allTarifsWithSeatForThisEvent, array $session): ?array
    {
        $specialTarifSession = null;
        $details = $session['reservation_detail'] ?? [];

        if (is_array($details) && !empty($details)) {
            foreach ($details as $d) {
                $code = is_object($d) ? ($d->tarif_access_code ?? null) : ($d['tarif_access_code'] ?? null);
                $tarifId = (int)(is_object($d) ? ($d->tarif_id ?? 0) : ($d['tarif_id'] ?? 0));
                if (!$code || $tarifId <= 0) {
                    continue;
                }
                foreach ($allTarifsWithSeatForThisEvent as $tarif) {
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



}