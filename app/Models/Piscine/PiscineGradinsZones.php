<?php

namespace app\Models\Piscine;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class PiscineGradinsZones
{
    private int $id;
    private int $piscine; // ID de la piscine
    private ?Piscines $piscineObject = null; // Objet Piscines lié
    private string $zone_name;
    private int $nb_seats_vertically;
    private int $nb_seats_horizontally;
    private bool $is_open = true;
    private bool $is_stairs_after = true;
    private ?string $comments = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getPiscine(): int
    {
        return $this->piscine;
    }

    public function getPiscineObject(): ?Piscines
    {
        return $this->piscineObject;
    }

    public function getZoneName(): string
    {
        return $this->zone_name;
    }

    public function getNbSeatsVertically(): int
    {
        return $this->nb_seats_vertically;
    }

    public function getNbSeatsHorizontally(): int
    {
        return $this->nb_seats_horizontally;
    }

    public function isOpen(): bool
    {
        return $this->is_open;
    }

    public function isStairsAfter(): bool
    {
        return $this->is_stairs_after;
    }

    public function getComments(): string
    {
        return $this->comments;
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

    public function setPiscine(int $piscine): self
    {
        $this->piscine = $piscine;
        return $this;
    }

    public function setPiscineObject(?Piscines $piscineObject): self
    {
        $this->piscineObject = $piscineObject;
        if ($piscineObject) {
            $this->piscine = $piscineObject->getId();
        }
        return $this;
    }

    public function setZoneName(string $zone_name): self
    {
        $this->zone_name = $zone_name;
        return $this;
    }

    public function setNbSeatsVertically(int $nb_seats_vertically): self
    {
        $this->nb_seats_vertically = $nb_seats_vertically;
        return $this;
    }

    public function setNbSeatsHorizontally(int $nb_seats_horizontally): self
    {
        $this->nb_seats_horizontally = $nb_seats_horizontally;
        return $this;
    }

    public function setIsOpen(bool $is_open): self
    {
        $this->is_open = $is_open;
        return $this;
    }

    public function setIsStairsAfter(bool $is_stairs_after): self
    {
        $this->is_stairs_after = $is_stairs_after;
        return $this;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     * @throws \Exception
     */
    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException|\Exception
     */
    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }
}