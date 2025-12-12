<?php

namespace app\Services\Piscine;

use app\Models\Piscine\Piscine;
use app\Models\Piscine\PiscineGradinsPlaces;
use app\Models\Piscine\PiscineGradinsZones;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;

readonly class SeatingPlanService
{
    private array $allowedAttributes;
    public function __construct(
        private PiscineGradinsZonesRepository  $piscineGradinsZonesRepository = new PiscineGradinsZonesRepository(),
        private PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository = new PiscineGradinsPlacesRepository(),
    ) {
        $this->allowedAttributes = ['is_pmr', 'is_vip', 'is_volunteer', 'is_open'];
    }

    /**
     * Retourne les zones d'une piscine (pour le sélecteur).
     */
    public function getZonesForPiscine(Piscine $piscine): array
    {
        return $this->piscineGradinsZonesRepository->findByPiscine($piscine);
    }

    /**
     * Construit le plan d'une zone:
     * - rows: liste des rangs avec leurs "cases" (siège existant ou vide).
     * - cols: nombre de colonnes de la zone (nb\_seats\_horizontally).
     *
     * @param PiscineGradinsZones $zone
     * @return array
     */
    public function getZonePlan(PiscineGradinsZones $zone): array
    {
        // Tous les sièges (ouverts et fermés), tri natif rang/numéro
        $places = $this->piscineGradinsPlacesRepository->findByFields(['zone' => $zone->getId()], true);

        // Groupement par rang -> numéro
        $byRow = [];
        foreach ($places as $p) {
            $rank = $p->getRankInZone();
            $num = self::toInt($p->getPlaceNumber());
            $byRow[$rank][$num] = $p;
        }

        // Tri des rangs par ordre "naturel" (A, B, C ou 1, 2, 10)
        uksort($byRow, 'strnatcasecmp');

        $cols = max(1, $zone->getNbSeatsHorizontally());
        $rows = [];
        $rowIndex = 0;

        foreach ($byRow as $rank => $seatMap) {
            $rowIndex++;
            ksort($seatMap, SORT_NUMERIC);

            $cells = [];
            for ($i = 1; $i <= $cols; $i++) {
                if (isset($seatMap[$i])) {
                    /** @var PiscineGradinsPlaces $pl */
                    $pl = $seatMap[$i];
                    $cells[] = [
                        'exists' => true,
                        'id' => $pl->getId(),
                        'label' => $pl->getShortPlaceName(),
                        'open' => $pl->isOpen(),
                        'pmr' => $pl->isPmr(),
                        'vip' => $pl->isVip(),
                        'volunteer' => $pl->isVolunteer(),
                        'classes' => self::seatCssClasses($pl),
                    ];
                } else {
                    // Case vide pour conserver l'alignement (entrée/absence de siège)
                    $cells[] = [
                        'exists' => false,
                        'classes' => 'seat seat-empty',
                    ];
                }
            }

            $rows[] = [
                'rank' => $rank,
                'index' => $rowIndex,
                'seats' => $cells,
            ];
        }

        $wantedRows = max(0, $zone->getNbSeatsVertically());
        while (count($rows) < $wantedRows) {
            $rowIndex++;
            $cells = [];
            for ($i = 1; $i <= $cols; $i++) {
                $cells[] = ['exists' => false, 'classes' => 'seat seat-empty'];
            }
            $rows[] = [
                'rank' => null, // rang inconnu/synthétique
                'index' => $rowIndex,
                'seats' => $cells,
            ];
        }

        return [
            'zone' => $this->piscineGradinsZonesRepository->toArray($zone),
            'rows' => $rows,
            'cols' => $cols,
        ];
    }

    /**
     * @param string $value
     * @return int
     */
    private static function toInt(string $value): int
    {
        // gère "01" -> 1, " 10 " -> 10
        return (int)preg_replace('/\D+/', '', $value) ?: 0;
    }

    /**
     * @param PiscineGradinsPlaces $p
     * @return string
     */
    private static function seatCssClasses(PiscineGradinsPlaces $p): string
    {
        $classes = ['seat'];
        if (!$p->isOpen()) $classes[] = 'seat-closed';
        if ($p->isPmr()) $classes[] = 'seat-pmr';
        if ($p->isVip()) $classes[] = 'seat-vip';
        if ($p->isVolunteer()) $classes[] = 'seat-vol';
        return implode(' ', $classes);
    }

    /**
     * Retourne true ou false avec un message après (in)validation des données
     *
     * @param int $seatId
     * @param string $attribute
     * @param bool $value
     * @return array
     */
    public function checkDataForUpdateAttributeSeat(int $seatId, string $attribute, bool $value): array
    {
        //L'ID doit être strictement supérieur à 0.
        if ($seatId <= 0) {
            return ['success' => false, 'message' => 'Ce siège n\'existe pas.'];
        }

        //On vérifie si l'attribut envoyé a le droit d'être modifié
        if (!in_array($attribute, $this->allowedAttributes, true)) {
            return ['success'  => false, 'message'  => "Modification non possible."];
        }
        return ['success' => true];
    }


    public function updateAttribute(int $seatId, string $attribute, bool $value): void
    {
        //On récupère l'objet
        $piscineGradinsPlace = $this->piscineGradinsPlacesRepository->findById($seatId);
        //On met à jour l'attribut demandé
        switch ($attribute) {
            case 'is_pmr':
                $piscineGradinsPlace->setIsPmr($value);
                break;
            case 'is_vip':
                $piscineGradinsPlace->setIsVip($value);
                break;
            case 'is_volunteer':
                $piscineGradinsPlace->setIsVolunteer($value);
                break;
            case 'is_open':
                // La checkbox 'Fermé' envoie `true` si cochée (place fermée), `false` si décochée (place ouverte).
                // La propriété `is_open` du modèle est l'inverse : `false` si fermée, `true` si ouverte.
                // Nous inversons donc la valeur reçue du frontend.
                $piscineGradinsPlace->setIsOpen(!$value);
                break;
        }
        //On persiste ensuite
        $this->piscineGradinsPlacesRepository->update($piscineGradinsPlace);
    }

}
