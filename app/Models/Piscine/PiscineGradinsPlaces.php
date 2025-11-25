<?php

namespace app\Models\Piscine;

use app\Models\AbstractModel;

class PiscineGradinsPlaces extends AbstractModel
{
    private int $zone; // ID de la zone
    private ?PiscineGradinsZones $zoneObject = null; // Objet PiscineGradinsZones lié
    private string $rank_in_zone;
    private string $place_number;
    private bool $is_pmr = false;
    private bool $is_vip = false;
    private bool $is_volunteer = false;
    private bool $is_open = true;

    // --- GETTERS ---
    public function getZone(): int { return $this->zone; }
    public function getZoneObject(): ?PiscineGradinsZones { return $this->zoneObject; }
    public function getRankInZone(): string { return $this->rank_in_zone; }
    public function getPlaceNumber(): string { return $this->place_number; }
    /*
     * Pour récupérer le nom/numéro complet de la place
     */
    public function getFullPlaceName(): string
    {
        $zoneName = $this->getZoneObject() ? $this->getZoneObject()->getZoneName() : $this->getZone();
        return sprintf('%s%s%02d', $zoneName, $this->getRankInZone(), $this->getPlaceNumber());
    }
    public function isPmr(): bool { return $this->is_pmr; }
    public function isVip(): bool { return $this->is_vip; }
    public function isVolunteer(): bool { return $this->is_volunteer; }
    public function isOpen(): bool { return $this->is_open; }

    // --- SETTERS ---
    public function setZone(int $zone): self { $this->zone = $zone; return $this; }
    public function setZoneObject(?PiscineGradinsZones $zoneObject): self
    {
        $this->zoneObject = $zoneObject;
        if ($zoneObject) {
            $this->zone = $zoneObject->getId();
        }
        return $this;
    }
    public function setRankInZone(string $rank_in_zone): self { $this->rank_in_zone = $rank_in_zone; return $this; }
    public function setPlaceNumber(string $place_number): self { $this->place_number = $place_number; return $this; }
    /**
     * Pour récupérer le nom court de la place (Rang + Numéro)
     * ex : 201 pour rang 2, place 1
     */
    public function getShortPlaceName(): string
    {
        return $this->getRankInZone() . $this->getPlaceNumber();
    }
    public function setIsPmr(bool $is_pmr): self { $this->is_pmr = $is_pmr; return $this; }
    public function setIsVip(bool $is_vip): self { $this->is_vip = $is_vip; return $this; }
    public function setIsVolunteer(bool $is_volunteer): self { $this->is_volunteer = $is_volunteer; return $this; }
    public function setIsOpen(bool $is_open): self { $this->is_open = $is_open; return $this; }

}
