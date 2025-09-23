<?php
namespace app\Models\Swimmer;

use app\Models\AbstractModel;

class Swimmer extends AbstractModel
{
    private string $name;
    private ?int $group = null; // FK swimmer_group.id
    private ?SwimmerGroup $groupObject = null;

    // --- GETTERS ---
    public function getName(): string { return $this->name; }
    public function getGroup(): ?int { return $this->group; }
    public function getGroupObject(): ?SwimmerGroup { return $this->groupObject; }

    // --- SETTERS ---
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setGroup(?int $group): self { $this->group = $group; return $this; }
    public function setGroupObject(?SwimmerGroup $group): self {
        $this->groupObject = $group;
        if ($group) { $this->group = $group->getId(); }
        return $this;
    }
}
