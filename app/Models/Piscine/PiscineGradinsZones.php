<?php
// php
namespace app\Models\Piscine;


use app\Models\AbstractModel;

class PiscineGradinsZones extends AbstractModel
{
    private int $piscine;
    private ?Piscine $piscineObject = null;
    private string $zone_name;
    private int $nb_seats_vertically;
    private int $nb_seats_horizontally;
    private bool $is_open = true;
    private bool $is_stairs_after = true;
    private ?string $comments = null;

    // Getters
    public function getPiscine(): int { return $this->piscine; }
    public function getPiscineObject(): ?Piscine { return $this->piscineObject; }
    public function getZoneName(): string { return $this->zone_name; }
    public function getNbSeatsVertically(): int { return $this->nb_seats_vertically; }
    public function getNbSeatsHorizontally(): int { return $this->nb_seats_horizontally; }
    public function isOpen(): bool { return $this->is_open; }
    public function isStairsAfter(): bool { return $this->is_stairs_after; }
    public function getComments(): ?string { return $this->comments; }

    // Setters
    public function setPiscine(int $piscine): self { $this->piscine = $piscine; return $this; }
    public function setPiscineObject(?Piscine $piscine): self {
        $this->piscineObject = $piscine;
        if ($piscine) { $this->piscine = $piscine->getId(); }
        return $this;
    }
    public function setZoneName(string $zone_name): self { $this->zone_name = $zone_name; return $this; }
    public function setNbSeatsVertically(int $v): self { $this->nb_seats_vertically = $v; return $this; }
    public function setNbSeatsHorizontally(int $h): self { $this->nb_seats_horizontally = $h; return $this; }
    public function setIsOpen(bool $is_open): self { $this->is_open = $is_open; return $this; }
    public function setIsStairsAfter(bool $is_stairs_after): self { $this->is_stairs_after = $is_stairs_after; return $this; }
    public function setComments(?string $comments): self { $this->comments = $comments; return $this; }

}
