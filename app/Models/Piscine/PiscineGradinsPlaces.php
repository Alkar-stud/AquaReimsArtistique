<?php

namespace app\Models\Piscine;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class PiscineGradinsPlaces
{
    private int $id;
    private int $zone; // ID de la zone
    private ?PiscineGradinsZones $zoneObject = null; // Objet PiscineGradinsZones lié
    private string $rankInZone;
    private int $place_number;
    private bool $is_pmr = false;
    private bool $is_vip = false;
    private bool $is_volunteer = false;
    private bool $is_open = true;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getZone(): int
    {
        return $this->zone;
    }

    public function getZoneObject(): ?PiscineGradinsZones
    {
        return $this->zoneObject;
    }

    public function getRankInZone(): string
    {
        return $this->rankInZone;
    }

    public function getPlaceNumber(): int
    {
        return $this->place_number;
    }

    /*
     * Pour récupérer le nom/numéro complet de la place
     */
    public function getFullPlaceName(): string
    {
        $zoneName = $this->getZoneObject() ? $this->getZoneObject()->getZoneName() : $this->getZone();
        return sprintf('%s%s%02d', $zoneName, $this->getRankInZone(), $this->getPlaceNumber());
    }

    public function isPmr(): bool
    {
        return $this->is_pmr;
    }

    public function isVip(): bool
    {
        return $this->is_vip;
    }

    public function isVolunteer(): bool
    {
        return $this->is_volunteer;
    }

    public function isOpen(): bool
    {
        return $this->is_open;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    // --- SETTERS ---

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setZone(int $zone): self
    {
        $this->zone = $zone;
        return $this;
    }

    public function setZoneObject(?PiscineGradinsZones $zoneObject): self
    {
        $this->zoneObject = $zoneObject;
        if ($zoneObject) {
            $this->zone = $zoneObject->getId();
        }
        return $this;
    }

    public function setRankInZone(string $rankInZone): self
    {
        $this->rankInZone = $rankInZone;
        return $this;
    }

    public function setPlaceNumber(int $place_number): self
    {
        $this->place_number = $place_number;
        return $this;
    }

    public function setIsPmr(bool $is_pmr): self
    {
        $this->is_pmr = $is_pmr;
        return $this;
    }

    public function setIsVip(bool $is_vip): self
    {
        $this->is_vip = $is_vip;
        return $this;
    }

    public function setIsVolunteer(bool $is_volunteer): self
    {
        $this->is_volunteer = $is_volunteer;
        return $this;
    }

    public function setIsOpen(bool $is_open): self
    {
        $this->is_open = $is_open;
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }
}